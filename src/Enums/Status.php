<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Enums;

/**
 * Enum-like constant holder (the package floor is PHP 8.0, where native enums
 * do not exist). On PHP >= 8.1 this could become: enum Status: string { … }.
 */
final class Status
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const UNSUPPORTED = 'unsupported';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::OK,
            self::WARNING,
            self::FAILED,
            self::SKIPPED,
            self::UNSUPPORTED,
        ];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    private function __construct()
    {
        // Static constant holder — never instantiated.
    }
}
