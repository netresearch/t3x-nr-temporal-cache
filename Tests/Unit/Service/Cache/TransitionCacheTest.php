<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Cache;

use Netresearch\TemporalCache\Service\Cache\TransitionCache;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Cache\TransitionCache
 */
final class TransitionCacheTest extends UnitTestCase
{
    private TransitionCache $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TransitionCache();
    }

    /**     */
    public function testGetNextTransitionReturnsNullWhenNotCached(): void
    {
        $result = $this->subject->getNextTransition(1000, 0, 0);

        self::assertNull($result);
    }

    /**     */
    public function testHasNextTransitionReturnsFalseWhenNotCached(): void
    {
        $result = $this->subject->hasNextTransition(1000, 0, 0);

        self::assertFalse($result);
    }

    /**     */
    public function testSetNextTransitionStoresValue(): void
    {
        $timestamp = \time();
        $transition = $timestamp + 3600;

        $this->subject->setNextTransition($timestamp, 0, 0, $transition);

        self::assertTrue($this->subject->hasNextTransition($timestamp, 0, 0));
        self::assertSame($transition, $this->subject->getNextTransition($timestamp, 0, 0));
    }

    /**     */
    public function testSetNextTransitionCanStoreNull(): void
    {
        $timestamp = \time();

        $this->subject->setNextTransition($timestamp, 0, 0, null);

        self::assertTrue($this->subject->hasNextTransition($timestamp, 0, 0));
        self::assertNull($this->subject->getNextTransition($timestamp, 0, 0));
    }

    /**     */
    public function testCacheKeyIncludesCurrentTimestamp(): void
    {
        $transition = \time() + 3600;

        $this->subject->setNextTransition(1000, 0, 0, $transition);
        $this->subject->setNextTransition(2000, 0, 0, $transition + 1000);

        self::assertSame($transition, $this->subject->getNextTransition(1000, 0, 0));
        self::assertSame($transition + 1000, $this->subject->getNextTransition(2000, 0, 0));
    }

    /**     */
    public function testCacheKeyIncludesWorkspaceUid(): void
    {
        $transition = \time() + 3600;

        $this->subject->setNextTransition(1000, 0, 0, $transition);
        $this->subject->setNextTransition(1000, 1, 0, $transition + 1000);

        self::assertSame($transition, $this->subject->getNextTransition(1000, 0, 0));
        self::assertSame($transition + 1000, $this->subject->getNextTransition(1000, 1, 0));
    }

    /**     */
    public function testCacheKeyIncludesLanguageUid(): void
    {
        $transition = \time() + 3600;

        $this->subject->setNextTransition(1000, 0, 0, $transition);
        $this->subject->setNextTransition(1000, 0, 1, $transition + 1000);

        self::assertSame($transition, $this->subject->getNextTransition(1000, 0, 0));
        self::assertSame($transition + 1000, $this->subject->getNextTransition(1000, 0, 1));
    }

    /**     */
    public function testClearRemovesAllCachedValues(): void
    {
        $timestamp = \time();

        $this->subject->setNextTransition($timestamp, 0, 0, $timestamp + 3600);
        $this->subject->setNextTransition($timestamp, 1, 0, $timestamp + 7200);
        $this->subject->setNextTransition($timestamp, 0, 1, $timestamp + 10800);

        self::assertTrue($this->subject->hasNextTransition($timestamp, 0, 0));
        self::assertTrue($this->subject->hasNextTransition($timestamp, 1, 0));
        self::assertTrue($this->subject->hasNextTransition($timestamp, 0, 1));

        $this->subject->clear();

        self::assertFalse($this->subject->hasNextTransition($timestamp, 0, 0));
        self::assertFalse($this->subject->hasNextTransition($timestamp, 1, 0));
        self::assertFalse($this->subject->hasNextTransition($timestamp, 0, 1));
    }

    /**     */
    public function testGetStatsReturnsZeroEntriesWhenEmpty(): void
    {
        $stats = $this->subject->getStats();

        self::assertSame(0, $stats['entries']);
        self::assertGreaterThan(0, $stats['memory']); // Empty array still has serialization overhead
    }

    /**     */
    public function testGetStatsReturnsCorrectEntryCount(): void
    {
        $timestamp = \time();

        $this->subject->setNextTransition($timestamp, 0, 0, $timestamp + 3600);
        $this->subject->setNextTransition($timestamp, 1, 0, $timestamp + 7200);
        $this->subject->setNextTransition($timestamp, 0, 1, $timestamp + 10800);

        $stats = $this->subject->getStats();

        self::assertSame(3, $stats['entries']);
        self::assertGreaterThan(0, $stats['memory']);
    }

    /**     */
    public function testGetStatsMemoryIncreasesWithMoreEntries(): void
    {
        $timestamp = \time();

        $this->subject->setNextTransition($timestamp, 0, 0, $timestamp + 3600);
        $statsWithOneEntry = $this->subject->getStats();

        $this->subject->setNextTransition($timestamp, 1, 0, $timestamp + 7200);
        $this->subject->setNextTransition($timestamp, 0, 1, $timestamp + 10800);
        $statsWithThreeEntries = $this->subject->getStats();

        self::assertGreaterThan($statsWithOneEntry['memory'], $statsWithThreeEntries['memory']);
    }

    /**     */
    public function testOverwritingCachedValueWorks(): void
    {
        $timestamp = \time();
        $firstValue = $timestamp + 3600;
        $secondValue = $timestamp + 7200;

        $this->subject->setNextTransition($timestamp, 0, 0, $firstValue);
        self::assertSame($firstValue, $this->subject->getNextTransition($timestamp, 0, 0));

        $this->subject->setNextTransition($timestamp, 0, 0, $secondValue);
        self::assertSame($secondValue, $this->subject->getNextTransition($timestamp, 0, 0));
    }

    /**     */
    public function testOverwritingWithNullWorks(): void
    {
        $timestamp = \time();
        $firstValue = $timestamp + 3600;

        $this->subject->setNextTransition($timestamp, 0, 0, $firstValue);
        self::assertSame($firstValue, $this->subject->getNextTransition($timestamp, 0, 0));

        $this->subject->setNextTransition($timestamp, 0, 0, null);
        self::assertNull($this->subject->getNextTransition($timestamp, 0, 0));
        self::assertTrue($this->subject->hasNextTransition($timestamp, 0, 0)); // Still cached, just null
    }
}
