<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class DynamicTimingStrategyTest extends UnitTestCase
{
    private ScopingStrategyInterface&Stub $scopingStrategy;
    private ExtensionConfiguration&Stub $configuration;
    private Context&Stub $context;
    private DynamicTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopingStrategy = $this->createStub(ScopingStrategyInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->context = $this->createStub(Context::class);

        $this->subject = new DynamicTimingStrategy(
            $this->scopingStrategy,
            $this->configuration
        );
    }

    /**     */
    public function testHandlesContentTypeReturnsAlwaysTrue(): void
    {
        self::assertTrue($this->subject->handlesContentType('page'));
        self::assertTrue($this->subject->handlesContentType('content'));
        self::assertTrue($this->subject->handlesContentType('any'));
    }

    /**     */
    public function testProcessTransitionDoesNothing(): void
    {
        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $event = new TransitionEvent(
            content: $content,
            timestamp: \time(),
            transitionType: 'start'
        );

        // Should not throw exception
        $this->subject->processTransition($event);
        self::assertTrue(true);
    }

    /**     */
    public function testGetCacheLifetimeReturnsLifetimeUntilNextTransition(): void
    {
        $currentTime = \time();
        $nextTransition = $currentTime + 3600;

        $this->configuration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400); // 24 hours max

        $this->scopingStrategy
            ->method('getNextTransition')
            ->willReturn($nextTransition);

        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertSame(3600, $lifetime);
    }

    /**     */
    public function testGetCacheLifetimeReturnsDefaultWhenNoTransitions(): void
    {
        $this->scopingStrategy
            ->method('getNextTransition')
            ->willReturn(null);

        $this->configuration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400);

        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertSame(86400, $lifetime);
    }

    /**     */
    public function testGetCacheLifetimeCapsAtMaximum(): void
    {
        $currentTime = \time();
        $nextTransition = $currentTime + 172800; // 2 days

        $this->scopingStrategy
            ->method('getNextTransition')
            ->willReturn($nextTransition);

        $this->configuration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400); // 1 day max

        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertSame(86400, $lifetime);
    }

    /**     */
    public function testGetCacheLifetimeReturnsMinimumForPastTransitions(): void
    {
        $currentTime = \time();
        $pastTransition = $currentTime - 3600;

        $this->scopingStrategy
            ->method('getNextTransition')
            ->willReturn($pastTransition);

        $lifetime = $this->subject->getCacheLifetime($this->context);

        self::assertSame(60, $lifetime); // Minimum 1 minute
    }

    /**     */
    public function testGetCacheLifetimePassesPageIdToScopingStrategy(): void
    {
        $currentTime = \time();
        $nextTransition = $currentTime + 1800;

        $this->configuration
            ->method('getDefaultMaxLifetime')
            ->willReturn(86400);

        /** @var ScopingStrategyInterface&MockObject $scopingStrategy */
        $scopingStrategy = $this->createMock(ScopingStrategyInterface::class);
        $scopingStrategy
            ->expects(self::once())
            ->method('getNextTransition')
            ->with($this->context, 42)
            ->willReturn($nextTransition);

        $subject = new DynamicTimingStrategy($scopingStrategy, $this->configuration);

        self::assertSame(1800, $subject->getCacheLifetime($this->context, 42));
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('dynamic', $this->subject->getName());
    }
}
