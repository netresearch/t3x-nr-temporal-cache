<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

// Included by Build/phpstan.neon. CI removes netresearch/typo3-ci-workflows
// on the TYPO3 ^12.4 cells (remove-dev-deps in .github/workflows/ci.yml),
// because its saschaegerer/phpstan-typo3 requires typo3/cms-core ^13.4.3.
// A plain NEON include of the shared config would fail there on a missing
// file, so the include is made only when the package is installed.
//
// phpstan/extension-installer is a direct dev dependency and stays on the
// ^12.4 cells. There it registers the PHPStan extensions that are still
// installed (saschaegerer/phpstan-typo3 1.x and bnf/phpstan-psr-container,
// the two this project analysed with before); the strict, deprecation,
// PHPUnit, phpat and ergebnis rule sets arrive only with the shared package
// and are not installed there. Without the package, the parameters the old
// config set keep the ^12.4 cells where they were. `max` rather than 10,
// because those cells resolve PHPStan 1.x, whose highest level is 9.
$shared = __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/phpstan/phpstan.neon';

if (is_file($shared)) {
    return ['includes' => [$shared]];
}

return [
    'parameters' => [
        'level'                     => 'max',
        'treatPhpDocTypesAsCertain' => false,
        // The explicit analyseAndScan key is needed here: a plain list that
        // works in NEON arrives empty from a PHP config file.
        'excludePaths' => ['analyseAndScan' => [
            __DIR__ . '/../.Build/*',
        ]],
    ],
];
