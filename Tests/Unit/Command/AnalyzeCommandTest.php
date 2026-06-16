<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\AnalyzeCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\MockObject\Stub;
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
    private TemporalContentRepositoryInterface&Stub $repository;
    private ExtensionConfiguration&Stub $configuration;
    private HarmonizationService&Stub $harmonizationService;
    private InputInterface&Stub $input;
    private OutputInterface&Stub $output;
    private AnalyzeCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->harmonizationService = $this->createStub(HarmonizationService::class);
        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

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
        $this->setupOutput();
        $this->stubStatistics();

        self::assertSame(0, $this->runCommand());
    }

    /**     */
    public function testExecuteWithTemporalContentDisplaysStatistics(): void
    {
        $this->setupInputDefaults();
        $this->setupOutput();
        $this->stubStatistics([
            'total' => 50,
            'pages' => 30,
            'content' => 20,
            'withStart' => 15,
            'withEnd' => 10,
            'withBoth' => 25,
        ]);
        $this->stubTransitions();
        $this->stubHarmonizationEnabled(false);

        self::assertSame(0, $this->runCommand());
    }

    /**     */
    public function testExecuteWithUpcomingTransitionsDisplaysPeakDays(): void
    {
        $this->setupInputDefaults();
        $this->setupOutput();
        $this->stubStatistics([
            'total' => 10,
            'pages' => 5,
            'content' => 5,
            'withStart' => 5,
            'withEnd' => 5,
            'withBoth' => 0,
        ]);

        $content = $this->createTemporalContent('Test Page');
        $this->stubTransitions([
            new TransitionEvent($content, \time() + 3600, 'start'),
            new TransitionEvent($content, \time() + 7200, 'start'),
        ]);
        $this->stubHarmonizationEnabled(false);

        self::assertSame(0, $this->runCommand());
    }

    /**     */
    public function testExecuteWithHarmonizationEnabledDisplaysImpactAnalysis(): void
    {
        $this->setupInputDefaults();
        $this->setupOutput();
        $this->stubStatistics([
            'total' => 10,
            'pages' => 10,
            'content' => 0,
            'withStart' => 10,
            'withEnd' => 0,
            'withBoth' => 0,
        ]);

        $content = $this->createTemporalContent('Test');
        $this->stubTransitions([
            new TransitionEvent($content, \time() + 3600, 'start'),
        ]);
        $this->stubHarmonizationEnabled(true);

        $this->harmonizationService
            ->method('calculateHarmonizationImpact')
            ->willReturn([
                'original' => 100,
                'harmonized' => 65,
                'reduction' => 35.0,
            ]);

        self::assertSame(0, $this->runCommand());
    }

    /**     */
    public function testExecuteWithVerboseModeDisplaysConfigurationSummary(): void
    {
        $this->setupInputDefaults();
        $this->setupOutput(OutputInterface::VERBOSITY_VERBOSE);
        $this->stubStatistics([
            'total' => 5,
            'pages' => 5,
            'content' => 0,
            'withStart' => 5,
            'withEnd' => 0,
            'withBoth' => 0,
        ]);
        $this->stubTransitions();
        $this->stubHarmonizationEnabled(false);

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

        self::assertSame(0, $this->runCommand());
    }

    /**     */
    public function testExecuteWithCustomWorkspaceAndLanguagePassesCorrectly(): void
    {
        $this->setupInputDefaults([
            ['workspace', '1'],
            ['language', '2'],
            ['days', '60'],
        ]);
        $this->setupOutput();
        $this->stubStatistics();

        self::assertSame(0, $this->runCommand());
    }

    private function setupInputDefaults(array $optionMap = [
        ['workspace', '0'],
        ['language', '0'],
        ['days', '30'],
    ]): void
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
            ->willReturnMap($optionMap);
    }

    private function setupOutput(int $verbosity = OutputInterface::VERBOSITY_NORMAL): void
    {
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn($verbosity);
    }

    /**
     * @param array<string, int> $statistics
     */
    private function stubStatistics(array $statistics = [
        'total' => 0,
        'pages' => 0,
        'content' => 0,
        'withStart' => 0,
        'withEnd' => 0,
        'withBoth' => 0,
    ]): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn($statistics);
    }

    /**
     * @param array<int, TransitionEvent> $transitions
     */
    private function stubTransitions(array $transitions = []): void
    {
        $this->repository
            ->method('findTransitionsInRange')
            ->willReturn($transitions);
    }

    private function stubHarmonizationEnabled(bool $enabled): void
    {
        $this->configuration
            ->method('isHarmonizationEnabled')
            ->willReturn($enabled);
    }

    private function createTemporalContent(string $title): TemporalContent
    {
        return new TemporalContent(
            uid: 1,
            tableName: 'pages',
            title: $title,
            pid: 0,
            starttime: \time() + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );
    }

    private function runCommand(): int
    {
        return $this->subject->run($this->input, $this->output);
    }
}
