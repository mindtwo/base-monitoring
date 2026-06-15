<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

/**
 * Project key + secret pair used for HMAC authentication. The secret never
 * leaves the host — only the key and a signature travel on the wire.
 */
final class Credentials
{
    public function __construct(
        public string $projectKey,
        public string $secret
    ) {}

    public static function empty(): self
    {
        return new self('', '');
    }

    public function isComplete(): bool
    {
        return $this->projectKey !== '' && $this->secret !== '';
    }
}
