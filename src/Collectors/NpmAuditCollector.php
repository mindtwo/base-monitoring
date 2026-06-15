<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * Vulnerabilities for the installed npm packages via `npm audit --json`:
 * severity counts plus the individual advisories so consumers can see *which*
 * packages are affected, not just how many. npm exits non-zero when
 * vulnerabilities exist — the JSON on stdout is parsed either way.
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
        $advisories = $this->extractAdvisories($decoded);

        $data = [
            'vulnerabilities' => $counts,
            'advisories_count' => count($advisories),
            'advisories' => $advisories,
        ];
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

    /**
     * The individual advisories, normalized to the same shape as
     * ComposerAuditCollector (plus npm's `fix_available`). Supports the npm v7+
     * report (top-level "vulnerabilities" map carrying a "via" array) and the
     * legacy v6 shape (top-level "advisories" map keyed by advisory id).
     *
     * @param  array<string, mixed>  $decoded
     * @return array<int, array<string, mixed>>
     */
    private function extractAdvisories(array $decoded): array
    {
        if (isset($decoded['vulnerabilities']) && is_array($decoded['vulnerabilities'])) {
            return $this->advisoriesFromReport($decoded['vulnerabilities']);
        }

        if (isset($decoded['advisories']) && is_array($decoded['advisories'])) {
            return $this->advisoriesFromLegacy($decoded['advisories']);
        }

        return [];
    }

    /**
     * npm v7+: each top-level entry is a vulnerable package whose `via` array
     * holds either advisory objects (the real source) or strings naming another
     * package in the dependency chain. We keep the objects, deduplicated by
     * their advisory `source` id since the same advisory can surface under
     * several packages.
     *
     * @param  array<string, mixed>  $vulnerabilities
     * @return array<int, array<string, mixed>>
     */
    private function advisoriesFromReport(array $vulnerabilities): array
    {
        $advisories = [];

        foreach ($vulnerabilities as $packageName => $vulnerability) {
            if (! is_array($vulnerability)) {
                continue;
            }

            $via = $vulnerability['via'] ?? [];

            if (! is_array($via)) {
                continue;
            }

            $fixAvailable = $this->normalizeFixAvailable($vulnerability['fixAvailable'] ?? null);

            foreach ($via as $source) {
                if (! is_array($source)) {
                    continue;
                }

                $advisory = [
                    'package' => (string) ($source['name'] ?? $packageName),
                    'severity' => $source['severity'] ?? ($vulnerability['severity'] ?? null),
                    'cve' => null,
                    'title' => $source['title'] ?? null,
                    'affected_versions' => $source['range'] ?? ($vulnerability['range'] ?? null),
                    'link' => $source['url'] ?? null,
                    'fix_available' => $fixAvailable,
                ];

                if (isset($source['source'])) {
                    $advisories[(string) $source['source']] = $advisory;
                } else {
                    $advisories[] = $advisory;
                }
            }
        }

        return array_values($advisories);
    }

    /**
     * npm v6: top-level "advisories" is a map of advisory id to a record with
     * snake_cased fields and a `cves` list.
     *
     * @param  array<int|string, mixed>  $advisories
     * @return array<int, array<string, mixed>>
     */
    private function advisoriesFromLegacy(array $advisories): array
    {
        $normalized = [];

        foreach ($advisories as $advisory) {
            if (! is_array($advisory)) {
                continue;
            }

            $cves = $advisory['cves'] ?? [];
            $patched = $advisory['patched_versions'] ?? null;

            $normalized[] = [
                'package' => (string) ($advisory['module_name'] ?? ''),
                'severity' => $advisory['severity'] ?? null,
                'cve' => is_array($cves) && isset($cves[0]) ? (string) $cves[0] : null,
                'title' => $advisory['title'] ?? null,
                'affected_versions' => $advisory['vulnerable_versions'] ?? null,
                'link' => $advisory['url'] ?? null,
                'fix_available' => is_string($patched) && $patched !== '' && $patched !== '<0.0.0',
            ];
        }

        return $normalized;
    }

    /**
     * npm reports `fixAvailable` as a bool, or an object describing the upgrade
     * target — the target version is the useful part when present.
     *
     * @param  mixed  $fixAvailable
     */
    private function normalizeFixAvailable($fixAvailable): bool|string
    {
        if (is_array($fixAvailable)) {
            return isset($fixAvailable['version']) ? (string) $fixAvailable['version'] : true;
        }

        return (bool) $fixAvailable;
    }
}
