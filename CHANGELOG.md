# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file starts with `v0.9.0-alpha1`; the entries below are reconstructed from the
git history, so they name the user-facing changes rather than every commit.

## [Unreleased]

## [1.0.1] - 2026-09-30

### Changed

- The extension requires a TYPO3 LTS release: `^12.4 || ^13.4 || ^14.3` for
  `typo3/cms-core`, `typo3/cms-scheduler` and `typo3/cms-reports`. The previous
  constraint admitted every 13.x and 14.x release.
- **Upgrade impact:** Composer installations on the non-LTS releases 13.0-13.3
  and 14.0-14.2 no longer resolve this or later versions; update TYPO3 to 13.4
  or 14.3 first. Classic mode still accepts these releases: `ext_emconf.php`
  declares the range `12.4.0-14.3.99` for `typo3`, `scheduler` and `reports`,
  and a range cannot leave a gap. The API listed in `Documentation/Api/` is
  unchanged.

### Fixed

- The backend module now shows the TYPO3 doc header on all three views. The
  extension shipped its own `Module` layout, which replaced core's and dropped
  the reload and bookmark buttons and the view menu the controller registers.
- The backend module follows the light and dark scheme: badges, buttons and
  info boxes use core's classes (`badge-*`, `btn-default`, `f:be.infobox`)
  instead of Bootstrap colours that stay fixed in both schemes, the filter
  buttons have a border and a visible focus ring, and headings no longer skip
  levels. The row checkboxes in the content list have an accessible name.
- The checkboxes of the content list are visible again on TYPO3 13 and 14.
  They sat outside core's `form-check` wrapper, which is what gives them their
  size, and rendered 0 by 0 pixels.
- The dashboard's "transitions in the next 30 days" figure is readable on
  TYPO3 12 (2.16:1 before, 9.4:1 now).
- The view menu in the doc header has its own accessible name ("View").
  Without a label it was an unnamed select on TYPO3 12 and 13, and on 14 it
  was named after its first entry.
- The backend module no longer triggers TYPO3 14 deprecations when it builds
  its doc header: it no longer adds its own reload and shortcut buttons there,
  and it creates the view menu, its entries and the link buttons directly
  instead of through the deprecated `make*()` methods.

## [1.0.0] - 2026-09-04

First stable release. The API listed in `Documentation/Api/` follows Semantic
Versioning from here on: the two strategy service tags and their interfaces,
`TemporalMonitorRegistry`'s registration methods, the two value objects, and the
eleven configuration keys.

### Added

- `Documentation/Api/` defines what is covered by the version promise and what is
  internal, together with the deprecation policy. `@api` and `@internal` in the
  code say the same.

### Fixed

- Per-content scoping never resolved a reference. The lookup filtered
  `sys_refindex` on a column that table does not have, so on MySQL the query
  raised an error the strategy swallowed and on SQLite it matched nothing —
  per-content behaved exactly like per-page.
- The documented way to monitor additional tables did nothing. All three recipes
  registered through a service definition that Symfony removes when it compiles
  the container. Register from `ext_localconf.php` instead; the manual now shows
  only that form.
- Deleted records could reach a transition lookup, shortening cache lifetimes for
  content an editor had removed.
- The manual stated the opposite of the code in three places: hybrid timing's
  content rule, the harmonization slot distance around midnight, and a claim that
  no scheduler task type is registered.
- LICENSE contained a placeholder instead of the GPL-2.0 text.

### Changed

- Extension state from `beta` to `stable`.
- The backend module tests now run on TYPO3 13 and 14 instead of skipping.

## [0.9.0] - 2026-09-03

### Added

- TYPO3 v14 and PHP 8.5 support.
- Page-aware cache scoping under dynamic timing.
- Persistent temporal field harmonization with table-aware routing.
- `ext_tables.sql` with `starttime`/`endtime` indexes; run `typo3 extension:setup`.
- XLIFF translations for 31 languages; separate extension and module icons.

### Changed

- **Upgrade impact:** under `timing.strategy = hybrid` the page cache lifetime now
  honours `timing.hybrid.content` as well as `timing.hybrid.pages`, taking the
  earliest. A site with `pages = scheduler` and `content = dynamic` previously got
  an unbounded page cache and will now get expiring caches.
- `harmonization.tolerance = 0` disables harmonization rather than removing a
  limit; `auto_round` is labelled as the reporting flag it is.
- Extension state raised from `alpha` to `beta`.

### Removed

- `timing.scheduler_interval` and `ExtensionConfiguration::getSchedulerInterval()`.
  Nothing read the value; the cadence is the task's frequency in the Scheduler
  module. An API change, acceptable before 1.0.

### Fixed

- `timing.strategy = scheduler` did nothing: no task type was registered, so the
  task could not be created and the per-page and per-content flush tags were
  unreachable. The page cache was left uncapped and never flushed.
- The task could not be saved on TYPO3 12.4 and 13, which serialize it; its
  injected services made that throw. It now re-resolves them on wake-up.
- `timing.hybrid.pages` was read as `pages` while the configuration supplied
  `page`, so page records always fell back to dynamic timing.
- Harmonization measured slot distance within one day, so a transition near
  midnight moved to the wrong slot. It is now circular.
- `temporalcache:verify` suggested the non-core `database:updateschema` and
  rejected `harmonization.tolerance = 0`.
- Backend module links use the Backend `UriBuilder` for v14.

## [0.9.0-alpha1] - 2025-11-17

First pre-release. Cache invalidation for `starttime`/`endtime` content with three
scoping strategies (global / per-page / per-content) and three timing strategies
(dynamic / scheduler / hybrid), a backend module, CLI commands and a Reports module
entry, for TYPO3 v12.4 and v13 on PHP 8.1-8.3.

[Unreleased]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v1.0.1...main
[1.0.1]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v0.9.0...v1.0.0
[0.9.0]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v0.9.0-alpha1...v0.9.0
[0.9.0-alpha1]: https://github.com/netresearch/t3x-nr-temporal-cache/releases/tag/v0.9.0-alpha1
