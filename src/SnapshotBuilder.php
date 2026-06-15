<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring;

use Closure;
use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Snapshot;
use Throwable;

/**
 * Runs every collector in isolation and assembles the snapshot. One failing or
 * unsupported collector can never abort the run — this is what makes the core
 * safe to execute on any environment.
 */
final class SnapshotBuilder
{
    public function __construct(private SnapshotFactory $factory) {}

    /**
     * @param  array<int, Collector>  $collectors
     * @param  array<string, mixed>  $customData  static values or lazy Closure providers, keyed by name
     */
    public function build(array $collectors, array $customData = []): Snapshot
    {
        $snapshot = $this->factory->make();

        foreach ($collectors as $collector) {
            $snapshot->add($this->runSafely($collector));
        }

        if ($customData !== []) {
            $snapshot->mergeCustomData($this->resolveCustomData($customData));
        }

        return $snapshot;
    }

    private function runSafely(Collector $collector): CollectionResult
    {
        $start = microtime(true);

        try {
            $result = $collector->supported()
                ? $collector->collect()
                : CollectionResult::unsupported($collector->key());
        } catch (Throwable $exception) {
            $result = CollectionResult::failed($collector->key(), $exception->getMessage());
        }

        return $result->withDuration((microtime(true) - $start) * 1000);
    }

    /**
     * Lazy providers are invoked guarded; a throwing provider is recorded as an
     * error under its key instead of aborting the snapshot. Only Closures and
     * invokable objects are executed — plain strings are kept as values so
     * accidentally callable strings (e.g. "date") are never invoked.
     *
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    private function resolveCustomData(array $customData): array
    {
        $resolved = [];

        foreach ($customData as $key => $value) {
            try {
                $isInvokable = $value instanceof Closure || (is_object($value) && is_callable($value));

                $resolved[$key] = $isInvokable ? $value() : $value;
            } catch (Throwable $exception) {
                $resolved[$key] = ['error' => $exception->getMessage()];
            }
        }

        return $resolved;
    }
}
