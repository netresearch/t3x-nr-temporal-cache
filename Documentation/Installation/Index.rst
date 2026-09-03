.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

.. _installation-requirements:

Requirements
============

**Minimum**, as declared in :file:`composer.json`:

- PHP 8.1 or newer
- TYPO3 ``^12.4 || ^13.0 || ^14.0``
- ``typo3/cms-scheduler`` and ``typo3/cms-reports``, both pulled in as
  dependencies

**Database**

- Four indexes are added through :file:`ext_tables.sql`, two on ``pages`` and
  two on ``tt_content``; run the database compare after installing
- No tables and no columns are added — the extension reads the standard
  ``starttime`` and ``endtime`` fields

.. _installation-compatibility:

Compatibility
=============

The combinations below are the ones the CI matrix in
:file:`.github/workflows/ci.yml` builds.

.. list-table::
   :header-rows: 1
   :widths: 20 20 20 40

   * - TYPO3 version
     - PHP version
     - Status
     - Notes
   * - 12.4+
     - 8.1 - 8.4
     - ✅ Supported
     - PHP 8.5 is excluded from this cell in CI
   * - 13.0+
     - 8.2 - 8.5
     - ✅ Supported
     - PHP 8.1 is excluded from this cell in CI
   * - 14.0+
     - 8.3 - 8.5
     - ✅ Supported
     - PHP 8.1 and 8.2 are excluded from this cell in CI
   * - 11.5
     - —
     - ⚠️ Not supported
     - Below the ``typo3/cms-core: ^12.4`` requirement

.. _installation-methods:

Installation methods
====================

Method 1: Composer (recommended)
--------------------------------

.. code-block:: bash

   composer require netresearch/nr-temporal-cache
   vendor/bin/typo3 extension:setup
   vendor/bin/typo3 cache:flush

:bash:`extension:setup` performs the database migrations, which is what creates
the four indexes.

Method 2: TER (Extension Repository)
------------------------------------

1. Go to :guilabel:`Admin Tools → Extensions`
2. Click :guilabel:`Get Extensions`
3. Search for ``nr_temporal_cache``
4. Click :guilabel:`Import and Install`
5. Activate the extension

Method 3: Manual installation
-----------------------------

1. Download from `GitHub <https://github.com/netresearch/t3x-nr-temporal-cache/releases>`__
2. Extract to :file:`typo3conf/ext/nr_temporal_cache/` (classic mode) or
   :file:`packages/nr_temporal_cache/` (Composer mode)
3. Activate in the Extension Manager
4. Clear all caches

.. _installation-configuration:

Configuration
=============

Zero configuration
------------------

The extension works immediately after installation.
It automatically:

- Registers the PSR-14 listener for ``ModifyCacheLifetimeForPageEvent`` under
  the identifier ``temporal-cache/modify-cache-lifetime``
- Monitors the ``pages`` and ``tt_content`` tables
- Caps the page cache lifetime at the next transition, using global scoping and
  dynamic timing

All twelve settings and their defaults are listed in :ref:`configuration`.

Optional: Monitor custom tables
--------------------------------

If you have custom extension tables with ``starttime/endtime`` fields, you can
register them for temporal cache monitoring using the ``TemporalMonitorRegistry``.

**Recommended:** Configure in ``Configuration/Services.yaml`` (modern dependency injection):

.. code-block:: yaml

   services:
     # Register custom news table
     my_ext_news_table_registration:
       class: 'Closure'
       factory: ['@Netresearch\TemporalCache\Service\TemporalMonitorRegistry', 'registerTable']
       arguments:
         - 'tx_news_domain_model_news'
         - ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid']

     # Register custom event table
     my_ext_events_table_registration:
       class: 'Closure'
       factory: ['@Netresearch\TemporalCache\Service\TemporalMonitorRegistry', 'registerTable']
       arguments:
         - 'tx_events_domain_model_event'
         - ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid']

**Alternative:** For ext_localconf.php (when DI not available):

.. code-block:: php

   <?php
   use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
   use TYPO3\CMS\Core\Utility\GeneralUtility;

   // Only use makeInstance() in ext_localconf.php where DI is not yet available
   $registry = GeneralUtility::makeInstance(TemporalMonitorRegistry::class);
   $registry->registerTable('tx_news_domain_model_news', [
       'uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'
   ]);

**Field requirements**

``registerTable()`` rejects a registration that misses one of these:

- ``uid``
- ``starttime``
- ``endtime``

Recommended in addition, because the queries and the backend module use them:

- ``pid`` — parent page id
- ``hidden`` and ``deleted`` — the transition queries exclude records whose
  TCA ``delete`` and ``enablecolumns.disabled`` fields are set
- ``sys_language_uid`` — the queries filter on the language of the context
- a label field such as ``title``, ``header`` or ``name`` for display

Passing an empty field list applies the default list
``uid, pid, title, starttime, endtime, hidden, deleted, sys_language_uid``.

**Default tables**

``pages`` and ``tt_content`` are monitored out of the box.
Re-registering either of them throws an exception.

Each registered table adds two ``MIN()`` queries to a transition lookup — see
:ref:`configuration-troubleshooting-performance`.

Optional: Adjust the maximum lifetime
--------------------------------------

``advanced.default_max_lifetime`` caps the cache lifetime the extension
calculates, and is the lifetime used when no transition is scheduled at all.

**Default**: 86400 seconds (24 hours)

Configure it in :guilabel:`Admin Tools → Extensions → nr_temporal_cache →
Configure`, under :guilabel:`Default Cache Lifetime (seconds)`, or in PHP:

.. code-block:: php
   :caption: config/system/additional.php

   <?php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['default_max_lifetime'] = 43200;

TypoScript ``config.cache_period`` takes precedence over this setting; see
:ref:`configuration-advanced`.

.. _installation-verification:

Verification
============

Check the setup
---------------

.. code-block:: bash

   vendor/bin/typo3 temporalcache:verify

The command confirms that the indexes and the columns the queries rely on
exist, and that the configured strategy names are valid.
:guilabel:`System → Reports → Temporal Cache` shows the same configuration from
the backend.

Test scheduled content
----------------------

1. Create a test page:

   - Set :guilabel:`Start` to 5 minutes in the future
   - Enable :guilabel:`In menu`
   - Save

2. Check the frontend menu — the page must not appear yet

3. Wait until the start time and reload — the page appears without any cache
   being cleared by hand

The page cache lifetime is capped at the transition, so the first request after
the start time regenerates the page.

Test expiring content
---------------------

1. Create a content element with :guilabel:`Stop` 5 minutes in the future
2. View the page — the element is visible
3. Wait until the stop time and reload — the element is gone

Inspect what the extension calculated
-------------------------------------

.. code-block:: text

   advanced.debug_logging = 1

Each page cache generation then logs the lifetime that was written, the
uncapped value, and which maximum applied.
See :ref:`configuration-advanced`.

.. _installation-troubleshooting:

Troubleshooting
===============

Content does not update
-----------------------

1. Confirm the extension is loaded::

      vendor/bin/typo3 extension:list

2. Confirm the timing strategy.
   ``scheduler`` always depends on the scheduler task, and ``hybrid`` does when
   at least one of ``timing.hybrid.pages`` and ``timing.hybrid.content`` is set
   to ``scheduler``.
   In those cases confirm the task exists and cron is running it — see
   :ref:`scheduler-setup`.
   A ``hybrid`` configuration with both rules on ``dynamic`` needs no task.

3. Clear all caches::

      vendor/bin/typo3 cache:flush

4. Work through :ref:`configuration-troubleshooting`

Slow page generation
--------------------

The dynamic timing strategy runs two ``MIN()`` queries per monitored table on
every page cache generation.
Confirm the indexes exist:

.. code-block:: sql

   SHOW INDEX FROM pages WHERE Key_name LIKE 'idx_temporalcache%';
   SHOW INDEX FROM tt_content WHERE Key_name LIKE 'idx_temporalcache%';

If they are missing, run the database compare — the definitions ship with the
extension:

.. code-block:: bash

   vendor/bin/typo3 extension:setup

To log the extension's own messages into a separate file:

.. code-block:: php
   :caption: config/system/additional.php

   <?php

   $GLOBALS['TYPO3_CONF_VARS']['LOG']['Netresearch']['TemporalCache']['writerConfiguration'] = [
       \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
           \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
               'logFile' => 'typo3temp/var/log/temporal_cache.log',
           ],
       ],
   ];

Workspace and language
----------------------

Every transition query resolves the workspace and the language from the
``Context`` API and filters on both.
A transition scheduled in another language therefore does not shorten the
lifetime of the page you are looking at.

.. _installation-uninstallation:

Uninstallation
==============

The extension adds no tables and no columns.
It does add four indexes to ``pages`` and ``tt_content``
(``idx_temporalcache_starttime`` and ``idx_temporalcache_endtime``, see
:file:`ext_tables.sql`); remove them in :guilabel:`Admin Tools → Maintenance →
Analyze Database Structure` after uninstalling if you do not want to keep them.

.. code-block:: bash

   composer remove netresearch/nr-temporal-cache
   vendor/bin/typo3 cache:flush

TYPO3 then reverts to its default behaviour: temporal content becomes visible
when the page cache happens to expire.

Next steps
==========

- :ref:`configuration` - Every setting and its default
- :ref:`architecture` - How the listener, strategies and repository fit together
- `GitHub issues <https://github.com/netresearch/t3x-nr-temporal-cache/issues>`__ - Report problems
