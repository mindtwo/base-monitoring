<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * Looks up the installed version of a Composer package via the Composer
 * runtime API, degrading to "unknown" instead of throwing (e.g. when running
 * from a path repository without installed metadata).
 */
final class InstalledVersion
{
    public const UNKNOWN = 'unknown';

    public static function of(string $package): string
    {
        try {
            if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
                return self::UNKNOWN;
            }

            return InstalledVersions::getPrettyVersion($package) ?? self::UNKNOWN;
        } catch (Throwable $exception) {
            return self::UNKNOWN;
        }
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}
