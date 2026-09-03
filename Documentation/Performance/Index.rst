.. include:: /Includes.rst.txt

.. _performance-considerations:

==========================
Performance considerations
==========================

.. _performance-overview:

Overview
========

The extension buys correct temporal behavior by making page cache entries expire earlier
than they otherwise would, or by flushing them from a background task.
Both cost cache hits.
How many depends entirely on which scoping and timing strategy is configured, and the
default is the most expensive combination.

.. important::
    The default configuration — ``scoping.strategy = global`` and
    ``timing.strategy = dynamic`` — shortens the lifetime of **every** page cache entry to
    the earliest upcoming transition anywhere on the site.
    One scheduled page therefore expires the whole site's page cache at that moment.

This chapter describes what each configuration actually does, so the cost can be measured
on a specific site.
It contains no benchmark figures: none have been measured for this extension, and numbers
from another site would not transfer.

.. _performance-model:

The model in one table
======================

Scoping and timing are independent, and scoping answers two different questions depending
on which timing strategy reads it.

.. list-table:: What each combination does
    :header-rows: 1
    :widths: 20 40 40

    * - Scoping
      - with ``dynamic`` timing (shortens lifetimes)
      - with ``scheduler`` timing (flushes tags)
    * - ``global``
      - Every entry expires at the earliest transition site-wide.
      - Flushes the ``pages`` tag: the entire page cache, once per transition.
    * - ``per-page``
      - An entry expires at the earlier of: the next ``pages`` transition site-wide, or the
        next transition of a content element on that page.
      - Flushes ``pageId_<uid>`` for a page, ``pageId_<pid>`` for a content element.
    * - ``per-content``
      - Same as ``global`` — this strategy does not narrow lifetimes.
      - Flushes one ``pageId_*`` tag per page that ``sys_refindex`` reports for the element.

Read the table before choosing a strategy.
Two combinations are commonly misread:

- ``per-content`` + ``dynamic`` narrows **nothing**.
  Its precision lives in the flush tags, which ``dynamic`` timing never reads.
  Configuring it without switching timing gives global behavior plus refindex code that
  never runs.
- ``per-page`` or ``per-content`` + ``scheduler`` flushes only the tags named above.
  A page transition then refreshes that page, not the menus on every other page.
  Only ``global`` scoping refreshes those.

``hybrid`` timing picks per record type: ``timing.hybrid.pages`` decides the lifetime
calculation and page transitions, ``timing.hybrid.content`` decides content transitions.

.. _performance-cost:

Where the cost sits
===================

Query cost with ``dynamic`` timing
   Every page cache write runs two ``MIN()`` queries per monitored table — four with the
   default ``pages`` and ``tt_content``, two more for every table another extension
   registers.
   Each query is a single indexed aggregate returning one integer.
   One request carries one workspace and one language, so the query count does not grow
   with the number of configured languages.

Cache hit ratio
   This is the cost that matters, and it is a property of the site, not of the extension:
   how often transitions occur, how many pages exist, and how much traffic arrives between
   two transitions.

Simultaneous expiry
   With ``global`` scoping every entry carries the same expiry timestamp, so they all miss
   at once.
   Behind a CDN or reverse proxy that miss propagates upstream.
   ``per-page`` scoping spreads the timestamps for content transitions; page transitions
   still land on all entries at once.

Query cost with ``scheduler`` timing
   Zero during page generation.
   The cost moves into the scheduled task, which is heavier than the lookup it replaces:
   each run loads **every** record carrying a ``starttime`` or ``endtime`` from every
   monitored table into PHP — one query per table, no time restriction in SQL — and then
   filters the run's time window in PHP.
   Its cost therefore scales with the total amount of temporal content on the site, not
   with the number of transitions in the window.
   Each transition that does fall in the window costs one ``flushByTag()`` per tag the
   scoping strategy names.

.. _performance-chapters:

Chapters
========

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: ⚡ Optimization strategies

        What scoping, timing and harmonization each change, and how to combine them.

        ..  card-footer:: :ref:`Read Strategies <performance-strategies>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚠️ Limitations

        What the approach cannot do, and the failure modes to plan for.

        ..  card-footer:: :ref:`Read Limitations <performance-limitations>`
            :button-style: btn btn-warning stretched-link

    ..  card:: 🎯 Decision guide

        Which configuration matches which shape of site, and what to measure before
        deciding.

        ..  card-footer:: :ref:`Read Decision Guide <decision-guide>`
            :button-style: btn btn-info stretched-link

    ..  card:: 🔄 Alternative approaches

        Solving temporal content without this extension: uncached menus, ESI, client-side
        loading, scheduled clearing.

        ..  card-footer:: :ref:`Read Alternatives <performance-alternatives>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: ❓ Frequently asked questions

        Cache synchronization, CDNs, workspaces, and when not to use the extension.

        ..  card-footer:: :ref:`Read FAQ <performance-faq>`
            :button-style: btn btn-success stretched-link

.. _performance-related:

Related documentation
=====================

- :ref:`architecture` — the implementation behind this behavior
- :ref:`configuration` — every option in detail
- :ref:`installation` — installation and index verification
- :ref:`phases` — why the approach is shaped this way

.. Meta Menu

.. toctree::
    :hidden:
    :maxdepth: 2

    Strategies
    Limitations
    DecisionGuide
    Alternatives
    FAQ
