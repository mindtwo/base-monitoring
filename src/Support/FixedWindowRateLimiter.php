<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Support;

/**
 * Minimal fixed-window rate limiter over an injectable key/value store, so
 * every plugin can throttle its pull endpoint with whatever cache its
 * framework provides (WP transients, Craft cache, a temp file, an array).
 */
final class FixedWindowRateLimiter
{
    /** @var callable(string): mixed */
    private $read;

    /** @var callable(string, mixed, int): void */
    private $write;

    /** @var (callable(): int)|null */
    private $clock;

    /**
     * @param  callable(string): mixed  $read  returns the stored value or null
     * @param  callable(string, mixed, int): void  $write  stores a value with a TTL in seconds
     * @param  (callable(): int)|null  $clock  Unix-timestamp source, injectable for tests
     */
    public function __construct(
        callable $read,
        callable $write,
        private int $maxAttempts = 10,
        private int $windowSeconds = 60,
        ?callable $clock = null
    ) {
        $this->read = $read;
        $this->write = $write;
        $this->clock = $clock;
    }

    /**
     * Records an attempt for $key and reports whether the limit is exceeded.
     */
    public function tooManyAttempts(string $key): bool
    {
        $now = $this->clock !== null ? ($this->clock)() : time();
        $window = intdiv($now, max(1, $this->windowSeconds));
        $storageKey = sprintf('mindtwo-monitoring|%s|%d', $key, $window);

        $attempts = ($this->read)($storageKey);
        $attempts = is_int($attempts) ? $attempts : 0;

        if ($attempts >= $this->maxAttempts) {
            return true;
        }

        ($this->write)($storageKey, $attempts + 1, $this->windowSeconds);

        return false;
    }
}
