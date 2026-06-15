<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

use Mindtwo\Monitoring\Contracts\ProcessRunner;

final class ProcessRunnerFactory
{
    /**
     * The best runner for this installation: symfony/process when installed,
     * the dependency-free native runner otherwise.
     */
    public static function make(): ProcessRunner
    {
        return SymfonyProcessRunner::supported()
            ? new SymfonyProcessRunner
            : new NativeProcessRunner;
    }

    private function __construct()
    {
        // Static factory — never instantiated.
    }
}
