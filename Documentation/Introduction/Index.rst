.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

.. _introduction-problem:

The problem
===========

TYPO3 invalidates a page cache entry when the data behind it changes, or when somebody
flushes a cache tag.
Neither happens when time simply passes.

A page or a content element with ``starttime`` or ``endtime`` changes its visibility at a
fixed moment without any record being edited.
The cached output still holds the visibility snapshot taken when it was rendered, and it
keeps holding it until its lifetime runs out or an editor clears the cache by hand.

This is the subject of TYPO3 Forge issue
`#14277 <https://forge.typo3.org/issues/14277>`__, which is still open.

.. _introduction-symptoms:

Symptoms
--------

Expiring content
   A page with an ``endtime`` in the past stays in cached menus.

Scheduled content
   A page with a ``starttime`` that has arrived does not appear in cached menus.

Content elements
   An element with ``starttime``/``endtime`` does not appear or disappear in cached page
   output.

Anything rendered from a cached page
   Sitemaps, breadcrumbs and listings inherit the same stale snapshot.

.. _introduction-solution:

What this extension does
========================

The extension registers a listener on
``TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent``.
When TYPO3 writes a page cache entry, the listener asks the configured strategies for the
next ``starttime``/``endtime`` transition and shortens the entry's lifetime so it ends
there.
The page is then regenerated at that moment with the correct visibility.

An alternative mode replaces the shortened lifetime with a Scheduler task that flushes the
affected cache tags in the background.
Which of the two runs is a configuration choice; see :ref:`configuration`.

.. _introduction-default-behavior:

Default behavior and its cost
------------------------------

.. warning::
    Out of the box the extension uses **global scoping** with **dynamic timing**
    (``scoping.strategy = global``, ``timing.strategy = dynamic``).
    In that combination the lifetime of *every* page cache entry is cut back to the
    earliest upcoming transition anywhere on the site, so **all page caches expire at every
    temporal transition**, not only the caches of the affected pages.

That is a deliberate default: it is the safest setting and needs no configuration.
It is also the most expensive one, and on a site with frequent transitions it can flatten
the cache hit ratio.

The extension ships two ways to narrow it:

- ``scoping.strategy = per-page`` shortens a page's lifetime for content on that page only.
  Page transitions are still watched site-wide, because a page appearing or disappearing
  changes menus everywhere.
- ``timing.strategy = scheduler`` (with ``per-page`` or ``per-content`` scoping) stops
  shortening lifetimes altogether and flushes individual ``pageId_*`` tags from a
  background task instead.

Read :ref:`performance-considerations` before deploying to a site where the cache hit
ratio matters, and :ref:`architecture-scoping` for what each strategy actually covers.

.. _introduction-status:

Status
======

.. important::
    ``ext_emconf.php`` declares version **1.0.0** and state **stable**.
    The API covered by :ref:`api` follows Semantic Versioning from this release
    onwards.

The approach itself is a workaround.
TYPO3's cache API has no absolute expiration timestamp, so the extension can only
approximate one by shortening relative lifetimes or by flushing tags from a scheduled task.
A solution inside TYPO3 core would not need either.
See :ref:`phases` for what such a solution would look like and what would change here.

.. _introduction-requirements:

Requirements
============

.. list-table::
    :header-rows: 1
    :widths: 30 70

    * - Requirement
      - Value
    * - TYPO3
      - ``^12.4 || ^13.0 || ^14.0``
    * - PHP
      - ``^8.1``
    * - Required TYPO3 extensions
      - ``scheduler``, ``reports``
    * - License
      - GPL-2.0-or-later

.. _introduction-quick-start:

Quick start
===========

.. code-block:: bash
    :caption: Install

    composer require netresearch/nr-temporal-cache

Run the database analyzer afterwards so the indexes from :file:`ext_tables.sql` are
created, then confirm the installation:

.. code-block:: bash
    :caption: Verify

    vendor/bin/typo3 temporalcache:verify

No configuration is required.
The extension is active as soon as it is installed, with global scoping and dynamic timing.

.. _introduction-next-steps:

Next steps
==========

- :ref:`installation` — setup and verification in detail
- :ref:`configuration` — scoping, timing and harmonization options
- :ref:`performance-considerations` — what the defaults cost and how to narrow them
- :ref:`architecture` — how the listener, strategies and queries fit together
