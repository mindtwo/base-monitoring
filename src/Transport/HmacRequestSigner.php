<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Transport;

use Mindtwo\Monitoring\Contracts\RequestSigner;
use Mindtwo\Monitoring\Data\Credentials;

/**
 * Signs requests with HMAC-SHA256 over "{timestamp}.{payload}". Including the
 * timestamp in the signed string (and verifying it within a tolerance window)
 * protects against replay; the secret itself never travels on the wire.
 */
final class HmacRequestSigner implements RequestSigner
{
    public const HEADER_KEY = 'X-Monitoring-Key';

    public const HEADER_TIMESTAMP = 'X-Monitoring-Timestamp';

    public const HEADER_SIGNATURE = 'X-Monitoring-Signature';

    public const ALGORITHM = 'sha256';

    /** @var (callable(): int)|null */
    private $clock;

    /**
     * @param  (callable(): int)|null  $clock  Unix-timestamp source, injectable for tests
     */
    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock;
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $payload, Credentials $credentials): array
    {
        $timestamp = (string) ($this->clock !== null ? ($this->clock)() : time());

        return [
            self::HEADER_KEY => $credentials->projectKey,
            self::HEADER_TIMESTAMP => $timestamp,
            self::HEADER_SIGNATURE => self::sign($payload, $timestamp, $credentials->secret),
        ];
    }

    public static function sign(string $payload, string $timestamp, string $secret): string
    {
        return hash_hmac(self::ALGORITHM, $timestamp.'.'.$payload, $secret);
    }
}
