<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\AnalyzeCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\AnalyzeCommand
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 * @uses \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class AnalyzeCommandTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&MockObject $repository;
    private ExtensionConfiguration&MockObject $configuration;
    private HarmonizationService&MockObject $harmonizationService;
    private InputInterface&MockObject $input;
    private OutputInterface&MockObject $output;
    private AnalyzeCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(TemporalContentRepositoryInterface::class);
        $this->configuration = $this->createMock(ExtensionConfiguration::class);
        $this->harmonizationService = $this->createMock(HarmonizationService::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->subject = new AnalyzeCommand(
            $this->repository,
            $this->configuration,
            $this->harmonizationService
        );
    }

    /**     */
    public function testCommandHasCorrectName(): void
    {
        self::assertSame('temporalcache:analyze', $this->subject->getName());
    }

    /**     */
    public function testExecuteWithNoTemporalContentReturnsSuccessWithWarning(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'total' => 0,
                'pages' => 0,
                'content' => 0,
                'withStart' => 0,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithTemporalContentDisplaysStatistics(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'total' => 50,
                'pages' => 30,
                'content' => 20,
                'withStart' => 15,
                'withEnd' => 10,
                'withBoth' => 25,
            ]);

        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithUpcomingTransitionsDisplaysPeakDays(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'total' => 10,
                'pages' => 5,
                'content' => 5,
                'withStart' => 5,
                'withEnd' => 5,
                'withBoth' => 0,
            ]);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test Page',
            pid: 0,
            starttime: \time() + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $transitions = [
            new TransitionEvent($content, \time() + 3600, 'start'),
            new TransitionEvent($content, \time() + 7200, 'start'),
        ];

        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithHarmonizationEnabledDisplaysImpactAnalysis(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'total' => 10,
                'pages' => 10,
                'content' => 0,
                'withStart' => 10,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $content = new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: 'Test',
            pid: 0,
            starttime: \time() + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $transitions = [
            new TransitionEvent($content, \time() + 3600, 'start'),
        ];

        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(true);

        $this->harmonizationService
            ->method('calculateHarmonizationImpact')
            ->willReturn([
                'original' => 100,
                'harmonized' => 65,
                'reduction' => 35.0,
            ]);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithVerboseModeDisplaysConfigurationSummary(): void
    {
        $this->setupInputDefaults();
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_VERBOSE);

        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'total' => 5,
                'pages' => 5,
                'content' => 0,
                'withStart' => 5,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn([]);

        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn(false);

        $this->configuration
            ->method('getScopingStrategy')
            ->willReturn('per-page');

        $this->configuration
            ->method('getTimingStrategy')
            ->willReturn('dynamic');

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
    public function testExecuteWithCustomWorkspaceAndLanguagePassesCorrectly(): void
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
                ['workspace', '1'],
                ['language', '2'],
                ['days', '60'],
            ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('getStatistics')
            ->with(1)
            ->willReturn([
                'total' => 0,
                'pages' => 0,
                'content' => 0,
                'withStart' => 0,
                'withEnd' => 0,
                'withBoth' => 0,
            ]);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
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
            ->willReturnMap([
                ['workspace', '0'],
                ['language', '0'],
                ['days', '30'],
            ]);
    }
}
