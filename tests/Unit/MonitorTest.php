<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\TransportResult;
use Mindtwo\Monitoring\Exceptions\DuplicateCollectorKey;
use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\SnapshotBuilder;
use Mindtwo\Monitoring\SnapshotFactory;

function monitor(?Transport $transport = null): Monitor
{
    return new Monitor(new SnapshotBuilder(new SnapshotFactory), $transport);
}

function namedCollector(string $key, array $data = []): Collector
{
    return new class($key, $data) implements Collector
    {
        public function __construct(private string $key, private array $data) {}

        public function key(): string
        {
            return $this->key;
        }

        public function supported(): bool
        {
            return true;
        }

        public function collect(): CollectionResult
        {
            return CollectionResult::ok($this->key, $this->data);
        }
    };
}

test('registering a duplicate key throws', function () {
    monitor()
        ->register(namedCollector('database'))
        ->register(namedCollector('database'));
})->throws(DuplicateCollectorKey::class, 'database');

test('replace intentionally overrides an existing collector', function () {
    $monitor = monitor()
        ->register(namedCollector('database', ['version' => 'client']))
        ->replace(namedCollector('database', ['version' => 'server']));

    expect($monitor->collectors())->toHaveCount(1)
        ->and($monitor->snapshot()->result('database')?->data['version'])->toBe('server');
});

test('collectors can be forgotten and queried', function () {
    $monitor = monitor()->register(namedCollector('git'));

    expect($monitor->has('git'))->toBeTrue();

    $monitor->forget('git');

    expect($monitor->has('git'))->toBeFalse()
        ->and($monitor->collectors())->toBe([]);
});

test('snapshot runs all registered collectors and merges custom data', function () {
    $snapshot = monitor()
        ->register(namedCollector('php', ['version' => '8.3.2']), namedCollector('os'))
        ->addCustomData('app', fn (): array => ['version' => '42'])
        ->snapshot();

    expect(array_keys($snapshot->results()))->toBe(['php', 'os'])
        ->and($snapshot->customData())->toBe(['app' => ['version' => '42']]);
});

test('push delivers the snapshot through the transport', function () {
    $transport = new class implements Transport
    {
        public ?Snapshot $sent = null;

        public function send(Snapshot $snapshot): TransportResult
        {
            $this->sent = $snapshot;

            return TransportResult::delivered(200);
        }
    };

    $result = monitor($transport)->register(namedCollector('php'))->push();

    expect($result->success)->toBeTrue()
        ->and($transport->sent)->toBeInstanceOf(Snapshot::class)
        ->and($transport->sent?->result('php'))->not->toBeNull();
});

test('push without any transport fails gracefully', function () {
    $result = monitor()->push();

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('No transport configured');
});

test('push prefers an explicitly passed transport and isolates a throwing one', function () {
    $throwing = new class implements Transport
    {
        public function send(Snapshot $snapshot): TransportResult
        {
            throw new RuntimeException('transport exploded');
        }
    };

    $result = monitor()->push($throwing);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('transport exploded');
});

test('make() wires the complete default catalog with unique keys', function () {
    $monitor = Monitor::make(sys_get_temp_dir());
    $keys = array_keys($monitor->collectors());

    expect($keys)->toBe([
        'os', 'php', 'database', 'nginx', 'apache', 'caddy', 'redis', 'node',
        'system', 'composer_packages', 'npm_packages', 'composer_audit',
        'composer_licenses', 'npm_audit', 'git',
    ]);
});
