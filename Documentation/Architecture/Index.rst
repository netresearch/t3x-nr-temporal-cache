.. include:: /Includes.rst.txt

.. _architecture:

============
Architecture
============

Root Cause Analysis
===================

TYPO3's Cache Invalidation Paradigms
-------------------------------------

TYPO3's cache system supports two invalidation strategies:

**Event-Driven Invalidation:**
   Invalidate when data changes (page edited, deleted, moved).

   Example: Page is edited → Cache tagged with ``pageId_123`` is flushed.

**Tag-Based Invalidation:**
   Invalidate entries matching specific tags.

   Example: ``$cache->flushByTag('news_category_5')`` clears all news in category 5.

**Missing: Temporal Invalidation:**
   Invalidate at absolute timestamp (when time passes, not when data changes).

   Example: Cache should expire at ``2025-10-28 14:30:00`` when page's ``endtime`` arrives.

The Architectural Gap
---------------------

.. code-block:: text

   Current TYPO3 Cache API:
   ├─ Relative TTL: new CacheTag('tag', 3600)  ← Expires in 3600 seconds
   └─ Event-based: flushByTag('pageId_123')    ← Manual invalidation

   Missing Capability:
   └─ Absolute expiration: new CacheTag('tag', absoluteExpire: 1730124600)
                                                 ↑ Unix timestamp

Why This Matters
-----------------

Content rendering pipeline:

.. code-block:: php

   // Simplified TYPO3 rendering flow

   function renderPage($pageId) {
       // 1. Fetch ALL content elements
       $elements = getContentElements($pageId);

       // 2. Filter by starttime/endtime (snapshot at current time!)
       $visible = array_filter($elements, function($el) {
           return isVisible($el, time());  // ← Uses CURRENT time
       });

       // 3. Render filtered elements
       $output = renderElements($visible);

       // 4. CACHE the result with relative TTL
       $cache->set($key, $output, $tags, 3600);  // ← Fixed 3600s lifetime

       return $output;
   }

**Problem:** Visibility filtering happens at render time, then result is cached with
fixed lifetime. Cache doesn't know to expire when temporal conditions change.

How Phase 1 Solves This
========================

Dynamic Cache Lifetime Strategy
--------------------------------

Instead of fixed lifetime, calculate when next temporal transition will occur:

.. code-block:: php
   :caption: Illustrative pseudo-code - the real API is shown further down

   function calculateCacheLifetime(): int
   {
       $now = time();

       // Earliest future starttime/endtime across all monitored tables.
       // null when nothing is scheduled — the real API returns ?int.
       $nextTransition = findNextTransition($now);

       if ($nextTransition === null) {
           return $defaultLifetime;
       }

       // Cache until that moment
       return max(0, $nextTransition - $now);
   }

**Result:** Cache expires exactly when temporal state changes.

Implementation: PSR-14 Event
-----------------------------

TYPO3 v12+ provides ``ModifyCacheLifetimeForPageEvent`` (Feature-96879):

.. code-block:: php
   :caption: Classes/EventListener/TemporalCacheLifetime.php (condensed - error handling and debug logging omitted)

   namespace Netresearch\TemporalCache\EventListener;

   use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
   use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
   use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
   use Psr\Log\LoggerInterface;
   use TYPO3\CMS\Core\Context\Context;
   use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

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
               $event->setCacheLifetime(min($lifetime, $maxLifetime));
           }
       }
   }

The listener itself contains no queries. It delegates to the configured timing strategy,
which in turn asks the configured scoping strategy for the next transition. A ``null``
lifetime (scheduler timing) leaves TYPO3's own lifetime untouched.

**Registration:** ``Configuration/Services.yaml``

.. code-block:: yaml

   services:
     Netresearch\TemporalCache\EventListener\TemporalCacheLifetime:
       tags:
         - name: event.listener
           identifier: 'temporal-cache/modify-cache-lifetime'
           event: TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent

Temporal Transition Detection
------------------------------

All transition lookups live in ``Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository``
(contract: ``TemporalContentRepositoryInterface``). It exposes three entry points:

.. code-block:: php
   :caption: Classes/Domain/Repository/TemporalContentRepositoryInterface.php

   // Earliest transition across every monitored table (site-wide)
   public function getNextTransition(
       int $currentTimestamp,
       int $workspaceUid = 0,
       int $languageUid = 0
   ): ?int;

   // Earliest transition in the pages table only - page transitions change menus everywhere
   public function getNextPageTransition(
       int $currentTimestamp,
       int $workspaceUid = 0,
       int $languageUid = 0
   ): ?int;

   // Earliest content-element transition on one page (content tables, restricted by pid)
   public function getNextContentTransitionForPage(
       int $pageId,
       int $currentTimestamp,
       int $workspaceUid = 0,
       int $languageUid = 0
   ): ?int;

Each of them runs one indexed ``MIN()`` query per table and per field
(``starttime``, ``endtime``) and returns the smallest result. The default restrictions are
removed deliberately - TYPO3's ``StartTimeRestriction``/``EndTimeRestriction`` would hide
exactly the future records the lookup needs - and the deleted/hidden, workspace and
language filters are re-added explicitly.

The scoping strategy decides which of the three is used. Per-page scoping combines the
two narrow lookups:

.. code-block:: php
   :caption: Classes/Service/Scoping/PerPageScopingStrategy.php

   public function getNextTransition(Context $context, ?int $pageId = null): ?int
   {
       $workspaceId = $this->resolveWorkspaceId($context);
       $languageId = $this->resolveLanguageId($context);
       $now = \time();

       if ($pageId === null) {
           return $this->temporalContentRepository->getNextTransition($now, $workspaceId, $languageId);
       }

       $candidates = \array_filter([
           $this->temporalContentRepository->getNextPageTransition($now, $workspaceId, $languageId),
           $this->temporalContentRepository->getNextContentTransitionForPage($pageId, $now, $workspaceId, $languageId),
       ], static fn (?int $value): bool => $value !== null);

       return $candidates === [] ? null : \min($candidates);
   }

Global scoping calls ``getNextTransition()`` directly; per-content scoping resolves the
affected pages through ``sys_refindex`` first.

Timeline Example
================

Scenario
--------

- **09:00:** Page render, 3 content elements:

  - Element A: ``starttime = 10:00``
  - Element B: visible now, ``endtime = 12:00``
  - Element C: visible now, no restrictions

- **11:00:** Another page with ``starttime = 11:00``

Execution Flow
--------------

**09:00 - Initial Render:**

.. code-block:: text

   1. Query finds:
      - Page starttime: 11:00
      - Content A starttime: 10:00
      - Content B endtime: 12:00

   2. Calculate next transition:
      min(11:00, 10:00, 12:00) = 10:00

   3. Set cache lifetime:
      10:00 - 09:00 = 3600 seconds (1 hour)

   4. Render page:
      - Element A: Hidden (starttime not reached)
      - Element B: Visible
      - Element C: Visible

   5. Cache result until 10:00

**10:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds:
      - Page starttime: 11:00
      - Content B endtime: 12:00
      (Element A now visible, no future starttime)

   3. Calculate next transition:
      min(11:00, 12:00) = 11:00

   4. Set cache lifetime:
      11:00 - 10:00 = 3600 seconds

   5. Render page:
      - Element A: NOW VISIBLE ✅
      - Element B: Still visible
      - Element C: Visible

   6. Cache result until 11:00

**11:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds:
      - Content B endtime: 12:00
      (Page now visible in menus)

   3. Calculate next transition:
      min(12:00) = 12:00

   4. Set cache lifetime:
      12:00 - 11:00 = 3600 seconds

   5. Render page + update menus:
      - Page: NOW IN MENU ✅
      - Element A: Visible
      - Element B: Still visible
      - Element C: Visible

   6. Cache result until 12:00

**12:00 - Cache Expires (automatic):**

.. code-block:: text

   1. Cache miss triggers regeneration

   2. Query finds: No future transitions

   3. Set cache lifetime: Default (24 hours)

   4. Render page:
      - Element A: Visible
      - Element B: NOW HIDDEN ✅
      - Element C: Visible

   5. Cache for 24 hours (no more temporal changes)

**Result:** ✅ Fully automatic, zero manual intervention

Performance Analysis
====================

Query Cost
----------

Each cache regeneration executes:

.. code-block:: sql

   -- One MIN() query per monitored table and per temporal field.
   -- With the default tables that is four queries: pages/tt_content x starttime/endtime.

   SELECT MIN(starttime) FROM pages
   WHERE starttime > {now}
     AND deleted = 0 AND hidden = 0
     AND sys_language_uid = {language}
   -- workspace filter...

   SELECT MIN(endtime) FROM tt_content
   WHERE endtime > {now}
     AND deleted = 0 AND hidden = 0
     AND sys_language_uid = {language}
   -- workspace filter, plus "AND pid = {pageId}" for per-page/per-content scoping

**Indexes** (added by the extension via :file:`ext_tables.sql`):

- ``pages(starttime, sys_language_uid)``
- ``pages(endtime, sys_language_uid)``
- ``tt_content(starttime, sys_language_uid)``
- ``tt_content(endtime, sys_language_uid)``

**Measured Performance:**

.. list-table::
   :header-rows: 1
   :widths: 40 30 30

   * - Operation
     - Time
     - Notes
   * - Pages query
     - ~2-4ms
     - Indexed, aggregates only
   * - Content query
     - ~3-6ms
     - More rows, still indexed
   * - Calculation overhead
     - ~0.1ms
     - Array operations
   * - **Total per cache miss**
     - **~5-10ms**
     - One-time cost

Cache Hit Rate Impact
---------------------

Typical TYPO3 site:

- Cache hit rate: 95-99%
- Cache miss: 1-5% of requests

Effective overhead:

.. code-block:: text

   10ms (query) × 2% (miss rate) = 0.2ms average per page load

**Verdict:** ✅ Negligible performance impact

Comparison: Current Workarounds
--------------------------------

**Manual Clearing:**

- Editorial overhead: ~5-10 minutes per scheduled item
- Risk: Forgotten cache clears = broken content
- Cost: Developer time + broken user experience

**Cron Cache Clearing:**

- Server overhead: Clear ALL caches regularly
- Side effect: Destroys all cache performance
- Granularity: Limited by cron frequency

**No Caching:**

- Every request regenerates: ~50-200ms per page
- 100x slower than temporal cache solution

Context Awareness
=================

Workspace Support
-----------------

Extension respects TYPO3 workspace context:

.. code-block:: php

   $workspaceId = $this->context->getPropertyFromAspect('workspace', 'id');

   // Query includes workspace overlay records
   $qb->where(/* workspace-aware conditions */);

**Result:** Preview mode shows correct temporal behavior for workspace versions.

Language Support
----------------

Extension respects language context:

.. code-block:: php

   $languageId = $this->context->getPropertyFromAspect('language', 'id');

   $qb->where(
       $qb->expr()->eq('sys_language_uid', $languageId)
   );

**Result:** Each language has independent cache lifetimes based on translated content's temporal fields.

Extensibility
=============

Custom Tables
-------------

Additional tables are registered with
``Netresearch\TemporalCache\Service\TemporalMonitorRegistry``. The registry is an autowired
singleton service and ``registerTable()`` is an instance method, so obtain it through
constructor injection - there is no static registration API:

.. code-block:: php

   namespace YourVendor\YourExtension\Service;

   use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;

   final class NewsTemporalRegistration
   {
       public function __construct(
           private readonly TemporalMonitorRegistry $monitorRegistry
       ) {
           $this->monitorRegistry->registerTable(
               'tx_news_domain_model_news',
               ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid']
           );
       }
   }

The second argument lists the columns to select and is optional; omitting it applies the
default field list shown above.

.. note::
   The registry has no field mapping: the table's temporal columns must literally be named
   ``starttime`` and ``endtime``. ``registerTable()`` throws an ``InvalidArgumentException``
   when the field list omits ``uid``, ``starttime`` or ``endtime``, when the table name is
   empty, or when it is ``pages`` or ``tt_content`` - both are monitored by default and
   cannot be re-registered.

Registered tables are picked up by ``TemporalContentRepository``, which queries every table
returned by ``TemporalMonitorRegistry::getAllTables()`` when it looks for the next
transition.

Custom Transition Logic
------------------------

.. note::
   The TemporalCacheLifetime class is ``final`` and cannot be extended.
   Use custom event listeners instead.

For custom temporal logic, create your own PSR-14 event listener:

.. code-block:: php

   namespace YourVendor\YourExtension\EventListener;

   use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

   final class CustomTemporalLogic
   {
       public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
       {
           // Add your custom temporal checks
           $customTransition = $this->getCustomTransition();
           if ($customTransition) {
               $currentLifetime = $event->getCacheLifetime();
               $lifetime = min($currentLifetime, $customTransition - time());
               $event->setCacheLifetime($lifetime);
           }
       }

       private function getCustomTransition(): ?int
       {
           // Your custom logic here
           return null;
       }
   }

Register in ``Configuration/Services.yaml``:

.. code-block:: yaml

   services:
     YourVendor\YourExtension\EventListener\CustomTemporalLogic:
       tags:
         - name: event.listener
           identifier: 'your-extension/custom-temporal-logic'
           event: TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent
           after: 'temporal-cache/modify-cache-lifetime'

Limitations & Trade-offs
=========================

Current Limitations
-------------------

1. **Symptom Fix:**
   Solves problem within current architecture but doesn't fix root cause
   (missing absolute expiration in TYPO3 core).

2. **Recalculation:**
   Queries execute on every cache miss. Minimal overhead but not zero.

3. **Maximum Granularity:**
   Limited to per-second precision (Unix timestamps).

4. **Cross-Page Dependencies:**
   Doesn't detect when Page A's visibility affects Page B's content.

When Phase 2/3 Are Better
--------------------------

This extension becomes obsolete when TYPO3 core implements:

- **Phase 2:** Absolute expiration timestamps in ``CacheTag`` API
- **Phase 3:** Automatic temporal dependency detection

See :ref:`phases` for migration path.

Next Steps
==========

- :ref:`phases` - Future improvements and migration plan
- `Source Code <https://github.com/netresearch/t3x-nr-temporal-cache>`__ - Examine implementation
- `Forge #14277 <https://forge.typo3.org/issues/14277>`__ - Track core development
