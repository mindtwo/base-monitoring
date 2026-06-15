<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring;

use Mindtwo\Monitoring\Collectors\DefaultCollectors;
use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\TransportResult;
use Mindtwo\Monitoring\Exceptions\DuplicateCollectorKey;
use Throwable;

/**
 * The collector registry and entry point of the suite. Plugins register their
 * collectors here; snapshot() builds the payload, push() delivers it.
 */
final class Monitor
{
    /** @var array<string, Collector> keyed by collector key */
    private array $collectors = [];

    /** @var array<string, mixed> static values or lazy Closure providers */
    private array $customData = [];

    public function __construct(
        private SnapshotBuilder $builder,
        private ?Transport $transport = null
    ) {}

    /**
     * Convenience factory: a monitor with the full default collector catalog,
     * ready to inspect the project in $projectRoot (defaults to the working
     * directory).
     */
    public static function make(
        ?string $projectRoot = null,
        ?SnapshotFactory $factory = null,
        ?ProcessRunner $processRunner = null,
        ?Transport $transport = null
    ): self {
        $monitor = new self(new SnapshotBuilder($factory ?? new SnapshotFactory), $transport);

        return $monitor->register(...DefaultCollectors::make($processRunner, $projectRoot));
    }

    /**
     * @throws DuplicateCollectorKey when a collector key is already taken
     */
    public function register(Collector ...$collectors): self
    {
        foreach ($collectors as $collector) {
            if ($this->has($collector->key())) {
                throw DuplicateCollectorKey::forCollector($collector);
            }

            $this->collectors[$collector->key()] = $collector;
        }

        return $this;
    }

    /**
     * Register collectors, intentionally overriding any existing collector
     * with the same key (e.g. a plugin replacing the base "database" detection
     * with a live connection).
     */
    public function replace(Collector ...$collectors): self
    {
        foreach ($collectors as $collector) {
            $this->collectors[$collector->key()] = $collector;
        }

        return $this;
    }

    public function forget(string $key): self
    {
        unset($this->collectors[$key]);

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->collectors[$key]);
    }

    /**
     * @return array<string, Collector> keyed by collector key
     */
    public function collectors(): array
    {
        return $this->collectors;
    }

    /**
     * Attach additional payload data under "custom_data". Pass a Closure to
     * defer (and fault-isolate) evaluation until the snapshot is built.
     *
     * @param  mixed  $value
     */
    public function addCustomData(string $key, $value): self
    {
        $this->customData[$key] = $value;

        return $this;
    }

    public function snapshot(): Snapshot
    {
        return $this->builder->build(array_values($this->collectors), $this->customData);
    }

    public function push(?Transport $transport = null): TransportResult
    {
        $transport ??= $this->transport;

        if ($transport === null) {
            return TransportResult::failed(
                'No transport configured. Pass one to push() or set it when constructing the Monitor.'
            );
        }

        try {
            return $transport->send($this->snapshot());
        } catch (Throwable $exception) {
            return TransportResult::failed($exception->getMessage());
        }
    }
}
