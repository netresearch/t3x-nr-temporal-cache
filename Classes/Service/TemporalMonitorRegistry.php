<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

use InvalidArgumentException;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Registry for custom tables to monitor for temporal content.
 *
 * Allows extensions to register their own tables with starttime/endtime fields
 * so the temporal cache extension can monitor them for transitions.
 *
 * Register from the consuming extension's ext_localconf.php:
 *
 * ```php
 * \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
 *     \Netresearch\TemporalCache\Service\TemporalMonitorRegistry::class
 * )->registerTable('tx_news_domain_model_news', [
 *     'uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid',
 * ]);
 * ```
 *
 * Not through a Services.yaml definition: an unreferenced service is removed when the
 * container is compiled, so the registration never runs and the table is silently not
 * monitored. registerTable() returns void and cannot act as a service factory either.
 *
 * @api This class is part of the public API — see Documentation/Api/Index.rst.
 */
final class TemporalMonitorRegistry implements SingletonInterface
{
    /**
     * Default tables monitored by the extension.
     */
    private const DEFAULT_TABLES = [
        'pages' => ['uid', 'title', 'pid', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'],
        'tt_content' => ['uid', 'pid', 'header', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'],
    ];

    /**
     * Registered custom tables.
     *
     * @var array<string, array<string>>
     */
    private array $registeredTables = [];

    /**
     * Register a custom table for temporal monitoring.
     *
     * @param string $tableName Name of the table to monitor
     * @param array<string> $fields Fields to select from the table (must include starttime, endtime)
     * @throws InvalidArgumentException If table name is empty or reserved, or required fields are missing
     */
    public function registerTable(string $tableName, array $fields = []): void
    {
        if ($tableName === '') {
            throw new InvalidArgumentException('Table name cannot be empty', 1730289600);
        }

        if (isset(self::DEFAULT_TABLES[$tableName])) {
            throw new InvalidArgumentException(
                \sprintf('Table "%s" is already monitored by default and cannot be re-registered', $tableName),
                1730289601
            );
        }

        // Use default fields if none provided
        if ($fields === []) {
            $fields = ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'];
        }

        // Validate required fields
        $requiredFields = ['uid', 'starttime', 'endtime'];
        foreach ($requiredFields as $required) {
            if (!\in_array($required, $fields, true)) {
                throw new InvalidArgumentException(
                    \sprintf('Table "%s" must include required field: %s', $tableName, $required),
                    1730289602
                );
            }
        }

        $this->registeredTables[$tableName] = $fields;
    }

    /**
     * Unregister a custom table.
     *
     * @param string $tableName Name of the table to unregister
     */
    public function unregisterTable(string $tableName): void
    {
        unset($this->registeredTables[$tableName]);
    }

    /**
     * Check if a table is registered.
     *
     * @param string $tableName Name of the table to check
     * @return bool True if table is registered (either default or custom)
     */
    public function isRegistered(string $tableName): bool
    {
        return isset(self::DEFAULT_TABLES[$tableName]) || isset($this->registeredTables[$tableName]);
    }

    /**
     * Get all registered tables (default + custom).
     *
     * @return array<string, array<string>> Map of table names to their field lists
     */
    public function getAllTables(): array
    {
        return \array_merge(self::DEFAULT_TABLES, $this->registeredTables);
    }

    /**
     * Get only custom registered tables.
     *
     * @return array<string, array<string>> Map of custom table names to their field lists
     */
    public function getCustomTables(): array
    {
        return $this->registeredTables;
    }

    /**
     * Get fields for a specific table.
     *
     * @param string $tableName Name of the table
     * @return array<string>|null Fields for the table, or null if not registered
     */
    public function getTableFields(string $tableName): ?array
    {
        return self::DEFAULT_TABLES[$tableName] ?? $this->registeredTables[$tableName] ?? null;
    }

    /**
     * Clear all custom registered tables.
     *
     * Note: This does not affect default tables (pages, tt_content).
     */
    public function clearCustomTables(): void
    {
        $this->registeredTables = [];
    }

    /**
     * Get count of registered custom tables.
     *
     * @return int Number of custom tables registered
     */
    public function getCustomTableCount(): int
    {
        return \count($this->registeredTables);
    }

    /**
     * Get total count of all monitored tables (default + custom).
     *
     * @return int Total number of monitored tables
     */
    public function getTotalTableCount(): int
    {
        return \count(self::DEFAULT_TABLES) + \count($this->registeredTables);
    }
}
