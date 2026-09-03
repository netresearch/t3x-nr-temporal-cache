.. include:: /Includes.rst.txt

.. _configuration-strategies:

=======================
Optimization strategies
=======================

Reference for the scoping, timing and harmonization settings.
Each setting is listed with the type, the default and the behaviour the
extension code derives from it.

For guidance on which combination suits which site, see
:ref:`performance-strategies`.

.. _configuration-strategies-how-settings-combine:

How the settings combine
========================

The scoping strategy answers two questions, and the timing strategy decides
which of the two answers is ever used:

Cache lifetime
   :php:`ScopingStrategyInterface::getNextTransition()` returns the next
   transition timestamp.
   The event listener caps the page cache lifetime at that timestamp.
   Only the ``dynamic`` timing strategy — and ``hybrid`` when its page rule is
   ``dynamic`` — asks for it.

Cache tags
   :php:`ScopingStrategyInterface::getCacheTagsToFlush()` returns the tags a
   transition flushes.
   Only the scheduler task calls
   :php:`TimingStrategyInterface::processTransition()`, so these tags take
   effect with the ``scheduler`` and ``hybrid`` timing strategies.
   :php:`DynamicTimingStrategy::processTransition()` is empty.

A scoping strategy whose benefit lies in its tags therefore has no effect while
``timing.strategy = dynamic``.

.. _configuration-scoping:

Scoping strategy
================

Controls which caches are invalidated when temporal transitions occur.

.. confval:: scoping.strategy

   :type: string
   :Default: ``global``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['strategy']

   Selects the scoping strategy by its :php:`getName()`.
   Accepted values are ``global``, ``per-page`` and ``per-content``.
   A value matching no registered strategy activates the tagged strategy with
   the highest priority, which is ``global``.

   ``global``
      Next transition: the earliest transition in any monitored table, for the
      current workspace and language.
      Cache tags: ``pages`` — every page cache is flushed.

   ``per-page``
      Next transition: the earlier of the next transition in the ``pages``
      table site-wide and the next content transition on the page being
      rendered.
      Page transitions stay site-wide because a page appearing or disappearing
      changes menus everywhere.
      Without a page id, for example on the command line, the site-wide
      transition is used.
      Cache tags: ``pageId_<uid>`` for a page record, ``pageId_<pid>`` for a
      content element.

   ``per-content``
      Next transition: the site-wide transition, the same value ``global``
      returns.
      Content can be referenced onto arbitrary pages, so narrowing the lifetime
      per page would risk serving stale embedded content.
      Cache tags: ``pageId_<uid>`` for a page record; for a content element,
      one tag per page that references it, resolved through ``sys_refindex``.

   The per-content precision lives entirely in the tags, so this strategy
   changes nothing while ``timing.strategy = dynamic``.
   Combine it with ``scheduler`` or ``hybrid`` timing.

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration
      scoping.strategy = per-content

.. confval:: scoping.use_refindex

   :type: boolean
   :Default: ``true``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['scoping']['use_refindex']

   Read by the ``per-content`` scoping strategy only.
   The other two strategies ignore it.

   **When enabled:**

   ``PerContentScopingStrategy`` asks ``RefindexService`` for every page that
   references the content element and returns one cache tag per page.

   **When disabled:**

   The lookup is skipped and the strategy flushes the content element's own
   page (``pid``) only, which is what ``per-page`` does.

   The same fallback to ``pid`` applies when the reference index lookup returns
   no pages or throws, so a stale ``sys_refindex`` degrades the result instead
   of dropping the invalidation.
   Keep the reference index current with
   :bash:`vendor/bin/typo3 referenceindex:update`.

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration
      scoping.use_refindex = 1

.. _configuration-timing:

Timing strategy
===============

Controls when the extension checks for temporal transitions.

.. confval:: timing.strategy

   :type: string
   :Default: ``dynamic``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['strategy']

   Selects the timing strategy by its :php:`getName()`.
   Accepted values are ``dynamic``, ``scheduler`` and ``hybrid``.
   A value matching no registered strategy activates the tagged strategy with
   the highest priority, which is ``dynamic``.

   ``dynamic``
      Calculates a cache lifetime on every page cache generation and caps the
      page cache at the next transition.
      With no transition ahead it returns
      ``advanced.default_max_lifetime``; a transition already in the
      past yields 60 seconds.
      Transitions are not processed separately — the cache simply expires.

   ``scheduler``
      Returns no lifetime, so the extension leaves the page cache lifetime
      untouched and TYPO3's own cache period applies.
      Invalidation happens when the scheduler task processes a transition and
      flushes the scoping strategy's cache tags.
      Requires the scheduler task, see :ref:`scheduler-setup`.

   ``hybrid``
      Delegates per content type to the dynamic or the scheduler strategy,
      configured through ``timing.hybrid.pages`` and
      ``timing.hybrid.content``.

   Example
   -------

   .. code-block:: text

      # Extension Manager configuration

      # Flush through the scheduler task
      timing.strategy = scheduler

      # Or split the two content types
      timing.strategy = hybrid
      timing.hybrid.pages = dynamic
      timing.hybrid.content = scheduler

.. confval:: timing.scheduler_interval

   :type: integer
   :Default: ``60``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['scheduler_interval']

   Intended check interval in seconds for the scheduler timing strategy.
   :php:`ExtensionConfiguration::getSchedulerInterval()` reads the value and
   raises anything below 60 to 60.

   .. warning::
      No component of this version reads that getter.
      How often transitions are actually processed is set by the frequency of
      the scheduler task itself, not by this value.

   Example
   -------

   .. code-block:: text

      timing.scheduler_interval = 60

.. confval:: timing.hybrid.pages

   :type: string
   :Default: ``dynamic``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['pages']

   Rule for records in the ``pages`` table, whose content type is ``page``.
   Accepted values are ``dynamic`` and ``scheduler``; any other value falls
   back to ``dynamic``.
   Only read when ``timing.strategy = hybrid``.

   This rule does double duty.
   Besides routing page transitions, it is the rule
   :php:`HybridTimingStrategy::getCacheLifetime()` consults on every page cache
   generation, because at that point the individual content elements of the
   page are not known.
   Leaving it at ``dynamic`` therefore keeps the lifetime calculation running
   for every cached page; setting it to ``scheduler`` removes the lifetime
   calculation for the whole site.

   .. note::
      The configuration key is ``pages``, the content type it maps to is
      ``page``.
      :php:`ExtensionConfiguration::getTimingRules()` performs that mapping, so
      the rule reaches :php:`HybridTimingStrategy`, which looks rules up by
      :php:`TemporalContent::getContentType()`.

   Example
   -------

   .. code-block:: text

      timing.strategy = hybrid
      timing.hybrid.pages = dynamic

.. confval:: timing.hybrid.content

   :type: string
   :Default: ``scheduler``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['timing']['hybrid']['content']

   Rule for records in every monitored table other than ``pages``, whose
   content type is ``content``.
   Accepted values are ``dynamic`` and ``scheduler``; any other value falls
   back to ``dynamic``.
   Only read when ``timing.strategy = hybrid``.

   This rule applies to transition processing only.
   The cache lifetime always follows ``timing.hybrid.pages``, so setting
   this rule to ``dynamic`` means content transitions are neither processed by
   the scheduler task nor reflected in a lifetime of their own.

   Example
   -------

   .. code-block:: text

      timing.strategy = hybrid
      timing.hybrid.content = scheduler

.. _configuration-harmonization:

Time harmonization
==================

Rounds transition timestamps to fixed time slots so that several transitions
share one cache flush.

.. confval:: harmonization.enabled

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['enabled']

   Master switch for ``HarmonizationService``.

   **When disabled:**

   :php:`harmonizeTimestamp()` returns every timestamp unchanged, the Content
   tab of the backend module hides its harmonization column, and the
   ``harmonize`` AJAX endpoint refuses the request.

   **When enabled:**

   Timestamps are moved to the nearest configured slot, subject to
   ``harmonization.tolerance``.
   Harmonization is applied where it is invoked — by the ``Harmonize selected``
   action in the backend module and by the
   :bash:`vendor/bin/typo3 temporalcache:harmonize` command, both of which
   write ``starttime`` and ``endtime`` back to the record.
   It does not silently rewrite records that editors save.

   Example
   -------

   .. code-block:: text

      harmonization.enabled = 1

.. confval:: harmonization.slots

   :type: string
   :Default: ``00:00,06:00,12:00,18:00``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['slots']

   Comma-separated list of times of day on a 24-hour clock.
   Both ``HH:MM`` and a single-digit hour (``H:MM``) are accepted, so ``8:00``
   and ``08:00`` are equivalent.
   Surrounding whitespace is trimmed and the list is sorted internally.
   An entry that matches neither form, or whose hours exceed 23 or minutes exceed
   59, is dropped without an error; if that leaves no slot at all,
   harmonization returns every timestamp unchanged.

   The slots repeat every day: a timestamp is compared against the slot times
   of its own day, in the server timezone.
   The comparison does not wrap around midnight, so 23:30 is 5 hours 30 minutes
   from an 18:00 slot and not 30 minutes from the next day's 00:00 slot.
   A slot at ``00:00`` therefore only attracts timestamps in the early hours.

   Examples
   --------

   .. code-block:: text

      # Every 6 hours (4 slots per day)
      harmonization.slots = 00:00,06:00,12:00,18:00

      # Every 4 hours (6 slots per day)
      harmonization.slots = 00:00,04:00,08:00,12:00,16:00,20:00

      # Business hours only
      harmonization.slots = 08:00,12:00,17:00

.. confval:: harmonization.tolerance

   :type: integer
   :Default: ``3600``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['tolerance']

   Largest shift in seconds that harmonization may apply.
   A timestamp is moved to its nearest slot only when the distance to that slot
   is at most this many seconds; anything further away is returned unchanged.

   .. warning::
      ``0`` does not mean "no limit".
      With a tolerance of ``0`` only a timestamp that already sits exactly on a
      slot passes the check, so harmonization changes nothing at all.
      The label in :file:`ext_conf_template.txt` still reads ``0 = no limit``
      and is wrong.
      :bash:`temporalcache:verify` treats a tolerance outside 1-86400 as
      invalid.

   **Example behaviour** (slots ``00:00,12:00``, tolerance ``3600``):

   .. code-block:: text

      11:30 → nearest slot 12:00, distance 30 min → harmonized to 12:00
      12:45 → nearest slot 12:00, distance 45 min → harmonized to 12:00
      10:30 → nearest slot 12:00, distance 90 min → left at 10:30

   When two slots are equally distant, the later one wins.

   Examples
   --------

   .. code-block:: text

      # Allow up to 1 hour shift (default)
      harmonization.tolerance = 3600

      # Stricter: max 30 minutes shift
      harmonization.tolerance = 1800

.. confval:: harmonization.auto_round

   :type: boolean
   :Default: ``false``
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_temporal_cache']['harmonization']['auto_round']

   Declares that harmonized times should be suggested while editing.

   .. note::
      The value is read and reported — by the Reports module status provider,
      by :bash:`temporalcache:analyze` and by :bash:`temporalcache:verify` —
      but no backend form acts on it in this version.
      Harmonization suggestions are shown in the Content tab of the backend
      module, which is gated by ``harmonization.enabled``, not by this
      setting.

   Example
   -------

   .. code-block:: text

      harmonization.auto_round = 1

Next steps
==========

- :ref:`configuration-advanced` - Cache lifetime cap, debug logging, scheduler task
- :ref:`configuration-examples` - Complete configuration examples
- :ref:`performance-strategies` - Which combination suits which site
