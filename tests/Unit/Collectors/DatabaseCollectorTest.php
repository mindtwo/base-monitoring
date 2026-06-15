<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\DatabaseCollector;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;

test('mysql client output is parsed', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('mysql --version', "mysql  Ver 8.0.36 for macos14.2 on arm64 (Homebrew)\n");

    $result = (new DatabaseCollector($runner, finderWith(['mysql'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('mysql')
        ->and($result->data['version'])->toBe('8.0.36')
        ->and($result->data['detected_via'])->toBe('cli')
        ->and($result->data['client'])->toBe('mysql');
});

test('a mariadb distribution behind the mysql binary is recognized', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('mysql --version', "mysql  Ver 15.1 Distrib 10.11.6-MariaDB, for debian-linux-gnu (x86_64)\n");

    $result = (new DatabaseCollector($runner, finderWith(['mysql'])))->collect();

    expect($result->data['technology'])->toBe('mariadb')
        ->and($result->data['version'])->toBe('10.11.6');
});

test('the modern mariadb client is recognized', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('mariadb --version', "mariadb from 11.4.2-MariaDB, client 15.2 for debian-linux-gnu\n");

    $result = (new DatabaseCollector($runner, finderWith(['mariadb'])))->collect();

    expect($result->data['technology'])->toBe('mariadb')
        ->and($result->data['version'])->toBe('11.4.2');
});

test('postgres client output is parsed', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('psql --version', "psql (PostgreSQL) 16.2 (Ubuntu 16.2-1.pgdg22.04+1)\n");

    $result = (new DatabaseCollector($runner, finderWith(['psql'])))->collect();

    expect($result->data['technology'])->toBe('postgresql')
        ->and($result->data['version'])->toBe('16.2');
});

test('sqlite client output is parsed', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('sqlite3 --version', "3.45.1 2024-01-30 16:01:20 e876e51a0ed5c5b3126f52e532044363a014bc594cfefa87ffb5b82257cc467a\n");

    $result = (new DatabaseCollector($runner, finderWith(['sqlite3'])))->collect();

    expect($result->data['technology'])->toBe('sqlite')
        ->and($result->data['version'])->toBe('3.45.1');
});

test('the first available client wins (mysql before psql)', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('mysql --version', "mysql  Ver 8.0.36\n")
        ->onOutput('psql --version', "psql (PostgreSQL) 16.2\n");

    $result = (new DatabaseCollector($runner, finderWith(['mysql', 'psql'])))->collect();

    expect($result->data['technology'])->toBe('mysql');
});

test('no client binaries means unsupported', function () {
    $collector = new DatabaseCollector(new FakeProcessRunner, new ExecutableFinder('', []));

    expect($collector->supported())->toBeFalse()
        ->and($collector->collect()->status)->toBe('unsupported');
});
