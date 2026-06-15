<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

/**
 * Outcome of an external command run through a ProcessRunner.
 */
final class ProcessResult
{
    public function __construct(
        public bool $successful,
        public string $output,
        public string $errorOutput = '',
        public ?int $exitCode = null,
        public bool $timedOut = false
    ) {}

    /**
     * Standard output, falling back to stderr (tools like `nginx -v` print
     * their version to stderr).
     */
    public function anyOutput(): string
    {
        return trim($this->output) !== '' ? $this->output : $this->errorOutput;
    }
}
