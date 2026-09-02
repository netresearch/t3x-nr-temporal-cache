.. include:: /Includes.rst.txt

.. _performance-strategies:

============================
Optimization Strategies
============================

The extension provides three complementary optimization approaches that can be combined
to achieve up to 99.975% reduction in cache churn:

1. **Scoping Strategies** - Control which caches are invalidated
2. **Timing Strategies** - Control when transition checks occur
3. **Time Harmonization** - Group transitions to reduce cache churn

Scoping Strategies
==================

Control which pages are affected by temporal transitions.

Global Scoping (Default)
-------------------------

**Behavior**: Invalidates all page caches on transitions

**Characteristics**:

✅ Simple, zero configuration
✅ Works immediately after installation
✅ Suitable for small sites
❌ All pages expire together
❌ Reduced cache hit ratio

**Best For**: Small sites (<1,000 pages)

**Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'global';

Per-Page Scoping
----------------

**Behavior**: Invalidates only the affected page

**Characteristics**:

✅ **95%+ reduction** in cache invalidations
✅ Only pages with temporal content are affected
✅ Better cache hit ratio
⚠️ Requires page-level temporal tracking
⚠️ Slightly more complex configuration

**Best For**: Medium sites (1,000-10,000 pages)

**Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-page';

**How It Works**:

The extension tracks which pages contain temporal content and only invalidates those specific
page caches when transitions occur.

Per-Content Scoping
-------------------

**Behavior**: Finds all pages containing temporal content via refindex

**Characteristics**:

✅ **99.7% reduction** in cache invalidations
✅ Maximum precision - only affected pages
✅ Uses TYPO3 reference index
⚠️ Requires refindex to be up-to-date
⚠️ Additional refindex queries

**Best For**: Large sites (>10,000 pages)

**Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-content';

**How It Works**:

Uses TYPO3's reference index (sys_refindex) to find which pages reference temporal content
elements, then invalidates only those specific pages.

**Important**: Keep refindex updated:

.. code-block:: bash

   # Update reference index regularly
   php vendor/bin/typo3 referenceindex:update

Scoping Strategy Comparison
----------------------------

.. list-table::
   :header-rows: 1
   :widths: 20 20 20 20 20

   * - Strategy
     - Cache Invalidations
     - Setup Complexity
     - Requirements
     - Best Use Case
   * - **Global**
     - All pages
     - ✅ Simple
     - None
     - <1,000 pages
   * - **Per-Page**
     - Affected page only
     - ⚠️ Medium
     - Page tracking
     - 1,000-10,000 pages
   * - **Per-Content**
     - Affected pages only
     - ⚠️ Medium
     - Updated refindex
     - >10,000 pages

Timing Strategies
=================

Control when temporal transition checks occur.

Dynamic Timing (Default)
-------------------------

**Behavior**: Checks on every page cache generation

**Characteristics**:

✅ Immediate response to transitions
✅ Zero configuration
✅ Works out of the box
❌ 4 database queries per page cache generation (~5-20ms)
❌ Overhead on every cache miss

**Best For**: Sites prioritizing temporal accuracy

**Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy'] = 'dynamic';

**Query Performance**:

- Pages query: ~2-4ms
- Content query: ~3-6ms
- Calculation overhead: ~0.1ms
- **Total**: ~5-10ms per cache generation

Scheduler Timing
----------------

**Behavior**: Background processing via TYPO3 Scheduler

**Characteristics**:

✅ **Zero per-page overhead**
✅ No queries during page rendering
✅ Predictable resource usage
⚠️ Slight delay (typically 1 minute)
⚠️ Requires TYPO3 Scheduler configured

**Best For**: High-traffic sites

**Configuration**:

1. Enable scheduler timing:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy'] = 'scheduler';

2. Add the "Temporal Cache" task (``Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask``)
   in the Scheduler backend module and note the task UID it receives.

3. Let cron run the Scheduler:

.. code-block:: text
   :caption: Crontab

   * * * * * php /path/to/typo3/vendor/bin/typo3 scheduler:run

.. note::
   ``scheduler:run`` executes every task that is due. To run only this one, pass its UID:
   ``scheduler:run --task=<uid>``. There is no dedicated console command for the transition
   check - the extension's own commands are ``temporalcache:analyze``,
   ``temporalcache:verify``, ``temporalcache:harmonize`` and ``temporalcache:list``.

**Recommendation**: Run every 1 minute for best balance between accuracy and performance.

Hybrid Timing
-------------

**Behavior**: Different timing for page transitions and content-element transitions

**Characteristics**:

✅ Optimizes for both accuracy and performance
✅ Two independent switches: pages and content
⚠️ More complex setup
⚠️ Requires careful planning

**Best For**: Sites with mixed requirements

**Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy'] = 'hybrid';

   // Dynamic for pages (immediate menu updates)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['pages'] = 'dynamic';

   // Scheduler for content elements (acceptable delay)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['content'] = 'scheduler';

.. note::
   Hybrid timing has exactly these two switches, ``timing.hybrid.pages`` and
   ``timing.hybrid.content``; both accept ``dynamic`` or ``scheduler``. There is no
   per-table timing setting - records are classified as ``page`` (table ``pages``) or
   ``content`` (every other monitored table).

Timing Strategy Comparison
---------------------------

.. list-table::
   :header-rows: 1
   :widths: 20 20 20 20 20

   * - Strategy
     - Per-Page Overhead
     - Response Time
     - Setup Complexity
     - Best Use Case
   * - **Dynamic**
     - ~5-10ms
     - Immediate
     - ✅ Simple
     - Accuracy priority
   * - **Scheduler**
     - 0ms
     - ~1 min delay
     - ⚠️ Medium
     - High traffic
   * - **Hybrid**
     - Mixed
     - Mixed
     - ⚠️ Complex
     - Mixed requirements

Time Harmonization
==================

Reduce cache churn by grouping temporal transitions to fixed time slots.

How It Works
------------

**Without Harmonization**:

::

   Scheduled content:
   - Article 1: starttime = 05:43:17
   - Article 2: starttime = 06:18:42
   - Article 3: starttime = 11:27:09
   - Article 4: starttime = 12:31:55

   Result: 4 separate cache invalidations throughout the day

**With Harmonization** (slots ``00:00,06:00,12:00,18:00``, tolerance 3600):

::

   Harmonized times:
   - Articles 1+2: Grouped to 06:00:00
   - Articles 3+4: Grouped to 12:00:00

   Result: 2 cache invalidations (at 06:00 and 12:00)
   Reduction: 50%

Each timestamp is moved to its *nearest* slot, and only when the distance to that slot is
at most ``harmonization.tolerance`` seconds. Transitions further away than the tolerance
are left untouched.

Real-World Impact
-----------------

**500 scheduled items/day** spread across the day:

**Without harmonization**: 500 cache invalidations/day

**With harmonization** (6-hour slots: 00:00, 06:00, 12:00, 18:00): 4 cache invalidations/day

**Reduction**: 99.2% (500 → 4)

Configuration
-------------

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   // Enable time harmonization
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['enabled'] = true;

   // Time slots: comma-separated HH:MM values in a single string
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['slots']
       = '00:00,06:00,12:00,18:00';

   // Tolerance (seconds) - don't shift if already close to slot
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['tolerance'] = 300; // 5 minutes

Tolerance Setting
-----------------

The tolerance is the **maximum shift** harmonization may apply. A transition is only moved
when its nearest slot is at most that many seconds away:

.. code-block:: text

   Slot at 12:00:00, tolerance = 300 seconds (5 minutes)

   Input time: 11:56:00 → 4 min from the slot → SHIFTED → Becomes 12:00:00
   Input time: 12:03:00 → 3 min from the slot → SHIFTED → Becomes 12:00:00
   Input time: 11:50:00 → 10 min from the slot → beyond tolerance → Stays 11:50:00

**Recommendation**: A small tolerance keeps publication times close to what editors entered
but harmonizes few transitions; a large one (the default is 3600) harmonizes more. Setting
it to ``0`` disables shifting entirely - it does not mean "no limit".

Best Practices
--------------

**DO:**

✅ Use 4-6 time slots per day (every 4-6 hours)
✅ Align slots with content publication schedules
✅ Set reasonable tolerance (5-10 minutes)
✅ Monitor actual transition times vs harmonized times

**DON'T:**

❌ Use too many slots (defeats the purpose)
❌ Set zero tolerance (nothing is harmonized at all)
❌ Use harmonization for real-time content (news breaking, live events)
❌ Forget to communicate harmonization to editors

Combined Strategy Example
==========================

**Large News Site** (15,000 pages, 100 scheduled articles/day)

**Optimized Configuration**:

.. code-block:: php
   :caption: ext_localconf.php or config/system/additional.php

   // Scoping: Per-content (99.7% reduction in affected pages)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping'] = [
       'strategy' => 'per-content',
       'use_refindex' => true,
   ];

   // Timing: Scheduler (zero per-page overhead)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing'] = [
       'strategy' => 'scheduler',
       'scheduler_interval' => 60,
   ];

   // Harmonization: 4 daily slots (99.2% reduction in transitions)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization'] = [
       'enabled' => true,
       'slots' => '00:00,06:00,12:00,18:00',
       'tolerance' => 300,
   ];

**Results**:

- Cache invalidations: 600/day → 12/day (99% reduction)
- Per-page overhead: 15ms → 0ms
- Cache hit ratio: 30% → 85%
- **Combined improvement**: 99.975% reduction in cache churn

See :ref:`configuration` for complete configuration reference.

Next Steps
==========

- :ref:`performance-limitations` - Understand Phase 1 constraints
- :ref:`decision-guide` - Site-specific recommendations
- :ref:`configuration` - Detailed configuration options
- :ref:`phases` - Future improvements roadmap
