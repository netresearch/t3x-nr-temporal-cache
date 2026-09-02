.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

Complete configuration reference for the TYPO3 Temporal Cache extension.

Overview
========

The extension provides flexible configuration options accessible through:

- **Extension Manager**: Admin Tools → Extensions → nr_temporal_cache → Configure
- **Backend Module**: Tools → Temporal Cache → Wizard (with guided presets)

All configuration is **optional**. The extension works out of the box with sensible defaults (global scoping with dynamic timing).

.. important::
   **Performance Impact**: Configuration choices significantly affect cache performance.

   Read :ref:`performance-considerations` before production deployment to understand
   trade-offs between optimization strategies.

Configuration Sections
======================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 🎯 Optimization Strategies

        Configure scoping, timing, and harmonization strategies to optimize cache
        performance for your site size and traffic patterns.

        **Covers**: Scoping (global/per-page/per-content), Timing (dynamic/scheduler/hybrid),
        Time harmonization slots

        ..  card-footer:: :ref:`Strategy Configuration <configuration-strategies>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚙️ Advanced Options

        Fine-tune cache lifetime limits, enable debug logging, and configure scheduler
        tasks for background processing.

        **Covers**: Cache lifetime cap, debug logging, scheduler task setup and intervals

        ..  card-footer:: :ref:`Advanced Settings <configuration-advanced>`
            :button-style: btn btn-info stretched-link

    ..  card:: 📋 Configuration Examples

        Pre-configured presets for common site profiles and real-world configuration
        scenarios with complete examples.

        **Covers**: Small/Medium/Large/High-traffic presets, Multi-language sites,
        News/Event sites, E-commerce

        ..  card-footer:: :ref:`Examples & Presets <configuration-examples>`
            :button-style: btn btn-success stretched-link

    ..  card:: 🔧 Troubleshooting

        Diagnose and resolve common configuration issues including cache synchronization
        problems and performance concerns.

        **Covers**: Cache not updating, High cache invalidation rate, Scheduler issues,
        Harmonization problems

        ..  card-footer:: :ref:`Configuration Troubleshooting <configuration-troubleshooting>`
            :button-style: btn btn-warning stretched-link

Quick Reference
===============

Default Configuration
---------------------

Out of the box, the extension uses:

.. code-block:: text

   Scoping:        global (all pages invalidated)
   Timing:         dynamic (checks on every cache generation)
   Harmonization:  disabled (precise transition times)

This works well for small sites (<1,000 pages) but should be optimized for larger deployments.

Recommended for Large Sites
----------------------------

For sites with >10,000 pages or >50 temporal transitions/day:

.. code-block:: text

   Scoping:        per-content (99.7% reduction)
   Timing:         scheduler (zero per-page overhead)
   Harmonization:  enabled (98% fewer transitions)

See :ref:`configuration-examples` for complete configuration examples.

Configuration Methods
=====================

Extension Manager
-----------------

.. code-block:: text

   1. Admin Tools → Extensions
   2. Find "nr_temporal_cache"
   3. Click "Configure" icon
   4. Adjust settings
   5. Save

Backend Module Wizard
----------------------

.. code-block:: text

   1. Tools → Temporal Cache
   2. Click "Configuration Wizard" tab
   3. Select site profile (Small/Medium/Large/Custom)
   4. Choose optimization strategies
   5. Preview and test configuration
   6. Apply or export as PHP

For guided setup with performance calculations, use the :ref:`backend-wizard`.

PHP Configuration
-----------------

Add to ``config/system/additional.php`` or ``typo3conf/system/additional.php``:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => [
           'strategy' => 'per-content',
           'use_refindex' => true,
       ],
       'timing' => [
           'strategy' => 'scheduler',
           'scheduler_interval' => 60,
       ],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 300,
       ],
   ];

Next Steps
==========

- :ref:`configuration-strategies` - Configure optimization strategies
- :ref:`backend-wizard` - Use guided configuration wizard
- :ref:`performance-considerations` - Understand performance implications
- :ref:`phases` - Learn about experimental vs stable status

.. toctree::
   :hidden:

   Strategies
   Advanced
   Examples
   Troubleshooting
