<!-- Managed by agent: keep sections and order; edit content, not structure.
Last updated: 2026-08-19 -->

# AGENTS.md — Documentation

## Overview

ReST documentation rendered for docs.typo3.org via `guides.xml` (TYPO3 render-guides). Chapters: `Introduction/`, `Installation/`, `Configuration/`, `Administrator/`, `Backend/`, `Architecture/`, `Performance/`, `Phases/`. Additional Markdown references for CLI commands (`CLI-Commands.md` and companions) live here but are not part of the rendered manual.

## Setup

Rendering runs in Docker — no local toolchain needed:

```bash
composer docs:render   # ghcr.io/typo3-documentation/render-guides, config Documentation/guides.xml
composer docs:serve    # Serve Documentation-GENERATED-temp/ on localhost:8090
composer docs:clean    # Remove the generated output
```

## Build & Tests

- After editing any `.rst`, run `composer docs:render` and check the output for warnings — the docs pipeline treats broken refs as errors
- Entry point is `Index.rst`; new chapters must be added to its toctree
- `Includes.rst.txt` is prepended to every file — keep shared directives there

## Conventions

- One sentence per line in ReST where practical (clean diffs)
- Use TYPO3 directives (`confval`, `versionadded`, `note`, `warning`) as in existing chapters
- Code blocks carry a language (`php`, `bash`, `typoscript`, `yaml`)
- US English

## Security

- No secrets, internal hostnames, or customer names in documentation
- Use placeholder values (`example.com`, `your-api-key`) in configuration examples

## Checklist

- [ ] `composer docs:render` completes without warnings
- [ ] New pages referenced from the relevant toctree (`Index.rst` or chapter index)
- [ ] Screenshots (if any) stored under the chapter they document
- [ ] No CLAUDE.md symlink added in this directory (the docs renderer rejects symlinks — the local CLAUDE.md is a regular file on purpose)

## Examples

- Chapter layout with index: `Architecture/Index.rst`
- Configuration reference style: `Configuration/`
- Performance guidance style: `Performance/`

## When stuck

- TYPO3 documentation guide: https://docs.typo3.org/permalink/h2document:start
- Render config: `guides.xml` in this directory
- Root `../AGENTS.md` for global rules
