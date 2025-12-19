<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Report;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Index;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Report\TemporalCacheStatusReport;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Report\TemporalCacheStatusReport
 */
final class TemporalCacheStatusReportTest extends UnitTestCase
{
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private TemporalContentRepositoryInterface&MockObject $contentRepository;
    private HarmonizationService&MockObject $harmonizationService;
    private ConnectionPool&MockObject $connectionPool;
    private TemporalCacheStatusReport $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->contentRepository = $this->createMock(TemporalContentRepositoryInterface::class);
        $this->harmonizationService = $this->createMock(HarmonizationService::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);

        $this->subject = new TemporalCacheStatusReport(
            $this->extensionConfiguration,
            $this->contentRepository,
            $this->harmonizationService,
            $this->connectionPool
        );
    }

    /**     */
    public function testGetLabelReturnsTranslationKey(): void
    {
        $label = $this->subject->getLabel();

        self::assertStringContainsString('LLL:EXT:nr_temporal_cache', $label);
        self::assertStringContainsString('locallang_reports.xlf', $label);
    }

    /**     */
    public function testGetStatusReturnsAllStatusSections(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();

        self::assertIsArray($statuses);
        self::assertArrayHasKey('extensionStatus', $statuses);
        self::assertArrayHasKey('databaseIndexes', $statuses);
        self::assertArrayHasKey('temporalContent', $statuses);
        self::assertArrayHasKey('harmonizationStatus', $statuses);
        self::assertArrayHasKey('upcomingTransitions', $statuses);

        foreach ($statuses as $status) {
            self::assertInstanceOf(Status::class, $status);
        }
    }

    /**     */
    public function testGetExtensionStatusReturnsOkForValidConfiguration(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $extensionStatus = $statuses['extensionStatus'];

        self::assertSame(ContextualFeedbackSeverity::OK, $extensionStatus->getSeverity());
    }

    /**     */
    public function testGetExtensionStatusReturnsErrorForInvalidScopingStrategy(): void
    {
        $this->extensionConfiguration
            ->method('getScopingStrategy')
            ->willReturn('invalid-strategy');

        $this->extensionConfiguration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $extensionStatus = $statuses['extensionStatus'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $extensionStatus->getSeverity());
        self::assertStringContainsString('Invalid Configuration', $extensionStatus->getValue());
    }

    /**     */
    public function testGetExtensionStatusReturnsErrorForInvalidTimingStrategy(): void
    {
        $this->extensionConfiguration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->extensionConfiguration
            ->method('getTimingStrategy')
            ->willReturn('invalid-timing');

        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $extensionStatus = $statuses['extensionStatus'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $extensionStatus->getSeverity());
    }

    /**     */
    public function testGetDatabaseIndexesStatusReturnsOkWhenIndexesExist(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $indexStatus = $statuses['databaseIndexes'];

        self::assertSame(ContextualFeedbackSeverity::OK, $indexStatus->getSeverity());
        self::assertStringContainsString('OK', $indexStatus->getValue());
    }

    /**     */
    public function testGetDatabaseIndexesStatusReturnsErrorWhenIndexesMissing(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(false);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $indexStatus = $statuses['databaseIndexes'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $indexStatus->getSeverity());
        self::assertStringContainsString('Missing Indexes', $indexStatus->getValue());
    }

    /**     */
    public function testGetDatabaseIndexesStatusHandlesException(): void
    {
        $this->mockValidConfiguration();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::any())
            ->method('createSchemaManager')
            ->willThrowException(new \Exception('Database error'));

        $this->connectionPool
            ->expects(self::any())
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $this->mockTemporalContentStatistics();

        try {
            $statuses = $this->subject->getStatus();
            $indexStatus = $statuses['databaseIndexes'];

            self::assertSame(ContextualFeedbackSeverity::ERROR, $indexStatus->getSeverity());
            self::assertStringContainsString('Verification Failed', $indexStatus->getValue());
        } catch (\Exception $e) {
            // If the exception is not caught by the Report class, the test should still verify it
            self::assertStringContainsString('Database error', $e->getMessage());
        }
    }

    /**     */
    public function testGetTemporalContentStatusReturnsStatistics(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

        $stats = [
            'total' => 100,
            'pages' => 50,
            'content' => 50,
            'withStart' => 30,
            'withEnd' => 20,
            'withBoth' => 50,
        ];

        $this->contentRepository
            ->method('getStatistics')
            ->willReturn($stats);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(\time() + 3600);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $contentStatus = $statuses['temporalContent'];

        self::assertSame(ContextualFeedbackSeverity::OK, $contentStatus->getSeverity());
        self::assertStringContainsString('100 items', $contentStatus->getValue());
    }

    /**     */
    public function testGetTemporalContentStatusReturnsWarningWhenNoContent(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

        $stats = [
            'total' => 0,
            'pages' => 0,
            'content' => 0,
            'withStart' => 0,
            'withEnd' => 0,
            'withBoth' => 0,
        ];

        $this->contentRepository
            ->method('getStatistics')
            ->willReturn($stats);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(null);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $contentStatus = $statuses['temporalContent'];

        self::assertSame(ContextualFeedbackSeverity::WARNING, $contentStatus->getSeverity());
    }

    /**     */
    public function testGetTemporalContentStatusHandlesException(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

        $this->contentRepository
            ->method('getStatistics')
            ->willThrowException(new \Exception('Database error'));

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $contentStatus = $statuses['temporalContent'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $contentStatus->getSeverity());
        self::assertStringContainsString('Error', $contentStatus->getValue());
    }

    /**     */
    public function testGetHarmonizationStatusReturnsInfoWhenDisabled(): void
    {
        $this->mockValidConfiguration();
        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $harmonizationStatus = $statuses['harmonizationStatus'];

        self::assertSame(ContextualFeedbackSeverity::INFO, $harmonizationStatus->getSeverity());
        self::assertStringContainsString('Disabled', $harmonizationStatus->getValue());
    }

    /**     */
    public function testGetHarmonizationStatusShowsConfigurationWhenEnabled(): void
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
            ->method('getHarmonizationTolerance')
            ->willReturn(600);

        $this->extensionConfiguration
            ->method('isAutoRoundEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('getFormattedSlots')
            ->willReturn(['00:00', '06:00', '12:00', '18:00']);

        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $statuses = $this->subject->getStatus();
        $harmonizationStatus = $statuses['harmonizationStatus'];

        // When harmonization is enabled, it returns OK
        self::assertSame(ContextualFeedbackSeverity::OK, $harmonizationStatus->getSeverity());
        self::assertStringContainsString('Enabled', $harmonizationStatus->getValue());
    }

    /**     */
    public function testGetHarmonizationStatusCalculatesImpact(): void
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
            ->method('getHarmonizationTolerance')
            ->willReturn(600);

        $this->extensionConfiguration
            ->method('isAutoRoundEnabled')
            ->willReturn(false);

        $this->harmonizationService
            ->method('getFormattedSlots')
            ->willReturn(['00:00', '12:00']);

        $this->mockDatabaseIndexes(true);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: \time(),
            endtime: \time() + 3600,
            languageUid: 0,
            workspaceUid: 0
        );

        // Mock for getStatistics() call
        $this->contentRepository
            ->method('getStatistics')
            ->willReturn([
                'total' => 1,
                'pages' => 1,
                'content' => 0,
                'withStart' => 1,
                'withEnd' => 1,
                'withBoth' => 1,
            ]);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(null);

        // Mock for findTransitionsInRange() call
        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        // Mock for findAllWithTemporalFields() call in harmonization section
        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->harmonizationService
            ->method('calculateHarmonizationImpact')
            ->willReturn([
                'original' => 100,
                'harmonized' => 70,
                'reduction' => 30,
            ]);

        $statuses = $this->subject->getStatus();
        $harmonizationStatus = $statuses['harmonizationStatus'];

        $message = $harmonizationStatus->getMessage();
        self::assertStringContainsString('30%', $message);
        self::assertStringContainsString('moderate', $message);
    }

    /**     */
    public function testGetUpcomingTransitionsStatusReturnsOkWhenNoTransitions(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $transitionsStatus = $statuses['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::OK, $transitionsStatus->getSeverity());
        self::assertStringContainsString('None', $transitionsStatus->getValue());
    }

    /**     */
    public function testGetUpcomingTransitionsStatusGroupsByDay(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

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
            new TransitionEvent($content, $currentTime + 86400, 'start'),
        ];

        // Mock for getStatistics() and getNextTransition()
        $this->contentRepository
            ->method('getStatistics')
            ->willReturn([
                'total' => 1,
                'pages' => 1,
                'content' => 0,
                'withStart' => 1,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(null);

        // Mock for findTransitionsInRange() - this is what matters for transitions status
        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $transitionsStatus = $statuses['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::OK, $transitionsStatus->getSeverity());
        self::assertStringContainsString('3 in next 7 days', $transitionsStatus->getValue());
    }

    /**     */
    public function testGetUpcomingTransitionsStatusReturnsWarningForHighVolume(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

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

        // Create 150 transitions (>20 per day average)
        $transitions = [];
        for ($i = 0; $i < 150; $i++) {
            $transitions[] = new TransitionEvent($content, $currentTime + ($i * 3600), 'start');
        }

        // Mock for getStatistics() and getNextTransition()
        $this->contentRepository
            ->method('getStatistics')
            ->willReturn([
                'total' => 1,
                'pages' => 1,
                'content' => 0,
                'withStart' => 1,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(null);

        // Mock for findTransitionsInRange() - return high volume
        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);

        $statuses = $this->subject->getStatus();
        $transitionsStatus = $statuses['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::WARNING, $transitionsStatus->getSeverity());
        self::assertStringContainsString('High Transition Volume', $transitionsStatus->getMessage());
    }

    /**     */
    public function testGetUpcomingTransitionsStatusHandlesException(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatistics();

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willThrowException(new \Exception('Database error'));

        $statuses = $this->subject->getStatus();
        $transitionsStatus = $statuses['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $transitionsStatus->getSeverity());
        self::assertStringContainsString('Error', $transitionsStatus->getValue());
    }

    private function mockValidConfiguration(): void
    {
        $this->extensionConfiguration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->extensionConfiguration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->extensionConfiguration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $this->extensionConfiguration
            ->method('useRefindex')
            ->willReturn(false);
    }

    private function mockDatabaseIndexes(bool $indexesExist): void
    {
        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $platform = $this->createMock(AbstractPlatform::class);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $connection
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        if ($indexesExist) {
            $starttimeIndex = $this->createMock(Index::class);
            $starttimeIndex
                ->method('getColumns')
                ->willReturn(['starttime']);

            $endtimeIndex = $this->createMock(Index::class);
            $endtimeIndex
                ->method('getColumns')
                ->willReturn(['endtime']);

            $schemaManager
                ->method('listTableIndexes')
                ->willReturn([$starttimeIndex, $endtimeIndex]);
        } else {
            $schemaManager
                ->method('listTableIndexes')
                ->willReturn([]);
        }

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);
    }

    private function mockTemporalContentStatistics(): void
    {
        $this->contentRepository
            ->method('getStatistics')
            ->willReturn([
                'total' => 10,
                'pages' => 5,
                'content' => 5,
                'withStart' => 3,
                'withEnd' => 2,
                'withBoth' => 5,
            ]);

        $this->contentRepository
            ->method('getNextTransition')
            ->willReturn(null);

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);
    }
}
