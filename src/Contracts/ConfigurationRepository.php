<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\Credentials;

/**
 * Per-framework configuration source. Implementations resolve values in the
 * order: admin backend → environment variables → secure defaults.
 */
interface ConfigurationRepository
{
    public function credentials(): Credentials;

    /**
     * Target endpoint; falls back to the central default base URL.
     */
    public function endpoint(): string;

    /**
     * @return array<int, string> Optional IP allow-list (plain IPs or CIDR ranges) for the pull endpoint.
     */
    public function ipAllowList(): array;

    /**
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null);
}
