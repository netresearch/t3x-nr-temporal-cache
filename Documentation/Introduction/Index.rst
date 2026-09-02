.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

The Problem: 20 Years of Temporal Cache Issues
===============================================

TYPO3 Forge Issue `#14277 <https://forge.typo3.org/issues/14277>`__
was reported in **August 2004** and remains unsolved as of 2025.

Root Cause
----------

TYPO3's cache invalidation architecture is **event-driven** (invalidates when
data changes) but doesn't handle **temporal dependencies** (when time passes).

The cache system supports:

- ✅ Event-driven invalidation: Invalidate when page edited/deleted
- ✅ Tag-based invalidation: Flush specific cache entries by tag
- ✅ Relative TTL: Cache for N seconds from NOW

But it's missing:

- ❌ Temporal invalidation: Invalidate at absolute timestamp
- ❌ Time-aware caching: Understand time-based visibility rules

Symptoms
--------

When pages or content elements have ``starttime`` or ``endtime`` values set:

**Expiring Content:**
   Pages with ``endtime`` remain visible in menus after expiration until cache
   is manually cleared.

**Scheduled Content:**
   Pages with ``starttime`` don't appear in menus when their scheduled time
   arrives until cache is manually cleared.

**Content Elements:**
   Content blocks with ``starttime/endtime`` don't update automatically in
   cached page output.

**Other Components:**
   Sitemaps, search results, breadcrumbs, and any cached listings don't reflect
   time-based visibility changes.

Impact
------

This affects **ALL** TYPO3 installations using:

- Navigation menus (HMENU)
- Scheduled content publication
- Content elements with time restrictions
- News/blog posts with publication dates
- Event calendars
- Any extension using ``starttime/endtime`` fields

**Severity:** HIGH

- Core CMS functionality (content scheduling) is unreliable
- No automatic solution exists in TYPO3 core
- Manual cache clearing required for every time-based transition
- Affects production sites' editorial workflows

Community Impact
----------------

- **Age:** 20+ years unresolved (2004-2025)
- **Watchers:** 5+ users actively tracking
- **Versions:** Confirmed affecting v9.5.7 through v14
- **Scope:** System-wide architectural limitation

Related Issues
--------------

- `#16815 <https://forge.typo3.org/issues/16815>`__ - Sitemap ignoring start/end flags
- `#98964 <https://forge.typo3.org/issues/98964>`__ - Menu caching excessive cache_hash records

Why It Took 20 Years to Solve
------------------------------

The issue manifests differently across components:

**Menus:**
   "Page is live but not in navigation"

**Content Elements:**
   "I had to clear cache to make scheduled content appear"

**Search:**
   "Search results show expired pages"

Same root cause, different symptoms - making systematic diagnosis difficult.

What This Extension Does
=========================

.. important::
   **Extension Status**: This extension is **stable and production-ready**.
   The code is thoroughly tested, follows TYPO3 best practices, and is professionally
   maintained.

   **Approach Status**: The temporal cache solution is **experimental**. This extension
   implements Phase 1 as a pragmatic workaround with known limitations (site-wide cache
   synchronization) until TYPO3 core provides native temporal cache support (Phase 2/3).

Implements **Phase 1** of a three-phase solution:

1. **Phase 1 (This Extension):** Dynamic cache lifetime based on next temporal
   transition - works TODAY with current TYPO3 versions
2. **Phase 2 (Future Core):** Absolute expiration API in TYPO3 cache system
   (planned for v15/v16)
3. **Phase 3 (Future Core):** Automatic temporal cache awareness (long-term vision)

See :ref:`phases` for complete roadmap, migration path, and detailed explanation of
why Phase 1 is necessary today.

Quick Start
===========

Installation::

   composer req netresearch/nr-temporal-cache

Configuration:

   **None required!** Extension works automatically after installation.

Result:

   ✅ Menus update when pages reach starttime/endtime
   ✅ Content elements appear/disappear automatically
   ✅ Sitemaps and listings stay current
   ✅ Zero manual cache clearing needed

Next Steps
==========

- :ref:`installation` - Detailed setup guide
- :ref:`architecture` - Technical implementation details
- :ref:`phases` - Complete three-phase roadmap
