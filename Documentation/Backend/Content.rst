.. include:: /Includes.rst.txt

.. _backend-content:

=======
Content
=======

Lists every page and content element that carries a start time or an end time, and — when harmonization is
enabled — lets you apply harmonization to selected records.

.. _backend-content-filters:

Filters
=======

Seven filter buttons above the table.
The active filter is kept while paging.

All Content
    Every record with a start time or an end time.

Pages Only
    Records from the ``pages`` table.

Content Elements
    Records from the ``tt_content`` table.

Active
    Records that are visible right now.

Scheduled
    Records whose start time lies in the future.

Expired
    Records whose end time lies in the past.

Harmonizable
    Records whose start time or end time harmonization would move to a different slot.

.. _backend-content-table:

Table columns
=============

Type
    :guilabel:`Page` or :guilabel:`Content`, depending on the source table.

UID
    The record UID.
    UIDs are unique per table, not across tables.

Title
    Page title or content element header, with a :guilabel:`hidden` badge when the record is hidden.

Start Time, End Time
    Formatted as ``DD.MM.YYYY HH:MM``, or ``-`` when the field is not set.

Status
    :guilabel:`Active` when the record is visible now, :guilabel:`Scheduled` when its start time lies in the
    future, :guilabel:`Expired` otherwise.

Harmonization Suggestion
    Only shown when harmonization is enabled.
    For the start time and the end time separately: the slot harmonization would move the value to, and the
    shift in minutes.
    Records without a suggestion show ``-``.

Rows with a harmonization suggestion are highlighted.
The list is not sortable and has no search field; use :ref:`temporalcache:list <cli-list>` on the command line
for sorting, filtering and export.

.. _backend-content-pagination:

Pagination
==========

The list shows 50 records per page.
Page links appear below the table as soon as there is more than one page.

.. _backend-content-harmonizing:

Harmonizing records
===================

The selection column, the suggestion column and the :guilabel:`Harmonize Selected` button appear only when
harmonization is enabled in the extension configuration.
A checkbox is rendered only for records that actually have a suggestion.

#.  Select one or more records, or use the checkbox in the table header to select all of them.
    :kbd:`Ctrl` + :kbd:`A` (:kbd:`Cmd` + :kbd:`A` on macOS) has the same effect while the focus is outside an
    input field.
#.  :guilabel:`Harmonize Selected` becomes active as soon as one record is selected.
#.  Confirm the dialog.
#.  The selected start and end times are written to the database, the page cache group is flushed, and the view
    reloads.

.. warning::
    Harmonization writes the new timestamps directly, bypassing the DataHandler.
    No ``sys_history`` entry is created and the change cannot be undone from the backend.
    Try :ref:`temporalcache:harmonize --dry-run <cli-harmonize>` first to see the full list of pending changes.

.. _backend-content-next-steps:

Next steps
==========

- :ref:`backend-dashboard` — counts and the transition timeline
- :ref:`backend-wizard` — review the available strategy combinations
- :ref:`configuration-harmonization` — configure time slots and tolerance
- :ref:`command-line` — the same list with sorting, filtering and export
