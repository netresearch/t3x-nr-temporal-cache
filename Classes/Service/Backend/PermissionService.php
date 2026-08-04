<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Backend;

use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Service for checking backend user permissions for temporal cache operations.
 *
 * Provides centralized permission checking for harmonization and other
 * write operations on temporal content tables.
 *
 * Permission hierarchy:
 * 1. Admin users bypass all checks
 * 2. Table-level write permissions (standard TYPO3)
 * 3. Module access permissions (via TSconfig)
 */
final class PermissionService implements SingletonInterface
{
    public function __construct(
        private readonly TemporalMonitorRegistry $monitorRegistry
    ) {
    }

    /**
     * Check if current backend user can modify temporal content.
     *
     * Verifies that the user has write permissions for all registered tables
     * that temporal cache monitors (default: pages and tt_content).
     *
     * @param string|null $tableName Specific table to check, or null to check all registered tables
     * @return bool True if user has write access
     */
    public function canModifyTemporalContent(?string $tableName = null): bool
    {
        $user = $this->getBackendUser();

        // Admin users can do everything
        if ($user->isAdmin()) {
            return true;
        }

        // Check specific table if provided
        if ($tableName !== null) {
            return $this->canModifyTable($tableName, $user);
        }

        // Check all registered tables (default + custom)
        foreach ($this->monitorRegistry->getAllTables() as $table => $fields) {
            if (!$this->canModifyTable($table, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user can modify a specific table.
     *
     * Checks TYPO3 table permissions for 'edit' and 'editcontent' operations.
     *
     * @param string $tableName Table name to check
     * @param BackendUserAuthentication $user Backend user
     * @return bool True if user can modify table
     */
    private function canModifyTable(string $tableName, BackendUserAuthentication $user): bool
    {
        // Table-specific permission check
        // For pages: check 'web' permission
        // For content tables: check 'tables_modify' permission
        if ($tableName === 'pages') {
            return $user->check('tables_modify', 'pages');
        }

        return $user->check('tables_modify', $tableName);
    }

    /**
     * Check if current backend user can access the temporal cache module.
     *
     * Verifies module access via TSconfig (options.hideModules).
     * This is checked automatically by TYPO3 module access control,
     * but provided for explicit permission verification.
     *
     * @return bool True if user has module access
     */
    public function canAccessModule(): bool
    {
        $user = $this->getBackendUser();

        // Admin users always have access
        if ($user->isAdmin()) {
            return true;
        }

        // Check if module is hidden via TSconfig
        $tsConfig = $user->getTSConfig();
        $options = $tsConfig['options.'] ?? [];
        \assert(\is_array($options));
        $hiddenModules = $options['hideModules'] ?? '';
        \assert(\is_string($hiddenModules));
        $hiddenModulesList = \array_filter(\array_map(trim(...), \explode(',', $hiddenModules)));

        return !\in_array('tools_TemporalCache', $hiddenModulesList, true);
    }

    /**
     * Get list of tables the current user cannot modify.
     *
     * Useful for generating specific error messages showing which
     * tables the user lacks permission for.
     *
     * @return array<string> List of table names user cannot modify
     */
    public function getUnmodifiableTables(): array
    {
        $user = $this->getBackendUser();

        // Admin users can modify all tables
        if ($user->isAdmin()) {
            return [];
        }

        $unmodifiableTables = [];
        foreach ($this->monitorRegistry->getAllTables() as $tableName => $fields) {
            if (!$this->canModifyTable($tableName, $user)) {
                $unmodifiableTables[] = $tableName;
            }
        }

        return $unmodifiableTables;
    }

    /**
     * Check if user has read-only access.
     *
     * Read-only users can view temporal content but cannot harmonize
     * or modify temporal fields.
     *
     * @return bool True if user has read-only access
     */
    public function isReadOnly(): bool
    {
        return !$this->canModifyTemporalContent();
    }

    /**
     * Get permission status summary for current user.
     *
     * Returns comprehensive permission information useful for
     * debugging and displaying user capabilities in the UI.
     *
     * @return array{isAdmin: bool, canModify: bool, canAccessModule: bool, unmodifiableTables: array<string>}
     */
    public function getPermissionStatus(): array
    {
        $user = $this->getBackendUser();

        return [
            'isAdmin' => $user->isAdmin(),
            'canModify' => $this->canModifyTemporalContent(),
            'canAccessModule' => $this->canAccessModule(),
            'unmodifiableTables' => $this->getUnmodifiableTables(),
        ];
    }

    /**
     * Get current backend user.
     *
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        $user = $GLOBALS['BE_USER'];
        \assert($user instanceof BackendUserAuthentication);
        return $user;
    }
}
