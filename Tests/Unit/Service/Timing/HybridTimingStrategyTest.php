<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(HybridTimingStrategy::class)]
#[UsesClass(TemporalContent::class)]
#[UsesClass(TransitionEvent::class)]
final class HybridTimingStrategyTest extends UnitTestCase
{
    private TimingStrategyInterface&Stub $dynamicStrategy;

    private TimingStrategyInterface&Stub $schedulerStrategy;

    private ExtensionConfiguration&Stub $configuration;

    private Context&Stub $context;

    private HybridTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dynamicStrategy = $this->createStub(TimingStrategyInterface::class);
        $this->schedulerStrategy = $this->createStub(TimingStrategyInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->context = $this->createStub(Context::class);
    }

    /**
     * @param array{page?: string, content?: string} $timingRules
     */
    private function createSubject(
        array $timingRules,
        ?TimingStrategyInterface $dynamicStrategy = null,
        ?TimingStrategyInterface $schedulerStrategy = null
    ): void {
        $this->configuration
            ->method('getTimingRules')
            ->willReturn($timingRules);

        $this->subject = new HybridTimingStrategy(
            $dynamicStrategy ?? $this->dynamicStrategy,
            $schedulerStrategy ?? $this->schedulerStrategy,
            $this->configuration
        );
    }

    private function createTransitionEvent(string $tableName): TransitionEvent
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: $tableName,
            title: 'Test',
            pid: 5,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        return new TransitionEvent(
            content: $content,
            timestamp: \time(),
            transitionType: 'start'
        );
    }

    #[DataProvider('handledContentTypeDataProvider')]
    public function testHandlesContentTypeAcceptsEveryType(string $contentType): void
    {
        $this->createSubject(['page' => 'dynamic', 'content' => 'scheduler']);

        self::assertTrue($this->subject->handlesContentType($contentType));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function handledContentTypeDataProvider(): array
    {
        return [
            'page' => ['page'],
            'content' => ['content'],
            'unknown type' => ['something-else'],
        ];
    }

    /**
     * The rule keys are the values of TemporalContent::getContentType(), so a key
     * mismatch ('pages' instead of 'page') silently resolves to the default here.
     *
     * @param array{page?: string, content?: string} $timingRules
     */
    #[DataProvider('contentTypeHandlingDataProvider')]
    public function testGetStrategyNameForContentTypeFollowsConfiguredRules(
        string $contentType,
        array $timingRules,
        string $expectedStrategy
    ): void {
        $this->createSubject($timingRules);

        self::assertSame($expectedStrategy, $this->subject->getStrategyNameForContentType($contentType));
    }

    /**
     * @return array<string, array{0: string, 1: array{page?: string, content?: string}, 2: string}>
     */
    public static function contentTypeHandlingDataProvider(): array
    {
        return [
            'page uses dynamic' => [
                'page',
                ['page' => 'dynamic', 'content' => 'scheduler'],
                'dynamic',
            ],
            'content uses scheduler' => [
                'content',
                ['page' => 'dynamic', 'content' => 'scheduler'],
                'scheduler',
            ],
            'page uses scheduler' => [
                'page',
                ['page' => 'scheduler', 'content' => 'dynamic'],
                'scheduler',
            ],
            'content uses dynamic' => [
                'content',
                ['page' => 'scheduler', 'content' => 'dynamic'],
                'dynamic',
            ],
        ];
    }

    public function testGetCacheLifetimeDelegatesToDynamicStrategyForPages(): void
    {
        $dynamicStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->createSubject(['page' => 'dynamic', 'content' => 'scheduler'], $dynamicStrategy);

        $dynamicStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context, 7)
            ->willReturn(3600);

        self::assertSame(3600, $this->subject->getCacheLifetime($this->context, 7));
    }

    /**
     * The content rule must reach the cache lifetime: a page's cache cannot outlive
     * the next transition of the content elements placed on it.
     */
    public function testGetCacheLifetimeConsultsTheContentRule(): void
    {
        $dynamicStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->createSubject(['page' => 'scheduler', 'content' => 'dynamic'], $dynamicStrategy);

        $dynamicStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context, 42)
            ->willReturn(1800);

        self::assertSame(1800, $this->subject->getCacheLifetime($this->context, 42));
    }

    /**
     * Two rules, two lifetimes: the earlier transition wins, because the later one
     * is still ahead when the cache expires and will be picked up on regeneration.
     *
     * Pins the rule at the interface contract - it is what separates "earliest"
     * from "first non-null". The shipped SchedulerTimingStrategy answers null, so
     * only a strategy substituted into that slot reaches this case in production.
     */
    public function testGetCacheLifetimeReturnsTheEarliestOfAllRules(): void
    {
        $this->dynamicStrategy->method('getCacheLifetime')->willReturn(7200);
        $this->schedulerStrategy->method('getCacheLifetime')->willReturn(900);

        $this->createSubject(['page' => 'dynamic', 'content' => 'scheduler']);

        self::assertSame(900, $this->subject->getCacheLifetime($this->context));
    }

    public function testGetCacheLifetimeReturnsNullWhenNoRuleYieldsALifetime(): void
    {
        $this->createSubject(['page' => 'scheduler', 'content' => 'scheduler']);

        self::assertNull($this->subject->getCacheLifetime($this->context));
    }

    /**
     * Both rules resolving to one strategy must not double the transition queries
     * that strategy runs during page generation.
     */
    public function testGetCacheLifetimeQueriesAStrategySharedByBothRulesOnlyOnce(): void
    {
        $dynamicStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->createSubject(['page' => 'dynamic', 'content' => 'dynamic'], $dynamicStrategy);

        $dynamicStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->willReturn(600);

        self::assertSame(600, $this->subject->getCacheLifetime($this->context));
    }

    public function testProcessTransitionDelegatesToSchedulerStrategyForContent(): void
    {
        $event = $this->createTransitionEvent('tt_content');

        $schedulerStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->createSubject(['page' => 'dynamic', 'content' => 'scheduler'], null, $schedulerStrategy);

        $schedulerStrategy
            ->expects(self::once())
            ->method('processTransition')
            ->with($event);

        $this->subject->processTransition($event);
    }

    public function testProcessTransitionDelegatesToTheStrategyConfiguredForPages(): void
    {
        $event = $this->createTransitionEvent('pages');

        $schedulerStrategy = $this->createMock(TimingStrategyInterface::class);

        $this->createSubject(['page' => 'scheduler', 'content' => 'dynamic'], null, $schedulerStrategy);

        $schedulerStrategy
            ->expects(self::once())
            ->method('processTransition')
            ->with($event);

        $this->subject->processTransition($event);
    }

    public function testGetNameReturnsCorrectIdentifier(): void
    {
        $this->createSubject([]);

        self::assertSame('hybrid', $this->subject->getName());
    }

    public function testGetTimingRulesReturnsTheConfiguredRules(): void
    {
        $this->createSubject(['page' => 'scheduler', 'content' => 'dynamic']);

        self::assertSame(['page' => 'scheduler', 'content' => 'dynamic'], $this->subject->getTimingRules());
    }
}
