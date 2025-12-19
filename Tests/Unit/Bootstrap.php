<?php

declare(strict_types=1);

/*
 * Custom bootstrap for unit tests.
 * Registers fixtures autoloader for TYPO3 stub classes.
 */

// Load Composer autoloader
require_once __DIR__ . '/../../.Build/vendor/autoload.php';

// Register fixtures autoloader for TYPO3 stubs
// IMPORTANT: Prepend to autoload stack so fixtures take precedence over vendor TYPO3 classes
\spl_autoload_register(function (string $class): void {
    // Only handle TYPO3\CMS namespace
    if (!\str_starts_with($class, 'TYPO3\\CMS\\')) {
        return;
    }

    // Convert namespace to file path
    $relativePath = \str_replace('\\', '/', $class);
    $filePath = __DIR__ . '/Fixtures/' . $relativePath . '.php';

    if (\file_exists($filePath)) {
        require_once $filePath;
    }
}, true, true); // throw=true, prepend=true to check fixtures BEFORE vendor
