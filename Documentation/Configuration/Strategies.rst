.. include:: /Includes.rst.txt

.. _configuration-strategies:

========================
Optimization Strategies
========================

Configure scoping, timing, and harmonization strategies to optimize cache performance.

.. _configuration-scoping:

Scoping Strategy
================

Controls which caches are invalidated when temporal transitions occur.

.. confval:: scoping.strategy

   :type: string
   :Default: ``global``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy']

   Choose cache invalidation scope.

   **Options:**

   ``global``
      Invalidates all page caches on every temporal transition.

      - **Zero configuration** required
      - **Simple** and reliable
      - **Default strategy**
      - **Best for**: Small sites (<1,000 pages)
      - **Impact**: High cache churn on large sites

   ``per-page``
      Invalidates only the affected page.

      - **Targeted invalidation** reduces cache churn
      - **95%+ reduction** in cache invalidations
      - **Best for**: Medium sites (1,000-10,000 pages)
      - **Impact**: Minimal, proportional to affected pages

   ``per-content``
      Finds all pages containing temporal content via sys_refindex.

      - **Maximum efficiency** - only affected pages
      - **99.7% reduction** in cache invalidations
      - **Best for**: Large sites (>10,000 pages)
      - **Requires**: sys_refindex enabled (use_refindex = 1)
      - **Impact**: Minimal, most efficient approach

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration
      scoping.strategy = per-content

.. confval:: scoping.use_refindex

   :type: boolean
   :Default: ``true``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['use_refindex']

   Enable sys_refindex for accurate content element tracking.

   **When enabled:**

   - Per-content strategy uses refindex to find all pages containing temporal content
   - Accurate tracking of content element usage across pages
   - Required for per-content strategy to work correctly

   **When disabled:**

   - Per-content strategy falls back to per-page behavior
   - May miss pages with content elements in non-standard locations
   - Not recommended except for debugging

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration
      scoping.use_refindex = 1

.. _configuration-timing:

Timing Strategy
===============

Controls when the extension checks for temporal transitions.

.. confval:: timing.strategy

   :type: string
   :Default: ``dynamic``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy']

   Choose when to check for transitions.

   **Options:**

   ``dynamic``
      Event-based checking on every page cache generation.

      - **Immediate response** to transitions
      - **Real-time accuracy**
      - **Overhead**: 4 database queries per page cache (~5-20ms with indexes)
      - **Best for**: Sites requiring immediate updates
      - **Default timing strategy**

   ``scheduler``
      Background processing via TYPO3 Scheduler task.

      - **Zero per-page overhead** (no queries during page generation)
      - **Background processing** at configured intervals
      - **Requires**: Scheduler task setup (see :ref:`scheduler-setup`)
      - **Best for**: High-traffic sites
      - **Slight delay**: Updates only as often as scheduler runs (typically 1 minute)

   ``hybrid``
      Configure different timing strategies per content type.

      - **Flexible**: Dynamic for pages, Scheduler for content
      - **Best of both worlds**: Real-time menus, background content updates
      - **Configuration**: Use ``timing.hybrid.pages`` and ``timing.hybrid.content``
      - **Best for**: Complex requirements

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration

      # Use scheduler for zero overhead
      timing.strategy = scheduler

      # Or hybrid for flexibility
      timing.strategy = hybrid
      timing.hybrid.pages = dynamic
      timing.hybrid.content = scheduler

.. confval:: timing.scheduler_interval

   :type: integer
   :Default: ``60``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['scheduler_interval']

   Check interval in seconds for scheduler-based timing strategy.

   **Guidelines:**

   - **Minimum**: 60 seconds (enforced)
   - **Default**: 60 seconds (recommended)
   - **High-traffic sites**: 60-120 seconds
   - **Low-frequency content**: 300-600 seconds

   **Impact:**

   - Lower interval = More accurate transitions, higher scheduler overhead
   - Higher interval = Less overhead, potential delay in transitions

   Example
   -------

   .. code-block:: text

      # Check every minute (default)
      timing.scheduler_interval = 60

      # Check every 5 minutes (low-frequency content)
      timing.scheduler_interval = 300

.. confval:: timing.hybrid.pages

   :type: string
   :Default: ``dynamic``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['pages']

   Timing strategy for page transitions (affects menus).

   Only applies when ``timing.strategy = hybrid``.

   **Recommendation**: Use ``dynamic`` for pages to ensure menus update immediately.

   Example
   -------

   .. code-block:: text

      timing.strategy = hybrid
      timing.hybrid.pages = dynamic

.. confval:: timing.hybrid.content

   :type: string
   :Default: ``scheduler``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['content']

   Timing strategy for content element transitions.

   Only applies when ``timing.strategy = hybrid``.

   **Recommendation**: Use ``scheduler`` for content elements to reduce per-page overhead.

   Example
   -------

   .. code-block:: text

      timing.strategy = hybrid
      timing.hybrid.content = scheduler

.. _configuration-harmonization:

Time Harmonization
==================

Reduces cache churn by rounding transition times to fixed time slots.

.. confval:: harmonization.enabled

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['enabled']

   Enable time slot harmonization.

   **When enabled:**

   - Transitions are rounded to nearest configured time slot
   - **98%+ reduction** in cache transitions possible
   - Multiple transitions group together at slot times
   - Example: Transitions at 00:05, 00:15, 00:45 all round to 00:00

   **When disabled:**

   - Each transition occurs at its exact scheduled time
   - More frequent cache invalidations

   **Impact Example:**

   .. code-block:: text

      Without harmonization:
      500 transitions/day → 500 cache flushes

      With harmonization (4 slots):
      500 transitions/day → 4 cache flushes (at 00:00, 06:00, 12:00, 18:00)

   Example
   -------

   .. code-block:: text

      harmonization.enabled = 1

.. confval:: harmonization.slots

   :type: string
   :Default: ``00:00,06:00,12:00,18:00``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['slots']

   Time slots in HH:MM format for harmonization.

   **Format:**

   - Comma-separated list
   - HH:MM format (24-hour)
   - Example: ``00:00,06:00,12:00,18:00``

   **Presets:**

   .. code-block:: text

      # Every 6 hours (4 slots per day)
      harmonization.slots = 00:00,06:00,12:00,18:00

      # Every 4 hours (6 slots per day)
      harmonization.slots = 00:00,04:00,08:00,12:00,16:00,20:00

      # Every 2 hours (12 slots per day)
      harmonization.slots = 00:00,02:00,04:00,06:00,08:00,10:00,12:00,14:00,16:00,18:00,20:00,22:00

      # Business hours only
      harmonization.slots = 08:00,12:00,17:00

   Example
   -------

   .. code-block:: text

      harmonization.enabled = 1
      harmonization.slots = 00:00,06:00,12:00,18:00

.. confval:: harmonization.tolerance

   :type: integer
   :Default: ``3600``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['tolerance']

   Maximum allowed time shift for harmonization in seconds.

   **Purpose:**

   Prevents unwanted time shifts for content that must appear at specific times.

   **Values:**

   - ``0``: No shift allowed - harmonization is effectively disabled
   - ``3600`` (default): Max 1 hour shift
   - ``1800``: Max 30 minutes shift
   - ``7200``: Max 2 hours shift

   **Example Behavior** (tolerance = 3600, slot = 12:00):

   .. code-block:: text

      Starttime 11:30 → 12:00 (shift 30 min, within tolerance, harmonized)
      Starttime 11:00 → 12:00 (shift 1 hour, at tolerance limit, harmonized)
      Starttime 10:30 → 10:30 (shift would be 90 min, exceeds tolerance, NOT harmonized)

   Example
   -------

   .. code-block:: text

      # Allow up to 1 hour shift (default)
      harmonization.tolerance = 3600

      # Stricter: Max 30 minutes shift
      harmonization.tolerance = 1800

      # Never shift a transition (harmonization has no effect)
      harmonization.tolerance = 0

.. confval:: harmonization.auto_round

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['auto_round']

   Automatically suggest harmonized times in backend forms.

   **When enabled:**

   - Backend forms show harmonization suggestions for starttime/endtime
   - Editors can accept suggested times with one click
   - Helps ensure content uses harmonized times

   **When disabled:**

   - No automatic suggestions
   - Editors can still use Backend Module to harmonize content manually

   .. note::
      Backend form integration is currently in development. The configuration setting
      is read and reported by the extension, but visual form suggestions are not yet
      implemented. Use the Backend Module's Content tab to apply harmonization suggestions.

   Example
   -------

   .. code-block:: text

      harmonization.auto_round = 1

Next Steps
==========

- :ref:`configuration-advanced` - Advanced options and scheduler setup
- :ref:`configuration-examples` - See complete configuration examples
- :ref:`performance-strategies` - Understand optimization trade-offs
