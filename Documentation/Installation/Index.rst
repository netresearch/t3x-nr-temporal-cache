.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

Requirements
============

**Minimum:**

- TYPO3 12.4 or 13.0+
- PHP 8.1+
- Composer (recommended)

**Database:**

- No schema changes required
- Uses standard TYPO3 ``starttime/endtime`` fields

Compatibility
=============

.. list-table::
   :header-rows: 1
   :widths: 20 20 20 40

   * - TYPO3 Version
     - PHP Version
     - Status
     - Notes
   * - 12.4+
     - 8.1 - 8.3
     - ✅ Fully supported
     - PSR-14 events available
   * - 13.0+
     - 8.2 - 8.3
     - ✅ Fully supported
     - Latest TYPO3 LTS
   * - 11.5
     - 7.4 - 8.2
     - ⚠️ Not supported
     - Missing required events
   * - 14.0 (future)
     - 8.2+
     - 🔄 Planned
     - May be superseded by Phase 2

Installation Methods
====================

Method 1: Composer (Recommended)
---------------------------------

Install via composer::

   composer req netresearch/temporal-cache

Activate extension::

   vendor/bin/typo3 extension:activate nr_temporal_cache

Clear cache::

   vendor/bin/typo3 cache:flush

Method 2: TER (Extension Repository)
-------------------------------------

1. Go to **Admin Tools > Extensions**
2. Click **Get Extensions**
3. Search for ``nr_temporal_cache``
4. Click **Import and Install**
5. Activate the extension

Method 3: Manual Installation
------------------------------

1. Download from `GitHub <https://github.com/netresearch/t3x-temporal-cache/releases>`__
2. Extract to ``typo3conf/ext/nr_temporal_cache/`` (classic mode) or ``packages/nr_temporal_cache/`` (composer mode)
3. Activate in Extension Manager
4. Clear all caches

Configuration
=============

Zero Configuration
------------------

**The extension works immediately after installation with no configuration required.**

It automatically:

- Registers PSR-14 event listener for ``ModifyCacheLifetimeForPageEvent``
- Monitors ``pages`` and ``tt_content`` tables for temporal transitions
- Adjusts cache lifetime dynamically

Optional: Monitor Custom Tables
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

**Field Requirements:**

- ``uid`` (required): Primary key
- ``starttime`` (required): Visibility start timestamp
- ``endtime`` (required): Visibility end timestamp
- ``pid``: Parent page ID (recommended)
- ``hidden``: Visibility flag (recommended)
- ``deleted``: Deletion flag (recommended)
- ``sys_language_uid``: Language identifier (recommended for multi-language sites)
- Additional fields like ``title``, ``header``, ``name`` for display purposes

**Default Tables:**

The extension monitors ``pages`` and ``tt_content`` tables automatically.
You cannot re-register these default tables.

Optional: Adjust Maximum Lifetime
----------------------------------

The extension provides configurable maximum cache lifetime to prevent extremely long
cache lifetimes when no temporal content is scheduled for extended periods.

**Default**: 86400 seconds (24 hours)

Configure via Extension Manager or PHP:

**Extension Manager:**

1. Admin Tools → Extensions
2. Find "nr_temporal_cache"
3. Click Configure
4. Adjust "Default Cache Lifetime (seconds)"

**PHP Configuration** (``config/system/additional.php``):

.. code-block:: php

   <?php

   // Adjust maximum cache lifetime (seconds)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['advanced']['default_max_lifetime'] = 43200; // 12 hours

For complete configuration details, see :ref:`configuration-advanced`.

Verification
============

Test Scheduled Content
----------------------

1. Create a test page:

   - Set **Start** to 5 minutes in the future
   - Set **In menu** enabled
   - Save

2. Check your frontend menu:

   - Page should NOT appear (starttime not reached)

3. Wait 5 minutes, refresh:

   - Page should appear automatically (no cache clearing needed!)

4. Monitor cache tags:

   **Console (development):**

   .. code-block:: bash

      # Enable cache debugging
      vendor/bin/typo3 cache:flush

      # Watch cache lifetime in TYPO3 admin panel
      # Should show lifetime = seconds until starttime

Test Expiring Content
---------------------

1. Create content element:

   - Set **Stop** to 5 minutes in the future
   - Save

2. View page:

   - Content element visible

3. Wait 5 minutes, refresh:

   - Content element hidden automatically

Performance Check
-----------------

Enable TYPO3 Admin Panel to monitor cache behavior:

1. Install admin panel: ``composer req typo3/cms-adminpanel``
2. Enable in backend: **User Settings > Admin Panel**
3. Check frontend: Bottom toolbar shows cache info
4. Verify:

   - Cache hits: Should remain high (95%+)
   - Cache lifetime: Should show seconds until next transition
   - Page generation: Should not increase significantly

Troubleshooting
===============

Cache Not Updating
------------------

**Symptom:** Content still doesn't update at scheduled times

**Checks:**

1. Verify extension is active::

      vendor/bin/typo3 extension:list

2. Check event listener is registered::

      # Should show temporal-cache/modify-cache-lifetime
      vendor/bin/typo3 config:show TYPO3_CONF_VARS/SYS/eventListeners

3. Clear ALL caches::

      vendor/bin/typo3 cache:flush

4. Enable debug mode::

      $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
      $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;

Performance Issues
------------------

**Symptom:** Slow page generation

**Diagnosis:**

Enable database query logging to check temporal queries:

**File:** ``config/system/additional.php``

.. code-block:: php

   <?php

   $GLOBALS['TYPO3_CONF_VARS']['LOG']['Netresearch']['TemporalCache']['writerConfiguration'] = [
       \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
           \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
               'logFile' => 'typo3temp/var/log/temporal_cache.log'
           ],
       ],
   ];

**Expected:** Queries should be <10ms with proper database indexes.

**Fix:** Ensure indexes exist::

   # Check indexes
   ALTER TABLE pages ADD INDEX idx_temporal (starttime, endtime);
   ALTER TABLE tt_content ADD INDEX idx_temporal (starttime, endtime);

Workspace Issues
----------------

**Symptom:** Workspace previews show incorrect content

**Note:** Extension respects workspace context automatically via ``Context`` API.

If issues persist, check:

1. Workspace ID is correct in context
2. Language overlay is applied properly
3. Clear workspace-specific caches

Uninstallation
==============

The extension adds no tables and no columns. It does add four indexes to the core tables
``pages`` and ``tt_content`` (``idx_temporalcache_starttime`` and
``idx_temporalcache_endtime``, see :file:`ext_tables.sql`); remove them in
:guilabel:`Admin Tools → Maintenance → Analyze Database Structure` after uninstalling if you
do not want to keep them.

1. Deactivate extension::

      vendor/bin/typo3 extension:deactivate nr_temporal_cache

2. Remove via composer::

      composer remove netresearch/nr-temporal-cache

3. Clear caches::

      vendor/bin/typo3 cache:flush

**Result:** TYPO3 reverts to default behavior (temporal content requires manual cache clearing)

Next Steps
==========

- :ref:`architecture` - Understand how it works
- :ref:`phases` - Learn about future improvements
- `GitHub Issues <https://github.com/netresearch/t3x-nr-temporal-cache/issues>`__ - Report problems
