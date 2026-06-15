<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Transport;

use Mindtwo\Monitoring\Contracts\SignatureVerifier;
use Mindtwo\Monitoring\Data\Credentials;

/**
 * Verifies requests signed by HmacRequestSigner: constant-time comparison of
 * the project key and signature, plus a timestamp tolerance window against
 * replayed requests.
 */
final class HmacSignatureVerifier implements SignatureVerifier
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /** @var (callable(): int)|null */
    private $clock;

    /**
     * @param  (callable(): int)|null  $clock  Unix-timestamp source, injectable for tests
     */
    public function __construct(
        private int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?callable $clock = null
    ) {
        $this->clock = $clock;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function verify(string $payload, array $headers, Credentials $credentials): bool
    {
        if (! $credentials->isComplete()) {
            return false;
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_string($value)) {
                $normalized[strtolower((string) $name)] = $value;
            }
        }

        $key = $normalized[strtolower(HmacRequestSigner::HEADER_KEY)] ?? null;
        $timestamp = $normalized[strtolower(HmacRequestSigner::HEADER_TIMESTAMP)] ?? null;
        $signature = $normalized[strtolower(HmacRequestSigner::HEADER_SIGNATURE)] ?? null;

        if ($key === null || $timestamp === null || $signature === null) {
            return false;
        }

        if (! hash_equals($credentials->projectKey, $key)) {
            return false;
        }

        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1) {
            return false;
        }

        $now = $this->clock !== null ? ($this->clock)() : time();

        if (abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = HmacRequestSigner::sign($payload, $timestamp, $credentials->secret);

        return hash_equals($expected, $signature);
    }
}
