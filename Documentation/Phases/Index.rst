.. include:: /Includes.rst.txt

.. _phases:

=====================================
Approach, limits and a core solution
=====================================

.. important::
    ``ext_emconf.php`` declares version **0.9.0** and state **beta**.
    ``v0.9.0`` is the first published release.

.. _phases-overview:

Overview
========

This chapter explains why the extension works the way it does, what the approach cannot do,
and what a solution inside TYPO3 core would have to provide to make the extension
unnecessary.

Nothing here describes committed work in TYPO3 core.
There is no accepted RFC, no target version and no timeline for a core solution; treat the
section on it as a description of the missing capability, not as a roadmap.

.. _phases-current:

What the extension does today
=============================

TYPO3's cache API accepts a *relative* lifetime — "keep this for N seconds".
It has no *absolute* expiration — "keep this until timestamp T".
Temporal visibility is an absolute-expiration problem, so the extension approximates one in
two ways.

Shorten the lifetime (``timing.strategy = dynamic``)
   The listener on ``ModifyCacheLifetimeForPageEvent`` computes ``nextTransition - time()``
   and sets that as the entry's lifetime.

.. code-block:: php
    :caption: Classes/EventListener/TemporalCacheLifetime.php (condensed)

    public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
    {
        $lifetime = $this->timingStrategy->getCacheLifetime($this->context, $event->getPageId());

        if ($lifetime !== null) {
            $maxLifetime = $this->determineMaxLifetime($event->getRenderingInstructions());
            $event->setCacheLifetime(\min($lifetime, $maxLifetime));
        }
    }

Flush tags from a scheduled task (``timing.strategy = scheduler``)
   The listener leaves the lifetime alone.
   ``TemporalCacheSchedulerTask`` asks for every transition since its last run and flushes
   the cache tags the scoping strategy names for each of them.

``hybrid`` combines the two, choosing per record type.
See :ref:`architecture-timing` for the exact behavior of each strategy.

.. _phases-properties:

Properties of this approach
---------------------------

Works with the released TYPO3 versions
   ``^12.4 || ^13.0 || ^14.0``, no core patch required.

Nothing to configure for it to work
   The defaults (global scoping, dynamic timing) are active on installation.

Runs queries on every page cache write
   With ``dynamic`` timing, each write costs two ``MIN()`` queries per monitored table —
   four with the default ``pages``/``tt_content`` pair.
   ``scheduler`` timing moves that cost out of page generation entirely.

Cannot express per-entry absolute expiration
   The only lever is the relative lifetime of the entry currently being written, so an
   upcoming transition anywhere in the scope shortens the lifetime of the entry, whether or
   not that entry actually shows the affected record.
   This is why global scoping expires all page caches at every transition.

Cannot follow arbitrary dependencies
   With ``dynamic`` timing, ``per-page`` scoping does not see content embedded from another
   page through ``CONTENT``/``RECORDS``, and ``per-content`` scoping narrows only the flush
   tags, not the lifetime.

.. _phases-core-solution:

What a core solution would need
===============================

Two capabilities are missing from TYPO3, and both would have to come from core.

.. _phases-core-absolute-expiration:

Absolute expiration in the cache API
------------------------------------

A cache entry would have to be able to carry an absolute expiry timestamp alongside its
relative lifetime, so the frontend could say "this entry is valid until 2026-10-28 14:30"
without a background job and without shortening anything else.

With that, the extension's work would reduce to attaching the transition timestamp of the
records that were actually rendered — per entry, with no site-wide lookup and no scheduler.

.. _phases-core-dependency-tracking:

Temporal dependency tracking
----------------------------

The deeper gap is that nothing records *which* temporal records contributed to a cache
entry.
If the rendering pipeline tracked that — the way cache tags already track record identity —
the expiry timestamp could be derived automatically and correctly, including for content
embedded across pages, which is exactly the case this extension cannot cover.

That is a change to the rendering pipeline, not to the cache API alone.

.. _phases-migration:

If core gains these capabilities
================================

Nothing in this extension currently detects core capabilities or switches APIs
automatically; that would have to be built when there is an API to build against.

What would happen in practice:

#. The extension would gain support for the new API on the TYPO3 versions that have it,
   keeping the current implementation for the versions that do not.
#. Once every supported TYPO3 version carried the core solution, the extension would have
   nothing left to do and could be removed from a project.

Until then, the settings described in :ref:`configuration` are the only lever.

.. _phases-when-to-use:

When this approach fits
=======================

It fits when
   Temporal content exists and stale menus or stale content elements are a real editorial
   problem; and the cache behavior of the chosen configuration has been measured on the
   site in question.

It does not fit when
   No record uses ``starttime``/``endtime`` — the extension then only adds queries; or the
   site cannot absorb the cache churn of the configuration it needs and no scoping/timing
   combination brings it down far enough.

Both cases are judgment calls about a specific site.
:ref:`decision-guide` walks through which configuration matches which shape of site, and
:ref:`performance-alternatives` covers approaches that do not involve this extension.

.. _phases-feedback:

Feedback
========

The interesting feedback for this problem is the concrete behavior of a real site: which
scoping and timing combination was chosen, and what happened to the cache hit ratio.

- `Forge #14277 <https://forge.typo3.org/issues/14277>`__ — the core issue
- `Issue tracker <https://github.com/netresearch/t3x-nr-temporal-cache/issues>`__ — bugs and
  feature requests for this extension

.. _phases-next-steps:

Next steps
==========

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📘 How it is implemented

        The listener, the two strategy families, the queries they run and how they are
        wired together.

        ..  card-footer:: :ref:`Read Architecture <architecture>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚡ What the defaults cost

        What each scoping and timing combination does to the cache, and how to narrow the
        default.

        ..  card-footer:: :ref:`Performance Considerations <performance-considerations>`
            :button-style: btn btn-warning stretched-link

    ..  card:: 🔧 Install the extension

        Requirements, installation and verification.

        ..  card-footer:: :ref:`Installation Guide <installation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🎯 Follow the core issue

        The Forge issue this extension addresses.

        ..  card-footer:: `Forge #14277 <https://forge.typo3.org/issues/14277>`__
            :button-style: btn btn-info stretched-link
