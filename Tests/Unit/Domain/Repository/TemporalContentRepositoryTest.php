<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Domain\Repository;

use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Cache\TransitionCache;
use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository
 * @uses \Netresearch\TemporalCache\Domain\Model\TemporalContent
 */
final class TemporalContentRepositoryTest extends UnitTestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private TransitionCache $transitionCache;
    private TemporalMonitorRegistry $monitorRegistry;
    private DeletedRestriction&Stub $deletedRestriction;
    private TemporalContentRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        // Use real TransitionCache instance (it's just in-memory, no side effects)
        $this->transitionCache = new TransitionCache();
        // Use real TemporalMonitorRegistry instance (it's a final class, can't be mocked)
        $this->monitorRegistry = new TemporalMonitorRegistry();
        $this->deletedRestriction = $this->createStub(DeletedRestriction::class);
        $this->subject = new TemporalContentRepository(
            $this->connectionPool,
            $this->transitionCache,
            $this->monitorRegistry,
            $this->deletedRestriction
        );
    }

    /**     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetNextTransitionReturnsNullWhenNoTransitions(): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createStub(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn(false);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        $result = $this->subject->getNextTransition(\time(), 0, 0);

        self::assertNull($result);
    }

    /**     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetNextTransitionReturnsEarliestTransition(): void
    {
        $nextTransition = \time() + 3600;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createStub(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn($nextTransition);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        $result = $this->subject->getNextTransition(\time(), 0, 0);

        self::assertSame($nextTransition, $result);
    }

    /**     */
    public function testGetNextTransitionUsesCacheOnSecondCall(): void
    {
        $currentTime = \time();
        $nextTransition = $currentTime + 3600;

        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createStub(\Doctrine\DBAL\Result::class);
        $result->method('fetchOne')->willReturn($nextTransition);
        $queryBuilder->method('executeQuery')->willReturn($result);

        // QueryBuilder should be called only once (first call), not on second call (cached)
        $this->connectionPool
            ->expects(self::exactly(4))  // 4 queries for MIN operations
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        // First call - should query database
        $result1 = $this->subject->getNextTransition($currentTime, 0, 0);
        self::assertSame($nextTransition, $result1);

        // Second call - should use cache (connection pool not called again)
        $result2 = $this->subject->getNextTransition($currentTime, 0, 0);
        self::assertSame($nextTransition, $result2);
    }

    /**     */
    #[AllowMockObjectsWithoutExpectations]
    public function testFindAllWithTemporalFieldsReturnsContentArray(): void
    {
        $queryBuilder = $this->createMockQueryBuilder();
        $result = $this->createStub(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'uid' => 1,
                'pid' => 0,
                'title' => 'Test Page',
                'starttime' => \time(),
                'endtime' => 0,
                'sys_language_uid' => 0,
                't3ver_wsid' => 0,
                'hidden' => 0,
                'deleted' => 0,
            ],
            false,  // End of first table result set
            false,  // Additional tables return no results
            false,
            false
        );
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        $result = $this->subject->findAllWithTemporalFields(0, 0);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
    }

    private function createMockQueryBuilder(): QueryBuilder&Stub
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('addSelectLiteral')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();

        // Use willReturnCallback with no type restrictions
        $queryBuilder->method('createNamedParameter')->willReturnCallback(
            function () {
                return ':param_' . \uniqid();
            }
        );
        $queryBuilder->method('quoteIdentifier')->willReturnArgument(0);

        // Create mock CompositeExpression objects for proper return types
        $compositeExpression = $this->createStub(CompositeExpression::class);
        $compositeExpression->method('__toString')->willReturn('expr_composite');

        // Expression builder returns CompositeExpression for or/and, string for others
        $expressionBuilder->method('eq')->willReturn('expr_eq');
        $expressionBuilder->method('gt')->willReturn('expr_gt');
        $expressionBuilder->method('or')->willReturn($compositeExpression);
        $expressionBuilder->method('and')->willReturn($compositeExpression);
        $expressionBuilder->method('isNull')->willReturn('expr_isnull');

        $restrictions->method('removeAll')->willReturnSelf();
        $restrictions->method('add')->willReturnSelf();

        return $queryBuilder;
    }
}
