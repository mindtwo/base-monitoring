<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\Credentials;

/**
 * Verifies an inbound (pull/receive) request that was signed by a RequestSigner.
 */
interface SignatureVerifier
{
    /**
     * @param  array<string, string>  $headers
     */
    public function verify(string $payload, array $headers, Credentials $credentials): bool;
}
