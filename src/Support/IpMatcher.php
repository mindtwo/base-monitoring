<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Support;

/**
 * Matches client IPs against an allow-list of plain addresses and CIDR ranges
 * (IPv4 and IPv6). Shared by every plugin's pull endpoint protection.
 */
final class IpMatcher
{
    /**
     * Whether $ip passes the allow-list. An empty list means the allow-list
     * feature is unused and every IP is allowed; invalid entries are ignored.
     *
     * @param  array<int, string>  $allowList
     */
    public static function allows(string $ip, array $allowList): bool
    {
        if ($allowList === []) {
            return true;
        }

        return self::matches($ip, $allowList);
    }

    /**
     * Whether $ip matches at least one entry of the list.
     *
     * @param  array<int, string>  $list
     */
    public static function matches(string $ip, array $list): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return false;
        }

        foreach ($list as $entry) {
            if (self::matchesEntry($binary, trim($entry))) {
                return true;
            }
        }

        return false;
    }

    private static function matchesEntry(string $ipBinary, string $entry): bool
    {
        if ($entry === '') {
            return false;
        }

        if (! str_contains($entry, '/')) {
            $entryBinary = @inet_pton($entry);

            return $entryBinary !== false && hash_equals($entryBinary, $ipBinary);
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        if (preg_match('/^\d{1,3}$/', $bits) !== 1) {
            return false;
        }

        $subnetBinary = @inet_pton(trim($subnet));
        $prefixLength = (int) $bits;

        if ($subnetBinary === false || strlen($subnetBinary) !== strlen($ipBinary)) {
            return false;
        }

        $maxBits = strlen($subnetBinary) * 8;

        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        if ($prefixLength === 0) {
            return true;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}
