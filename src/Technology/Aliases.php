<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Technology;

/**
 * Default alias map: what detectors and package ecosystems call a technology,
 * mapped to its canonical endoflife.date slug. Keys are normalized identifiers
 * (see EndOfLifeTechnologyResolver::normalize()). Extensible via the resolver
 * constructor.
 */
final class Aliases
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            // Web servers
            'apache' => 'apache-http-server',
            'apache2' => 'apache-http-server',
            'httpd' => 'apache-http-server',

            // Runtimes
            'node' => 'nodejs',
            'node-js' => 'nodejs',
            'golang' => 'go',

            // Databases
            'postgres' => 'postgresql',
            'pgsql' => 'postgresql',
            'mariadb-server' => 'mariadb',

            // Operating systems
            'darwin' => 'macos',
            'osx' => 'macos',
            'mac-os' => 'macos',

            // CMS / frameworks
            'craftcms' => 'craft-cms',
            'concrete5' => 'concrete-cms',
            'rails' => 'ruby-on-rails',
            'next' => 'nextjs',
            'vuejs' => 'vue',
            'vue-js' => 'vue',
            'tailwindcss' => 'tailwind-css',

            // Infrastructure
            'k8s' => 'kubernetes',
            'docker' => 'docker-engine',

            // Composer packages whose repository segment does not match their slug
            'laravel/framework' => 'laravel',
            'laravel/laravel' => 'laravel',
            'craftcms/cms' => 'craft-cms',
            'typo3/cms-core' => 'typo3',
            'shopware/core' => 'shopware',
            'shopware/platform' => 'shopware',
            'statamic/cms' => 'statamic',
            'symfony/framework-bundle' => 'symfony',
            'symfony/symfony' => 'symfony',
            'getkirby/cms' => 'kirby',

            // npm packages (scoped names lose their "@" during normalization)
            'angular/core' => 'angular',
            'vue/cli' => 'vue',
        ];
    }

    private function __construct()
    {
        // Static map — never instantiated.
    }
}
