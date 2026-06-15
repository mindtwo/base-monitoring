<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * The PHP runtime itself — always supported, no process required.
 */
final class PhpCollector extends AbstractCollector
{
    private TechnologyResolver $technologies;

    public function __construct(?TechnologyResolver $technologies = null)
    {
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'php';
    }

    public function collect(): CollectionResult
    {
        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve('php'),
            PHP_VERSION,
            [
                'sapi' => PHP_SAPI,
                'memory_limit' => (string) ini_get('memory_limit'),
            ]
        ));
    }
}
