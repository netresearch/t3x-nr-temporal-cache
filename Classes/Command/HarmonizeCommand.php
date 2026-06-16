<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Command;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CLI Command: Perform harmonization of temporal fields.
 *
 * This command harmonizes (rounds) starttime and endtime fields in pages and
 * content elements to configured time slots, reducing cache churn and improving
 * cache hit rates.
 *
 * Usage:
 *   vendor/bin/typo3 temporalcache:harmonize --dry-run
 *   vendor/bin/typo3 temporalcache:harmonize
 *   vendor/bin/typo3 temporalcache:harmonize --workspace=1
 *   vendor/bin/typo3 temporalcache:harmonize --table=pages --verbose
 *
 * Safety features:
 * - Dry-run mode to preview changes before applying
 * - Only processes records where harmonization makes a difference
 * - Writes directly via the database connection and flushes the pages cache afterwards
 * - Respects workspace and language context
 *
 * Example output (dry-run):
 *   Pages: 45 records would be updated
 *   Content: 123 records would be updated
 *   Estimated reduction: 35% fewer cache invalidations
 *
 * Warning: This command modifies database records. Always run with --dry-run first!
 */
final class HarmonizeCommand extends Command
{
    public function __construct(
        private readonly TemporalContentRepositoryInterface $repository,
        private readonly HarmonizationService $harmonizationService,
        private readonly ExtensionConfiguration $configuration,
        private readonly ConnectionPool $connectionPool
    ) {
        parent::__construct('temporalcache:harmonize');
    }

    protected function configure(): void
    {
        $this->setDescription('Harmonize temporal fields to configured time slots');
        $this->setHelp(
            <<<'HELP'
                This command harmonizes (rounds) starttime and endtime fields to configured
                time slots, reducing the number of unique transition timestamps and improving
                cache efficiency.

                <info>How it works:</info>
                  1. Scans all temporal content (pages and content elements)
                  2. Calculates harmonized timestamps for each starttime/endtime
                  3. Updates records where harmonization makes a difference
                  4. Reports statistics about changes made

                <info>Examples:</info>

                  # Preview changes without making modifications
                  <comment>vendor/bin/typo3 temporalcache:harmonize --dry-run</comment>

                  # Apply harmonization to all temporal content
                  <comment>vendor/bin/typo3 temporalcache:harmonize</comment>

                  # Harmonize only pages
                  <comment>vendor/bin/typo3 temporalcache:harmonize --table=pages</comment>

                  # Harmonize with verbose output
                  <comment>vendor/bin/typo3 temporalcache:harmonize --verbose</comment>

                  # Harmonize specific workspace
                  <comment>vendor/bin/typo3 temporalcache:harmonize --workspace=1</comment>

                <info>Safety recommendations:</info>
                  1. ALWAYS run with --dry-run first
                  2. Backup your database before running
                  3. Test on staging environment first
                  4. Review harmonization configuration before running

                <info>Requirements:</info>
                  - Harmonization must be enabled in extension configuration
                  - Time slots must be configured
                  - Tolerance must be set appropriately
                HELP
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview changes without modifying database'
        );

        $this->addOption(
            'workspace',
            'w',
            InputOption::VALUE_REQUIRED,
            'Workspace UID to harmonize (0 = live workspace)',
            '0'
        );

        $this->addOption(
            'language',
            'l',
            InputOption::VALUE_REQUIRED,
            'Language UID to harmonize (0 = default)',
            '0'
        );

        $this->addOption(
            'table',
            't',
            InputOption::VALUE_REQUIRED,
            'Limit to specific table (pages or tt_content)',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRunOption = $input->getOption('dry-run');
        $workspaceOption = $input->getOption('workspace');
        $languageOption = $input->getOption('language');
        $tableFilter = $input->getOption('table');

        \assert(\is_bool($dryRunOption) || \is_string($dryRunOption) || \is_int($dryRunOption) || $dryRunOption === null);
        \assert(\is_string($workspaceOption) || \is_int($workspaceOption));
        \assert(\is_string($languageOption) || \is_int($languageOption));

        $dryRun = (bool)$dryRunOption;
        $workspaceUid = (int)$workspaceOption;
        $languageUid = (int)$languageOption;

        $io->title('Temporal Field Harmonization');

        // Verify harmonization is enabled
        if (!$this->configuration->isHarmonizationEnabled()) {
            $io->error('Harmonization is not enabled in extension configuration!');
            $io->note('Enable harmonization in the TYPO3 backend: Settings > Extension Configuration > temporal_cache');
            return Command::FAILURE;
        }

        // Validate table filter
        if ($tableFilter !== null) {
            \assert(\is_string($tableFilter));
            if (!\in_array($tableFilter, ['pages', 'tt_content'], true)) {
                $io->error("Invalid table name: {$tableFilter}. Must be 'pages' or 'tt_content'.");
                return Command::FAILURE;
            }
        }

        // Display mode
        if ($dryRun) {
            $io->warning('DRY-RUN MODE: No changes will be made to the database');
        } else {
            $io->caution('LIVE MODE: Database will be modified');
        }

        // Display context
        $io->section('Harmonization Context');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Mode', $dryRun ? 'Dry-run' : 'Live'],
                ['Workspace', $workspaceUid === 0 ? 'Live (0)' : "Workspace {$workspaceUid}"],
                ['Language', "Language {$languageUid}"],
                ['Table Filter', $tableFilter ?? 'All tables'],
                ['Time Slots', \implode(', ', $this->configuration->getHarmonizationSlots())],
                ['Tolerance', $this->configuration->getHarmonizationTolerance() . ' seconds'],
            ]
        );

        // Load temporal content
        $io->section('Loading Temporal Content');
        $allContent = $this->repository->findAllWithTemporalFields($workspaceUid, $languageUid);

        if (empty($allContent)) {
            $io->warning('No temporal content found to harmonize.');
            return Command::SUCCESS;
        }

        $io->writeln(\sprintf('Found <info>%d</info> temporal records', \count($allContent)));

        // Filter by table if specified
        if ($tableFilter !== null) {
            $allContent = \array_filter(
                $allContent,
                fn ($content) => $content->tableName === $tableFilter
            );
            $io->writeln(\sprintf('Filtered to <info>%d</info> records from table: %s', \count($allContent), $tableFilter));
        }

        // Analyze and harmonize
        $io->section($dryRun ? 'Analyzing Changes' : 'Applying Harmonization');

        $changes = $this->analyzeHarmonization($allContent);

        if (empty($changes)) {
            $io->success('No records need harmonization. All timestamps already aligned with configured slots.');
            return Command::SUCCESS;
        }

        // Display summary
        $this->displayChangeSummary($io, $changes);

        // Apply changes if not dry-run
        if (!$dryRun) {
            if (!$io->confirm('Proceed with harmonization?', false)) {
                $io->note('Harmonization cancelled by user.');
                return Command::SUCCESS;
            }

            $io->section('Applying Changes');
            $this->applyHarmonization($io, $changes, $workspaceUid);

            // Flush the pages cache so the harmonized transition times take effect immediately.
            GeneralUtility::makeInstance(CacheManager::class)->flushCachesInGroup('pages');
        }

        // Calculate impact
        $this->displayImpactAnalysis($io, $changes, $dryRun);

        if ($dryRun) {
            $io->note('Dry-run complete. Run without --dry-run to apply changes.');
        } else {
            $io->success('Harmonization complete!');
        }

        return Command::SUCCESS;
    }

    /**
     * Analyze which records need harmonization.
     *
     * @param array<\Netresearch\TemporalCache\Domain\Model\TemporalContent> $content
     * @return array<array{table: string, uid: int, field: string, old: int, new: int}>
     */
    private function analyzeHarmonization(array $content): array
    {
        $changes = [];

        foreach ($content as $item) {
            // Check starttime
            if ($item->starttime !== null) {
                $harmonized = $this->harmonizationService->harmonizeTimestamp($item->starttime);
                if ($harmonized !== $item->starttime) {
                    $changes[] = [
                        'table' => $item->tableName,
                        'uid' => $item->uid,
                        'field' => 'starttime',
                        'old' => $item->starttime,
                        'new' => $harmonized,
                        'title' => $item->title,
                    ];
                }
            }

            // Check endtime
            if ($item->endtime !== null) {
                $harmonized = $this->harmonizationService->harmonizeTimestamp($item->endtime);
                if ($harmonized !== $item->endtime) {
                    $changes[] = [
                        'table' => $item->tableName,
                        'uid' => $item->uid,
                        'field' => 'endtime',
                        'old' => $item->endtime,
                        'new' => $harmonized,
                        'title' => $item->title,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Display summary of changes to be made.
     *
     * @param array<array{table: string, uid: int, field: string, old: int, new: int}> $changes
     */
    private function displayChangeSummary(SymfonyStyle $io, array $changes): void
    {
        $pageChanges = \array_filter($changes, fn ($c) => $c['table'] === 'pages');
        $contentChanges = \array_filter($changes, fn ($c) => $c['table'] === 'tt_content');

        $io->table(
            ['Table', 'Changes'],
            [
                ['pages', \count($pageChanges)],
                ['tt_content', \count($contentChanges)],
                ['<info>Total</info>', '<info>' . \count($changes) . '</info>'],
            ]
        );

        // Show sample changes in verbose mode
        if ($io->isVerbose() && !empty($changes)) {
            $io->writeln("\n<comment>Sample Changes (first 10):</comment>");
            $table = new Table($io);
            $table->setHeaders(['Table', 'UID', 'Field', 'Old Time', 'New Time', 'Shift']);

            $displayed = 0;
            foreach ($changes as $change) {
                if ($displayed >= 10) {
                    break;
                }

                $shift = $change['new'] - $change['old'];
                $shiftMin = (int)($shift / 60);

                $table->addRow([
                    $change['table'],
                    $change['uid'],
                    $change['field'],
                    \date('Y-m-d H:i', $change['old']),
                    \date('Y-m-d H:i', $change['new']),
                    \sprintf('%+d min', $shiftMin),
                ]);

                $displayed++;
            }

            $table->render();

            if (\count($changes) > 10) {
                $io->writeln(\sprintf("\n<info>... and %d more changes</info>", \count($changes) - 10));
            }
        }
    }

    /**
     * Apply harmonization changes to database.
     *
     * @param array<array{table: string, uid: int, field: string, old: int, new: int}> $changes
     */
    private function applyHarmonization(SymfonyStyle $io, array $changes, int $workspaceUid): void
    {
        $progressBar = new ProgressBar($io, \count($changes));
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $updated = 0;
        $failed = 0;

        foreach ($changes as $change) {
            try {
                $connection = $this->connectionPool->getConnectionForTable($change['table']);
                $connection->update(
                    $change['table'],
                    [$change['field'] => $change['new']],
                    ['uid' => $change['uid']]
                );
                $updated++;
            } catch (\Exception $e) {
                $failed++;
                if ($io->isVerbose()) {
                    $io->writeln('');
                    $io->error(\sprintf(
                        'Failed to update %s:%d - %s',
                        $change['table'],
                        $change['uid'],
                        $e->getMessage()
                    ));
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Display results
        $io->table(
            ['Result', 'Count'],
            [
                ['Updated', $updated],
                ['Failed', $failed],
            ]
        );

        if ($failed > 0) {
            $io->warning(\sprintf('%d records failed to update. Check error messages above.', $failed));
        }
    }

    /**
     * Display impact analysis of harmonization.
     *
     * @param array<array{table: string, uid: int, field: string, old: int, new: int}> $changes
     */
    private function displayImpactAnalysis(SymfonyStyle $io, array $changes, bool $dryRun): void
    {
        $io->section('Impact Analysis');

        // Calculate unique timestamps before and after
        $oldTimestamps = \array_unique(\array_column($changes, 'old'));
        $newTimestamps = \array_unique(\array_column($changes, 'new'));

        $reduction = \count($oldTimestamps) > 0
            ? (\count($oldTimestamps) - \count($newTimestamps)) / \count($oldTimestamps) * 100
            : 0;

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Changes', \count($changes)],
                ['Unique Timestamps (Before)', \count($oldTimestamps)],
                ['Unique Timestamps (After)', \count($newTimestamps)],
                ['Timestamp Reduction', \sprintf('%.1f%%', $reduction)],
                ['Cache Invalidations Saved', \count($oldTimestamps) - \count($newTimestamps)],
            ]
        );

        if ($reduction > 0) {
            $message = \sprintf(
                'Harmonization %s reduce cache invalidations by %.1f%%!',
                $dryRun ? 'would' : 'will',
                $reduction
            );
            $io->success($message);
        }
    }
}
