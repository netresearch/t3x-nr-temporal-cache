<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

namespace Netresearch\TemporalCache\Tests\Functional\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Which page cache entries a transition processed by the scheduler removes.
 *
 * Page cache entries carry the tags TYPO3 gives them (pageId_<uid> and the
 * page's own cache_tags), so the tags a scoping strategy names must match
 * those for an entry to disappear.
 */
#[CoversClass(SchedulerTimingStrategy::class)]
#[CoversClass(GlobalScopingStrategy::class)]
#[UsesClass(PerPageScopingStrategy::class)]
#[UsesClass(TemporalContent::class)]
#[UsesClass(TransitionEvent::class)]
#[UsesClass(ExtensionConfiguration::class)]
final class SchedulerTransitionFlushTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /**
     * The testing framework gives every cache the NullBackend; this test needs
     * a page cache that keeps entries and their tags.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    public function testThePageCacheKeepsEntriesInThisTest(): void
    {
        $this->pageCache()->set('page1', 'cached page 1', ['pageId_1']);

        self::assertTrue($this->pageCache()->has('page1'));
    }

    public function testGlobalScopingRemovesEveryPageCacheEntry(): void
    {
        $pageCache = $this->pageCache();
        $pageCache->set('page1', 'cached page 1', ['pageId_1']);
        $pageCache->set('page2', 'cached page 2', ['pageId_2']);

        $this->schedulerStrategy(
            new GlobalScopingStrategy($this->get(TemporalContentRepositoryInterface::class))
        )->processTransition($this->transitionOfContentOnPage(1));

        self::assertFalse($pageCache->has('page1'));
        self::assertFalse($pageCache->has('page2'));
    }

    public function testPerPageScopingRemovesOnlyTheAffectedPage(): void
    {
        $pageCache = $this->pageCache();
        $pageCache->set('page1', 'cached page 1', ['pageId_1']);
        $pageCache->set('page2', 'cached page 2', ['pageId_2']);

        $this->schedulerStrategy(
            new PerPageScopingStrategy($this->get(TemporalContentRepositoryInterface::class))
        )->processTransition($this->transitionOfContentOnPage(1));

        self::assertFalse($pageCache->has('page1'));
        self::assertTrue($pageCache->has('page2'));
    }

    private function pageCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('pages');
    }

    private function schedulerStrategy(ScopingStrategyInterface $scopingStrategy): SchedulerTimingStrategy
    {
        return new SchedulerTimingStrategy(
            $scopingStrategy,
            $this->get(CacheManager::class),
            $this->get(Context::class),
            new NullLogger(),
            $this->get(ExtensionConfiguration::class)
        );
    }

    private function transitionOfContentOnPage(int $pageId): TransitionEvent
    {
        return new TransitionEvent(
            content: new TemporalContent(
                uid: 10,
                tableName: 'tt_content',
                title: 'Teaser',
                pid: $pageId,
                starttime: null,
                endtime: \time() - 1,
                languageUid: 0,
                workspaceUid: 0
            ),
            timestamp: \time() - 1,
            transitionType: 'end'
        );
    }
}
