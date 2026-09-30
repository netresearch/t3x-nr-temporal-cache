<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/../Classes')
            ->in(__DIR__ . '/../Tests')
    );
