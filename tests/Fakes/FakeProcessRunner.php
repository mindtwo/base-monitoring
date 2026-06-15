<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Tests\Fakes;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\ProcessResult;

/**
 * Records every command and answers from a pattern map. Patterns match when
 * they are a substring of the joined command line (the binary's directory is
 * stripped, so fake bin dirs don't leak into expectations).
 */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var array<int, array{pattern: string, result: ProcessResult}> */
    private array $handlers = [];

    /** @var array<int, string> joined command lines, in execution order */
    public array $commands = [];

    public function __construct(private bool $available = true) {}

    public function on(string $pattern, ProcessResult $result): self
    {
        $this->handlers[] = ['pattern' => $pattern, 'result' => $result];

        return $this;
    }

    public function onOutput(string $pattern, string $output): self
    {
        return $this->on($pattern, new ProcessResult(true, $output));
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function run(array $command, ?int $timeoutSeconds = 15): ProcessResult
    {
        $joined = $this->join($command);

        $this->commands[] = $joined;

        foreach ($this->handlers as $handler) {
            if (str_contains($joined, $handler['pattern'])) {
                return $handler['result'];
            }
        }

        return new ProcessResult(false, '', 'No fake result registered for: '.$joined, 127);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function join(array $command): string
    {
        if ($command === []) {
            return '';
        }

        $command[0] = basename($command[0]);

        return implode(' ', $command);
    }
}
