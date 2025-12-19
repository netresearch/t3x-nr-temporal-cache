<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class SchedulerTimingStrategyTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private ScopingStrategyInterface&MockObject $scopingStrategy;
    private CacheManager&MockObject $cacheManager;
    private Context&MockObject $context;
    private LoggerInterface&MockObject $logger;
    private ExtensionConfiguration&MockObject $configuration;
    private SchedulerTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->context = $this->createMock(Context::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configuration = $this->createMock(ExtensionConfiguration::class);

        $this->subject = new SchedulerTimingStrategy(
            $this->scopingStrategy,
            $this->cacheManager,
            $this->context,
            $this->logger,
            $this->configuration
        );
    }

    /**     */
    public function testHandlesContentTypeReturnsAlwaysTrue(): void
    {
        self::assertTrue($this->subject->handlesContentType('page'));
        self::assertTrue($this->subject->handlesContentType('content'));
    }

    /**     */
    public function testGetCacheLifetimeReturnsNull(): void
    {
        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertNull($lifetime);
    }

    /**     */
    public function testProcessTransitionFlushesCache(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test',
            pid: 5,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $event = new TransitionEvent(
            content: $content,
            timestamp: \time(),
            transitionType: 'start'
        );

        $this->scopingStrategy
            ->method('getCacheTagsToFlush')
            ->willReturn(['pageId_5', 'pageId_10']);

        $cache = $this->createMock(FrontendInterface::class);
        // Code calls flushByTag() in a loop, not flushByTags() once
        $cache->expects(self::exactly(2))
            ->method('flushByTag')
            ->willReturnCallback(function ($tag) {
                self::assertContains($tag, ['pageId_5', 'pageId_10']);
            });

        $this->cacheManager
            ->method('getCache')
            ->with('pages')
            ->willReturn($cache);

        $this->subject->processTransition($event);
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('scheduler', $this->subject->getName());
    }
}
