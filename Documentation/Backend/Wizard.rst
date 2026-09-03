.. include:: /Includes.rst.txt

.. _backend-wizard:

====================
Configuration wizard
====================

Guided walkthrough of the available strategies, with three ready-made preset combinations.

.. important::
    The wizard does not write any configuration.
    Every "Apply" button ends in a notification that points at
    :guilabel:`Admin Tools > Settings > Extension Configuration > nr_temporal_cache`, where the settings have to
    be entered by hand.
    See :ref:`configuration` for the settings themselves.

.. _backend-wizard-steps:

Wizard steps
============

The wizard is a single backend action that renders one step at a time, selected by a ``step`` parameter in the
URL.
Opening :guilabel:`Configuration Wizard` from the module menu starts at the welcome step.

.. _backend-wizard-step-welcome:

Welcome
-------

Shows three figures for the current site:

- the number of temporal records found
- the number of days in the next 30 days that carry at least one transition
- the number of records whose start time harmonization would move, if harmonization is enabled

:guilabel:`Start Configuration` continues to the analysis step.

.. _backend-wizard-step-analysis:

Analysis
--------

Lists recommendations derived from the current configuration and the figures above.
A recommendation is shown when one of these applies:

- harmonization is disabled and more than 10 of the next 30 days carry transitions
- the scoping strategy is ``global`` and more than 100 temporal content elements exist
- the timing strategy is ``dynamic`` and more than 20 of the next 30 days carry transitions

When none applies, the step shows no recommendations.
:guilabel:`Continue to Presets` leads to the presets step, :guilabel:`Back` returns to the welcome step.

.. _backend-wizard-step-presets:

Presets
-------

Shows the three preset combinations side by side, each with its scoping strategy, timing strategy and
harmonization state.
:guilabel:`Apply Preset` opens a confirmation dialog and then the notification described above — the preset is
not written anywhere.

:guilabel:`Custom Configuration` leads to the custom step, :guilabel:`Back` returns to the analysis step.

.. _backend-wizard-step-custom:

Custom configuration
--------------------

A form with the three strategy choices, pre-selected from the current configuration:

Scoping strategy
    ``global``, ``per-page`` or ``per-content``.

Timing strategy
    ``dynamic``, ``scheduler`` or ``hybrid``.

Time slot harmonization
    A single on/off switch.
    The slots and the tolerance are not part of this form; they are configured in the extension configuration.

:guilabel:`Apply Configuration` shows the notification and saves nothing.
The form itself states that changes have to be applied in the extension configuration.

.. _backend-wizard-step-summary:

Summary
-------

A closing step with a link back to the dashboard.
It is part of the module but no button in the wizard currently links to it.

.. _backend-wizard-presets:

Presets in detail
=================

The preset definitions live in the module controller.
Enter the values shown here in the extension configuration to reproduce a preset.

.. _backend-wizard-preset-simple:

Simple (Phase 1 compatible)
---------------------------

Backward compatible with Phase 1: global scoping, dynamic timing, no harmonization.

.. code-block:: php
    :caption: Settings of the "Simple" preset

    'scoping' => ['strategy' => 'global'],
    'timing' => ['strategy' => 'dynamic'],
    'harmonization' => ['enabled' => false],

.. _backend-wizard-preset-balanced:

Balanced
--------

Per-page scoping with hybrid timing and harmonization on a six-hour grid.

.. code-block:: php
    :caption: Settings of the "Balanced" preset

    'scoping' => ['strategy' => 'per-page'],
    'timing' => ['strategy' => 'hybrid'],
    'harmonization' => ['enabled' => true, 'slots' => '00:00,06:00,12:00,18:00'],

.. _backend-wizard-preset-aggressive:

Aggressive optimization
-----------------------

Per-content scoping backed by the reference index, scheduler timing and harmonization on a four-hour grid.

.. code-block:: php
    :caption: Settings of the "Aggressive Optimization" preset

    'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
    'timing' => ['strategy' => 'scheduler'],
    'harmonization' => ['enabled' => true, 'slots' => '00:00,04:00,08:00,12:00,16:00,20:00'],

.. _backend-wizard-next-steps:

Next steps
==========

- :ref:`configuration` — where the settings are actually entered
- :ref:`configuration-strategies` — what the scoping and timing strategies do
- :ref:`backend-dashboard` — check the effect after changing the configuration
- :ref:`backend-tips` — practical recommendations
