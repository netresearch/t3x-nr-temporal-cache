.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

Reference for the twelve extension configuration settings of
``nr_temporal_cache``.

All of them are optional.
With no configuration the extension uses global scoping and dynamic timing, and
harmonization is off.

.. important::
   The choice of scoping and timing strategy changes what the extension does,
   not only how fast it does it.
   Read :ref:`configuration-strategies-how-settings-combine` before changing
   either, and :ref:`performance-considerations` before deploying the change.

.. _configuration-chapters:

Chapters
========

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 🎯 Optimization strategies

        Scoping, timing and harmonization settings, and how the three interact.

        **Covers**: ``scoping.strategy``, ``scoping.use_refindex``,
        ``timing.strategy``, ``timing.scheduler_interval``,
        ``timing.hybrid.pages``, ``timing.hybrid.content``,
        ``harmonization.enabled``, ``harmonization.slots``,
        ``harmonization.tolerance``, ``harmonization.auto_round``

        ..  card-footer:: :ref:`Strategy configuration <configuration-strategies>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚙️ Advanced options

        Cache lifetime cap, debug logging, and the state of the scheduler task.

        **Covers**: ``advanced.default_max_lifetime``,
        ``advanced.debug_logging``, :ref:`scheduler-setup`

        ..  card-footer:: :ref:`Advanced settings <configuration-advanced>`
            :button-style: btn btn-info stretched-link

    ..  card:: 📋 Examples & presets

        The presets the backend wizard offers, and worked configurations with
        what each of them changes.

        ..  card-footer:: :ref:`Examples & presets <configuration-examples>`
            :button-style: btn btn-success stretched-link

    ..  card:: 🔧 Troubleshooting

        Cache not updating, high database load, harmonization doing nothing,
        settings that appear to be ignored.

        ..  card-footer:: :ref:`Configuration troubleshooting <configuration-troubleshooting>`
            :button-style: btn btn-warning stretched-link

.. _configuration-defaults:

All settings at a glance
========================

Defaults as implemented in
:php:`Netresearch\TemporalCache\Configuration\ExtensionConfiguration`.

.. list-table::
   :header-rows: 1
   :widths: 34 16 22 28

   * - Setting
     - Type
     - Default
     - Accepted values
   * - ``scoping.strategy``
     - string
     - ``global``
     - ``global``, ``per-page``, ``per-content``
   * - ``scoping.use_refindex``
     - boolean
     - ``true``
     - ``0``, ``1``
   * - ``timing.strategy``
     - string
     - ``dynamic``
     - ``dynamic``, ``scheduler``, ``hybrid``
   * - ``timing.scheduler_interval``
     - integer
     - ``60``
     - seconds, raised to 60 when lower
   * - ``timing.hybrid.pages``
     - string
     - ``dynamic``
     - ``dynamic``, ``scheduler``
   * - ``timing.hybrid.content``
     - string
     - ``scheduler``
     - ``dynamic``, ``scheduler``
   * - ``harmonization.enabled``
     - boolean
     - ``false``
     - ``0``, ``1``
   * - ``harmonization.slots``
     - string
     - ``00:00,06:00,12:00,18:00``
     - comma-separated ``HH:MM``
   * - ``harmonization.tolerance``
     - integer
     - ``3600``
     - seconds; ``0`` harmonizes nothing
   * - ``harmonization.auto_round``
     - boolean
     - ``false``
     - ``0``, ``1``
   * - ``advanced.default_max_lifetime``
     - integer
     - ``86400``
     - seconds greater than 0
   * - ``advanced.debug_logging``
     - boolean
     - ``false``
     - ``0``, ``1``

.. _configuration-methods:

Where to configure
==================

Extension Manager
-----------------

.. code-block:: text

   1. Admin Tools → Extensions
   2. Find "nr_temporal_cache"
   3. Click the "Configure" icon
   4. Adjust the settings, grouped by scoping, timing, harmonization, advanced
   5. Save

The form is generated from :file:`ext_conf_template.txt`, and the values are
stored in :file:`config/system/settings.php`.

PHP configuration
-----------------

.. code-block:: php
   :caption: config/system/additional.php

   <?php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => [
           'strategy' => 'per-page',
           'use_refindex' => true,
       ],
       'timing' => [
           'strategy' => 'dynamic',
       ],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 3600,
       ],
   ];

:file:`additional.php` is read after :file:`config/system/settings.php`, so it
wins over the Extension Manager.

Backend module wizard
---------------------

:guilabel:`Tools → Temporal Cache → Wizard` walks through five steps — welcome,
analysis, presets, custom, summary — showing statistics for the current site
and recommending settings.

.. note::
   The wizard does not write configuration.
   It shows which values to use, as its own note says; apply them in the
   Extension Manager or in :file:`additional.php`.

.. toctree::
   :hidden:

   Strategies
   Advanced
   Examples
   Troubleshooting

Next steps
==========

- :ref:`configuration-strategies` - Every setting in detail
- :ref:`configuration-examples` - Complete configurations
- :ref:`performance-considerations` - Performance implications
- :ref:`backend-wizard` - The wizard in the backend module
