<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Support;

/**
 * Maps a database driver name plus the raw server version string to a
 * technology identifier and a clean version. MariaDB notoriously hides behind
 * the mysql driver with versions like
 * "5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204". Shared by every plugin
 * that inspects a live connection (Laravel PDO, Craft/Yii, …).
 */
final class DatabaseVersion
{
    /**
     * @return array{0: string, 1: string|null} [technology identifier, version]
     */
    public static function normalize(string $driver, string $rawVersion): array
    {
        $version = preg_match('/\d+(?:\.\d+)+/', $rawVersion, $matches) === 1 ? $matches[0] : null;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            if ($driver === 'mariadb' || stripos($rawVersion, 'mariadb') !== false) {
                if (preg_match('/(\d+\.\d+\.\d+)-mariadb/i', $rawVersion, $matches) === 1) {
                    $version = $matches[1];
                }

                return ['mariadb', $version];
            }

            return ['mysql', $version];
        }

        if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
            return ['postgresql', $version];
        }

        if ($driver === 'sqlite') {
            return ['sqlite', $version];
        }

        if ($driver === 'sqlsrv' || $driver === 'mssql' || $driver === 'dblib') {
            return ['mssql-server', $version];
        }

        return [$driver !== '' && $driver !== 'unknown' ? $driver : 'unknown', $version];
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}
