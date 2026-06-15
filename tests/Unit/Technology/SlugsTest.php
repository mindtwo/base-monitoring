<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Technology\Aliases;
use Mindtwo\Monitoring\Technology\Slugs;

test('the registry is well-formed: lowercase, unique and sorted', function () {
    $slugs = Slugs::all();

    expect($slugs)->not->toBeEmpty()
        ->and(count($slugs))->toBeGreaterThan(300)
        ->and($slugs)->toBe(array_values(array_unique($slugs)));

    $sorted = $slugs;
    sort($sorted, SORT_STRING);

    expect($slugs)->toBe($sorted);

    foreach ($slugs as $slug) {
        expect($slug)->toMatch('/^[a-z0-9][a-z0-9-]*$/');
    }
});

test('the registry contains the slugs the suite relies on', function () {
    $slugs = array_fill_keys(Slugs::all(), true);

    foreach ([
        'php', 'laravel', 'wordpress', 'typo3', 'craft-cms', 'composer',
        'nginx', 'apache-http-server', 'caddy', 'redis', 'nodejs',
        'mysql', 'mariadb', 'postgresql', 'sqlite',
        'ubuntu', 'debian', 'almalinux', 'macos',
    ] as $required) {
        expect($slugs)->toHaveKey($required);
    }
});

test('every alias target is a known slug', function () {
    $slugs = array_fill_keys(Slugs::all(), true);

    foreach (Aliases::all() as $alias => $target) {
        expect($slugs)->toHaveKey($target, "Alias \"$alias\" points at unknown slug \"$target\"");
    }
});
