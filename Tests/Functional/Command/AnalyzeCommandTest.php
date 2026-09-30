<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

namespace Netresearch\TemporalCache\Tests\Functional\Command;

use Netresearch\TemporalCache\Command\AnalyzeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * temporalcache:analyze against a real database, in verbose mode: the transition list,
 * the peak-day rating, the harmonization impact and the configuration summary.
 */
#[CoversClass(AnalyzeCommand::class)]
final class AnalyzeCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nr_temporal_cache' => [
                'harmonization' => [
                    'enabled' => '1',
                    'slots' => '00:00,12:00',
                    'tolerance' => '3600',
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // 12:00 UTC in three days: the eleven transitions below stay on one date in any
        // time zone within twelve hours of UTC.
        $slot = ((int)\floor(\time() / 86400) + 3) * 86400 + 43200;

        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        for ($i = 0; $i < 11; $i++) {
            $content->insert('tt_content', [
                'uid' => 100 + $i,
                'pid' => 1,
                'header' => 'Launch ' . $i,
                'starttime' => $slot + ($i + 1) * 60,
                'endtime' => 0,
                'sys_language_uid' => 0,
            ]);
        }

        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Campaign page',
            'starttime' => 0,
            'endtime' => $slot + 86400 + 300,
            'sys_language_uid' => 0,
        ]);
    }

    public function testVerboseAnalysisReportsTransitionsImpactAndConfiguration(): void
    {
        $tester = new CommandTester($this->get(AnalyzeCommand::class));
        $status = $tester->execute(['--days' => '10'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $status);
        $display = $tester->getDisplay();

        self::assertStringContainsString('Found 12 transitions', $display);
        // Eleven transitions on one day rate as high impact, the single one as low.
        self::assertStringContainsString('HIGH', $display);
        self::assertStringContainsString('LOW', $display);
        self::assertStringContainsString('Next 10 Transitions:', $display);
        self::assertStringContainsString('Launch 0', $display);
        self::assertStringContainsString('... and 2 more transitions', $display);

        // All eleven launches round onto the same noon slot, the page end onto the next one.
        self::assertStringContainsString('Harmonization reduces cache invalidations', $display);
        self::assertStringContainsString('Configured Time Slots:', $display);
        self::assertStringContainsString('Tolerance: 3600 seconds (60 minutes)', $display);

        self::assertStringContainsString('Extension Configuration', $display);
        self::assertMatchesRegularExpression('/Scoping Strategy\s+global/', $display);
        self::assertMatchesRegularExpression('/Harmonization Enabled\s+Yes/', $display);
        self::assertStringContainsString('Analysis complete!', $display);
    }

    public function testNormalVerbosityOmitsTheDetailLists(): void
    {
        $tester = new CommandTester($this->get(AnalyzeCommand::class));
        $status = $tester->execute(['--days' => '10']);

        self::assertSame(0, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Found 12 transitions', $display);
        self::assertStringNotContainsString('Next 10 Transitions:', $display);
        self::assertStringNotContainsString('Configured Time Slots:', $display);
    }
}
