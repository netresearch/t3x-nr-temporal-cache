<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

/**
 * ES module import map for nr_temporal_cache.
 *
 * Maps the '@netresearch/nr-temporal-cache/' specifier prefix to the public
 * JavaScript directory so PageRenderer::loadJavaScriptModule() can resolve
 * '@netresearch/nr-temporal-cache/backend-module.js' in the backend module.
 */
return [
    'dependencies' => ['backend'],
    'imports' => [
        '@netresearch/nr-temporal-cache/' => 'EXT:nr_temporal_cache/Resources/Public/JavaScript/',
    ],
];
