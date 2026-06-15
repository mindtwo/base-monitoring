<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

uses()
    ->afterEach(fn () => TemporaryDirectories::flush())
    ->in(__DIR__);

function fixturePath(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

function fixtureContents(string $name): string
{
    return (string) file_get_contents(fixturePath($name));
}
