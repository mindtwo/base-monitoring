<?php

declare(strict_types=1);

test('all source files declare strict types')
    ->expect('Mindtwo\Monitoring')
    ->toUseStrictTypes();

test('the core never depends on a framework')
    ->expect('Mindtwo\Monitoring')
    ->not->toUse(['Illuminate', 'Laravel']);

test('contracts are interfaces')
    ->expect('Mindtwo\Monitoring\Contracts')
    ->toBeInterfaces();

test('data objects are final')
    ->expect('Mindtwo\Monitoring\Data')
    ->toBeFinal();

test('no debug or dangerous shell helpers are used')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'exec', 'shell_exec', 'system', 'passthru', 'popen'])
    ->not->toBeUsed();
