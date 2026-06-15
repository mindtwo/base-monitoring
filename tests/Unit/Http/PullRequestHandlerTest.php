<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Http\PullRequestHandler;
use Mindtwo\Monitoring\Support\FixedWindowRateLimiter;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;

function pullConfig(?Credentials $credentials = null, array $ipAllowList = []): ConfigurationRepository
{
    return new class($credentials ?? new Credentials('prj_test', 'secret'), $ipAllowList) implements ConfigurationRepository
    {
        public function __construct(private Credentials $credentials, private array $ipAllowList) {}

        public function credentials(): Credentials
        {
            return $this->credentials;
        }

        public function endpoint(): string
        {
            return 'https://monitoring.mindtwo.com/api/monitoring';
        }

        public function ipAllowList(): array
        {
            return $this->ipAllowList;
        }

        public function get(string $key, $default = null)
        {
            return $default;
        }
    };
}

function pullHandler(?ConfigurationRepository $config = null, ?FixedWindowRateLimiter $limiter = null): PullRequestHandler
{
    return new PullRequestHandler($config ?? pullConfig(), new HmacSignatureVerifier, $limiter);
}

function validPullHeaders(string $body = ''): array
{
    return (new HmacRequestSigner)->headers($body, new Credentials('prj_test', 'secret'));
}

test('a valid signed request receives the snapshot payload', function () {
    [$status, $payload] = pullHandler()->handle('203.0.113.10', validPullHeaders(), '', fn (): array => ['schema_version' => '1.0']);

    expect($status)->toBe(200)
        ->and($payload)->toBe(['schema_version' => '1.0']);
});

test('an invalid signature is rejected with 401', function () {
    $headers = validPullHeaders();
    $headers['X-Monitoring-Signature'] = str_repeat('0', 64);

    [$status, $payload] = pullHandler()->handle('203.0.113.10', $headers, '', fn (): array => []);

    expect($status)->toBe(401)
        ->and($payload['message'])->toBe('Unauthorized.');
});

test('IPs outside the allow-list are rejected with 403 before signature work', function () {
    $handler = pullHandler(pullConfig(ipAllowList: ['10.0.0.0/8']));

    [$status] = $handler->handle('203.0.113.10', validPullHeaders(), '', fn (): array => []);

    expect($status)->toBe(403);

    [$status] = $handler->handle('10.1.2.3', validPullHeaders(), '', fn (): array => ['ok' => true]);

    expect($status)->toBe(200);
});

test('unconfigured credentials answer 503 without exposing data', function () {
    $handler = pullHandler(pullConfig(Credentials::empty()));

    [$status, $payload] = $handler->handle('203.0.113.10', validPullHeaders(), '', fn (): array => ['secret' => 'data']);

    expect($status)->toBe(503)
        ->and($payload)->not->toHaveKey('secret');
});

test('a throwing snapshot provider becomes a generic 500', function () {
    [$status, $payload] = pullHandler()->handle('203.0.113.10', validPullHeaders(), '', function (): array {
        throw new RuntimeException('internal details that must not leak');
    });

    expect($status)->toBe(500)
        ->and(json_encode($payload))->not->toContain('internal details');
});

test('the rate limiter rejects excess requests with 429', function () {
    $store = [];
    $limiter = new FixedWindowRateLimiter(
        static function (string $key) use (&$store) {
            return $store[$key] ?? null;
        },
        static function (string $key, $value, int $ttl) use (&$store): void {
            $store[$key] = $value;
        },
        maxAttempts: 2,
        windowSeconds: 60,
        clock: static fn (): int => 1_000_000
    );

    $handler = pullHandler(limiter: $limiter);

    [$first] = $handler->handle('203.0.113.10', validPullHeaders(), '', fn (): array => []);
    [$second] = $handler->handle('203.0.113.10', validPullHeaders(), '', fn (): array => []);
    [$third] = $handler->handle('203.0.113.10', validPullHeaders(), '', fn (): array => []);

    expect($first)->toBe(200)
        ->and($second)->toBe(200)
        ->and($third)->toBe(429);
});

test('the signed body must match exactly', function () {
    $headers = validPullHeaders('{"a":1}');

    [$status] = pullHandler()->handle('203.0.113.10', $headers, '{"a":2}', fn (): array => []);

    expect($status)->toBe(401);

    [$status] = pullHandler()->handle('203.0.113.10', $headers, '{"a":1}', fn (): array => []);

    expect($status)->toBe(200);
});
