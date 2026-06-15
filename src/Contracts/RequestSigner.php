<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\Credentials;

/**
 * Produces authentication headers for an outbound request. The secret is never
 * transmitted: the payload is signed and only the project key, a timestamp and
 * the signature travel on the wire.
 */
interface RequestSigner
{
    /**
     * Auth headers for an outbound (push) request carrying $payload.
     *
     * @return array<string, string>
     */
    public function headers(string $payload, Credentials $credentials): array;
}
