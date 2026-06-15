<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Contracts;

use Mindtwo\Monitoring\Data\Technology;

/**
 * Normalizes detected software to a technology slug. See docs/technology-slugs.md
 * for the resolution algorithm and the pinned slug registry.
 */
interface TechnologyResolver
{
    /**
     * Resolve a known slug, a package name, or "org/repo" to a Technology.
     */
    public function resolve(string $identifier): Technology;

    public function isKnown(string $slug): bool;

    /**
     * @return array<int, string>
     */
    public function knownSlugs(): array;
}
