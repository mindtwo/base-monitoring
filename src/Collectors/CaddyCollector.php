<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

final class CaddyCollector extends BinaryVersionCollector
{
    public function key(): string
    {
        return 'caddy';
    }

    protected function binaries(): array
    {
        return ['caddy'];
    }

    protected function arguments(): array
    {
        return ['version'];
    }

    protected function technologyIdentifier(): string
    {
        return 'caddy';
    }

    protected function parseVersion(string $output): ?string
    {
        // "v2.7.6 h1:w0NymbG2m9PcvKWsrXO6EEkY9Ru4FJK8uQbYcev1p3A="
        return preg_match('/\bv?(\d+\.\d+(?:\.\d+)*)\b/', $output, $matches) === 1 ? $matches[1] : null;
    }
}
