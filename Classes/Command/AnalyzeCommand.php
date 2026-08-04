<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Command;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI Command: Analyze temporal content and provide statistics.
 *
 * This command analyzes all temporal content (pages and content elements with
 * starttime/endtime) in the TYPO3 system and provides comprehensive statistics
 * about cache behavior, harmonization impact, and upcoming transitions.
 *
 * Usage:
 *   vendor/bin/typo3 temporalcache:analyze
 *   vendor/bin/typo3 temporalcache:analyze --workspace=1
 *   vendor/bin/typo3 temporalcache:analyze --language=1
 *   vendor/bin/typo3 temporalcache:analyze --days=30 --verbose
 *
 * Output includes:
 * - Total count of temporal content (pages and content elements)
 * - Distribution by type (pages vs content elements)
 * - Distribution by temporal field (starttime only, endtime only, both)
 * - Upcoming transitions in next N days
 * - Harmonization impact analysis (if enabled)
 * - Peak transition days
 */
final class AnalyzeCommand extends Command
{
    public function __construct(
        private readonly TemporalContentRepositoryInterface $repository,
        private readonly ExtensionConfiguration $configuration,
        private readonly HarmonizationService $harmonizationService
    ) {
        parent::__construct('temporalcache:analyze');
    }

    protected function configure(): void
    {
        $this->setDescription('Analyze temporal content and provide cache statistics');
        $this->setHelp(
            <<<'HELP'
                This command analyzes all temporal content in the TYPO3 system and provides
                comprehensive statistics about cache behavior and upcoming transitions.

                <info>Examples:</info>

                  # Basic analysis (default workspace, all languages)
                  <comment>vendor/bin/typo3 temporalcache:analyze</comment>

                  # Analyze specific workspace
                  <comment>vendor/bin/typo3 temporalcache:analyze --workspace=1</comment>

                  # Analyze next 60 days with verbose output
                  <comment>vendor/bin/typo3 temporalcache:analyze --days=60 --verbose</comment>

                  # Analyze specific language
                  <comment>vendor/bin/typo3 temporalcache:analyze --language=1</comment>

                <info>Output includes:</info>
                  - Temporal content distribution (pages, content elements)
                  - Temporal field usage (starttime, endtime, both)
                  - Upcoming transitions timeline
                  - Harmonization impact analysis
                  - Peak transition days
                HELP
        );

        $this->addOption(
            'workspace',
            'w',
            InputOption::VALUE_REQUIRED,
            'Workspace UID to analyze (0 = live workspace)',
            '0'
        );

        $this->addOption(
            'language',
            'l',
            InputOption::VALUE_REQUIRED,
            'Language UID to analyze (-1 = all languages, 0 = default)',
            '0'
        );

        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_REQUIRED,
            'Number of days to analyze for upcoming transitions',
            '30'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workspaceOption = $input->getOption('workspace');
        $languageOption = $input->getOption('language');
        $daysOption = $input->getOption('days');

        \assert(\is_string($workspaceOption) || \is_int($workspaceOption));
        \assert(\is_string($languageOption) || \is_int($languageOption));
        \assert(\is_string($daysOption) || \is_int($daysOption));

        $workspaceUid = (int)$workspaceOption;
        $languageUid = (int)$languageOption;
        $days = (int)$daysOption;

        $io->title('Temporal Cache Analysis');

        // Display context
        $io->section('Analysis Context');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Workspace', $workspaceUid === 0 ? 'Live (0)' : "Workspace {$workspaceUid}"],
                ['Language', $languageUid === -1 ? 'All languages' : "Language {$languageUid}"],
                ['Analysis Period', "{$days} days from now"],
                ['Current Time', \date('Y-m-d H:i:s')],
            ]
        );

        // Get basic statistics
        $io->section('Temporal Content Statistics');

        $stats = $this->repository->getStatistics($workspaceUid);

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Temporal Items', $stats['total']],
                ['  Pages', $stats['pages']],
                ['  Content Elements', $stats['content']],
                ['With Start Time Only', $stats['withStart']],
                ['With End Time Only', $stats['withEnd']],
                ['With Both Start & End', $stats['withBoth']],
            ]
        );

        if ($stats['total'] === 0) {
            $io->warning('No temporal content found in the system.');
            return Command::SUCCESS;
        }

        // Analyze upcoming transitions
        $this->analyzeUpcomingTransitions($io, $workspaceUid, $languageUid, $days);

        // Analyze harmonization impact (if enabled)
        if ($this->configuration->isHarmonizationEnabled()) {
            $this->analyzeHarmonizationImpact($io, $workspaceUid, $languageUid, $days);
        } else {
            $io->note('Harmonization is disabled. Enable in extension configuration to see impact analysis.');
        }

        // Show configuration summary
        if ($output->isVerbose()) {
            $this->showConfigurationSummary($io);
        }

        $io->success('Analysis complete!');

        return Command::SUCCESS;
    }

    /**
     * Analyze upcoming transitions in the specified time range.
     */
    private function analyzeUpcomingTransitions(
        SymfonyStyle $io,
        int $workspaceUid,
        int $languageUid,
        int $days
    ): void {
        $io->section('Upcoming Transitions');

        $now = \time();
        $endTime = $now + ($days * 86400);

        $transitions = $this->repository->findTransitionsInRange(
            $now,
            $endTime,
            $workspaceUid,
            $languageUid
        );

        if ($transitions === []) {
            $io->note("No transitions in the next {$days} days.");
            return;
        }

        $io->writeln(\sprintf('<info>Found %d transitions</info>', \count($transitions)));

        // Group transitions by day
        $transitionsPerDay = [];
        foreach ($transitions as $transition) {
            $date = \date('Y-m-d', $transition->timestamp);
            if (!isset($transitionsPerDay[$date])) {
                $transitionsPerDay[$date] = 0;
            }

            $transitionsPerDay[$date]++;
        }

        // Find peak days
        \arsort($transitionsPerDay);
        $peakDays = \array_slice($transitionsPerDay, 0, 5, true);

        $io->writeln("\n<comment>Peak Transition Days:</comment>");
        $table = new Table($io);
        $table->setHeaders(['Date', 'Transitions', 'Impact']);

        foreach ($peakDays as $date => $count) {
            $impact = $this->getImpactLevel($count);
            $table->addRow([$date, $count, $impact]);
        }

        $table->render();

        // Show next 10 transitions in verbose mode
        if ($io->isVerbose()) {
            $io->writeln("\n<comment>Next 10 Transitions:</comment>");
            $table = new Table($io);
            $table->setHeaders(['Time', 'Type', 'Table', 'Title']);

            $displayed = 0;
            foreach ($transitions as $transition) {
                if ($displayed >= 10) {
                    break;
                }

                $table->addRow([
                    \date('Y-m-d H:i', $transition->timestamp),
                    \ucfirst($transition->transitionType),
                    $transition->content->tableName,
                    \mb_substr($transition->content->title, 0, 40),
                ]);

                $displayed++;
            }

            $table->render();

            if (\count($transitions) > 10) {
                $io->writeln(\sprintf(
                    "\n<info>... and %d more transitions</info>",
                    \count($transitions) - 10
                ));
            }
        }
    }

    /**
     * Analyze harmonization impact on cache invalidations.
     */
    private function analyzeHarmonizationImpact(
        SymfonyStyle $io,
        int $workspaceUid,
        int $languageUid,
        int $days
    ): void {
        $io->section('Harmonization Impact Analysis');

        $now = \time();
        $endTime = $now + ($days * 86400);

        $transitions = $this->repository->findTransitionsInRange(
            $now,
            $endTime,
            $workspaceUid,
            $languageUid
        );

        if ($transitions === []) {
            return;
        }

        // Extract timestamps
        $timestamps = \array_map(
            fn ($transition) => $transition->timestamp,
            $transitions
        );

        // Calculate harmonization impact
        $impact = $this->harmonizationService->calculateHarmonizationImpact($timestamps);

        $io->table(
            ['Metric', 'Value'],
            [
                ['Original Transitions', $impact['original']],
                ['After Harmonization', $impact['harmonized']],
                ['Reduction', $impact['reduction'] . '%'],
                ['Cache Invalidations Saved', $impact['original'] - $impact['harmonized']],
            ]
        );

        if ($impact['reduction'] > 0) {
            $io->success(\sprintf(
                'Harmonization reduces cache invalidations by %.1f%%!',
                $impact['reduction']
            ));
        } else {
            $io->note('Current configuration shows no harmonization benefit. Consider adjusting slot configuration.');
        }

        // Show configured slots
        if ($io->isVerbose()) {
            $slots = $this->harmonizationService->getFormattedSlots();
            $io->writeln("\n<comment>Configured Time Slots:</comment>");
            $io->listing($slots);

            $tolerance = $this->configuration->getHarmonizationTolerance();
            $io->writeln(\sprintf(
                "<info>Tolerance: %d seconds (%d minutes)</info>\n",
                $tolerance,
                (int)($tolerance / 60)
            ));
        }
    }

    /**
     * Show current configuration summary.
     */
    private function showConfigurationSummary(SymfonyStyle $io): void
    {
        $io->section('Extension Configuration');

        $io->table(
            ['Setting', 'Value'],
            [
                ['Scoping Strategy', $this->configuration->getScopingStrategy()],
                ['Timing Strategy', $this->configuration->getTimingStrategy()],
                ['Harmonization Enabled', $this->configuration->isHarmonizationEnabled() ? 'Yes' : 'No'],
                ['Harmonization Slots', \implode(', ', $this->configuration->getHarmonizationSlots())],
                ['Harmonization Tolerance', $this->configuration->getHarmonizationTolerance() . ' seconds'],
                ['Auto-round Enabled', $this->configuration->isAutoRoundEnabled() ? 'Yes' : 'No'],
            ]
        );
    }

    /**
     * Get impact level label based on transition count.
     */
    private function getImpactLevel(int $count): string
    {
        if ($count >= 10) {
            return '<fg=red>HIGH</>';
        }

        if ($count >= 5) {
            return '<fg=yellow>MEDIUM</>';
        }

        return '<fg=green>LOW</>';
    }
}
