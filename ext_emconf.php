<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Temporal Cache Management',
    'description' => 'Automatic cache invalidation for time-based content (starttime/endtime), addressing Forge #14277, with three scoping and three timing strategies. Read the Performance chapter before deployment.',
    'category' => 'fe',
    'author' => 'Netresearch',
    'author_email' => '',
    'author_company' => 'Netresearch DTT GmbH',
    'state' => 'stable',
    'version' => '1.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.3.99',
            'php' => '8.1.0-8.5.99',
            'scheduler' => '12.4.0-14.3.99',
            'reports' => '12.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Netresearch\\TemporalCache\\' => 'Classes/',
        ],
    ],
];
