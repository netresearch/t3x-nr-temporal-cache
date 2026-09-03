<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Domain\Repository;

use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A deleted record must never reach a transition lookup. Acting on one shortens a page's
 * cache lifetime for content an editor removed, or flushes caches for a page that no longer
 * shows anything — and on a site that deletes a lot of temporal content, permanently.
 *
 * AGENTS.md names "always apply DeletedRestriction" as a security rule, but removing
 * `->add($this->deletedRestriction)` from the query behind findAllWithTemporalFields()
 * left both suites green, so nothing enforced it. These tests do.
 */
#[CoversClass(TemporalContentRepository::class)]
final class DeletedRecordExclusionTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    private int $future;

    protected function setUp(): void
    {
        parent::setUp();
        $this->future = \time() + 3600;

        $pages = $this->get(ConnectionPool::class)->getConnectionForTable('pages');
        $pages->insert('pages', [
            'uid' => 800,
            'pid' => 0,
            'title' => 'Live page with a pending transition',
            'hidden' => 0,
            'deleted' => 0,
            'starttime' => $this->future,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);
        $pages->insert('pages', [
            'uid' => 801,
            'pid' => 0,
            'title' => 'Deleted page with a pending transition',
            'hidden' => 0,
            'deleted' => 1,
            'starttime' => $this->future,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);

        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        $content->insert('tt_content', [
            'uid' => 810,
            'pid' => 800,
            'header' => 'Live content with a pending transition',
            'hidden' => 0,
            'deleted' => 0,
            'starttime' => $this->future,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);
        $content->insert('tt_content', [
            'uid' => 811,
            'pid' => 800,
            'header' => 'Deleted content with a pending transition',
            'hidden' => 0,
            'deleted' => 1,
            'starttime' => $this->future,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);
    }

    public function testFindAllWithTemporalFieldsSkipsDeletedRecords(): void
    {
        $uids = \array_map(
            static fn ($record): int => $record->uid,
            $this->get(TemporalContentRepository::class)->findAllWithTemporalFields()
        );

        self::assertContains(800, $uids, 'The live page was not returned at all — the fixture is wrong.');
        self::assertContains(810, $uids, 'The live content element was not returned at all — the fixture is wrong.');

        self::assertNotContains(
            801,
            $uids,
            'A deleted page reached the transition lookup. Its starttime would shorten cache '
            . 'lifetimes for content no editor can see.'
        );
        self::assertNotContains(
            811,
            $uids,
            'A deleted content element reached the transition lookup. Its starttime would '
            . 'shorten cache lifetimes for content no editor can see.'
        );
    }

    public function testTransitionsInRangeSkipDeletedRecords(): void
    {
        $transitions = $this->get(TemporalContentRepository::class)->findTransitionsInRange(
            \time(),
            $this->future + 3600
        );

        $uids = \array_map(static fn ($event): int => $event->content->uid, $transitions);

        self::assertContains(800, $uids, 'The live page produced no transition — the fixture is wrong.');
        self::assertNotContains(801, $uids, 'A deleted page produced a transition to act on.');
        self::assertNotContains(811, $uids, 'A deleted content element produced a transition to act on.');
    }
}
