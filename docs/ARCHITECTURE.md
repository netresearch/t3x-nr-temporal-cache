# Architecture

Agent-facing component map. Verify claims against the referenced files before relying on them; update this file when components move.

## System Overview

The extension addresses TYPO3 Forge #14277: pages containing time-controlled records (`starttime`/`endtime`) must drop out of cache exactly when a transition occurs. A PSR-14 event listener caps the page cache lifetime at the next upcoming transition (dynamic timing), a scheduler task flushes caches on transitions (scheduler timing), or both combine (hybrid). Which records count as relevant is decided by a scoping strategy (global, per-page, per-content).

## Components

| Component | Path | Role |
|-----------|------|------|
| Cache lifetime listener | `Classes/EventListener/TemporalCacheLifetime.php` | Handles `ModifyCacheLifetimeForPageEvent`; delegates to the timing strategy, caps lifetime via TypoScript `config.cache_period` / extension default |
| Timing strategies | `Classes/Service/Timing/` | `DynamicTimingStrategy`, `SchedulerTimingStrategy`, `HybridTimingStrategy` behind `TimingStrategyInterface`; chosen by `TimingStrategyFactory` from extension configuration |
| Scoping strategies | `Classes/Service/Scoping/` | `GlobalScopingStrategy`, `PerPageScopingStrategy`, `PerContentScopingStrategy` behind `ScopingStrategyInterface`; chosen by `ScopingStrategyFactory` |
| Repository | `Classes/Domain/Repository/TemporalContentRepository.php` | All temporal DB queries (next transition, temporal content lookup); interface in the same directory |
| Domain models | `Classes/Domain/Model/` | `TemporalContent`, `TransitionEvent` value objects |
| Transition cache | `Classes/Service/Cache/TransitionCache.php` | Request-level in-memory cache preventing duplicate transition queries |
| Harmonization | `Classes/Service/HarmonizationService.php` | Rounds transitions to configured time slots to reduce cache churn |
| Refindex integration | `Classes/Service/RefindexService.php` | Resolves which pages reference temporal records via `sys_refindex` |
| Monitor registry | `Classes/Service/TemporalMonitorRegistry.php` | Registry of monitored tables/fields |
| Scheduler task | `Classes/Task/TemporalCacheSchedulerTask.php` | Flushes page caches on transitions for scheduler/hybrid timing |
| CLI commands | `Classes/Command/` | `AnalyzeCommand`, `HarmonizeCommand`, `ListCommand`, `VerifyCommand` |
| Backend module | `Classes/Controller/Backend/TemporalCacheController.php` + `Classes/Service/Backend/` | Dashboard, wizard, statistics, permissions |
| Reports status | `Classes/Report/TemporalCacheStatusReport.php` | TYPO3 Reports module status provider |
| Extension config | `Classes/Configuration/ExtensionConfiguration.php` | Typed access to `ext_conf_template.txt` settings |

## Dependency Rules

Wiring is declared in `Configuration/Services.yaml` (no phpat architecture test suite exists):

- Strategies are excluded from autoregistration and tagged `nr_temporal_cache.scoping_strategy` / `nr_temporal_cache.timing_strategy`; the factories consume those tags via `!tagged_iterator` and select the strategy whose `getName()` matches the extension configuration, falling back to the highest-priority tagged service.
- The event listener is registered with tag `event.listener`, identifier `temporal-cache/modify-cache-lifetime`.
- Services depend on the repository interface and `ExtensionConfiguration`, not on concrete strategy classes.

## Data Flow

1. Frontend page render fires `ModifyCacheLifetimeForPageEvent`.
2. `TemporalCacheLifetime` asks the configured timing strategy for a lifetime; the strategy consults the scoping strategy and `TemporalContentRepository` (through `TransitionCache`) for the next `starttime`/`endtime` transition.
3. Dynamic: lifetime = seconds until next transition (capped). Scheduler: listener returns null, the scheduler task (`TemporalCacheSchedulerTask`) flushes the `pages` cache group when transitions pass. Hybrid: conditional mix.
4. Optional harmonization rounds transition timestamps into slots before they influence lifetimes/flushes.

## Key Decisions

- Rendered documentation (chapters `Documentation/Architecture/`, `Documentation/Performance/`, `Documentation/Phases/`) is the authoritative narrative; this file is only the code map.
- The composer description flags the extension as a Phase 1 experimental approach: global scoping expires ALL page caches on every transition — read `Documentation/Performance/` before changing default strategies.
