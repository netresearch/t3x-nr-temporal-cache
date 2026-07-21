<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Icon configuration for nr_temporal_cache extension
 *
 * TYPO3 13 LTS standard: Icons are registered via Configuration/Icons.php
 * instead of ext_localconf.php to avoid deprecation warnings.
 *
 * TYPO3 v14 ships a redesigned backend with light/dark mode: use the flat,
 * three-color module icon that adapts via currentColor. v12/v13 use the
 * colored (teal tile) variant that matches the classic module menu.
 */
$moduleIcon = (new Typo3Version())->getMajorVersion() >= 14
    ? 'EXT:nr_temporal_cache/Resources/Public/Icons/ModuleIcon.svg'
    : 'EXT:nr_temporal_cache/Resources/Public/Icons/ModuleIcon.legacy.svg';

return [
    'temporal-cache-module' => [
        'provider' => SvgIconProvider::class,
        'source' => $moduleIcon,
    ],
];
