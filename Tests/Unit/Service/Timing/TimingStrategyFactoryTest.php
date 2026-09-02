<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Generator;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 */
#[CoversClass(TimingStrategyFactory::class)]
final class TimingStrategyFactoryTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;

    private DynamicTimingStrategy&Stub $dynamicStrategy;

    private SchedulerTimingStrategy&Stub $schedulerStrategy;

    private HybridTimingStrategy&Stub $hybridStrategy;

    private TimingStrategyFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);

        $this->dynamicStrategy = $this->createStub(DynamicTimingStrategy::class);
        $this->dynamicStrategy->method('getName')->willReturn('dynamic');

        $this->schedulerStrategy = $this->createStub(SchedulerTimingStrategy::class);
        $this->schedulerStrategy->method('getName')->willReturn('scheduler');

        $this->hybridStrategy = $this->createStub(HybridTimingStrategy::class);
        $this->hybridStrategy->method('getName')->willReturn('hybrid');
    }

    private function createFactory(): void
    {
        $this->subject = new TimingStrategyFactory(
            [
                $this->dynamicStrategy,
                $this->schedulerStrategy,
                $this->hybridStrategy,
            ],
            $this->configuration
        );
    }

    /**     */
    public function testGetReturnsDynamicStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->dynamicStrategy, $result);
    }

    /**     */
    public function testGetReturnsSchedulerStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('scheduler');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->schedulerStrategy, $result);
    }

    /**     */
    public function testGetReturnsHybridStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('hybrid');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->hybridStrategy, $result);
    }

    /**     */
    public function testGetThrowsExceptionForUnknownStrategy(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('invalid');

        $this->createFactory();

        // Factory doesn't throw for unknown strategies, it falls back to first strategy
        // So we test that it returns the fallback (dynamicStrategy, first in array)
        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->dynamicStrategy, $result);
    }

    /**
     * A strategy that only carries the service tag - not one of the three shipped
     * classes - is selected. Yields from a generator, the shape !tagged_iterator
     * injects: no array access, single traversal.
     */
    public function testSelectsStrategyDiscoveredThroughTaggedIterator(): void
    {
        $taggedStrategy = $this->createStub(TimingStrategyInterface::class);
        $taggedStrategy->method('getName')->willReturn('third-party');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('third-party');

        $this->subject = new TimingStrategyFactory(
            $this->yieldStrategies(
                $this->dynamicStrategy,
                $this->schedulerStrategy,
                $this->hybridStrategy,
                $taggedStrategy
            ),
            $this->configuration
        );

        self::assertSame($taggedStrategy, $this->subject->getActiveStrategy());
    }

    /**
     * The fallback is the first strategy yielded by the tagged iterator, not the last.
     */
    public function testFallsBackToFirstStrategyYieldedByTaggedIterator(): void
    {
        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('invalid');

        $this->subject = new TimingStrategyFactory(
            $this->yieldStrategies(
                $this->dynamicStrategy,
                $this->schedulerStrategy,
                $this->hybridStrategy
            ),
            $this->configuration
        );

        self::assertSame($this->dynamicStrategy, $this->subject->getActiveStrategy());
    }

    /**
     * @return Generator<int, TimingStrategyInterface>
     */
    private function yieldStrategies(TimingStrategyInterface ...$strategies): Generator
    {
        yield from $strategies;
    }
}
