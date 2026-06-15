<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * Security advisories for the installed Composer packages via
 * `composer audit --format=json`. A non-zero exit code simply means advisories
 * were found — the JSON on stdout is parsed either way.
 */
final class ComposerAuditCollector extends AbstractCollector
{
    public const DEFAULT_TIMEOUT_SECONDS = 60;

    private ProcessRunner $processRunner;

    private ExecutableFinder $executables;

    private string $projectRoot;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?string $projectRoot = null,
        ?ExecutableFinder $executables = null,
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->executables = $executables ?? new ExecutableFinder;
        $this->projectRoot = $projectRoot ?? (string) getcwd();
    }

    public function key(): string
    {
        return 'composer_audit';
    }

    public function supported(): bool
    {
        return $this->processRunner->available()
            && is_readable($this->projectRoot.'/composer.lock')
            && $this->executables->exists('composer');
    }

    public function collect(): CollectionResult
    {
        $composer = $this->executables->find('composer');

        if ($composer === null) {
            return CollectionResult::unsupported($this->key());
        }

        $result = $this->processRunner->run([
            $composer,
            'audit',
            '--format=json',
            '--no-interaction',
            '--working-dir='.$this->projectRoot,
        ], $this->timeoutSeconds);

        if ($result->timedOut) {
            return CollectionResult::failed($this->key(), 'composer audit timed out.');
        }

        $decoded = json_decode($result->output, true);

        if (! is_array($decoded)) {
            return CollectionResult::failed($this->key(), sprintf(
                'composer audit produced no parsable JSON: %s',
                $this->excerpt($result->errorOutput !== '' ? $result->errorOutput : $result->output)
            ));
        }

        $advisories = $this->flattenAdvisories($decoded['advisories'] ?? []);
        $abandoned = $this->normalizeAbandoned($decoded['abandoned'] ?? []);

        $data = [
            'advisories_count' => count($advisories),
            'advisories' => $advisories,
            'abandoned_count' => count($abandoned),
            'abandoned' => $abandoned,
        ];

        return $advisories === []
            ? CollectionResult::ok($this->key(), $data)
            : CollectionResult::warning($this->key(), $data);
    }

    /**
     * @param  mixed  $advisories
     * @return array<int, array<string, mixed>>
     */
    private function flattenAdvisories($advisories): array
    {
        if (! is_array($advisories)) {
            return [];
        }

        $flattened = [];

        foreach ($advisories as $package => $entries) {
            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $flattened[] = [
                    'package' => (string) ($entry['packageName'] ?? $package),
                    'severity' => $entry['severity'] ?? null,
                    'cve' => $entry['cve'] ?? null,
                    'title' => $entry['title'] ?? null,
                    'affected_versions' => $entry['affectedVersions'] ?? null,
                    'link' => $entry['link'] ?? null,
                ];
            }
        }

        return $flattened;
    }

    /**
     * @param  mixed  $abandoned
     * @return array<string, string|null> package => suggested replacement
     */
    private function normalizeAbandoned($abandoned): array
    {
        if (! is_array($abandoned)) {
            return [];
        }

        $normalized = [];

        foreach ($abandoned as $package => $replacement) {
            if (is_string($package)) {
                $normalized[$package] = is_string($replacement) ? $replacement : null;
            }
        }

        return $normalized;
    }
}
