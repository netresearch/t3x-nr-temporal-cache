<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — .github/workflows

## Overview

All workflows are thin callers of shared reusables — logic, pinning, and harden-runner live centrally:

| File | Calls | Purpose |
|------|-------|---------|
| `ci.yml` | `netresearch/typo3-ci-workflows/.github/workflows/ci.yml@main` | Test matrix PHP 8.1–8.5 × TYPO3 ^12.4/^13.0/^14.0 (with excludes), functional tests on |
| `checks.yml` | `netresearch/typo3-ci-workflows` + `netresearch/.github` reusables | security, gitleaks, zizmor, fuzz, license-check, CodeQL, scorecard, dependency-review, pr-quality + local gate job |
| `harness-verify.yml` | `netresearch/.github/.github/workflows/script-check.yml@main` | Agent-harness consistency (`Build/Scripts/verify-harness.sh`) |
| `release.yml` | `netresearch/typo3-ci-workflows/.github/workflows/release-typo3-extension.yml@main` | Extension release |
| `auto-merge-deps.yml`, `labeler.yml`, `community.yml`, `check-template-drift.yml` | `netresearch/.github` reusables | Dependency auto-merge, labeling, stale/lock/greetings, template drift |

## Workflow files

- The matrix truth lives in `ci.yml` `with:` inputs (`php-versions`, `typo3-versions`, `matrix-exclude`, `remove-dev-deps`, `rector-php-version`) — keep its explanatory comments in sync when changing them
- `remove-dev-deps` drops `netresearch/typo3-ci-workflows` from ^12.4 cells (unresolvable there); the Rector job is pinned to PHP 8.2

## Commands

```bash
gh pr checks <nr> -R netresearch/t3x-nr-temporal-cache   # PR check status
gh run watch <run-id>                                     # Follow a run
bash Build/Scripts/verify-harness.sh                      # What harness-verify runs (exit 2 = warnings only, passes)
```

## Workflow conventions

- Never inline scripts that exist as reusables in `netresearch/.github` or `netresearch/typo3-ci-workflows`
- `permissions: {}` at workflow level; grant per job
- Action version bumps arrive via Renovate — do not hand-pin new SHAs

## Security

- zizmor and scorecard lint these workflows — no `pull_request_target` with checkout of PR code, no secrets in `with:`/`env:` blocks visible to forks
- Keep top-level `permissions` empty and job-level grants minimal

## Checklist

- [ ] Changed matrix inputs still match `composer.json` constraints (PHP ^8.1, TYPO3 ^12.4 || ^13.0 || ^14.0)
- [ ] zizmor passes (runs in `checks.yml`)
- [ ] Root `../../AGENTS.md` updated if commands or CI claims changed

## Examples

- Thin-caller pattern: `ci.yml` (single job, all config via `with:`)
- Multi-reusable aggregation with gate: `checks.yml`

## When stuck

- Reusable sources: https://github.com/netresearch/typo3-ci-workflows and https://github.com/netresearch/.github
- Root `../../AGENTS.md` for global rules
