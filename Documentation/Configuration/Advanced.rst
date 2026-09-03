.. include:: /Includes.rst.txt

.. _configuration-advanced:

================
Advanced options
================

Cache lifetime cap, debug logging and the scheduler task.

.. _configuration-advanced-settings:

Advanced settings
=================

.. confval:: advanced.default_max_lifetime

   :type: integer
   :Default: ``86400``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['default_max_lifetime']

   Upper bound in seconds for the cache lifetime this extension calculates.

   Two places read it:

   #. :php:`DynamicTimingStrategy::getCacheLifetime()` returns this value when
      no transition is scheduled at all, and caps the calculated lifetime at it
      otherwise.
   #. :php:`TemporalCacheLifetime` caps the value it finally writes to the
      event, using the hierarchy below.

   **Configuration hierarchy**

   :php:`TemporalCacheLifetime::determineMaxLifetime()` takes the first value
   that applies:

   #. TypoScript ``config.cache_period``, when it is set and greater than 0
   #. This setting, when it is greater than 0
   #. 86400 seconds

   A value of 0 or less is therefore not a way to lift the cap — it is skipped
   and 86400 is used.

   **Prefer TypoScript for a site-wide period**

   .. code-block:: typoscript
      :caption: setup.typoscript

      config.cache_period = 43200

   TypoScript wins over this setting and applies to all other TYPO3 cache
   handling as well.
   Configure the extension setting only when temporal cache should have a
   different maximum than the rest of the site.

   Examples
   --------

   .. code-block:: text

      # Default: 24 hours
      advanced.default_max_lifetime = 86400

      # Shorter: 12 hours
      advanced.default_max_lifetime = 43200

      # Longer: 48 hours
      advanced.default_max_lifetime = 172800

   With ``advanced.debug_logging`` enabled, the listener records
   which source it used in the ``max_from_typoscript``,
   ``max_from_extension_config`` and ``max_lifetime`` keys of its log entry.

.. confval:: advanced.debug_logging

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['debug_logging']

   Gates the extension's diagnostic log entries.
   Three call sites check it:

   :php:`TemporalCacheLifetime`
      Debug entry per page cache generation in which a lifetime was set, with
      the keys ``lifetime``, ``uncapped_lifetime``, ``max_lifetime``,
      ``max_from_typoscript``, ``max_from_extension_config``,
      ``timing_strategy`` and ``scoping_strategy``.

   :php:`SchedulerTimingStrategy`
      Info entry per processed transition, with the flushed cache tags and the
      active scoping strategy.

   :php:`TemporalCacheSchedulerTask`
      Debug entries when the task starts and when it finds no transitions in
      the range it examined.

   Errors are logged regardless of this setting, and so is the completion entry
   of the scheduler task, so a failing transition is visible without switching
   it on.

   **Log location**

   Log entries go to TYPO3's configured log writers; the default file writer
   writes to :file:`var/log/typo3_*.log`.

   .. code-block:: bash

      grep TemporalCache var/log/typo3_*.log

   .. warning::
      Enable only for debugging.
      The listener writes one entry per page cache generation.

   Example
   -------

   .. code-block:: text

      advanced.debug_logging = 1

.. _scheduler-setup:

Scheduler task
==============

The ``scheduler`` and ``hybrid`` timing strategies do not flush caches
themselves.
They rely on :php:`Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask`,
which finds the transitions that occurred since its last run and hands each one
to the active timing strategy.

:file:`ext_localconf.php` registers the task type in
:php:`$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']`, the list
the Scheduler module builds its task selection from, so
:guilabel:`Scheduler → Create new task` offers **Temporal Cache: Process
transitions**.

.. note::
   Registering the task type is not the same as scheduling it.
   Until a task is created and runs,
   ``timing.strategy = scheduler`` leaves the page cache lifetime untouched and
   has nothing that flushes it; ``hybrid`` still calculates a lifetime for
   whichever content type is routed to ``dynamic``, but the transitions routed
   to the scheduler wait for the task; and the targeted cache tags of
   ``per-page`` and ``per-content`` scoping stay unused, because only the task
   triggers them.
   ``timing.strategy = dynamic`` needs no task at all.

Creating the task is not enough on its own either — the Scheduler needs a cron
entry that runs it:

.. code-block:: bash
   :caption: crontab

   * * * * * /usr/bin/php /var/www/html/vendor/bin/typo3 scheduler:run

:bash:`scheduler:run` executes every task that is due.

.. _configuration-advanced-verify:

Verifying the setup
===================

:bash:`vendor/bin/typo3 temporalcache:verify` runs four checks:

#. An index whose leading column is ``starttime``, and one whose leading column
   is ``endtime``, on both ``pages`` and ``tt_content``
#. ``scoping.strategy`` is one of ``global``, ``per-page``, ``per-content`` and
   ``timing.strategy`` is one of ``dynamic``, ``scheduler``, ``hybrid``
#. The time slot configuration, when harmonization is enabled
#. The columns the queries rely on: ``starttime``, ``endtime``, ``hidden``,
   ``deleted``, ``sys_language_uid`` on both tables, plus ``pid`` on
   ``tt_content``

It exits with 0 when every check passes and 1 otherwise.
Add ``--verbose`` for the per-field table of the schema check.

Next steps
==========

- :ref:`configuration-strategies` - Scoping, timing and harmonization settings
- :ref:`configuration-examples` - Complete configuration examples
- :ref:`configuration-troubleshooting` - Diagnose configuration issues
