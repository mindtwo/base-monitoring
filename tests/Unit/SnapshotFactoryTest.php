<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\SnapshotFactory;

test('snapshots carry schema version, environment, project key and UTC timestamp', function () {
    $factory = new SnapshotFactory(
        new Source('laravel', 'mindtwo/laravel-monitoring', '1.0.0', '1.0.0'),
        'staging',
        'prj_test',
        new DateTimeImmutable('2026-06-09 14:30:00', new DateTimeZone('Europe/Berlin'))
    );

    $snapshot = $factory->make();

    expect($snapshot->schemaVersion)->toBe('1.0')
        ->and($snapshot->environment)->toBe('staging')
        ->and($snapshot->projectKey)->toBe('prj_test')
        ->and($snapshot->collectedAt)->toBe('2026-06-09T12:30:00+00:00');
});

test('the default factory produces a library snapshot with a current timestamp', function () {
    $snapshot = (new SnapshotFactory)->make();

    expect($snapshot->environment)->toBe('production')
        ->and($snapshot->projectKey)->toBeNull()
        ->and($snapshot->source->type)->toBe('library')
        ->and(new DateTimeImmutable($snapshot->collectedAt))
        ->toBeGreaterThan(new DateTimeImmutable('-1 minute'));
});
