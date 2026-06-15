<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use JsonException;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * All installed Composer packages with versions, parsed offline from
 * composer.lock. Packages matching a known technology slug are tagged so the
 * dashboard can join them against end-of-life data.
 */
final class ComposerPackagesCollector extends AbstractCollector
{
    private string $projectRoot;

    private TechnologyResolver $technologies;

    public function __construct(
        ?string $projectRoot = null,
        ?TechnologyResolver $technologies = null
    ) {
        $this->projectRoot = $projectRoot ?? (string) getcwd();
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'composer_packages';
    }

    public function supported(): bool
    {
        return is_readable($this->lockFile());
    }

    public function collect(): CollectionResult
    {
        try {
            $lock = json_decode((string) file_get_contents($this->lockFile()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return CollectionResult::failed($this->key(), 'Unable to parse composer.lock: '.$exception->getMessage());
        }

        if (! is_array($lock)) {
            return CollectionResult::failed($this->key(), 'Malformed composer.lock.');
        }

        $direct = $this->directRequirements();
        $packages = [];

        foreach (['packages' => false, 'packages-dev' => true] as $section => $dev) {
            $entries = $lock[$section] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! isset($entry['name'], $entry['version'])) {
                    continue;
                }

                $name = (string) $entry['name'];

                $package = [
                    'name' => $name,
                    'version' => ltrim((string) $entry['version'], 'v'),
                    'dev' => $dev,
                    'direct' => isset($direct[$name]),
                ];

                $technology = $this->technologies->resolve($name);

                if ($technology->isKnown()) {
                    $package['technology'] = $technology->slug;
                }

                $packages[] = $package;
            }
        }

        return CollectionResult::ok($this->key(), [
            'count' => count($packages),
            'packages' => $packages,
        ]);
    }

    private function lockFile(): string
    {
        return $this->projectRoot.'/composer.lock';
    }

    /**
     * Names listed in composer.json require/require-dev, to flag direct
     * dependencies vs. transitive ones.
     *
     * @return array<string, true>
     */
    private function directRequirements(): array
    {
        $manifest = $this->projectRoot.'/composer.json';

        if (! is_readable($manifest)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        if (! is_array($decoded)) {
            return [];
        }

        $names = [];

        foreach (['require', 'require-dev'] as $section) {
            if (! isset($decoded[$section]) || ! is_array($decoded[$section])) {
                continue;
            }

            foreach (array_keys($decoded[$section]) as $name) {
                if (is_string($name) && str_contains($name, '/')) {
                    $names[$name] = true;
                }
            }
        }

        return $names;
    }
}
