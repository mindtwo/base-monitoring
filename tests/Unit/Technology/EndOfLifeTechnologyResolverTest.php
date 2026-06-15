<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Technology;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;
use Mindtwo\Monitoring\Technology\Slugs;

function resolver(): EndOfLifeTechnologyResolver
{
    return EndOfLifeTechnologyResolver::default();
}

test('known slugs resolve directly', function (string $identifier, string $slug) {
    $technology = resolver()->resolve($identifier);

    expect($technology->slug)->toBe($slug)
        ->and($technology->source)->toBe(Technology::SOURCE_KNOWN);
})->with([
    ['php', 'php'],
    ['Ubuntu', 'ubuntu'],
    [' nginx ', 'nginx'],
    ['MySQL', 'mysql'],
    ['Tailwind CSS', 'tailwind-css'],
]);

test('aliases map detector output to canonical slugs', function (string $identifier, string $slug) {
    $technology = resolver()->resolve($identifier);

    expect($technology->slug)->toBe($slug)
        ->and($technology->source)->toBe(Technology::SOURCE_KNOWN);
})->with([
    ['apache2', 'apache-http-server'],
    ['httpd', 'apache-http-server'],
    ['node', 'nodejs'],
    ['Node.js', 'nodejs'],
    ['postgres', 'postgresql'],
    ['darwin', 'macos'],
    ['k8s', 'kubernetes'],
    ['craftcms', 'craft-cms'],
    ['golang', 'go'],
]);

test('package identifiers with slashes resolve through alias or repository fallback', function () {
    $laravel = resolver()->resolve('laravel/framework');
    $symfonyConsole = resolver()->resolve('symfony/console');
    $internal = resolver()->resolve('mindtwo/laravel-quick-sort');

    expect($laravel->slug)->toBe('laravel')
        ->and($laravel->source)->toBe(Technology::SOURCE_KNOWN)
        ->and($symfonyConsole->slug)->toBe('console')
        ->and($symfonyConsole->source)->toBe(Technology::SOURCE_REPOSITORY)
        ->and($internal->slug)->toBe('laravel-quick-sort')
        ->and($internal->source)->toBe(Technology::SOURCE_REPOSITORY);
});

test('repository segments that are known slugs count as known', function () {
    $technology = resolver()->resolve('redis/redis');

    expect($technology->slug)->toBe('redis')
        ->and($technology->source)->toBe(Technology::SOURCE_KNOWN);
});

test('scoped npm packages lose their @ and resolve via alias', function () {
    $angular = resolver()->resolve('@angular/core');

    expect($angular->slug)->toBe('angular')
        ->and($angular->source)->toBe(Technology::SOURCE_KNOWN);
});

test('unknown bare names fall back to a package-derived slug', function () {
    $technology = resolver()->resolve('Some_Internal Tool');

    expect($technology->slug)->toBe('some-internal-tool')
        ->and($technology->source)->toBe(Technology::SOURCE_PACKAGE)
        ->and($technology->isKnown())->toBeFalse();
});

test('empty and degenerate identifiers resolve to unknown', function () {
    expect(resolver()->resolve('')->slug)->toBe('unknown')
        ->and(resolver()->resolve('---')->slug)->toBe('unknown')
        ->and(resolver()->resolve('@')->slug)->toBe('unknown');
});

test('isKnown normalizes before checking', function () {
    expect(resolver()->isKnown('PHP'))->toBeTrue()
        ->and(resolver()->isKnown('Tailwind CSS'))->toBeTrue()
        ->and(resolver()->isKnown('not-a-real-slug-xyz'))->toBeFalse();
});

test('knownSlugs returns the pinned registry', function () {
    expect(resolver()->knownSlugs())->toBe(Slugs::all());
});

test('custom aliases extend the default map', function () {
    $resolver = new EndOfLifeTechnologyResolver(Slugs::all(), ['my-fork' => 'php']);

    expect($resolver->resolve('my-fork')->slug)->toBe('php');
});

test('aliases pointing at unknown slugs are ignored', function () {
    $resolver = new EndOfLifeTechnologyResolver(['php'], ['weird' => 'not-in-registry']);
    $technology = $resolver->resolve('weird');

    expect($technology->slug)->toBe('weird')
        ->and($technology->source)->toBe(Technology::SOURCE_PACKAGE);
});
