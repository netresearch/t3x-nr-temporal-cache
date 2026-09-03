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
   **Extension Status**: Stable (version 1.0.0, state ``stable`` in
   :file:`ext_emconf.php`). The API covered by :ref:`api` follows Semantic
   Versioning from this release onwards.

   **Approach**: TYPO3's cache API has no absolute expiration, so the extension
   approximates one by shortening relative lifetimes or by flushing tags from a
   scheduled task. A solution inside TYPO3 core would need neither.

   See :ref:`phases` for what the approach cannot do, and what core would have to
   provide to make the extension unnecessary.

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

        Complete installation guide for TYPO3 v12.4 LTS, v13 and v14 including
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

        Deep dive into root cause analysis, the implementation approach, and how
        the extension addresses the temporal content problem.

        ..  card-footer:: :ref:`Architecture Details <architecture>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔮 Approach & Limits

        Why the extension works the way it does, what the approach cannot do, and
        what a solution inside TYPO3 core would have to provide.

        ..  card-footer:: :ref:`Approach and limits <phases>`
            :button-style: btn btn-success stretched-link

.. Meta Menu

.. toctree::
   :hidden:

   Introduction/Index
   Performance/Index
   Installation/Index
   Configuration/Index
   Backend/Index
   CommandLine/Index
   Administrator/ReportsModule
   Architecture/Index
   Api/Index
   Phases/Index
