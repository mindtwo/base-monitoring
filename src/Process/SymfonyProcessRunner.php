<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\ProcessResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runner backed by symfony/process when it is installed (an optional
 * dependency — use ProcessRunnerFactory::make() to pick it up automatically).
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    public static function supported(): bool
    {
        return class_exists(Process::class);
    }

    public function available(): bool
    {
        return self::supported() && (new NativeProcessRunner)->available();
    }

    public function run(array $command, ?int $timeoutSeconds = 15, array $extraPaths = []): ProcessResult
    {
        if ($command === []) {
            return new ProcessResult(false, '', 'No command given.');
        }

        if (! $this->available()) {
            return new ProcessResult(false, '', 'Process execution is not available on this system.');
        }

        $process = new Process(array_values($command));
        $process->setTimeout($timeoutSeconds !== null ? (float) $timeoutSeconds : null);

        // Merge over the inherited environment so wrapper scripts find their
        // interpreter; only PATH is overridden, every other variable is kept.
        $process->setEnv(['PATH' => ProcessEnvironment::augmentedPath($extraPaths)]);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            return new ProcessResult(
                false,
                $process->getOutput(),
                trim($process->getErrorOutput()."\n".$exception->getMessage()),
                null,
                true
            );
        } catch (Throwable $exception) {
            return new ProcessResult(false, '', $exception->getMessage());
        }

        return new ProcessResult(
            $process->isSuccessful(),
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getExitCode()
        );
    }
}
