<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — Resources

## Overview

Frontend-free resource assets:

- `Private/Language/` — XLIFF translation files (`locallang_mod.xlf`, `locallang_reports.xlf` and ~30 language variants each)
- `Private/Templates/Backend/TemporalCache/` — Fluid templates for the backend module (`Dashboard.html`, `Content.html`, `Wizard.html`)
- `Private/Layouts/` — Fluid layouts (`Default.html`, `Module.html`)
- `Public/Icons/` — `Extension.svg`, `ModuleIcon.svg`, `ModuleIcon.legacy.svg`
- `Public/JavaScript/backend-module.js` — backend module ES module (registered in `../Configuration/JavaScriptModules.php`)

## Setup

- Translations are managed via Crowdin (`../crowdin.yml`) — only the source (English, unprefixed) XLIFF files are edited in this repo; `<lang>.locallang_*.xlf` variants are synced by Crowdin
- Backend module wiring: `../Configuration/Backend/Modules.php`, icons registered in `../Configuration/Icons.php`

## Build & Tests

- No build step — assets ship as-is
- After label changes, flush TYPO3 caches in the dev instance to see them
- Fluid template changes are exercised by the backend controller functional tests (`../Tests/Functional/Controller/`)

## Conventions

- XLIFF: keep `<trans-unit>` ids stable — they are referenced from PHP and Fluid; new labels go into the English source file only
- Fluid: no business logic in templates; data is prepared in `TemporalCacheController`
- SVG icons: keep viewBox, avoid embedded raster images

## Security

- Escape all output in Fluid (default `{variable}` escaping stays on; no `f:format.raw` on user-provided data)
- No inline event handlers in backend JavaScript; the module JS is CSP-compatible as an ES module

## Checklist

- [ ] New labels added to the English source XLIFF only (Crowdin fills translations)
- [ ] Label keys referenced in PHP/Fluid actually exist
- [ ] Backend module still renders (dashboard, wizard, content views)
- [ ] No raw output of user data in Fluid templates

## Examples

- Translation source file: `Private/Language/locallang_mod.xlf`
- Backend template pattern: `Private/Templates/Backend/TemporalCache/Dashboard.html`

## When stuck

- Fluid docs: https://docs.typo3.org/permalink/t3coreapi:fluid
- XLIFF handling in TYPO3: https://docs.typo3.org/permalink/t3coreapi:xliff
- Root `../AGENTS.md` for global rules
