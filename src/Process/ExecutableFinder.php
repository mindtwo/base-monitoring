<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Process;

/**
 * Locates binaries without shelling out: scans the PATH plus common system
 * directories (web server binaries often live in sbin directories that are
 * missing from PHP's PATH).
 */
final class ExecutableFinder
{
    private const FALLBACK_DIRECTORIES = [
        '/usr/local/sbin',
        '/usr/local/bin',
        '/usr/sbin',
        '/usr/bin',
        '/sbin',
        '/bin',
        '/opt/homebrew/bin',
        '/opt/homebrew/sbin',
    ];

    /** @var array<int, string> */
    private array $directories;

    /**
     * @param  array<int, string>|null  $fallbackDirectories
     */
    public function __construct(?string $path = null, ?array $fallbackDirectories = null)
    {
        $path ??= (string) getenv('PATH');

        $directories = array_merge(
            explode(PATH_SEPARATOR, $path),
            $fallbackDirectories ?? self::FALLBACK_DIRECTORIES
        );

        $this->directories = array_values(array_unique(array_filter(
            $directories,
            static fn (string $directory): bool => trim($directory) !== ''
        )));
    }

    public function find(string $binary): ?string
    {
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return $this->isExecutableFile($binary) ? $binary : null;
        }

        foreach ($this->directories as $directory) {
            foreach ($this->candidateNames($binary) as $name) {
                $candidate = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;

                if ($this->isExecutableFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function exists(string $binary): bool
    {
        return $this->find($binary) !== null;
    }

    /**
     * @return array<int, string>
     */
    private function candidateNames(string $binary): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [$binary];
        }

        return [$binary.'.exe', $binary.'.bat', $binary.'.cmd', $binary];
    }

    private function isExecutableFile(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }
}
