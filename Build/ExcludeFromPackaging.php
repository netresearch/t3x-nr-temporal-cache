<?php

declare(strict_types=1);

/*
 * Packaging exclude list for `tailor ter:publish`, selected via the
 * TYPO3_EXCLUDE_FROM_PACKAGING environment variable.
 *
 * Why this file exists
 * --------------------
 * tailor zips the WORKING DIRECTORY, not the git tree, and applies only this
 * list. It never reads .gitattributes, so `export-ignore` has no effect on the
 * TER artifact — which is how AGENTS.md, index.html, phpstatus, renovate.json
 * and crowdin.yml reached the published 0.9.0 zip.
 *
 * The override REPLACES tailor's own conf/ExcludeFromPackaging.php; nothing is
 * merged (VersionService::getExcludeConfiguration() returns the loaded array
 * verbatim). Rather than restate the upstream entries here, this file composes
 * them from TailorDefaults-1.7.0.php — a snapshot of the release
 * `typo3/tailor:^1` installs — so each upstream entry exists in exactly one
 * place and refreshing the snapshot is the whole update.
 *
 * Matching, per VersionService::createZipArchiveFromPath():
 *   directories  preg_match('/^' . $entry . '/i', $path)      root-anchored
 *                prefix match on the path relative to the extension root; the
 *                whole subtree is pruned. "build" also matches "buildkite".
 *   files        preg_match('/' . $entry . '$/i', $filename)  suffix match on
 *                the BASENAME, so a rule cannot be scoped to the root and
 *                applies at every depth. This is why leading dots are omitted:
 *                "gitignore" matches ".gitignore" by suffix.
 *
 * Entries are interpolated into the pattern RAW — tailor 1.7.0 has no
 * preg_quote() (quoteExcludePattern() exists only on main). Keep entries plain:
 * a "." is a wildcard here and a literal "/" would terminate the delimiter.
 * Nested directories need the historic escaping, e.g. Resources\/Private\/Build.
 *
 * Deliberately NOT excluded, though .gitattributes export-ignores them for the
 * GitHub archive: README.md and CHANGELOG.md. Both belong in an extension
 * shipped to TER, where they are what a reader finds after unpacking.
 */

$tailorDefaults = require __DIR__ . '/TailorDefaults-1.7.0.php';

return [
    'directories' => array_merge($tailorDefaults['directories'], [
        // Serena MCP server state and agent scratch output: developer-machine
        // artifacts with no meaning outside the checkout.
        '.serena',
        'claudedocs',
        // The rendered manual is published to docs.typo3.org; shipping a copy
        // would double the artifact and go stale against it.
        'Documentation-GENERATED-temp',
    ]),
    'files' => array_merge($tailorDefaults['files'], [
        // Agent instructions. AGENTS.md matches the scoped copies in Classes/,
        // Tests/ and elsewhere too, which is intended — the suffix match on the
        // basename cannot be scoped to the root anyway.
        'AGENTS.md',
        'CLAUDE.md',
        // Local development only: the DDEV landing page, the container
        // healthcheck endpoint (answered from DocumentRoot /var/www/html by
        // the customised .ddev/apache/apache-site.conf, which drops the stock
        // /phpstatus Alias — so the file is load-bearing in DDEV and must stay
        // in the repository), and the DDEV setup notes.
        // The basename match also catches Documentation/index.html, a
        // hand-written page the RST build does not reference; docs.typo3.org
        // renders from the sources, so nothing is lost by leaving it out.
        'index.html',
        'phpstatus',
        'DDEV_SETUP.md',
        // Repository tooling with no runtime meaning in an installed extension.
        'renovate.json',
        'codecov.yml',
        'fractor.php',
        // Upstream 1.7.0 knows only crowdin.yaml; this repository uses the
        // .yml spelling, which the default list therefore misses.
        'crowdin.yml',
    ]),
];
