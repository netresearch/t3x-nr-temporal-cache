<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\ListCommand;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\ListCommand
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class ListCommandTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&MockObject $repository;
    private InputInterface&MockObject $input;
    private OutputInterface&MockObject $output;
    private ListCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(TemporalContentRepositoryInterface::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->subject = new ListCommand($this->repository);
    }

    /**     */
    public function testCommandHasCorrectName(): void
    {
        self::assertSame('temporalcache:list', $this->subject->getName());
    }

    /**     */
    public function testExecuteWithNoTemporalContentReturnsSuccess(): void
    {
        $this->setupInputDefaults('table');
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn([]);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithInvalidTableNameReturnsFailure(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => 'invalid_table',
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => 'uid',
            'format' => 'table',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithInvalidSortFieldReturnsFailure(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => null,
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => 'invalid_field',
            'format' => 'table',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithInvalidFormatReturnsFailure(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => null,
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => 'uid',
            'format' => 'invalid_format',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(1, $result);
    }

    /**     */
    public function testExecuteWithTableFormatDisplaysTable(): void
    {
        $this->setupInputDefaults('table');
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Test Page',
                pid: 0,
                starttime: \time() + 3600,
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithJsonFormatOutputsJson(): void
    {
        $this->setupInputDefaults('json');
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Test Page',
                pid: 0,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        // Expect JSON output
        $this->output
            ->expects(self::atLeastOnce())
            ->method('writeln')
            ->with(self::stringContains('"table":'));

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithCsvFormatOutputsCsv(): void
    {
        $this->setupInputDefaults('csv');
        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Test Page',
                pid: 0,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        // Expect CSV output with header
        $this->output
            ->expects(self::atLeastOnce())
            ->method('writeln')
            ->with(self::logicalOr(
                self::stringContains('Table,UID'),
                self::stringContains('pages,1')
            ));

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithTableFilterOnlyShowsSpecifiedTable(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => 'pages',
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => 'uid',
            'format' => 'table',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Page',
                pid: 0,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
            new TemporalContent(
                uid: 2,
                tableName: 'tt_content',
                title: 'Content',
                pid: 1,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithUpcomingFilterOnlyShowsFutureTransitions(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => null,
            'workspace' => '0',
            'language' => '0',
            'upcoming' => true,
            'sort' => 'uid',
            'format' => 'table',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $futureTime = \time() + 3600;
        $pastTime = \time() - 3600;

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Future',
                pid: 0,
                starttime: $futureTime,
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
            new TemporalContent(
                uid: 2,
                tableName: 'pages',
                title: 'Past',
                pid: 0,
                starttime: $pastTime,
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     */
    public function testExecuteWithLimitOptionLimitsResults(): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => null,
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => 'uid',
            'format' => 'table',
            'limit' => '1',
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'Page 1',
                pid: 0,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
            new TemporalContent(
                uid: 2,
                tableName: 'pages',
                title: 'Page 2',
                pid: 0,
                starttime: \time(),
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    /**     * @dataProvider sortFieldDataProvider
     */
    public function testExecuteWithDifferentSortFieldsSortsCorrectly(string $sortField): void
    {
        $this->setupInputDefaultsWithOptions([
            'table' => null,
            'workspace' => '0',
            'language' => '0',
            'upcoming' => false,
            'sort' => $sortField,
            'format' => 'table',
            'limit' => null,
        ]);

        $this->output->method('isDecorated')->willReturn(false);
        $this->output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $content = [
            new TemporalContent(
                uid: 2,
                tableName: 'tt_content',
                title: 'B Content',
                pid: 0,
                starttime: \time() + 7200,
                endtime: \time() + 10800,
                languageUid: 0,
                workspaceUid: 0
            ),
            new TemporalContent(
                uid: 1,
                tableName: 'pages',
                title: 'A Page',
                pid: 0,
                starttime: \time() + 3600,
                endtime: \time() + 14400,
                languageUid: 0,
                workspaceUid: 0
            ),
        ];

        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);

        $result = $this->subject->run($this->input, $this->output);

        self::assertSame(0, $result);
    }

    public static function sortFieldDataProvider(): array
    {
        return [
            'sort by uid' => ['uid'],
            'sort by title' => ['title'],
            'sort by table' => ['table'],
            'sort by starttime' => ['starttime'],
            'sort by endtime' => ['endtime'],
        ];
    }

    private function setupInputDefaults(string $format): void
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
                ['table', null],
                ['workspace', '0'],
                ['language', '0'],
                ['upcoming', false],
                ['sort', 'uid'],
                ['format', $format],
                ['limit', null],
            ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function setupInputDefaultsWithOptions(array $options): void
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

        $map = [];
        foreach ($options as $key => $value) {
            $map[] = [$key, $value];
        }

        $this->input
            ->method('getOption')
            ->willReturnMap($map);
    }
}
