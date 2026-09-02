.. include:: /Includes.rst.txt

.. _decision-guide:

================================
Site-Specific Decision Guide
================================

.. important::
   The extension provides flexible strategies for different site sizes and can be used
   effectively on sites of any size with appropriate configuration.

Decision Matrix by Site Size
============================

Small Sites (<1,000 pages)
---------------------------

✅ **Recommended Configuration**: Default (Global + Dynamic)

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'global'],
       'timing' => ['strategy' => 'dynamic'],
       'harmonization' => ['enabled' => false],
   ];

**Why**:

- Zero configuration required
- Simple and reliable
- Minimal temporal content = minimal impact
- Performance overhead negligible

**Expected Impact**:

- Cache invalidations: Low frequency (matches temporal content)
- Overhead: 5-20ms per page cache
- Overall: Negligible impact

**Verdict**: ✅ Safe to install

Medium Sites (1,000-10,000 pages)
----------------------------------

✅ **Recommended Configuration**: Per-Page + Dynamic + Harmonization

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-page'],
       'timing' => ['strategy' => 'dynamic'],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 300,
       ],
   ];

**Why**:

- 95%+ reduction in cache invalidations (per-page scoping)
- 98%+ reduction in transitions (harmonization)
- Real-time updates (dynamic timing)
- Balanced performance/accuracy trade-off

**Expected Impact**:

- Cache invalidations: Targeted (affected pages only)
- Overhead: 5-20ms per page cache
- Overall: Minimal impact with major efficiency gains

**Verdict**: ⚠️ Test thoroughly, monitor closely

Large Sites (>10,000 pages)
----------------------------

✅ **Recommended Configuration**: Per-Content + Scheduler + Harmonization

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
       'timing' => ['strategy' => 'scheduler', 'scheduler_interval' => 60],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 300,
       ],
   ];

**Why**:

- 99.7% reduction in cache invalidations (per-content scoping)
- Zero per-page overhead (scheduler timing)
- 98%+ reduction in transitions (harmonization)
- Maximum efficiency

**Expected Impact**:

- Cache invalidations: Minimal (only affected pages)
- Overhead: 0ms per page (background processing)
- Overall: Excellent performance, suitable for large sites

**Requires**: Scheduler task setup (see :ref:`configuration`)

**Verdict**: ⚠️ Test extensively, implement all mitigations

High-Traffic Sites (>10M pageviews/month)
------------------------------------------

✅ **Recommended Configuration**: Per-Content + Hybrid + Harmonization

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
       'timing' => [
           'strategy' => 'hybrid',
           'hybrid' => [
               'pages' => 'dynamic',
               'content' => 'scheduler',
           ],
       ],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,04:00,08:00,12:00,16:00,20:00', // Every 4 hours
           'tolerance' => 300,
       ],
   ];

**Why**:

- Real-time menu updates (dynamic for pages)
- Zero overhead for content (scheduler for content elements)
- Frequent harmonization slots (every 4 hours)
- Optimized for both accuracy and performance

**Expected Impact**:

- Cache invalidations: Minimal and targeted
- Overhead: 5-20ms for pages only (content has zero overhead)
- Overall: Optimal for high-traffic scenarios

**Verdict**: ⚠️ Evaluate carefully, robust infrastructure required

Multi-Language Sites
--------------------

⚠️ **Special Considerations**:

Query overhead multiplies by number of languages with dynamic timing.

✅ **Recommended**: Scheduler or Hybrid timing

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-content'],
       'timing' => ['strategy' => 'scheduler'],
       'harmonization' => ['enabled' => true],
   ];

**Why**:

- Eliminates per-page query overhead across all languages
- Per-language isolation (automatic)
- Background processing more efficient

When NOT to Use This Extension
-------------------------------

❌ Consider alternatives if:

- No temporal content is used (no benefit from extension)
- Content can tolerate manual cache clearing
- Site has >50,000 pages AND >100 transitions/day
- Waiting for Phase 2/3 TYPO3 core integration is acceptable

See :ref:`performance-alternatives` for other solutions.

Real-World Impact Scenarios
============================

Scenario 1: Corporate Website (Low Impact)
-------------------------------------------

**Profile**:

- 100 pages, single language
- 2-3 pages scheduled per month
- Default cache: 24 hours

**With Extension**:

- Minimal change (occasional expiration)
- Cache hit ratio: 90% → 88%
- Query load: Negligible

**Verdict**: ✅ Safe to use

Scenario 2: News Portal (Medium Impact)
---------------------------------------

**Profile**:

- 500 pages, 5 languages = 2,500 page variants
- 20 articles scheduled daily (every 1-2 hours)
- Current cache: 24-hour default

**With Extension**:

- Cache expires every 1-2 hours
- Cache hit ratio: 90% → 40%
- Query load: 10,000 queries per cache rebuild

**With Mitigation** (indexing + warming + harmonization):

- Query time: 5ms → 2ms per query
- Cache warming reduces user-facing impact
- Cache hit ratio stabilizes: 60-70%

**Verdict**: ⚠️ Acceptable with proper infrastructure

Scenario 3: Enterprise Portal (High Impact)
-------------------------------------------

**Profile**:

- 10,000 pages, 10 languages = 100,000 page variants
- 100+ elements scheduled across departments daily
- Multiple independent content teams

**With Extension**:

- Cache expires every 10-30 minutes
- Cache hit ratio: 90% → 30%
- Query load: 400,000 queries per rebuild
- Constant cache churn

**Verdict**: ❌ DO NOT USE - needs Phase 2 solution

→ **Recommendation**: Wait for Phase 2/3 or custom solution

Decision Formula
================

**Step 1: Calculate Your Metrics**

.. code-block:: text

   A = Requests_per_day
   B = Temporal_transitions_per_day
   C = Number_of_pages
   D = Page_regeneration_time (ms)

**Step 2: Estimate Cache Impact**

.. code-block:: text

   Cache_invalidations_per_day = B × C (with global scoping)
   Cache_invalidations_per_day = B × 0.05 × C (with per-page scoping)
   Cache_invalidations_per_day = B × 0.003 × C (with per-content scoping)

**Step 3: Apply Decision Logic**

.. code-block:: text

   if (C < 1000 && B < 10) {
       ✅ Safe to install (default configuration)
   } else if (C < 10000 && B < 50 && can_implement_mitigations) {
       ⚠️ Test thoroughly, optimize configuration
   } else if (C < 50000 && B < 100 && robust_infrastructure) {
       ⚠️ Evaluate carefully, implement ALL mitigations
   } else {
       ❌ Wait for Phase 2/3 or use manual clearing
   }

Testing Checklist
=================

Before Production Deployment
----------------------------

**Development Testing**:

.. code-block:: text

   □ Install extension in development
   □ Enable debug logging
   □ Measure baseline performance (without extension)
   □ Install extension and measure impact
   □ Verify temporal transitions work correctly
   □ Check database query performance (<5ms per query)

**Staging Testing**:

.. code-block:: text

   □ Deploy to staging with production-like data
   □ Run synthetic load tests
   □ Monitor cache hit ratio
   □ Test cache miss storms (trigger transition under load)
   □ Validate CDN behavior (if applicable)
   □ Test across all languages (if multi-language)

**Production Rollout**:

.. code-block:: text

   □ Enable for preview workspace first
   □ Monitor for 1 week
   □ Validate performance is acceptable
   □ Enable for live workspace
   □ Monitor continuously for first month

See :ref:`performance-limitations` for mitigation strategies.

Next Steps
==========

- :ref:`performance-strategies` - Optimization approaches
- :ref:`performance-limitations` - Understand constraints
- :ref:`performance-alternatives` - Explore alternatives
- :ref:`configuration` - Complete configuration reference
