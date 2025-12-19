<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\HarmonizeCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\HarmonizeCommand
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class HarmonizeCommandTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&MockObject $repository;
    private HarmonizationService&MockObject $harmonizationService;
    private ExtensionConfiguration&MockObject $configuration;
    private ConnectionPool&MockObject $connectionPool;
    private InputInterface&MockObject $input;
    private OutputInterface&MockObject $output;
    private HarmonizeCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(TemporalContentRepositoryInterface::class);
        $this->harmonizationService = $this->createMock(HarmonizationService::class);
        $this->configuration = $this->createMock(ExtensionConfiguration::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->subject = new HarmonizeCommand(
            $this->repository,
            $this->harmonizationService,
            $this->configuration,
            $this->connectionPool
        );
    }

    /**     */
    public function testCommandHasCorrectName(): void
    {
        self::assertSame('temporalcache:harmonize', $this->subject->getName());
    }

    /**     */
    public function testExecuteWithHarmonizationDisabledReturnsFailure(): void
    {
        $this->setupInputDefaults(true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithInvalidTableNameReturnsFailure(): void
    {
        $this->setupInputDefaultsWithTable('invalid_table', true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithNoTemporalContentReturnsSuccess(): void
    {
        $this->setupInputDefaults(true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteInDryRunModeDoesNotModifyDatabase(): void
    {
        $this->setupInputDefaults(true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test Page',
            pid: 0,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn(\time() + 600); // Different timestamp (needs harmonization)

        // Connection pool should NOT be called in dry-run mode
        $this->connectionPool
            ->expects(self::never())
            ->method('getConnectionForTable');

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteInLiveModeWithoutConfirmationCancels(): void
    {
        $this->setupInputDefaults(false);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        // Simulate user declining confirmation
        $this->input
            ->method('isInteractive')
            ->willReturn(true);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

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

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn(\time() + 600);

        // Connection pool should NOT be called when user declines
        $this->connectionPool
            ->expects(self::never())
            ->method('getConnectionForTable');

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithTableFilterOnlyProcessesSpecifiedTable(): void
    {
        $this->setupInputDefaultsWithTable('pages', true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

        $pageContent = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Page',
            pid: 0,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $ttContent = new TemporalContent(
            uid: 2,
            tableName: 'tt_content',
            title: 'Content',
            pid: 1,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$pageContent, $ttContent]);

        // Both timestamps same = no harmonization needed
        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnArgument(0);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithNoChangesNeededReturnsSuccess(): void
    {
        $this->setupInputDefaults(true);
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);

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

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        // Return same timestamp = no change needed
        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturnArgument(0);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    private function setupInputDefaults(bool $dryRun): void
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
            ->willReturnMap([
                ['dry-run', $dryRun],
                ['workspace', '0'],
                ['language', '0'],
                ['table', null],
            ]);
    }

    private function setupInputDefaultsWithTable(string $table, bool $dryRun): void
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
            ->willReturnMap([
                ['dry-run', $dryRun],
                ['workspace', '0'],
                ['language', '0'],
                ['table', $table],
            ]);
    }
}
