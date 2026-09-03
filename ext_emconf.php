<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Temporal Cache Management',
    'description' => 'Automatic TYPO3 cache invalidation for time-based content (starttime/endtime), addressing Forge #14277. Three scoping strategies (global, per-page, per-content) and three timing strategies (dynamic, scheduler, hybrid). The default global scoping expires all page caches on every transition - read the Performance chapter before deployment.',
    'category' => 'fe',
    'author' => 'Netresearch',
    'author_email' => '',
    'author_company' => 'Netresearch DTT GmbH',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'php' => '8.1.0-8.5.99',
            'scheduler' => '12.4.0-14.99.99',
            'reports' => '12.4.0-14.99.99',
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
