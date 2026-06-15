<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

final class NginxCollector extends BinaryVersionCollector
{
    public function key(): string
    {
        return 'nginx';
    }

    protected function binaries(): array
    {
        return ['nginx'];
    }

    protected function arguments(): array
    {
        // nginx prints its version to stderr.
        return ['-v'];
    }

    protected function technologyIdentifier(): string
    {
        return 'nginx';
    }

    protected function parseVersion(string $output): ?string
    {
        // "nginx version: nginx/1.24.0 (Ubuntu)"
        return preg_match('#nginx/(\d+(?:\.\d+)*)#', $output, $matches) === 1 ? $matches[1] : null;
    }
}
