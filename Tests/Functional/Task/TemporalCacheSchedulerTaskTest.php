<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Task;

use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory;
use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for TemporalCacheSchedulerTask
 *
 * @covers \Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask
 */
final class TemporalCacheSchedulerTaskTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /**     */
    public function testTaskExecutesSuccessfully(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');

        $repository = $this->get(TemporalContentRepository::class);
        $timingStrategyFactory = $this->get(TimingStrategyFactory::class);
        $timingStrategy = $timingStrategyFactory->get();

        $task = new TemporalCacheSchedulerTask($repository, $timingStrategy);

        $result = $task->execute();

        self::assertTrue($result);
    }
}
