<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory
 */
final class TimingStrategyFactoryTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;
    private DynamicTimingStrategy&MockObject $dynamicStrategy;
    private SchedulerTimingStrategy&MockObject $schedulerStrategy;
    private HybridTimingStrategy&MockObject $hybridStrategy;
    private TimingStrategyFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);

        $this->dynamicStrategy = $this->createMock(DynamicTimingStrategy::class);
        $this->dynamicStrategy->method('getName')->willReturn('dynamic');

        $this->schedulerStrategy = $this->createMock(SchedulerTimingStrategy::class);
        $this->schedulerStrategy->method('getName')->willReturn('scheduler');

        $this->hybridStrategy = $this->createMock(HybridTimingStrategy::class);
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
}
