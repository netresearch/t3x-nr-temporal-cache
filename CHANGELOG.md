# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file starts with `v0.9.0-alpha1`; the entries below are reconstructed from the
git history, so they name the user-facing changes rather than every commit.

## [Unreleased]

### Added

- TYPO3 v14 and PHP 8.5 support, declared in `composer.json` and `ext_emconf.php`.
- Page-aware cache scoping under dynamic timing.
- Persistent temporal field harmonization with table-aware routing.
- `ext_tables.sql` adding `starttime`/`endtime` indexes to `pages` and `tt_content`.
  Run the database compare (Install Tool or `typo3 extension:setup`) after updating.
- XLIFF translations for 31 languages, maintained through Crowdin.
- Separate extension and backend module icons, including the TYPO3 v14 three-color style.

### Changed

- Extension state raised from `alpha` to `beta`.
- Backend module registration modernized for TYPO3 v13: `Configuration/Backend/Routes.php`
  removed, `Configuration/Icons.php` and `Configuration/JavaScriptModules.php` added.

### Fixed

- Backend module links now use the Backend `UriBuilder`, which TYPO3 v14 requires.
- JavaScript module import map for the backend module.
- `IconSize` handling on TYPO3 v12.
- `ext_emconf.php` now declares the `reports` dependency that `composer.json` already required.

## [0.9.0-alpha1] - 2025-11-17

First pre-release. Cache invalidation for `starttime`/`endtime` content with three
scoping strategies (global / per-page / per-content) and three timing strategies
(dynamic / scheduler / hybrid), a backend module, CLI commands and a Reports module
entry, for TYPO3 v12.4 and v13 on PHP 8.1-8.3.

[Unreleased]: https://github.com/netresearch/t3x-nr-temporal-cache/compare/v0.9.0-alpha1...main
[0.9.0-alpha1]: https://github.com/netresearch/t3x-nr-temporal-cache/releases/tag/v0.9.0-alpha1
