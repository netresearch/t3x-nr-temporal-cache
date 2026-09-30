#!/usr/bin/env php
<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

$settingsFile = '/var/www/html/v13/config/system/settings.php';

if (!file_exists($settingsFile)) {
    echo "Settings file not found!\n";
    exit(1);
}

$settings = include $settingsFile;

// Add/update backend security settings for TYPO3 13
$settings['BE']['cookieSameSite'] = 'lax';

// Ensure SYS configuration exists
if (!isset($settings['SYS'])) {
    $settings['SYS'] = [];
}

// Add referrer check configuration
$settings['SYS']['trustedHostsPattern'] = '.*\.ddev\.site';

// Disable strict referrer check for development (DDEV environment)
if (!isset($settings['BE'])) {
    $settings['BE'] = [];
}

// Add reverse proxy configuration for DDEV
$settings['SYS']['reverseProxyIP'] = '*';
$settings['SYS']['reverseProxyHeaderMultiValue'] = 'first';

file_put_contents($settingsFile, "<?php\nreturn " . var_export($settings, true) . ";\n");

echo "Updated TYPO3 configuration for referrer handling\n";
echo "Added:\n";
echo "  - BE.cookieSameSite = lax\n";
echo "  - SYS.trustedHostsPattern = .*\.ddev\.site\n";
echo "  - SYS.reverseProxyIP = *\n";
echo "  - SYS.reverseProxyHeaderMultiValue = first\n";
