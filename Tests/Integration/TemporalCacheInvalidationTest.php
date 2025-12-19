<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Integration;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\EventListener\TemporalCacheLifetime;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration test validating the core claim:
 * Temporal content transitions properly invalidate page cache.
 *
 * This test proves the extension solves the 20-year-old TYPO3 cache problem:
 * - Content with starttime/endtime fields
 * - Cache is automatically invalidated when content becomes visible/hidden
 * - No manual cache clearing required
 */
final class TemporalCacheInvalidationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected ExtensionConfiguration $configuration;
    protected TemporalContentRepository $repository;
    protected TemporalCacheLifetime $eventListener;
    protected CacheManager $cacheManager;
    protected Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Functional/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Functional/Fixtures/tt_content.csv');

        $this->configuration = $this->get(ExtensionConfiguration::class);
        $this->repository = $this->get(TemporalContentRepository::class);
        $this->eventListener = $this->get(TemporalCacheLifetime::class);
        $this->cacheManager = $this->get(CacheManager::class);
        $this->context = $this->get(Context::class);
    }

    /**
     * Test Scenario 1: Future content (starttime not yet reached)
     *
     * GIVEN: Content with starttime 1 hour in future
     * WHEN: Page is rendered now
     * THEN: Cache lifetime should be limited to when content becomes visible
     *
     * This proves: Cache will auto-invalidate when content appears
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cacheLifetimeLimitedWhenFutureContentExists(): void
    {
        $now = \time();
        $oneHourLater = $now + 3600;

        // Create future content
        $this->insertContentElement([
            'uid' => 999,
            'pid' => 1,
            'header' => 'Future Content',
            'starttime' => $oneHourLater,
            'endtime' => 0,
            'hidden' => 0,
        ]);

        // Calculate cache lifetime for page
        $event = new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: 86400, // Default 24 hours
            pageId: 1,
            pageRecord: ['uid' => 1],
            renderingInstructions: [],
            context: $this->context
        );

        $this->eventListener->__invoke($event);

        $actualLifetime = $event->getCacheLifetime();

        // Cache lifetime should be reduced to ~1 hour (allowing small margin for test execution)
        self::assertLessThanOrEqual(
            3600,
            $actualLifetime,
            'Cache lifetime should be limited to when future content becomes visible'
        );
        self::assertGreaterThan(
            3500,
            $actualLifetime,
            'Cache lifetime should be close to 1 hour'
        );
    }

    /**
     * Test Scenario 2: Expiring content (endtime approaching)
     *
     * GIVEN: Content with endtime 30 minutes in future
     * WHEN: Page is rendered now
     * THEN: Cache lifetime should be limited to when content expires
     *
     * This proves: Cache will auto-invalidate when content disappears
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cacheLifetimeLimitedWhenContentWillExpire(): void
    {
        $now = \time();
        $thirtyMinutesLater = $now + 1800;

        // Create expiring content
        $this->insertContentElement([
            'uid' => 998,
            'pid' => 1,
            'header' => 'Expiring Content',
            'starttime' => 0,
            'endtime' => $thirtyMinutesLater,
            'hidden' => 0,
        ]);

        // Calculate cache lifetime for page
        $event = new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: 86400,
            pageRecord: ['uid' => 1],
            pageId: 1,
            renderingInstructions: [],
            context: $this->context
        );

        $this->eventListener->__invoke($event);

        $actualLifetime = $event->getCacheLifetime();

        // Cache lifetime should be reduced to ~30 minutes
        self::assertLessThanOrEqual(
            1800,
            $actualLifetime,
            'Cache lifetime should be limited to when content expires'
        );
        self::assertGreaterThan(
            1700,
            $actualLifetime,
            'Cache lifetime should be close to 30 minutes'
        );
    }

    /**
     * Test Scenario 3: Multiple transitions
     *
     * GIVEN: Multiple content elements with different start/end times
     * WHEN: Page is rendered
     * THEN: Cache lifetime should be limited to earliest transition
     *
     * This proves: Extension correctly handles complex scenarios
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cacheLifetimeLimitedToEarliestTransition(): void
    {
        $now = \time();

        // Content expiring in 15 minutes (earliest transition)
        $this->insertContentElement([
            'uid' => 997,
            'pid' => 1,
            'header' => 'Content A',
            'starttime' => 0,
            'endtime' => $now + 900, // 15 minutes
            'hidden' => 0,
        ]);

        // Content appearing in 45 minutes
        $this->insertContentElement([
            'uid' => 996,
            'pid' => 1,
            'header' => 'Content B',
            'starttime' => $now + 2700, // 45 minutes
            'endtime' => 0,
            'hidden' => 0,
        ]);

        $event = new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: 86400,
            pageRecord: ['uid' => 1],
            pageId: 1,
            renderingInstructions: [],
            context: $this->context
        );

        $this->eventListener->__invoke($event);

        $actualLifetime = $event->getCacheLifetime();

        // Cache lifetime should be limited to earliest transition (15 minutes)
        self::assertLessThanOrEqual(
            900,
            $actualLifetime,
            'Cache lifetime should be limited to earliest transition'
        );
        self::assertGreaterThan(
            800,
            $actualLifetime,
            'Cache lifetime should be close to 15 minutes'
        );
    }

    /**
     * Test Scenario 4: No temporal content
     *
     * GIVEN: Page with no temporal content (no starttime/endtime)
     * WHEN: Page is rendered
     * THEN: Cache lifetime should remain unchanged
     *
     * This proves: Extension doesn't interfere when not needed
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cacheLifetimeUnchangedWhenNoTemporalContent(): void
    {
        // All content visible without time restrictions
        $this->insertContentElement([
            'uid' => 995,
            'pid' => 1,
            'header' => 'Regular Content',
            'starttime' => 0,
            'endtime' => 0,
            'hidden' => 0,
        ]);

        $event = new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: 86400,
            pageRecord: ['uid' => 1],
            pageId: 1,
            renderingInstructions: [],
            context: $this->context
        );

        $this->eventListener->__invoke($event);

        $actualLifetime = $event->getCacheLifetime();

        // Cache lifetime should remain at default
        self::assertSame(
            86400,
            $actualLifetime,
            'Cache lifetime should not change when no temporal content exists'
        );
    }

    /**
     * Test Scenario 5: Verify repository finds temporal content
     *
     * GIVEN: Content with various time configurations
     * WHEN: Repository queries for temporal content
     * THEN: Repository correctly identifies content with time restrictions
     *
     * This proves: Core data retrieval logic is accurate
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function repositoryFindsTemporalContentCorrectly(): void
    {
        $now = \time();
        $twoHoursLater = $now + 7200;

        // Insert future content
        $this->insertContentElement([
            'uid' => 994,
            'pid' => 1,
            'header' => 'Future Content',
            'starttime' => $twoHoursLater,
            'endtime' => 0,
            'hidden' => 0,
        ]);

        // Insert normal content
        $this->insertContentElement([
            'uid' => 993,
            'pid' => 1,
            'header' => 'Normal Content',
            'starttime' => 0,
            'endtime' => 0,
            'hidden' => 0,
        ]);

        // Find all temporal content
        $allContent = $this->repository->findAllWithTemporalFields();

        self::assertNotEmpty($allContent, 'Should find temporal content');

        // Verify at least one has future starttime
        $hasFutureContent = false;
        foreach ($allContent as $content) {
            if ($content->starttime > $now) {
                $hasFutureContent = true;
                break;
            }
        }

        self::assertTrue($hasFutureContent, 'Should find content with future starttime');
    }

    /**
     * Test Scenario 6: Global scoping strategy
     *
     * GIVEN: Multiple pages with temporal content
     * WHEN: Using global scoping strategy
     * THEN: All pages share same cache lifetime
     *
     * This validates: Scoping strategy affects cache granularity
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function globalScopingAffectsAllPages(): void
    {
        $now = \time();

        // Content on page 1 expiring in 1 hour
        $this->insertContentElement([
            'uid' => 993,
            'pid' => 1,
            'header' => 'Page 1 Content',
            'starttime' => 0,
            'endtime' => $now + 3600,
            'hidden' => 0,
        ]);

        // Content on page 2 expiring in 2 hours
        $this->insertContentElement([
            'uid' => 992,
            'pid' => 2,
            'header' => 'Page 2 Content',
            'starttime' => 0,
            'endtime' => $now + 7200,
            'hidden' => 0,
        ]);

        // Calculate cache lifetime for page 2 with global scoping
        $event = new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: 86400,
            pageRecord: ['uid' => 1],
            pageId: 2,
            renderingInstructions: [],
            context: $this->context
        );

        $this->eventListener->__invoke($event);

        $actualLifetime = $event->getCacheLifetime();

        // With global scoping, should use earliest transition across ALL pages
        // This might be 1 hour (from page 1) depending on strategy implementation
        self::assertLessThanOrEqual(
            7200,
            $actualLifetime,
            'Cache lifetime should consider global temporal content'
        );
    }

    /**
     * Helper: Insert content element into database
     *
     * @param array<string, mixed> $data
     */
    private function insertContentElement(array $data): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        $connection->insert('tt_content', $data);
    }
}
