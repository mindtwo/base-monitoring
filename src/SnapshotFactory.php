<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\Source;

/**
 * Creates empty snapshots carrying the static context of this installation
 * (source, environment, project key) with a UTC collection timestamp.
 */
final class SnapshotFactory
{
    private Source $source;

    public function __construct(
        ?Source $source = null,
        private string $environment = 'production',
        private ?string $projectKey = null,
        private ?DateTimeImmutable $clock = null
    ) {
        $this->source = $source ?? Source::library();
    }

    public function make(): Snapshot
    {
        $now = $this->clock ?? new DateTimeImmutable('now');

        return new Snapshot(
            Snapshot::SCHEMA_VERSION,
            $now->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
            $this->environment,
            $this->source,
            $this->projectKey
        );
    }
}
