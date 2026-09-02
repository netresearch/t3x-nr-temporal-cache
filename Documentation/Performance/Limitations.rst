.. include:: /Includes.rst.txt

.. _performance-limitations:

===========
Limitations
===========

This chapter lists what the approach cannot do and which failure modes to plan for.
:ref:`performance-model` has the matrix of what each configuration does; this chapter
assumes it.

.. _performance-limitations-relative-lifetime:

Only a relative lifetime is available
=====================================

TYPO3's cache API accepts "keep this for N seconds", not "keep this until timestamp T".
``ModifyCacheLifetimeForPageEvent`` is the only lever the extension has over a page cache
entry, and it takes a duration.

The event does supply the page id (``getPageId()``) and the rendering instructions, so the
extension can and does scope by page — but it says nothing about which records were
rendered into the entry.
The extension therefore has to infer the relevant transitions from the configured scope
rather than from the page's actual content.

.. _performance-limitations-synchronized-expiry:

Synchronized expiry with global scoping
=======================================

With ``scoping.strategy = global`` every page cache entry written in the same second
receives the same expiry timestamp: the earliest upcoming transition anywhere on the site.

.. code-block:: text

    Site with 10,000 pages
      Page A has a future starttime at 10:00
      Every other page has no temporal restriction

    Every page cache entry written before 10:00 expires at 10:00.
    After 10:00 they all miss, and each miss regenerates a page.

Two consequences follow.

Cache hit ratio
   The effective lifetime of the whole page cache becomes the gap between transitions.
   On a site with frequent transitions that is far shorter than the lifetime the site would
   otherwise use.

Simultaneous misses
   Because the expiry is identical rather than staggered, misses arrive together.
   A CDN or reverse proxy in front of TYPO3 respects the same ``Cache-Control`` window, so
   the burst reaches the origin as one wave.

Mitigations
-----------

- Switch to ``per-page`` scoping.
  Content transitions then expire only the page carrying the content; page transitions
  still land on every entry, since they change menus everywhere.
- Switch to ``scheduler`` timing.
  Nothing expires by time at all; the task flushes what the scoping strategy names.
- Serve stale content while regenerating, so the burst does not reach the origin at full
  size.

.. code-block:: apache
    :caption: Apache

    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=3600, stale-while-revalidate=300"
    </IfModule>

.. code-block:: text
    :caption: Varnish VCL

    sub vcl_backend_response {
        set beresp.grace = 5m;
    }

.. code-block:: nginx
    :caption: Nginx rate limiting on the origin

    limit_req_zone $binary_remote_addr zone=one:10m rate=10r/s;
    limit_req zone=one burst=20 nodelay;

- Warm the cache before the transition, using the transition times
  ``temporalcache:list`` reports.

.. _performance-limitations-query-cost:

Query cost on every cache write
===============================

With ``dynamic`` timing, each page cache write runs two ``MIN()`` queries per monitored
table — four with the default ``pages`` and ``tt_content``.

.. code-block:: sql
    :caption: The shape of each query (per-page scoping adds the pid clause)

    SELECT MIN(`starttime`) AS min_transition
    FROM `tt_content`
    WHERE `starttime` > :now
      AND `deleted` = 0
      AND `hidden` = 0
      AND `pid` = :pageId
      AND (`t3ver_wsid` = 0 OR `t3ver_wsid` IS NULL)
      AND `sys_language_uid` = :language

Notes on that query:

- Records with ``starttime = 0`` are excluded by the ``> :now`` comparison; there is no
  separate ``!= 0`` clause.
- The ``deleted``/``hidden`` column names come from the table's TCA ``ctrl`` section.
  Where no TCA is loaded, those clauses are simply absent.
- The workspace clause is ``t3ver_wsid = :workspace`` for any workspace other than live.

Indexes
-------

:file:`ext_tables.sql` ships the matching composite indexes for the default tables, so no
manual ``CREATE INDEX`` is needed — run the database analyzer after installing and confirm
with ``temporalcache:verify``:

- ``pages``: ``idx_temporalcache_starttime (starttime, sys_language_uid)``,
  ``idx_temporalcache_endtime (endtime, sys_language_uid)``
- ``tt_content``: the same two

.. warning::
    A table registered through ``TemporalMonitorRegistry`` gets **no** index from this
    extension, and adds two queries to every lookup.
    Ship the equivalent index with the extension that registers the table.

Only the site-wide lookup is memoized, in a request-scoped singleton keyed by timestamp,
workspace and language.
The per-page and per-content lookups are not memoized.

.. _performance-limitations-scheduler-cost:

The scheduler task is not free
==============================

``scheduler`` timing removes the per-request queries, but its task loads **every** record
that carries a ``starttime`` or ``endtime`` from every monitored table into PHP on each run
— one query per table, with no time restriction in SQL — and then filters the run's window
in PHP.

Its cost scales with the total volume of temporal content on the site, not with the number
of transitions that actually occurred.
On a site with a lot of scheduled content, running the task every minute repeats that load
every minute.

The first run has no stored timestamp and therefore processes the range from epoch to now,
which flushes for every past transition once.

.. _performance-limitations-scheduler-scope:

Scheduler flushes are narrower than they look
=============================================

Under ``scheduler`` or ``hybrid`` timing the flush tags come from the scoping strategy:

- ``global`` flushes the ``pages`` tag — everything.
- ``per-page`` flushes ``pageId_<uid>`` for a page and ``pageId_<pid>`` for a content
  element.
- ``per-content`` flushes one ``pageId_*`` per refindex hit; a page record still yields only
  its own tag.

So with ``per-page`` or ``per-content`` scoping, a page reaching its ``starttime`` refreshes
that page's own cache and nothing else.
Menus on other pages keep showing the old page tree until their entries expire for another
reason.
Only ``global`` scoping refreshes them.

.. _performance-limitations-per-content-lifetime:

Per-content scoping does not narrow lifetimes
=============================================

``PerContentScopingStrategy::getNextTransition()`` returns the site-wide transition, the
same value ``global`` returns.
Narrowing it per page would risk serving stale content that was embedded from elsewhere.

Consequence: ``per-content`` combined with ``dynamic`` timing is indistinguishable from
``global`` in its cache effect.
The strategy's precision is in its flush tags and needs ``scheduler`` or ``hybrid`` timing
to have any effect.

.. _performance-limitations-cross-page:

Cross-page dependencies with dynamic timing
===========================================

``per-page`` scoping looks at content elements by ``pid``.
An element rendered onto another page through a ``CONTENT`` or ``RECORDS`` cObject is
therefore invisible to that page's lifetime calculation, and the embedding page keeps its
long lifetime when the element transitions.

There is no configuration that fixes this for ``dynamic`` timing.
``per-content`` scoping resolves the references, but only for flush tags.

.. _performance-limitations-hybrid:

A hybrid combination that drops transitions
===========================================

``timing.hybrid.pages`` and ``timing.hybrid.content`` both accept ``dynamic`` and
``scheduler``, so four combinations are configurable.
One of them does not work:

.. list-table::
    :header-rows: 1
    :widths: 20 20 60

    * - ``pages``
      - ``content``
      - Effect
    * - ``dynamic``
      - ``scheduler``
      - The documented pairing. Lifetimes are shortened; content transitions are flushed by
        the task.
    * - ``dynamic``
      - ``dynamic``
      - Equivalent to ``timing.strategy = dynamic``.
    * - ``scheduler``
      - ``scheduler``
      - Equivalent to ``timing.strategy = scheduler``.
    * - ``scheduler``
      - ``dynamic``
      - **Content transitions are dropped.** The lifetime is ``null`` because the ``pages``
        rule decides it, and the task hands content transitions to the dynamic strategy,
        whose ``processTransition()`` does nothing.

.. _performance-limitations-scheduler-context:

The scheduler task sees only live and the default language
==========================================================

``TemporalCacheSchedulerTask`` calls ``findTransitionsInRange()`` without a workspace or
language argument, so it runs with the defaults: workspace ``0`` and
``sys_language_uid = 0``.

Transitions on translated records are therefore not processed by ``scheduler`` or
``hybrid`` timing.
On a multi-language site, use ``dynamic`` timing — which reads workspace and language from
the request context — for the languages that matter.

.. _performance-limitations-scope-of-effect:

Only the page cache
===================

The listener sets the page cache lifetime.
Caches written by other code — an extension's own cache, a reverse proxy configured
independently, a static file cache — keep whatever lifetime that code assigns.

Granularity is one second, because transitions are Unix timestamps.

.. _performance-limitations-no-filtering:

No per-page-tree switch
=======================

The configuration is global to the installation.
There is no setting to exclude a page tree, a doktype or a content type from the
calculation.

The workaround is a second listener on the same event, ordered after this extension's, that
overrides the lifetime for the pages it wants to exclude — see
:ref:`architecture-custom-listener`.

.. _performance-limitations-next-steps:

Next steps
==========

- :ref:`performance-strategies` — the settings that change this behavior
- :ref:`decision-guide` — which configuration matches which site
- :ref:`architecture` — the implementation these limits come from
- :ref:`phases` — what a core solution would change
