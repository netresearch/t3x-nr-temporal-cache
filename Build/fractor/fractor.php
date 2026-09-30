<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\FractorTypo3\Set\Typo3LevelSetList;
use a9f\FractorTypo3\Set\Typo3SetList;

return FractorConfiguration::configure()
    ->withPaths([
        __DIR__ . '/../../Classes',
        __DIR__ . '/../../Configuration',
    ])
    ->withSkip([
        __DIR__ . '/../../.Build',
    ])
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_12,
        Typo3SetList::TYPO3_13,
    ])
    ->withPhpSets(
        phpVersion: 80100 // PHP 8.1+
    );
