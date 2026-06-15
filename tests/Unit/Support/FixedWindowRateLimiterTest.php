<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Support\FixedWindowRateLimiter;

function arrayLimiter(int $max, int $window, int &$now, array &$store): FixedWindowRateLimiter
{
    return new FixedWindowRateLimiter(
        static function (string $key) use (&$store) {
            return $store[$key][0] ?? null;
        },
        static function (string $key, $value, int $ttl) use (&$store): void {
            $store[$key] = [$value, $ttl];
        },
        $max,
        $window,
        static function () use (&$now): int {
            return $now;
        }
    );
}

test('attempts within the limit pass and excess attempts are blocked', function () {
    $now = 1_000_000;
    $store = [];
    $limiter = arrayLimiter(3, 60, $now, $store);

    expect($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeTrue()
        ->and($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeTrue();
});

test('keys are isolated from each other', function () {
    $now = 1_000_000;
    $store = [];
    $limiter = arrayLimiter(1, 60, $now, $store);

    expect($limiter->tooManyAttempts('pull|1.1.1.1'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|2.2.2.2'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|1.1.1.1'))->toBeTrue();
});

test('a new window resets the counter', function () {
    $now = 1_000_000;
    $store = [];
    $limiter = arrayLimiter(1, 60, $now, $store);

    expect($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeFalse()
        ->and($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeTrue();

    $now += 60;

    expect($limiter->tooManyAttempts('pull|1.2.3.4'))->toBeFalse();
});

test('values are written with the window as TTL', function () {
    $now = 1_000_000;
    $store = [];
    arrayLimiter(5, 120, $now, $store)->tooManyAttempts('pull|1.2.3.4');

    expect($store)->toHaveCount(1)
        ->and(array_values($store)[0])->toBe([1, 120]);
});
