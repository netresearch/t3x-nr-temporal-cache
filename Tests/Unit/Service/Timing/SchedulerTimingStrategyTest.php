<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(SchedulerTimingStrategy::class)]
#[UsesClass(TemporalContent::class)]
#[UsesClass(TransitionEvent::class)]
final class SchedulerTimingStrategyTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private ScopingStrategyInterface&Stub $scopingStrategy;

    private CacheManager&MockObject $cacheManager;

    private Context&Stub $context;

    private LoggerInterface&Stub $logger;

    private ExtensionConfiguration&Stub $configuration;

    private SchedulerTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopingStrategy = $this->createStub(ScopingStrategyInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->context = $this->createStub(Context::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);

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
            ->willReturnCallback(function ($tag): void {
                self::assertContains($tag, ['pageId_5', 'pageId_10']);
            });

        $this->cacheManager
            ->expects(self::once())
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

    public function testProcessTransitionLogsTheFlushedTagsWhenDebugLoggingIsEnabled(): void
    {
        $this->scopingStrategy->method('getCacheTagsToFlush')->willReturn(['pageId_5']);
        $this->scopingStrategy->method('getName')->willReturn('per-page');
        $this->cacheManager->method('getCache')->willReturn($this->createStub(FrontendInterface::class));
        $this->configuration->method('isDebugLoggingEnabled')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Processed temporal transition',
                self::callback(static fn (array $context): bool => $context['flushed_tags'] === ['pageId_5']
                    && $context['strategy'] === 'per-page'
                    && \str_contains((string)$context['event'], 'tt_content'))
            );
        $logger->expects(self::never())->method('error');

        $this->createSubjectWithLogger($logger)->processTransition($this->createEvent());
    }

    public function testProcessTransitionDoesNotLogWhenTheConfigurationCannotBeRead(): void
    {
        $this->scopingStrategy->method('getCacheTagsToFlush')->willReturn(['pageId_5']);
        $this->cacheManager->method('getCache')->willReturn($this->createStub(FrontendInterface::class));
        $this->configuration->method('isDebugLoggingEnabled')->willThrowException(new RuntimeException('no config'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::never())->method('error');

        $this->createSubjectWithLogger($logger)->processTransition($this->createEvent());
    }

    public function testProcessTransitionLogsAFailedFlushAndDoesNotThrow(): void
    {
        $this->scopingStrategy->method('getCacheTagsToFlush')->willReturn(['pageId_5']);
        $this->cacheManager->method('getCache')->willThrowException(new RuntimeException('cache unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to process temporal transition',
                self::callback(static fn (array $context): bool => $context['error'] === 'cache unavailable')
            );

        // The scheduler task processes the next transition after this one, so no exception.
        $this->createSubjectWithLogger($logger)->processTransition($this->createEvent());
    }

    private function createSubjectWithLogger(LoggerInterface $logger): SchedulerTimingStrategy
    {
        return new SchedulerTimingStrategy(
            $this->scopingStrategy,
            $this->cacheManager,
            $this->context,
            $logger,
            $this->configuration
        );
    }

    private function createEvent(): TransitionEvent
    {
        return new TransitionEvent(
            content: new TemporalContent(
                uid: 123,
                tableName: 'tt_content',
                title: 'Test',
                pid: 5,
                starttime: 1893456000,
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
            timestamp: 1893456000,
            transitionType: 'start'
        );
    }
}
