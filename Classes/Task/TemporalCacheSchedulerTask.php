<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Task;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task for processing temporal content transitions.
 *
 * This task:
 * 1. Finds all transitions that occurred since last run
 * 2. Creates TransitionEvent objects for each transition
 * 3. Delegates to timing strategy for cache invalidation
 *
 * Required for scheduler and hybrid timing strategies.
 * Not needed for dynamic timing strategy (event-based).
 *
 * @internal Not covered by the public API — see Documentation/Api/Index.rst.
 */
final class TemporalCacheSchedulerTask extends AbstractTask
{
    private const REGISTRY_NAMESPACE = 'tx_temporalcache';

    private const REGISTRY_KEY_LAST_RUN = 'scheduler_last_run';

    /**
     * Runtime dependencies that must never become part of the serialized task
     * object: the five injected services, plus the inherited $scheduler and
     * $logger, which TYPO3 12.4 and 13 drop themselves before storing a task
     * (SchedulerTaskRepository::update() calls $task->unsetScheduler()).
     * $scheduler no longer exists on TYPO3 14; names that are not properties of
     * the current version are simply ignored.
     *
     * @var list<string>
     */
    private const TRANSIENT_PROPERTIES = [
        'repository',
        'timingStrategy',
        'extensionConfiguration',
        'context',
        'registry',
        'scheduler',
        'logger',
    ];

    private ?TemporalContentRepository $repository = null;

    private ?TimingStrategyInterface $timingStrategy = null;

    private ?ExtensionConfiguration $extensionConfiguration = null;

    private ?Context $context = null;

    private ?Registry $registry = null;

    /**
     * Inject dependencies via setter methods for scheduler compatibility.
     * TYPO3 scheduler requires tasks to be serializable, so we use lazy injection.
     */
    public function injectTemporalContentRepository(TemporalContentRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function injectTimingStrategy(TimingStrategyInterface $timingStrategy): void
    {
        $this->timingStrategy = $timingStrategy;
    }

    public function injectExtensionConfiguration(ExtensionConfiguration $extensionConfiguration): void
    {
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function injectContext(Context $context): void
    {
        $this->context = $context;
    }

    public function injectRegistry(Registry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * Keep the injected services out of the serialized task object.
     *
     * TYPO3 12.4 and 13 store a scheduler task as serialize($task) in
     * tx_scheduler_task.serialized_task_object. Serializing the injected services
     * throws ("Serialization of 'Closure' is not allowed"), which makes the task
     * impossible to save at all. TYPO3 14 rebuilds the task from the container
     * and never takes this path.
     *
     * @return list<string> Names of the properties to serialize
     */
    public function __sleep(): array
    {
        return \array_values(
            \array_diff(\array_keys(\get_object_vars($this)), self::TRANSIENT_PROPERTIES)
        );
    }

    /**
     * Restore the services that __sleep() left out.
     */
    public function __wakeup(): void
    {
        $this->resolveMissingDependencies();
    }

    /**
     * Resolve the injected services from the container.
     *
     * Unserializing bypasses dependency injection, so the task is re-created
     * through the container - it is registered as a non-shared public service -
     * and the services are taken over from that instance.
     */
    private function resolveMissingDependencies(): void
    {
        if ($this->validateDependencies()) {
            return;
        }

        $injectedTask = GeneralUtility::makeInstance(self::class);

        $this->repository ??= $injectedTask->repository;
        $this->timingStrategy ??= $injectedTask->timingStrategy;
        $this->extensionConfiguration ??= $injectedTask->extensionConfiguration;
        $this->context ??= $injectedTask->context;
        $this->registry ??= $injectedTask->registry;
    }

    /**
     * Execute the scheduler task.
     *
     * @return bool TRUE if task executed successfully, FALSE on failure
     */
    public function execute(): bool
    {
        // Validate dependencies were injected
        if (!$this->validateDependencies()) {
            $this->logError('Missing required dependencies - check DI configuration');
            return false;
        }

        try {
            // After validation, properties are guaranteed non-null
            \assert($this->repository instanceof TemporalContentRepository);
            \assert($this->timingStrategy instanceof TimingStrategyInterface);
            \assert($this->extensionConfiguration instanceof ExtensionConfiguration);
            \assert($this->context instanceof Context);
            \assert($this->registry instanceof Registry);

            $lastRun = $this->getLastRunTimestamp();
            $now = \time();

            $this->logDebug('Scheduler task started', [
                'last_run' => $lastRun ? \date('Y-m-d H:i:s', $lastRun) : 'never',
                'current_time' => \date('Y-m-d H:i:s', $now),
                'timing_strategy' => $this->timingStrategy->getName(),
            ]);

            // Find all transitions that occurred since last run
            if (!$this->repository instanceof TemporalContentRepository) {
                throw new RuntimeException('TemporalContentRepository not injected', 1699876543);
            }

            $transitions = $this->repository->findTransitionsInRange($lastRun ?? 0, $now);

            if ($transitions === []) {
                $this->logDebug('No transitions found in time range');
                $this->setLastRunTimestamp($now);
                return true;
            }

            // Process each transition through timing strategy
            $processedCount = 0;
            $errorCount = 0;

            foreach ($transitions as $event) {
                try {
                    if (!$this->timingStrategy instanceof TimingStrategyInterface) {
                        throw new RuntimeException('TimingStrategy not injected', 1699876544);
                    }

                    $this->timingStrategy->processTransition($event);
                    $processedCount++;
                } catch (Throwable $e) {
                    $errorCount++;
                    $this->logError('Failed to process transition', [
                        'content_uid' => $event->content->uid,
                        'table' => $event->content->tableName,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $this->logInfo('Scheduler task completed', [
                'transitions_found' => \count($transitions),
                'transitions_processed' => $processedCount,
                'errors' => $errorCount,
            ]);

            // Update last run timestamp on success
            $this->setLastRunTimestamp($now);

            // Return true if at least some transitions processed
            return $errorCount === 0 || $processedCount > 0;
        } catch (Throwable $e) {
            $this->logError('Scheduler task failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Validate all dependencies are available.
     */
    private function validateDependencies(): bool
    {
        return $this->repository instanceof TemporalContentRepository
            && $this->timingStrategy instanceof TimingStrategyInterface
            && $this->extensionConfiguration instanceof ExtensionConfiguration
            && $this->context instanceof Context
            && $this->registry instanceof Registry;
    }

    /**
     * Get timestamp of last successful run.
     */
    private function getLastRunTimestamp(): ?int
    {
        if (!$this->registry instanceof Registry) {
            throw new RuntimeException('Registry not injected', 1699876545);
        }

        $lastRun = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN);
        return \is_int($lastRun) ? $lastRun : null;
    }

    /**
     * Store timestamp of current run.
     */
    private function setLastRunTimestamp(int $timestamp): void
    {
        if (!$this->registry instanceof Registry) {
            throw new RuntimeException('Registry not injected', 1699876546);
        }

        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN, $timestamp);
    }

    /**
     * Get additional information for scheduler module display.
     *
     * @return string Human-readable task information
     */
    public function getAdditionalInformation(): string
    {
        if (!$this->validateDependencies()) {
            return 'Dependencies not initialized';
        }

        // After validation, properties are guaranteed non-null
        \assert($this->timingStrategy instanceof TimingStrategyInterface);
        \assert($this->registry instanceof Registry);

        $lastRun = $this->getLastRunTimestamp();
        $strategy = $this->timingStrategy->getName();

        $info = [
            'Timing Strategy: ' . $strategy,
        ];

        if ($lastRun !== null) {
            $info[] = 'Last Run: ' . \date('Y-m-d H:i:s', $lastRun);
        } else {
            $info[] = 'Last Run: Never';
        }

        return \implode(' | ', $info);
    }

    /**
     * Get logger instance.
     */
    private function getLogger(): LoggerInterface
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
    }

    /**
     * Log debug message if debug logging enabled.
     *
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (!$this->extensionConfiguration instanceof ExtensionConfiguration) {
            throw new RuntimeException('ExtensionConfiguration not injected', 1699876547);
        }

        if ($this->extensionConfiguration->isDebugLoggingEnabled()) {
            $this->getLogger()->debug($message, $context);
        }
    }

    /**
     * Log info message.
     *
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->getLogger()->info($message, $context);
    }

    /**
     * Log error message.
     *
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        $this->getLogger()->error($message, $context);
    }
}
