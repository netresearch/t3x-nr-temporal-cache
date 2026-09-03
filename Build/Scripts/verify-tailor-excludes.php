<?php

declare(strict_types=1);

/*
 * Guards Build/ExcludeFromPackaging.php against upstream drift.
 *
 * That file REPLACES tailor's own conf/ExcludeFromPackaging.php rather than
 * merging with it, so it restates the upstream list in full. When tailor adds
 * an entry, our copy silently stops covering it and files reappear in the TER
 * zip. This script compares the two and fails when they diverge.
 *
 * Usage:
 *   php Build/Scripts/verify-tailor-excludes.php
 *   php Build/Scripts/verify-tailor-excludes.php /path/to/tailor/conf/ExcludeFromPackaging.php
 *
 * Without an argument it locates tailor in the usual global Composer paths and
 * skips (exit 0) when tailor is not installed, so the script is safe to run in
 * environments that have no tailor. Exit 1 means real drift.
 */

$ours = __DIR__ . '/../ExcludeFromPackaging.php';
if (!is_file($ours)) {
    fwrite(STDERR, "Missing {$ours}\n");
    exit(1);
}

$candidates = $argv[1] ?? null
    ? [$argv[1]]
    : [
        getenv('HOME') . '/.composer/vendor/typo3/tailor/conf/ExcludeFromPackaging.php',
        getenv('HOME') . '/.config/composer/vendor/typo3/tailor/conf/ExcludeFromPackaging.php',
        __DIR__ . '/../../.Build/vendor/typo3/tailor/conf/ExcludeFromPackaging.php',
    ];

$upstreamFile = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $upstreamFile = $candidate;
        break;
    }
}

if ($upstreamFile === null) {
    echo "tailor not installed — nothing to compare. Install with:\n";
    echo "  composer global require typo3/tailor:^1\n";
    exit(0);
}

$upstream = require $upstreamFile;
$mine = require $ours;

foreach (['directories', 'files'] as $key) {
    if (!isset($upstream[$key], $mine[$key])) {
        fwrite(STDERR, "Both files must define '{$key}'\n");
        exit(1);
    }
}

$exit = 0;
foreach (['directories', 'files'] as $key) {
    $missing = array_values(array_diff($upstream[$key], $mine[$key]));
    if ($missing !== []) {
        $exit = 1;
        fwrite(STDERR, sprintf(
            "Upstream tailor excludes %d %s that %s does not:\n  %s\n"
            . "Add them to the \"tailor defaults, verbatim\" block.\n\n",
            count($missing),
            $key,
            'Build/ExcludeFromPackaging.php',
            implode("\n  ", $missing)
        ));
    }
}

if ($exit === 0) {
    printf(
        "Build/ExcludeFromPackaging.php covers every upstream entry (%s).\n"
        . "  directories: %d upstream, %d ours\n  files:       %d upstream, %d ours\n",
        $upstreamFile,
        count($upstream['directories']),
        count($mine['directories']),
        count($upstream['files']),
        count($mine['files'])
    );
}

exit($exit);
