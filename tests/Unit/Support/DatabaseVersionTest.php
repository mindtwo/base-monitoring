<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Support\DatabaseVersion;

test('driver and raw server versions normalize to technology identifiers', function (string $driver, string $raw, string $technology, ?string $version) {
    expect(DatabaseVersion::normalize($driver, $raw))->toBe([$technology, $version]);
})->with([
    'mysql' => ['mysql', '8.0.36', 'mysql', '8.0.36'],
    'mariadb behind mysql driver' => ['mysql', '5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204', 'mariadb', '10.11.6'],
    'native mariadb driver' => ['mariadb', '11.4.2-MariaDB', 'mariadb', '11.4.2'],
    'postgres' => ['pgsql', '16.2 (Debian 16.2-1.pgdg120+2)', 'postgresql', '16.2'],
    'sqlite' => ['sqlite', '3.45.1', 'sqlite', '3.45.1'],
    'sqlserver' => ['sqlsrv', '15.00.4430', 'mssql-server', '15.00.4430'],
    'unknown driver' => ['mongodb', '7.0.5', 'mongodb', '7.0.5'],
    'empty driver' => ['', 'x', 'unknown', null],
    'no version' => ['mysql', 'unparseable', 'mysql', null],
]);
