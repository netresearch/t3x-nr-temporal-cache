.. include:: /Includes.rst.txt

.. _performance-faq:

================================
Frequently Asked Questions
================================

Q: Why does my entire site cache expire when one page has a future starttime?
=============================================================================

A: This is a Phase 1 architectural constraint when using **global scoping strategy**.

The default ``global`` scoping strategy queries all monitored tables site-wide, so a
transition anywhere shortens the cache lifetime of every page.

**Solution**: Switch to per-page or per-content scoping strategies. Both narrow the lookup
to the page being rendered, using the page ID that ``ModifyCacheLifetimeForPageEvent``
provides:

.. code-block:: php
   :caption: ext_localconf.php

   // Per-page scoping (95%+ reduction)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-page';

   // Per-content scoping (99.7% reduction)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-content';

Phase 2/3 (TYPO3 core integration) will enable native per-page scoping. See :ref:`phases`.

Q: Can I disable this for specific page trees?
===============================================

A: Not in v1.0.x. Configuration options are planned for v1.2.0.

**Workaround**: Register your own listener for the same event, ordered after this
extension's listener, and undo its lifetime for the page trees you want to exclude:

.. code-block:: php
   :caption: EXT:my_extension/Classes/EventListener/ConditionalTemporalCache.php

   namespace MyVendor\MyExtension\EventListener;

   use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

   final class ConditionalTemporalCache
   {
       /**
        * @param int[] $excludedPageIds pages that keep the long lifetime
        */
       public function __construct(private readonly array $excludedPageIds = [])
       {
       }

       public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
       {
           if ($this->isExcluded($event->getPageId())) {
               $event->setCacheLifetime(86400);
           }
       }

       private function isExcluded(int $pageId): bool
       {
           return in_array($pageId, $this->excludedPageIds, true);
       }
   }

.. code-block:: yaml
   :caption: EXT:my_extension/Configuration/Services.yaml

   services:
     MyVendor\MyExtension\EventListener\ConditionalTemporalCache:
       tags:
         - name: event.listener
           identifier: 'my-extension/conditional-temporal-cache'
           event: TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent
           after: 'temporal-cache/modify-cache-lifetime'

**Future**: Native configuration support in v1.2.0+.

Q: Will this work with my CDN/Varnish setup?
=============================================

A: Yes, but be aware of cache miss storms.

**CDN Behavior**:

- CDN respects ``Cache-Control`` headers from TYPO3
- When TYPO3 cache expires, CDN cache also expires
- ALL requests hit origin simultaneously = potential overload

**Mitigation**:

✅ Configure ``stale-while-revalidate``:

.. code-block:: apache
   :caption: Apache .htaccess

   Header set Cache-Control "public, max-age=3600, stale-while-revalidate=300"

✅ Enable origin rate limiting
✅ Use per-page/per-content scoping (reduces synchronized expiration)
✅ Implement cache warming

See :ref:`performance-limitations` for detailed mitigation strategies.

Q: Should I use this on a 50,000 page site?
============================================

A: Probably not with default configuration, unless:

✅ Very infrequent temporal transitions (<10/day)
✅ Robust infrastructure with cache warming
✅ Thorough staging testing completed
✅ Per-content scoping + scheduler timing configured
✅ All mitigation strategies implemented

**Recommendation**:

- Test thoroughly in staging with production-like data
- Monitor continuously in production
- Consider waiting for Phase 2/3 (TYPO3 core integration)

See :ref:`decision-guide` for site-specific recommendations.

Q: Does this affect backend performance?
=========================================

A: No, only frontend page cache generation is affected.

**Backend operations unchanged**:

- Page editing
- Content editing
- Backend module access
- Scheduler tasks
- CLI commands

**Frontend impact**:

- 4 database queries per page cache generation (dynamic timing)
- ~5-20ms overhead per cache miss
- Zero overhead with scheduler timing

Q: What if I don't use temporal content?
=========================================

A: The extension has minimal impact - queries return null, default cache lifetime is used.

**However**: There's no benefit to installing it if you don't use starttime/endtime.

**Uninstall if**:

- No pages use starttime/endtime
- No content elements use starttime/endtime
- No custom records use temporal fields

Q: How do I monitor extension performance?
===========================================

A: Track these metrics:

**Cache Hit Ratio**:

.. code-block:: bash

   # TYPO3 Admin Panel → Cache
   # Monitor: cache_hits / (cache_hits + cache_misses)

**Database Query Performance**:

.. code-block:: sql

   -- Enable slow query log
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 0.05; -- 50ms threshold

   -- Check for temporal cache queries
   SELECT * FROM mysql.slow_log
   WHERE sql_text LIKE '%starttime%' OR sql_text LIKE '%endtime%';

**Origin Request Rate** (with CDN):

- Monitor CDN analytics for request spikes
- Correlate with cache expiration times
- Alert on spikes >10x baseline

**Page Generation Time**:

- Use APM tools (New Relic, Datadog)
- Compare before/after extension installation
- Acceptable increase: <10ms per page

See :ref:`performance-limitations` for complete monitoring guide.

Q: Can I use this with EXT:warming?
====================================

A: Yes, and it's recommended for large sites!

**Setup**:

1. Install warming extension:

.. code-block:: bash

   composer req typo3/cms-warming

2. Configure warming task:

.. code-block:: php
   :caption: ext_localconf.php

   use TYPO3\CMS\Warming\Task\WarmTask;

   // Configure warming to run before cache expiration
   $task = new WarmTask();
   $task->setInterval(3600); // Run every hour

3. Monitor next expiration:

.. code-block:: bash

   # Warm cache 5 minutes before expiration
   # Based on extension's next transition timestamp

**Result**: Proactive cache warming eliminates user-facing cache misses.

Q: What happens during high-traffic cache expiration?
======================================================

A: Potential "thundering herd" problem with global scoping.

**Scenario**:

::

   10:00 AM: ALL site caches expire (10,000 pages)
   10:00:01: First 100 concurrent requests hit origin
   10:00:02: All requests regenerate simultaneously
   Result: Server load spike

**Mitigation**:

✅ Per-page/per-content scoping (eliminates synchronized expiration)
✅ Stale-while-revalidate (serves stale content during regeneration)
✅ Origin rate limiting (prevents overload)
✅ Cache warming (proactive regeneration)

Q: How does this work with workspaces?
=======================================

A: Extension respects workspace context automatically.

**Behavior**:

- Queries are workspace-aware
- Preview mode shows correct temporal behavior
- Live workspace and preview workspaces have independent cache lifetimes
- No configuration needed

**Example**:

::

   Live workspace: Page has starttime = 10:00 AM
   Draft workspace: Same page edited, starttime = 11:00 AM

   Result:
   - Live workspace cache expires at 10:00 AM
   - Draft workspace cache expires at 11:00 AM
   - Independent cache lifetimes ✅

Q: Can I combine this with custom cache tags?
==============================================

A: Yes, extension works alongside custom cache tagging.

**Extension responsibility**: Set cache lifetime
**Your responsibility**: Cache tagging and invalidation logic

**Example**:

.. code-block:: php

   // Your code: Custom cache tags
   $cacheManager->flushByTag('news_category_5');

   // Extension: Sets cache lifetime
   $event->setCacheLifetime($nextTransition - time());

   // Result: Both work together ✅

Q: What's the performance impact of harmonization?
===================================================

A: Harmonization REDUCES performance impact significantly.

**Without harmonization**:

- 500 scheduled items/day = 500 cache invalidations
- Constant cache churn

**With harmonization** (4 slots/day):

- 500 items → grouped to 4 time slots
- 4 cache invalidations/day
- **99.2% reduction** in cache churn

**Overhead**: Near-zero (simple time rounding calculation)

See :ref:`performance-strategies` for configuration.

Q: Does this work with multi-language sites?
=============================================

A: Yes, but query overhead multiplies by language count.

**Impact**:

- 1 language: 4 queries per cache generation
- 5 languages: 20 queries per cache generation
- 10 languages: 40 queries per cache generation

**Mitigation**:

✅ Use scheduler timing (eliminates per-page overhead)
✅ Database query caching (reduces redundant queries)
✅ Per-language cache isolation (automatic)

Q: When will Phase 2/3 be available?
=====================================

A: Tentative timeline (subject to change):

**Phase 2** (Absolute Expiration API):

- RFC discussion: 2025
- Implementation: 2025-2026
- TYPO3 LTS inclusion: v15 or v16 (2026-2027)

**Phase 3** (Automatic Detection):

- Research phase: Post-Phase 2
- Timeline: 2027+ (long-term vision)

See :ref:`phases` for complete roadmap and migration path.

**What this means**:

- Extension remains necessary for 2-5+ years
- Migration path will be provided
- Extension will become obsolete when Phase 2/3 are stable

Next Steps
==========

- :ref:`performance-strategies` - Optimization approaches
- :ref:`performance-limitations` - Understand constraints
- :ref:`decision-guide` - Site-specific recommendations
- :ref:`phases` - Future improvements roadmap
