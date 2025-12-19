<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service;

use Netresearch\TemporalCache\Service\RefindexService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RefindexService
 *
 * @covers \Netresearch\TemporalCache\Service\RefindexService
 */
final class RefindexServiceTest extends UnitTestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private DeletedRestriction&MockObject $deletedRestriction;
    private RefindexService $subject;
    /** @var array<string, QueryBuilder[]> Queue of query builders by table name */
    private array $queryBuilders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->deletedRestriction = $this->createMock(DeletedRestriction::class);

        // Set up callback to return query builders by table name from queue
        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) {
                // If we have a queue for this table, shift the first one off
                if (!empty($this->queryBuilders[$table])) {
                    return \array_shift($this->queryBuilders[$table]);
                }
                // Otherwise create a default mock
                return $this->createMockQueryBuilder();
            });

        $this->subject = new RefindexService($this->connectionPool, $this->deletedRestriction);
    }

    /**     */
    public function testFindPagesWithContentReturnsDirectParentPage(): void
    {
        $contentUid = 123;
        $parentPageId = 5;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [], []);
        $this->mockPagesQuery('shortcut', [], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertSame([$parentPageId], $result);
    }

    /**     */
    public function testFindPagesWithContentReturnsReferencedPages(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $referencedPageId = 10;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'pages', 'recuid' => $referencedPageId],
        ]);
        $this->mockPagesQuery('mount_pid', [$parentPageId, $referencedPageId], []);
        $this->mockPagesQuery('shortcut', [$parentPageId, $referencedPageId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $referencedPageId], $result);
    }

    /**     */
    public function testFindPagesWithContentIncludesMountPoints(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $mountPointId = 15;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [$parentPageId], [
            ['uid' => $mountPointId],
        ]);
        $this->mockPagesQuery('shortcut', [$parentPageId, $mountPointId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $mountPointId], $result);
    }

    /**     */
    public function testFindPagesWithContentIncludesShortcuts(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $shortcutId = 20;

        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);
        $this->mockRefindexQuery($contentUid, 0, []);
        $this->mockPagesQuery('mount_pid', [$parentPageId], []);
        $this->mockPagesQuery('shortcut', [$parentPageId], [
            ['uid' => $shortcutId],
        ]);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEqualsCanonicalizing([$parentPageId, $shortcutId], $result);
    }

    /**     */
    public function testFindPagesWithContentReturnsUniquePageIds(): void
    {
        $contentUid = 123;
        $pageId = 5;

        $this->mockTtContentQuery($contentUid, ['pid' => $pageId]);
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'pages', 'recuid' => $pageId], // Duplicate
        ]);
        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertSame([$pageId], $result);
    }

    /**     */
    public function testFindPagesWithContentHandlesContentReferences(): void
    {
        $contentUid = 123;
        $parentPageId = 5;
        $referencingContentUid = 456;
        $referencingContentPage = 10;

        // Queue order: tt_content (parent), sys_refindex, tt_content (referencing), pages (mount), pages (shortcut)
        $this->mockTtContentQuery($contentUid, ['pid' => $parentPageId]);

        // Refindex query returns a content reference
        $this->mockRefindexQuery($contentUid, 0, [
            ['tablename' => 'tt_content', 'recuid' => $referencingContentUid],
        ]);

        // Second tt_content query for the referencing content's page
        $this->mockTtContentQuery($referencingContentUid, ['pid' => $referencingContentPage]);

        $this->mockPagesQuery('mount_pid', [], []);
        $this->mockPagesQuery('shortcut', [], []);

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertContains($parentPageId, $result);
        self::assertContains($referencingContentPage, $result);
    }

    /**     */
    public function testFindPagesWithContentReturnsEmptyArrayWhenContentNotFound(): void
    {
        $contentUid = 999;

        // Mock for tt_content (getDirectParentPage)
        $qb1 = $this->createMockQueryBuilder();
        $result1 = $this->createMock(\Doctrine\DBAL\Result::class);
        $result1->method('fetchOne')->willReturn(false);
        $qb1->method('executeQuery')->willReturn($result1);
        $this->queryBuilders['tt_content'][] = $qb1;

        // Mock for sys_refindex (findReferencesFromRefindex)
        $qb2 = $this->createMockQueryBuilder();
        $result2 = $this->createMock(\Doctrine\DBAL\Result::class);
        $result2->method('fetchAssociative')->willReturn(false);
        $qb2->method('executeQuery')->willReturn($result2);
        $this->queryBuilders['sys_refindex'][] = $qb2;

        // Mock for pages (findMountPointReferences)
        $qb3 = $this->createMockQueryBuilder();
        $result3 = $this->createMock(\Doctrine\DBAL\Result::class);
        $result3->method('fetchAssociative')->willReturn(false);
        $qb3->method('executeQuery')->willReturn($result3);
        $this->queryBuilders['pages'][] = $qb3;

        // Mock for pages (findShortcutReferences)
        $qb4 = $this->createMockQueryBuilder();
        $result4 = $this->createMock(\Doctrine\DBAL\Result::class);
        $result4->method('fetchAssociative')->willReturn(false);
        $qb4->method('executeQuery')->willReturn($result4);
        $this->queryBuilders['pages'][] = $qb4;

        $result = $this->subject->findPagesWithContent($contentUid);

        self::assertEmpty($result);
    }

    /**     */
    public function testHasIndirectReferencesReturnsTrueForMountPoints(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], [
            ['uid' => 10],
        ]);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertTrue($result);
    }

    /**     */
    public function testHasIndirectReferencesReturnsTrueForShortcuts(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], [
            ['uid' => 15],
        ]);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertTrue($result);
    }

    /**     */
    public function testHasIndirectReferencesReturnsFalseWhenNoReferences(): void
    {
        $pageId = 5;

        $this->mockPagesQuery('mount_pid', [$pageId], []);
        $this->mockPagesQuery('shortcut', [$pageId], []);

        $result = $this->subject->hasIndirectReferences($pageId);

        self::assertFalse($result);
    }

    /**     */
    public function testGetContentElementsOnPageReturnsAllContentUids(): void
    {
        $pageId = 5;
        $languageUid = 0;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['uid' => 10],
                ['uid' => 20],
                ['uid' => 30],
                false
            );
        $queryBuilder->method('executeQuery')->willReturn($result);

        // Store in queryBuilders queue so setUp() callback can return it
        $this->queryBuilders['tt_content'][] = $queryBuilder;

        $result = $this->subject->getContentElementsOnPage($pageId, $languageUid);

        self::assertSame([10, 20, 30], $result);
    }

    /**     */
    public function testGetContentElementsOnPageReturnsEmptyArrayWhenNoContent(): void
    {
        $pageId = 999;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturn(false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        // Store in queryBuilders queue so setUp() callback can return it
        $this->queryBuilders['tt_content'][] = $queryBuilder;

        $result = $this->subject->getContentElementsOnPage($pageId);

        self::assertEmpty($result);
    }

    private function createMockQueryBuilder(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn(':param');

        $expressionBuilder->method('eq')->willReturn('eq_expr');
        $expressionBuilder->method('in')->willReturn('in_expr');
        $restrictions->method('removeAll')->willReturnSelf();
        $restrictions->method('add')->willReturnSelf();

        return $queryBuilder;
    }

    private function mockTtContentQuery(int $contentUid, array $row): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn($row['pid'] ?? false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['tt_content'][] = $queryBuilder;
    }

    private function mockRefindexQuery(int $contentUid, int $languageUid, array $rows): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $calls = \array_map(fn ($row) => $row, $rows);
        $calls[] = false;

        $result->method('fetchAssociative')->willReturnOnConsecutiveCalls(...$calls);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['sys_refindex'][] = $queryBuilder;
    }

    private function mockPagesQuery(string $field, array $pageIds, array $resultRows): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $calls = \array_map(fn ($row) => $row, $resultRows);
        $calls[] = false;

        $result->method('fetchAssociative')->willReturnOnConsecutiveCalls(...$calls);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->queryBuilders['pages'][] = $queryBuilder;
    }
}
