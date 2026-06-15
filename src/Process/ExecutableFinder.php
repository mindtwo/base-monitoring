<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

/**
 * Locates binaries without shelling out: scans the PATH plus common system
 * directories (web server binaries often live in sbin directories that are
 * missing from PHP's PATH).
 */
final class ExecutableFinder
{
    private const FALLBACK_DIRECTORIES = [
        '/usr/local/sbin',
        '/usr/local/bin',
        '/usr/sbin',
        '/usr/bin',
        '/sbin',
        '/bin',
        '/opt/homebrew/bin',
        '/opt/homebrew/sbin',
    ];

    /** @var array<int, string> */
    private array $directories;

    /**
     * @param  array<int, string>|null  $fallbackDirectories
     */
    public function __construct(?string $path = null, ?array $fallbackDirectories = null)
    {
        $path ??= (string) getenv('PATH');

        $directories = array_merge(
            explode(PATH_SEPARATOR, $path),
            $fallbackDirectories ?? self::FALLBACK_DIRECTORIES
        );

        $this->directories = array_values(array_unique(array_filter(
            $directories,
            static fn (string $directory): bool => trim($directory) !== ''
        )));
    }

    public function find(string $binary): ?string
    {
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return $this->isExecutableFile($binary) ? $binary : null;
        }

        foreach ($this->directories as $directory) {
            foreach ($this->candidateNames($binary) as $name) {
                $candidate = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;

                if ($this->isExecutableFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function exists(string $binary): bool
    {
        return $this->find($binary) !== null;
    }

    /**
     * The directory containing the given binary, or null when it cannot be
     * located. Used to widen a spawned process's PATH so wrapper scripts can
     * resolve an interpreter they re-exec (composer → php, npm → node).
     */
    public function directoryOf(string $binary): ?string
    {
        $path = $this->find($binary);

        return $path !== null ? dirname($path) : null;
    }

    /**
     * The standard system bin directories scanned in addition to PATH. Exposed
     * so spawned processes can be given a PATH at least as wide as the finder's
     * own search (see ProcessEnvironment).
     *
     * @return array<int, string>
     */
    public static function fallbackDirectories(): array
    {
        return self::FALLBACK_DIRECTORIES;
    }

    /**
     * @return array<int, string>
     */
    private function candidateNames(string $binary): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [$binary];
        }

        return [$binary.'.exe', $binary.'.bat', $binary.'.cmd', $binary];
    }

    private function isExecutableFile(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }
}
