<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Execution Plans

Working directory for multi-step agent task plans.

- `active/` — plans currently being executed (create on demand)
- `completed/` — finished plans kept for reference (create on demand)

Keep plans small and disposable: goal, ordered steps, verification per step. Delete or move to `completed/` when done. Long-lived knowledge belongs in `docs/ARCHITECTURE.md` or the AGENTS.md files, not here.
