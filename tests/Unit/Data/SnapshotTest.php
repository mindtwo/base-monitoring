<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\Source;

function makeSnapshot(): Snapshot
{
    return new Snapshot(
        Snapshot::SCHEMA_VERSION,
        '2026-06-09T12:00:00+00:00',
        'production',
        new Source('laravel', 'mindtwo/laravel-monitoring', '1.2.0', '1.0.3'),
        'prj_live_8f3a'
    );
}

test('a snapshot serializes the canonical payload shape', function () {
    $snapshot = makeSnapshot()
        ->add(CollectionResult::ok('php', ['technology' => 'php', 'version' => '8.3.2']))
        ->add(CollectionResult::unsupported('nginx'));

    $payload = $snapshot->toArray();

    expect(array_keys($payload))->toBe([
        'schema_version', 'collected_at', 'environment', 'project_key',
        'source', 'metrics', 'technologies', 'custom_data',
    ])
        ->and($payload['schema_version'])->toBe('1.0')
        ->and($payload['collected_at'])->toBe('2026-06-09T12:00:00+00:00')
        ->and($payload['environment'])->toBe('production')
        ->and($payload['project_key'])->toBe('prj_live_8f3a')
        ->and($payload['source'])->toBe([
            'type' => 'laravel',
            'package' => 'mindtwo/laravel-monitoring',
            'version' => '1.2.0',
            'base_version' => '1.0.3',
            'server_ip' => null,
        ])
        ->and($payload['metrics']['php'])->toBe(['status' => 'ok', 'technology' => 'php', 'version' => '8.3.2'])
        ->and($payload['metrics']['nginx'])->toBe(['status' => 'unsupported']);
});

test('technologies are flattened from metrics that detected one', function () {
    $snapshot = makeSnapshot()
        ->add(CollectionResult::ok('os', ['technology' => 'ubuntu', 'version' => '22.04']))
        ->add(CollectionResult::ok('custom', ['technology' => 'internal-tool', 'version' => '2.0', 'technology_source' => 'package']))
        ->add(CollectionResult::ok('system', ['cpu_count' => 8]));

    expect($snapshot->technologies())->toBe([
        ['technology' => 'ubuntu', 'version' => '22.04', 'source' => 'known'],
        ['technology' => 'internal-tool', 'version' => '2.0', 'source' => 'package'],
    ]);
});

test('adding a result with an existing key replaces it', function () {
    $snapshot = makeSnapshot()
        ->add(CollectionResult::ok('database', ['version' => 'client']))
        ->add(CollectionResult::ok('database', ['version' => 'server']));

    expect($snapshot->results())->toHaveCount(1)
        ->and($snapshot->result('database')?->data['version'])->toBe('server');
});

test('custom data merges and empty custom data encodes as a JSON object', function () {
    $snapshot = makeSnapshot();

    expect(json_decode($snapshot->toJson(), false)->custom_data)->toBeObject();

    $snapshot->mergeCustomData(['plugins' => ['a' => '1.0']]);
    $snapshot->mergeCustomData(['theme' => 'dark']);

    expect($snapshot->customData())->toBe(['plugins' => ['a' => '1.0'], 'theme' => 'dark']);
});

test('toJson produces valid unescaped JSON', function () {
    $json = makeSnapshot()
        ->add(CollectionResult::ok('git', ['branch' => 'feature/x']))
        ->toJson();

    expect($json)->toContain('"branch":"feature/x"')
        ->and(json_decode($json, true))->toBeArray();
});
