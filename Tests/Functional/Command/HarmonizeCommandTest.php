<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Command;

use Netresearch\TemporalCache\Command\HarmonizeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * temporalcache:harmonize against a real database: what a live run writes, what a dry run
 * leaves alone, and that a live run flushes the page cache so the new times take effect.
 */
#[CoversClass(HarmonizeCommand::class)]
final class HarmonizeCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        // The testing framework gives the page cache a null backend; a database backend
        // lets the test see whether the command flushed it.
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
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

    /** 12:00 UTC in ten days; harmonization works on UTC times of day. */
    private int $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slot = ((int)\floor(\time() / 86400) + 10) * 86400 + 43200;

        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Page ten minutes after the slot',
            'starttime' => $this->slot + 600,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);

        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        // Twelve content elements a few minutes before a noon slot, one per day.
        for ($i = 0; $i < 12; $i++) {
            $content->insert('tt_content', [
                'uid' => 100 + $i,
                'pid' => 1,
                'header' => 'Content ' . $i,
                'starttime' => 0,
                'endtime' => $this->slot + $i * 86400 - 300,
                'sys_language_uid' => 0,
            ]);
        }

        // Already on a slot: nothing to change.
        $content->insert('tt_content', [
            'uid' => 200,
            'pid' => 1,
            'header' => 'Content on the slot',
            'starttime' => $this->slot,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);
    }

    public function testLiveRunWritesSlotTimesAndFlushesThePageCache(): void
    {
        $pageCache = $this->get(CacheManager::class)->getCache('pages');
        $pageCache->set('temporal_probe', 'cached page', ['pageId_1']);
        self::assertTrue($pageCache->has('temporal_probe'), 'The page cache in this instance stores nothing.');

        $tester = new CommandTester($this->get(HarmonizeCommand::class));
        $tester->setInputs(['yes']);

        $status = $tester->execute([], ['interactive' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Sample Changes (first 10)', $display);
        self::assertStringContainsString('... and 3 more changes', $display);
        self::assertStringContainsString('Harmonization complete!', $display);
        self::assertMatchesRegularExpression('/Updated\s+13\b/', $display);

        self::assertSame($this->slot, $this->readTime('pages', 1, 'starttime'));
        for ($i = 0; $i < 12; $i++) {
            self::assertSame($this->slot + $i * 86400, $this->readTime('tt_content', 100 + $i, 'endtime'));
        }

        self::assertSame($this->slot, $this->readTime('tt_content', 200, 'starttime'));

        self::assertFalse($pageCache->has('temporal_probe'), 'A live run must flush the page cache.');
    }

    public function testDryRunChangesNothing(): void
    {
        $tester = new CommandTester($this->get(HarmonizeCommand::class));
        $status = $tester->execute(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('DRY-RUN MODE', $display);
        self::assertStringContainsString('Harmonization would reduce cache invalidations', $display);

        self::assertSame($this->slot + 600, $this->readTime('pages', 1, 'starttime'));
        self::assertSame($this->slot - 300, $this->readTime('tt_content', 100, 'endtime'));
    }

    public function testNonInteractiveLiveRunDoesNotWrite(): void
    {
        $tester = new CommandTester($this->get(HarmonizeCommand::class));
        $status = $tester->execute([], ['interactive' => false]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Harmonization cancelled by user.', $tester->getDisplay());
        self::assertSame($this->slot + 600, $this->readTime('pages', 1, 'starttime'));
    }

    public function testTableFilterLimitsTheRunToThatTable(): void
    {
        $tester = new CommandTester($this->get(HarmonizeCommand::class));
        $tester->setInputs(['yes']);

        $status = $tester->execute(['--table' => 'pages'], ['interactive' => true]);

        self::assertSame(0, $status);
        self::assertSame($this->slot, $this->readTime('pages', 1, 'starttime'));
        self::assertSame($this->slot - 300, $this->readTime('tt_content', 100, 'endtime'));
    }

    private function readTime(string $table, int $uid, string $field): int
    {
        // Without restrictions: the default ones would hide a record whose starttime lies ahead.
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $value = $queryBuilder
            ->select($field)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        self::assertIsInt($value);

        return $value;
    }
}
