<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md - TYPO3 Temporal Cache Extension

**Precedence**: The closest AGENTS.md to files you're changing wins. Root holds global defaults only.

## Project Overview

TYPO3 extension addressing Forge #14277: automatic page cache invalidation for time-based content (`starttime`/`endtime`). Three scoping strategies (global / per-page / per-content) combined with three timing strategies (dynamic / scheduler / hybrid).

- **Package**: `netresearch/nr-temporal-cache` (Composer) / extension key `nr_temporal_cache`
- **Namespace**: `Netresearch\TemporalCache\` (PSR-4 from `Classes/`)
- **Tech stack**: PHP ^8.1, TYPO3 ^12.4 || ^13.0 || ^14.0, license GPL-2.0-or-later
- **Version/state**: see `ext_emconf.php` (single source of truth)
- **Architecture map**: see `docs/ARCHITECTURE.md`

## Global Rules

- **Strict types**: `declare(strict_types=1)` in all PHP files
- **Standards**: TYPO3 coding standards via PHP-CS-Fixer (`Build/.php-cs-fixer.php`), PHPStan `level: max` (`Build/phpstan.neon`)
- **Architecture**: PSR-14 events, constructor DI via `Configuration/Services.yaml`; `final` classes except where a unit test doubles them
- **Testing**: unit + functional suites; CI uploads coverage to Codecov, which reports each PR against the 69% project target in `codecov.yml`. `ci:test:php:coverage:check` is the local equivalent, measured on the unit suite alone
- **Commits**: Conventional Commits; signed (`git commit -S --signoff`) — the `require-signed-commits` ruleset rejects unsigned commits at merge time and the DCO check requires the `Signed-off-by` trailer

## Commands

All QA runs through composer scripts (verified against `composer.json`):

```bash
composer ci:test:php:cgl        # Code style check (PHP-CS-Fixer dry-run)
composer ci:cgl                 # Auto-fix code style
composer ci:test:php:phpstan    # PHPStan level max
composer ci:test:php:rector     # Rector dry-run
composer ci:test:php:unit       # Unit tests
composer ci:test:php:functional # Functional tests
composer ci:test:php:coverage   # Unit-suite coverage report (HTML + clover)
composer ci:test:php:coverage:check  # Check that report against 69% statement coverage
composer docs:render            # Render Documentation/ via Docker
```

Make targets wrap the same scripts: `make cgl`, `make cgl-fix`, `make phpstan`, `make test`, `make test-unit`, `make test-functional`. Docker-based multi-version runs: `Build/Scripts/runTests.sh`.

## Project Structure

```
Classes/          # Source code (PSR-4) - see Classes/AGENTS.md
Configuration/    # Services.yaml, backend module, site sets (Default, Performance)
Documentation/    # ReST docs for docs.typo3.org - see Documentation/AGENTS.md
Tests/            # Unit, Functional, Integration - see Tests/AGENTS.md
Resources/        # Language files, backend templates, icons - see Resources/AGENTS.md
Build/            # phpunit configs, phpstan.neon, php-cs-fixer, rector, fractor, runTests.sh
docs/             # Agent-facing docs: ARCHITECTURE.md, exec-plans/
.ddev/            # Local development environment - see .ddev/AGENTS.md
.github/          # Workflows, CODEOWNERS, issue templates, Dependabot - see .github/workflows/AGENTS.md
```

## Code Conventions

- PSR-12 / TYPO3 CGL; type hints on all parameters, returns, properties
- `final` by default; a class the unit suite doubles directly stays non-final — `createStub()`/`createMock()` generate a subclass, and no bypass-finals bridge is in `require-dev`. `Classes/AGENTS.md` names the current exceptions and the check to run. Readonly properties for immutable dependencies
- PSR-14 events for extensibility; Context API for workspace/language awareness
- QueryBuilder with explicit restrictions only — see `Classes/AGENTS.md` for the canonical query patterns (workspace-aware, separate starttime/endtime queries)
- Strategies registered via service tags `nr_temporal_cache.scoping_strategy` / `nr_temporal_cache.timing_strategy` in `Configuration/Services.yaml`

## Security & Safety

- **No SQL injection**: always use QueryBuilder parameter binding
- **Query restrictions**: always apply `DeletedRestriction`; handle `hidden`, `starttime`, `endtime` explicitly where temporal logic requires it
- **Context isolation**: respect workspace and language context in every query
- **Input validation**: validate all external input (CLI arguments, extension configuration)
- **Type safety**: strict types + PHPStan level max

## PR/Commit Checklist

- [ ] Unit + functional tests pass (`composer ci:test:php:unit`, `composer ci:test:php:functional`)
- [ ] PHPStan clean (`composer ci:test:php:phpstan`)
- [ ] Code style compliant (`composer ci:test:php:cgl`)
- [ ] Unit coverage ≥69% (`composer ci:test:php:coverage` then `composer ci:test:php:coverage:check`); Codecov reports the same target for the combined suites
- [ ] Documentation updated if behavior changed
- [ ] No debug code (`var_dump`, `console.log`, ...)
- [ ] Commit signed with `-S --signoff`, Conventional Commit format

## Index of scoped AGENTS.md

- `./Classes/AGENTS.md` — PHP source (strategies, event listener, commands, query patterns)
- `./Tests/AGENTS.md` — Unit, functional, and integration test suites
- `./Documentation/AGENTS.md` — ReST documentation for docs.typo3.org
- `./Resources/AGENTS.md` — XLIFF translations (Crowdin-managed), backend templates, icons
- `./.ddev/AGENTS.md` — DDEV local development environment
- `./.github/workflows/AGENTS.md` — CI/CD workflows (thin callers of shared reusables)

## When Stuck

- **TYPO3 Docs**: https://docs.typo3.org/
- **Forge issue this extension addresses**: https://forge.typo3.org/issues/14277
- **Rendered docs source**: `Documentation/` (`composer docs:render`)
- **CI truth**: `.github/workflows/ci.yml` + `netresearch/typo3-ci-workflows` reusable

## House Rules

1. **Fix before feature**: fix critical bugs before adding features
2. **Test first**: write tests before fixing bugs or adding features
3. **Performance matters**: target <10ms overhead on cache operations
4. **Context aware**: always respect TYPO3 context (workspace, language)
5. **No shortcuts**: never skip deleted/hidden filters to force a green test

## When Instructions Conflict

Nearest AGENTS.md wins. Explicit user prompts override files.
