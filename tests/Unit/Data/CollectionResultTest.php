<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Enums\Status;

test('ok results carry their data', function () {
    $result = CollectionResult::ok('php', ['version' => '8.3.2']);

    expect($result->key)->toBe('php')
        ->and($result->status)->toBe(Status::OK)
        ->and($result->successful())->toBeTrue()
        ->and($result->toArray())->toBe(['status' => 'ok', 'version' => '8.3.2']);
});

test('warning results keep data and may carry an error', function () {
    $result = CollectionResult::warning('composer_audit', ['advisories_count' => 2], 'advisories found');

    expect($result->status)->toBe(Status::WARNING)
        ->and($result->successful())->toBeTrue()
        ->and($result->toArray())->toBe([
            'status' => 'warning',
            'error' => 'advisories found',
            'advisories_count' => 2,
        ]);
});

test('failed results expose the error', function () {
    $result = CollectionResult::failed('git', 'git binary missing');

    expect($result->status)->toBe(Status::FAILED)
        ->and($result->successful())->toBeFalse()
        ->and($result->toArray())->toBe(['status' => 'failed', 'error' => 'git binary missing']);
});

test('unsupported and skipped results serialize minimally', function () {
    expect(CollectionResult::unsupported('nginx')->toArray())->toBe(['status' => 'unsupported'])
        ->and(CollectionResult::skipped('npm_audit', 'disabled by config')->toArray())
        ->toBe(['status' => 'skipped', 'error' => 'disabled by config']);
});

test('the status enum lists all known statuses', function () {
    expect(Status::all())->toBe(['ok', 'warning', 'failed', 'skipped', 'unsupported'])
        ->and(Status::isValid('ok'))->toBeTrue()
        ->and(Status::isValid('great'))->toBeFalse();
});
