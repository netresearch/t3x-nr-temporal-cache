.. include:: /Includes.rst.txt

.. _configuration-troubleshooting:

===============
Troubleshooting
===============

Diagnose and resolve common configuration issues.

.. _configuration-troubleshooting-first:

Start here
==========

.. code-block:: bash

   vendor/bin/typo3 temporalcache:verify

The command checks the indexes and columns the queries need, the two strategy
names, and the time slots when harmonization is enabled.
It reports which check failed and exits with 1.
See :ref:`configuration-advanced-verify` for the full list of checks.

.. _configuration-troubleshooting-not-updating:

Cache not updating
==================

**Symptoms**

- Temporal content does not appear or disappear at the scheduled time
- Menus show pages whose ``starttime`` has not been reached

**Checks**

#. **Which timing strategy is active?**

   .. code-block:: bash

      vendor/bin/typo3 temporalcache:analyze

   Only ``dynamic`` expires the cache by itself.
   ``scheduler`` relies entirely on the scheduler task, and ``hybrid`` does for
   whichever content type is routed to it, so check that the task exists in the
   Scheduler module and that cron is running it.
   See :ref:`scheduler-setup`.

#. **Do the indexes exist?**

   The extension ships them in :file:`ext_tables.sql`; TYPO3 creates them
   during the database compare, not at installation time.

   .. code-block:: sql

      SHOW INDEX FROM pages WHERE Key_name LIKE 'idx_temporalcache%';
      SHOW INDEX FROM tt_content WHERE Key_name LIKE 'idx_temporalcache%';

   Expected on both tables: ``idx_temporalcache_starttime`` over
   ``(starttime, sys_language_uid)`` and ``idx_temporalcache_endtime`` over
   ``(endtime, sys_language_uid)``.
   If they are missing, run the database compare rather than creating them by
   hand:

   .. code-block:: bash

      vendor/bin/typo3 extension:setup

   :guilabel:`Admin Tools → Maintenance → Analyze Database Structure` does the
   same from the backend.

#. **Is the record actually visible?**

   The transition queries skip records that are deleted or hidden, and they
   filter on the language of the current context.
   A hidden record's ``starttime`` never triggers anything.

#. **Turn on debug logging.**

   .. code-block:: text

      advanced.debug_logging = 1

   .. code-block:: bash

      grep TemporalCache var/log/typo3_*.log | tail -50

   With ``dynamic`` timing, one entry per page cache generation shows the
   lifetime that was written and which maximum capped it.

.. _configuration-troubleshooting-performance:

High database load
==================

**Symptoms**

- Slow page generation with ``timing.strategy = dynamic``
- Many ``MIN(starttime)`` / ``MIN(endtime)`` queries in the slow query log

**What the extension queries**

The dynamic strategy runs two ``MIN()`` queries per monitored table — one for
``starttime``, one for ``endtime`` — on every page cache generation.
With the two default tables that is four queries.
Each table registered through ``TemporalMonitorRegistry`` adds two more.

The site-wide lookup used by ``global`` and ``per-content`` scoping is cached
for the duration of the request; the two lookups of ``per-page`` scoping are
not.

**Options**

#. Make sure the indexes above exist — without them these are full table scans.
#. Set ``timing.strategy = scheduler`` to remove the queries from page
   generation entirely.
   This needs the scheduler task; read :ref:`scheduler-setup` first.
#. Check the query plan:

   .. code-block:: sql

      EXPLAIN SELECT MIN(starttime) FROM pages
      WHERE starttime > UNIX_TIMESTAMP()
        AND hidden = 0 AND deleted = 0
        AND sys_language_uid = 0;

   The plan should use one of the ``idx_temporalcache_*`` indexes instead of
   scanning the table.

Changing ``scoping.strategy`` does not reduce this load: under dynamic timing
every scoping strategy performs the same kind of lookup, and ``per-content``
performs the site-wide one.

.. _configuration-troubleshooting-harmonization:

Harmonization not working
=========================

**Symptoms**

- The Content tab shows no harmonization column or no suggestions
- Timestamps stay where they were

**Checks**

#. ``harmonization.enabled = 1``.
   While it is off, the service returns every timestamp unchanged and the
   backend module hides the column.

#. The slots parse.
   Entries must be ``HH:MM`` or ``H:MM`` with hours 0-23 and minutes 0-59;
   anything else is
   dropped silently, and with no valid slot left nothing is harmonized.
   :bash:`temporalcache:verify` reports the parsed slots when harmonization is
   enabled.

#. The tolerance is large enough.
   A timestamp is only moved when its nearest slot is at most
   ``harmonization.tolerance`` seconds away.

   .. code-block:: text

      Slots 00:00,12:00 with tolerance 3600:

      11:30 → nearest slot 12:00, 30 min away  → harmonized to 12:00
      10:30 → nearest slot 12:00, 90 min away  → left at 10:30

   ``harmonization.tolerance = 0`` harmonizes nothing at all.
   The label in :file:`ext_conf_template.txt` calls it "no limit", which is
   backwards.

#. The distance is measured within the day.
   23:30 is far from a ``00:00`` slot, not close to the next day's.

#. Harmonization is invoked, not automatic.
   Nothing rewrites timestamps when an editor saves a record.
   Use :guilabel:`Harmonize selected` in :guilabel:`Tools → Temporal Cache →
   Content`, or:

   .. code-block:: bash

      vendor/bin/typo3 temporalcache:harmonize

.. _configuration-troubleshooting-scheduler:

Scheduler task
==============

This version registers no scheduler task type, so the task cannot be created in
:guilabel:`System → Scheduler`.
:ref:`scheduler-setup` describes what that means for the ``scheduler`` and
``hybrid`` timing strategies.

If a task type has been registered on your installation, verify that the
Scheduler itself runs:

.. code-block:: bash

   crontab -l | grep scheduler
   vendor/bin/typo3 scheduler:run

.. _configuration-troubleshooting-values:

Configuration not taking effect
===============================

**Typos in strategy names are not errors.**
``ScopingStrategyFactory`` and ``TimingStrategyFactory`` activate the strategy
whose name matches the configured value and fall back to the highest-priority
tagged strategy — ``global`` and ``dynamic`` — when none does.
A misspelled ``per-contnet`` therefore behaves exactly like the default.
:bash:`temporalcache:verify` flags both values as ``INVALID``.

**Out-of-range values are clamped, not rejected.**
``advanced.default_max_lifetime`` of 0 or less is skipped in favour of 86400.

**Two configuration sources.**
The Extension Manager writes to :file:`config/system/settings.php`;
:file:`config/system/additional.php` is read afterwards, so an assignment there
overrides the Extension Manager value.
Check both files before concluding that a setting is ignored.

.. _configuration-troubleshooting-help:

Getting help
============

Collect before reporting:

.. code-block:: bash

   vendor/bin/typo3 extension:list | grep temporal_cache
   vendor/bin/typo3 temporalcache:verify
   vendor/bin/typo3 temporalcache:analyze
   grep TemporalCache var/log/typo3_*.log | tail -50

Report at `GitHub issues
<https://github.com/netresearch/t3x-nr-temporal-cache/issues>`__ with the TYPO3
version, the extension version and the output above.

Next steps
==========

- :ref:`configuration-strategies` - What each setting does
- :ref:`configuration-examples` - Complete configuration examples
- :ref:`backend-dashboard` - What the backend module reports
- :ref:`performance-considerations` - Performance implications
