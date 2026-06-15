<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

final class ApacheCollector extends BinaryVersionCollector
{
    public function key(): string
    {
        return 'apache';
    }

    protected function binaries(): array
    {
        return ['apachectl', 'apache2ctl', 'httpd', 'apache2'];
    }

    protected function arguments(): array
    {
        return ['-v'];
    }

    protected function technologyIdentifier(): string
    {
        return 'apache';
    }

    protected function parseVersion(string $output): ?string
    {
        // "Server version: Apache/2.4.58 (Unix)"
        return preg_match('#Apache/(\d+(?:\.\d+)*)#i', $output, $matches) === 1 ? $matches[1] : null;
    }
}
