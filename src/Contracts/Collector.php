<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\CollectionResult;

interface Collector
{
    /**
     * Stable, machine-readable key. Becomes the key under "metrics" in the
     * snapshot, e.g. "os", "database", "composer_packages". Must be unique
     * across all registered collectors.
     */
    public function key(): string;

    /**
     * Whether this collector can run in the current environment. Returning
     * false marks the metric as "unsupported" without executing collect().
     */
    public function supported(): bool;

    /**
     * Gather the data. Implementations may throw — SnapshotBuilder isolates and
     * records the failure — but should prefer returning CollectionResult::failed().
     */
    public function collect(): CollectionResult;
}
