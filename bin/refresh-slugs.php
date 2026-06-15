<?php

declare(strict_types=1);

/**
 * Regenerates src/Technology/Slugs.php from the endoflife.date release data.
 *
 * Usage: composer refresh-slugs
 *
 * The slug list is pinned in code on purpose: resolution must stay offline,
 * deterministic and reproducible per release. Run this script, review the
 * diff, commit, and tag a release. See docs/technology-slugs.md.
 */
$endpoint = 'https://api.github.com/repos/endoflife-date/release-data/contents/releases';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", [
            'User-Agent: mindtwo/base-monitoring slug refresh',
            'Accept: application/vnd.github+json',
        ]),
        'timeout' => 30,
    ],
]);

fwrite(STDOUT, "Fetching slug list from endoflife-date/release-data …\n");

$response = file_get_contents($endpoint, false, $context);

if ($response === false) {
    fwrite(STDERR, "Unable to reach the GitHub contents API.\n");
    exit(1);
}

/** @var mixed $entries */
$entries = json_decode($response, true);

if (! is_array($entries)) {
    fwrite(STDERR, "Unexpected response from the GitHub contents API.\n");
    exit(1);
}

$slugs = [];

foreach ($entries as $entry) {
    if (! is_array($entry) || ! isset($entry['name']) || ! is_string($entry['name'])) {
        continue;
    }

    if (! str_ends_with($entry['name'], '.json')) {
        continue;
    }

    $slug = substr($entry['name'], 0, -5);

    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1) {
        $slugs[] = $slug;
    }
}

if (count($slugs) < 100) {
    fwrite(STDERR, sprintf("Only %d slugs found — refusing to overwrite the registry.\n", count($slugs)));
    exit(1);
}

$slugs = array_values(array_unique($slugs));
sort($slugs, SORT_STRING);

$lines = '';

foreach (array_chunk($slugs, 6) as $chunk) {
    $lines .= "            '".implode("',\n            '", $chunk)."',\n";
}

$template = <<<PHP
<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Technology;

/**
 * Pinned registry of known technology slugs, generated from the
 * endoflife.date release data (https://endoflife.date). Resolution against
 * this list is offline and deterministic.
 *
 * Do not edit by hand — regenerate via `composer refresh-slugs`
 * (see bin/refresh-slugs.php and docs/technology-slugs.md).
 */
final class Slugs
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
{$lines}        ];
    }

    private function __construct()
    {
        // Static registry — never instantiated.
    }
}

PHP;

$target = __DIR__.'/../src/Technology/Slugs.php';

file_put_contents($target, $template);

fwrite(STDOUT, sprintf("Wrote %d slugs to %s\n", count($slugs), realpath($target) ?: $target));
exit(0);
