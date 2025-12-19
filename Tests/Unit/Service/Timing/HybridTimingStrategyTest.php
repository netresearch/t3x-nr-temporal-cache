<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class HybridTimingStrategyTest extends UnitTestCase
{
    private TimingStrategyInterface&MockObject $dynamicStrategy;
    private TimingStrategyInterface&MockObject $schedulerStrategy;
    private ExtensionConfiguration&Stub $configuration;
    private Context&MockObject $context;
    private HybridTimingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dynamicStrategy = $this->createMock(TimingStrategyInterface::class);
        $this->schedulerStrategy = $this->createMock(TimingStrategyInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->context = $this->createMock(Context::class);
    }

    private function createSubject(): void
    {
        $this->subject = new HybridTimingStrategy(
            $this->dynamicStrategy,
            $this->schedulerStrategy,
            $this->configuration
        );
    }

    /**     * @dataProvider contentTypeHandlingDataProvider
     */
    public function testHandlesContentTypeDelegatesToCorrectStrategy(
        string $contentType,
        array $timingRules,
        string $expectedStrategy
    ): void {
        $this->configuration
            ->method('getTimingRules')
            ->willReturn($timingRules);

        $this->createSubject();

        $result = $this->subject->handlesContentType($contentType);

        // Strategy always handles some type
        self::assertTrue($result);
    }

    public static function contentTypeHandlingDataProvider(): array
    {
        return [
            'page uses dynamic' => [
                'page',
                ['pages' => 'dynamic', 'content' => 'scheduler'],
                'dynamic',
            ],
            'content uses scheduler' => [
                'content',
                ['pages' => 'dynamic', 'content' => 'scheduler'],
                'scheduler',
            ],
        ];
    }

    /**     */
    public function testGetCacheLifetimeDelegatesToDynamicStrategyForPages(): void
    {
        $this->configuration
            ->method('getTimingRules')
            ->willReturn(['pages' => 'dynamic', 'content' => 'scheduler']);

        $this->createSubject();

        $this->dynamicStrategy
            ->expects(self::once())
            ->method('getCacheLifetime')
            ->with($this->context)
            ->willReturn(3600);

        $result = $this->subject->getCacheLifetime($this->context);

        self::assertSame(3600, $result);
    }

    /**     */
    public function testProcessTransitionDelegatesToSchedulerStrategyForContent(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test',
            pid: 5,
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

        $this->configuration
            ->method('getTimingRules')
            ->willReturn(['pages' => 'dynamic', 'content' => 'scheduler']);

        $this->createSubject();

        $this->schedulerStrategy
            ->expects(self::once())
            ->method('processTransition')
            ->with($event);

        $this->subject->processTransition($event);
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        $this->configuration
            ->method('getTimingRules')
            ->willReturn([]);

        $this->createSubject();

        self::assertSame('hybrid', $this->subject->getName());
    }
}
