<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Support;

/**
 * Best-effort detection of the host's primary IP address, resolved identically
 * in CLI (push) and web/php-fpm (pull) contexts so a snapshot reports the same
 * server regardless of how it was produced. Returns null when no usable address
 * can be determined.
 */
final class ServerIp
{
    public static function detect(): ?string
    {
        return self::viaDefaultRoute() ?? self::viaHostname();
    }

    /**
     * The source address the OS would use to reach a public destination — i.e.
     * the primary outbound interface. "Connecting" a UDP socket makes the kernel
     * select that address without sending a single packet, so this needs no
     * network round-trip and no socket extension.
     */
    private static function viaDefaultRoute(): ?string
    {
        // 192.0.2.0/24 is TEST-NET-1 (RFC 5737): never routed on the internet,
        // used only to make the kernel pick the default interface's source IP.
        $socket = @stream_socket_client('udp://192.0.2.1:53', $errno, $errstr, 1);

        if ($socket === false) {
            return null;
        }

        $local = @stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($local) || $local === '') {
            return null;
        }

        return self::usableIp(self::stripPort($local));
    }

    private static function viaHostname(): ?string
    {
        $hostname = gethostname();

        if ($hostname === false) {
            return null;
        }

        // gethostbyname() returns its input unchanged when resolution fails.
        return self::usableIp(gethostbyname($hostname));
    }

    /**
     * Drops the trailing ":port" from a "host:port" pair as returned by
     * stream_socket_get_name(), handling bracketed IPv6 and bare addresses.
     */
    private static function stripPort(string $address): string
    {
        if (str_starts_with($address, '[')) {
            $end = strpos($address, ']');

            return $end === false ? $address : substr($address, 1, $end - 1);
        }

        // A single colon means IPv4 with a port; multiple colons mean a bare
        // IPv6 address that must be left intact.
        if (substr_count($address, ':') === 1) {
            $separator = (int) strrpos($address, ':');

            return substr($address, 0, $separator);
        }

        return $address;
    }

    private static function usableIp(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (in_array($ip, ['0.0.0.0', '::'], true) || $ip === '::1' || str_starts_with($ip, '127.')) {
            return null;
        }

        return $ip;
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}
