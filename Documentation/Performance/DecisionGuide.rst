.. include:: /Includes.rst.txt

.. _decision-guide:

==============
Decision guide
==============

.. important::
    No benchmark figures for this extension exist in this repository, so this chapter gives
    no thresholds in pages, requests or milliseconds.
    It describes which configuration matches which shape of site, and what to measure on
    your own installation before deciding.

.. _decision-guide-questions:

Four questions that decide the configuration
============================================

Where is the temporal content?
   Only in the ``pages`` table, so only menus and the page tree are affected?
   Or in ``tt_content`` and other records too?
   ``per-page`` scoping only helps when content transitions outnumber page transitions:
   page transitions stay site-wide in every strategy.

Is content reused across pages?
   If elements are placed on one page each, ``per-page`` scoping is accurate.
   If ``CONTENT`` or ``RECORDS`` cObjects pull elements onto other pages, only
   ``per-content`` scoping resolves those references — and only for flush tags, which means
   ``scheduler`` or ``hybrid`` timing.

How far apart are transitions?
   With ``dynamic`` timing the effective page cache lifetime is the gap to the next
   transition in scope.
   If that gap is routinely shorter than the interval in which a page would otherwise be
   requested twice, the page cache is doing little work.

Is a delay acceptable?
   ``scheduler`` timing trades exactness for zero per-request cost.
   Content appears or disappears up to one scheduler interval late.

.. _decision-guide-configurations:

Configurations
==============

.. _decision-guide-default:

Default: global scoping, dynamic timing
---------------------------------------

.. code-block:: php
    :caption: config/system/additional.php — this is what an unconfigured install does

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
        'scoping' => ['strategy' => 'global'],
        'timing' => ['strategy' => 'dynamic'],
    ];

Fits a site where transitions are rare and the page count is small enough that regenerating
everything is cheap.
Nothing can be missed and nothing has to be set up.

The cost is exact and predictable: every transition anywhere expires the whole page cache.
If transitions are frequent, this is the configuration to move away from first.

.. _decision-guide-per-page:

Narrow the lifetime: per-page scoping, dynamic timing
-----------------------------------------------------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
        'scoping' => ['strategy' => 'per-page'],
        'timing' => ['strategy' => 'dynamic'],
    ];

Fits a site whose temporal content is mostly content elements sitting on the page they
belong to.
A scheduled element then shortens only its own page's lifetime.

What it does not change: page transitions still shorten every page's lifetime, because a
page entering or leaving the tree changes menus everywhere.
On a site whose temporal content is mostly *pages*, this configuration behaves close to the
default.

What it can miss: an element embedded onto another page through ``CONTENT``/``RECORDS``.
That page keeps its long lifetime through the element's transition.

.. _decision-guide-scheduler:

Remove per-request cost: scheduler timing
------------------------------------------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
        'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
        'timing' => ['strategy' => 'scheduler'],
    ];

Fits a site where the page cache has to keep its normal lifetime and a delay of one
scheduler interval is acceptable.
Page generation then runs no extra query at all, and invalidation is limited to the pages
the reference index reports.

Requires the Scheduler task to be registered and cron to run it; without both, nothing is
invalidated.
Requires ``sys_refindex`` to be current, otherwise the strategy silently falls back to the
element's own page.

Consider before choosing it:

- A page transition flushes only that page's tag, so menus elsewhere are not refreshed.
  If correct menus matter more than cache hits, keep ``global`` scoping and accept the
  full flush.
- The task loads every temporal record on the site on each run.
- Transitions on translated records are not processed: the task runs against the live
  workspace and the default language only.

.. _decision-guide-hybrid:

Split the two: hybrid timing
----------------------------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
        'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
        'timing' => [
            'strategy' => 'hybrid',
            'hybrid' => [
                'pages' => 'dynamic',
                'content' => 'scheduler',
            ],
        ],
    ];

Fits a site that needs menus to be exact but can tolerate a delay on content elements.
Page transitions keep shortening lifetimes; content transitions are handled by the task.

.. note::
    The lifetime calculation always follows the ``pages`` rule, and that calculation covers
    the content tables as well.
    ``pages = dynamic`` therefore keeps the per-request queries — hybrid changes who reacts
    to content transitions, not the query cost.

.. warning::
    Do not configure ``pages = scheduler`` together with ``content = dynamic``.
    Content transitions are silently dropped in that combination; see
    :ref:`performance-limitations-hybrid`.

.. _decision-guide-harmonization:

Add harmonization when publication times are scattered
------------------------------------------------------

Harmonization helps whichever strategy is configured, because it reduces the number of
distinct transition moments in the data.
It is a rewrite of editorial ``starttime``/``endtime`` values, so it needs the editors'
agreement, and its effect depends on how close the existing times already are to the chosen
slots.
See :ref:`performance-strategies-harmonization`.

.. _decision-guide-not-for-you:

When not to use the extension
=============================

No record uses ``starttime`` or ``endtime``
   Every lookup returns ``null`` and the lifetime falls back to
   ``advanced.default_max_lifetime``.
   The queries still run.
   There is nothing to gain.

Correct menus everywhere are required *and* the full flush is unaffordable
   The two are in tension: only ``global`` scoping refreshes menus on unaffected pages, and
   only the narrower strategies keep the cache.
   No configuration resolves that; see :ref:`performance-alternatives` for approaches that
   take menus out of the page cache entirely.

Manual clearing is already reliable in practice
   Then the extension only adds moving parts.

.. _decision-guide-measure:

What to measure
===============

Before deploying, on a copy of the production data:

.. code-block:: bash
    :caption: How much temporal content exists, and when it transitions

    vendor/bin/typo3 temporalcache:analyze
    vendor/bin/typo3 temporalcache:list

.. code-block:: bash
    :caption: Confirm the indexes exist before measuring query cost

    vendor/bin/typo3 temporalcache:verify

Then measure, with the extension installed and again without it:

- The page cache hit ratio over a period covering several transitions.
- Page generation time on a cache miss, which tells you the cost of a lookup on your data
  volume.
- What arrives at the origin when a transition passes, if a CDN or reverse proxy is in
  front.

Enable ``advanced.debug_logging`` while doing this: the listener then logs the lifetime it
set, the cap it applied and both strategy names for every cache write.

.. _decision-guide-next-steps:

Next steps
==========

- :ref:`performance-strategies` — what each setting does
- :ref:`performance-limitations` — what none of them fixes
- :ref:`performance-alternatives` — solving it without this extension
- :ref:`configuration` — every option in detail
