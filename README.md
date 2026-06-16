# TYPO3 Temporal Cache Management

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)
[![Version](https://img.shields.io/badge/version-0.9.0-blue.svg)](#)
[![License](https://img.shields.io/github/license/netresearch/t3x-nr-temporal-cache)](LICENSE)

**Addresses [TYPO3 Forge Issue #14277](https://forge.typo3.org/issues/14277)**: Automatic cache invalidation for time-based content.

## The Problem (20+ Years Old)

TYPO3's cache system is **event-driven** (invalidates when data changes) but doesn't handle **temporal dependencies** (when time passes):

- ❌ Pages with `starttime` don't appear in menus when scheduled time arrives
- ❌ Pages with `endtime` remain visible in menus after expiration
- ❌ Content elements with `starttime/endtime` don't update automatically
- ❌ Sitemaps, search results, and listings show stale temporal content
- ⚠️ **Requires manual cache clearing** for every time-based transition

## The Solution

This extension provides **automatic temporal cache management** with flexible strategies optimized for different site sizes and requirements.

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

- ✅ **Menus (HMENU)** - Pages appear/disappear based on starttime/endtime
- ✅ **Content Elements** - Scheduled content blocks update automatically
- ✅ **Sitemaps** - XML sitemaps reflect current page visibility
- ✅ **Search Results** - Cached search listings stay current
- ✅ **Plugin Output** - Any cached plugin with temporal records
- ✅ **Custom Records** - Extensions using starttime/endtime fields

## Phase 1 Features

### Three Scoping Strategies

Choose how cache invalidation is scoped:

1. **Global Scoping** (default)
   - Invalidates all caches on temporal transitions
   - Zero configuration, works everywhere
   - Best for: Small sites (<1,000 pages)

2. **Per-Page Scoping** (Targeted invalidation)
   - Invalidates only the affected page
   - Significantly reduces cache churn
   - Best for: Medium sites (1,000-10,000 pages)

3. **Per-Content Scoping** (Maximum efficiency)
   - Finds all pages containing temporal content via refindex
   - **99.7% reduction in cache invalidations**
   - Best for: Large sites (>10,000 pages)

### Three Timing Strategies

Choose when to check for temporal transitions:

1. **Dynamic Timing** (Event-based)
   - Checks on every page cache generation
   - Immediate response to transitions
   - Best for: Real-time requirements

2. **Scheduler Timing** (Background processing)
   - Checks via TYPO3 Scheduler task
   - **Zero per-page overhead**
   - Best for: High-traffic sites

3. **Hybrid Timing** (Best of both)
   - Configure different timing per content type
   - Example: Dynamic for pages, Scheduler for content
   - Best for: Complex requirements

### Time Harmonization

Reduce cache churn by rounding transition times to fixed slots:

- Configure time slots (e.g., 00:00, 06:00, 12:00, 18:00)
- **98%+ reduction in cache transitions**
- Transitions at 00:05, 00:15, 00:45 all round to 00:00
- Configurable tolerance to prevent unwanted shifts

Example impact:
```
Without harmonization: 500 transitions/day → 500 cache flushes
With harmonization:    500 transitions/day → 4 cache flushes (at time slots)
```

### Backend Module

Visual management interface accessible at **Tools → Temporal Cache**:

- **Dashboard Tab**
  - Live statistics and KPIs
  - Timeline visualization of upcoming transitions
  - Performance impact summary
  - Current configuration overview

- **Content Tab**
  - Browse all temporal content (pages and content elements)
  - View harmonization suggestions
  - Bulk harmonization operations
  - Filter by status (active, pending, expired)

- **Configuration Wizard**
  - Guided setup with presets
  - Small/Medium/Large site configurations
  - Performance impact calculator
  - Test configuration before applying

## Installation

### Composer (Recommended)

```bash
composer req netresearch/nr-temporal-cache
```

### TER (TYPO3 Extension Repository)

Search for `nr_temporal_cache` in the Extension Manager.

### Manual

1. Download from [GitHub](https://github.com/netresearch/t3x-nr-temporal-cache)
2. Extract to `typo3conf/ext/nr_temporal_cache/`
3. Activate in Extension Manager

### Requirements

**Required Dependencies:**
- TYPO3 12.4 LTS, 13.4 LTS or 14.x
- PHP 8.1 - 8.5
- `typo3/cms-scheduler` (installed automatically via Composer; required for the scheduler/hybrid timing strategies)

**Optional Dependencies:**
- `typo3/cms-reports` - Adds a Temporal Cache health entry to the Reports module (installed automatically via Composer)

### Post-Installation

1. **Create Database Indexes** (REQUIRED for optimal performance):

```sql
-- Pages table
CREATE INDEX idx_temporal_pages ON pages (
    starttime, endtime, sys_language_uid, hidden, deleted
);

-- Content elements table
CREATE INDEX idx_temporal_content ON tt_content (
    starttime, endtime, sys_language_uid, hidden, deleted
);
```

2. **Configure Extension** (Optional - defaults work for most sites):
   - Open Extension Manager → temporal_cache → Configure
   - Or use Backend Module → Temporal Cache → Wizard

## Quick Start

### CLI Commands Quick Reference

For administrators and DevOps:

```bash
# Verify system health and configuration
vendor/bin/typo3 temporalcache:verify

# Analyze temporal content and statistics
vendor/bin/typo3 temporalcache:analyze --days=30

# List all temporal content
vendor/bin/typo3 temporalcache:list --upcoming

# Apply harmonization (with dry-run first)
vendor/bin/typo3 temporalcache:harmonize --dry-run
```

See [CLI Commands Guide](Documentation/CLI-Commands.md) for complete documentation.

### Reports Module

Monitor system health via TYPO3 backend:
1. Navigate to **Admin Tools → Reports → Status Report**
2. Scroll to **Temporal Cache** section
3. Review health indicators and recommendations

See [Reports Module Guide](Documentation/Administrator/ReportsModule.rst) for details.

### Default Configuration (Zero Config)

Extension works out of the box with sensible defaults:
- **Scoping**: Global (site-wide)
- **Timing**: Dynamic (event-based)
- **Harmonization**: Disabled

This provides immediate automatic temporal cache management with zero configuration required.

### Recommended Configuration for Medium Sites

```
Scoping Strategy: per-page
Timing Strategy: dynamic
Harmonization: enabled (slots: 00:00,06:00,12:00,18:00)
```

Benefits:
- 95%+ reduction in cache invalidations
- Minimal per-page overhead
- Automatic cache updates at slot times

### Recommended Configuration for Large Sites

```
Scoping Strategy: per-content
Timing Strategy: scheduler (interval: 60 seconds)
Harmonization: enabled (slots: 00:00,06:00,12:00,18:00)
```

Benefits:
- 99.7%+ reduction in cache invalidations
- Zero per-page overhead
- Background processing via scheduler

### Setup Scheduler Task (For Scheduler Timing)

1. Go to **System → Scheduler**
2. Create new task: **Temporal Cache: Process Transitions**
3. Set frequency: Every 1 minute (or as configured)
4. Save and activate

## Configuration Options

### Via Extension Manager

**Scoping Strategy** (`scoping.strategy`)
- `global` - Site-wide cache invalidation (default)
- `per-page` - Per-page invalidation (targeted)
- `per-content` - Per-content invalidation via refindex (maximum efficiency)

**Use Refindex** (`scoping.use_refindex`)
- Enable sys_refindex for accurate content tracking (per-content strategy only)

**Timing Strategy** (`timing.strategy`)
- `dynamic` - Event-based checking on page cache generation
- `scheduler` - Background processing via TYPO3 Scheduler
- `hybrid` - Configure timing per content type

**Scheduler Interval** (`timing.scheduler_interval`)
- Check interval in seconds (minimum 60, default 60)

**Hybrid Strategy - Pages** (`timing.hybrid.pages`)
- Timing for page transitions (affects menus)

**Hybrid Strategy - Content** (`timing.hybrid.content`)
- Timing for content element transitions

**Enable Harmonization** (`harmonization.enabled`)
- Round transitions to fixed time slots

**Time Slots** (`harmonization.slots`)
- Comma-separated slots in HH:MM format (e.g., `00:00,06:00,12:00,18:00`)

**Tolerance** (`harmonization.tolerance`)
- Maximum allowed time shift in seconds (0 = no limit, default 3600)

**Auto-Round New Content** (`harmonization.auto_round`)
- Suggest harmonized times in backend forms

**Default Max Lifetime** (`advanced.default_max_lifetime`)
- Maximum cache lifetime when no temporal content exists (default 86400 = 24h)

**Debug Logging** (`advanced.debug_logging`)
- Enable detailed logging for debugging

See [Configuration Reference](Documentation/Configuration.rst) for detailed explanations and examples.

## Performance Summary

### Impact by Configuration

| Scoping Strategy | Cache Invalidations | Timing Strategy | Per-Page Overhead | Cache Reduction |
|-----------------|---------------------|-----------------|-------------------|-----------------|
| **Global** | All pages | Dynamic | 4 queries (~5-20ms) | N/A (default) |
| **Per-Page** | Affected page only | Dynamic | 4 queries (~5-20ms) | 95%+ |
| **Per-Content** | Affected pages via refindex | Dynamic | 4 queries (~5-20ms) | 99.7% |
| Any scoping | Same as above | **Scheduler** | **0 queries** | Same + zero overhead |
| Any scoping + **Harmonization** | 98%+ fewer transitions | Any timing | Same as timing | Additional 98%+ |

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
- Sites <1,000 pages (use global or per-page)
- Sites 1,000-10,000 pages (use per-page or per-content)
- Sites >10,000 pages (use per-content + scheduler + harmonization)

⚠️ **Evaluate Carefully**:
- High-traffic sites (>1M pageviews/month) - consider scheduler timing
- Multi-language sites - overhead multiplies per language
- CDN/Varnish setups - configure stale-while-revalidate

See [Performance Considerations](Documentation/Performance-Considerations.rst) for detailed analysis and mitigation strategies.

## Installation and Setup

The extension works immediately after installation with zero configuration required.

To optimize for your site:
1. Install via Composer: `composer require netresearch/nr-temporal-cache`
2. Configure strategies in Extension Manager (optional)
3. Test in staging environment
4. Deploy to production

See [Installation Guide](Documentation/Installation/Index.rst) and [Configuration Reference](Documentation/Configuration.rst) for details.

## Documentation

Comprehensive documentation available in `Documentation/`:

- **[Introduction](Documentation/Introduction/Index.rst)** - Problem background
- **[Installation](Documentation/Installation/Index.rst)** - Setup guide
- **[Configuration](Documentation/Configuration.rst)** - Complete configuration reference
- **[Backend Module](Documentation/Backend-Module.rst)** - Backend module user guide
- **[CLI Commands](Documentation/CLI-Commands.md)** - Command-line interface guide
- **[Reports Module](Documentation/Administrator/ReportsModule.rst)** - TYPO3 Reports integration
- **[Performance Considerations](Documentation/Performance-Considerations.rst)** - Performance impact and mitigation
- **[Architecture](Documentation/Architecture/Index.rst)** - Technical details

## Compatibility

| TYPO3 Version | PHP Version | Support |
|---------------|-------------|---------|
| 12.4 LTS      | 8.1 - 8.5   | ✅ Full |
| 13.4 LTS      | 8.2 - 8.5   | ✅ Full |
| 14.x          | 8.2 - 8.5   | ✅ Full |

## The Three-Phase Roadmap

### Phase 1: Extension with Strategies (Current - v0.9, beta)
- ✅ Dynamic cache lifetime via PSR-14 event
- ✅ Three scoping strategies (global, per-page, per-content), page-aware under dynamic timing
- ✅ Three timing strategies (dynamic, scheduler, hybrid)
- ✅ Time harmonization for reduced cache churn (CLI + backend persistence)
- ✅ Backend module for visual management
- **Status**: ✅ Implemented (v0.9.0, beta) — not yet released to TER

### Phase 2: Absolute Expiration API (Future TYPO3 Core)
- Extend `CacheTag` to support absolute timestamps
- Native support: `new CacheTag('tag', absoluteExpire: 1730124600)`
- System-wide temporal cache awareness
- **Status**: 🔄 RFC planned for TYPO3 v15/v16

### Phase 3: Automatic Temporal Detection (Future TYPO3 Core)
- Zero-configuration temporal caching
- Automatic detection of starttime/endtime dependencies
- Uses Phase 2 API transparently
- **Status**: 📋 Planned for TYPO3 v16+

Once Phase 2/3 are in TYPO3 core, this extension will be deprecated.

## Testing

**400+ automated tests** (316 unit + 90 functional) covering all scenarios:

```bash
# All tests (unit + functional)
composer test

# Unit tests only
composer test:unit

# Functional + integration tests
composer test:functional

# With coverage report
composer test:coverage
```

### Test Coverage
- **Unit Tests**: 316 tests with stubbed/mocked dependencies
- **Functional Tests**: 90 tests with real database integration
  (event listener, scheduler task, scoping/timing strategies, harmonization persistence, backend controller)
- **Coverage gate**: CI enforces a minimum line-coverage threshold via `composer ci:test:php:coverage:check`

## Contributing

Contributions welcome!

1. Fork the repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Commit changes: `git commit -am 'Add feature'`
4. Push to branch: `git push origin feature/my-feature`
5. Submit pull request

## Support & Issues

- **Issues**: [GitHub Issues](https://github.com/netresearch/t3x-nr-temporal-cache/issues)
- **Forge**: [TYPO3 Forge #14277](https://forge.typo3.org/issues/14277)
- **Documentation**: [docs.typo3.org](https://docs.typo3.org/)

## License

GPL-2.0-or-later - See [LICENSE](LICENSE) file

## Credits

**Developed by**: [Netresearch DTT GmbH](https://www.netresearch.de/)

**Addresses**: TYPO3 Forge Issue [#14277](https://forge.typo3.org/issues/14277) (reported 2004, unsolved for 20+ years)

**Related Issues**:
- [#16815](https://forge.typo3.org/issues/16815) - Sitemap ignoring start/end flags
- [#98964](https://forge.typo3.org/issues/98964) - Menu caching excessive cache_hash

---

**Made with ❤️ for the TYPO3 Community**
