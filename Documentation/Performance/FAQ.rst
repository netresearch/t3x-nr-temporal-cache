.. include:: /Includes.rst.txt

.. _performance-faq:

==========================
Frequently asked questions
==========================

.. _performance-faq-whole-site:

Why does my entire site cache expire when one page has a future starttime?
=========================================================================

Because the default scoping strategy is ``global``.
Its transition lookup covers every monitored table site-wide and ignores the page id, so
every page cache entry written in that second gets the same shortened lifetime.

Switching to ``per-page`` narrows it — but only for content elements:

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy'] = 'per-page';

A transition in the ``pages`` table still shortens every page's lifetime, in every strategy,
because a page entering or leaving the tree changes menus everywhere.

.. warning::
    Switching to ``per-content`` does **not** help here.
    That strategy narrows flush tags, not lifetimes, and flush tags are only read by
    ``scheduler`` and ``hybrid`` timing.
    With the default ``dynamic`` timing it behaves exactly like ``global``.

.. _performance-faq-page-trees:

Can I disable this for specific page trees?
===========================================

Not through configuration.
The settings are global to the installation; there is no page-tree, doktype or content-type
filter.

The workaround is a second listener on the same event, ordered after this extension's, that
overrides the lifetime for the pages it should not apply to:

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
            if (\in_array($event->getPageId(), $this->excludedPageIds, true)) {
                $event->setCacheLifetime(86400);
            }
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

.. _performance-faq-cdn:

Will this work with a CDN or Varnish?
=====================================

Yes, with one caveat.
A CDN honors the ``Cache-Control`` window it is given, so a shortened page cache lifetime
propagates to the edge.
With ``global`` scoping every entry carries the same expiry, so the edge misses arrive
together and reach the origin as one wave.

The mitigations are the usual ones — serve stale while revalidating, rate-limit the origin,
warm the cache ahead of a known transition — plus the extension-side option of moving to
``scheduler`` timing, which stops shortening lifetimes altogether.
See :ref:`performance-limitations-synchronized-expiry`.

.. _performance-faq-backend:

Does this affect backend performance?
=====================================

The cache lifetime listener runs on frontend page cache writes only, so ordinary backend
editing is untouched.

Three parts of the extension do run in the backend, on demand: the backend module, the
Reports module status provider, and the console commands.
The backend module and the harmonization analysis load temporal records to build their
figures, so they get slower as the amount of temporal content grows.

.. _performance-faq-no-temporal-content:

What if I do not use temporal content at all?
=============================================

Every lookup returns ``null`` and the lifetime falls back to
``advanced.default_max_lifetime`` (default ``86400``), so behavior is unchanged.
The queries still run on every page cache write with ``dynamic`` timing.

There is no benefit in that case — uninstall it.

.. _performance-faq-monitoring:

How do I see what the extension is doing?
=========================================

Turn on ``advanced.debug_logging``.
The listener then logs, for every page cache write it modifies: the lifetime it set, the
uncapped value, the cap and where the cap came from, and the names of both active
strategies.

.. code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['debug_logging'] = true;

The same flag makes ``SchedulerTimingStrategy`` log each flush with the tags it flushed, and
makes the scheduler task log its run window.

To see the data rather than the decisions:

.. code-block:: bash

    vendor/bin/typo3 temporalcache:analyze   # counts and statistics
    vendor/bin/typo3 temporalcache:list      # every temporal record and its next transition
    vendor/bin/typo3 temporalcache:verify    # indexes and configuration

.. _performance-faq-warming:

Can I combine this with cache warming?
======================================

Yes, and it is worth doing with ``global`` scoping, where all entries expire at the same
known moment.
``temporalcache:list`` reports the next transition per record, so a warming run can be
scheduled shortly after it.

The extension ships no warming itself and integrates with no particular warming extension;
anything that requests pages after the transition works.

.. _performance-faq-thundering-herd:

What happens under load when the cache expires?
===============================================

With ``global`` scoping, every page cache entry expires at the same second, so every
subsequent request is a miss until the entries are rebuilt.
Under load that arrives at the origin as a single burst.

Four levers, in rough order of effectiveness:

- ``scheduler`` timing — nothing expires by time.
- ``per-page`` scoping — content transitions stagger; page transitions do not.
- Stale-while-revalidate at the edge, so the burst is absorbed.
- Cache warming timed to the known transition.

.. _performance-faq-workspaces:

How does this work with workspaces?
===================================

With ``dynamic`` timing, correctly and without configuration.
The strategies read the workspace id from the Context API and pass it into every query; a
live request and a workspace preview therefore resolve different transitions and get
different lifetimes.

.. warning::
    With ``scheduler`` or ``hybrid`` timing this does not hold.
    The scheduler task looks for transitions in the live workspace and the default language
    only, so transitions on workspace versions and on translated records are not processed.

.. _performance-faq-cache-tags:

Can I combine this with my own cache tags?
==========================================

Yes.
The extension sets the page cache lifetime and, under ``scheduler`` timing, flushes
``pages`` or ``pageId_*`` tags.
It does not interfere with tags your own code adds or flushes.

If your listener also sets a lifetime, order it after
``temporal-cache/modify-cache-lifetime`` and combine the two values with ``min()`` rather
than overwriting.

.. _performance-faq-harmonization:

What does harmonization cost?
=============================

Nothing at runtime — it changes no code path.
``temporalcache:harmonize`` rewrites the stored ``starttime``/``endtime`` values once, and
from then on the same transition lookups simply find fewer distinct moments.

The real cost is editorial: publication times move to the nearest slot within the
configured tolerance.
Preview with ``--dry-run`` before running it, because the command writes by default.

.. _performance-faq-multilanguage:

Does the query cost multiply with the number of languages?
==========================================================

No.
One frontend request carries one language, and the language id from the Context API goes
into the query as a single ``sys_language_uid`` condition.
A ten-language site runs the same number of queries per cache write as a single-language
site — each language just maintains its own cache entries and its own transitions.

The number of queries grows with the number of **monitored tables**, at two per table.

.. _performance-faq-next-steps:

Next steps
==========

- :ref:`performance-strategies` — what each setting changes
- :ref:`performance-limitations` — what none of them fixes
- :ref:`decision-guide` — choosing a configuration
- :ref:`architecture` — the implementation behind the answers
