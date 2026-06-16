<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Command;

use Netresearch\TemporalCache\Command\ListCommand;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Command\ListCommand
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class ListCommandTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $repository;
    private InputInterface&Stub $input;
    private OutputInterface&Stub $output;
    private ListCommand $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

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
        $this->stubOutputDefaults($this->output);
        $this->stubRepositoryReturns([]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
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
        $this->stubOutputDefaults($this->output);

        self::assertSame(1, $this->subject->run($this->input, $this->output));
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
        $this->stubOutputDefaults($this->output);

        self::assertSame(1, $this->subject->run($this->input, $this->output));
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
        $this->stubOutputDefaults($this->output);

        self::assertSame(1, $this->subject->run($this->input, $this->output));
    }

    /**     */
    public function testExecuteWithTableFormatDisplaysTable(): void
    {
        $this->setupInputDefaults('table');
        $this->stubOutputDefaults($this->output);

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Test Page', starttime: \time() + 3600),
        ]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
    }

    /**     */
    public function testExecuteWithJsonFormatOutputsJson(): void
    {
        $this->setupInputDefaults('json');

        $output = $this->createMock(OutputInterface::class);
        $this->stubOutputDefaults($output);

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Test Page', starttime: \time()),
        ]);

        // Expect JSON output
        $output
            ->expects(self::atLeastOnce())
            ->method('writeln')
            ->with(self::stringContains('"table":'));

        self::assertSame(0, $this->subject->run($this->input, $output));
    }

    /**     */
    public function testExecuteWithCsvFormatOutputsCsv(): void
    {
        $this->setupInputDefaults('csv');

        $output = $this->createMock(OutputInterface::class);
        $this->stubOutputDefaults($output);

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Test Page', starttime: \time()),
        ]);

        // Expect CSV output with header
        $output
            ->expects(self::atLeastOnce())
            ->method('writeln')
            ->with(self::logicalOr(
                self::stringContains('Table,UID'),
                self::stringContains('pages,1')
            ));

        self::assertSame(0, $this->subject->run($this->input, $output));
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
        $this->stubOutputDefaults($this->output);

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Page', starttime: \time()),
            $this->makeContent(uid: 2, tableName: 'tt_content', title: 'Content', pid: 1, starttime: \time()),
        ]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
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
        $this->stubOutputDefaults($this->output);

        $futureTime = \time() + 3600;
        $pastTime = \time() - 3600;

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Future', starttime: $futureTime),
            $this->makeContent(uid: 2, title: 'Past', starttime: $pastTime),
        ]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
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
        $this->stubOutputDefaults($this->output);

        $this->stubRepositoryReturns([
            $this->makeContent(uid: 1, title: 'Page 1', starttime: \time()),
            $this->makeContent(uid: 2, title: 'Page 2', starttime: \time()),
        ]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sortFieldDataProvider')]
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
        $this->stubOutputDefaults($this->output);

        $this->stubRepositoryReturns([
            $this->makeContent(
                uid: 2,
                tableName: 'tt_content',
                title: 'B Content',
                starttime: \time() + 7200,
                endtime: \time() + 10800
            ),
            $this->makeContent(
                uid: 1,
                title: 'A Page',
                starttime: \time() + 3600,
                endtime: \time() + 14400
            ),
        ]);

        self::assertSame(0, $this->subject->run($this->input, $this->output));
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

    /**
     * Stubs the verbosity/decoration accessors every execution path queries.
     */
    private function stubOutputDefaults(OutputInterface&Stub $output): void
    {
        $output->method('isDecorated')->willReturn(false);
        $output->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * @param list<TemporalContent> $content
     */
    private function stubRepositoryReturns(array $content): void
    {
        $this->repository
            ->method('findAllWithTemporalFields')
            ->willReturn($content);
    }

    private function makeContent(
        int $uid,
        string $title,
        int $starttime,
        string $tableName = 'pages',
        int $pid = 0,
        ?int $endtime = null,
    ): TemporalContent {
        return new TemporalContent(
            uid: $uid,
            tableName: $tableName,
            title: $title,
            pid: $pid,
            starttime: $starttime,
            endtime: $endtime,
            languageUid: 0,
            workspaceUid: 0
        );
    }

    private function setupInputDefaults(string $format): void
    {
        $this->stubInputBaseline();

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
        $this->stubInputBaseline();

        $map = [];
        foreach ($options as $key => $value) {
            $map[] = [$key, $value];
        }

        $this->input
            ->method('getOption')
            ->willReturnMap($map);
    }

    /**
     * Stubs the input methods the Symfony command lifecycle calls regardless of options.
     */
    private function stubInputBaseline(): void
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
    }
}
