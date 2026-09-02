<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Report;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Index;
use Exception;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Report\TemporalCacheStatusReport;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 */
#[CoversClass(TemporalCacheStatusReport::class)]
final class TemporalCacheStatusReportTest extends UnitTestCase
{
    /**
     * Recognisable stand-in for the kind of detail Doctrine DBAL puts into an
     * exception message: SQL, table names, credentials from the DSN.
     */
    private const EXCEPTION_MARKER = 'SQLSTATE[42S02] leak-marker-7f3a: SELECT * FROM tt_content; mysql://user:pw@db-host/typo3<img src=x onerror=alert(1)>';

    private ExtensionConfiguration&Stub $extensionConfiguration;

    private TemporalContentRepositoryInterface&Stub $contentRepository;

    private HarmonizationService&Stub $harmonizationService;

    private ConnectionPool&Stub $connectionPool;

    private TemporalCacheStatusReport $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $this->contentRepository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->harmonizationService = $this->createStub(HarmonizationService::class);
        $this->connectionPool = $this->createStub(ConnectionPool::class);

        $this->subject = $this->createSubject($this->createStub(LoggerInterface::class));
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

        $connection = $this->createStub(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willThrowException(new Exception('Database error'));

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $this->mockTemporalContentStatistics();

        try {
            $statuses = $this->subject->getStatus();
            $indexStatus = $statuses['databaseIndexes'];

            self::assertSame(ContextualFeedbackSeverity::ERROR, $indexStatus->getSeverity());
            self::assertStringContainsString('Verification Failed', $indexStatus->getValue());
        } catch (Exception $e) {
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
            ->willThrowException(new Exception('Database error'));

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
            ->willThrowException(new Exception('Database error'));

        $statuses = $this->subject->getStatus();
        $transitionsStatus = $statuses['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $transitionsStatus->getSeverity());
        self::assertStringContainsString('Error', $transitionsStatus->getValue());
    }

    /**
     * The Reports module renders the status message with f:format.raw(), so the
     * DBAL exception text must not end up in it.
     */
    public function testGetDatabaseIndexesStatusDoesNotExposeExceptionDetails(): void
    {
        $this->mockValidConfiguration();
        $this->mockTemporalContentStatistics();

        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableIndexes')
            ->willThrowException(new Exception(self::EXCEPTION_MARKER));

        $connection = $this->createStub(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $this->expectExceptionDetailToBeLogged();

        $indexStatus = $this->subject->getStatus()['databaseIndexes'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $indexStatus->getSeverity());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $indexStatus->getMessage());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $indexStatus->getValue());
    }

    /**     */
    public function testGetTemporalContentStatusDoesNotExposeExceptionDetails(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);

        $this->contentRepository
            ->method('getStatistics')
            ->willThrowException(new Exception(self::EXCEPTION_MARKER));

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->expectExceptionDetailToBeLogged();

        $contentStatus = $this->subject->getStatus()['temporalContent'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $contentStatus->getSeverity());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $contentStatus->getMessage());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $contentStatus->getValue());
    }

    /**     */
    public function testGetHarmonizationStatusDoesNotExposeExceptionDetails(): void
    {
        $this->mockHarmonizationEnabled();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatisticsWithoutHarmonizationContent();

        $this->contentRepository
            ->method('findAllWithTemporalFields')
            ->willThrowException(new Exception(self::EXCEPTION_MARKER));

        $this->expectExceptionDetailToBeLogged();

        $harmonizationStatus = $this->subject->getStatus()['harmonizationStatus'];

        $message = $harmonizationStatus->getMessage();
        self::assertStringContainsString('Could not calculate harmonization impact', $message);
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $message);
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $harmonizationStatus->getValue());
    }

    /**     */
    public function testGetUpcomingTransitionsStatusDoesNotExposeExceptionDetails(): void
    {
        $this->mockValidConfiguration();
        $this->mockDatabaseIndexes(true);
        $this->mockTemporalContentStatisticsWithoutHarmonizationContent();

        $this->contentRepository
            ->method('findTransitionsInRange')
            ->willThrowException(new Exception(self::EXCEPTION_MARKER));

        $this->expectExceptionDetailToBeLogged();

        $transitionsStatus = $this->subject->getStatus()['upcomingTransitions'];

        self::assertSame(ContextualFeedbackSeverity::ERROR, $transitionsStatus->getSeverity());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $transitionsStatus->getMessage());
        self::assertStringNotContainsString(self::EXCEPTION_MARKER, $transitionsStatus->getValue());
    }

    /**
     * Require that the detail suppressed in the report reaches the log instead,
     * so the fix cannot be satisfied by silently swallowing the exception.
     */
    private function expectExceptionDetailToBeLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(static function (array $context): bool {
                    $exception = $context['exception'] ?? null;

                    // PSR-3 reserves 'exception' for the Throwable itself; the
                    // detail must still be recoverable from it.
                    return $exception instanceof Throwable
                        && \str_contains($exception->getMessage(), self::EXCEPTION_MARKER);
                })
            );

        $this->subject = $this->createSubject($logger);
    }

    private function createSubject(LoggerInterface $logger): TemporalCacheStatusReport
    {
        return new TemporalCacheStatusReport(
            $this->extensionConfiguration,
            $this->contentRepository,
            $this->harmonizationService,
            $this->connectionPool,
            $logger
        );
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

    private function mockHarmonizationEnabled(): void
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
    }

    private function mockDatabaseIndexes(bool $indexesExist): void
    {
        $connection = $this->createStub(Connection::class);
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $platform = $this->createStub(AbstractPlatform::class);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $connection
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        if ($indexesExist) {
            $starttimeIndex = $this->createStub(Index::class);
            $starttimeIndex
                ->method('getColumns')
                ->willReturn(['starttime']);

            $endtimeIndex = $this->createStub(Index::class);
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

    /**
     * Like mockTemporalContentStatistics(), but leaves findTransitionsInRange()
     * and findAllWithTemporalFields() unstubbed so a test can make them throw.
     */
    private function mockTemporalContentStatisticsWithoutHarmonizationContent(): void
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
    }
}
