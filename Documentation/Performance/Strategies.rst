.. include:: /Includes.rst.txt

.. _performance-strategies:

=======================
Optimization strategies
=======================

Three settings change how much cache the extension costs:

#. **Scoping** — which records a lookup covers and which tags a transition flushes
#. **Timing** — whether invalidation happens through a shortened lifetime or a background
   task
#. **Harmonization** — a one-off rewrite of the stored ``starttime``/``endtime`` values so
   fewer distinct transition moments exist

Scoping and timing interact, and one combination is a trap; see :ref:`performance-model`
for the full matrix before reading on.

.. _performance-strategies-scoping:

Scoping strategies
==================

.. _performance-strategies-scoping-global:

Global (default)
----------------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'global';

Lifetime
   ``getNextTransition()`` returns the earliest upcoming transition across every monitored
   table, site-wide.
   The page id is ignored, so every page cache entry written in that second gets the same
   expiry.

Flush tags
   ``['pages']`` — the tag every page cache entry carries, so a transition flushes the whole
   page cache.

Trade-off
   Nothing to configure and nothing can be missed.
   Every transition anywhere costs the whole page cache.

.. _performance-strategies-scoping-per-page:

Per-page
--------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-page';

Lifetime
   The earlier of two lookups: the next transition in the ``pages`` table site-wide, and the
   next transition in the content tables restricted to ``pid = <rendered page>``.
   Page transitions stay site-wide on purpose — a page appearing or disappearing changes
   menus on every page.
   When no page id is available the strategy falls back to the site-wide lookup.

Flush tags
   ``pageId_<uid>`` for a page record, ``pageId_<pid>`` for a content element.

Trade-off
   Content churn is confined to the page that carries the content.
   Content embedded from another page through ``CONTENT`` or ``RECORDS`` cObjects is not
   seen, so that page's lifetime is not shortened when the embedded element transitions.

.. _performance-strategies-scoping-per-content:

Per-content
-----------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping'] = [
        'strategy' => 'per-content',
        'use_refindex' => true,
    ];

Lifetime
   Identical to ``global``: the site-wide next transition.
   ``PerContentScopingStrategy::getNextTransition()`` deliberately does not narrow, because
   an element can be embedded into arbitrary pages and a narrowed lifetime could serve
   stale embedded content.

Flush tags
   One ``pageId_*`` tag per page that ``sys_refindex`` reports as referencing the element,
   which covers direct placement, ``CONTENT``/``RECORDS`` embedding, mount points and
   shortcuts.
   A page record always yields its own tag only.

Trade-off
   The most precise invalidation available, but only along the flush-tag path.

.. warning::
    ``per-content`` with the default ``dynamic`` timing changes nothing at all — that
    combination reads only the lifetime, which ``per-content`` leaves site-wide.
    Pair it with ``scheduler`` or ``hybrid`` timing, or the refindex work never runs.

Set ``scoping.use_refindex = 0`` to skip the refindex lookup; the strategy then falls back
to the element's own ``pid``, which is what ``per-page`` already does. The strategy also
falls back to the ``pid`` when the refindex returns nothing or the lookup throws, so a stale
``sys_refindex`` degrades quietly rather than failing.

.. code-block:: bash
    :caption: Keep the reference index current

    vendor/bin/typo3 referenceindex:update

.. _performance-strategies-timing:

Timing strategies
=================

.. _performance-strategies-timing-dynamic:

Dynamic (default)
-----------------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy'] = 'dynamic';

The listener asks the scoping strategy for the next transition on every page cache write
and sets the lifetime to the remaining seconds, capped at
``advanced.default_max_lifetime``.
With no upcoming transition the lifetime is ``advanced.default_max_lifetime``; with a
transition already in the past it is ``60``.

Cost: two ``MIN()`` queries per monitored table on every cache write — four with the default
``pages`` and ``tt_content``.
Only the site-wide lookup is memoized for the duration of a request, and its cache key
includes the current timestamp, so the memo helps within one second.

Transitions take effect at the moment they happen, on the next request to the page.
The scheduler is not involved and no task has to be set up.

.. _performance-strategies-timing-scheduler:

Scheduler
---------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy'] = 'scheduler';

``getCacheLifetime()`` returns ``null``, so the listener leaves TYPO3's own lifetime
untouched and page generation carries no extra query.
Invalidation moves to ``Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask``:

#. Add the task in the Scheduler backend module and note the UID it receives.
#. Let cron run the Scheduler.

.. code-block:: text
    :caption: Crontab

    * * * * * php /path/to/typo3/vendor/bin/typo3 scheduler:run

.. note::
    ``scheduler:run`` executes every task that is due.
    To run only this one, pass its UID: ``scheduler:run --task=<uid>``.
    There is no dedicated console command for the transition check — the extension's own
    commands are ``temporalcache:analyze``, ``temporalcache:verify``,
    ``temporalcache:harmonize`` and ``temporalcache:list``.

Each run reads its last-run timestamp from TYPO3's ``Registry`` (namespace
``tx_temporalcache``, key ``scheduler_last_run``), processes every transition since then,
and stores the new timestamp.
How often that happens is the task's frequency in the Scheduler module.

.. warning::
    A transition is only noticed on the next task run, so content appears or disappears up
    to one interval late.
    The very first run has no stored timestamp and therefore treats the range as starting at
    epoch, which means every past transition on the site is processed once.

.. _performance-strategies-timing-hybrid:

Hybrid
------

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing'] = [
        'strategy' => 'hybrid',
        'hybrid' => [
            'pages' => 'dynamic',
            'content' => 'scheduler',
        ],
    ];

Two switches, each accepting ``dynamic`` or ``scheduler``.
There is no per-table setting: a record is classified as ``page`` (table ``pages``) or
``content`` (every other monitored table).

``timing.hybrid.pages`` decides two things — how page transitions are processed by the
scheduler task, **and** which strategy computes the cache lifetime.
``timing.hybrid.content`` decides only how content transitions are processed by the task.

.. note::
    Because the lifetime always follows the ``pages`` rule, ``pages = dynamic`` keeps the
    per-request ``MIN()`` queries, and those queries cover the content tables too.
    Hybrid with ``pages = dynamic`` does not remove query cost for content; it changes who
    reacts to content transitions.

.. warning::
    ``pages = scheduler`` combined with ``content = dynamic`` is accepted by the
    configuration but does nothing for content: the lifetime is then ``null`` and the
    scheduler hands content transitions to the dynamic strategy, whose
    ``processTransition()`` is a no-op.
    Content transitions are silently dropped in that combination.

.. _performance-strategies-harmonization:

Time harmonization
==================

Harmonization is a **data change**, not runtime behavior.
``temporalcache:harmonize`` (and the equivalent backend action) rewrite the stored
``starttime``/``endtime`` values of records, moving each to its nearest configured slot.
Fewer distinct transition moments means fewer cache invalidations, whatever the scoping and
timing strategy.

.. important::
    Enabling ``harmonization.enabled`` on its own changes no cache behavior.
    It unlocks the harmonize command and the backend analysis; the reduction only happens
    once the command has actually written the rounded values.
    Those values are the editors' publication times, so agree the change with them first —
    and preview it with ``temporalcache:harmonize --dry-run``, since the command writes to
    the database by default.

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization'] = [
        'enabled' => true,
        'slots' => '00:00,06:00,12:00,18:00',
        'tolerance' => 3600,
    ];

.. _performance-strategies-harmonization-slots:

Slots and tolerance
-------------------

Slots are a single comma-separated string of ``HH:MM`` values.
Each timestamp is moved to its *nearest* slot, and only when the distance to that slot is at
most ``harmonization.tolerance`` seconds.

.. code-block:: text

    Slot at 12:00:00, tolerance = 300 seconds

    11:56:00 → 4 minutes away  → shifted to 12:00:00
    12:03:00 → 3 minutes away  → shifted to 12:00:00
    11:50:00 → 10 minutes away → beyond tolerance, stays 11:50:00

The tolerance is the maximum shift the rewrite may apply, not a threshold below which
nothing happens.
A small tolerance keeps publication times close to what editors entered and harmonizes few
records; the default ``3600`` harmonizes anything within an hour of a slot.

.. warning::
    ``harmonization.tolerance = 0`` shifts nothing except timestamps that already sit
    exactly on a slot.
    It does not mean "no limit".

.. _performance-strategies-harmonization-example:

Effect
------

.. code-block:: text

    Before harmonize, slots 00:00,06:00,12:00,18:00, tolerance 3600:

      Article 1: starttime 05:43  → within an hour of 06:00 → rewritten to 06:00
      Article 2: starttime 06:18  → within an hour of 06:00 → rewritten to 06:00
      Article 3: starttime 11:27  → within an hour of 12:00 → rewritten to 12:00
      Article 4: starttime 12:31  → within an hour of 12:00 → rewritten to 12:00

    Four distinct transition moments become two.

Records further from a slot than the tolerance keep their time and keep their own
transition moment, so the reduction depends on how the existing publication times are
distributed — not on the number of slots alone.

.. _performance-strategies-combining:

Combining the three
===================

.. code-block:: php
    :caption: config/system/additional.php — narrow invalidation, no query cost per request

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
        'scoping' => [
            'strategy' => 'per-content',
            'use_refindex' => true,
        ],
        'timing' => [
            'strategy' => 'scheduler',
        ],
        'harmonization' => [
            'enabled' => true,
            'slots' => '00:00,06:00,12:00,18:00',
            'tolerance' => 3600,
        ],
    ];

What this configuration gives, and what it costs:

- No queries during page generation, because the lifetime calculation is skipped entirely.
- Invalidation limited to the pages the refindex reports for a transitioning element.
- Up to one scheduler interval of delay before a transition takes effect.
- Menus on unaffected pages are not refreshed by a page transition, because the flush tag is
  that page's own — this is the price of leaving ``global`` scoping.
- A scheduler run that loads all temporal records on the site, whether or not any of them
  transitioned.

Requires the Scheduler task to be registered and cron to be running.
If either is missing, nothing invalidates anything.

.. _performance-strategies-next-steps:

Next steps
==========

- :ref:`performance-limitations` — what the approach cannot do
- :ref:`decision-guide` — which configuration matches which site
- :ref:`configuration` — every option in detail
