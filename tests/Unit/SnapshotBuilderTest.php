<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\SnapshotBuilder;
use Mindtwo\Monitoring\SnapshotFactory;

function builder(): SnapshotBuilder
{
    return new SnapshotBuilder(new SnapshotFactory);
}

function collectorReturning(CollectionResult $result, bool $supported = true): Collector
{
    return new class($result, $supported) implements Collector
    {
        public function __construct(private CollectionResult $result, private bool $supported) {}

        public function key(): string
        {
            return $this->result->key;
        }

        public function supported(): bool
        {
            return $this->supported;
        }

        public function collect(): CollectionResult
        {
            return $this->result;
        }
    };
}

test('a throwing collector becomes a failed metric and never aborts the run', function () {
    $crashing = new class implements Collector
    {
        public function key(): string
        {
            return 'crashing';
        }

        public function supported(): bool
        {
            return true;
        }

        public function collect(): CollectionResult
        {
            throw new RuntimeException('boom');
        }
    };

    $snapshot = builder()->build([
        $crashing,
        collectorReturning(CollectionResult::ok('php', ['version' => '8.3.2'])),
    ]);

    expect($snapshot->result('crashing')?->status)->toBe('failed')
        ->and($snapshot->result('crashing')?->error)->toBe('boom')
        ->and($snapshot->result('php')?->status)->toBe('ok');
});

test('a collector throwing inside supported() is also isolated', function () {
    $broken = new class implements Collector
    {
        public function key(): string
        {
            return 'broken';
        }

        public function supported(): bool
        {
            throw new LogicException('cannot even check support');
        }

        public function collect(): CollectionResult
        {
            return CollectionResult::ok('broken');
        }
    };

    expect(builder()->build([$broken])->result('broken')?->status)->toBe('failed');
});

test('unsupported collectors are recorded without being executed', function () {
    $collector = new class implements Collector
    {
        public bool $executed = false;

        public function key(): string
        {
            return 'nginx';
        }

        public function supported(): bool
        {
            return false;
        }

        public function collect(): CollectionResult
        {
            $this->executed = true;

            return CollectionResult::ok('nginx');
        }
    };

    $snapshot = builder()->build([$collector]);

    expect($snapshot->result('nginx')?->status)->toBe('unsupported')
        ->and($collector->executed)->toBeFalse();
});

test('every metric is annotated with its collection duration', function () {
    $snapshot = builder()->build([
        collectorReturning(CollectionResult::ok('php', ['version' => '8.3.2'])),
        collectorReturning(CollectionResult::ok('nginx'), supported: false),
    ]);

    expect($snapshot->result('php')?->durationMs)->toBeFloat()
        ->and($snapshot->result('php')?->durationMs)->toBeGreaterThanOrEqual(0.0)
        ->and($snapshot->result('nginx')?->durationMs)->toBeFloat()
        ->and($snapshot->result('php')?->toArray())->toHaveKey('duration_ms');
});

test('custom data closures are evaluated lazily and fault-isolated', function () {
    $snapshot = builder()->build([], [
        'static' => 'value',
        'lazy' => fn (): array => ['computed' => true],
        'failing' => function (): void {
            throw new RuntimeException('provider broke');
        },
    ]);

    expect($snapshot->customData())->toBe([
        'static' => 'value',
        'lazy' => ['computed' => true],
        'failing' => ['error' => 'provider broke'],
    ]);
});

test('callable-looking strings are kept as plain values', function () {
    $snapshot = builder()->build([], ['name' => 'date', 'function' => 'strtoupper']);

    expect($snapshot->customData())->toBe(['name' => 'date', 'function' => 'strtoupper']);
});
