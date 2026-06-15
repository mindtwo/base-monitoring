<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;

const FROZEN_TIME = 1_750_000_000;

function credentials(): Credentials
{
    return new Credentials('prj_live_8f3a', 'top-secret');
}

function frozenSigner(int $time = FROZEN_TIME): HmacRequestSigner
{
    return new HmacRequestSigner(fn (): int => $time);
}

function frozenVerifier(int $time = FROZEN_TIME, int $tolerance = 300): HmacSignatureVerifier
{
    return new HmacSignatureVerifier($tolerance, fn (): int => $time);
}

test('signed payloads verify (round trip)', function () {
    $payload = '{"schema_version":"1.0"}';
    $headers = frozenSigner()->headers($payload, credentials());

    expect($headers)->toHaveKeys(['X-Monitoring-Key', 'X-Monitoring-Timestamp', 'X-Monitoring-Signature'])
        ->and($headers['X-Monitoring-Key'])->toBe('prj_live_8f3a')
        ->and($headers['X-Monitoring-Timestamp'])->toBe((string) FROZEN_TIME)
        ->and(frozenVerifier()->verify($payload, $headers, credentials()))->toBeTrue();
});

test('the secret never appears in the headers', function () {
    $headers = frozenSigner()->headers('payload', credentials());

    expect(implode(' ', $headers))->not->toContain('top-secret');
});

test('header names are matched case-insensitively', function () {
    $headers = frozenSigner()->headers('payload', credentials());
    $lowercased = array_change_key_case($headers, CASE_LOWER);

    expect(frozenVerifier()->verify('payload', $lowercased, credentials()))->toBeTrue();
});

test('a tampered payload fails verification', function () {
    $headers = frozenSigner()->headers('{"environment":"production"}', credentials());

    expect(frozenVerifier()->verify('{"environment":"hacked"}', $headers, credentials()))->toBeFalse();
});

test('a wrong project key fails verification', function () {
    $headers = frozenSigner()->headers('payload', credentials());
    $headers['X-Monitoring-Key'] = 'prj_other';

    expect(frozenVerifier()->verify('payload', $headers, credentials()))->toBeFalse();
});

test('a wrong secret fails verification', function () {
    $headers = frozenSigner()->headers('payload', credentials());

    expect(frozenVerifier()->verify('payload', $headers, new Credentials('prj_live_8f3a', 'other-secret')))->toBeFalse();
});

test('requests outside the tolerance window are rejected (replay protection)', function () {
    $headers = frozenSigner()->headers('payload', credentials());

    expect(frozenVerifier(FROZEN_TIME + 301)->verify('payload', $headers, credentials()))->toBeFalse()
        ->and(frozenVerifier(FROZEN_TIME + 299)->verify('payload', $headers, credentials()))->toBeTrue()
        ->and(frozenVerifier(FROZEN_TIME - 301)->verify('payload', $headers, credentials()))->toBeFalse();
});

test('the tolerance window is configurable', function () {
    $headers = frozenSigner()->headers('payload', credentials());

    expect(frozenVerifier(FROZEN_TIME + 500, 600)->verify('payload', $headers, credentials()))->toBeTrue();
});

test('missing or malformed headers fail verification', function (array $headers) {
    expect(frozenVerifier()->verify('payload', $headers, credentials()))->toBeFalse();
})->with([
    'empty' => [[]],
    'missing signature' => [['X-Monitoring-Key' => 'prj_live_8f3a', 'X-Monitoring-Timestamp' => (string) FROZEN_TIME]],
    'non-numeric timestamp' => [['X-Monitoring-Key' => 'prj_live_8f3a', 'X-Monitoring-Timestamp' => 'now', 'X-Monitoring-Signature' => 'abc']],
    'negative timestamp' => [['X-Monitoring-Key' => 'prj_live_8f3a', 'X-Monitoring-Timestamp' => '-100', 'X-Monitoring-Signature' => 'abc']],
]);

test('incomplete server credentials always fail verification', function () {
    $headers = frozenSigner()->headers('payload', credentials());

    expect(frozenVerifier()->verify('payload', $headers, Credentials::empty()))->toBeFalse();
});

test('the signature covers timestamp and payload', function () {
    $signature = HmacRequestSigner::sign('payload', '123', 'secret');

    expect($signature)->toBe(hash_hmac('sha256', '123.payload', 'secret'))
        ->and(HmacRequestSigner::sign('payload', '124', 'secret'))->not->toBe($signature);
});
