# TYPO3 Temporal Cache Management

[![CI](https://github.com/netresearch/t3x-nr-temporal-cache/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-nr-temporal-cache/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/netresearch/t3x-nr-temporal-cache/graph/badge.svg)](https://codecov.io/gh/netresearch/t3x-nr-temporal-cache)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)
[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/netresearch/t3x-nr-temporal-cache/releases)
[![License](https://img.shields.io/github/license/netresearch/t3x-nr-temporal-cache)](LICENSE)

Automatic cache invalidation for time-based content, developed by [Netresearch DTT GmbH](https://www.netresearch.de/).

**Addresses [TYPO3 Forge Issue #14277](https://forge.typo3.org/issues/14277)**: "Start/Stop time for pages is ignored in standard menu objects", reported in 2004 and still open.

> **Status**: `ext_emconf.php` declares version 1.0.0, state `stable`. The API listed in [Documentation/Api](Documentation/Api/Index.rst) follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from this release onwards.

## The Problem (20+ Years Old)

TYPO3's cache system is **event-driven** (invalidates when data changes) but doesn't handle **temporal dependencies** (when time passes):

- ❌ Pages with `starttime` don't appear in menus when scheduled time arrives
- ❌ Pages with `endtime` remain visible in menus after expiration
- ❌ Content elements with `starttime/endtime` don't update automatically
- ❌ Sitemaps, search results, and listings show stale temporal content
- ⚠️ **Requires manual cache clearing** for every time-based transition

## The Solution

This extension provides **automatic temporal cache management** with configurable scoping and timing strategies.

### How It Works

```
Timeline:
09:00 → Cache generated, expires at 10:00 (next starttime)
10:00 → Cache regenerates, content now visible, expires at 11:00
11:00 → Cache regenerates, page appears in menu, expires at 12:00
12:00 → Cache regenerates, expired content hidden

✅ Fully automatic, no manual intervention
```

### What Gets Fixed

Dynamic timing caps the page cache lifetime at the next transition; scheduler and hybrid timing flush cache tags once a transition has passed. Either way the cached output is regenerated:

- ✅ **Menus (HMENU)** - Pages appear/disappear based on starttime/endtime
- ✅ **Content Elements** - Scheduled content blocks update automatically
- ✅ **Sitemaps** - XML sitemaps reflect current page visibility
- ✅ **Search Results** - Cached search listings stay current
- ✅ **Plugin Output** - Any cached plugin with temporal records
- ✅ **Custom Records** - Tables registered through `TemporalMonitorRegistry`; `pages` and `tt_content` are monitored by default

## Features

### Three Scoping Strategies

Choose how cache invalidation is scoped:

1. **Global Scoping** (default)
   - Under dynamic timing, every rendered page expires at the next transition anywhere on the site
   - Zero configuration, works everywhere
   - Best for: sites with minimal temporal content

2. **Per-Page Scoping** (Targeted invalidation)
   - Flushes `pageId_<uid>` for the page a transition belongs to
   - Under dynamic timing, a rendered page's lifetime considers page transitions site-wide (menus) plus content transitions on that page
   - Best for: most sites

3. **Per-Content Scoping** (Reference-aware)
   - Resolves every page a content element appears on via `sys_refindex` and flushes those page caches
   - Falls back to the element's parent page when `scoping.use_refindex` is off or the reference index yields nothing
   - Best for: sites with extensive temporal content shared across pages

### Three Timing Strategies

Choose when to check for temporal transitions:

1. **Dynamic Timing** (Event-based)
   - Caps the cache lifetime on every page cache generation, via `ModifyCacheLifetimeForPageEvent`
   - Immediate response to transitions
   - Best for: real-time requirements

2. **Scheduler Timing** (Background processing)
   - Sets no cache lifetime at all, so page rendering runs no temporal queries
   - Transitions are processed by the scheduler task, which flushes the cache tags the scoping strategy selects
   - Best for: high-traffic sites

3. **Hybrid Timing** (Both)
   - Separate timing per content type (`timing.hybrid.pages`, `timing.hybrid.content`)
   - Example: dynamic for pages, scheduler for content
   - Best for: complex requirements

### Time Harmonization

Reduce cache churn by rounding transition times to fixed slots:

- Configure time slots (e.g., 00:00, 06:00, 12:00, 18:00)
- Transitions at 00:05, 00:15 and 00:45 all round to 00:00
- The tolerance is the maximum shift: a timestamp further from the nearest slot than the tolerance is left unchanged
- Rounding is applied by `temporalcache:harmonize` and by the backend module's bulk harmonization, not to records as they are saved

### Backend Module

Visual management interface at **Admin Tools → Temporal Cache**:

- **Dashboard**
  - Statistics: total, active and scheduled temporal content, transitions in the next 30 days
  - Timeline of the next seven days of transitions, grouped by day
  - Current configuration summary and derived KPIs

- **Content**
  - Paginated list of temporal pages and content elements
  - Filters: all, pages, content, active, scheduled, expired, harmonizable
  - Per-record harmonization suggestions
  - Bulk harmonization of selected records, after a confirmation dialog

- **Configuration Wizard**
  - Analysis of the current content and configuration, with recommendations
  - Three presets: `simple` (global/dynamic), `balanced` (per-page/hybrid/harmonization), `aggressive` (per-content/scheduler/harmonization)
  - The wizard shows the values; they are entered in Extension Configuration

## Installation

### Composer

```bash
composer require netresearch/nr-temporal-cache:^1.0
```

No stability flag is needed: `v1.0.0` carries no pre-release suffix, and `ext_emconf.php` declares state `stable`.

### TER (TYPO3 Extension Repository)

The extension key `nr_temporal_cache` is registered in TER; version 1.0.0 is published there.

### Manual

1. Download from [GitHub](https://github.com/netresearch/t3x-nr-temporal-cache)
2. Extract to `typo3conf/ext/nr_temporal_cache/`
3. Activate in Extension Manager

### Requirements

From `composer.json` and `ext_emconf.php`:

- TYPO3 `^12.4 || ^13.0 || ^14.0` (`ext_emconf.php`: 12.4.0-14.99.99)
- PHP `^8.1` (`ext_emconf.php`: 8.1.0-8.5.99)
- `typo3/cms-scheduler` — required, installed with the extension; the scheduler task backs the scheduler and hybrid timing strategies
- `typo3/cms-reports` — required, installed with the extension; adds a Temporal Cache entry to the Reports module

### Post-Installation

1. **Apply the database schema.** The extension ships its indexes in `ext_tables.sql` — `idx_temporalcache_starttime` and `idx_temporalcache_endtime` on `pages` and `tt_content` — so they are created by the schema migrator, not by hand:

```bash
vendor/bin/typo3 extension:setup
```

   Admin Tools → Maintenance → Analyze Database Structure does the same. `vendor/bin/typo3 temporalcache:verify` reports whether the indexes are in place.

2. **Configure the extension** (optional - defaults work for most sites):
   - Admin Tools → Settings → Extension Configuration → `nr_temporal_cache`

## Quick Start

### CLI Commands Quick Reference

For administrators and DevOps:

```bash
# Verify database indexes and configuration
vendor/bin/typo3 temporalcache:verify

# Analyze temporal content and statistics
vendor/bin/typo3 temporalcache:analyze --days=30

# List all temporal content
vendor/bin/typo3 temporalcache:list --upcoming

# Apply harmonization (with dry-run first)
vendor/bin/typo3 temporalcache:harmonize --dry-run
```

See the [command-line interface chapter](Documentation/CommandLine/Index.rst) for all four commands and their options.

### Reports Module

Monitor system health via the TYPO3 backend:

1. Navigate to **System → Reports → Status Report**
2. Scroll to the **Temporal Cache** entries
3. Review health indicators and recommendations

See the [Reports module chapter](Documentation/Administrator/ReportsModule.rst) for details.

### Default Configuration (Zero Config)

Extension works out of the box with the defaults from `ext_conf_template.txt`:

- **Scoping**: `global` (site-wide)
- **Timing**: `dynamic` (event-based)
- **Harmonization**: disabled

This provides automatic temporal cache management with no configuration.

### Recommended Configuration: "Balanced" Preset

```
scoping.strategy = per-page
timing.strategy = hybrid
harmonization.enabled = 1
harmonization.slots = 00:00,06:00,12:00,18:00
```

Trade-off: content-driven cache churn is limited to the page a content element lives on, while page transitions still expire every page so menus stay correct.

### Recommended Configuration: "Aggressive" Preset

```
scoping.strategy = per-content
scoping.use_refindex = 1
timing.strategy = scheduler
harmonization.enabled = 1
harmonization.slots = 00:00,04:00,08:00,12:00,16:00,20:00
```

Trade-off: page rendering runs no temporal queries at all, and invalidation is limited to the pages a transitioned element actually appears on — at the cost of depending on a background task and an up-to-date reference index.

### Scheduler Task (For Scheduler and Hybrid Timing)

`Netresearch\TemporalCache\Task\TemporalCacheSchedulerTask` collects every transition that passed since its last run and hands each one to the timing strategy, which flushes the cache tags the scoping strategy selects. It records its last run in the TYPO3 registry (`tx_temporalcache/scheduler_last_run`), so the interval between runs determines how quickly a transition takes effect. Dynamic timing does not need the task.

## Configuration Options

The twelve settings from `ext_conf_template.txt`, with their defaults:

**Scoping Strategy** (`scoping.strategy`, default `global`)
- `global` - site-wide: under dynamic timing every page expires at the next transition anywhere
- `per-page` - the affected page
- `per-content` - every page the affected content appears on

**Use Refindex** (`scoping.use_refindex`, default `1`)
- Read by the per-content strategy; with it off, a content transition falls back to the element's parent page

**Timing Strategy** (`timing.strategy`, default `dynamic`)
- `dynamic`, `scheduler`, `hybrid`

**Hybrid Strategy - Pages** (`timing.hybrid.pages`, default `dynamic`)
- Rule for records in the `pages` table

**Hybrid Strategy - Content** (`timing.hybrid.content`, default `scheduler`)
- Rule for content records. With `hybrid` timing the page cache lifetime is the earliest across both rules

**Enable Harmonization** (`harmonization.enabled`, default `0`)
- Round transitions to fixed time slots

**Time Slots** (`harmonization.slots`, default `00:00,06:00,12:00,18:00`)
- Comma-separated slots on a 24-hour clock; `HH:MM` and `H:MM` are both accepted

**Tolerance** (`harmonization.tolerance`, default `3600`)
- Maximum shift harmonization may apply, in seconds. A transition further from its nearest slot is left untouched; `0` disables harmonization entirely

**Auto-Round Reporting Flag** (`harmonization.auto_round`, default `0`)
- Records the intended policy for the status report and the `analyze` / `verify` commands. It has no save-time effect — harmonization is applied on demand from the backend module or `temporalcache:harmonize`

**Default Max Lifetime** (`advanced.default_max_lifetime`, default `86400`)
- Cache lifetime when no transition is pending, and the cap when TypoScript `config.cache_period` is not set

**Debug Logging** (`advanced.debug_logging`, default `0`)
- Log temporal cache decisions

See the [configuration reference](Documentation/Configuration/Index.rst) for detailed explanations and examples.

## Performance Summary

### Behaviour by Configuration

| Scoping Strategy | Cache tags flushed on a transition | Timing Strategy | Per-page render cost |
|-----------------|------------------------------------|-----------------|----------------------|
| **Global** | `pages` | Dynamic | 2 `MIN()` queries per monitored table - 4 with the default `pages` + `tt_content` - held in a request-level cache |
| **Per-Page** | `pageId_<uid>` of the affected page | Dynamic | 4 queries by default: page transitions site-wide plus content transitions on the rendered page |
| **Per-Content** | `pageId_<uid>` for every page the element appears on | Dynamic | Same as global - the lifetime lookup is the site-wide one |
| Any scoping | Same as above | **Scheduler** | **No queries** - the listener sets no lifetime |
| Any scoping | Same as above | **Hybrid** | Per content type, according to `timing.hybrid.*` |

> **How scoping and timing interact:**
> - **Dynamic timing** caps each rendered page's cache lifetime to its next *relevant* transition, as determined by the scoping strategy:
>   - `global` — the next transition anywhere (every page expires together).
>   - `per-page` — the next **page** transition site-wide (menus) **plus** content transitions **on that page only**. This is where per-page scoping reduces content-driven cache churn.
>   - `per-content` — conservatively uses the site-wide transition for the *lifetime* (content can be embedded into arbitrary pages via references, so a per-page lifetime could serve stale embedded content). Its precise, per-content cache **invalidation** is applied to the flush tags under **scheduler/hybrid** timing.
> - **Scheduler/Hybrid timing** actively flush the cache tags chosen by the scoping strategy when a transition passes, giving `per-content` its full precision.
>
> Rule of thumb: `per-page` + `dynamic` is great for content-heavy pages; large sites with cross-page embedded content should use `per-content` + `scheduler`.

### Decision Guide

✅ **Safe for**:
- Sites with minimal temporal content (global scoping, dynamic timing - the defaults)
- Most sites (per-page scoping)
- Sites with extensive temporal content shared across pages (per-content scoping with scheduler timing and harmonization)

⚠️ **Evaluate Carefully**:
- Dynamic timing queries on every uncached page render - scheduler timing removes them
- Multi-language sites: transitions are resolved per workspace and language, so a request-level result is not reused across languages
- CDN/reverse proxy setups: the extension caps TYPO3's page cache lifetime only, upstream TTLs are unaffected

See [Performance Considerations](Documentation/Performance/Index.rst) for detailed analysis and mitigation strategies.

## Rollout

The extension works immediately after installation with the default configuration.

1. Install it (see [Installation](#installation))
2. Apply the database schema update
3. Run `vendor/bin/typo3 temporalcache:verify`
4. Adjust the strategies in Extension Configuration (optional)
5. Test in a staging environment
6. Deploy to production

See the [installation guide](Documentation/Installation/Index.rst) and the [configuration reference](Documentation/Configuration/Index.rst) for details.

## Documentation

The rendered manual lives in `Documentation/` (build it with `composer docs:render`):

- **[Introduction](Documentation/Introduction/Index.rst)** - Problem background
- **[Performance Considerations](Documentation/Performance/Index.rst)** - Performance impact and mitigation
- **[Installation](Documentation/Installation/Index.rst)** - Setup guide
- **[Configuration](Documentation/Configuration/Index.rst)** - Complete configuration reference
- **[Backend Module](Documentation/Backend/Index.rst)** - Backend module user guide
- **[Command-line interface](Documentation/CommandLine/Index.rst)** - CLI command reference
- **[Reports Module](Documentation/Administrator/ReportsModule.rst)** - TYPO3 Reports integration
- **[Architecture](Documentation/Architecture/Index.rst)** - Technical details
- **[Phases](Documentation/Phases/Index.rst)** - Approach, limits and a core solution

## Compatibility

Declared support comes from `composer.json` and `ext_emconf.php`; the tested column is the matrix in `.github/workflows/ci.yml`.

| TYPO3 constraint | Declared PHP | PHP versions tested in CI |
|------------------|--------------|---------------------------|
| `^12.4`          | `^8.1`       | 8.1, 8.2, 8.3, 8.4        |
| `^13.0`          | `^8.1`       | 8.2, 8.3, 8.4, 8.5        |
| `^14.0`          | `^8.1`       | 8.3, 8.4, 8.5             |

## What a core solution would need

### Phase 1: Extension with Strategies (Current)
- ✅ Dynamic cache lifetime via PSR-14 event
- ✅ Three scoping strategies (global, per-page, per-content), page-aware under dynamic timing
- ✅ Three timing strategies (dynamic, scheduler, hybrid)
- ✅ Time harmonization for reduced cache churn
- ✅ Backend module for visual management
- **Status**: implemented as v1.0.0, state `stable`; published to TER and Packagist

### Phase 2: Absolute Expiration API (Proposed for TYPO3 Core)
- Extend `CacheTag` so a tag can carry an absolute expiration timestamp
- System-wide temporal cache awareness, without an extension

### Phase 3: Automatic Temporal Detection (Proposed for TYPO3 Core)
- Zero-configuration temporal caching
- Automatic detection of starttime/endtime dependencies
- Uses the Phase 2 API transparently

None of this describes committed work in TYPO3 core: there is no accepted RFC, no target version and no timeline. The intent is to deprecate this extension if core ever covers it. See [Phases](Documentation/Phases/Index.rst).

## Testing

```bash
# Unit tests
composer ci:test:php:unit

# Functional + integration tests
composer ci:test:php:functional

# Coverage report for the unit suite (HTML in .Build/coverage, clover in .Build/logs)
composer ci:test:php:coverage

# Check that report against the 69% threshold
composer ci:test:php:coverage:check

# Static analysis, code style and Rector
composer ci:test:php:phpstan
composer ci:test:php:cgl      # check only
composer ci:cgl               # auto-fix
composer ci:test:php:rector   # dry-run
```

### Test Suites
- **Unit**: `Tests/Unit`, 27 test classes with stubbed/mocked dependencies (`Build/phpunit/UnitTests.xml`)
- **Functional**: `Build/phpunit/FunctionalTests.xml` runs `Tests/Functional` and `Tests/Integration`, 11 test classes against a real database (event listener, scheduler task, scoping/timing strategies, harmonization persistence, backend controller)
- **Coverage gate**: CI runs both suites with coverage and uploads them to Codecov, which reports every pull request against the 69% project target in [`codecov.yml`](codecov.yml). `composer ci:test:php:coverage:check` is the local equivalent, measured on the unit suite alone.

## Contributing

Contributions welcome. The conventions this repository enforces are in [`AGENTS.md`](AGENTS.md).

1. Fork the repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Commit with a Conventional Commit subject, signed and signed off: `git commit -S --signoff -m 'feat: add my feature'`
4. Push to branch: `git push origin feature/my-feature`
5. Submit pull request

CI runs code style, PHPStan and Rector, plus the unit and functional suites across the version matrix above.

## Support & Issues

- **Issues**: [GitHub Issues](https://github.com/netresearch/t3x-nr-temporal-cache/issues)
- **Forge**: [TYPO3 Forge #14277](https://forge.typo3.org/issues/14277)
- **Documentation**: [`Documentation/`](Documentation/) in this repository

## License

GPL-2.0-or-later - See [LICENSE](LICENSE) file

## Credits

**Developed by**: [Netresearch DTT GmbH](https://www.netresearch.de/)

**Addresses**: TYPO3 Forge Issue [#14277](https://forge.typo3.org/issues/14277), reported 2004-08-20 and still open

**Related Issues** (both closed):
- [#16815](https://forge.typo3.org/issues/16815) - Sitemap ignoring "Start" and "End" flags
- [#98964](https://forge.typo3.org/issues/98964) - Menu object caching creates too many records resulting in huge cache_hash table

---

**Made with ❤️ for the TYPO3 Community**
