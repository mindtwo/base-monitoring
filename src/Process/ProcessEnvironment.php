<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

/**
 * Builds the environment for a spawned process so wrapper scripts can locate
 * the interpreter they re-exec through "#!/usr/bin/env php|node".
 *
 * The wrapper itself (composer, npm) is located by the ExecutableFinder, which
 * also searches fallback directories outside PATH. The child process, however,
 * only inherits PHP's own PATH — and in restricted contexts (php-fpm, cron,
 * launchd) that PATH frequently omits the directory holding php/node, so
 * "env php" fails with "env: php: No such file or directory". Worse, under
 * php-fpm PHP_BINARY points at the fpm binary rather than a CLI php, so the
 * child cannot rediscover it. Widening the child PATH with the interpreter's
 * directory (supplied by the collector) resolves both cases.
 */
final class ProcessEnvironment
{
    /**
     * A snapshot of the current environment whose PATH is widened by the given
     * directories. Suitable for proc_open(), which replaces the environment
     * wholesale rather than merging it.
     *
     * @param  array<int, string>  $extraPaths
     * @return array<string, string>
     */
    public static function withAugmentedPath(array $extraPaths = []): array
    {
        $environment = getenv();
        $environment['PATH'] = self::augmentedPath($extraPaths);

        return $environment;
    }

    /**
     * The current PATH widened by the given directories, the running PHP
     * binary's directory and the standard system bin directories. The supplied
     * directories take precedence, so the interpreter the collector located is
     * the one a shebang resolves to.
     *
     * @param  array<int, string>  $extraPaths
     */
    public static function augmentedPath(array $extraPaths = []): string
    {
        $directories = array_merge(
            $extraPaths,
            explode(PATH_SEPARATOR, (string) getenv('PATH')),
            [dirname(PHP_BINARY)],
            ExecutableFinder::fallbackDirectories()
        );

        $directories = array_filter(
            $directories,
            static fn (string $directory): bool => $directory !== ''
        );

        return implode(PATH_SEPARATOR, array_values(array_unique($directories)));
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}
