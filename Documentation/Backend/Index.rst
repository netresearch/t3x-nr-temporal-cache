.. include:: /Includes.rst.txt

.. _backend-module:

==============
Backend module
==============

The extension registers a backend module :guilabel:`Temporal Cache` in the :guilabel:`Tools` section.
It shows what temporal content exists, which transitions are coming up, which configuration is active, and it
can apply harmonization to selected records.

.. _backend-module-access:

Accessing the module
====================

Navigate to :guilabel:`Tools > Temporal Cache`.

Access
    The module is registered for administrators only and is available in the Live workspace only.
    See :ref:`backend-permissions`.

TYPO3 versions
    The extension supports TYPO3 12.4, 13 and 14 — see :ref:`installation`.

The module menu offers three views.
A fourth controller action, ``harmonize``, is the endpoint the content view calls when applying harmonization;
it has no view of its own.

.. _backend-module-views:

Views
=====

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📊 Dashboard

        Counters for temporal content, the active configuration, and a day-by-day timeline of the transitions
        due in the next seven days.

        ..  card-footer:: :ref:`View dashboard <backend-dashboard>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 📝 Content

        The full list of temporal pages and content elements, with filters, harmonization suggestions and bulk
        harmonization.

        ..  card-footer:: :ref:`Manage content <backend-content>`
            :button-style: btn btn-info stretched-link

    ..  card:: ⚙️ Configuration wizard

        A walkthrough of the scoping and timing strategies with three ready-made presets.
        The wizard shows settings, it does not write them.

        ..  card-footer:: :ref:`Open wizard <backend-wizard>`
            :button-style: btn btn-success stretched-link

    ..  card:: 💡 Tips and best practices

        Index checks, harmonization advice, troubleshooting and the permission model.

        ..  card-footer:: :ref:`Read tips <backend-tips>`
            :button-style: btn btn-secondary stretched-link

.. _backend-module-doc-header:

Doc header buttons
==================

Every view carries a reload button and a bookmark button.
The dashboard additionally offers a :guilabel:`View Content` button that opens the content view.

There is no cache-flush, export or configuration-test button in the module.
Flushing caches is done through the standard TYPO3 tools:

.. code-block:: bash
    :caption: Flush all caches from the command line

    vendor/bin/typo3 cache:flush

.. _backend-module-cli:

Command-line equivalents
========================

Everything the module reads is also available on the command line, and harmonization can be previewed there
before it is applied:

.. code-block:: bash
    :caption: The commands behind the module views

    vendor/bin/typo3 temporalcache:analyze
    vendor/bin/typo3 temporalcache:list
    vendor/bin/typo3 temporalcache:harmonize --dry-run
    vendor/bin/typo3 temporalcache:verify

See :ref:`command-line` for the full reference.

.. _backend-module-related:

Related documentation
=====================

- :ref:`configuration` — configuration reference
- :ref:`command-line` — command reference
- :ref:`reports-module` — status reporting inside the TYPO3 Reports module
- :ref:`performance-considerations` — performance implications
- :ref:`installation` — installation and setup

.. Meta Menu

.. toctree::
   :hidden:
   :maxdepth: 2

   Dashboard
   Content
   Wizard
   Tips
