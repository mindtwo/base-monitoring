<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * The full base collector catalog, wired with shared dependencies. Framework
 * plugins start from this list and add or replace collectors.
 */
final class DefaultCollectors
{
    /**
     * @return array<int, Collector>
     */
    public static function make(
        ?ProcessRunner $processRunner = null,
        ?string $projectRoot = null,
        ?TechnologyResolver $technologies = null,
        ?ExecutableFinder $executables = null
    ): array {
        $runner = $processRunner ?? ProcessRunnerFactory::make();
        $finder = $executables ?? new ExecutableFinder;
        $resolver = $technologies ?? EndOfLifeTechnologyResolver::default();
        $root = $projectRoot ?? (string) getcwd();

        return [
            new OsCollector($runner, $resolver),
            new PhpCollector($resolver),
            new DatabaseCollector($runner, $finder, $resolver),
            new NginxCollector($runner, $finder, $resolver),
            new ApacheCollector($runner, $finder, $resolver),
            new CaddyCollector($runner, $finder, $resolver),
            new RedisCollector($runner, $finder, $resolver),
            new NodeCollector($runner, $finder, $resolver),
            new SystemStatsCollector($runner, $root),
            new ComposerPackagesCollector($root, $resolver),
            new NpmPackagesCollector($root),
            new ComposerAuditCollector($runner, $root, $finder),
            new ComposerLicensesCollector($runner, $root, $finder),
            new NpmAuditCollector($runner, $root, $finder),
            new GitStatusCollector($runner, $root, $finder),
        ];
    }

    private function __construct()
    {
        // Static catalog — never instantiated.
    }
}
