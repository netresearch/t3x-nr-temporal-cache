<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class TemporalCacheStatisticsServiceTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&MockObject $contentRepository;
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private HarmonizationService&MockObject $harmonizationService;
    private TemporalCacheStatisticsService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentRepository = $this->createMock(TemporalContentRepositoryInterface::class);
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->harmonizationService = $this->createMock(HarmonizationService::class);

        $this->subject = new TemporalCacheStatisticsService(
            $this->contentRepository,
            $this->extensionConfiguration,
            $this->harmonizationService
        );
    }

    /**     */
    public function testCalculateStatisticsWithNoContentReturnsZeroStatistics(): void
    {
        $currentTime = \time();

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(0, $result['totalCount']);
        self::assertSame(0, $result['pageCount']);
        self::assertSame(0, $result['contentCount']);
        self::assertSame(0, $result['activeCount']);
        self::assertSame(0, $result['futureCount']);
        self::assertSame(0, $result['transitionsNext30Days']);
        self::assertSame(0, $result['transitionsPerDay']);
        self::assertSame(0, $result['harmonizableCandidates']);
    }

    /**     */
    public function testCalculateStatisticsCountsPageAndContentCorrectly(): void
    {
        $currentTime = \time();

        $page = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Page',
            pid: 0,
            starttime: $currentTime - 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $content = new TemporalContent(
            uid: 2,
            tableName: 'tt_content',
            title: 'Content',
            pid: 1,
            starttime: $currentTime - 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$page, $content]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(2, $result['totalCount']);
        self::assertSame(1, $result['pageCount']);
        self::assertSame(1, $result['contentCount']);
    }

    /**     */
    public function testCalculateStatisticsCountsActiveAndFutureContentCorrectly(): void
    {
        $currentTime = \time();

        $activeContent = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Active',
            pid: 0,
            starttime: $currentTime - 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $futureContent = new TemporalContent(
            uid: 2,
            tableName: 'pages',
            title: 'Future',
            pid: 0,
            starttime: $currentTime + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$activeContent, $futureContent]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(1, $result['activeCount']);
        self::assertSame(1, $result['futureCount']);
    }

    /**     */
    public function testCalculateStatisticsCountsTransitionsCorrectly(): void
    {
        $currentTime = \time();

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $transitions = [
            new TransitionEvent($content, $currentTime + 3600, 'start'),
            new TransitionEvent($content, $currentTime + 7200, 'start'),
        ];

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([
                '2025-01-01' => 2,
                '2025-01-02' => 1,
            ]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(2, $result['transitionsNext30Days']);
        self::assertSame(2, $result['transitionsPerDay']);
    }

    /**     */
    public function testCalculateStatisticsCountsHarmonizableCandidatesWhenEnabled(): void
    {
        $currentTime = \time();

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        // Mock harmonization service to return different timestamp
        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($currentTime + 600);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(1, $result['harmonizableCandidates']);
    }

    /**     */
    public function testCalculateStatisticsDoesNotCountHarmonizableCandidatesWhenDisabled(): void
    {
        $currentTime = \time();

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(0, $result['harmonizableCandidates']);
    }

    /**     */
    public function testBuildTimelineReturnsEmptyArrayWhenNoTransitions(): void
    {
        $currentTime = \time();

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $result = $this->subject->buildTimeline($currentTime);

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    /**     */
    public function testBuildTimelineGroupsTransitionsByDay(): void
    {
        $currentTime = \time();

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $day1Time = $currentTime + 3600;
        $day1Time2 = $currentTime + 7200;
        $day2Time = $currentTime + 86400 + 3600;

        $transitions = [
            new TransitionEvent($content, $day1Time, 'start'),
            new TransitionEvent($content, $day1Time2, 'start'),
            new TransitionEvent($content, $day2Time, 'start'),
        ];

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $result = $this->subject->buildTimeline($currentTime);

        self::assertCount(2, $result); // 2 different days
        self::assertCount(2, $result[0]['transitions']); // Day 1 has 2 transitions
        self::assertCount(1, $result[1]['transitions']); // Day 2 has 1 transition
    }

    /**     */
    public function testBuildTimelineRespectsCustomDaysAhead(): void
    {
        $currentTime = \time();
        $daysAhead = 14;

        $this->contentRepository
            ->expects(self::once())
            ->method('findTransitionsInRange')
            ->with(
                $currentTime,
                $currentTime + (86400 * $daysAhead),
                0,
                0
            )
            ->willReturn([]);

        $this->subject->buildTimeline($currentTime, $daysAhead);
    }

    /**     */
    public function testGetConfigurationSummaryReturnsAllConfigurationFields(): void
    {
        $this->extensionConfiguration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->extensionConfiguration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->extensionConfiguration
            ->method('useRefindex')
            ->willReturn(false);

        $this->extensionConfiguration
            ->method('isDebugLoggingEnabled')
            ->willReturn(true);

        $result = $this->subject->getConfigurationSummary();

        self::assertSame('per-page', $result['scopingStrategy']);
        self::assertSame('dynamic', $result['timingStrategy']);
        self::assertTrue($result['harmonizationEnabled']);
        self::assertFalse($result['useRefindex']);
        self::assertTrue($result['debugLogging']);
    }

    /**     */
    public function testCalculateAverageTransitionsPerDayReturnsZeroWhenNoTransitions(): void
    {
        $startTime = \time();
        $endTime = $startTime + 86400 * 30;

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $result = $this->subject->calculateAverageTransitionsPerDay($startTime, $endTime);

        self::assertSame(0.0, $result);
    }

    /**     */
    public function testCalculateAverageTransitionsPerDayReturnsCorrectAverage(): void
    {
        $startTime = \time();
        $endTime = $startTime + 86400 * 30;

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([
                '2025-01-01' => 10,
                '2025-01-02' => 20,
                '2025-01-03' => 30,
            ]);

        $result = $this->subject->calculateAverageTransitionsPerDay($startTime, $endTime);

        self::assertSame(20.0, $result); // (10 + 20 + 30) / 3 = 20.0
    }

    /**     */
    public function testGetPeakTransitionDayReturnsNullWhenNoTransitions(): void
    {
        $startTime = \time();
        $endTime = $startTime + 86400 * 30;

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([]);

        $result = $this->subject->getPeakTransitionDay($startTime, $endTime);

        self::assertNull($result);
    }

    /**     */
    public function testGetPeakTransitionDayReturnsHighestDay(): void
    {
        $startTime = \time();
        $endTime = $startTime + 86400 * 30;

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn([
                '2025-01-01' => 10,
                '2025-01-02' => 50, // Peak day
                '2025-01-03' => 30,
            ]);

        $result = $this->subject->getPeakTransitionDay($startTime, $endTime);

        self::assertIsArray($result);
        self::assertSame('2025-01-02', $result['date']);
        self::assertSame(50, $result['count']);
    }
}
