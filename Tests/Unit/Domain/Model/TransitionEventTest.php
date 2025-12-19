<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Domain\Model;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for TransitionEvent value object
 *
 * @covers \Netresearch\TemporalCache\Domain\Model\TransitionEvent
 */
final class TransitionEventTest extends UnitTestCase
{
    private TemporalContent $temporalContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporalContent = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test Content',
            pid: 5,
            starttime: 1609459200,
            endtime: 1612137600,
            languageUid: 0,
            workspaceUid: 0
        );
    }

    /**     */
    public function testConstructorCreatesImmutableObject(): void
    {
        $timestamp = 1609459200;

        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: $timestamp,
            transitionType: 'start',
            workspaceId: 1,
            languageId: 2
        );

        self::assertSame($this->temporalContent, $subject->content);
        self::assertSame($timestamp, $subject->timestamp);
        self::assertSame('start', $subject->transitionType);
        self::assertSame(1, $subject->workspaceId);
        self::assertSame(2, $subject->languageId);
    }

    /**     */
    public function testConstructorUsesDefaultValues(): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: 'start'
        );

        self::assertSame(0, $subject->workspaceId);
        self::assertSame(0, $subject->languageId);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validTransitionTypeDataProvider')]
    public function testConstructorAcceptsValidTransitionTypes(string $transitionType): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: $transitionType
        );

        self::assertSame($transitionType, $subject->transitionType);
    }

    public static function validTransitionTypeDataProvider(): array
    {
        return [
            'start' => ['start'],
            'end' => ['end'],
            'unknown' => ['unknown'],
        ];
    }

    /**     */
    public function testConstructorThrowsExceptionForInvalidTransitionType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TransitionType must be "start", "end", or "unknown"');

        new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: 'invalid'
        );
    }

    /**     */
    public function testIsStartTransitionReturnsTrueForStartType(): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: 'start'
        );

        self::assertTrue($subject->isStartTransition());
        self::assertFalse($subject->isEndTransition());
    }

    /**     */
    public function testIsEndTransitionReturnsTrueForEndType(): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1612137600,
            transitionType: 'end'
        );

        self::assertTrue($subject->isEndTransition());
        self::assertFalse($subject->isStartTransition());
    }

    /**     */
    public function testIsStartAndEndReturnFalseForUnknownType(): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: 'unknown'
        );

        self::assertFalse($subject->isStartTransition());
        self::assertFalse($subject->isEndTransition());
    }

    /**     */
    public function testGetLogMessageReturnsFormattedMessage(): void
    {
        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: 1609459200,
            transitionType: 'start',
            workspaceId: 1,
            languageId: 2
        );

        $message = $subject->getLogMessage();

        self::assertStringContainsString('tt_content', $message);
        self::assertStringContainsString('#123', $message);
        self::assertStringContainsString('Test Content', $message);
        self::assertStringContainsString('start', $message);
        self::assertStringContainsString('workspace=1', $message);
        self::assertStringContainsString('language=2', $message);
    }

    /**     */
    public function testGetLogMessageIncludesFormattedTimestamp(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC

        $subject = new TransitionEvent(
            content: $this->temporalContent,
            timestamp: $timestamp,
            transitionType: 'start'
        );

        $message = $subject->getLogMessage();

        self::assertStringContainsString(\date('Y-m-d H:i:s', $timestamp), $message);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('transitionScenarioDataProvider')]
    public function testTransitionEventsWorkForDifferentScenarios(
        string $tableName,
        string $transitionType,
        int $workspaceId,
        int $languageId
    ): void {
        $content = new TemporalContent(
            uid: 456,
            tableName: $tableName,
            title: 'Test',
            pid: 10,
            starttime: 1609459200,
            endtime: 1612137600,
            languageUid: $languageId,
            workspaceUid: $workspaceId
        );

        $subject = new TransitionEvent(
            content: $content,
            timestamp: 1609459200,
            transitionType: $transitionType,
            workspaceId: $workspaceId,
            languageId: $languageId
        );

        $message = $subject->getLogMessage();

        self::assertStringContainsString($tableName, $message);
        self::assertStringContainsString($transitionType, $message);
        self::assertStringContainsString("workspace=$workspaceId", $message);
        self::assertStringContainsString("language=$languageId", $message);
    }

    public static function transitionScenarioDataProvider(): array
    {
        return [
            'page start in default workspace/language' => ['pages', 'start', 0, 0],
            'content end in workspace 1' => ['tt_content', 'end', 1, 0],
            'page start in language 2' => ['pages', 'start', 0, 2],
            'content unknown in workspace 1 and language 3' => ['tt_content', 'unknown', 1, 3],
        ];
    }
}
