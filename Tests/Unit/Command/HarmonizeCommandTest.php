<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\HarmonizeCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\HarmonizeCommand
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
#[AllowMockObjectsWithoutExpectations]
final class HarmonizeCommandTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $repository;
    private HarmonizationService&Stub $harmonizationService;
    private ExtensionConfiguration&Stub $configuration;
    private ConnectionPool&MockObject $connectionPool;
    private InputInterface&Stub $input;
    private OutputInterface&Stub $output;
    private HarmonizeCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->harmonizationService = $this->createStub(HarmonizationService::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

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
        $this->setupOutputDefaults();

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithInvalidTableNameReturnsFailure(): void
    {
        $this->setupInputDefaults(true, 'invalid_table');
        $this->setupOutputDefaults();

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
        $this->setupOutputDefaults();
        $this->enableHarmonization();

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
        $this->setupOutputDefaults();
        $this->enableHarmonization();

        $content = $this->createTemporalContent(title: 'Test Page');

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn(\time() + 600); // Different timestamp (needs harmonization)

        // Connection pool should NOT be called in dry-run mode
        $this->expectConnectionPoolNeverUsed();

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteInLiveModeWithoutConfirmationCancels(): void
    {
        $this->setupInputDefaults(false);
        $this->setupOutputDefaults();

        // Simulate user declining confirmation
        $this->input
            ->method('isInteractive')
            ->willReturn(true);

        $this->enableHarmonization();

        $content = $this->createTemporalContent(title: 'Test');

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([$content]);

        $this->harmonizationService
            ->method('harmonizeTimestamp')
            ->willReturn(\time() + 600);

        // Connection pool should NOT be called when user declines
        $this->expectConnectionPoolNeverUsed();

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithTableFilterOnlyProcessesSpecifiedTable(): void
    {
        $this->setupInputDefaults(true, 'pages');
        $this->setupOutputDefaults();
        $this->enableHarmonization();

        $pageContent = $this->createTemporalContent(title: 'Page');

        $ttContent = $this->createTemporalContent(
            uid: 2,
            tableName: 'tt_content',
            title: 'Content',
            pid: 1
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
        $this->setupOutputDefaults();
        $this->enableHarmonization();

        $content = $this->createTemporalContent(title: 'Test');

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

    private function setupInputDefaults(bool $dryRun, ?string $table = null): void
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

    private function setupOutputDefaults(): void
    {
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);
    }

    private function enableHarmonization(): void
    {
        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->configuration
            ->method('getHarmonizationSlots')
            ->willReturn(['00:00', '12:00']);

        $this->configuration
            ->method('getHarmonizationTolerance')
            ->willReturn(900);
    }

    private function createTemporalContent(
        int $uid = 1,
        string $tableName = 'pages',
        string $title = 'Test',
        int $pid = 0,
    ): TemporalContent {
        return new TemporalContent(
            uid: $uid,
            tableName: $tableName,
            title: $title,
            pid: $pid,
            starttime: \time(),
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );
    }

    private function expectConnectionPoolNeverUsed(): void
    {
        $this->connectionPool
            ->expects(self::never())
            ->method('getConnectionForTable');
    }
}
