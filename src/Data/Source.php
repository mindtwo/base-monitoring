<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Data;

use Mindtwo\Monitoring\Support\InstalledVersion;
use Mindtwo\Monitoring\Support\ServerIp;

/**
 * Identifies the producer of a snapshot, so the central dashboard knows which
 * plugin and which version reported, and from which host (server IP).
 */
final class Source
{
    public const TYPE_LIBRARY = 'library';

    public const TYPE_LARAVEL = 'laravel';

    public const TYPE_WORDPRESS = 'wordpress';

    public const TYPE_CRAFT = 'craft';

    public const TYPE_SERVER = 'server';

    public function __construct(
        public string $type,
        public string $package,
        public string $version,
        public string $baseVersion,
        public ?string $serverIp = null
    ) {}

    /**
     * Source for direct (framework-less) usage of the base package.
     */
    public static function library(): self
    {
        $baseVersion = InstalledVersion::of('mindtwo/base-monitoring');

        return new self(self::TYPE_LIBRARY, 'mindtwo/base-monitoring', $baseVersion, $baseVersion, ServerIp::detect());
    }

    /**
     * Source for a framework plugin built on top of the base package.
     */
    public static function plugin(string $type, string $package): self
    {
        return new self(
            $type,
            $package,
            InstalledVersion::of($package),
            InstalledVersion::of('mindtwo/base-monitoring'),
            ServerIp::detect()
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'package' => $this->package,
            'version' => $this->version,
            'base_version' => $this->baseVersion,
            'server_ip' => $this->serverIp,
        ];
    }
}
