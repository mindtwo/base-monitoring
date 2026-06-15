<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Exceptions;

use InvalidArgumentException;
use Mindtwo\Monitoring\Contracts\Collector;

final class DuplicateCollectorKey extends InvalidArgumentException implements MonitoringException
{
    public static function forCollector(Collector $collector): self
    {
        return new self(sprintf(
            'A collector with the key "%s" is already registered. Collector keys must be unique — '.
            'use Monitor::replace() to intentionally override an existing collector (%s).',
            $collector->key(),
            get_class($collector)
        ));
    }
}
