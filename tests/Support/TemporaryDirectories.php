<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Creates throwaway directories (fake bin dirs, fake project roots) and wipes
 * them after each test.
 */
final class TemporaryDirectories
{
    /** @var array<int, string> */
    private static array $directories = [];

    public static function create(string $prefix = 'm2-monitoring'): string
    {
        $path = sys_get_temp_dir().'/'.uniqid($prefix.'-', true);

        mkdir($path, 0755, true);

        self::$directories[] = $path;

        return $path;
    }

    /**
     * A directory containing fake executable files with the given names —
     * meant to be discovered by an ExecutableFinder, never actually run.
     *
     * @param  array<int, string>  $binaries
     */
    public static function binDir(array $binaries): string
    {
        $dir = self::create('m2-bins');

        foreach ($binaries as $name) {
            file_put_contents($dir.'/'.$name, "#!/bin/sh\nexit 0\n");
            chmod($dir.'/'.$name, 0755);
        }

        return $dir;
    }

    public static function flush(): void
    {
        foreach (self::$directories as $directory) {
            self::delete($directory);
        }

        self::$directories = [];
    }

    private static function delete(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
