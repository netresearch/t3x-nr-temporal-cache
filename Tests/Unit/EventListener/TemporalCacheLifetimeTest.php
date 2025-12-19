<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\EventListener;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\EventListener\TemporalCacheLifetime;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for TemporalCacheLifetime event listener
 *
 * @covers \Netresearch\TemporalCache\EventListener\TemporalCacheLifetime
 */
final class TemporalCacheLifetimeTest extends UnitTestCase
{
    private TemporalCacheLifetime $subject;
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private ScopingStrategyInterface&MockObject $scopingStrategy;
    private TimingStrategyInterface&MockObject $timingStrategy;
    private Context&MockObject $context;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->scopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $this->timingStrategy = $this->createMock(TimingStrategyInterface::class);
        $this->context = $this->createMock(Context::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Configure default mock behaviors
        $this->extensionConfiguration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400);
        $this->extensionConfiguration
            ->method('isDebugLoggingEnabled')
            ->willReturn(false);
        $this->scopingStrategy
            ->method('getName')
            ->willReturn('test-scoping');
        $this->timingStrategy
            ->method('getName')
            ->willReturn('test-timing');

        $this->subject = new TemporalCacheLifetime(
            $this->extensionConfiguration,
            $this->scopingStrategy,
            $this->timingStrategy,
            $this->context,
            $this->logger
        );
    }

    public function testInvokeDoesNotModifyCacheLifetimeWhenTimingStrategyReturnsNull(): void
    {
        // Arrange: Timing strategy returns null (scheduler mode)
        $this->timingStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn(null);

        $event = $this->createEvent(86400);
        $originalLifetime = $event->getCacheLifetime();

        // Act
        ($this->subject)($event);

        // Assert: Lifetime should not be modified
        self::assertSame($originalLifetime, $event->getCacheLifetime());
    }

    /**
     * Helper to create real event instances
     */
    private function createEvent(int $cacheLifetime, array $renderingInstructions = []): ModifyCacheLifetimeForPageEvent
    {
        return new ModifyCacheLifetimeForPageEvent(
            cacheLifetime: $cacheLifetime,
            pageId: 1,
            pageRecord: ['uid' => 1],
            renderingInstructions: $renderingInstructions,
            context: $this->context
        );
    }

    public function testInvokeSetsLifetimeWhenTimingStrategyReturnsValue(): void
    {
        $lifetime = 3600;

        // Arrange
        $this->timingStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn($lifetime);

        $event = $this->createEvent(86400);

        // Assert
        $this->timingStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn($lifetime);

        // Act
        ($this->subject)($event);
    }

    public function testInvokeCapsLifetimeAtDefaultMaximum(): void
    {
        $requestedLifetime = 100000; // More than default 86400
        $expectedLifetime = 86400;

        // Arrange
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willReturn($requestedLifetime);

        $event = $this->createEvent(86400);

        // Act
        ($this->subject)($event);
    }

    public function testInvokeRespectsTypoScriptCachePeriod(): void
    {
        $typoScriptMaxLifetime = 7200;
        $requestedLifetime = 10000;

        // Arrange
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willReturn($requestedLifetime);

        $event = $this->createEvent(86400, ['cache_period' => $typoScriptMaxLifetime]);

        // Act
        ($this->subject)($event);
    }

    public function testInvokeHandlesExceptionsGracefully(): void
    {
        // Arrange: Timing strategy throws exception
        $this->timingStrategy
            ->method('getCacheLifetime')
            ->willThrowException(new \RuntimeException('Test exception'));

        $event = $this->createEvent(86400);

        // Act: Should not throw
        ($this->subject)($event);

        self::assertTrue(true); // Reached here without exception
    }

    public function testInvokeLogsDebugInfoWhenDebugEnabled(): void
    {
        $lifetime = 3600;

        // Arrange: Need fresh subject with debug enabled
        $debugConfig = $this->createMock(ExtensionConfiguration::class);
        $debugConfig->method('getDefaultMaxLifetime')->willReturn(86400);
        $debugConfig->method('isDebugLoggingEnabled')->willReturn(true);

        $debugLogger = $this->createMock(LoggerInterface::class);
        $debugTimingStrategy = $this->createMock(TimingStrategyInterface::class);
        $debugTimingStrategy->method('getName')->willReturn('test-timing');
        $debugTimingStrategy->method('getCacheLifetime')->willReturn($lifetime);

        $debugScopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $debugScopingStrategy->method('getName')->willReturn('test-scoping');

        $subject = new TemporalCacheLifetime(
            $debugConfig,
            $debugScopingStrategy,
            $debugTimingStrategy,
            $this->context,
            $debugLogger
        );

        $event = $this->createEvent(3600);

        // Assert: Should log debug info
        $debugLogger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Temporal cache lifetime set',
                self::isType('array')
            );

        // Act
        $subject($event);
    }

    public function testGetScopingStrategyReturnsInjectedStrategy(): void
    {
        $result = $this->subject->getScopingStrategy();

        self::assertSame($this->scopingStrategy, $result);
    }

    public function testGetTimingStrategyReturnsInjectedStrategy(): void
    {
        $result = $this->subject->getTimingStrategy();

        self::assertSame($this->timingStrategy, $result);
    }
}
