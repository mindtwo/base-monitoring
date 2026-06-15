<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\TransportResult;

interface Transport
{
    /**
     * Deliver a snapshot to its destination. Implementations must never throw —
     * delivery problems are reported through the returned TransportResult.
     */
    public function send(Snapshot $snapshot): TransportResult;
}
