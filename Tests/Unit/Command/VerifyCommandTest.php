<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\Type;
use Netresearch\TemporalCache\Command\VerifyCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\VerifyCommand
 */
final class VerifyCommandTest extends UnitTestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private ExtensionConfiguration&MockObject $configuration;
    private InputInterface&MockObject $input;
    private OutputInterface&MockObject $output;
    private VerifyCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->configuration = $this->createMock(ExtensionConfiguration::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->subject = new VerifyCommand(
            $this->connectionPool,
            $this->configuration
        );
    }

    /**     */
    public function testCommandHasCorrectName(): void
    {
        self::assertSame('temporalcache:verify', $this->subject->getName());
    }

    /**     */
    public function testExecuteWithAllChecksPassingReturnsSuccess(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        // Mock database connection and schema manager
        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        // Mock indexes exist
        $starttimeIndex = $this->createMock(Index::class);
        $starttimeIndex->method('getColumns')->willReturn(['starttime']);

        $endtimeIndex = $this->createMock(Index::class);
        $endtimeIndex->method('getColumns')->willReturn(['endtime']);

        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([$starttimeIndex, $endtimeIndex]);

        // Mock columns exist
        $starttimeCol = $this->createMock(Column::class);
        $endtimeCol = $this->createMock(Column::class);
        $hiddenCol = $this->createMock(Column::class);
        $deletedCol = $this->createMock(Column::class);
        $languageCol = $this->createMock(Column::class);
        $pidCol = $this->createMock(Column::class);

        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'starttime' => $starttimeCol,
                'endtime' => $endtimeCol,
                'hidden' => $hiddenCol,
                'deleted' => $deletedCol,
                'sys_language_uid' => $languageCol,
                'pid' => $pidCol,
            ]);

        // Mock valid configuration
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithInvalidConfigurationReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        // Mock database checks passing
        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $starttimeIndex = $this->createMock(Index::class);
        $starttimeIndex->method('getColumns')->willReturn(['starttime']);

        $endtimeIndex = $this->createMock(Index::class);
        $endtimeIndex->method('getColumns')->willReturn(['endtime']);

        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([$starttimeIndex, $endtimeIndex]);

        $col = $this->createMock(Column::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'starttime' => $col,
                'endtime' => $col,
                'hidden' => $col,
                'deleted' => $col,
                'sys_language_uid' => $col,
                'pid' => $col,
            ]);

        // Mock invalid configuration
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('invalid-strategy');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithMissingIndexesReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        // No indexes
        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([]);

        $col = $this->createMock(Column::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'starttime' => $col,
                'endtime' => $col,
                'hidden' => $col,
                'deleted' => $col,
                'sys_language_uid' => $col,
                'pid' => $col,
            ]);

        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithHarmonizationEnabledVerifiesHarmonizationConfig(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $starttimeIndex = $this->createMock(Index::class);
        $starttimeIndex->method('getColumns')->willReturn(['starttime']);

        $endtimeIndex = $this->createMock(Index::class);
        $endtimeIndex->method('getColumns')->willReturn(['endtime']);

        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([$starttimeIndex, $endtimeIndex]);

        $col = $this->createMock(Column::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'starttime' => $col,
                'endtime' => $col,
                'hidden' => $col,
                'deleted' => $col,
                'sys_language_uid' => $col,
                'pid' => $col,
            ]);

        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

        $this->configuration
            ->method('isAutoRoundEnabled')
            ->willReturn(true);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithInvalidHarmonizationSlotsReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $starttimeIndex = $this->createMock(Index::class);
        $starttimeIndex->method('getColumns')->willReturn(['starttime']);

        $endtimeIndex = $this->createMock(Index::class);
        $endtimeIndex->method('getColumns')->willReturn(['endtime']);

        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([$starttimeIndex, $endtimeIndex]);

        $col = $this->createMock(Column::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'starttime' => $col,
                'endtime' => $col,
                'hidden' => $col,
                'deleted' => $col,
                'sys_language_uid' => $col,
                'pid' => $col,
            ]);

        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        // Invalid slot format
        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['invalid-slot']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    private function setupInputDefaults(): void
    {
        $this->input
            ->method('bind')
            ->willReturnSelf();

        $this->input
            ->method('isInteractive')
            ->willReturn(false);

        $this->input
            ->method('hasArgument')
            ->willReturn(false);

        $this->input
            ->method('validate')
            ->willReturnSelf();

        $this->input
            ->method('getOption')
            ->willReturn(null);
    }
}
