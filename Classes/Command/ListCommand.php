<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Command;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI Command: List all temporal content with transitions.
 *
 * This command displays a comprehensive list of all temporal content
 * (pages and content elements) with their start/end times and upcoming
 * transition information.
 *
 * Usage:
 *   vendor/bin/typo3 temporalcache:list
 *   vendor/bin/typo3 temporalcache:list --table=pages
 *   vendor/bin/typo3 temporalcache:list --sort=starttime
 *   vendor/bin/typo3 temporalcache:list --upcoming
 *   vendor/bin/typo3 temporalcache:list --format=json
 *
 * Output formats:
 * - table (default): Human-readable table format
 * - json: Machine-readable JSON format
 * - csv: CSV format for import into spreadsheets
 *
 * Filtering options:
 * - By table (pages or tt_content)
 * - By upcoming transitions only
 * - By workspace and language
 *
 * Sorting options:
 * - By starttime, endtime, title, uid
 */
final class ListCommand extends Command
{
    /**
     * Valid output formats.
     */
    private const VALID_FORMATS = ['table', 'json', 'csv'];

    /**
     * Valid sort fields.
     */
    private const VALID_SORT_FIELDS = ['uid', 'title', 'starttime', 'endtime', 'table'];

    public function __construct(
        private readonly TemporalContentRepositoryInterface $repository
    ) {
        parent::__construct('temporalcache:list');
    }

    protected function configure(): void
    {
        $this->setDescription('List all temporal content with transition information');
        $this->setHelp(
            <<<'HELP'
                This command displays a comprehensive list of all temporal content in the
                TYPO3 system, including pages and content elements with starttime or endtime.

                <info>Examples:</info>

                  # List all temporal content
                  <comment>vendor/bin/typo3 temporalcache:list</comment>

                  # List only pages
                  <comment>vendor/bin/typo3 temporalcache:list --table=pages</comment>

                  # List only content with upcoming transitions
                  <comment>vendor/bin/typo3 temporalcache:list --upcoming</comment>

                  # Sort by start time
                  <comment>vendor/bin/typo3 temporalcache:list --sort=starttime</comment>

                  # Export to JSON
                  <comment>vendor/bin/typo3 temporalcache:list --format=json > temporal-content.json</comment>

                  # Export to CSV
                  <comment>vendor/bin/typo3 temporalcache:list --format=csv > temporal-content.csv</comment>

                  # Filter by workspace
                  <comment>vendor/bin/typo3 temporalcache:list --workspace=1</comment>

                <info>Output formats:</info>
                  table = Human-readable table (default)
                  json  = Machine-readable JSON
                  csv   = CSV for spreadsheet import

                <info>Sort options:</info>
                  uid, title, starttime, endtime, table
                HELP
        );

        $this->addOption(
            'table',
            't',
            InputOption::VALUE_REQUIRED,
            'Filter by table (pages or tt_content)'
        );

        $this->addOption(
            'workspace',
            'w',
            InputOption::VALUE_REQUIRED,
            'Workspace UID to list (0 = live workspace)',
            '0'
        );

        $this->addOption(
            'language',
            'l',
            InputOption::VALUE_REQUIRED,
            'Language UID to list (-1 = all, 0 = default)',
            '0'
        );

        $this->addOption(
            'upcoming',
            'u',
            InputOption::VALUE_NONE,
            'Show only content with upcoming transitions'
        );

        $this->addOption(
            'sort',
            's',
            InputOption::VALUE_REQUIRED,
            'Sort by field (uid, title, starttime, endtime, table)',
            'uid'
        );

        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_REQUIRED,
            'Output format (table, json, csv)',
            'table'
        );

        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Limit number of results'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workspaceOption = $input->getOption('workspace');
        $languageOption = $input->getOption('language');
        $tableFilter = $input->getOption('table');
        $upcomingOption = $input->getOption('upcoming');
        $sortField = $input->getOption('sort');
        $format = $input->getOption('format');
        $limitOption = $input->getOption('limit');

        \assert(\is_string($workspaceOption) || \is_int($workspaceOption));
        \assert(\is_string($languageOption) || \is_int($languageOption));
        \assert(\is_bool($upcomingOption) || \is_string($upcomingOption) || \is_int($upcomingOption) || $upcomingOption === null);

        $workspaceUid = (int)$workspaceOption;
        $languageUid = (int)$languageOption;
        $upcomingOnly = (bool)$upcomingOption;
        \assert(\is_string($limitOption) || \is_int($limitOption) || $limitOption === null);
        $limit = $limitOption !== null ? (int)$limitOption : null;

        // Validate table filter
        if ($tableFilter !== null) {
            \assert(\is_string($tableFilter));
            if (!\in_array($tableFilter, ['pages', 'tt_content'], true)) {
                $io->error("Invalid table name: {$tableFilter}. Must be 'pages' or 'tt_content'.");
                return Command::FAILURE;
            }
        }

        // Validate sort field
        \assert(\is_string($sortField));
        if (!\in_array($sortField, self::VALID_SORT_FIELDS, true)) {
            $io->error("Invalid sort field: {$sortField}. Must be one of: " . \implode(', ', self::VALID_SORT_FIELDS));
            return Command::FAILURE;
        }

        // Validate format
        \assert(\is_string($format));
        if (!\in_array($format, self::VALID_FORMATS, true)) {
            $io->error("Invalid format: {$format}. Must be one of: " . \implode(', ', self::VALID_FORMATS));
            return Command::FAILURE;
        }

        // Only show title in table format
        if ($format === 'table') {
            $io->title('Temporal Content List');
        }

        // Load temporal content
        $allContent = $this->repository->findAllWithTemporalFields($workspaceUid, $languageUid);

        if ($allContent === []) {
            if ($format === 'table') {
                $io->warning('No temporal content found.');
            }

            return Command::SUCCESS;
        }

        // Apply filters
        $content = $this->applyFilters($allContent, $tableFilter, $upcomingOnly);

        if ($content === []) {
            if ($format === 'table') {
                $io->warning('No content matches the specified filters.');
            }

            return Command::SUCCESS;
        }

        // Sort content
        $content = $this->sortContent($content, $sortField);

        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $content = \array_slice($content, 0, $limit);
        }

        // Output in requested format
        match ($format) {
            'json' => $this->outputJson($output, $content),
            'csv' => $this->outputCsv($output, $content),
            default => $this->outputTable($io, $content, $workspaceUid, $languageUid),
        };

        return Command::SUCCESS;
    }

    /**
     * Apply filters to content list.
     *
     * @param array<TemporalContent> $content
     * @return array<TemporalContent>
     */
    private function applyFilters(array $content, ?string $tableFilter, bool $upcomingOnly): array
    {
        // Filter by table
        if ($tableFilter !== null) {
            $content = \array_filter(
                $content,
                fn (TemporalContent $item): bool => $item->tableName === $tableFilter
            );
        }

        // Filter by upcoming transitions
        if ($upcomingOnly) {
            $now = \time();
            $content = \array_filter(
                $content,
                fn (TemporalContent $item): bool => ($item->starttime !== null && $item->starttime > $now) ||
                             ($item->endtime !== null && $item->endtime > $now)
            );
        }

        return \array_values($content);
    }

    /**
     * Sort content by specified field.
     *
     * @param array<TemporalContent> $content
     * @return array<TemporalContent>
     */
    private function sortContent(array $content, string $sortField): array
    {
        \usort($content, fn ($a, $b): int => match ($sortField) {
            'uid' => $a->uid <=> $b->uid,
            'title' => \strcasecmp($a->title, $b->title),
            'table' => \strcasecmp($a->tableName, $b->tableName),
            'starttime' => ($a->starttime ?? PHP_INT_MAX) <=> ($b->starttime ?? PHP_INT_MAX),
            'endtime' => ($a->endtime ?? PHP_INT_MAX) <=> ($b->endtime ?? PHP_INT_MAX),
            default => 0,
        });

        return $content;
    }

    /**
     * Output content as table.
     *
     * @param array<TemporalContent> $content
     */
    private function outputTable(SymfonyStyle $io, array $content, int $workspaceUid, int $languageUid): void
    {
        // Display context
        $io->section('Filters');
        $io->writeln(\sprintf('Workspace: %d | Language: %d | Total: %d records', $workspaceUid, $languageUid, \count($content)));

        // Create table
        $table = new Table($io);
        $table->setHeaders(['Table', 'UID', 'Title', 'Start Time', 'End Time', 'Next Transition']);

        $now = \time();

        foreach ($content as $item) {
            $startTime = $item->starttime !== null ? \date('Y-m-d H:i', $item->starttime) : '-';
            $endTime = $item->endtime !== null ? \date('Y-m-d H:i', $item->endtime) : '-';

            // Calculate next transition
            $nextTransition = $this->calculateNextTransition($item, $now);

            $table->addRow([
                $item->tableName,
                $item->uid,
                \mb_substr($item->title, 0, 30),
                $startTime,
                $endTime,
                $nextTransition,
            ]);
        }

        $table->render();

        // Summary
        $io->newLine();
        $io->writeln(\sprintf('<info>Total: %d records</info>', \count($content)));
    }

    /**
     * Output content as JSON.
     *
     * @param array<TemporalContent> $content
     */
    private function outputJson(OutputInterface $output, array $content): void
    {
        $data = \array_map(fn (TemporalContent $item): array => [
            'table' => $item->tableName,
            'uid' => $item->uid,
            'pid' => $item->pid,
            'title' => $item->title,
            'starttime' => $item->starttime,
            'starttime_formatted' => $item->starttime !== null ? \date('Y-m-d H:i:s', $item->starttime) : null,
            'endtime' => $item->endtime,
            'endtime_formatted' => $item->endtime !== null ? \date('Y-m-d H:i:s', $item->endtime) : null,
            'language_uid' => $item->languageUid,
            'workspace_uid' => $item->workspaceUid,
            'hidden' => $item->hidden,
            'deleted' => $item->deleted,
        ], $content);

        $jsonOutput = \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        \assert(\is_string($jsonOutput), 'JSON encoding failed');
        $output->writeln($jsonOutput);
    }

    /**
     * Output content as CSV.
     *
     * @param array<TemporalContent> $content
     */
    private function outputCsv(OutputInterface $output, array $content): void
    {
        // Write header
        $output->writeln('Table,UID,PID,Title,StartTime,EndTime,Language,Workspace,Hidden,Deleted');

        // Write rows
        foreach ($content as $item) {
            $row = [
                $item->tableName,
                $item->uid,
                $item->pid,
                '"' . \str_replace('"', '""', $item->title) . '"',
                $item->starttime !== null ? \date('Y-m-d H:i:s', $item->starttime) : '',
                $item->endtime !== null ? \date('Y-m-d H:i:s', $item->endtime) : '',
                $item->languageUid,
                $item->workspaceUid,
                $item->hidden ? '1' : '0',
                $item->deleted ? '1' : '0',
            ];

            $output->writeln(\implode(',', $row));
        }
    }

    /**
     * Calculate next transition for an item.
     */
    private function calculateNextTransition(
        TemporalContent $item,
        int $now
    ): string {
        $nextStart = $item->starttime !== null && $item->starttime > $now ? $item->starttime : null;
        $nextEnd = $item->endtime !== null && $item->endtime > $now ? $item->endtime : null;

        if ($nextStart === null && $nextEnd === null) {
            return '<fg=gray>-</>';
        }

        $nextTransition = null;
        $type = '';

        if ($nextStart !== null && $nextEnd !== null) {
            if ($nextStart < $nextEnd) {
                $nextTransition = $nextStart;
                $type = 'Start';
            } else {
                $nextTransition = $nextEnd;
                $type = 'End';
            }
        } elseif ($nextStart !== null) {
            $nextTransition = $nextStart;
            $type = 'Start';
        } else {
            $nextTransition = $nextEnd;
            $type = 'End';
        }

        $timeUntil = $this->formatTimeUntil($nextTransition - $now);

        return \sprintf(
            '<fg=cyan>%s</> in %s',
            $type,
            $timeUntil
        );
    }

    /**
     * Format time until in human-readable format.
     */
    private function formatTimeUntil(int $seconds): string
    {
        if ($seconds < 60) {
            return \sprintf('%d sec', $seconds);
        }

        if ($seconds < 3600) {
            return \sprintf('%d min', (int)($seconds / 60));
        }

        if ($seconds < 86400) {
            return \sprintf('%d hours', (int)($seconds / 3600));
        }

        return \sprintf('%d days', (int)($seconds / 86400));
    }
}
