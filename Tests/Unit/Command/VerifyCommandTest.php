<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Netresearch\TemporalCache\Command\VerifyCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\MockObject\Stub;
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
    private ConnectionPool&Stub $connectionPool;
    private ExtensionConfiguration&Stub $configuration;
    private InputInterface&Stub $input;
    private OutputInterface&Stub $output;
    private VerifyCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionPool = $this->createStub(ConnectionPool::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

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
        $this->setupOutputDefaults();

        $schemaManager = $this->mockSchemaManager();
        $this->mockTableIndexes($schemaManager);
        $this->mockTableColumns($schemaManager);

        $this->mockBaseConfiguration('per-page');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        self::assertSame(0, $this->runSubject());
    }

    /**     */
    public function testExecuteWithInvalidConfigurationReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->setupOutputDefaults();

        $schemaManager = $this->mockSchemaManager();
        $this->mockTableIndexes($schemaManager);
        $this->mockTableColumns($schemaManager);

        // Mock invalid configuration
        $this->mockBaseConfiguration('invalid-strategy');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        self::assertSame(1, $this->runSubject());
    }

    /**     */
    public function testExecuteWithMissingIndexesReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->setupOutputDefaults();

        $schemaManager = $this->mockSchemaManager();

        // No indexes
        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([]);

        $this->mockTableColumns($schemaManager);

        $this->mockBaseConfiguration('per-page');

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        self::assertSame(1, $this->runSubject());
    }

    /**     */
    public function testExecuteWithHarmonizationEnabledVerifiesHarmonizationConfig(): void
    {
        $this->setupInputDefaults();
        $this->setupOutputDefaults();

        $schemaManager = $this->mockSchemaManager();
        $this->mockTableIndexes($schemaManager);
        $this->mockTableColumns($schemaManager);

        $this->mockBaseConfiguration('per-page');

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

        self::assertSame(0, $this->runSubject());
    }

    /**     */
    public function testExecuteWithInvalidHarmonizationSlotsReturnsFailure(): void
    {
        $this->setupInputDefaults();
        $this->setupOutputDefaults();

        $schemaManager = $this->mockSchemaManager();
        $this->mockTableIndexes($schemaManager);
        $this->mockTableColumns($schemaManager);

        $this->mockBaseConfiguration('per-page');

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

        self::assertSame(1, $this->runSubject());
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

    private function setupOutputDefaults(): void
    {
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * Wires connectionPool -> connection -> schema manager and returns the
     * schema manager stub so the caller can declare its table indexes/columns.
     */
    private function mockSchemaManager(): AbstractSchemaManager&Stub
    {
        $connection = $this->createStub(Connection::class);
        $schemaManager = $this->createStub(AbstractSchemaManager::class);

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($connection);

        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        return $schemaManager;
    }

    /**
     * Declares the expected starttime/endtime indexes on the schema manager.
     */
    private function mockTableIndexes(AbstractSchemaManager&Stub $schemaManager): void
    {
        $starttimeIndex = $this->createStub(Index::class);
        $starttimeIndex->method('getColumns')->willReturn(['starttime']);

        $endtimeIndex = $this->createStub(Index::class);
        $endtimeIndex->method('getColumns')->willReturn(['endtime']);

        $schemaManager
            ->method('listTableIndexes')
            ->willReturn([$starttimeIndex, $endtimeIndex]);
    }

    /**
     * Declares the expected table columns on the schema manager.
     */
    private function mockTableColumns(AbstractSchemaManager&Stub $schemaManager): void
    {
        $col = $this->createStub(Column::class);

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
    }

    /**
     * Declares the scoping strategy plus the default (valid) timing strategy.
     */
    private function mockBaseConfiguration(string $scopingStrategy): void
    {
        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn($scopingStrategy);

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');
    }

    private function runSubject(): int
    {
        return $this->subject->run($this->input, $this->output);
    }
}
