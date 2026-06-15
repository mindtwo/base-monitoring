<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Data\CollectionResult;

/**
 * All installed npm packages with versions, parsed offline from
 * package-lock.json (v1–v3), npm-shrinkwrap.json, yarn.lock (classic and
 * berry formats) or pnpm-lock.yaml (v6 and v9 formats).
 */
final class NpmPackagesCollector extends AbstractCollector
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? (string) getcwd();
    }

    public function key(): string
    {
        return 'npm_packages';
    }

    public function supported(): bool
    {
        return $this->lockFile() !== null;
    }

    public function collect(): CollectionResult
    {
        $lockFile = $this->lockFile();

        if ($lockFile === null) {
            return CollectionResult::unsupported($this->key());
        }

        $contents = (string) file_get_contents($this->projectRoot.'/'.$lockFile);

        if (str_ends_with($lockFile, '.json')) {
            $packages = $this->parseNpmLock($contents);
        } elseif ($lockFile === 'pnpm-lock.yaml') {
            $packages = $this->parsePnpmLock($contents);
        } else {
            $packages = $this->parseYarnLock($contents);
        }

        if ($packages === null) {
            return CollectionResult::failed($this->key(), sprintf('Unable to parse %s.', $lockFile));
        }

        return CollectionResult::ok($this->key(), [
            'count' => count($packages),
            'lockfile' => $lockFile,
            'packages' => $packages,
        ]);
    }

    private function lockFile(): ?string
    {
        foreach (['package-lock.json', 'npm-shrinkwrap.json', 'yarn.lock', 'pnpm-lock.yaml'] as $candidate) {
            if (is_readable($this->projectRoot.'/'.$candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function parseNpmLock(string $contents): ?array
    {
        $lock = json_decode($contents, true);

        if (! is_array($lock)) {
            return null;
        }

        // Lockfile v2/v3: flat "packages" map keyed by install path.
        if (isset($lock['packages']) && is_array($lock['packages'])) {
            $found = [];
            $depths = [];

            foreach ($lock['packages'] as $path => $meta) {
                if (! is_string($path) || ! is_array($meta) || ! isset($meta['version'])) {
                    continue;
                }

                $position = strrpos($path, 'node_modules/');

                if ($position === false) {
                    continue; // The root project or a workspace link.
                }

                $name = substr($path, $position + strlen('node_modules/'));
                $depth = substr_count($path, 'node_modules/');

                if ($name === '') {
                    continue;
                }

                // Keep the shallowest (hoisted) occurrence of duplicated packages.
                if (isset($found[$name]) && $depths[$name] <= $depth) {
                    continue;
                }

                $found[$name] = [
                    'name' => $name,
                    'version' => (string) $meta['version'],
                    'dev' => (bool) ($meta['dev'] ?? false),
                ];
                $depths[$name] = $depth;
            }

            ksort($found, SORT_STRING);

            return array_values($found);
        }

        // Lockfile v1: nested "dependencies" tree.
        if (isset($lock['dependencies']) && is_array($lock['dependencies'])) {
            $found = [];
            $this->walkV1Dependencies($lock['dependencies'], $found);
            ksort($found, SORT_STRING);

            return array_values($found);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $dependencies
     * @param  array<string, array<string, mixed>>  $found
     */
    private function walkV1Dependencies(array $dependencies, array &$found): void
    {
        foreach ($dependencies as $name => $meta) {
            if (! is_string($name) || ! is_array($meta) || ! isset($meta['version'])) {
                continue;
            }

            if (! isset($found[$name])) {
                $found[$name] = [
                    'name' => $name,
                    'version' => (string) $meta['version'],
                    'dev' => (bool) ($meta['dev'] ?? false),
                ];
            }

            if (isset($meta['dependencies']) && is_array($meta['dependencies'])) {
                $this->walkV1Dependencies($meta['dependencies'], $found);
            }
        }
    }

    /**
     * Best-effort parser covering yarn classic ('pkg@^1.0:' blocks with
     * 'version "1.2.3"') and yarn berry ('"pkg@npm:^1.0":' with 'version: 1.2.3').
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseYarnLock(string $contents): array
    {
        $found = [];
        $currentName = null;

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            // Block header: not indented, ends with ":".
            if (preg_match('/^\S.*:\s*$/', $line) === 1) {
                $currentName = $this->yarnPackageName($line);

                continue;
            }

            if ($currentName === null) {
                continue;
            }

            if (preg_match('/^\s+version:?\s+"?([^"\s]+)"?\s*$/', $line, $matches) === 1) {
                if (! isset($found[$currentName])) {
                    $found[$currentName] = [
                        'name' => $currentName,
                        'version' => $matches[1],
                    ];
                }

                $currentName = null;
            }
        }

        ksort($found, SORT_STRING);

        return array_values($found);
    }

    /**
     * Best-effort parser for pnpm-lock.yaml. Package keys live in the
     * "packages:" section as "  /name@1.2.3(peer@x):" (v6) or
     * "  'name@1.2.3': " / "  name@1.2.3:" (v9); scoped names keep their "@".
     *
     * @return array<int, array<string, mixed>>
     */
    private function parsePnpmLock(string $contents): array
    {
        $found = [];
        $inPackages = false;

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            if (preg_match('/^(packages|snapshots):\s*$/', $line) === 1) {
                $inPackages = true;

                continue;
            }

            // A new top-level section ends the packages block.
            if ($inPackages && preg_match('/^\S/', $line) === 1) {
                $inPackages = false;
            }

            if (! $inPackages) {
                continue;
            }

            if (preg_match('/^ {2}[\'"]?\/?((?:@[^\/\s\'"]+\/)?[^@\s\'"]+)@(\d[^()\'":\s]*)/', $line, $matches) !== 1) {
                continue;
            }

            [, $name, $version] = $matches;

            if (! isset($found[$name])) {
                $found[$name] = ['name' => $name, 'version' => $version];
            }
        }

        ksort($found, SORT_STRING);

        return array_values($found);
    }

    /**
     * Extracts "pkg" from headers like:
     * pkg@^1.0, pkg@~2.0:  |  "@scope/pkg@^1.0":  |  "pkg@npm:^1.0, pkg@npm:^2.0":
     */
    private function yarnPackageName(string $header): ?string
    {
        $first = trim((string) explode(',', rtrim(trim($header), ':'))[0], " \t\"");

        if ($first === '' || $first === '__metadata') {
            return null;
        }

        $separator = strrpos($first, '@');

        if ($separator === false || $separator === 0) {
            return $separator === false ? $first : null;
        }

        return substr($first, 0, $separator);
    }
}
