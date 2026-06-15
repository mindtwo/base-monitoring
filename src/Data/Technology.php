<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

/**
 * A normalized technology slug plus the provenance of its resolution.
 *
 * Deliberately a value object instead of a native enum: detection routinely
 * encounters software without an endoflife.date slug, and those must still be
 * representable (via package/repository-derived fallback slugs).
 */
final class Technology
{
    /** Matched the endoflife.date registry — safe to join against EOL data. */
    public const SOURCE_KNOWN = 'known';

    /** Derived from a package name — best-effort identifier. */
    public const SOURCE_PACKAGE = 'package';

    /** Derived from an "org/repo" name — best-effort identifier. */
    public const SOURCE_REPOSITORY = 'repository';

    public function __construct(
        public string $slug,
        public string $source = self::SOURCE_KNOWN
    ) {}

    public function isKnown(): bool
    {
        return $this->source === self::SOURCE_KNOWN;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'source' => $this->source,
        ];
    }
}
