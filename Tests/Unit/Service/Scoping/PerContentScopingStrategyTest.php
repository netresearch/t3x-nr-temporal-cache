<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\RefindexService;
use Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class PerContentScopingStrategyTest extends UnitTestCase
{
    private RefindexService&MockObject $refindexService;
    private TemporalContentRepositoryInterface&Stub $repository;
    private ExtensionConfiguration&Stub $configuration;
    private Context&Stub $context;
    private PerContentScopingStrategy $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refindexService = $this->createMock(RefindexService::class);
        $this->repository = $this->createStub(TemporalContentRepositoryInterface::class);
        $this->configuration = $this->createStub(ExtensionConfiguration::class);
        $this->context = $this->createStub(Context::class);

        $this->subject = new PerContentScopingStrategy(
            $this->refindexService,
            $this->repository,
            $this->configuration
        );
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
    public function testGetCacheTagsToFlushUsesRefindexForContent(): void
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

        $this->configuration->method('useRefindex')->willReturn(true);
        $this->refindexService
            ->expects(self::once())
            ->method('findPagesWithContent')
            ->with(123, 0)
            ->willReturn([5, 10, 15]);

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertEqualsCanonicalizing(['pageId_5', 'pageId_10', 'pageId_15'], $tags);
    }

    /**     */
    public function testGetCacheTagsToFlushFallsBackToParentPageWhenRefindexDisabled(): void
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

        $this->configuration->method('useRefindex')->willReturn(false);

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pageId_5'], $tags);
    }

    /**     */
    public function testGetCacheTagsToFlushFallsBackToParentPageOnRefindexFailure(): void
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

        $this->configuration->method('useRefindex')->willReturn(true);
        $this->refindexService
            ->method('findPagesWithContent')
            ->willThrowException(new \Exception('Refindex error'));

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pageId_5'], $tags);
    }

    /**     */
    public function testGetCacheTagsToFlushFallsBackWhenRefindexReturnsEmpty(): void
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

        $this->configuration->method('useRefindex')->willReturn(true);
        $this->refindexService
            ->method('findPagesWithContent')
            ->willReturn([]);

        $tags = $this->subject->getCacheTagsToFlush($content, $this->context);

        self::assertSame(['pageId_5'], $tags);
    }

    /**     */
    public function testGetNameReturnsCorrectIdentifier(): void
    {
        self::assertSame('per-content', $this->subject->getName());
    }
}
