<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — .ddev

## Overview

DDEV environment for developing the extension against real TYPO3 instances. Project `temporal-cache`, type `php`, PHP 8.2, MariaDB 10.11. Custom commands install TYPO3 v12/v13 with the extension mounted. See `README.md` and `MAKEFILE-INTEGRATION.md` in this directory for the full walkthrough.

## Setup

```bash
ddev start
ddev install-v12    # TYPO3 v12 instance with the extension
ddev install-v13    # TYPO3 v13 instance
ddev install-all    # Both
```

Custom commands live in `commands/web/` (`install-v12`, `install-v13`, `install-all`, `fix-typo3-referrer`) and `commands/host/` (`docs`).

## Build & Tests

- Run composer QA inside the container: `ddev composer ci:test:php:unit` etc. (script names in root `../AGENTS.md` → Commands)
- CI does not use DDEV — the authoritative matrix runs on GitHub Actions; DDEV is for debugging and manual backend verification

## Conventions

- `config.yaml` is the base config; per-developer overrides belong in `config.*.yaml` files that stay untracked
- Provider examples (`providers/*.example`, upstream defaults) are DDEV scaffolding — do not edit them for project needs

## Security

- Never commit credentials or API keys into DDEV configs
- The environment is local-only; do not expose it (`ddev share`) with real data loaded

## Checklist

- [ ] `ddev start` succeeds after config changes
- [ ] Install commands still work if you touched `commands/web/`
- [ ] No secrets in tracked config files

## Examples

- Custom web command pattern: `commands/web/install-v12`
- Host command pattern: `commands/host/docs`

## When stuck

- `README.md` and `MAKEFILE-INTEGRATION.md` in this directory
- DDEV docs: https://ddev.readthedocs.io/
- Root `../AGENTS.md` for global rules
