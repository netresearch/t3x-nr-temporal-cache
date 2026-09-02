<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for GlobalScopingStrategy
 */
#[CoversClass(GlobalScopingStrategy::class)]
#[UsesClass(TemporalContent::class)]
final class GlobalScopingStrategyTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $repository;

    private Context&Stub $context;

    private GlobalScopingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->context = $this->createStub(Context::class);
        $this->subject = new GlobalScopingStrategy($this->repository);
    }

    /**     */
    public function testGetCacheTagsToFlushReturnsGlobalTagForPages(): void
    {
        $content = new TemporalContent(
            uid: 5,
            tableName: 'pages',
            title: 'Test Page',
            pid: 1,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pages'], $tags);
    }

    /**     */
    public function testGetCacheTagsToFlushReturnsGlobalTagForContent(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test Content',
            pid: 5,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pages'], $tags);
    }

    /**     */
    public function testGetNextTransitionDelegatesToRepository(): void
    {
        $expectedTransition = 1620000000;

        $this->context
            ->method('getPropertyFromAspect')
            ->willReturnMap([
                ['workspace', 'id', 0, 0],
                ['language', 'id', 0, 0],
            ]);

        $this->repository
            ->method('getNextTransition')
            ->willReturn($expectedTransition);

        $result = $this->subject->getNextTransition($this->context);

        self::assertSame($expectedTransition, $result);
    }

    /**     */
    public function testGetNextTransitionRespectsWorkspaceContext(): void
    {
        $workspaceId = 1;
        $passedWorkspaceId = null;

        $this->context
            ->method('getPropertyFromAspect')
            ->willReturnMap([
                ['workspace', 'id', 0, $workspaceId],
                ['language', 'id', 0, 0],
            ]);

        $this->repository
            ->method('getNextTransition')
            ->willReturnCallback(
                static function (int $currentTimestamp, int $workspaceUid = 0, int $languageUid = 0) use (&$passedWorkspaceId): ?int {
                    $passedWorkspaceId = $workspaceUid;

                    return null;
                }
            );

        $this->subject->getNextTransition($this->context);

        // Assert: the workspace from the context reaches the repository query
        self::assertSame($workspaceId, $passedWorkspaceId);
    }

    /**     */
    public function testGetNextTransitionRespectsLanguageContext(): void
    {
        $languageId = 2;
        $passedLanguageId = null;

        $this->context
            ->method('getPropertyFromAspect')
            ->willReturnMap([
                ['workspace', 'id', 0, 0],
                ['language', 'id', 0, $languageId],
            ]);

        $this->repository
            ->method('getNextTransition')
            ->willReturnCallback(
                static function (int $currentTimestamp, int $workspaceUid = 0, int $languageUid = 0) use (&$passedLanguageId): ?int {
                    $passedLanguageId = $languageUid;

                    return null;
                }
            );

        $this->subject->getNextTransition($this->context);

        // Assert: the language from the context reaches the repository query
        self::assertSame($languageId, $passedLanguageId);
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('global', $this->subject->getName());
    }
}
