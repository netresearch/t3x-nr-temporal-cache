<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Task;

use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * IMPORTANT: Executing the task requires the full TYPO3 Scheduler framework.
 * TemporalCacheSchedulerTask extends AbstractTask which requires Scheduler dependencies.
 * Those tests live in Tests/Functional/Task/.
 */
#[CoversClass(TemporalCacheSchedulerTask::class)]
final class TemporalCacheSchedulerTaskTest extends UnitTestCase
{
    /**     */
    public function testExecuteReturnsTrue(): void
    {
        self::markTestSkipped('Requires full TYPO3 Scheduler framework - functional test needed');
    }

    /**     */
    public function testExecuteProcessesTransitions(): void
    {
        self::markTestSkipped('Requires full TYPO3 Scheduler framework - functional test needed');
    }

    /**
     * TYPO3 12.4 and 13 store a scheduler task as serialize($task). The injected
     * services must stay out of that state - they are resolved from the container
     * again after unserializing.
     */
    public function testSerializationOmitsInjectedServices(): void
    {
        $task = new TemporalCacheSchedulerTask();

        $serializedProperties = $task->__sleep();

        foreach (['repository', 'timingStrategy', 'extensionConfiguration', 'context', 'registry'] as $serviceProperty) {
            self::assertNotContains($serviceProperty, $serializedProperties);
        }

        self::assertContains('taskUid', $serializedProperties, 'The task state itself must still be serialized.');
    }
}
