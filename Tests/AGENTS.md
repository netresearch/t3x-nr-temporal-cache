<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — Tests

## Overview

PHPUnit test suites on `typo3/testing-framework`:

- `Unit/` — isolated tests with mocked dependencies, mirrors `Classes/` (Command, Configuration, Domain, EventListener, Report, Service, Task)
- `Functional/` — real database, TYPO3 integration (Backend, Controller, EventListener, Service, Task, Integration), CSV fixtures in `Functional/Fixtures/`
- `Integration/` — end-to-end workflow tests (`CompleteWorkflowIntegrationTest`, `TemporalCacheInvalidationTest`)

## Setup

- PHPUnit configs live in `../Build/phpunit/`: `UnitTests.xml`, `FunctionalTests.xml`
- Functional tests need a database; CI runs them via the shared `netresearch/typo3-ci-workflows` reusable
- Docker-based multi-version runs: `../Build/Scripts/runTests.sh`

## Build & Tests

```bash
composer ci:test:php:unit            # Unit suite
composer ci:test:php:functional      # Functional suite
composer ci:test:php:coverage        # Coverage HTML + clover (.Build/logs/clover.xml)
composer ci:test:php:coverage:check  # Gate: 69% line coverage (Build/scripts/check-coverage.php)
```

Make wrappers: `make test`, `make test-unit`, `make test-functional`.

## Code style

- Test classes mirror the source path: `Classes/Service/Foo.php` → `Tests/Unit/Service/FooTest.php`
- One behavior per test method; descriptive names; data providers for input matrices
- Mock all dependencies in unit tests; never mock the system under test
- Use CSV fixtures for functional test data (`Functional/Fixtures/`)

## Security

- Never commit real credentials or PII into fixtures — use placeholder values
- Functional tests must not depend on network access
- Keep expected-error output captured and asserted, not printed

## Checklist

- [ ] New code paths have unit tests with meaningful assertions
- [ ] Query/database changes have functional coverage
- [ ] Suites green locally: `composer ci:test:php:unit && composer ci:test:php:functional`
- [ ] Coverage gate holds: `composer ci:test:php:coverage:check`
- [ ] No weakened or skipped tests to force green

## Examples

- Unit strategy test pattern: `Unit/Service/Timing/` (one test class per strategy)
- Functional event listener test: `Functional/EventListener/`
- Workflow integration: `Integration/CompleteWorkflowIntegrationTest.php`

## When stuck

- `../Build/phpunit/*.xml` for suite wiring, bootstrap, environment variables
- typo3/testing-framework docs: https://docs.typo3.org/permalink/t3coreapi:testing
- Root `../AGENTS.md` for global rules
