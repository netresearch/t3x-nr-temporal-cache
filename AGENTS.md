<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2025-10-28 -->

# AGENTS.md - TYPO3 Temporal Cache Extension

**Precedence**: The closest AGENTS.md to files you're changing wins.

## Global Rules

- **Language**: PHP 8.1+ with strict types (`declare(strict_types=1)`)
- **Framework**: TYPO3 12.4+ and 13.0+
- **Standards**: PSR-12, PHPStan Level Max (highest strictness)
- **Architecture**: PSR-14 events, dependency injection, final classes
- **Testing**: 70% minimum coverage, multi-database support (SQLite, MariaDB, PostgreSQL)

## Pre-commit Checks

```bash
# Type check
composer code:phpstan

# Lint & format check
composer code:style:check

# Auto-fix format
composer code:style:fix

# Run tests
composer test

# Full CI check
composer ci
```

## Project Structure

```
t3x-nr-temporal-cache/
├── Classes/              # Source code (PSR-4)
├── Configuration/        # TYPO3 configuration
├── Documentation/        # ReST documentation
├── Tests/               # PHPUnit tests
│   ├── Unit/           # Unit tests (mocked dependencies)
│   ├── Functional/     # Functional tests (real database)
│   └── Fixtures/       # CSV test data
├── Build/              # Build configuration
└── .ddev/              # Development environment
```

## Code Conventions

### PHP Style
- PSR-12 coding standard
- Strict types: `declare(strict_types=1)` in all files
- Type hints: All parameters, return types, properties
- Final classes: Prevent inheritance unless designed for extension
- Readonly properties: Use for immutable dependencies

### TYPO3 Patterns
- **Events**: Use PSR-14 for extensibility
- **DI**: Constructor injection via Services.yaml
- **Context**: Use Context API for workspace/language awareness
- **QueryBuilder**: Always use TYPO3's QueryBuilder with restrictions
- **Restrictions**: Apply DeletedRestriction, HiddenRestriction appropriately

### Database Queries
```php
// ✅ GOOD: With restrictions
$queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
$queryBuilder->getRestrictions()
    ->removeAll()
    ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

// ❌ BAD: No restrictions (includes deleted records)
$queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
```

### Testing
- **Unit tests**: Mock all dependencies, test logic in isolation
- **Functional tests**: Real database, test TYPO3 integration
- **Integration tests**: Complete workflow verification
- **CSV fixtures**: Use for functional test data
- **Performance tests**: Validate <50ms for 200 records

## Security & Safety

- **No SQL injection**: Always use QueryBuilder parameter binding
- **Query restrictions**: Always filter deleted=0, hidden=0 where appropriate
- **Context isolation**: Respect workspace and language context
- **Input validation**: Validate all external input
- **Type safety**: Use strict types and PHPStan Level 8

## PR/Commit Checklist

- [ ] All tests pass (`composer test`)
- [ ] PHPStan clean (`composer code:phpstan`)
- [ ] Code style compliant (`composer code:style:check`)
- [ ] Coverage ≥70% (`composer test:coverage:check`)
- [ ] Documentation updated if API changed
- [ ] CHANGELOG.md updated with changes
- [ ] No debug code (var_dump, console.log, etc.)

## Examples

### ✅ GOOD: Workspace-Aware Queries with Separate Starttime/Endtime (v1.0.1+)
```php
private function getNextPageTransition(): ?int
{
    $now = time();
    $workspaceId = $this->context->getPropertyFromAspect('workspace', 'id');
    $languageId = $this->context->getPropertyFromAspect('language', 'id');

    // Query 1: Earliest future starttime
    $qb1 = $this->getQueryBuilderForTable('pages');
    $qb1->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));

    $starttime = $qb1->select('starttime')->from('pages')
        ->where(
            $qb1->expr()->eq('hidden', 0),
            $qb1->expr()->gt('starttime', $now),
            $qb1->expr()->neq('starttime', 0),
            $qb1->expr()->eq('sys_language_uid', $languageId)
        )
        ->orderBy('starttime', 'ASC')
        ->setMaxResults(1)
        ->executeQuery()->fetchOne();

    // Query 2: Earliest future endtime
    $qb2 = $this->getQueryBuilderForTable('pages');
    $qb2->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));

    $endtime = $qb2->select('endtime')->from('pages')
        ->where(
            $qb2->expr()->eq('hidden', 0),
            $qb2->expr()->gt('endtime', $now),
            $qb2->expr()->neq('endtime', 0),
            $qb2->expr()->eq('sys_language_uid', $languageId)
        )
        ->orderBy('endtime', 'ASC')
        ->setMaxResults(1)
        ->executeQuery()->fetchOne();

    // Return minimum
    $transitions = array_filter([
        $starttime !== false ? (int)$starttime : null,
        $endtime !== false ? (int)$endtime : null,
    ]);
    return !empty($transitions) ? min($transitions) : null;
}
```

### ❌ BAD: Missing Workspace Filtering (v1.0.0 bug)
```php
private function getNextPageTransition(): ?int
{
    $queryBuilder = $this->getQueryBuilderForTable('pages');
    $workspaceId = $this->context->getPropertyFromAspect('workspace', 'id');
    // ❌ Retrieved but never used - workspace isolation broken!

    $result = $queryBuilder
        ->select('starttime', 'endtime')
        ->from('pages')
        ->where(
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('hidden', 0)
            // ❌ No workspace filtering
        )
        ->executeQuery();
}
```

### ❌ BAD: LIMIT with OR Logic (v1.0.0-v1.1.0 bug)
```php
$result = $queryBuilder
    ->where(
        $queryBuilder->expr()->or(
            $queryBuilder->expr()->gt('starttime', $now),
            $queryBuilder->expr()->gt('endtime', $now)
        )
    )
    ->orderBy('starttime', 'ASC')  // ❌ Doesn't guarantee earliest
    ->addOrderBy('endtime', 'ASC')
    ->setMaxResults(50)             // ❌ Could miss transition at row 51
    ->executeQuery();
// ❌ Correctness bug: Earliest transition might not be in first 50 rows
```

## When Stuck

- **TYPO3 Docs**: https://docs.typo3.org/
- **Forge Issues**: https://forge.typo3.org/projects/typo3cms-core
- **Extension Key**: temporal_cache
- **Issue**: Addresses Forge #14277 (20-year-old caching problem)
- **Review**: See `claudedocs/COMPREHENSIVE_REVIEW.md`

## House Rules

1. **Fix before feature**: Always fix critical bugs before adding features
2. **Test first**: Write tests before fixing bugs or adding features
3. **Performance matters**: Target <10ms overhead on cache operations
4. **Context aware**: Always respect TYPO3 context (workspace, language)
5. **Query smart**: Use LIMIT, ORDER BY, and proper restrictions
6. **Document well**: Update docs when behavior changes
7. **No shortcuts**: Don't skip deleted/hidden filters to "make it work"

---

**Version**: 1.0.0
**Maintained by**: Netresearch DTT GmbH
**Last review**: 2025-10-28

## Commit Signing

Signed commits are required: `git commit -S --signoff`. The `require-signed-commits` ruleset on the default branch rejects unsigned commits at merge time, and the DCO check additionally requires the `Signed-off-by` trailer. Quickest setup is SSH signing — register your SSH key as a *signing key* on your GitHub account, then `git config gpg.format ssh && git config user.signingkey ~/.ssh/<key>.pub`.
