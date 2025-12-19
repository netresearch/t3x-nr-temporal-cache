<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\Backend\HarmonizationAnalysisService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Backend\HarmonizationAnalysisService
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class HarmonizationAnalysisServiceTest extends UnitTestCase
{
    private HarmonizationService&MockObject $harmonizationService;
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private HarmonizationAnalysisService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harmonizationService = $this->createMock(HarmonizationService::class);
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);

        $this->subject = new HarmonizationAnalysisService(
            $this->harmonizationService,
            $this->extensionConfiguration
        );
    }

    /**     */
    public function testIsHarmonizableReturnsFalseWhenHarmonizationDisabled(): void
    {
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->isHarmonizable($content);

        self::assertFalse($result);
    }

    /**     */
    public function testIsHarmonizableReturnsFalseWhenNoTemporalFields(): void
    {
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

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

        $result = $this->subject->isHarmonizable($content);

        self::assertFalse($result);
    }

    /**     */
    public function testIsHarmonizableReturnsTrueWhenStarttimeCanBeHarmonized(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->with($currentTime)
            ->willReturn($currentTime + 600); // Different timestamp

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

        $result = $this->subject->isHarmonizable($content);

        self::assertTrue($result);
    }

    /**     */
    public function testIsHarmonizableReturnsTrueWhenEndtimeCanBeHarmonized(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnCallback(function ($timestamp) use ($currentTime) {
                if ($timestamp === $currentTime) {
                    return $currentTime + 600; // Different for endtime
                }
                return $timestamp;
            });

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: $currentTime,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->isHarmonizable($content);

        self::assertTrue($result);
    }

    /**     */
    public function testIsHarmonizableReturnsFalseWhenAlreadyHarmonized(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnArgument(0); // Same timestamp

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

        $result = $this->subject->isHarmonizable($content);

        self::assertFalse($result);
    }

    /**     */
    public function testGenerateHarmonizationSuggestionIncludesStarttimeWhenHarmonizable(): void
    {
        $currentTime = \time();

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->with($currentTime)
            ->willReturn($currentTime + 600);

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

        $result = $this->subject->generateHarmonizationSuggestion($content, $currentTime);

        self::assertSame($content, $result['content']);
        self::assertTrue($result['hasChanges']);
        self::assertArrayHasKey('starttime', $result['suggestions']);
        self::assertSame($currentTime, $result['suggestions']['starttime']['current']);
        self::assertSame($currentTime + 600, $result['suggestions']['starttime']['suggested']);
        self::assertSame(600, $result['suggestions']['starttime']['diff']);
    }

    /**     */
    public function testGenerateHarmonizationSuggestionIncludesEndtimeWhenHarmonizable(): void
    {
        $currentTime = \time();

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->with($currentTime)
            ->willReturn($currentTime + 600);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: null,
            endtime: $currentTime,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->generateHarmonizationSuggestion($content, $currentTime);

        self::assertTrue($result['hasChanges']);
        self::assertArrayHasKey('endtime', $result['suggestions']);
        self::assertSame($currentTime, $result['suggestions']['endtime']['current']);
        self::assertSame($currentTime + 600, $result['suggestions']['endtime']['suggested']);
    }

    /**     */
    public function testGenerateHarmonizationSuggestionReturnsNoChangesWhenAlreadyHarmonized(): void
    {
        $currentTime = \time();

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnArgument(0);

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

        $result = $this->subject->generateHarmonizationSuggestion($content, $currentTime);

        self::assertFalse($result['hasChanges']);
        self::assertEmpty($result['suggestions']);
    }

    /**     */
    public function testAnalyzeHarmonizableCandidatesReturnsZeroWhenDisabled(): void
    {
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->analyzeHarmonizableCandidates([$content]);

        self::assertSame(0, $result['harmonizableCount']);
        self::assertSame(1, $result['totalCount']);
        self::assertSame(0.0, $result['averageShiftSeconds']);
        self::assertSame(0, $result['starttimeChanges']);
        self::assertSame(0, $result['endtimeChanges']);
        self::assertEmpty($result['harmonizableItems']);
    }

    /**     */
    public function testAnalyzeHarmonizableCandidatesCountsHarmonizableItems(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($currentTime + 600);

        $content1 = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Harmonizable',
            pid: 0,
            starttime: $currentTime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $content2 = new TemporalContent(
            uid: 2,
            tableName: 'pages',
            title: 'Also Harmonizable',
            pid: 0,
            starttime: $currentTime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->analyzeHarmonizableCandidates([$content1, $content2]);

        self::assertSame(2, $result['harmonizableCount']);
        self::assertSame(2, $result['totalCount']);
        self::assertSame(2, $result['starttimeChanges']);
        self::assertSame(0, $result['endtimeChanges']);
        self::assertCount(2, $result['harmonizableItems']);
    }

    /**     */
    public function testAnalyzeHarmonizableCandidatesCalculatesAverageShift(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnCallback(function ($timestamp) {
                return $timestamp + 600; // Always shift by 600 seconds
            });

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime,
            endtime: $currentTime + 1000,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->analyzeHarmonizableCandidates([$content]);

        self::assertEquals(600.0, $result['averageShiftSeconds']); // Use assertEquals for float comparison
        self::assertSame(1, $result['starttimeChanges']);
        self::assertSame(1, $result['endtimeChanges']);
    }

    /**     */
    public function testFilterHarmonizableContentReturnsEmptyWhenDisabled(): void
    {
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->filterHarmonizableContent([$content]);

        self::assertEmpty($result);
    }

    /**     */
    public function testFilterHarmonizableContentFiltersCorrectly(): void
    {
        $currentTime = \time();

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnCallback(function ($timestamp) use ($currentTime) {
                // Only harmonize first content item
                if ($timestamp === $currentTime) {
                    return $currentTime + 600;
                }
                return $timestamp;
            });

        $harmonizable = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Harmonizable',
            pid: 0,
            starttime: $currentTime,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $notHarmonizable = new TemporalContent(
            uid: 2,
            tableName: 'pages',
            title: 'Already Harmonized',
            pid: 0,
            starttime: $currentTime + 1000,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->filterHarmonizableContent([$harmonizable, $notHarmonizable]);

        self::assertCount(1, $result);
        self::assertSame($harmonizable, \array_values($result)[0]);
    }

    /**     */
    public function testCalculateHarmonizationImpactReturnsLowPriorityForSmallShifts(): void
    {
        $currentTime = \time();
        $futureTime = $currentTime + 86400; // 1 day in future

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($futureTime + 300); // Shift by 5 minutes = low priority

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $futureTime, // Future content, not currently visible
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->calculateHarmonizationImpact($content, $currentTime);

        self::assertSame(300, $result['maxShiftSeconds']);
        self::assertFalse($result['affectsVisibility']);
        self::assertSame('low', $result['priority']);
    }

    /**     */
    public function testCalculateHarmonizationImpactReturnsMediumPriorityForModerateShifts(): void
    {
        $currentTime = \time();
        $futureTime = $currentTime + 86400; // 1 day in future

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($futureTime + 1200); // Shift by 20 minutes = medium priority

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $futureTime, // Future content, not currently visible
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->calculateHarmonizationImpact($content, $currentTime);

        self::assertSame(1200, $result['maxShiftSeconds']);
        self::assertSame('medium', $result['priority']);
    }

    /**     */
    public function testCalculateHarmonizationImpactReturnsHighPriorityForLargeShifts(): void
    {
        $currentTime = \time();

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($currentTime + 3700); // > 1 hour = high priority

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

        $result = $this->subject->calculateHarmonizationImpact($content, $currentTime);

        self::assertSame(3700, $result['maxShiftSeconds']);
        self::assertSame('high', $result['priority']);
    }

    /**     */
    public function testCalculateHarmonizationImpactDetectsVisibilityAffection(): void
    {
        $currentTime = \time();

        // Content currently visible (starttime in past)
        // Harmonization will move starttime to future (making it invisible)
        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn($currentTime + 600); // Move to future

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: $currentTime - 100, // Currently visible
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $result = $this->subject->calculateHarmonizationImpact($content, $currentTime);

        self::assertTrue($result['affectsVisibility']);
        self::assertSame('high', $result['priority']); // High because affects visibility
    }
}
