<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

use Mindtwo\Monitoring\Enums\Status;

/**
 * Outcome of a single collector run. Treat instances as immutable.
 */
final class CollectionResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $key,
        public string $status,
        public array $data = [],
        public ?string $error = null,
        public ?float $durationMs = null
    ) {}

    /**
     * A copy of this result annotated with how long collection took — set by
     * the SnapshotBuilder so slow collectors are visible on the dashboard.
     */
    public function withDuration(float $durationMs): self
    {
        return new self($this->key, $this->status, $this->data, $this->error, round($durationMs, 2));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(string $key, array $data = []): self
    {
        return new self($key, Status::OK, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function warning(string $key, array $data = [], ?string $error = null): self
    {
        return new self($key, Status::WARNING, $data, $error);
    }

    public static function skipped(string $key, ?string $reason = null): self
    {
        return new self($key, Status::SKIPPED, [], $reason);
    }

    public static function unsupported(string $key): self
    {
        return new self($key, Status::UNSUPPORTED);
    }

    public static function failed(string $key, string $error): self
    {
        return new self($key, Status::FAILED, [], $error);
    }

    public function successful(): bool
    {
        return $this->status === Status::OK || $this->status === Status::WARNING;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = ['status' => $this->status];

        if ($this->error !== null) {
            $base['error'] = $this->error;
        }

        if ($this->durationMs !== null) {
            $base['duration_ms'] = $this->durationMs;
        }

        return array_merge($base, $this->data);
    }
}
