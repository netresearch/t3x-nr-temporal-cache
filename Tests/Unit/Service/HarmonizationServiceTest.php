<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for HarmonizationService
 *
 * @covers \Netresearch\TemporalCache\Service\HarmonizationService
 */
final class HarmonizationServiceTest extends UnitTestCase
{
    private ExtensionConfiguration&Stub $configuration;
    private ConnectionPool&Stub $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->connectionPool = $this->createStub(ConnectionPool::class);
    }

    /**     */
    public function testHarmonizeTimestampReturnsOriginalWhenDisabled(): void
    {
        $timestamp = 1609462800; // 2021-01-01 01:00:00 UTC

        $this->configuration->method('isHarmonizationEnabled')->willReturn(false);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($timestamp, $subject->harmonizeTimestamp($timestamp));
    }

    /**     */
    public function testHarmonizeTimestampReturnsOriginalWhenNoSlotsConfigured(): void
    {
        $timestamp = 1609462800;

        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn([]);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($timestamp, $subject->harmonizeTimestamp($timestamp));
    }

    /**     */
    public function testHarmonizeTimestampRoundsToNearestSlot(): void
    {
        // 2021-01-01 00:30:00 UTC should round to 00:00:00
        $timestamp = 1609461000;
        $expectedTimestamp = 1609459200; // 2021-01-01 00:00:00 UTC

        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expectedTimestamp, $subject->harmonizeTimestamp($timestamp));
    }

    /**     */
    public function testHarmonizeTimestampReturnsOriginalWhenOutsideTolerance(): void
    {
        // 2021-01-01 03:00:00 UTC is 3 hours from 00:00 and 06:00
        $timestamp = 1609470000;

        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600); // 1 hour tolerance

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        // Should return original because 3 hours > 1 hour tolerance
        self::assertSame($timestamp, $subject->harmonizeTimestamp($timestamp));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('harmonizationDataProvider')]
    public function testHarmonizeTimestampWorksForVariousSlots(
        int $inputTimestamp,
        array $slots,
        int $tolerance,
        int $expectedTimestamp
    ): void {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn($slots);
        $this->configuration->method('getHarmonizationTolerance')->willReturn($tolerance);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expectedTimestamp, $subject->harmonizeTimestamp($inputTimestamp));
    }

    public static function harmonizationDataProvider(): array
    {
        $midnight = 1609459200; // 2021-01-01 00:00:00 UTC

        return [
            'round down to midnight' => [
                $midnight + 1800, // 00:30
                ['00:00', '06:00', '12:00', '18:00'],
                3600,
                $midnight,
            ],
            'round up to 06:00' => [
                $midnight + 19800, // 05:30
                ['00:00', '06:00', '12:00', '18:00'],
                3600,
                $midnight + 21600, // 06:00
            ],
            'exact match returns same' => [
                $midnight,
                ['00:00', '06:00', '12:00', '18:00'],
                3600,
                $midnight,
            ],
            'custom slots work' => [
                $midnight + 7200, // 02:00
                ['00:00', '04:00', '08:00'],
                7200,
                $midnight + 14400, // 04:00
            ],
        ];
    }

    /**     */
    public function testGetSlotsInRangeReturnsAllSlotsInRange(): void
    {
        $start = 1609459200; // 2021-01-01 00:00:00
        $end = 1609632000;   // 2021-01-03 00:00:00

        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '12:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $slots = $subject->getSlotsInRange($start, $end);

        // Should have 5 slots: 01-01 00:00, 01-01 12:00, 01-02 00:00, 01-02 12:00, 01-03 00:00
        self::assertCount(5, $slots);
        self::assertContains($start, $slots);
        self::assertContains($start + 43200, $slots); // +12 hours
        self::assertContains($end, $slots);
    }

    /**     */
    public function testGetSlotsInRangeReturnsEmptyWhenNoSlotsConfigured(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn([]);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $slots = $subject->getSlotsInRange(1609459200, 1609632000);

        self::assertEmpty($slots);
    }

    /**     */
    public function testGetNextSlotReturnsNextSlotToday(): void
    {
        $timestamp = 1609462800; // 2021-01-01 01:00:00
        $expected = 1609480800;  // 2021-01-01 06:00:00

        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expected, $subject->getNextSlot($timestamp));
    }

    /**     */
    public function testGetNextSlotReturnsFirstSlotTomorrow(): void
    {
        $timestamp = 1609527600; // 2021-01-01 19:00:00 (after last slot)
        $expected = 1609545600;  // 2021-01-02 00:00:00

        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expected, $subject->getNextSlot($timestamp));
    }

    /**     */
    public function testGetNextSlotReturnsNullWhenNoSlotsConfigured(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn([]);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertNull($subject->getNextSlot(1609459200));
    }

    /**     */
    public function testGetPreviousSlotReturnsPreviousSlotToday(): void
    {
        $timestamp = 1609527600; // 2021-01-01 19:00:00
        $expected = 1609524000;  // 2021-01-01 18:00:00

        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expected, $subject->getPreviousSlot($timestamp));
    }

    /**     */
    public function testGetPreviousSlotReturnsLastSlotYesterday(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00 (first slot)
        $expected = 1609437600;  // 2020-12-31 18:00:00

        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expected, $subject->getPreviousSlot($timestamp));
    }

    /**     */
    public function testGetPreviousSlotReturnsNullWhenNoSlotsConfigured(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn([]);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertNull($subject->getPreviousSlot(1609459200));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('slotBoundaryDataProvider')]
    public function testIsOnSlotBoundaryDetectsSlotBoundaries(int $timestamp, array $slots, bool $expected): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn($slots);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame($expected, $subject->isOnSlotBoundary($timestamp));
    }

    public static function slotBoundaryDataProvider(): array
    {
        $midnight = 1609459200; // 2021-01-01 00:00:00

        return [
            'on boundary' => [$midnight, ['00:00', '06:00'], true],
            'not on boundary' => [$midnight + 3600, ['00:00', '06:00'], false],
            'on 06:00 boundary' => [$midnight + 21600, ['00:00', '06:00', '12:00'], true],
            'no slots configured' => [$midnight, [], false],
        ];
    }

    /**     */
    public function testFormatSlotReturnsHumanReadableTime(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        self::assertSame('00:00', $subject->formatSlot(0));
        self::assertSame('06:00', $subject->formatSlot(21600));
        self::assertSame('12:30', $subject->formatSlot(45000));
        self::assertSame('23:59', $subject->formatSlot(86340));
    }

    /**     */
    public function testGetFormattedSlotsReturnsAllFormattedSlots(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $formatted = $subject->getFormattedSlots();

        self::assertSame(['00:00', '06:00', '12:00', '18:00'], $formatted);
    }

    /**     */
    public function testCalculateHarmonizationImpactReturnsCorrectStatistics(): void
    {
        $midnight = 1609459200;
        $timestamps = [
            $midnight + 600,   // 00:10
            $midnight + 1200,  // 00:20
            $midnight + 1800,  // 00:30
            $midnight + 22200, // 06:10
            $midnight + 22800, // 06:20
        ];

        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $impact = $subject->calculateHarmonizationImpact($timestamps);

        self::assertSame(5, $impact['original']);
        self::assertSame(2, $impact['harmonized']); // Groups to 00:00 and 06:00
        self::assertSame(60.0, $impact['reduction']); // (5-2)/5 * 100
    }

    /**     */
    public function testCalculateHarmonizationImpactHandlesEmptyArray(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00']);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $impact = $subject->calculateHarmonizationImpact([]);

        self::assertSame(0, $impact['original']);
        self::assertSame(0, $impact['harmonized']);
        self::assertSame(0.0, $impact['reduction']);
    }

    /**     */
    public function testInvalidSlotFormatsAreIgnored(): void
    {
        $this->configuration->method('getHarmonizationSlots')->willReturn([
            '00:00',
            'invalid',
            '25:00',
            '12:60',
            '06:00',
        ]);

        $subject = new HarmonizationService($this->configuration, $this->connectionPool);
        $formatted = $subject->getFormattedSlots();

        // Only valid slots should be included
        self::assertSame(['00:00', '06:00'], $formatted);
    }

    private function createContent(?int $starttime, ?int $endtime, string $table = 'tt_content'): TemporalContent
    {
        return new TemporalContent(
            uid: 1,
            tableName: $table,
            title: 'Test',
            pid: 1,
            starttime: $starttime,
            endtime: $endtime,
            languageUid: 0,
            workspaceUid: 0,
            hidden: false,
            deleted: false,
        );
    }

    /**     */
    public function testHarmonizeContentReturnsNoChangesWhenAlreadyHarmonized(): void
    {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $content = $this->createContent(1609459200, null); // already on the 00:00 slot
        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        $result = $subject->harmonizeContent($content, false);

        self::assertTrue($result['success']);
        self::assertSame([], $result['changes']);
        self::assertStringContainsString('No changes needed', $result['message']);
    }

    /**     */
    public function testHarmonizeContentDryRunReturnsChangesWithoutPersisting(): void
    {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $content = $this->createContent(1609461000, null); // 00:30 -> 00:00
        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        $result = $subject->harmonizeContent($content, true);

        self::assertTrue($result['success']);
        self::assertArrayHasKey('starttime', $result['changes']);
        self::assertSame(1609459200, $result['changes']['starttime']['new']);
        self::assertStringContainsString('Dry-run', $result['message']);
    }

    /**     */
    public function testHarmonizeContentPersistsAndReturnsSuccess(): void
    {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $connection = $this->createStub(Connection::class);
        $connection->method('update')->willReturn(1);
        $this->connectionPool->method('getConnectionForTable')->willReturn($connection);

        $content = $this->createContent(1609461000, null);
        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        $result = $subject->harmonizeContent($content, false);

        self::assertTrue($result['success']);
        self::assertStringContainsString('harmonized successfully', $result['message']);
    }

    /**     */
    public function testHarmonizeContentReturnsFailureWhenNoRowsAffected(): void
    {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $connection = $this->createStub(Connection::class);
        $connection->method('update')->willReturn(0);
        $this->connectionPool->method('getConnectionForTable')->willReturn($connection);

        $content = $this->createContent(1609461000, null);
        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        $result = $subject->harmonizeContent($content, false);

        self::assertFalse($result['success']);
        self::assertStringContainsString('could not be updated', $result['message']);
    }

    /**     */
    public function testHarmonizeContentReturnsFailureOnDatabaseException(): void
    {
        $this->configuration->method('isHarmonizationEnabled')->willReturn(true);
        $this->configuration->method('getHarmonizationSlots')->willReturn(['00:00', '06:00', '12:00', '18:00']);
        $this->configuration->method('getHarmonizationTolerance')->willReturn(3600);

        $connection = $this->createStub(Connection::class);
        $connection->method('update')->willThrowException(new \RuntimeException('db down'));
        $this->connectionPool->method('getConnectionForTable')->willReturn($connection);

        $content = $this->createContent(1609461000, null);
        $subject = new HarmonizationService($this->configuration, $this->connectionPool);

        $result = $subject->harmonizeContent($content, false);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Failed to persist', $result['message']);
    }
}
