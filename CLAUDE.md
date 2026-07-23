# Claude Code Instructions

Read `.ai/AGENTS.md` first. It is the **single source of truth** for this project (Secomm Launchpad — Magento 2.4.8-p5 + Hyvä 3.x).

Do not modify files outside the requested scope.
Do not commit or push code.
Stop and request TL review before changing: **payment (Mollie / VNPAY), checkout (Mageplaza OSC), shipping (TableRate), order, customer/PII data, database migration, security-sensitive logic (including the Hyvä Packagist token in `auth.json`), or deployment scripts.**

Use `.claude/skills/` when relevant to the task.
Prefer reading the relevant `project-context/` subset (`.ai/AGENTS.md` §4) over scanning the entire codebase.
Output prose in **Vietnamese (vi)**; code, identifiers, file paths, and technical terms stay English.
