<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * License inventory of the installed Composer packages via
 * `composer licenses --format=json`: a summary count per license plus the
 * per-package license list for compliance review.
 */
final class ComposerLicensesCollector extends AbstractCollector
{
    public const DEFAULT_TIMEOUT_SECONDS = 30;

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
        return 'composer_licenses';
    }

    public function supported(): bool
    {
        return $this->processRunner->available()
            && is_readable($this->projectRoot.'/composer.json')
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
            'licenses',
            '--format=json',
            '--no-interaction',
            '--working-dir='.$this->projectRoot,
        ], $this->timeoutSeconds, $this->interpreterPaths());

        if ($result->timedOut) {
            return CollectionResult::failed($this->key(), 'composer licenses timed out.');
        }

        $decoded = json_decode($result->output, true);

        if (! is_array($decoded) || ! isset($decoded['dependencies']) || ! is_array($decoded['dependencies'])) {
            return CollectionResult::failed($this->key(), sprintf(
                'composer licenses produced no parsable JSON: %s',
                $this->excerpt($result->errorOutput !== '' ? $result->errorOutput : $result->output)
            ));
        }

        $summary = [];
        $packages = [];

        foreach ($decoded['dependencies'] as $name => $meta) {
            if (! is_string($name) || ! is_array($meta)) {
                continue;
            }

            $licenses = isset($meta['license']) && is_array($meta['license'])
                ? array_values(array_filter($meta['license'], 'is_string'))
                : [];

            $packages[$name] = $licenses;

            foreach ($licenses === [] ? ['unknown'] : $licenses as $license) {
                $summary[$license] = ($summary[$license] ?? 0) + 1;
            }
        }

        arsort($summary, SORT_NUMERIC);

        return CollectionResult::ok($this->key(), [
            'licenses' => $summary,
            'packages' => $packages,
        ]);
    }

    /**
     * Composer re-execs php through its "#!/usr/bin/env php" shebang, so the
     * spawned process needs php's directory on its PATH even when the inherited
     * PATH is restricted (php-fpm, cron).
     *
     * @return array<int, string>
     */
    private function interpreterPaths(): array
    {
        $phpDirectory = $this->executables->directoryOf('php');

        return $phpDirectory !== null ? [$phpDirectory] : [];
    }
}
