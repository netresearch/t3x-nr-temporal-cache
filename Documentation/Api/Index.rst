.. include:: /Includes.rst.txt

.. _api:

=============
Public API
=============

This extension follows `Semantic Versioning <https://semver.org/spec/v2.0.0.html>`__
from 1.0.0 onwards. That promise needs a boundary: without one, every class the
service container can reach would be frozen for the whole 1.x line.

This page is that boundary. What it lists as stable will not break in a minor or
patch release. Everything else is internal and may change at any time, even
though PHP's visibility rules do not stop you from calling it.

.. _api-stable:

Stable
======

Extension points
----------------

Two service tags, and the interfaces behind them. Tag a service and the matching
factory picks it up — the strategy whose :php:`getName()` equals the configured
value wins, and no code in this extension needs to know it exists.

.. confval:: nr_temporal_cache.scoping_strategy
   :type: service tag

   The tagged service must implement
   :php:`Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface`.
   It decides which cache tags a transition flushes.

.. confval:: nr_temporal_cache.timing_strategy
   :type: service tag

   The tagged service must implement
   :php:`Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface`.
   It decides whether a transition shortens the page cache lifetime, is handed
   to the scheduler task, or both.

.. code-block:: yaml
   :caption: A third-party extension adding its own scoping strategy

   services:
     Vendor\MyExtension\Scoping\MyScopingStrategy:
       tags:
         - { name: 'nr_temporal_cache.scoping_strategy' }

Both interfaces extend :php:`Netresearch\TemporalCache\Service\NamedStrategyInterface`,
which contributes :php:`getName()`. Adding a method to any of the three would be
a breaking change and will not happen inside 1.x.

Monitoring additional tables
----------------------------

:php:`Netresearch\TemporalCache\Service\TemporalMonitorRegistry` — the methods
:php:`registerTable()`, :php:`isRegistered()`, :php:`getAllTables()` and
:php:`getTableFields()`.

Register in :file:`ext_localconf.php`, which is the recipe that works:

.. code-block:: php
   :caption: EXT:my_extension/ext_localconf.php

   \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
       \Netresearch\TemporalCache\Service\TemporalMonitorRegistry::class
   )->registerTable('tx_myext_domain_model_event', ['uid', 'pid', 'starttime', 'endtime']);

Value objects
-------------

:php:`Netresearch\TemporalCache\Domain\Model\TemporalContent` and
:php:`Netresearch\TemporalCache\Domain\Model\TransitionEvent`. Their readonly
properties are part of the API, because a scoping or timing strategy written
against the interfaces above receives them and reads those properties.

Configuration keys
------------------

The eleven keys in :file:`ext_conf_template.txt`, documented in
:ref:`configuration-strategies`. Removing one is a breaking change; 0.9.0
removed ``timing.scheduler_interval`` for exactly that reason, before 1.0 made
it costly.

.. _api-not-stable:

Not stable
==========

Everything below is reachable through the container — several of these are
``public: true``, and TYPO3 forces every :php:`SingletonInterface` implementation
public regardless of what :file:`Services.yaml` says. Reachable is not the same
as supported:

*  :php:`Configuration\ExtensionConfiguration` — read configuration through
   TYPO3's own API, not through this class.
*  :php:`Service\Scoping\ScopingStrategyFactory`,
   :php:`Service\Timing\TimingStrategyFactory` — resolve strategies for the
   extension itself.
*  the three shipped scoping strategies and the three shipped timing strategies
   as concrete classes. Depend on the interfaces, not on
   :php:`GlobalScopingStrategy` and its siblings.
*  :php:`Service\RefindexService`, :php:`Service\HarmonizationService`,
   :php:`Service\Cache\TransitionCache`, everything under :php:`Service\Backend\`.
*  :php:`Domain\Repository\TemporalContentRepository` and
   :php:`TemporalContentRepositoryInterface` — the interface exists so the
   extension's own tests can double it, not as an implementation contract for
   third parties.
*  :php:`EventListener\TemporalCacheLifetime`, :php:`Task\TemporalCacheSchedulerTask`,
   :php:`Report\TemporalCacheStatusReport`,
   :php:`Controller\Backend\TemporalCacheController` — public so TYPO3 can
   instantiate them.
*  the console commands. Their names are stable; their classes are not.

.. _api-deprecation-policy:

Deprecation policy
==================

Anything listed under :ref:`api-stable` is removed only in a major release, and
only after being marked ``@deprecated`` in a minor release first, with the
replacement named in the annotation and in the changelog. A deprecation stands
for at least one minor release before removal.

Behaviour can change in a minor release where the current behaviour is a defect
— 0.9.0 did this when hybrid timing began honouring both of its rules, and the
changelog said so under **Upgrade impact**. Such a change is always announced
there.

.. _api-marker:

Reading the annotations
=======================

The code carries the same information:

*  ``@api`` marks a class, interface or method covered by this page.
*  ``@internal`` marks one that is not, whatever its PHP visibility.
*  An unannotated symbol is internal. When the two disagree, this page wins.
