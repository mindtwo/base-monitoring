<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Technology;

use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\Technology;

/**
 * Resolves identifiers to technology slugs against the pinned endoflife.date
 * registry — fully offline. Unknown identifiers degrade to best-effort slugs
 * derived from the package or "org/repo" name (see docs/technology-slugs.md).
 */
final class EndOfLifeTechnologyResolver implements TechnologyResolver
{
    /** @var array<string, true> */
    private array $known;

    /** @var array<string, string> */
    private array $aliases;

    /**
     * @param  array<int, string>  $slugs
     * @param  array<string, string>  $aliases  normalized detector output => canonical slug
     */
    public function __construct(array $slugs, array $aliases = [])
    {
        $this->known = array_fill_keys($slugs, true);
        $this->aliases = $aliases;
    }

    /**
     * Resolver with the pinned slug registry and the default alias map.
     */
    public static function default(): self
    {
        return new self(Slugs::all(), Aliases::all());
    }

    public function resolve(string $identifier): Technology
    {
        $slug = $this->normalize($identifier);

        if ($slug === '') {
            return new Technology('unknown', Technology::SOURCE_PACKAGE);
        }

        if (isset($this->known[$slug])) {
            return new Technology($slug, Technology::SOURCE_KNOWN);
        }

        $alias = $this->aliases[$slug] ?? null;

        if ($alias !== null && isset($this->known[$alias])) {
            return new Technology($alias, Technology::SOURCE_KNOWN);
        }

        if (str_contains($slug, '/')) {
            $segment = strrchr($slug, '/');
            $repository = $this->normalize($segment === false ? $slug : substr($segment, 1));

            if ($repository === '') {
                return new Technology('unknown', Technology::SOURCE_REPOSITORY);
            }

            return isset($this->known[$repository])
                ? new Technology($repository, Technology::SOURCE_KNOWN)
                : new Technology($repository, Technology::SOURCE_REPOSITORY);
        }

        return new Technology($slug, Technology::SOURCE_PACKAGE);
    }

    public function isKnown(string $slug): bool
    {
        return isset($this->known[$this->normalize($slug)]);
    }

    /**
     * @return array<int, string>
     */
    public function knownSlugs(): array
    {
        return array_keys($this->known);
    }

    /**
     * Lowercase; strip a leading "@"; turn whitespace, dots and underscores
     * into hyphens; collapse repeats; trim stray hyphens. Slashes survive so
     * "org/repo" identifiers remain recognizable.
     */
    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = ltrim($value, '@');
        $value = (string) preg_replace('/[\s._]+/', '-', $value);
        $value = (string) preg_replace('/-+/', '-', $value);

        return trim($value, '-');
    }
}
