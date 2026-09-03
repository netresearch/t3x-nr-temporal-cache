.. include:: /Includes.rst.txt

.. _architecture:

============
Architecture
============

.. _architecture-problem:

The gap this extension fills
============================

TYPO3's page cache is invalidated by two mechanisms.

Event-driven invalidation
   A cache entry is dropped when the data behind it changes, for example when an editor
   saves a page.

Tag-based invalidation
   ``flushByTag()`` drops every entry carrying a given tag.

Neither reacts to the passage of time.
A page or content element with ``starttime`` or ``endtime`` changes its visibility at a
fixed moment without anybody editing a record, so no invalidation is triggered.
The cache entry keeps the visibility snapshot taken at render time until its relative
lifetime runs out.

.. code-block:: text

   Render at 09:00           Cache entry written with a relative lifetime
   ├─ element A: hidden      (starttime 10:00 has not been reached)
   └─ element B: visible

   10:00                     Nothing edits a record, so nothing invalidates
                             the entry. Element A stays hidden until the
                             lifetime expires.

The extension closes that gap by shortening the page cache lifetime so it ends at the
next temporal transition instead of at an arbitrary later moment.

.. _architecture-listener:

Entry point: the cache lifetime event
=====================================

TYPO3 dispatches ``TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent`` while a page
cache entry is being written.
``Netresearch\TemporalCache\EventListener\TemporalCacheLifetime`` is registered for exactly
that event class in :file:`Configuration/Services.yaml`, and it is the extension's only
frontend hook.

.. code-block:: php
    :caption: Classes/EventListener/TemporalCacheLifetime.php (error handling and debug logging omitted)

    final class TemporalCacheLifetime
    {
        public function __construct(
            private readonly ExtensionConfiguration $extensionConfiguration,
            private readonly ScopingStrategyInterface $scopingStrategy,
            private readonly TimingStrategyInterface $timingStrategy,
            private readonly Context $context,
            private readonly LoggerInterface $logger
        ) {
        }

        public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
        {
            $lifetime = $this->timingStrategy->getCacheLifetime($this->context, $event->getPageId());

            if ($lifetime !== null) {
                $maxLifetime = $this->determineMaxLifetime($event->getRenderingInstructions());
                $event->setCacheLifetime(\min($lifetime, $maxLifetime));
            }
        }
    }

The listener runs no queries of its own.
It asks the active timing strategy for a lifetime and caps the answer.
A ``null`` lifetime — what the scheduler timing strategy always returns — leaves TYPO3's
own lifetime untouched.

The whole ``__invoke()`` body is wrapped in a ``try``/``catch (Throwable)``.
A failing strategy is logged as an error and the page renders with TYPO3's lifetime; it
never breaks page rendering.

.. _architecture-max-lifetime:

Cap on the calculated lifetime
------------------------------

``determineMaxLifetime()`` resolves the upper bound in this order:

#. ``cache_period`` from the rendering instructions, if it is set and greater than zero
   (TypoScript ``config.cache_period``)
#. ``advanced.default_max_lifetime`` from the extension configuration, if greater than zero
#. ``86400``

The listener also caps the value the timing strategy already capped, because
``DynamicTimingStrategy`` limits its own result to ``advanced.default_max_lifetime``
independently.

.. _architecture-registration:

Registration
------------

.. code-block:: yaml
    :caption: Configuration/Services.yaml (arguments omitted)

    services:
      Netresearch\TemporalCache\EventListener\TemporalCacheLifetime:
        public: true
        tags:
          - name: event.listener
            identifier: 'temporal-cache/modify-cache-lifetime'
            event: TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent
            method: '__invoke'

.. _architecture-strategy-selection:

Strategy selection and wiring
=============================

Two independent strategy families decide what happens:

Scoping strategy (``ScopingStrategyInterface``)
   Answers *which* records a transition lookup covers and *which* cache tags a transition
   flushes.
   Implementations: ``GlobalScopingStrategy``, ``PerPageScopingStrategy``,
   ``PerContentScopingStrategy``.

Timing strategy (``TimingStrategyInterface``)
   Answers *when* the invalidation happens — through a shortened cache lifetime, through a
   background scheduler run, or a mix of the two.
   Implementations: ``DynamicTimingStrategy``, ``SchedulerTimingStrategy``,
   ``HybridTimingStrategy``.

Both interfaces extend ``Netresearch\TemporalCache\Service\NamedStrategyInterface``, which
declares the single method ``getName(): string``.

.. _architecture-strategy-factories:

How a strategy is activated
---------------------------

Every strategy is registered as a service carrying one of two tags:

- ``nr_temporal_cache.scoping_strategy``
- ``nr_temporal_cache.timing_strategy``

``ScopingStrategyFactory`` and ``TimingStrategyFactory`` receive all services carrying
their family's tag through Symfony's ``!tagged_iterator``:

.. code-block:: yaml
    :caption: Configuration/Services.yaml

    Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory:
      public: true
      arguments:
        $strategies: !tagged_iterator 'nr_temporal_cache.scoping_strategy'
        $extensionConfiguration: '@Netresearch\TemporalCache\Configuration\ExtensionConfiguration'

    Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface:
      alias: Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory
      public: false

Both factories share the selection logic in the trait
``Netresearch\TemporalCache\Service\SelectsNamedStrategy``:

.. code-block:: php
    :caption: Classes/Service/SelectsNamedStrategy.php

    private function selectNamedStrategy(
        iterable $strategies,
        string $configuredName,
        string $emptyMessage
    ): NamedStrategyInterface {
        $firstStrategy = null;

        foreach ($strategies as $strategy) {
            $firstStrategy ??= $strategy;

            if ($strategy->getName() === $configuredName) {
                return $strategy;
            }
        }

        return $firstStrategy ?? throw new RuntimeException($emptyMessage);
    }

The configured name is ``scoping.strategy`` respectively ``timing.strategy`` from the
extension configuration.
Three consequences follow from this code:

- The tags carry **no** ``identifier`` attribute.
  A strategy is identified by its ``getName()`` return value alone.
- When no name matches, the **first** tagged service wins.
  ``GlobalScopingStrategy`` and ``DynamicTimingStrategy`` carry ``priority: 100`` in
  :file:`Configuration/Services.yaml` so that they come first and hold that fallback.
- When a family has no tagged service at all, the factory throws a ``RuntimeException``.

Each factory implements its own family interface and delegates every call to the selected
strategy, and the interface name is aliased to the factory.
Anything type-hinting ``ScopingStrategyInterface`` or ``TimingStrategyInterface``
therefore receives the factory and, through it, the configured strategy.

.. _architecture-custom-strategy:

Adding a strategy from another extension
----------------------------------------

Because the factories iterate a tag rather than a hard-coded list, a strategy declared in
another extension needs nothing but the tag:

.. code-block:: php
    :caption: EXT:my_extension/Classes/Scoping/RootlineScopingStrategy.php

    namespace MyVendor\MyExtension\Scoping;

    use Netresearch\TemporalCache\Domain\Model\TemporalContent;
    use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
    use TYPO3\CMS\Core\Context\Context;

    final class RootlineScopingStrategy implements ScopingStrategyInterface
    {
        public function getCacheTagsToFlush(TemporalContent $content, Context $context): array
        {
            return ['pageId_' . $content->pid];
        }

        public function getNextTransition(Context $context, ?int $pageId = null): ?int
        {
            return null;
        }

        public function getName(): string
        {
            return 'rootline';
        }
    }

.. code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    services:
      MyVendor\MyExtension\Scoping\RootlineScopingStrategy:
        tags:
          - { name: 'nr_temporal_cache.scoping_strategy' }

Setting ``scoping.strategy`` to ``rootline`` then activates it.

.. note::
    Inside this extension, autoregistration excludes ``Service/Scoping/*Strategy.php`` and
    ``Service/Timing/*Strategy.php``, so its own strategies need explicit service
    definitions.
    An extension with the default ``autoconfigure`` setup only has to add the tag.

.. _architecture-repository:

Transition lookups
==================

All temporal queries live in
``Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository``
(contract: ``TemporalContentRepositoryInterface``).
Three methods feed the strategies:

.. code-block:: php
    :caption: Classes/Domain/Repository/TemporalContentRepositoryInterface.php

    // Earliest transition across every monitored table (site-wide)
    public function getNextTransition(
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

    // Earliest transition in the pages table only
    public function getNextPageTransition(
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

    // Earliest transition in the content tables, restricted to one pid
    public function getNextContentTransitionForPage(
        int $pageId,
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

Each of them runs one ``MIN()`` query per monitored table and per temporal field
(``starttime``, ``endtime``) and returns the smallest non-null result.
With the two default tables that is four queries.

``findMinTransitionForTable()`` builds every query the same way:

- ``removeAll()`` on the restrictions.
  TYPO3's ``StartTimeRestriction``/``EndTimeRestriction`` would hide exactly the future
  records the lookup needs.
- ``MIN(<field>)`` selected as a literal, ``WHERE <field> > :now``.
  Records with ``0`` are excluded by that comparison, so no separate ``!= 0`` clause exists.
- The table's ``deleted`` and ``disabled`` columns, resolved from TCA, each compared to
  ``0``.
  When no TCA is available the query simply runs without them.
- ``pid = :pageId`` when the caller passed a page id.
- The workspace clause: for workspace ``0`` it matches ``t3ver_wsid = 0 OR t3ver_wsid IS
  NULL``, otherwise ``t3ver_wsid = :workspace``.
- ``sys_language_uid = :language`` whenever the language id is ``>= 0``.

.. _architecture-request-cache:

Request-level memoization
-------------------------

``getNextTransition()`` — the site-wide lookup only — is memoized in
``Netresearch\TemporalCache\Service\Cache\TransitionCache``, a singleton keyed by
timestamp, workspace id and language id.
Repeated site-wide lookups within the same request and the same second are answered from
memory.
``getNextPageTransition()`` and ``getNextContentTransitionForPage()`` are not memoized.

.. _architecture-indexes:

Indexes
-------

:file:`ext_tables.sql` adds two composite indexes per default table so the ``MIN()``
aggregation and the ``> :now`` range scan can be served from an index:

- ``pages``: ``idx_temporalcache_starttime (starttime, sys_language_uid)`` and
  ``idx_temporalcache_endtime (endtime, sys_language_uid)``
- ``tt_content``: the same two indexes

``temporalcache:verify`` checks that these indexes exist.
Tables registered by other extensions get no index from this extension.

.. _architecture-scoping:

What each scoping strategy does
===============================

A scoping strategy answers two separate questions, and the answers do not have to agree.

``getNextTransition()``
   Used by ``DynamicTimingStrategy`` to compute a cache lifetime.

``getCacheTagsToFlush()``
   Used by ``SchedulerTimingStrategy`` to flush caches from the scheduler task.
   ``DynamicTimingStrategy`` never calls it.

.. list-table:: Scoping strategies
    :header-rows: 1
    :widths: 20 40 40

    * - Strategy
      - ``getNextTransition()`` covers
      - ``getCacheTagsToFlush()`` returns
    * - ``global``
      - Every monitored table, site-wide. The page id is ignored.
      - ``['pages']`` — the tag every page cache entry carries.
    * - ``per-page``
      - The ``pages`` table site-wide, plus the content tables restricted to the rendered
        page. Falls back to the site-wide lookup when no page id is available.
      - ``['pageId_<uid>']`` for a page, ``['pageId_<pid>']`` for a content element.
    * - ``per-content``
      - Every monitored table, site-wide — the same lookup as ``global``.
      - One ``pageId_<uid>`` tag per page that ``sys_refindex`` reports for the element.

.. important::
    ``per-content`` narrows the flush tags, not the cache lifetime.
    Its ``getNextTransition()`` deliberately returns the site-wide transition, because a
    content element can be embedded into arbitrary pages and a narrowed lifetime would risk
    serving stale embedded content.
    Combined with ``dynamic`` timing — which only ever reads the lifetime — ``per-content``
    therefore behaves exactly like ``global``.
    Its precision takes effect with ``scheduler`` or ``hybrid`` timing.

.. note::
    ``per-page`` keeps menus correct by watching all page transitions site-wide, but it does
    not see content embedded from another page through ``CONTENT``/``RECORDS`` cObjects.
    That page's lifetime is not shortened when the embedded element transitions.

.. _architecture-scoping-refindex:

Refindex resolution
-------------------

``PerContentScopingStrategy`` resolves the affected pages through
``Netresearch\TemporalCache\Service\RefindexService``, which reads ``sys_refindex``.
It falls back to the element's own ``pid`` when ``scoping.use_refindex`` is off, when the
lookup returns no page, or when it throws.
Pages are never resolved through the refindex — a page transition always yields the page's
own tag.

.. _architecture-timing:

What each timing strategy does
==============================

.. list-table:: Timing strategies
    :header-rows: 1
    :widths: 20 40 40

    * - Strategy
      - ``getCacheLifetime()``
      - ``processTransition()``
    * - ``dynamic``
      - Seconds until the scoping strategy's next transition, capped at
        ``advanced.default_max_lifetime``. ``60`` if the transition is already in the past,
        ``advanced.default_max_lifetime`` if there is none.
      - No-op. Expiry alone does the work.
    * - ``scheduler``
      - ``null`` — the listener leaves TYPO3's lifetime alone.
      - Flushes every tag the scoping strategy returns from the ``pages`` cache.
    * - ``hybrid``
      - Delegates to the strategy configured under ``timing.hybrid.pages``.
      - Delegates per record: ``timing.hybrid.pages`` for a page,
        ``timing.hybrid.content`` for anything else.

.. note::
    ``HybridTimingStrategy::getCacheLifetime()`` always uses the ``pages`` rule; it cannot
    tell during page generation which content elements a page contains.
    With the default ``pages = dynamic`` the lifetime calculation therefore still queries
    the content tables through the scoping strategy.

.. _architecture-scheduler-task:

The scheduler task
------------------

``Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask`` is what drives
``processTransition()``.
Each run reads the last-run timestamp from TYPO3's ``Registry``
(namespace ``tx_temporalcache``, key ``scheduler_last_run``), asks
``findTransitionsInRange()`` for every transition since then, hands each one to the active
timing strategy, and writes the new timestamp back.
A transition that fails is logged and the run continues with the next one.

The interval is whatever frequency the task is given in the Scheduler module.

.. _architecture-context:

Workspace and language awareness
================================

All three scoping strategies read the current context through the trait
``Netresearch\TemporalCache\Service\Scoping\ResolvesContextAspects``:

.. code-block:: php
    :caption: Classes/Service/Scoping/ResolvesContextAspects.php

    $workspaceId = $context->getPropertyFromAspect('workspace', 'id', 0);
    $languageId = $context->getPropertyFromAspect('language', 'id', 0);

Both values are passed down into every query, so a workspace preview and each language
resolve their own next transition and therefore their own cache lifetime.
One frontend request carries one workspace and one language, so the number of queries does
not grow with the number of languages configured on the site.

.. _architecture-extensibility:

Extensibility
=============

.. _architecture-custom-tables:

Monitoring additional tables
----------------------------

Additional tables are registered with
``Netresearch\TemporalCache\Service\TemporalMonitorRegistry``.
The registry is a singleton and ``registerTable()`` is an instance method. Register from
:file:`ext_localconf.php`, which runs before any transition lookup:

.. code-block:: php
    :caption: EXT:my_extension/ext_localconf.php

    <?php

    defined('TYPO3') or die();

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \Netresearch\TemporalCache\Service\TemporalMonitorRegistry::class
    )->registerTable(
        'tx_news_domain_model_news',
        ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid']
    );

A registration performed in the constructor of a service of your own does not work
reliably: unless something the request instantiates references that service, Symfony
removes the definition when it compiles the container, and the registration never runs.
The failure is silent — the table is simply not monitored.

The second argument is optional.
Omitting it applies the default field list, which is the one shown above.

.. note::
    The registry has no field mapping: the table's temporal columns must literally be named
    ``starttime`` and ``endtime``.
    ``registerTable()`` throws an ``InvalidArgumentException`` when the field list omits
    ``uid``, ``starttime`` or ``endtime``, when the table name is empty, or when it is
    ``pages`` or ``tt_content`` — both are monitored by default and cannot be re-registered.

``TemporalContentRepository`` queries every table returned by
``TemporalMonitorRegistry::getAllTables()``, so each registered table adds two ``MIN()``
queries per lookup.
``getNextContentTransitionForPage()`` queries every registered table except ``pages``,
which requires those tables to carry a ``pid``.

.. _architecture-custom-listener:

Custom cache lifetime logic
---------------------------

.. note::
    ``TemporalCacheLifetime`` is ``final`` and cannot be extended.
    Register your own listener instead.

.. code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/CustomTemporalLogic.php

    namespace MyVendor\MyExtension\EventListener;

    use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

    final class CustomTemporalLogic
    {
        public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
        {
            $customTransition = $this->getCustomTransition($event->getPageId());

            if ($customTransition !== null) {
                $event->setCacheLifetime(
                    \min($event->getCacheLifetime(), \max(0, $customTransition - \time()))
                );
            }
        }

        private function getCustomTransition(int $pageId): ?int
        {
            return null;
        }
    }

.. code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    services:
      MyVendor\MyExtension\EventListener\CustomTemporalLogic:
        tags:
          - name: event.listener
            identifier: 'my-extension/custom-temporal-logic'
            event: TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent
            after: 'temporal-cache/modify-cache-lifetime'

.. _architecture-limitations:

Known limitations
=================

Only the page cache is addressed
   The listener sets the page cache lifetime.
   Other caches keep whatever lifetime their own code assigns.

Per-second granularity
   Transitions are Unix timestamps; nothing finer is possible.

No cross-page dependency detection with ``dynamic`` timing
   ``per-page`` scoping does not shorten a page's lifetime for content embedded from
   elsewhere, and ``per-content`` scoping only narrows flush tags.

Scheduler timing flushes only what the scoping strategy names
   With ``per-page`` or ``per-content`` scoping a page transition flushes only that page's
   own tag, so menus on other pages are not refreshed by the scheduler run.
   ``global`` scoping flushes the ``pages`` tag and does refresh them.

Additional tables get no indexes
   :file:`ext_tables.sql` covers ``pages`` and ``tt_content``.
   A registered table needs its own index on ``starttime`` and ``endtime``.

.. _architecture-next-steps:

Next steps
==========

- :ref:`performance-considerations` — configuration trade-offs
- :ref:`configuration` — every option in detail
- :ref:`phases` — where this approach sits relative to a core solution
- `Source code <https://github.com/netresearch/t3x-nr-temporal-cache>`__
- `Forge #14277 <https://forge.typo3.org/issues/14277>`__ — the core issue
