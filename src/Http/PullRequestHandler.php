<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Http;

use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Contracts\SignatureVerifier;
use Mindtwo\Monitoring\Support\FixedWindowRateLimiter;
use Mindtwo\Monitoring\Support\IpMatcher;
use Throwable;

/**
 * Framework-agnostic core of a pull endpoint: rate limiting, IP allow-list,
 * configuration guard and signature verification in the right order, returning
 * a status code plus JSON payload for the host framework to emit. Used by the
 * WordPress, Craft and server plugins; Laravel uses idiomatic middleware that
 * mirrors this exact behavior.
 */
final class PullRequestHandler
{
    public function __construct(
        private ConfigurationRepository $config,
        private SignatureVerifier $verifier,
        private ?FixedWindowRateLimiter $rateLimiter = null
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  callable(): array<string, mixed>  $snapshot  lazily builds the payload
     * @return array{0: int, 1: array<string, mixed>} [HTTP status code, JSON payload]
     */
    public function handle(string $ip, array $headers, string $body, callable $snapshot): array
    {
        if ($this->rateLimiter !== null && $this->rateLimiter->tooManyAttempts('pull|'.$ip)) {
            return [429, ['message' => 'Too many requests.']];
        }

        if (! IpMatcher::allows($ip, $this->config->ipAllowList())) {
            return [403, ['message' => 'Forbidden.']];
        }

        $credentials = $this->config->credentials();

        if (! $credentials->isComplete()) {
            return [503, ['message' => 'Monitoring is not configured.']];
        }

        if (! $this->verifier->verify($body, $headers, $credentials)) {
            return [401, ['message' => 'Unauthorized.']];
        }

        try {
            return [200, $snapshot()];
        } catch (Throwable $exception) {
            return [500, ['message' => 'Unable to build the monitoring snapshot.']];
        }
    }
}
