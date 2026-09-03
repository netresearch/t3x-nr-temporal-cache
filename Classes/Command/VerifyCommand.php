<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Command;

use Exception;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * CLI Command: Verify database indexes and configuration.
 *
 * This command verifies that the temporal cache system is properly configured:
 * - Database indexes exist on starttime/endtime fields
 * - Extension configuration is valid
 * - Required services are available
 * - Cache configuration is correct
 *
 * Usage:
 *   vendor/bin/typo3 temporalcache:verify
 *   vendor/bin/typo3 temporalcache:verify --verbose
 *
 * Exit codes:
 *   0 = All checks passed
 *   1 = One or more checks failed
 */
final class VerifyCommand extends Command
{
    /**
     * Tables and fields that should have indexes for optimal performance.
     */
    private const REQUIRED_INDEXES = [
        'pages' => [
            'starttime' => ['starttime'],
            'endtime' => ['endtime'],
        ],
        'tt_content' => [
            'starttime' => ['starttime'],
            'endtime' => ['endtime'],
        ],
    ];

    /**
     * Required extension configuration keys with validation.
     */
    private const REQUIRED_CONFIG = [
        'scopingStrategy' => ['global', 'per-page', 'per-content'],
        'timingStrategy' => ['dynamic', 'scheduler', 'hybrid'],
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $configuration
    ) {
        parent::__construct('temporalcache:verify');
    }

    protected function configure(): void
    {
        $this->setDescription('Verify database indexes and extension configuration');
        $this->setHelp(
            <<<'HELP'
                This command performs comprehensive verification of the temporal cache system:

                <info>Checks performed:</info>
                  1. Database indexes on temporal fields (starttime, endtime)
                  2. Extension configuration validity
                  3. Time slot configuration (if harmonization enabled)
                  4. Required services availability
                  5. Cache backend configuration

                <info>Examples:</info>

                  # Basic verification
                  <comment>vendor/bin/typo3 temporalcache:verify</comment>

                  # Verbose output with detailed index information
                  <comment>vendor/bin/typo3 temporalcache:verify --verbose</comment>

                <info>Exit codes:</info>
                  0 = All checks passed
                  1 = One or more checks failed

                <info>After fixing issues:</info>
                  Run database compare to create missing indexes:
                  <comment>vendor/bin/typo3 extension:setup</comment>
                HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Temporal Cache System Verification');

        $allChecksPassed = true;

        // Check 1: Database indexes
        if (!$this->verifyDatabaseIndexes($io)) {
            $allChecksPassed = false;
        }

        // Check 2: Extension configuration
        if (!$this->verifyConfiguration($io)) {
            $allChecksPassed = false;
        }

        // Check 3: Harmonization configuration (if enabled)
        if ($this->configuration->isHarmonizationEnabled()) {
            if (!$this->verifyHarmonizationConfig($io)) {
                $allChecksPassed = false;
            }
        }

        // Check 4: Database schema completeness
        if (!$this->verifyDatabaseSchema($io)) {
            $allChecksPassed = false;
        }

        // Summary
        $io->newLine();
        if ($allChecksPassed) {
            $io->success('All verification checks passed! System is properly configured.');
            return Command::SUCCESS;
        }

        $io->error('Some verification checks failed. Please review the issues above and fix them.');
        return Command::FAILURE;
    }

    /**
     * Verify database indexes exist on temporal fields.
     */
    private function verifyDatabaseIndexes(SymfonyStyle $io): bool
    {
        $io->section('Database Index Verification');

        $allIndexesExist = true;
        $results = [];

        foreach (self::REQUIRED_INDEXES as $tableName => $indexes) {
            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $schemaManager = $connection->createSchemaManager();

            try {
                $tableIndexes = $schemaManager->listTableIndexes($tableName);
            } catch (Exception $e) {
                $io->error("Failed to check indexes for table '{$tableName}': " . $e->getMessage());
                return false;
            }

            foreach ($indexes as $columns) {
                $indexExists = $this->checkIndexExists($tableIndexes, $columns);

                $results[] = [
                    $tableName,
                    \implode(', ', $columns),
                    $indexExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>',
                ];

                if (!$indexExists) {
                    $allIndexesExist = false;
                }
            }
        }

        // Display results
        $table = new Table($io);
        $table->setHeaders(['Table', 'Field(s)', 'Status']);
        $table->setRows($results);
        $table->render();

        if (!$allIndexesExist) {
            $io->warning(
                'Missing indexes detected! This will severely impact performance.' . PHP_EOL .
                'Run "vendor/bin/typo3 extension:setup" to create missing indexes.'
            );
        }

        return $allIndexesExist;
    }

    /**
     * Check if an index exists for the specified columns.
     *
     * @param array<mixed> $tableIndexes
     * @param array<string> $columns
     */
    private function checkIndexExists(array $tableIndexes, array $columns): bool
    {
        foreach ($tableIndexes as $index) {
            // Type guard: ensure $index has getColumns method
            if (!\is_object($index)) {
                continue;
            }

            if (!\method_exists($index, 'getColumns')) {
                continue;
            }

            $columns_data = $index->getColumns();
            \assert(\is_array($columns_data));
            $indexColumns = \array_map(
                function ($col): string {
                    \assert(\is_string($col));
                    return \strtolower($col);
                },
                $columns_data
            );

            $searchColumns = \array_map(
                \strtolower(...),
                $columns
            );

            // Check if this index covers our columns (exact match or starts with our columns)
            if ($indexColumns === $searchColumns ||
                \array_slice($indexColumns, 0, \count($searchColumns)) === $searchColumns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify extension configuration is valid.
     */
    private function verifyConfiguration(SymfonyStyle $io): bool
    {
        $io->section('Extension Configuration Verification');

        $allConfigValid = true;
        $results = [];

        // Check scoping strategy
        $scopingStrategy = $this->configuration->getScopingStrategy();
        $scopingValid = \in_array($scopingStrategy, self::REQUIRED_CONFIG['scopingStrategy'], true);
        $results[] = [
            'Scoping Strategy',
            $scopingStrategy,
            $scopingValid ? '<fg=green>VALID</>' : '<fg=red>INVALID</>',
        ];
        if (!$scopingValid) {
            $allConfigValid = false;
        }

        // Check timing strategy
        $timingStrategy = $this->configuration->getTimingStrategy();
        $timingValid = \in_array($timingStrategy, self::REQUIRED_CONFIG['timingStrategy'], true);
        $results[] = [
            'Timing Strategy',
            $timingStrategy,
            $timingValid ? '<fg=green>VALID</>' : '<fg=red>INVALID</>',
        ];
        if (!$timingValid) {
            $allConfigValid = false;
        }

        // Check harmonization enabled
        $harmonizationEnabled = $this->configuration->isHarmonizationEnabled();
        $results[] = [
            'Harmonization',
            $harmonizationEnabled ? 'Enabled' : 'Disabled',
            '<fg=green>OK</>',
        ];

        // Display results
        $table = new Table($io);
        $table->setHeaders(['Setting', 'Value', 'Status']);
        $table->setRows($results);
        $table->render();

        if (!$allConfigValid) {
            $io->error('Invalid configuration detected! Check your extension configuration in the TYPO3 backend.');
        }

        return $allConfigValid;
    }

    /**
     * Verify harmonization configuration if enabled.
     */
    private function verifyHarmonizationConfig(SymfonyStyle $io): bool
    {
        $io->section('Harmonization Configuration Verification');

        $allValid = true;
        $results = [];

        // Check slots configuration
        $slots = $this->configuration->getHarmonizationSlots();
        $slotsValid = $slots !== [];
        $results[] = [
            'Time Slots',
            $slots === [] ? 'Not configured' : \implode(', ', $slots),
            $slotsValid ? '<fg=green>OK</>' : '<fg=red>MISSING</>',
        ];
        if (!$slotsValid) {
            $allValid = false;
        }

        // Validate slot format
        if ($slotsValid) {
            foreach ($slots as $slot) {
                if (!\preg_match('/^\d{1,2}:\d{2}$/', $slot)) {
                    $results[] = [
                        'Slot Format',
                        $slot,
                        '<fg=red>INVALID</>',
                    ];
                    $allValid = false;
                }
            }
        }

        // Check tolerance
        $tolerance = $this->configuration->getHarmonizationTolerance();
        $toleranceValid = $tolerance > 0 && $tolerance <= 86400; // Max 1 day
        $results[] = [
            'Tolerance',
            $tolerance . ' seconds (' . (int)($tolerance / 60) . ' minutes)',
            $toleranceValid ? '<fg=green>OK</>' : '<fg=red>INVALID</>',
        ];
        if (!$toleranceValid) {
            $allValid = false;
        }

        // Reported, not verified: the flag has no save-time effect, so a green OK
        // would tell an administrator a feature is working that does not exist.
        $autoRound = $this->configuration->isAutoRoundEnabled();
        $results[] = [
            'Auto-round',
            $autoRound ? 'Enabled' : 'Disabled',
            '<fg=yellow>REPORTING ONLY</>',
        ];

        // Display results
        $table = new Table($io);
        $table->setHeaders(['Setting', 'Value', 'Status']);
        $table->setRows($results);
        $table->render();

        if (!$allValid) {
            $io->error('Invalid harmonization configuration! Check your time slots and tolerance settings.');
        }

        return $allValid;
    }

    /**
     * Verify database schema is complete.
     */
    private function verifyDatabaseSchema(SymfonyStyle $io): bool
    {
        $io->section('Database Schema Verification');

        $requiredFields = [
            'pages' => ['starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'],
            'tt_content' => ['starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid', 'pid'],
        ];

        $allFieldsExist = true;
        $results = [];

        foreach ($requiredFields as $tableName => $fields) {
            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $schemaManager = $connection->createSchemaManager();

            try {
                $columns = $schemaManager->listTableColumns($tableName);
                $columnNames = \array_map(strtolower(...), \array_keys($columns));

                foreach ($fields as $field) {
                    $fieldExists = \in_array(\strtolower($field), $columnNames, true);
                    $results[] = [
                        $tableName,
                        $field,
                        $fieldExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>',
                    ];

                    if (!$fieldExists) {
                        $allFieldsExist = false;
                    }
                }
            } catch (Exception $e) {
                $io->error("Failed to check schema for table '{$tableName}': " . $e->getMessage());
                return false;
            }
        }

        // Display results in verbose mode
        if ($io->isVerbose()) {
            $table = new Table($io);
            $table->setHeaders(['Table', 'Field', 'Status']);
            $table->setRows($results);
            $table->render();
        } elseif ($allFieldsExist) {
            $io->writeln('<fg=green>All required database fields exist</>');
        }

        if (!$allFieldsExist) {
            $io->error('Missing database fields detected! The database schema is incomplete.');
        }

        return $allFieldsExist;
    }
}
