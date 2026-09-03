.. include:: /Includes.rst.txt

.. _command-line:

======================
Command-line interface
======================

The extension registers four Symfony console commands.
They are available through the TYPO3 console binary once the extension is installed and active.

.. code-block:: bash
    :caption: List the commands of this extension

    vendor/bin/typo3 list temporalcache

.. _cli-overview:

Command overview
================

.. list-table::
    :header-rows: 1
    :widths: 30 45 25

    *   -   Command
        -   Description
        -   Modifies data
    *   -   :ref:`temporalcache:analyze <cli-analyze>`
        -   Analyze temporal content and provide cache statistics
        -   No
    *   -   :ref:`temporalcache:verify <cli-verify>`
        -   Verify database indexes and extension configuration
        -   No
    *   -   :ref:`temporalcache:list <cli-list>`
        -   List all temporal content with transition information
        -   No
    *   -   :ref:`temporalcache:harmonize <cli-harmonize>`
        -   Harmonize temporal fields to configured time slots
        -   Yes

Only ``temporalcache:harmonize`` is registered as schedulable
(``schedulable: true`` in :file:`Configuration/Services.yaml`).
The other three commands are not offered in the scheduler.

.. _cli-common-options:

Options shared with every Symfony command
=========================================

Besides the options documented per command, the standard Symfony console options apply.
Two of them change the output of these commands:

``-v``, ``--verbose``
    Adds extra output sections.
    What each command adds is documented in its own section below.

``-n``, ``--no-interaction``
    Answers interactive questions with their default.
    :ref:`temporalcache:harmonize <cli-harmonize>` is the only command that asks one, and its default answer is
    *no* — so a non-interactive run of that command writes nothing.

.. _cli-analyze:

temporalcache:analyze
=====================

Reads temporal content and reports statistics, upcoming transitions and — when harmonization is enabled — the
reduction harmonization would achieve.
The command never writes to the database and always exits with code ``0``.

.. _cli-analyze-options:

Options
-------

.. list-table::
    :header-rows: 1
    :widths: 20 12 15 12 41

    *   -   Option
        -   Short
        -   Value
        -   Default
        -   Description
    *   -   ``--workspace``
        -   ``-w``
        -   required
        -   ``0``
        -   Workspace UID to analyze (``0`` = live workspace)
    *   -   ``--language``
        -   ``-l``
        -   required
        -   ``0``
        -   Language UID to analyze (``-1`` = all languages, ``0`` = default language)
    *   -   ``--days``
        -   ``-d``
        -   required
        -   ``30``
        -   Number of days to analyze for upcoming transitions

.. note::
    The statistics table is resolved per workspace only.
    The ``--language`` value is applied to the transition analysis and the harmonization impact, not to the
    content counts.

.. _cli-analyze-output:

Output
------

The command prints these sections in order:

Analysis context
    Workspace, language, analysis period and the current server time.

Temporal content statistics
    Total temporal items, split into pages and content elements, and the distribution across
    start time only, end time only and both.
    If no temporal content exists at all, the command stops here with a warning.

Upcoming transitions
    The number of transitions found in the analysis period, followed by the five days carrying the most
    transitions.
    Each day is rated ``LOW`` (fewer than 5 transitions), ``MEDIUM`` (5 to 9) or ``HIGH`` (10 or more).
    With ``--verbose`` the next ten transitions are listed with time, type, table and title.

Harmonization impact analysis
    Only when harmonization is enabled in the extension configuration.
    Compares the number of original transitions with the number remaining after harmonization and shows the
    resulting reduction.
    With ``--verbose`` the configured time slots and the tolerance are listed as well.
    When harmonization is disabled, the command prints a note instead of this section.

Extension configuration
    Only with ``--verbose``.
    Scoping strategy, timing strategy, harmonization state, slots, tolerance and the auto-round setting.

.. _cli-analyze-examples:

Examples
--------

.. code-block:: bash
    :caption: Analyze the live workspace for the next 30 days

    vendor/bin/typo3 temporalcache:analyze

.. code-block:: bash
    :caption: Analyze a workspace over a longer period, with the detailed sections

    vendor/bin/typo3 temporalcache:analyze --workspace=1 --days=60 --verbose

.. code-block:: bash
    :caption: Analyze transitions across all languages

    vendor/bin/typo3 temporalcache:analyze --language=-1

.. _cli-verify:

temporalcache:verify
====================

Checks that the database and the extension configuration are in the state the extension needs.
The command takes no options of its own and writes nothing.

Exit code ``0`` means every check passed, exit code ``1`` means at least one check failed.
That makes the command usable as a health probe in monitoring or deployment pipelines.

.. _cli-verify-checks:

Checks performed
----------------

Database index verification
    Requires an index led by ``starttime`` and an index led by ``endtime``, on both ``pages`` and
    ``tt_content``.
    An index over more columns satisfies the check as long as the temporal field is the leading column.
    The indexes ship with the extension in :file:`ext_tables.sql`; apply them with
    ``vendor/bin/typo3 database:updateschema``.

Extension configuration verification
    The scoping strategy must be ``global``, ``per-page`` or ``per-content``.
    The timing strategy must be ``dynamic``, ``scheduler`` or ``hybrid``.
    The harmonization state is reported but never fails the check.

Harmonization configuration verification
    Runs only when harmonization is enabled.
    At least one time slot must be configured, every slot must match the ``H:MM`` or ``HH:MM`` pattern, and the
    tolerance must be greater than ``0`` and at most ``86400`` seconds.
    The auto-round setting is reported but never fails the check.

Database schema verification
    ``pages`` must carry ``starttime``, ``endtime``, ``hidden``, ``deleted`` and ``sys_language_uid``;
    ``tt_content`` must carry those five plus ``pid``.
    Without ``--verbose`` the command prints a single confirmation line; with ``--verbose`` it prints the full
    field-by-field table.

.. _cli-verify-examples:

Examples
--------

.. code-block:: bash
    :caption: Run all checks

    vendor/bin/typo3 temporalcache:verify

.. code-block:: bash
    :caption: Run all checks and print the per-field schema table

    vendor/bin/typo3 temporalcache:verify --verbose

.. code-block:: bash
    :caption: Use the exit code in a shell script

    if vendor/bin/typo3 temporalcache:verify >/dev/null 2>&1; then
        echo "Temporal cache system healthy"
    else
        echo "Temporal cache system reported issues"
    fi

.. _cli-list:

temporalcache:list
==================

Lists pages and content elements that carry a start time or an end time.
The command writes nothing.
It exits with ``1`` when ``--table``, ``--sort`` or ``--format`` receives a value outside its allowed set,
and with ``0`` otherwise — including when the result set is empty.

.. _cli-list-options:

Options
-------

.. list-table::
    :header-rows: 1
    :widths: 20 12 15 12 41

    *   -   Option
        -   Short
        -   Value
        -   Default
        -   Description
    *   -   ``--table``
        -   ``-t``
        -   required
        -   *none*
        -   Filter by table; ``pages`` or ``tt_content``
    *   -   ``--workspace``
        -   ``-w``
        -   required
        -   ``0``
        -   Workspace UID to list (``0`` = live workspace)
    *   -   ``--language``
        -   ``-l``
        -   required
        -   ``0``
        -   Language UID to list (``-1`` = all, ``0`` = default language)
    *   -   ``--upcoming``
        -   ``-u``
        -   none
        -   *off*
        -   Show only content whose start time or end time lies in the future
    *   -   ``--sort``
        -   ``-s``
        -   required
        -   ``uid``
        -   Sort by ``uid``, ``title``, ``starttime``, ``endtime`` or ``table``
    *   -   ``--format``
        -   ``-f``
        -   required
        -   ``table``
        -   Output format; ``table``, ``json`` or ``csv``
    *   -   ``--limit``
        -   *none*
        -   required
        -   *none*
        -   Maximum number of records to output

Sorting by ``title`` or ``table`` is case-insensitive.
Sorting by ``starttime`` or ``endtime`` places records without that field last.
``--limit`` is applied after filtering and sorting; a value of ``0`` or lower is ignored.

.. _cli-list-formats:

Output formats
--------------

``table``
    Human-readable output with a heading, a filter line and a table of
    table name, UID, title (truncated to 30 characters), start time, end time and the next transition.
    Warnings about an empty result set are printed in this format only.

``json``
    A pretty-printed JSON array.
    Each object carries the keys ``table``, ``uid``, ``pid``, ``title``, ``starttime``,
    ``starttime_formatted``, ``endtime``, ``endtime_formatted``, ``language_uid``, ``workspace_uid``,
    ``hidden`` and ``deleted``.
    The raw fields hold Unix timestamps or ``null``; the ``_formatted`` fields hold ``Y-m-d H:i:s`` strings or
    ``null``.

``csv``
    Comma-separated output with the fixed header line:

    .. code-block:: text

        Table,UID,PID,Title,StartTime,EndTime,Language,Workspace,Hidden,Deleted

    Titles are quoted and inner quotes are doubled.
    Timestamps are written as ``Y-m-d H:i:s``, empty when the field is not set.
    ``Hidden`` and ``Deleted`` are written as ``1`` or ``0``.

In ``json`` and ``csv`` format an empty result set produces no output at all.

.. _cli-list-examples:

Examples
--------

.. code-block:: bash
    :caption: List all temporal content of the live workspace

    vendor/bin/typo3 temporalcache:list

.. code-block:: bash
    :caption: List the ten pages whose next start time comes first

    vendor/bin/typo3 temporalcache:list --table=pages --upcoming --sort=starttime --limit=10

.. code-block:: bash
    :caption: Export the inventory for a spreadsheet

    vendor/bin/typo3 temporalcache:list --format=csv > temporal-content.csv

.. code-block:: bash
    :caption: Export the inventory for further processing

    vendor/bin/typo3 temporalcache:list --format=json > temporal-content.json

.. _cli-harmonize:

temporalcache:harmonize
=======================

Rounds ``starttime`` and ``endtime`` values to the configured time slots, so that fewer distinct transition
timestamps remain and the cache is invalidated less often.

This is the only command that changes data.

.. warning::
    The command writes the new timestamps directly through the database connection, bypassing the DataHandler.
    No ``sys_history`` entry is created and the change cannot be undone from the backend.
    Each write is recorded in the TYPO3 log instead.
    Run with ``--dry-run`` and take a database backup first.

.. _cli-harmonize-options:

Options
-------

.. list-table::
    :header-rows: 1
    :widths: 20 12 15 12 41

    *   -   Option
        -   Short
        -   Value
        -   Default
        -   Description
    *   -   ``--dry-run``
        -   *none*
        -   none
        -   *off*
        -   Preview changes without modifying the database
    *   -   ``--workspace``
        -   ``-w``
        -   required
        -   ``0``
        -   Workspace UID to harmonize (``0`` = live workspace)
    *   -   ``--language``
        -   ``-l``
        -   required
        -   ``0``
        -   Language UID to harmonize (``0`` = default language)
    *   -   ``--table``
        -   ``-t``
        -   required
        -   *none*
        -   Limit to a single table; ``pages`` or ``tt_content``

.. _cli-harmonize-behavior:

What the command does
---------------------

#.  Aborts with exit code ``1`` when harmonization is disabled in the extension configuration, or when
    ``--table`` receives a value other than ``pages`` or ``tt_content``.
#.  Prints the harmonization context: mode, workspace, language, table filter, configured time slots and
    tolerance.
#.  Loads the temporal content of the selected workspace and language and applies the table filter.
#.  Calculates the harmonized timestamp for every start time and end time and collects the records where the
    value would change.
    With ``--verbose`` the first ten pending changes are listed with their time shift.
#.  In live mode, asks *Proceed with harmonization?* — the default answer is *no*.
    No option confirms it up front; running with ``--no-interaction`` takes the default and writes nothing.
#.  Applies the changes, reports how many records were updated and how many failed, and flushes the ``pages``
    cache group.
#.  Prints the impact analysis: number of changes, unique timestamps before and after, and the resulting
    reduction.

The command exits with ``0`` in every case after the configuration check has passed — including when nothing
needs harmonizing and when the confirmation is declined.
Individual failed record updates are counted and reported, but do not change the exit code.

.. _cli-harmonize-examples:

Examples
--------

.. code-block:: bash
    :caption: Preview the changes without touching the database

    vendor/bin/typo3 temporalcache:harmonize --dry-run

.. code-block:: bash
    :caption: Preview the changes including the first ten records

    vendor/bin/typo3 temporalcache:harmonize --dry-run --verbose

.. code-block:: bash
    :caption: Harmonize pages only, after reviewing the dry run

    vendor/bin/typo3 temporalcache:harmonize --table=pages

.. _cli-next-steps:

Next steps
==========

-   :ref:`configuration` — extension configuration reference, including the harmonization slots and tolerance
    that ``temporalcache:harmonize`` reads
-   :ref:`backend-module` — the same data in the TYPO3 backend
-   :ref:`reports-module` — status reporting inside the TYPO3 Reports module
