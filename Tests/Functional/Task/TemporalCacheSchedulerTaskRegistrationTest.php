<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Task;

use Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Service\TaskService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The scheduler module builds its list of selectable task types from
 * $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'].
 *
 * Without a registration there the task cannot be created in the module, so
 * timing.strategy = scheduler never processes a single transition.
 */
#[CoversClass(TemporalCacheSchedulerTask::class)]
final class TemporalCacheSchedulerTaskRegistrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    /**
     * The array TYPO3 12.4, 13 and 14 read to build the list of selectable task types.
     */
    public function testTaskIsRegisteredAsSchedulerTaskType(): void
    {
        $registeredTasks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'] ?? [];

        self::assertArrayHasKey(
            TemporalCacheSchedulerTask::class,
            $registeredTasks,
            'The scheduler task type is not registered, so it cannot be created in the scheduler module.'
        );
        self::assertSame(
            'nr_temporal_cache',
            $registeredTasks[TemporalCacheSchedulerTask::class]['extension'],
            'The task is not grouped under the extension key in the scheduler module.'
        );
    }

    /**
     * The module displays the registered labels, so they have to resolve.
     */
    public function testRegisteredLabelsResolveFromTheLanguageFile(): void
    {
        $registration = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TemporalCacheSchedulerTask::class] ?? [];

        self::assertSame(
            'Temporal Cache: Process Transitions',
            $GLOBALS['LANG']->sL($registration['title'] ?? ''),
            'Task title does not resolve through the XLIFF file.'
        );
        self::assertStringContainsString(
            'temporal content transitions',
            $GLOBALS['LANG']->sL($registration['description'] ?? ''),
            'Task description does not resolve through the XLIFF file.'
        );
    }

    /**
     * TYPO3 14 offers the task type through TaskService and stores a new task as a
     * regular tx_scheduler_task record - the DataHandler only accepts a task type
     * that TaskService knows. Both APIs differ below v14, which the serialization
     * test below covers instead.
     */
    public function testSchedulerModuleOffersAndStoresTheTaskType(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 14) {
            self::markTestSkipped('TaskService and record-based task storage exist as of TYPO3 14.');
        }

        $taskService = $this->get(TaskService::class);

        self::assertTrue($taskService->isTaskTypeRegistered(TemporalCacheSchedulerTask::class));
        self::assertArrayHasKey(TemporalCacheSchedulerTask::class, $taskService->getAllTaskTypes());

        $execution = new Execution();
        $execution->setStart(\time());
        $execution->setInterval(3600);
        $execution->setMultiple(false);

        $task = $this->get(TemporalCacheSchedulerTask::class);
        $task->setExecution($execution);

        $taskRepository = $this->get(SchedulerTaskRepository::class);
        self::assertTrue($taskRepository->add($task), 'The scheduler refused to store the task.');

        $persistedTask = $taskRepository->findByUid($task->getTaskUid());

        self::assertInstanceOf(TemporalCacheSchedulerTask::class, $persistedTask);
        self::assertTrue($persistedTask->execute(), 'The persisted task lost its dependencies and refuses to run.');
    }

    /**
     * TYPO3 12.4 and 13 store the task as serialize($task) in
     * tx_scheduler_task.serialized_task_object and unserialize it before every run
     * (TYPO3 14 rebuilds it from the container instead).
     *
     * The injected services must therefore stay out of the serialized state -
     * serializing them fails outright - and must be restored after unserializing,
     * otherwise the task refuses to run on every supported version below 14.
     */
    public function testTaskSurvivesSerializationAsUsedBelowTypo3V14(): void
    {
        $task = $this->get(TemporalCacheSchedulerTask::class);

        $restoredTask = \unserialize(\serialize($task));

        self::assertInstanceOf(TemporalCacheSchedulerTask::class, $restoredTask);
        self::assertTrue($restoredTask->execute(), 'The unserialized task lost its dependencies and refuses to run.');
    }
}
