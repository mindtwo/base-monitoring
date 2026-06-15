<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\DefaultCollectors;
use Mindtwo\Monitoring\Contracts\Collector;

test('the catalog covers every metric of the execution plan with unique keys', function () {
    $collectors = DefaultCollectors::make(projectRoot: sys_get_temp_dir());
    $keys = array_map(static fn (Collector $collector): string => $collector->key(), $collectors);

    expect($keys)->toBe([
        'os', 'php', 'database', 'nginx', 'apache', 'caddy', 'redis', 'node',
        'system', 'composer_packages', 'npm_packages', 'composer_audit',
        'composer_licenses', 'npm_audit', 'git',
    ])
        ->and($keys)->toBe(array_unique($keys));
});

test('a full default snapshot runs safely on the real host', function () {
    $monitor = Mindtwo\Monitoring\Monitor::make(sys_get_temp_dir());
    $payload = $monitor->snapshot()->toArray();

    expect($payload['metrics'])->toHaveCount(15);

    foreach ($payload['metrics'] as $metric) {
        expect(in_array($metric['status'], ['ok', 'warning', 'failed', 'skipped', 'unsupported'], true))->toBeTrue();
    }
});
