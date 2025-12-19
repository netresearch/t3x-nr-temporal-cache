<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\EventListener;

use Netresearch\TemporalCache\EventListener\TemporalCacheLifetime;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for TemporalCacheLifetime event listener
 *
 * Tests actual integration with TYPO3 database and context system.
 *
 * @covers \Netresearch\TemporalCache\EventListener\TemporalCacheLifetime
 */
final class TemporalCacheLifetimeTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Import database fixtures
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
    }

    /**     */
    public function testEventListenerIsRegisteredInContainer(): void
    {
        $subject = $this->get(TemporalCacheLifetime::class);

        self::assertInstanceOf(TemporalCacheLifetime::class, $subject);
    }

    /**     */
    public function testCalculatesLifetimeBasedOnPageStarttime(): void
    {
        $now = \time();
        $futureStarttime = $now + 3600; // 1 hour from now

        // Insert page with future starttime
        $this->insertPage($futureStarttime, 0);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400); // 24h default

        $subject->__invoke($event);

        // Assert lifetime is approximately 1 hour (allow 2 second tolerance)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(3598, $lifetime);
        self::assertLessThan(3602, $lifetime);
    }

    /**     */
    public function testCalculatesLifetimeBasedOnPageEndtime(): void
    {
        $now = \time();
        $futureEndtime = $now + 7200; // 2 hours from now

        // Insert page with future endtime
        $this->insertPage(0, $futureEndtime);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Assert lifetime is approximately 2 hours
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(7198, $lifetime);
        self::assertLessThan(7202, $lifetime);
    }

    /**     */
    public function testCalculatesLifetimeBasedOnContentElementStarttime(): void
    {
        $now = \time();
        $futureStarttime = $now + 1800; // 30 minutes from now

        // Insert content element with future starttime
        $this->insertContentElement($futureStarttime, 0);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Assert lifetime is approximately 30 minutes
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(1798, $lifetime);
        self::assertLessThan(1802, $lifetime);
    }

    /**     */
    public function testSelectsNearestTransitionFromMultipleRecords(): void
    {
        $now = \time();
        $nearTransition = $now + 1800;  // 30 min
        $farTransition = $now + 7200;   // 2 hours

        // Insert multiple records with different transitions
        $this->insertPage($farTransition, 0);
        $this->insertContentElement($nearTransition, 0);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should select nearest (30 min)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(1798, $lifetime);
        self::assertLessThan(1802, $lifetime);
    }

    /**     */
    public function testRespectsLanguageContext(): void
    {
        $now = \time();
        $futureStarttime = $now + 3600;

        // Insert page in default language (0)
        $this->insertPage($futureStarttime, 0, 0);

        // Insert page in language 1
        $this->insertPage($now + 7200, 0, 1);

        // Test with language 0 context
        $context = $this->get(Context::class);
        $context->setAspect('language', new \TYPO3\CMS\Core\Context\LanguageAspect(0, 0, \TYPO3\CMS\Core\Context\LanguageAspect::OVERLAYS_OFF));

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should use language 0 page (1 hour)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(3598, $lifetime);
        self::assertLessThan(3602, $lifetime);
    }

    /**     */
    public function testIgnoresPastStarttimes(): void
    {
        $now = \time();
        $pastStarttime = $now - 3600;   // 1 hour ago
        $futureEndtime = $now + 3600;   // 1 hour from now

        $this->insertPage($pastStarttime, $futureEndtime);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should only consider future endtime
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(3598, $lifetime);
        self::assertLessThan(3602, $lifetime);
    }

    /**     */
    public function testIgnoresZeroTimestamps(): void
    {
        $now = \time();
        $futureStarttime = $now + 3600;

        // Insert page with zero timestamps (should be ignored)
        $this->insertPage(0, 0);

        // Insert page with actual timestamp
        $this->insertPage($futureStarttime, 0);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should use non-zero timestamp
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(3598, $lifetime);
        self::assertLessThan(3602, $lifetime);
    }

    /**     */
    public function testDoesNotModifyLifetimeWhenNoTemporalContent(): void
    {
        // Insert page with no temporal restrictions
        $this->insertPage(0, 0);
        $this->insertContentElement(0, 0);

        $subject = $this->get(TemporalCacheLifetime::class);
        $originalLifetime = 86400;
        $event = new ModifyCacheLifetimeForPageEvent($originalLifetime);

        $subject->__invoke($event);

        // Lifetime should remain unchanged
        self::assertEquals($originalLifetime, $event->getCacheLifetime());
    }

    /**     */
    public function testHandlesHiddenContentElements(): void
    {
        $now = \time();
        $futureStarttime = $now + 3600;

        // Insert hidden content element (should still be considered for cache lifetime)
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', [
            'pid' => 1,
            'header' => 'Hidden Content',
            'starttime' => $futureStarttime,
            'endtime' => 0,
            'hidden' => 1,
            'sys_language_uid' => 0,
        ]);

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should still calculate lifetime based on hidden element
        // (hidden elements may become visible, affecting cache)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(0, $lifetime);
    }

    /**     */
    public function testHandlesMultipleContentElementsOnSamePage(): void
    {
        $now = \time();
        $transitions = [
            $now + 1800,  // 30 min
            $now + 3600,  // 1 hour
            $now + 5400,  // 1.5 hours
        ];

        foreach ($transitions as $transition) {
            $this->insertContentElement($transition, 0);
        }

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $subject->__invoke($event);

        // Should select earliest transition (30 min)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(1798, $lifetime);
        self::assertLessThan(1802, $lifetime);
    }

    /**     */
    public function testPerformanceWithManyRecords(): void
    {
        $now = \time();

        // Insert 100 pages with various temporal settings
        for ($i = 0; $i < 100; $i++) {
            $this->insertPage(
                $now + ($i * 100),
                $now + ($i * 200)
            );
        }

        // Insert 100 content elements
        for ($i = 0; $i < 100; $i++) {
            $this->insertContentElement(
                $now + ($i * 150),
                $now + ($i * 250)
            );
        }

        $subject = $this->get(TemporalCacheLifetime::class);
        $event = new ModifyCacheLifetimeForPageEvent(86400);

        $startTime = \microtime(true);
        $subject->__invoke($event);
        $duration = \microtime(true) - $startTime;

        // Performance assertion: Should complete in < 50ms even with 200 records
        self::assertLessThan(0.05, $duration, 'Performance degraded: took ' . ($duration * 1000) . 'ms');

        // Should select earliest transition (first record)
        $lifetime = $event->getCacheLifetime();
        self::assertGreaterThan(0, $lifetime);
        self::assertLessThan(200, $lifetime);
    }

    private function insertPage(int $starttime, int $endtime, int $languageUid = 0): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Test Page',
            'starttime' => $starttime,
            'endtime' => $endtime,
            'hidden' => 0,
            'sys_language_uid' => $languageUid,
        ]);
    }

    private function insertContentElement(int $starttime, int $endtime): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', [
            'pid' => 1,
            'header' => 'Test Content',
            'starttime' => $starttime,
            'endtime' => $endtime,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ]);
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return $this->get(ConnectionPool::class);
    }
}
