<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * Vulnerability counts for the installed npm packages via `npm audit --json`.
 * npm exits non-zero when vulnerabilities exist — the JSON on stdout is parsed
 * either way.
 */
final class NpmAuditCollector extends AbstractCollector
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
        return 'npm_audit';
    }

    public function supported(): bool
    {
        return $this->processRunner->available()
            && $this->executables->exists('npm')
            && (is_readable($this->projectRoot.'/package-lock.json') || is_readable($this->projectRoot.'/npm-shrinkwrap.json'));
    }

    public function collect(): CollectionResult
    {
        $npm = $this->executables->find('npm');

        if ($npm === null) {
            return CollectionResult::unsupported($this->key());
        }

        $result = $this->processRunner->run([
            $npm,
            '--prefix',
            $this->projectRoot,
            'audit',
            '--json',
        ], $this->timeoutSeconds, $this->interpreterPaths($npm));

        if ($result->timedOut) {
            return CollectionResult::failed($this->key(), 'npm audit timed out.');
        }

        $decoded = json_decode($result->output, true);

        if (! is_array($decoded)) {
            return CollectionResult::failed($this->key(), sprintf(
                'npm audit produced no parsable JSON: %s',
                $this->excerpt($result->errorOutput !== '' ? $result->errorOutput : $result->output)
            ));
        }

        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? ($decoded['error']['summary'] ?? 'npm audit failed.') : 'npm audit failed.';

            return CollectionResult::failed($this->key(), is_string($message) ? $message : 'npm audit failed.');
        }

        $counts = $this->vulnerabilityCounts($decoded);

        $data = ['vulnerabilities' => $counts];
        $actionable = ($counts['low'] ?? 0) + ($counts['moderate'] ?? 0) + ($counts['high'] ?? 0) + ($counts['critical'] ?? 0);

        return $actionable > 0
            ? CollectionResult::warning($this->key(), $data)
            : CollectionResult::ok($this->key(), $data);
    }

    /**
     * npm re-execs node through its "#!/usr/bin/env node" shebang, so the
     * spawned process needs node's directory on its PATH even when the
     * inherited PATH is restricted (php-fpm, cron). node usually sits beside
     * npm, so npm's own directory is included as a fallback.
     *
     * @return array<int, string>
     */
    private function interpreterPaths(string $npm): array
    {
        $paths = [dirname($npm)];

        $nodeDirectory = $this->executables->directoryOf('node');

        if ($nodeDirectory !== null) {
            array_unshift($paths, $nodeDirectory);
        }

        return array_values(array_unique($paths));
    }

    /**
     * Supports the npm v7+ report (metadata.vulnerabilities) and the legacy v6
     * shape (metadata.vulnerabilities with the same keys).
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, int>
     */
    private function vulnerabilityCounts(array $decoded): array
    {
        $metadata = $decoded['metadata'] ?? [];
        $raw = is_array($metadata) && isset($metadata['vulnerabilities']) && is_array($metadata['vulnerabilities'])
            ? $metadata['vulnerabilities']
            : [];

        $counts = [];

        foreach (['info', 'low', 'moderate', 'high', 'critical', 'total'] as $severity) {
            $counts[$severity] = (int) ($raw[$severity] ?? 0);
        }

        if (! isset($raw['total'])) {
            $counts['total'] = $counts['info'] + $counts['low'] + $counts['moderate'] + $counts['high'] + $counts['critical'];
        }

        return $counts;
    }
}
