<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

$createConfig = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

$config = $createConfig(
    "SPDX-License-Identifier: GPL-2.0-or-later\nSPDX-FileCopyrightText: Netresearch DTT GmbH",
    __DIR__ . '/..',
);

// A snapshot of typo3/tailor's own exclude list, kept byte for byte and
// without a notice of ours (ce6f8ec); header_comment would add one.
$finder = $config->getFinder();
assert($finder instanceof PhpCsFixer\Finder);
$finder->notPath('Build/TailorDefaults-1.7.0.php');

return $config;
