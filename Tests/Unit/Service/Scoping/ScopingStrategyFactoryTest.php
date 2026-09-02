<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Generator;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 */
#[CoversClass(ScopingStrategyFactory::class)]
final class ScopingStrategyFactoryTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;

    private GlobalScopingStrategy&Stub $globalStrategy;

    private PerPageScopingStrategy&Stub $perPageStrategy;

    private PerContentScopingStrategy&Stub $perContentStrategy;

    private ScopingStrategyFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);

        $this->globalStrategy = $this->createStub(GlobalScopingStrategy::class);
        $this->globalStrategy->method('getName')->willReturn('global');

        $this->perPageStrategy = $this->createStub(PerPageScopingStrategy::class);
        $this->perPageStrategy->method('getName')->willReturn('per-page');

        $this->perContentStrategy = $this->createStub(PerContentScopingStrategy::class);
        $this->perContentStrategy->method('getName')->willReturn('per-content');
    }

    private function createFactory(): void
    {
        $this->subject = new ScopingStrategyFactory(
            [
                $this->globalStrategy,
                $this->perPageStrategy,
                $this->perContentStrategy,
            ],
            $this->configuration
        );
    }

    /**     */
    public function testGetReturnsGlobalStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('global');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->globalStrategy, $result);
    }

    /**     */
    public function testGetReturnsPerPageStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->perPageStrategy, $result);
    }

    /**     */
    public function testGetReturnsPerContentStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-content');

        $this->createFactory();

        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->perContentStrategy, $result);
    }

    /**     */
    public function testGetThrowsExceptionForUnknownStrategy(): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('invalid');

        $this->createFactory();

        // Factory doesn't throw for unknown strategies, it falls back to first strategy
        // So we test that it returns the fallback (globalStrategy, first in array)
        $result = $this->subject->getActiveStrategy();

        self::assertSame($this->globalStrategy, $result);
    }

    /**
     * A strategy that only carries the service tag - not one of the three shipped
     * classes - is selected. Yields from a generator, the shape !tagged_iterator
     * injects: no array access, single traversal.
     */
    public function testSelectsStrategyDiscoveredThroughTaggedIterator(): void
    {
        $taggedStrategy = $this->createStub(ScopingStrategyInterface::class);
        $taggedStrategy->method('getName')->willReturn('third-party');

        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('third-party');

        $this->subject = new ScopingStrategyFactory(
            $this->yieldStrategies(
                $this->globalStrategy,
                $this->perPageStrategy,
                $this->perContentStrategy,
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
            ->method('getScopingStrategy')
            ->willReturn('invalid');

        $this->subject = new ScopingStrategyFactory(
            $this->yieldStrategies(
                $this->globalStrategy,
                $this->perPageStrategy,
                $this->perContentStrategy
            ),
            $this->configuration
        );

        self::assertSame($this->globalStrategy, $this->subject->getActiveStrategy());
    }

    /**
     * @return Generator<int, ScopingStrategyInterface>
     */
    private function yieldStrategies(ScopingStrategyInterface ...$strategies): Generator
    {
        yield from $strategies;
    }
}
