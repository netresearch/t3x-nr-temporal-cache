<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Security assurance

This document states what users of `netresearch/nr-temporal-cache` can and cannot expect in terms of security, where the extension's trust boundaries are, and which code and tests counter the weaknesses that matter for it. It describes the code on `main`; when this file and the code disagree, the code wins and this file is corrected. Vulnerabilities are reported as described in [SECURITY.md](../SECURITY.md), privately through GitHub Security Advisories. The component map is in [ARCHITECTURE.md](ARCHITECTURE.md).

The extension changes when TYPO3 regenerates cached pages. It does not render records and it does not decide which records are visible: TYPO3 core applies the `hidden`, `starttime`, `endtime` and `deleted` restrictions when it renders a page. The extension's security question is therefore one of freshness: how long a cached page can keep showing a record after TYPO3 would no longer render it, or before TYPO3 would start rendering it.

## What the extension does, security-wise

| Entry point | Who can reach it | Input | Code |
|-------------|------------------|-------|------|
| Page cache lifetime | Any frontend request that makes TYPO3 generate a page | The page id and the language and workspace of the TYPO3 `Context`; no request parameter | `Classes/EventListener/TemporalCacheLifetime.php`, `Classes/Service/Timing/`, `Classes/Service/Scoping/` |
| Scheduler task | The TYPO3 scheduler, configured by an administrator | The time of the last run from the TYPO3 registry | `Classes/Task/TemporalCacheSchedulerTask.php` |
| Backend module (Admin Tools > Temporal Cache) | Backend administrators (`'access' => 'admin'` in `Configuration/Backend/Modules.php`) | Page number, filter, wizard step; for `harmonize` a list of record uids and tables and a dry-run flag | `Classes/Controller/Backend/TemporalCacheController.php`, `Classes/Service/Backend/` |
| Reports status provider | Users who can open the Reports module (administrators in a default TYPO3 installation) | None | `Classes/Report/TemporalCacheStatusReport.php` |
| Console commands `temporalcache:analyze`, `:list`, `:verify`, `:harmonize` | Whoever can run `vendor/bin/typo3` on the server | Command options | `Classes/Command/` |
| Table registration | Other extensions, through `Configuration/Services.yaml` | Table and field names | `Classes/Service/TemporalMonitorRegistry.php` |

Only two paths write to content records: harmonization from the backend module (`HarmonizationService::harmonizeContent()`) and `temporalcache:harmonize` (`HarmonizeCommand::applyHarmonization()`). Both write only the `starttime` and `endtime` columns of the selected records. The scheduler task also stores the time of its last run in the TYPO3 registry (`sys_registry`, `TemporalCacheSchedulerTask::setLastRunTimestamp()`).

## Security expectations

What you can expect:

- **Visibility stays with TYPO3.** The extension never outputs record content in the frontend. It sets the page cache lifetime through `ModifyCacheLifetimeForPageEvent` and flushes cache tags; which records a regenerated page shows is decided by TYPO3's query restrictions.
- **Hidden and deleted records do not drive cache lifetimes.** The lifetime queries require the TCA `delete` and `disabled` columns to be 0 (`TemporalContentRepository::findMinTransitionForTable()` and `getEnableColumns()`). Covered by `Tests/Functional/Service/PageAwareScopingTest.php` (`testGetNextContentTransitionIsScopedToPageAndExcludesDeletedHidden`) and `Tests/Functional/Domain/Repository/DeletedRecordExclusionTest.php`.
- **Lifetimes follow the workspace and language of the request.** The scoping strategies read both from the TYPO3 `Context` (`Classes/Service/Scoping/ResolvesContextAspects.php`), the repository filters on them, and the request-level cache keys on them (`Classes/Service/Cache/TransitionCache.php`, `Tests/Unit/Service/Cache/TransitionCacheTest.php`).
- **A bounded lifetime under dynamic timing.** When the timing strategy returns a lifetime, the listener caps it at TypoScript `config.cache_period`, or, without it, at `advanced.default_max_lifetime` (`TemporalCacheLifetime::determineMaxLifetime()`).
- **A failure leaves TYPO3's lifetime in place.** An exception during the calculation is logged and the event is left unchanged, so the page is cached with the lifetime TYPO3 computed (`TemporalCacheLifetime::__invoke()`).
- **Only administrators reach the backend module**, and every module request, including the `harmonize` action, carries the TYPO3 backend route token, which TYPO3 checks for every route that is not `public`.
- **Harmonization writes need write permission** on every monitored table (`PermissionService::canModifyTemporalContent()`, checked in `TemporalCacheController::harmonizeAction()` before any write). The action runs as a dry run when the request carries no `dryRun` value, accepts only positive integer uids, and only tables registered in `TemporalMonitorRegistry` (`TemporalContentRepository::findByUid()`). `temporalcache:harmonize` asks for confirmation before writing and answers "no" when it runs non-interactively (`HarmonizeCommand::execute()`).
- **Database errors stay in the log.** A failed harmonization write returns a fixed message to the browser and logs the exception (`HarmonizationService::harmonizeContent()`).

What you cannot expect:

- **The extension is not an access control.** It cannot hide a record that TYPO3 would render, and its correctness does not replace the `hidden` flag or user group restrictions.
- **A cached page can show a record after its `endtime`, or miss one after its `starttime`, for a bounded time that depends on the configuration, including:**
  - scheduler timing sets no lifetime of its own: a cached page keeps the lifetime TYPO3 computes, which `config.cache_period` bounds (86400 seconds by default), and `TemporalCacheSchedulerTask` flushes the cache tags the scoping strategy names when it processes a passed transition;
  - hybrid timing uses the earlier lifetime of its two rules, so it behaves like scheduler timing only when both `timing.hybrid.pages` and `timing.hybrid.content` are set to `scheduler`;
  - per-page scoping under dynamic timing considers content transitions on the rendered page only, not content embedded from other pages through CONTENT or RECORDS objects (documented in `PerPageScopingStrategy::getNextTransition()` and in the README);
  - per-content scoping resolves pages through `sys_refindex` and falls back to the parent page when the reference index is off or empty, so an outdated reference index narrows the pages that are flushed;
  - only tables registered in `TemporalMonitorRegistry` are watched; `pages` and `tt_content` are registered by default.
- **Caches outside TYPO3's page cache are not touched.** A CDN, a reverse proxy or the browser keep their own lifetimes.
- **Harmonization moves the visibility window on purpose.** It rewrites `starttime` and `endtime` by up to `harmonization.tolerance` seconds, earlier or later, so a record may become visible or invisible up to that long before or after the time an editor entered. The write uses the database connection directly, not DataHandler: it creates no `sys_log` or `sys_history` entry (the extension logs it through its PSR-3 logger instead), and it does not apply record-level page permissions or workspace versioning.
- **Many transitions mean many regenerated pages.** With global scoping and dynamic timing every transition expires every cached page (see the Performance chapter in `Documentation/Performance/`). Editors who set many distinct start and end times raise the page generation load; harmonization exists to reduce that.
- **Extension configuration is trusted.** Values are cast to their types (`Classes/Configuration/ExtensionConfiguration.php`), not range-checked. Unknown strategy names fall back to the highest-priority strategy (`Classes/Service/SelectsNamedStrategy.php`); slots that do not match `H:MM` or `HH:MM` are ignored (`HarmonizationService::parseTimeSlot()`).

## Threat model and trust boundaries

Actors:

- **Frontend visitors** trigger page generation. They choose the page and language through the URL, which TYPO3 resolves before the extension runs; the extension reads no request parameter in the frontend.
- **Editors** set `starttime`, `endtime` and `hidden` on records. These values cross into the extension as data: they determine lifetimes and the tags the scheduler task flushes.
- **Administrators** configure the extension, run the backend module and schedule the task.
- **Operators with shell access** run the console commands with the database rights of the TYPO3 instance.
- **Other extensions** register tables and fields in `TemporalMonitorRegistry`; this is configuration at the same trust level as the extension itself.

Trust boundaries:

1. **Database content to cache lifetime.** Editor-controlled timestamps decide when pages expire. The repository reads them with parameterised queries and ignores hidden and deleted rows.
2. **Backend HTTP request to database write.** The `harmonize` action is the only request handler that writes. TYPO3 authenticates the backend user, checks the route token and the module's admin restriction; the action then checks table write permissions and filters the posted uids and table names.
3. **Console to database.** Commands run with full database access; `temporalcache:harmonize` restricts `--table` to `pages` or `tt_content` and needs confirmation for writes.
4. **Extension configuration and table registrations to queries.** Table and field names are used as SQL identifiers through TYPO3's QueryBuilder, which quotes them; values are never concatenated into SQL.

## Secure design principles applied

- **Least privilege:** the module is admin-only; writes additionally require `tables_modify` on every monitored table, even though administrators pass that check by definition, so a later relaxation of the module access still leaves the write check in place.
- **Fail-safe defaults:** backend harmonization is a dry run when the request does not say otherwise; console harmonization needs an interactive "yes"; harmonization is off by default (`ext_conf_template.txt`); a failed lifetime calculation leaves TYPO3's lifetime untouched.
- **Economy of mechanism:** the extension only adjusts lifetimes and flushes tags; visibility logic stays in TYPO3 core.
- **Complete mediation:** the permission check runs on every `harmonize` request, not only when the module renders the button (`TemporalCacheController::harmonizeAction()`; `canModifyContent` in `contentAction()` only controls the view).
- **Separation of configuration from data:** strategies are chosen from extension configuration and service tags (`Configuration/Services.yaml`), never from request data.

## Countering common weaknesses

| Weakness | Countermeasure | Evidence |
|----------|----------------|----------|
| SQL injection (CWE-89, OWASP A03) | Every value that comes from a request, the configuration or the database goes through `createNamedParameter()`; the only value inlined is the integer constant `0` in the `hidden`, enable-column, `t3ver_wsid`, `starttime` and `endtime` conditions; the one literal expression, `MIN(<field>)`, quotes the field with `quoteIdentifier()` | `Classes/Domain/Repository/TemporalContentRepository.php`, `Classes/Service/RefindexService.php` |
| Cross-site scripting (CWE-79, A03) | Fluid escapes all output; `Resources/Private/` contains no `f:format.raw`; `Resources/Public/JavaScript/backend-module.js` writes text with `textContent` and TYPO3's `Notification` API and uses no `innerHTML`; the AJAX response is `json_encode()`d; the Reports status messages contain fixed text, counts and strategy names checked against a fixed list | `Resources/Private/Templates/Backend/TemporalCache/`, `Resources/Public/JavaScript/backend-module.js`, `TemporalCacheStatusReport::getExtensionStatus()` |
| Cross-site request forgery (CWE-352, A01) | Module routes are not `public`, so TYPO3's backend `RouteDispatcher` rejects a request without a valid route token | `Configuration/Backend/Modules.php` |
| Missing authorization (CWE-862, A01) | Admin-only module; `PermissionService` check before writes | `Classes/Service/Backend/PermissionService.php`, `Tests/Unit/Service/Backend/PermissionServiceTest.php` |
| Improper input validation (CWE-20) | Posted uids must be positive integers, tables must be registered; console `--table` is limited to two names | `TemporalCacheController::harmonizeAction()`, `HarmonizeCommand::execute()`, `Tests/Unit/Command/HarmonizeCommandTest.php` |
| Information exposure through error messages (CWE-209) | Fixed message to the client, exception detail to the log | `HarmonizationService::harmonizeContent()` |
| Uncontrolled resource consumption (CWE-400) | Transition lookups are `MIN()` queries on indexed columns (`ext_tables.sql`), cached per request; scheduler timing removes them from page rendering | `TemporalContentRepository::getNextTransition()`, `Classes/Service/Cache/TransitionCache.php` |
| Vulnerable and outdated components (A06) | Composer Audit, Dependency Review and PHP License Audit on every pull request; Renovate (`renovate.json`) proposes updates | `.github/workflows/checks.yml` |

## Verification

- Unit and functional suites (`Build/phpunit/UnitTests.xml`, `Build/phpunit/FunctionalTests.xml`) run on every pull request across the PHP and TYPO3 matrix in `.github/workflows/ci.yml`; the functional suite runs against a real database (SQLite in CI).
- PHPStan at `level: max` (`Build/phpstan.neon`), Rector and PHP-CS-Fixer run in the same workflow.
- Opengrep, CodeQL for the JavaScript, Betterleaks secret scanning and zizmor run from `.github/workflows/checks.yml`; the README section "Governance and policies" lists every pull-request check.
