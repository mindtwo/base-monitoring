<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\ProcessResult;

/**
 * Guarded, timeout-bounded shell access. Collectors never call exec() directly;
 * implementations must use argv arrays (no shell interpolation) and degrade
 * gracefully where process functions are disabled.
 */
interface ProcessRunner
{
    /**
     * Whether external processes can be run at all (e.g. proc_open not disabled).
     */
    public function available(): bool;

    /**
     * @param  array<int, string>  $command
     * @param  array<int, string>  $extraPaths  Directories prepended to the child
     *                                          process PATH so wrapper scripts can
     *                                          locate an interpreter they re-exec
     *                                          (e.g. composer → php, npm → node).
     */
    public function run(array $command, ?int $timeoutSeconds = 15, array $extraPaths = []): ProcessResult;
}
