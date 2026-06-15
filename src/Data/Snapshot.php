<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

/**
 * The serializable monitoring payload. "metrics" is an open map — each
 * collector owns the shape under its key, so adding a collector adds a key
 * and never requires a schema migration.
 */
final class Snapshot
{
    public const SCHEMA_VERSION = '1.0';

    /** @var array<string, CollectionResult> keyed by collector key */
    private array $results = [];

    /** @var array<string, mixed> */
    private array $customData = [];

    public function __construct(
        public string $schemaVersion,
        public string $collectedAt,
        public string $environment,
        public Source $source,
        public ?string $projectKey = null
    ) {}

    public function add(CollectionResult $result): self
    {
        $this->results[$result->key] = $result;

        return $this;
    }

    /**
     * @return array<string, CollectionResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function result(string $key): ?CollectionResult
    {
        return $this->results[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mergeCustomData(array $data): self
    {
        $this->customData = array_merge($this->customData, $data);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function customData(): array
    {
        return $this->customData;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $metrics = [];

        foreach ($this->results as $key => $result) {
            $metrics[$key] = $result->toArray();
        }

        return [
            'schema_version' => $this->schemaVersion,
            'collected_at' => $this->collectedAt,
            'environment' => $this->environment,
            'project_key' => $this->projectKey,
            'source' => $this->source->toArray(),
            'metrics' => $metrics,
            'technologies' => $this->technologies(),
            'custom_data' => count($this->customData) === 0 ? new \stdClass : $this->customData,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode(
            $this->toArray(),
            $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Flattened {technology, version} list derived from metrics that detected
     * a technology, for convenient dashboard rendering.
     *
     * @return array<int, array<string, mixed>>
     */
    public function technologies(): array
    {
        $technologies = [];

        foreach ($this->results as $result) {
            if (! isset($result->data['technology']) || ! is_string($result->data['technology'])) {
                continue;
            }

            $technologies[] = [
                'technology' => $result->data['technology'],
                'version' => $result->data['version'] ?? null,
                'source' => $result->data['technology_source'] ?? Technology::SOURCE_KNOWN,
            ];
        }

        return $technologies;
    }
}
