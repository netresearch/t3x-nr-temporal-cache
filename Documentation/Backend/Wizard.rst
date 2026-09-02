.. include:: /Includes.rst.txt

.. _backend-wizard:

====================
Configuration Wizard
====================

Guided configuration setup with presets and performance impact calculator.

Wizard Overview
===============

The Configuration Wizard helps you:

- Select optimal configuration for your site size
- Choose from predefined presets
- Calculate performance impact
- Test configuration before applying
- Export configuration as PHP code

Wizard Steps
============

Step 1: Site Profile
---------------------

Select your site characteristics:

**Small Site**
   - <1,000 pages
   - <10 temporal transitions/day
   - Single or few languages
   - Recommended: Default configuration

**Medium Site**
   - 1,000-10,000 pages
   - 10-50 transitions/day
   - Multi-language possible
   - Recommended: Per-page scoping + Harmonization

**Large Site**
   - >10,000 pages
   - >50 transitions/day
   - Multi-language common
   - Recommended: Per-content scoping + Scheduler + Harmonization

**Custom**
   - Manual configuration
   - Advanced users only

Step 2: Strategy Selection
---------------------------

Choose optimization strategies:

**Scoping Strategy**:

- Global (simple, all pages affected)
- Per-Page (95% reduction)
- Per-Content (99.7% reduction)

**Timing Strategy**:

- Dynamic (immediate, 5-20ms overhead)
- Scheduler (zero overhead, 1-minute delay)
- Hybrid (mixed per table)

**Harmonization**:

- Disabled (precise timing)
- Enabled (reduced cache churn)

Step 3: Performance Calculator
-------------------------------

Input your metrics:

.. code-block:: text

   Site Metrics:
   - Number of pages: 5,000
   - Pageviews/day: 100,000
   - Temporal transitions/day: 25

   Calculated Impact:
   - Cache invalidations/day: 7,500 (global) → 375 (per-page) → 75 (per-content)
   - With harmonization: 75 → 8 (grouping to 8 time slots)
   - Overhead: 15ms per cache miss (dynamic) or 0ms (scheduler)

   Recommendation: Per-content + Scheduler + Harmonization

Step 4: Preview Configuration
------------------------------

Review generated configuration:

.. code-block:: php
   :caption: Generated Configuration

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
       'timing' => ['strategy' => 'scheduler', 'scheduler_interval' => 60],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 300,
       ],
   ];

**Actions**:

- Test Configuration (simulates behavior)
- Export as PHP (download file)
- Apply Configuration (updates extension settings)

Step 5: Testing
---------------

Test configuration without applying:

**Test Results**:

.. code-block:: text

   Simulation Results (based on current temporal content):

   Next 24 Hours:
   - Transitions: 8 (down from 25 with harmonization)
   - Cache invalidations: 40 pages (down from 5,000 with per-content)
   - Estimated cache hit ratio: 82% (vs 35% with global scoping)

   Query Performance:
   - Database queries: 0ms (scheduler timing)
   - Background task frequency: Every 60 seconds

   Overall Assessment: ✅ Recommended for your site

**Interpretation**:

- Green: Optimal configuration
- Yellow: Acceptable with monitoring
- Red: Not recommended, revise settings

Configuration Presets
======================

Small Site Preset
-----------------

**Configuration**:

.. code-block:: php

   'scoping' => ['strategy' => 'global'],
   'timing' => ['strategy' => 'dynamic'],
   'harmonization' => ['enabled' => false],

**Characteristics**:

- Zero configuration complexity
- Minimal performance impact
- Suitable for <1,000 pages

Medium Site Preset
------------------

**Configuration**:

.. code-block:: php

   'scoping' => ['strategy' => 'per-page'],
   'timing' => ['strategy' => 'dynamic'],
   'harmonization' => [
       'enabled' => true,
       'slots' => '00:00,06:00,12:00,18:00', // Every 6 hours
   ],

**Characteristics**:

- Balanced accuracy and performance
- 95-98% reduction in cache churn
- Suitable for 1,000-10,000 pages

Large Site Preset
-----------------

**Configuration**:

.. code-block:: php

   'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
   'timing' => ['strategy' => 'scheduler', 'scheduler_interval' => 60],
   'harmonization' => [
       'enabled' => true,
       'slots' => '00:00,04:00,08:00,12:00,16:00,20:00', // Every 4 hours
   ],

**Characteristics**:

- Maximum efficiency
- 99.7% reduction + zero per-page overhead
- Suitable for >10,000 pages

High-Traffic Preset
-------------------

**Configuration**:

.. code-block:: php

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
       'slots' => '00:00,04:00,08:00,12:00,16:00,20:00',
   ],

**Characteristics**:

- Real-time menu updates + background content processing
- Optimized for >1M pageviews/day
- Balances accuracy and performance

Performance Impact Calculator
==============================

Input Fields
------------

**Site Metrics**:

- Number of pages
- Number of languages
- Pageviews per day
- Temporal transitions per day
- Cache hit ratio baseline (optional)

**Current Configuration** (optional):

- Pre-fill with current settings
- Compare impact of changes

Calculated Metrics
------------------

**Cache Invalidations**:

.. code-block:: text

   Global Scoping:
   - Per transition: All 5,000 pages invalidated
   - Daily: 25 transitions × 5,000 = 125,000 invalidations

   Per-Page Scoping:
   - Per transition: 1 page invalidated
   - Daily: 25 transitions × 1 = 25 invalidations
   - Reduction: 99.98%

   With Harmonization (8 slots):
   - Transitions: 25 → 8
   - Invalidations: 25 → 8
   - Reduction: 68% additional

**Query Overhead**:

.. code-block:: text

   Dynamic Timing:
   - Per cache miss: 15ms (4 queries × 3.75ms avg)
   - Daily (at 70% cache hit ratio): 30,000 × 15ms = 450 seconds
   - Impact: Noticeable but manageable

   Scheduler Timing:
   - Per cache miss: 0ms
   - Background: 60-second intervals
   - Impact: Minimal

**Cache Hit Ratio**:

.. code-block:: text

   Baseline: 90%
   With extension (global): 35% (frequent site-wide invalidations)
   With optimization: 82% (targeted invalidations + harmonization)

Comparison View
---------------

Side-by-side comparison:

.. list-table::
   :header-rows: 1
   :widths: 30 35 35

   * - Metric
     - Current
     - Proposed
   * - Cache invalidations/day
     - 125,000
     - 8
   * - Query overhead
     - 15ms per miss
     - 0ms
   * - Cache hit ratio
     - 35%
     - 82%
   * - Assessment
     - ⚠️ High impact
     - ✅ Optimized

Applying Configuration
=======================

Apply Button
------------

Applies configuration to extension settings.

**Process**:

1. Validates configuration
2. Backs up current settings
3. Applies new settings
4. Flushes cache
5. Confirms success

**Confirmation Dialog**:

.. code-block:: text

   Apply Configuration?

   This will:
   - Update extension settings
   - Flush all page caches
   - Restart scheduler tasks (if timing changes)

   Current settings will be backed up and can be restored.

   [Cancel]  [Apply]

Export as PHP
-------------

Downloads configuration as PHP code for manual installation.

**Use Cases**:

- Version control configuration
- Deploy to multiple environments
- Share configuration with team
- Document setup

**Generated File**:

.. code-block:: php
   :caption: temporal_cache_config.php

   <?php
   // Generated by TYPO3 Temporal Cache Configuration Wizard
   // Date: 2025-10-30 12:00:00

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => ['strategy' => 'per-content'],
       'timing' => ['strategy' => 'scheduler'],
       // ... full configuration
   ];

Test Configuration
------------------

Simulates configuration without applying.

**Test Process**:

1. Analyzes current temporal content
2. Simulates cache behavior with proposed settings
3. Calculates projected metrics
4. Provides recommendation

**Test Report**:

- Projected cache invalidations
- Estimated cache hit ratio
- Query performance impact
- Overall recommendation (green/yellow/red)

Next Steps
==========

- :ref:`configuration` - Detailed configuration reference
- :ref:`backend-dashboard` - Monitor after configuration
- :ref:`performance-considerations` - Understand trade-offs
- :ref:`backend-tips` - Optimization recommendations
