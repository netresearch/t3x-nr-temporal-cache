<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Domain\Repository;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The repository queries the backend module, the Reports status and the console commands
 * read: statistics, transitions per day, the temporal content of a page and single records.
 * They run against a real database here because each one builds its own query, with its
 * own restrictions, rather than going through the MIN() lookup the page lifetime uses.
 */
#[CoversClass(TemporalContentRepository::class)]
final class TemporalContentRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /** Midday, so that "day" and "next day" do not depend on the time the test runs. */
    private int $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->day = (int)(\floor(\time() / 86400) + 10) * 86400 + 43200;

        $pages = $this->get(ConnectionPool::class)->getConnectionForTable('pages');
        foreach ([
            // uid, title, starttime, endtime, sys_language_uid, deleted
            [900, 'Page with start', $this->day, 0, 0, 0],
            [901, 'Page with end', 0, $this->day + 86400, 0, 0],
            [902, 'Page with both', $this->day + 3600, $this->day + 86400 + 3600, 0, 0],
            [903, 'Page without times', 0, 0, 0, 0],
            [904, 'Deleted page with start', $this->day, 0, 0, 1],
        ] as [$uid, $title, $start, $end, $language, $deleted]) {
            $pages->insert('pages', [
                'uid' => $uid,
                'pid' => 0,
                'title' => $title,
                'hidden' => 0,
                'deleted' => $deleted,
                'starttime' => $start,
                'endtime' => $end,
                'sys_language_uid' => $language,
            ]);
        }

        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        foreach ([
            // uid, pid, header, starttime, endtime, sys_language_uid, hidden, deleted
            [910, 900, 'Content with start', $this->day + 7200, 0, 0, 0, 0],
            [911, 900, 'Hidden content with end', 0, $this->day + 7200, 0, 1, 0],
            [912, 900, 'Content without times', 0, 0, 0, 0, 0],
            [913, 900, 'Translated content with start', $this->day, 0, 1, 0, 0],
            [914, 900, 'Deleted content with start', $this->day, 0, 0, 0, 1],
            [915, 901, 'Content on another page', $this->day, 0, 0, 0, 0],
        ] as [$uid, $pid, $header, $start, $end, $language, $hidden, $deleted]) {
            $content->insert('tt_content', [
                'uid' => $uid,
                'pid' => $pid,
                'header' => $header,
                'hidden' => $hidden,
                'deleted' => $deleted,
                'starttime' => $start,
                'endtime' => $end,
                'sys_language_uid' => $language,
            ]);
        }
    }

    public function testGetStatisticsCountsTemporalRecordsByTableAndField(): void
    {
        // Pages 900-902 and content 910, 911, 913, 915; deleted and time-less records do not count.
        self::assertSame(
            [
                'total' => 7,
                'pages' => 3,
                'content' => 4,
                'withStart' => 4,
                'withEnd' => 2,
                'withBoth' => 1,
            ],
            $this->getSubject()->getStatistics()
        );
    }

    public function testCountTransitionsPerDayGroupsStartAndEndByDate(): void
    {
        $counts = $this->getSubject()->countTransitionsPerDay($this->day - 60, $this->day + 2 * 86400);

        // Default language only: 900, 902 (start), 910, 911 (hidden records are listed here,
        // the module shows them), 915 on the first day; 901 and 902 (end) on the second.
        self::assertSame(
            [
                \date('Y-m-d', $this->day) => 5,
                \date('Y-m-d', $this->day + 86400) => 2,
            ],
            $counts
        );
    }

    public function testFindTransitionsInRangeReturnsThemInChronologicalOrder(): void
    {
        $transitions = $this->getSubject()->findTransitionsInRange($this->day - 60, $this->day + 2 * 86400);

        $timestamps = \array_map(static fn (TransitionEvent $transition): int => $transition->timestamp, $transitions);
        $sorted = $timestamps;
        \sort($sorted);

        self::assertCount(7, $transitions);
        self::assertSame($sorted, $timestamps);
    }

    public function testFindByPageIdReturnsTemporalContentOfThatPageAndLanguage(): void
    {
        $records = $this->getSubject()->findByPageId(900);

        $byUid = [];
        foreach ($records as $record) {
            $byUid[$record->uid] = $record;
        }

        \ksort($byUid);

        // 912 has no times, 913 is another language, 914 is deleted, 915 is on page 901.
        self::assertSame([910, 911], \array_keys($byUid));
        self::assertSame('Content with start', $byUid[910]->title);
        self::assertSame($this->day + 7200, $byUid[910]->starttime);
        self::assertNull($byUid[910]->endtime);
        self::assertFalse($byUid[910]->hidden);
        self::assertTrue($byUid[911]->hidden);
        self::assertSame($this->day + 7200, $byUid[911]->endtime);
    }

    public function testFindByPageIdHonoursTheRequestedLanguage(): void
    {
        $records = $this->getSubject()->findByPageId(900, 0, 1);

        self::assertCount(1, $records);
        self::assertSame(913, $records[0]->uid);
        self::assertSame(1, $records[0]->languageUid);
    }

    public function testFindByUidReturnsThePageRecord(): void
    {
        $record = $this->getSubject()->findByUid(902, 'pages');

        self::assertInstanceOf(TemporalContent::class, $record);
        self::assertSame('Page with both', $record->title);
        self::assertSame(0, $record->pid);
        self::assertSame($this->day + 3600, $record->starttime);
        self::assertSame($this->day + 86400 + 3600, $record->endtime);
        self::assertTrue($record->isPage());
    }

    public function testFindByUidReturnsNullForADeletedOrMissingRecord(): void
    {
        self::assertNull($this->getSubject()->findByUid(914));
        self::assertNull($this->getSubject()->findByUid(99999));
    }

    public function testFindByUidRejectsATableThatIsNotMonitored(): void
    {
        // sys_category has starttime, endtime, hidden, deleted and workspace columns like a
        // monitored table, but TemporalMonitorRegistry does not know it. The record exists,
        // so only the table check can make the result null.
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_category')->insert('sys_category', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Scheduled category',
            'starttime' => $this->day,
            'endtime' => 0,
        ]);

        self::assertNull($this->getSubject()->findByUid(1, 'sys_category'));
    }

    private function getSubject(): TemporalContentRepository
    {
        return $this->get(TemporalContentRepository::class);
    }
}
