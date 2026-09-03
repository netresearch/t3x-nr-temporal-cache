<?php

declare(strict_types=1);

/*
 * Guards Build/ExcludeFromPackaging.php against drift from tailor's own list.
 *
 * That file REPLACES tailor's conf/ExcludeFromPackaging.php rather than merging
 * with it (VersionService::getExcludeConfiguration() returns the loaded array
 * verbatim), so it restates the upstream list in full. Two ways that goes wrong:
 *
 *   - tailor ADDS an entry and our copy stops covering it, so the file
 *     reappears in the TER zip;
 *   - tailor REMOVES an entry and we keep excluding a file that should ship.
 *
 * Both directions are checked, against Build/TailorDefaults-1.7.0.php — a
 * committed snapshot of the pinned release rather than whatever tailor happens
 * to be installed. That keeps the check meaningful in CI, where tailor is not a
 * dependency: comparing against an absent tool could only ever be skipped, and a
 * check that skips is a check that passes for the wrong reason.
 *
 * Entries this repository adds on top of the upstream list are expected and are
 * reported for information, not as failures.
 *
 * Usage:
 *   php Build/Scripts/verify-tailor-excludes.php
 *   php Build/Scripts/verify-tailor-excludes.php /path/to/other/ExcludeFromPackaging.php
 *
 * The optional argument replaces the snapshot as the baseline — pass a freshly
 * downloaded upstream file to check the snapshot itself against a new release.
 */

$ours = __DIR__ . '/../ExcludeFromPackaging.php';
$snapshot = __DIR__ . '/../TailorDefaults-1.7.0.php';

// An explicit argument always wins, including "" and "0", which a truthiness
// test would drop back to the default baseline — the script would then compare
// against a different file and report success for it.
$baseline = array_key_exists(1, $argv) ? $argv[1] : $snapshot;

foreach (['baseline' => $baseline, 'exclude list' => $ours] as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$upstream = require $baseline;
$mine = require $ours;

foreach (['directories', 'files'] as $key) {
    if (!isset($upstream[$key], $mine[$key])) {
        fwrite(STDERR, "Both files must define '{$key}'\n");
        exit(1);
    }
}

$exit = 0;
foreach (['directories', 'files'] as $key) {
    // tailor added an entry we do not carry: it would ship in the TER zip.
    $missing = array_values(array_diff($upstream[$key], $mine[$key]));
    if ($missing !== []) {
        $exit = 1;
        fwrite(STDERR, sprintf(
            "Upstream excludes %d %s that Build/ExcludeFromPackaging.php does not:\n  %s\n"
            . "Add them to the \"tailor defaults, verbatim\" block.\n\n",
            count($missing),
            $key,
            implode("\n  ", $missing)
        ));
    }

    // Entries we carry beyond the upstream list. Most are this repository's own
    // additions and are fine; the ones that matter are entries tailor has since
    // REMOVED, which would keep a file out of the zip that should now ship.
    // The two cannot be told apart mechanically, so they are listed for review
    // rather than failed on.
    $extra = array_values(array_diff($mine[$key], $upstream[$key]));
    if ($extra !== []) {
        printf(
            "%d local %s beyond the baseline (expected for this repository's own"
            . " additions; check none of them is an entry tailor has removed):\n  %s\n\n",
            count($extra),
            $key,
            implode("\n  ", $extra)
        );
    }
}

if ($exit === 0) {
    printf(
        "Build/ExcludeFromPackaging.php covers every entry in %s\n"
        . "  directories: %d baseline, %d ours\n  files:       %d baseline, %d ours\n",
        basename($baseline),
        count($upstream['directories']),
        count($mine['directories']),
        count($upstream['files']),
        count($mine['files'])
    );
}

exit($exit);
