<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

final class RedisCollector extends BinaryVersionCollector
{
    public function key(): string
    {
        return 'redis';
    }

    protected function binaries(): array
    {
        return ['redis-server', 'redis-cli'];
    }

    protected function technologyIdentifier(): string
    {
        return 'redis';
    }

    protected function parseVersion(string $output): ?string
    {
        // "Redis server v=7.2.4 sha=00000000:0 malloc=libc bits=64" or "redis-cli 7.2.4"
        if (preg_match('/v=(\d+(?:\.\d+)+)/', $output, $matches) === 1) {
            return $matches[1];
        }

        return preg_match('/redis-cli (\d+(?:\.\d+)+)/i', $output, $matches) === 1 ? $matches[1] : null;
    }
}
