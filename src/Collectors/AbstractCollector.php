<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Technology;

/**
 * Convenience base class: collectors extending it only need key() and
 * collect(); supported() defaults to true.
 */
abstract class AbstractCollector implements Collector
{
    public function supported(): bool
    {
        return true;
    }

    abstract public function key(): string;

    abstract public function collect(): CollectionResult;

    /**
     * Standard shape for a metric describing a detected technology. The
     * "technology_source" key is only added for fallback slugs, so dashboards
     * can tell pinned endoflife.date slugs from best-effort identifiers.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function technologyData(Technology $technology, ?string $version, array $extra = []): array
    {
        $data = [
            'technology' => $technology->slug,
            'version' => $version,
        ];

        if (! $technology->isKnown()) {
            $data['technology_source'] = $technology->source;
        }

        return array_merge($data, $extra);
    }

    /**
     * Compact single-line excerpt of command output for error messages.
     */
    protected function excerpt(string $output, int $limit = 120): string
    {
        $singleLine = (string) preg_replace('/\s+/', ' ', trim($output));

        return mb_strlen($singleLine) > $limit
            ? mb_substr($singleLine, 0, $limit).'…'
            : $singleLine;
    }
}
