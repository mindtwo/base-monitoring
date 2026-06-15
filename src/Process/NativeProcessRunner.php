<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\ProcessResult;

/**
 * Dependency-free process runner built on proc_open. Commands are passed as
 * argv arrays, so no shell is ever involved and nothing can be interpolated.
 * Runs are bounded by a deadline; processes exceeding it are terminated.
 */
final class NativeProcessRunner implements ProcessRunner
{
    private const POLL_INTERVAL_MICROSECONDS = 10_000;

    private const TERMINATION_GRACE_MICROSECONDS = 100_000;

    public function available(): bool
    {
        // Functions on the disable_functions list are reported as undefined on PHP >= 8.0.
        return function_exists('proc_open')
            && function_exists('proc_close')
            && function_exists('proc_get_status')
            && function_exists('proc_terminate');
    }

    public function run(array $command, ?int $timeoutSeconds = 15, array $extraPaths = []): ProcessResult
    {
        if ($command === []) {
            return new ProcessResult(false, '', 'No command given.');
        }

        if (! $this->available()) {
            return new ProcessResult(false, '', 'Process execution is not available on this system.');
        }

        if ((str_contains($command[0], '/') || str_contains($command[0], '\\')) && ! is_file($command[0])) {
            return new ProcessResult(false, '', sprintf('Executable "%s" does not exist.', $command[0]), 127);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];

        set_error_handler(static fn (): bool => true);

        try {
            $process = proc_open(
                array_values($command),
                $descriptors,
                $pipes,
                null,
                ProcessEnvironment::withAugmentedPath($extraPaths)
            );
        } finally {
            restore_error_handler();
        }

        if (! is_resource($process)) {
            return new ProcessResult(false, '', sprintf('Unable to start process "%s".', $command[0]), 127);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $exitCode = null;
        $deadline = $timeoutSeconds !== null ? microtime(true) + $timeoutSeconds : null;

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($process);

            if (! $status['running']) {
                $exitCode = $status['exitcode'] !== -1 ? $status['exitcode'] : null;

                break;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                usleep(self::TERMINATION_GRACE_MICROSECONDS);

                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }

                break;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $closeCode = proc_close($process);

        if ($exitCode === null && ! $timedOut && $closeCode !== -1) {
            $exitCode = $closeCode;
        }

        if ($timedOut) {
            $stderr = trim($stderr."\nProcess exceeded the timeout of {$timeoutSeconds} seconds and was terminated.");
        }

        return new ProcessResult(
            ! $timedOut && $exitCode === 0,
            $stdout,
            $stderr,
            $exitCode,
            $timedOut
        );
    }
}
