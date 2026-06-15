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
     */
    public function run(array $command, ?int $timeoutSeconds = 15): ProcessResult;
}
