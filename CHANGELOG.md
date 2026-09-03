# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file starts with `v0.9.0-alpha1`; the entries below are reconstructed from the
git history, so they name the user-facing changes rather than every commit.

## [0.9.0] - 2026-09-03

### Added

- TYPO3 v14 and PHP 8.5 support, declared in `composer.json` and `ext_emconf.php`.
- Page-aware cache scoping under dynamic timing.
- Persistent temporal field harmonization with table-aware routing.
- `ext_tables.sql` adding `starttime`/`endtime` indexes to `pages` and `tt_content`.
  Run the database compare (Install Tool or `typo3 extension:setup`) after updating.
- XLIFF translations for 31 languages, maintained through Crowdin.
- Separate extension and backend module icons, including the TYPO3 v14 three-color style.

### Changed

- With `timing.strategy = hybrid`, the page cache lifetime now honours
  `timing.hybrid.content` as well as `timing.hybrid.pages`, taking the earliest
  of the two. Previously only the pages rule was consulted, so a site with
  `pages = scheduler` and `content = dynamic` got an unbounded page cache.
  **On upgrade such a site will start receiving expiring caches** — that is the
  defect being fixed, but it changes cache behaviour.
- `harmonization.tolerance` is documented correctly: `0` disables harmonization
  rather than removing a limit, which is what the previous label claimed.
- `harmonization.auto_round` is labelled as the reporting flag it is. It never
  had a save-time effect; the status report and `verify` no longer present it as
  an active feature.
- Extension state raised from `alpha` to `beta`.
- Backend module registration modernized for TYPO3 v13: `Configuration/Backend/Routes.php`
  removed, `Configuration/Icons.php` and `Configuration/JavaScriptModules.php` added.

### Removed

- Setting `timing.scheduler_interval` and the public method
  `ExtensionConfiguration::getSchedulerInterval()`. Nothing read the value — the
  cadence is whatever frequency the task is given in the Scheduler module. This
  drops a method from a `public: true` service, which is acceptable before 1.0
  but is an API change.

### Fixed

- `timing.strategy = scheduler` did nothing at all. No scheduler task type was
  registered, so `TemporalCacheSchedulerTask` could not be created in the
  Scheduler module — and because that task is the only caller of
  `processTransition()`, the per-page and per-content flush tags were
  unreachable too. `SchedulerTimingStrategy` deliberately returns no cache
  lifetime, expecting the task to flush, so selecting scheduler timing left the
  page cache both uncapped and never flushed.
- The scheduler task could not be saved on TYPO3 12.4 and 13 even once
  registered: those versions store a task as `serialize($task)`, and the
  injected services made that throw `Serialization of 'Closure' is not allowed`.
  The task now omits its services from serialization and resolves them again on
  wake-up. TYPO3 14 rebuilds the task from the container and never took this
  path.
- `timing.hybrid.pages` was read under the key `pages` while the configuration
  getter supplied it as `page`, so the setting never matched and page records
  always fell back to dynamic timing.
- Harmonization measured slot distance within a single day, so a transition near
  midnight was compared against the wrong slot. With slots at 00:00 and 18:00 a
  23:30 timestamp was treated as 5.5 hours from 18:00 rather than 30 minutes
  from the next day's 00:00 — it was either left untouched or shifted backwards
  by up to 12 hours. Distance is now circular over the day.
- `temporalcache:verify` pointed at `typo3 database:updateschema` when an index
  was missing. That command belongs to `helhum/typo3-console`, which this
  extension does not require, so on a plain installation the suggested recovery
  step did not exist. It now names `typo3 extension:setup`.
- Backend module links now use the Backend `UriBuilder`, which TYPO3 v14 requires.
- JavaScript module import map for the backend module.
- `IconSize` handling on TYPO3 v12.
- `ext_emconf.php` now declares the `reports` dependency that `composer.json` already required.

## [0.9.0-alpha1] - 2025-11-17

First pre-release. Cache invalidation for `starttime`/`endtime` content with three
scoping strategies (global / per-page / per-content) and three timing strategies
(dynamic / scheduler / hybrid), a backend module, CLI commands and a Reports module
entry, for TYPO3 v12.4 and v13 on PHP 8.1-8.3.

[0.9.0]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v0.9.0-alpha1...v0.9.0
[0.9.0-alpha1]: https://github.com/netresearch/t3x-nr-temporal-cache/releases/tag/v0.9.0-alpha1
