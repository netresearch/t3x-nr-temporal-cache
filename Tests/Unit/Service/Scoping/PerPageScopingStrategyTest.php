<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class PerPageScopingStrategyTest extends UnitTestCase
{
    private TemporalContentRepositoryInterface&Stub $repository;
    private Context&Stub $context;
    private PerPageScopingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->context = $this->createStub(Context::class);
        $this->subject = new PerPageScopingStrategy($this->repository);
    }

    /**     */
    public function testGetCacheTagsToFlushReturnsPageTagForPages(): void
    {
        $content = new TemporalContent(
            uid: 5,
            tableName: 'pages',
            title: 'Test',
            pid: 1,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pageId_5'], $tags);
    }

    /**     */
    public function testGetCacheTagsToFlushReturnsParentPageTagForContent(): void
    {
        $content = new TemporalContent(
            uid: 123,
            tableName: 'tt_content',
            title: 'Test',
            pid: 5,
            starttime: null,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pageId_5'], $tags);
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('per-page', $this->subject->getName());
    }
}
