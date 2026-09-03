.. include:: /Includes.rst.txt

.. _configuration-examples:

==================
Examples & presets
==================

Complete configurations, and what each of them actually changes.

For help choosing between them, see :ref:`performance-strategies` and
:ref:`decision-guide`.

.. note::
   Every example that sets ``timing.strategy`` to ``scheduler``, or routes a
   content type to the scheduler through ``hybrid``, depends on the scheduler
   task.
   Read :ref:`scheduler-setup` first — this version registers no task type, so
   nothing processes those transitions yet.

.. _configuration-presets:

Presets offered by the wizard
=============================

The Wizard tab of the backend module offers these three presets.
The values below are the ones it shows; the wizard does not write them — apply
them in the Extension Manager or in :file:`config/system/additional.php`.

Simple
------

Global scoping, dynamic timing, no harmonization — the shipped defaults.

.. code-block:: text

   scoping.strategy = global
   timing.strategy = dynamic
   harmonization.enabled = 0

Every temporal transition flushes every page cache, and the page cache lifetime
is capped at the next transition anywhere on the site.
Nothing beyond the extension itself has to be set up.

Balanced
--------

.. code-block:: text

   scoping.strategy = per-page
   timing.strategy = hybrid
   harmonization.enabled = 1
   harmonization.slots = 00:00,06:00,12:00,18:00

With ``timing.hybrid.pages`` and ``timing.hybrid.content`` left at their
defaults, page transitions stay dynamic and content transitions are routed to
the scheduler task.
The cache lifetime follows the page rule, so it is still calculated on every
page cache generation, narrowed to page transitions site-wide plus content
transitions on the page being rendered.

Aggressive
----------

.. code-block:: text

   scoping.strategy = per-content
   scoping.use_refindex = 1
   timing.strategy = scheduler
   harmonization.enabled = 1
   harmonization.slots = 00:00,04:00,08:00,12:00,16:00,20:00

The extension stops modifying the cache lifetime altogether.
Invalidation happens only when the scheduler task processes a transition, and
then it flushes exactly the pages on which the changed content appears,
resolved through ``sys_refindex``.
This is the combination in which per-content scoping pays off — and the one
that does nothing at all without the scheduler task.

.. _configuration-scenarios:

Worked configurations
=====================

Exact publication times matter
------------------------------

Flash sales, embargoed articles: the transition must happen at the time the
editor entered.

.. code-block:: text

   scoping.strategy = per-page
   timing.strategy = dynamic
   harmonization.enabled = 0

Dynamic timing is the only strategy that expires the cache at the transition
itself.
Harmonization stays off so no timestamp is moved.
Scoping is ``per-page`` because under dynamic timing the scoping strategy only
supplies the next-transition timestamp, and ``per-page`` is the one that
narrows that timestamp to the page being rendered; ``per-content`` would return
the site-wide value here.

Many content transitions, menus must stay current
-------------------------------------------------

.. code-block:: text

   scoping.strategy = per-content
   scoping.use_refindex = 1
   timing.strategy = hybrid
   timing.hybrid.pages = dynamic
   timing.hybrid.content = scheduler

Page transitions keep their dynamic lifetime, which is what keeps menus
correct.
Content transitions are handed to the scheduler task, which flushes only the
pages the content appears on.
Note that the lifetime query still runs on every page cache generation: it
follows the page rule, and that rule is ``dynamic`` here.

Fewer, grouped cache flushes
----------------------------

.. code-block:: text

   harmonization.enabled = 1
   harmonization.slots = 00:00,06:00,12:00,18:00
   harmonization.tolerance = 3600

Transitions within an hour of a slot are moved onto it, so several records
share one transition time instead of each having its own.
Records further than an hour from every slot keep their original times.
Harmonization is applied when it is invoked — through the
:guilabel:`Harmonize selected` action in the Content tab of the backend module,
or through :bash:`vendor/bin/typo3 temporalcache:harmonize` — not when editors
save a record.

Publication at one fixed time of day
------------------------------------

.. code-block:: text

   harmonization.enabled = 1
   harmonization.slots = 09:00
   harmonization.tolerance = 3600

With a single slot, only timestamps within an hour of 09:00 are moved onto it.
Raise ``harmonization.tolerance`` to cover the spread you actually want to
absorb; a timestamp at 20:00 is 11 hours from the slot and needs a tolerance of
at least 39600 to be moved.

Multi-language site
-------------------

No language-specific setting exists.
Every transition query filters on the language of the current context, and the
cache tags the scoping strategies emit are page-based, so language handling
needs no configuration.

.. _configuration-examples-php:

PHP configuration
=================

Complete configuration
----------------------

.. code-block:: php
   :caption: config/system/additional.php

   <?php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache'] = [
       'scoping' => [
           'strategy' => 'per-content',
           'use_refindex' => true,
       ],
       'timing' => [
           'strategy' => 'scheduler',
       ],
       'harmonization' => [
           'enabled' => true,
           'slots' => '00:00,06:00,12:00,18:00',
           'tolerance' => 3600,
           'auto_round' => false,
       ],
       'advanced' => [
           'default_max_lifetime' => 86400,
           'debug_logging' => false,
       ],
   ];

Every key is optional; the getters in
:php:`Netresearch\TemporalCache\Configuration\ExtensionConfiguration` supply the
documented default for anything absent.

Environment-specific configuration
----------------------------------

.. code-block:: php
   :caption: config/system/additional.php

   <?php

   if (getenv('TYPO3_CONTEXT') === 'Development') {
       $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['debug_logging'] = true;
   }

:file:`additional.php` is read after the settings written by the Extension
Manager, so assignments here override them.

Next steps
==========

- :ref:`configuration-strategies` - What each setting does
- :ref:`configuration-advanced` - Cache lifetime cap, debug logging, scheduler task
- :ref:`configuration-troubleshooting` - Diagnose configuration issues
