.. include:: /Includes.rst.txt

.. _backend-dashboard:

=========
Dashboard
=========

The dashboard is the entry view of the module.
It shows how much temporal content exists, which configuration is active, and which transitions are due in the
coming days.

All figures are read live when the view is opened; there is no stored history.
The counters cover the live workspace across all languages, the timeline covers the default language.

.. _backend-dashboard-cards:

Statistics cards
================

Four cards across the top of the view.

Total Temporal Content
    Number of pages and content elements that carry a start time or an end time, with the split into
    :guilabel:`Pages` and :guilabel:`Content` below the figure.

Active Content
    Records that are visible at the moment the view is opened — the start time has passed or is unset, and the
    end time lies in the future or is unset.

Scheduled Content
    Records whose start time lies in the future.

Transitions
    Number of transition events in the next 30 days.
    A record with both a start time and an end time in that window contributes two events.

.. _backend-dashboard-configuration:

Current configuration
=====================

Shows the active scoping strategy, the active timing strategy and whether harmonization is enabled.
The values come from the extension configuration; change them under
:guilabel:`Admin Tools > Settings > Extension Configuration`, see :ref:`configuration`.

When harmonization is enabled and records exist whose start time would move, an additional hint appears with a
:guilabel:`View Harmonizable Content` link that opens the :ref:`content view <backend-content>` with the
harmonizable filter applied.

.. _backend-dashboard-timeline:

Upcoming transitions timeline
=============================

Lists the transitions of the next seven days, grouped per day.
Each day carries a badge with its number of transitions, and each entry shows:

- the time of day
- the title of the page or content element
- a :guilabel:`Start` or :guilabel:`End` badge, colored green for start and red for end
- the source record as ``table:uid``

Days without transitions are omitted.
When no transition falls into the next seven days, the card shows a note instead of the list.

.. note::
    The :guilabel:`Transitions` card counts 30 days, the timeline below it covers 7 days.
    The two figures are expected to differ.

.. _backend-dashboard-kpi:

Key performance indicators
==========================

Average Transitions per Day
    The number of days within the next 30 days that carry at least one transition.

Harmonization Potential
    Only shown when harmonization is enabled.
    The number of records whose start time harmonization would move to a different slot.

.. _backend-dashboard-actions:

Quick actions
=============

:guilabel:`View All Temporal Content`
    Opens the :ref:`content view <backend-content>` without a filter.

:guilabel:`Configuration Wizard`
    Opens the :ref:`configuration wizard <backend-wizard>`.

:guilabel:`Harmonize Content`
    Only shown when harmonization is enabled and candidates exist.
    Opens the content view with the harmonizable filter applied.

The doc header additionally offers a reload button, a bookmark button, and a
:guilabel:`View Content` button.

.. _backend-dashboard-next-steps:

Next steps
==========

- :ref:`backend-content` — inspect and harmonize individual records
- :ref:`backend-wizard` — review the available strategy combinations
- :ref:`command-line` — the same data on the command line
- :ref:`performance-considerations` — what the strategies cost
