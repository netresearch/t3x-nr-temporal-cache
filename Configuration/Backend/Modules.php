<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;

/**
 * Backend module configuration for Temporal Cache.
 *
 * Registers the module in the Tools section of TYPO3 backend.
 */
return [
    'tools_TemporalCache' => [
        'parent' => 'tools',
        'position' => ['after' => 'tools_ExtensionmanagerExtensionmanager'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/tools/temporal-cache',
        'labels' => 'LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'TemporalCache',
        'iconIdentifier' => 'temporal-cache-module',
        'controllerActions' => [
            TemporalCacheController::class => [
                'dashboard',
                'content',
                'wizard',
                'harmonize',
            ],
        ],
    ],
];
