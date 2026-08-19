<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — Classes

## Overview

PHP source of the temporal cache extension. PSR-4 autoloaded under `Netresearch\TemporalCache\`, `declare(strict_types=1)` everywhere, PHPStan `level: max`, final classes with constructor DI.

Key layout (all paths relative to `Classes/`):

| Path | Purpose |
|------|---------|
| `EventListener/TemporalCacheLifetime.php` | Listens to `ModifyCacheLifetimeForPageEvent`; caps page cache lifetime at the next temporal transition |
| `Service/Scoping/` | `ScopingStrategyInterface` + global / per-page / per-content strategies, `ScopingStrategyFactory` |
| `Service/Timing/` | `TimingStrategyInterface` + dynamic / scheduler / hybrid strategies, `TimingStrategyFactory` |
| `Service/Cache/TransitionCache.php` | Request-level in-memory cache for transition lookups |
| `Service/HarmonizationService.php` | Rounds transition timestamps to configured time slots to reduce cache churn |
| `Domain/Repository/TemporalContentRepository.php` | All temporal DB queries (implements `TemporalContentRepositoryInterface`) |
| `Command/` | `AnalyzeCommand`, `HarmonizeCommand`, `ListCommand`, `VerifyCommand` (Symfony console) |
| `Task/TemporalCacheSchedulerTask.php` | Scheduler task (`extends AbstractTask`) for scheduler/hybrid timing |
| `Report/TemporalCacheStatusReport.php` | Reports module status provider (`StatusProviderInterface`) |
| `Controller/Backend/TemporalCacheController.php` | Backend module controller |
| `Configuration/ExtensionConfiguration.php` | Typed access to extension configuration |

## Setup

- Services are wired in `../Configuration/Services.yaml`; strategies are registered explicitly with tags `nr_temporal_cache.scoping_strategy` / `nr_temporal_cache.timing_strategy` and an `identifier` attribute — new strategies must be tagged there, autoregistration excludes them.
- Constructor injection only; no `GeneralUtility::makeInstance()` for own services.

## Build & Tests

```bash
composer ci:test:php:phpstan    # PHPStan level max (Build/phpstan.neon)
composer ci:test:php:cgl        # PHP-CS-Fixer dry-run
composer ci:test:php:unit       # Unit tests (Tests/Unit mirrors Classes/)
composer ci:test:php:functional # Functional tests against a real database
```

## Code style

- PSR-12 / TYPO3 CGL, full native type declarations, readonly properties for immutable dependencies
- PSR-14 events for extensibility; Context API for workspace/language state
- Strategy pattern for scoping/timing: implement the interface, register with the service tag, resolve via the factory

## Security

- QueryBuilder parameter binding only — never string-concatenated SQL
- Always apply `DeletedRestriction`; add `WorkspaceRestriction` with the current workspace ID for temporal queries; filter `hidden`, `sys_language_uid`, `starttime`/`endtime` explicitly
- Validate CLI input and extension configuration values before use

## PR/Commit Checklist

- [ ] `composer ci:test:php:phpstan` clean
- [ ] `composer ci:test:php:cgl` clean
- [ ] Unit test added/updated in `Tests/Unit/` mirroring the class path
- [ ] Query changes covered by a functional test
- [ ] No `var_dump`/debug output

## Examples

### GOOD: workspace-aware transition lookup (two separate queries)

```php
$qb = $this->getQueryBuilderForTable('pages');
$qb->getRestrictions()->removeAll()
    ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
    ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));
$starttime = $qb->select('starttime')->from('pages')
    ->where(
        $qb->expr()->eq('hidden', 0),
        $qb->expr()->gt('starttime', $now),
        $qb->expr()->neq('starttime', 0),
        $qb->expr()->eq('sys_language_uid', $languageId)
    )
    ->orderBy('starttime', 'ASC')->setMaxResults(1)
    ->executeQuery()->fetchOne();
// second, identical query for endtime; return min() of both results
```

### BAD: single OR query with LIMIT

```php
// ORDER BY starttime + addOrderBy endtime + setMaxResults(50) does NOT
// guarantee the earliest transition — it may sit beyond the LIMIT window.
// Fetching workspace ID but not adding WorkspaceRestriction breaks isolation.
```

## When stuck

- Root `../AGENTS.md` for global rules; `../docs/ARCHITECTURE.md` for the component map and data flow
- TYPO3 API docs: https://docs.typo3.org/
- Behavior documentation: `../Documentation/` (Architecture, Performance chapters)
