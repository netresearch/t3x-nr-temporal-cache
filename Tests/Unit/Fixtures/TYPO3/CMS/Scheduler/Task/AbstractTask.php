<?php

declare(strict_types=1);

namespace TYPO3\CMS\Scheduler\Task;

/**
 * Test stub for TYPO3 Scheduler AbstractTask.
 *
 * This stub allows unit tests to run without requiring full TYPO3 Scheduler.
 * Provides minimal interface needed for TemporalCacheSchedulerTask testing.
 *
 * @internal For testing purposes only
 */
abstract class AbstractTask
{
    protected ?int $taskUid = null;

    protected bool $disabled = false;

    protected ?int $executionTime = null;

    /**
     * Execute the task.
     *
     * @return bool TRUE if task executed successfully
     */
    abstract public function execute();

    /**
     * Get additional information for task overview.
     *
     * @return string Additional information
     */
    public function getAdditionalInformation(): string
    {
        return '';
    }

    /**
     * Set task UID.
     */
    public function setTaskUid(int $taskUid): void
    {
        $this->taskUid = $taskUid;
    }

    /**
     * Get task UID.
     */
    public function getTaskUid(): ?int
    {
        return $this->taskUid;
    }

    /**
     * Set disabled flag.
     */
    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    /**
     * Check if task is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Set execution time.
     */
    public function setExecutionTime(int $executionTime): void
    {
        $this->executionTime = $executionTime;
    }

    /**
     * Get execution time.
     */
    public function getExecutionTime(): ?int
    {
        return $this->executionTime;
    }
}
