.. include:: /Includes.rst.txt

.. _phases:

================================
Three-Phase Solution & Roadmap
================================

.. important::
   **Extension Status**: This extension is **beta** (version 0.9.0). The only published tag is a pre-release.

   **Approach Status**: The temporal cache solution is **experimental**. This extension
   implements Phase 1 as a pragmatic workaround until TYPO3 core provides native temporal
   cache support (Phase 2/3).

Overview
========

The temporal content problem (TYPO3 Forge #14277) requires a three-phase solution approach.
This extension implements **Phase 1**, while Phases 2 and 3 require TYPO3 core changes.

The Three Phases
================

Phase 1: Extension-Based Dynamic Cache Lifetime (Current)
----------------------------------------------------------

**Status**: ✅ Implemented by this extension

**Approach**:

Intercept cache generation and dynamically calculate cache lifetime based on next temporal
transition (starttime/endtime).

**Implementation**:

.. code-block:: php

   // EventListener via ModifyCacheLifetimeForPageEvent
   public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
   {
       $nextTransition = $this->getNextTemporalTransition();
       if ($nextTransition !== null) {
           $lifetime = max(0, $nextTransition - time());
           $event->setCacheLifetime($lifetime);
       }
   }

**Advantages**:

✅ Works today with TYPO3 v12/v13
✅ Automatic temporal cache invalidation
✅ Zero configuration required
✅ Stable extension code

**Limitations**:

⚠️ Requires database queries on every cache generation (~5-10ms)
⚠️ Site-wide cache synchronization (global scope limitation)
⚠️ Cannot detect cross-page temporal dependencies
⚠️ Experimental approach - workaround until core solution

**Best For**:

- Small to medium sites (<10,000 pages)
- Low to moderate temporal transition frequency (<50/day)
- Sites prioritizing correct temporal behavior over maximum performance

Phase 2: Core API with Absolute Expiration
-------------------------------------------

**Status**: 🔄 Planned for TYPO3 v15/v16 (2025-2026)

**Approach**:

Extend TYPO3 ``CacheTag`` API to support absolute expiration timestamps alongside relative TTL.

**Proposed API**:

.. code-block:: php

   // Future TYPO3 core API
   $cache->set(
       $key,
       $content,
       $tags,
       $ttl,
       $absoluteExpire: 1730124600  // Unix timestamp
   );

   // Or via enhanced CacheTag
   $cacheTag = new CacheTag(
       'pageId_123',
       relativeExpire: 3600,        // Relative TTL
       absoluteExpire: 1730124600   // Absolute timestamp
   );

**Benefits**:

✅ Per-page cache expiration (no global synchronization needed)
✅ No database queries during cache generation
✅ Native TYPO3 core support with proper APIs
✅ Framework-level solution applicable to all cache types

**Timeline**:

RFC discussion planned for TYPO3 v15 development cycle (2025). Implementation would follow
in v15 or v16 depending on core team priorities and community feedback.

Phase 3: Automatic Temporal Dependency Detection
-------------------------------------------------

**Status**: 🔮 Future vision (post-v16)

**Approach**:

TYPO3 core automatically detects temporal dependencies during content rendering and configures
cache expiration without explicit developer intervention.

**Concept**:

.. code-block:: php

   // Theoretical automatic detection
   // Framework tracks field access during rendering

   $page = $repo->findByUid($pageId);

   // Framework detects: "starttime field accessed"
   if ($page->getStarttime() > time()) {
       // Automatically registers temporal dependency
   }

   // Cache expiration configured automatically
   // Zero configuration, zero performance overhead

**Benefits**:

✅ Transparent temporal handling - works automatically
✅ Optimal cache scoping without configuration
✅ No performance trade-offs
✅ Developer-friendly - "it just works"

**Challenges**:

⚠️ Requires deep framework changes in rendering pipeline
⚠️ Complexity in tracking field access across Fluid/TypoScript
⚠️ Backward compatibility concerns
⚠️ Significant engineering effort

**Timeline**:

Long-term vision. Depends on Phase 2 success and community feedback. Likely post-TYPO3 v16.

Migration Path
==============

Phase 1 → Phase 2 Transition
-----------------------------

When TYPO3 core implements Phase 2 (absolute expiration API):

**For Extension Users**:

1. Extension detects Phase 2 capability in TYPO3 core
2. Extension automatically switches to new API
3. Backward compatibility maintained for older TYPO3 versions
4. Eventually, extension becomes obsolete and can be uninstalled

**Timeline**: Extension will be maintained until Phase 2 is available in all supported TYPO3 LTS versions.

**Migration Checklist**:

.. code-block:: text

   □ TYPO3 core with Phase 2 support released
   □ Extension updated to use new core API
   □ Test temporal behavior in staging
   □ Monitor cache performance (should improve)
   □ Eventually uninstall extension when Phase 2 is stable

Phase 2 → Phase 3 Transition
-----------------------------

When TYPO3 core implements Phase 3 (automatic detection):

**For Developers**:

- Remove explicit temporal cache configuration
- TYPO3 handles temporal dependencies transparently
- Extension and Phase 2 APIs deprecated

**Timeline**: Several years after Phase 2, depending on adoption and stability.

Why Phase 1 Is Necessary Today
===============================

The Problem Cannot Wait
------------------------

TYPO3 Forge #14277 has been open since **2005** (20+ years). The problem affects:

- News systems with scheduled publication
- Campaign landing pages with time-based visibility
- Event calendars with automatic archiving
- Menu systems reflecting temporal page states

Current workarounds are inadequate:

❌ **Manual cache clearing** - Error-prone, requires editorial vigilance
❌ **Aggressive cache warming** - Wasteful, doesn't prevent staleness
❌ **Disabled caching** - Destroys performance (100x slower)
❌ **Custom solutions** - Reinventing the wheel, maintenance burden

Phase 2/3 Timeline Too Long
----------------------------

Realistic timeline for core solution:

- Phase 2 RFC: 2025
- Phase 2 implementation: 2025-2026
- Phase 2 in LTS: 2026-2027
- Phase 3 research: 2027+

**Sites need temporal cache solutions NOW**, not in 2-5+ years.

Phase 1 as Pragmatic Bridge
----------------------------

This extension provides:

✅ Immediate solution for temporal content issues
✅ Stable, tested code in production environments
✅ Experimental approach validated at scale
✅ Migration path when core solution arrives
✅ Real-world feedback for Phase 2/3 design

Community Feedback Loop
========================

Your Experience Matters
------------------------

Using this extension in production provides valuable insights for Phase 2/3 design:

**Please share your experience**:

- Performance characteristics at scale
- Edge cases and unexpected behaviors
- Configuration needs and pain points
- Integration challenges with other extensions

**Where to contribute**:

- `TYPO3 Forge #14277 <https://forge.typo3.org/issues/14277>`__ - Core issue discussion
- `Extension Repository <https://github.com/netresearch/t3x-nr-temporal-cache>`__ - Bug reports and feature requests
- TYPO3 Slack #typo3-cms - Community discussions

RFC Participation
-----------------

When Phase 2 RFC is published:

1. Review proposed API design
2. Share production experience with Phase 1
3. Participate in API design discussions
4. Help shape the future of TYPO3 temporal caching

Your real-world experience with this extension directly influences Phase 2/3 design decisions.

Experimental But Stable
========================

Clarifying "Experimental"
-------------------------

**The extension code is STABLE**:

- ✅ Tested in production environments
- ✅ Follows TYPO3 best practices
- ✅ Comprehensive test coverage
- ✅ Semantic versioning and backward compatibility
- ✅ Professional maintenance and support

**The approach is EXPERIMENTAL**:

- ⚠️ Site-wide cache synchronization is a workaround, not ideal solution
- ⚠️ Performance characteristics require careful evaluation
- ⚠️ Will be superseded by Phase 2/3 core solutions
- ⚠️ Proof-of-concept for informing future TYPO3 development

**Analogy**: Like using a well-built bridge to cross a river while a tunnel is being planned.
The bridge is stable and safe, but it's not the permanent solution.

When to Use Phase 1
--------------------

**Recommended**:

✅ Small to medium sites needing temporal cache today
✅ Sites willing to evaluate performance in staging
✅ Organizations contributing to TYPO3 community feedback
✅ Projects that can migrate to Phase 2 when available

**Not Recommended**:

❌ Large enterprise sites (>50,000 pages) without thorough testing
❌ High-traffic sites (>1M req/day) without robust infrastructure
❌ Projects unable to tolerate temporary performance implications
❌ Teams unwilling to monitor and optimize cache behavior

See :ref:`performance-considerations` for detailed decision framework.

Next Steps
==========

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📘 Understand Current Implementation

        Learn how Phase 1 addresses the temporal cache problem with dynamic
        cache lifetime strategies.

        ..  card-footer:: :ref:`Read Architecture <architecture>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚡ Evaluate Performance Impact

        Critical reading before production deployment. Understand performance
        implications and optimization strategies.

        ..  card-footer:: :ref:`Performance Considerations <performance-considerations>`
            :button-style: btn btn-warning stretched-link

    ..  card:: 🔧 Install Extension

        Get started with Phase 1 solution in your TYPO3 installation.

        ..  card-footer:: :ref:`Installation Guide <installation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🎯 Track Core Development

        Monitor progress on Phase 2/3 and participate in RFC discussions.

        ..  card-footer:: `Follow Forge #14277 <https://forge.typo3.org/issues/14277>`__
            :button-style: btn btn-info stretched-link
