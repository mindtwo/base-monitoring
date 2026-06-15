<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

/**
 * Outcome of a transport delivery attempt.
 */
final class TransportResult
{
    public function __construct(
        public bool $success,
        public ?int $statusCode = null,
        public ?string $error = null
    ) {}

    public static function delivered(?int $statusCode = null): self
    {
        return new self(true, $statusCode);
    }

    public static function failed(string $error, ?int $statusCode = null): self
    {
        return new self(false, $statusCode, $error);
    }
}
