<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class TemporalCacheStatisticsServiceTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $contentRepository;
    private ExtensionConfiguration&Stub $extensionConfiguration;
    private HarmonizationService&Stub $harmonizationService;
    private TemporalCacheStatisticsService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentRepository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $this->harmonizationService = $this->createStub(HarmonizationService::class);

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

        $this->stubContentRepository();
        $this->stubHarmonizationEnabled(false);

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

        $page = $this->createContent(
            uid: 1,
            tableName: 'pages',
            title: 'Page',
            starttime: $currentTime - 3600,
        );

        $content = $this->createContent(
            uid: 2,
            tableName: 'tt_content',
            title: 'Content',
            pid: 1,
            starttime: $currentTime - 3600,
        );

        $this->stubContentRepository(content: [$page, $content]);
        $this->stubHarmonizationEnabled(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(2, $result['totalCount']);
        self::assertSame(1, $result['pageCount']);
        self::assertSame(1, $result['contentCount']);
    }

    /**     */
    public function testCalculateStatisticsCountsActiveAndFutureContentCorrectly(): void
    {
        $currentTime = \time();

        $activeContent = $this->createContent(
            uid: 1,
            title: 'Active',
            starttime: $currentTime - 3600,
        );

        $futureContent = $this->createContent(
            uid: 2,
            title: 'Future',
            starttime: $currentTime + 3600,
        );

        $this->stubContentRepository(content: [$activeContent, $futureContent]);
        $this->stubHarmonizationEnabled(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(1, $result['activeCount']);
        self::assertSame(1, $result['futureCount']);
    }

    /**     */
    public function testCalculateStatisticsCountsTransitionsCorrectly(): void
    {
        $currentTime = \time();

        $content = $this->createContent(starttime: $currentTime + 3600);

        $transitions = [
            new TransitionEvent($content, $currentTime + 3600, 'start'),
            new TransitionEvent($content, $currentTime + 7200, 'start'),
        ];

        $this->stubContentRepository(
            content: [$content],
            transitions: $transitions,
            transitionsPerDay: [
                '2025-01-01' => 2,
                '2025-01-02' => 1,
            ],
        );
        $this->stubHarmonizationEnabled(false);

        $result = $this->subject->calculateStatistics($currentTime);

        self::assertSame(2, $result['transitionsNext30Days']);
        self::assertSame(2, $result['transitionsPerDay']);
    }

    /**     */
    public function testCalculateStatisticsCountsHarmonizableCandidatesWhenEnabled(): void
    {
        $currentTime = \time();

        $content = $this->createContent(starttime: $currentTime);

        $this->stubContentRepository(content: [$content]);
        $this->stubHarmonizationEnabled(true);

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

        $content = $this->createContent(starttime: $currentTime);

        $this->stubContentRepository(content: [$content]);
        $this->stubHarmonizationEnabled(false);

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

        $content = $this->createContent(starttime: $currentTime + 3600);

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

        $contentRepository = $this->createMock(TemporalContentRepositoryInterface::class);
        $contentRepository
            ->expects(self::once())
            ->method('findTransitionsInRange')
            ->with(
                $currentTime,
                $currentTime + (86400 * $daysAhead),
                0,
                0
            )
            ->willReturn([]);

        $subject = new TemporalCacheStatisticsService(
            $contentRepository,
            $this->extensionConfiguration,
            $this->harmonizationService
        );

        $subject->buildTimeline($currentTime, $daysAhead);
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

    private function createContent(
        int $uid = 1,
        string $tableName = 'pages',
        string $title = 'Test',
        int $pid = 0,
        ?int $starttime = null,
        ?int $endtime = null,
    ): TemporalContent {
        return new TemporalContent(
            uid: $uid,
            tableName: $tableName,
            title: $title,
            pid: $pid,
            starttime: $starttime,
            endtime: $endtime,
            languageUid: 0,
            workspaceUid: 0
        );
    }

    /**
     * @param list<TemporalContent>   $content
     * @param list<TransitionEvent>   $transitions
     * @param array<string, int>      $transitionsPerDay
     */
    private function stubContentRepository(
        array $content = [],
        array $transitions = [],
        array $transitionsPerDay = [],
    ): void {
        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->contentRepository
            ->method('countTransitionsPerDay')
            ->willReturn($transitionsPerDay);
    }

    private function stubHarmonizationEnabled(bool $enabled): void
    {
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn($enabled);
    }
}
