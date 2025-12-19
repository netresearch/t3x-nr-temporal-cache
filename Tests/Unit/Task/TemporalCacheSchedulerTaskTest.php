<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Task;

use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask
 *
 * IMPORTANT: These tests require full TYPO3 Scheduler framework.
 * TemporalCacheSchedulerTask extends AbstractTask which requires Scheduler dependencies.
 * Skipped in unit tests - requires functional/integration test setup.
 */
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
}
