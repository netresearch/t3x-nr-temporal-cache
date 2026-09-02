<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Task;

use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for TemporalCacheSchedulerTask
 */
#[CoversClass(TemporalCacheSchedulerTask::class)]
final class TemporalCacheSchedulerTaskTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /**     */
    public function testTaskExecutesSuccessfully(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');

        // Get the task from DI container - it will have all dependencies injected
        $task = $this->get(TemporalCacheSchedulerTask::class);

        $result = $task->execute();

        self::assertTrue($result);
    }
}
