<?php

declare(strict_types=1);

use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;

defined('TYPO3') || die();

// Icon registration moved to Configuration/Icons.php (TYPO3 13 LTS standard)

/*
 * Scheduler task registration.
 *
 * TYPO3 12.4, 13 and 14 all build the list of selectable task types in the
 * scheduler module from this array (TYPO3\CMS\Scheduler\Service\TaskService).
 * TYPO3 14 additionally offers native TCA task types, but the TCA table
 * tx_scheduler_task does not exist before v14, so this is the only form that
 * works on every supported version.
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TemporalCacheSchedulerTask::class] = [
    'extension' => 'nr_temporal_cache',
    'title' => 'LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:task.processTransitions.title',
    'description' => 'LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:task.processTransitions.description',
    'icon' => 'temporal-cache-module',
];
