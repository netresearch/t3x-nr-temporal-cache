.. include:: /Includes.rst.txt

==========================
Temporal Cache Management
==========================

:Extension key:
   nr_temporal_cache

:Package name:
   netresearch/nr-temporal-cache

:Version:
   |release|

:Language:
   en

:Author:
   Netresearch DTT GmbH

:License:
   This document is published under the
   `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
   license.

:Rendered:
   |today|

----

Automatic cache invalidation for time-based content in TYPO3.

Addresses `TYPO3 Forge Issue #14277 <https://forge.typo3.org/issues/14277>`__:
Menus and content with starttime/endtime update automatically when time passes,
without manual cache clearing.

.. important::
   **Extension Status**: Beta (version 0.9.0, state ``beta`` in
   :file:`ext_emconf.php`). There is no stable release yet; the only published
   tag is the pre-release ``v0.9.0-alpha1``. Test before using in production.

   **Approach**: This is an **experimental solution** implementing Phase 1
   of a three-phase approach. The extension provides a pragmatic workaround
   until TYPO3 core provides native temporal cache support (Phase 2/3).

   See :ref:`phases` for the complete roadmap and migration path.

----

Documentation
=============

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📘 Introduction

        Get started with the Temporal Cache extension. Understand what problem
        it solves, how it works, and whether it's right for your TYPO3 site.

        ..  card-footer:: :ref:`Read Introduction <introduction>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚡ Performance Considerations

        **CRITICAL**: Read this before production deployment. Understand performance
        implications, site-wide cache synchronization, and optimization strategies.

        ..  card-footer:: :ref:`Performance Guide <performance-considerations>`
            :button-style: btn btn-warning stretched-link

    ..  card:: 🔧 Installation

        Complete installation guide for TYPO3 v12.4 LTS and v13.4 LTS including
        Composer setup, extension activation, and verification steps.

        ..  card-footer:: :ref:`Installation Guide <installation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🎯 Configuration

        Configure optimization strategies, timing modes, and monitoring to match
        your site's requirements and infrastructure.

        ..  card-footer:: :ref:`Configuration Reference <configuration>`
            :button-style: btn btn-info stretched-link

    ..  card:: 🖥️ Backend Module

        Monitor temporal transitions, analyze cache performance, and validate
        extension functionality through the TYPO3 backend interface.

        ..  card-footer:: :ref:`Backend Module <backend-module>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 📊 Reports Module

        Track transition history, cache hit rates, and system health through
        integrated TYPO3 Reports module analytics.

        ..  card-footer:: :ref:`Reports Module <reports-module>`
            :button-style: btn btn-info stretched-link

    ..  card:: 🏗️ Architecture

        Deep dive into root cause analysis, implementation approach, and how
        Phase 1 addresses the temporal content problem.

        ..  card-footer:: :ref:`Architecture Details <architecture>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔮 Roadmap & Phases

        Understand the three-phase solution approach, migration path, and future
        TYPO3 core integration (Phase 2/3). Learn about experimental vs stable status.

        ..  card-footer:: :ref:`View Roadmap <phases>`
            :button-style: btn btn-success stretched-link

.. Meta Menu

.. toctree::
   :hidden:

   Introduction/Index
   Performance/Index
   Installation/Index
   Configuration/Index
   Backend/Index
   Administrator/ReportsModule
   Architecture/Index
   Phases/Index
   Sitemap
