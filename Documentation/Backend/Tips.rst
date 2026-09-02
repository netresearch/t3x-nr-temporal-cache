.. include:: /Includes.rst.txt

.. _backend-tips:

=======================
Tips and best practices
=======================

.. _backend-tips-indexes:

Check the database indexes first
================================

The extension ships the indexes it needs in :file:`ext_tables.sql`:
an index led by ``starttime`` and one led by ``endtime``, on ``pages`` and on ``tt_content``.
They are created by the schema migrator, not by the extension itself.

.. code-block:: bash
    :caption: Create the indexes after installing or updating the extension

    vendor/bin/typo3 database:updateschema

.. code-block:: bash
    :caption: Confirm that they exist

    vendor/bin/typo3 temporalcache:verify

The same check appears in the :ref:`Reports module <reports-module>`.
Without these indexes every temporal lookup falls back to a full table scan.

.. _backend-tips-harmonization:

Using harmonization effectively
===============================

Align the slots with your editorial rhythm
    If articles are published at 09:00, 13:00 and 17:00, use exactly those times as slots.
    Timestamps are only moved when they lie within the configured tolerance of a slot, so slots far from your
    actual publishing times harmonize nothing.

Review the shifts before applying them
    The :guilabel:`Harmonization Suggestion` column in the :ref:`content view <backend-content>` shows the
    shift in minutes per record.
    A large shift moves content visibility noticeably; decide per record whether that is acceptable.

Preview a bulk run on the command line
    :ref:`temporalcache:harmonize --dry-run <cli-harmonize>` lists every pending change without writing.
    Run it before harmonizing from the backend, since the backend writes bypass the DataHandler and leave no
    history entry to revert.

Start small
    Harmonize one table at a time with ``--table=pages`` or ``--table=tt_content``.

.. _backend-tips-content:

Managing temporal content
=========================

Clean up expired records
    The :guilabel:`Expired` filter lists everything past its end time.

Watch for clustering
    The dashboard timeline shows how many transitions fall on the same day.
    Many transitions on one day mean many cache invalidations on that day; that is where harmonization pays
    off.

Export an inventory
    The backend list has no export.
    Use :ref:`temporalcache:list <cli-list>` with ``--format=csv`` or ``--format=json``.

.. _backend-tips-troubleshooting:

Troubleshooting
===============

.. _backend-tips-troubleshooting-cache:

Cache is not updating
---------------------

#. Run ``vendor/bin/typo3 temporalcache:verify`` and fix everything it reports.
#. Check the timing strategy on the dashboard.
   With the ``scheduler`` or ``hybrid`` strategy, confirm that the scheduler task runs — see
   :ref:`scheduler-setup`.
#. Confirm that the affected record is listed at all, with
   ``vendor/bin/typo3 temporalcache:list --upcoming``.

.. _backend-tips-troubleshooting-harmonization:

No harmonization suggestions appear
-----------------------------------

#. Harmonization must be enabled in the extension configuration — the suggestion column is not rendered
   otherwise.
#. Check slots and tolerance with ``vendor/bin/typo3 temporalcache:verify``, which validates the slot format
   and the tolerance range.
#. A timestamp outside the tolerance of every slot is left alone by design; widen the tolerance or add slots.

.. _backend-permissions:

Access and permissions
======================

The module is registered for administrators only (``'access' => 'admin'``) and is available in the Live
workspace only (``'workspaces' => 'live'``).
Non-administrators do not see it in the :guilabel:`Tools` section.

To hide it from an administrator as well, use TSconfig:

.. code-block:: typoscript
    :caption: User TSconfig or Group TSconfig

    options.hideModules := addToList(tools_TemporalCache)

Before applying harmonization the module additionally checks write access to every monitored table — by
default ``pages`` and ``tt_content``, plus any table registered through ``TemporalMonitorRegistry``.
Administrators pass this check unconditionally.
When the check fails, the request is rejected with a message naming the tables the user cannot modify.

.. _backend-tips-next-steps:

Next steps
==========

- :ref:`configuration` — complete configuration reference
- :ref:`command-line` — command reference
- :ref:`performance-considerations` — performance impact analysis
- :ref:`backend-dashboard` — monitor your temporal cache
