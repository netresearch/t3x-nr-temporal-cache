<?php

declare(strict_types=1);

/*
 * Checks the TER packaging exclude list against tailor's own.
 *
 * Build/ExcludeFromPackaging.php REPLACES tailor's conf/ExcludeFromPackaging.php
 * rather than merging with it (VersionService::getExcludeConfiguration() returns
 * the loaded array verbatim), so it must carry every upstream entry. It composes
 * them from Build/TailorDefaults-1.7.0.php, a snapshot of the release
 * `typo3/tailor:^1` installs, which makes coverage true by construction — the
 * remaining risk is the snapshot going stale against a newer tailor.
 *
 * So this script checks two things:
 *
 *   1. the composed list still covers every entry in the snapshot — cheap, and
 *      it catches an edit that drops the array_merge or mangles the include;
 *   2. with a baseline given as an argument, the snapshot against that file, in
 *      BOTH directions. tailor ADDING an entry means a file reappears in the
 *      zip; tailor REMOVING one means we keep excluding a file that should ship.
 *
 * Usage:
 *   php Build/Scripts/verify-tailor-excludes.php
 *   php Build/Scripts/verify-tailor-excludes.php path/to/upstream/ExcludeFromPackaging.php
 *
 * The second form is how a tailor upgrade is checked: download the new
 * conf/ExcludeFromPackaging.php and pass it. CI runs the first form, which needs
 * no tailor installation — comparing against an absent tool could only ever be
 * skipped, and a check that skips is a check that passes for the wrong reason.
 */

$ours = __DIR__ . '/../ExcludeFromPackaging.php';
$snapshot = __DIR__ . '/../TailorDefaults-1.7.0.php';

// An explicit argument always wins, including "" and "0", which a truthiness
// test would drop back to the snapshot — the script would then compare against
// a different file and report success for it.
$baseline = array_key_exists(1, $argv) ? $argv[1] : null;

foreach (['exclude list' => $ours, 'snapshot' => $snapshot] as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

if ($baseline !== null && !is_file($baseline)) {
    fwrite(STDERR, "Not a file: {$baseline}\n");
    exit(1);
}

$mine = require $ours;
$pinned = require $snapshot;

foreach (['ours' => $mine, 'snapshot' => $pinned] as $label => $config) {
    if (!isset($config['directories'], $config['files'])) {
        fwrite(STDERR, "The {$label} list must define 'directories' and 'files'\n");
        exit(1);
    }
}

$exit = 0;

// 1. The composed list must still carry every pinned entry.
foreach (['directories', 'files'] as $key) {
    $missing = array_values(array_diff($pinned[$key], $mine[$key]));
    if ($missing !== []) {
        $exit = 1;
        fwrite(STDERR, sprintf(
            "Build/ExcludeFromPackaging.php lost %d pinned %s:\n  %s\n"
            . "These ship in the TER zip until restored.\n\n",
            count($missing),
            $key,
            implode("\n  ", $missing)
        ));
    }
}

// 2. With a baseline, compare the snapshot against it in both directions.
if ($baseline !== null) {
    $upstream = require $baseline;

    if (!isset($upstream['directories'], $upstream['files'])) {
        fwrite(STDERR, "The baseline must define 'directories' and 'files'\n");
        exit(1);
    }

    foreach (['directories', 'files'] as $key) {
        $added = array_values(array_diff($upstream[$key], $pinned[$key]));
        if ($added !== []) {
            $exit = 1;
            fwrite(STDERR, sprintf(
                "tailor has ADDED %d %s since the snapshot:\n  %s\n"
                . "Refresh Build/TailorDefaults-1.7.0.php; these currently ship.\n\n",
                count($added),
                $key,
                implode("\n  ", $added)
            ));
        }

        $removed = array_values(array_diff($pinned[$key], $upstream[$key]));
        if ($removed !== []) {
            $exit = 1;
            fwrite(STDERR, sprintf(
                "tailor has REMOVED %d %s since the snapshot:\n  %s\n"
                . "Refresh the snapshot; we currently exclude files that should ship.\n\n",
                count($removed),
                $key,
                implode("\n  ", $removed)
            ));
        }
    }
}

if ($exit === 0) {
    printf(
        "Exclude list consistent with %s\n"
        . "  directories: %d pinned + %d local = %d\n"
        . "  files:       %d pinned + %d local = %d\n",
        basename($baseline ?? $snapshot),
        count($pinned['directories']),
        count($mine['directories']) - count($pinned['directories']),
        count($mine['directories']),
        count($pinned['files']),
        count($mine['files']) - count($pinned['files']),
        count($mine['files'])
    );
}

exit($exit);
