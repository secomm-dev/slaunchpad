# CHANGELOG — Project AI Tool (Secomm Launchpad)

> Runtime log of changes to this project's `.ai/` AI-toolkit. Updated via the `update-project-ai-tool` function (governed by `.ai/rules/ai-tool-self-update.md` + `before-ai-tool-update` / `after-ai-tool-update` hooks). One entry per change; never delete.

## [1.0.0] — 2026-07-14

### Added
- **Initial generation.** Full v4 Project AI Toolkit generated from the approved `PROJECT_AI_BLUEPRINT.md` (Secomm Production AI Toolkit v4; route PROJECT_INITIALIZATION).
- `.ai/AGENTS.md` — single source of truth (Magento 2.4.8-p5 + Hyvä 3.x + Tailwind v4 + Magewire; §1–14 + §8.5 Outcome-Oriented Execution + §8.6 Execution Efficiency).
- `project-context/` 01–12 + `CODING_RULES.md` + `memory/` (8 files) + `engineering-standards/` (base + Magento + Hyvä + PHP + tech).
- `instincts/`, `rules/` (11), `hooks/` (12), `mcp/` (4), `commands/`, `evidence/`, `checklists/`, `templates/`.
- `agents/` (11), `functions/` (base + magento + hyva + security + performance), `audits/` (base + magento + hyva).
- v4 subsystems: `research/`, `workflow/`, `intents/`, `initialization/`, `runtime/` (Human Navigator), enablement (`WELCOME.md` + `guides/` + `reference/` + `learning/`).
- Root: `AGENTS.md` (pointer), `CLAUDE.md` (thin wrapper), `pull_request_template.md`, `.github/copilot-instructions.md`, `codex/AGENTS.md`.
- `.claude/skills/` (9 base + magento + security) + `.claude/skills/dev/` (Magento incl. Hyvä).
- `toolkit/` (PROJECT_AI_BLUEPRINT.md, generation-log.md, selection-manifest.md, validation-report.md, toolkit-version.md).

### Notes
- Target resolved uniquely (`/var/www/html/slaunchpad`, P2+P4); generator root excluded (Hard Gate 6 passed).
- Stack-specific corrections applied: Tailwind v4 CSS-first config (no `tailwind.config.js`); project high-risk areas (VNPAY payment/IPN, Mageplaza OSC checkout, VN address dropdown, undefined production infra) reflected in AGENTS §11/§12 + SECURITY_BASELINE.
- Open project decisions seeded in `runtime/DECISION_QUEUE.md` (DEC-1 multi-store, DEC-2 prod infra, DEC-6 VNPAY hardening).

## [1.1.0] — 2026-07-15

### Added — Capability-Contract upgrade (toolkit Entry (d))
- **Capability registry** `.ai/registry/toolkit-capability-registry.yaml` + `README.md` — single source of truth for every advertised capability (30 + 1 deprecated + 2 aliases; per-tool adapter + invocation_type + portable fallback + approval_gate).
- **Navigator skills** `.claude/skills/nav-{next,help,decisions,evidence,explain,approve,tbd}/` — the 7 nav commands (`/next` `/help-nav` `/decisions` `/evidence` `/explain` `/approve` `/tbd`) are now real invokable Claude skills (were NL-only). `approve`/`tbd` are mutating (DEC-ID + confirm-before-mutate).
- **Validator** `.ai/bin/project-ai-validate` (bash) — `--project` mode validates this project standalone.
- **7 missing templates** `.ai/templates/{implementation-plan,incident-report,post-mortem,release-checklist,meeting-notes,change-log,uat-signoff}-template.md` (fixes broken refs in workflow guides + `/plan` shim).

### Changed
- `guides/CHEATSHEET.md` — nav table now carries `/next`, the mutating (⚠️) guard, and the per-tool note (sourced from the registry).
- `intents/supported-commands.md` — added per-tool `Claude` / `Codex·Copilot` invocation_type columns; **deprecated `/tl-review`** (it is an approval gate, not a command); added registry pointer + legend.
- `commands/commands-index.md` — added registry source-of-truth pointer + alias/deprecation note.

### Notes
- Applied via `bin/project-ai-upgrade --apply` (idempotent; 17 items added, 0 overwritten; 13 existing skills preserved as user-extension; project-owned content untouched).
- Project validates clean: `bash .ai/bin/project-ai-validate --project /var/www/html/slaunchpad` → VALID (0 FAIL).

## [1.2.0] — 2026-07-16

### Changed — Skill directories renamed to canonical command names
- **15 skills renamed** so every advertised `/command` resolves to a real Claude Code skill (was: skills dir-named `analyze-ticket`/`nav-next`/`generate-spec`/… with no frontmatter, so `/task` `/next` `/spec` etc. did not invoke). Map: `analyze-ticket`→`task`, `generate-spec`→`spec`, `pre-review`→`review-code`, `qc-testcase`→`testcase`, `deployment-checklist`→`deploy`, `summarize-project`→`status`, `resume-work`→`continue`, `project-context-update`→`update-memory`, `nav-{next,help,decisions,evidence,explain,approve,tbd}`→`{next,help-nav,decisions,evidence,explain,approve,tbd}`. `compact-context` unchanged.
- `.claude/skills/` — 15 dirs renamed; `.ai/registry/toolkit-capability-registry.yaml` — adapter/canonical_source paths updated; `delivery.estimate` claude → `invocation_type: nl-only` (no own skill; runs via `task` effort field).
- `guides/CHEATSHEET.md` + role guides + `QUICK_START` + `learning/` — Top-5/nav command cells canonicalized (`/review`→`/review-code`, `/qc-testcase`→`/testcase`, `/summarize-project`→`/status`, `/deployment-checklist`→`/deploy`, `/project-context-update`→`/update-memory`, `/resume-work`→`/continue`); `nav-*` glob → explicit id list.
- `functions/`, `agents/`, `engineering-standards/`, `commands/`, `rules/`, `workflow/`, `toolkit/`, `reference/` — skill-id references updated; **function names preserved** (`analyze-ticket`, `resume-work` functions unchanged — only their internal skill refs renamed).
- Legacy aliases `/review`, `/resume-work` retained in registry `aliases:` + `intents/supported-commands.md` for backward-compat.

### Notes
- Toolkit-owned contract files (registry, validator, 7 templates, 7 nav skills) confirmed in-sync with `secomm-production-ai-toolkit` via diff. `bash .ai/bin/project-ai-validate --project /var/www/html/slaunchpad` → VALID.
- Source change: `secomm-production-ai-toolkit` skill-rename (toolkit `bin/project-ai-upgrade` refactored to derive the nav-skill list from the registry).

## [1.3.0] — 2026-07-23

### Added — Phase-1 retrofit sync (toolkit batch 2026-07-22)
- **Migration markers stamped.** `.ai/toolkit/toolkit-version.md` now carries `applied_migrations: [P1A..P1F]` + `phase1_capabilities` mirror + `last_upgraded_at: 2026-07-23` (engine `--apply` stamp + manual metadata align). Project no longer reads as pre-Phase-1 baseline.
- **5 templates added** via engine: `current-workflow-state`, `next-action`, `session-state`, `toolkit-version`, `workflow-history` (→ `.ai/templates/`).
- **2 runtime guides added** (manual, outside engine manifest): `workflow/WORKFLOW_RUNTIME_GUIDE.md`, `initialization/INITIALIZATION_RUNTIME_GUIDE.md`.
- **NOT added** `bin/run-contract-tests` — verified toolkit-workspace-only: it resolves `ROOT` relative to its own path and needs `$ROOT/shared-core/registry/` (toolkit layout), which the project layout (`.ai/registry/`) lacks. Runs green only from the toolkit (`PASS=25 FAIL=0`); project contract is covered by `project-ai-validate` + `project-ai-upgrade` dry-run (both green).

### Changed — contract files force-synced to toolkit 2026-07-22 (additive; no project custom lost)
- `.ai/bin/project-ai-validate` — now 26 KB / 6 Phase-1 `--check-*` modes (records, modes, project-state, runtime-separation, context-efficiency, governance-dedup).
- `.ai/registry/toolkit-capability-registry.yaml` — additive `phase1_capabilities` block; `spec` → `records/features/`; `record-decision` → `records/decisions/`.
- `.ai/templates/{decision-record,feature-record}-template.md` — B1 fields (`rejected` status, `owners`, `decision_type`, `approval_date`) + `decision_refs` + `decision_approval_summary` (single-read approval-state).
- **5 skills synced** (engine treats non-nav skills as user-extension → manual copy after diff-confirm additive-only): `spec`, `task`, `testcase` (Phase-1a legacy notes), `review-code` (label align), `magento-module-analysis` (+19-line *Module Documentation & Structure* check: README/CHANGELOG for Secomm modules, module separation, dual-theme Luma+Hyva via `hyva_` handle, `getTemplate()` anti-pattern).

### Notes
- Applied via `bash /var/www/html/secomm-production-ai-toolkit/bin/project-ai-upgrade --project /var/www/html/slaunchpad --apply --force` (idempotent; 5 ADD, 4 UPDATE `--force`, 6 migrations stamped, 20 preserved). Semantic P1B/P1E/P1F already satisfied in AGENTS §8.5/§8.6/§9 — markers stamped, **no AGENTS.md rewrite** (shared-owned, untouched).
- Pre-sync backup: `.ai/toolkit/_pre-sync-backup-2026-07-23/` (4 contract files + 5 skill dirs). `.ai/` + `.claude/` still untracked in git — TL to review + commit.
- ADR: DEC-016 (scope + force-sync rationale). Lesson: `project-context/memory/CONTINUOUS_LEARNING.md`.
- Validates clean: `bash .ai/bin/project-ai-validate --project /var/www/html/slaunchpad` → VALID.

## [1.3.1] — 2026-07-23

### Changed — `.gitignore` hygiene (dedup + AI-toolkit ignores)
- Root [.gitignore](.gitignore): removed 3 duplicates (`/.idea`, `.DS_Store`, Maven `dist/` — kept the unanchored copies at lines that also catch nested paths); added `# AI Toolkit (Claude Code)` section ignoring `.claude/settings.local.json` + `.claude/*.local.json` (local/personal settings only — skills + shared `settings.json` stay committed).
- [.ai/.gitignore](.ai/.gitignore): added `/toolkit/_pre-sync-backup-*/` (transient rollback backups) + committed `.gitkeep` skeleton in `.ai/runtime/{session,workflow,evidence}/` (via `/runtime/**` + `!/runtime/**/.gitkeep` negation) so a fresh clone has the runtime dirs without depending on the agent to mkdir-on-write. `.gitkeep` is normally addable (no `-f`).
- Removed transient `.ai/toolkit/_pre-sync-backup-2026-07-23/` (referenced in 1.3.0; sync verified IN SYNC — recoverable via idempotent engine re-run).

### Notes
- Respects P1D design: AI `.ai/`-scoped ignores stay in `.ai/.gitignore` (validator `--check-runtime-separation` depends on the `/runtime/` carve-out there); only non-`.ai` AI artifacts go to root. Minor; no bin/security.
- **Runtime skeleton + validator fix backported to toolkit** (toolkit CHANGELOG Entry (e), 2026-07-23): `bin/project-ai-upgrade` `run_P1D()` now emits the negation pattern + `.gitkeep`; `bin/project-ai-validate` checks a runtime *content* path (`.ai/runtime/session/NEXT_ACTION.md`) instead of the dir — fixing the false "not ignored" WARN that a tracked `.gitkeep` inside an ignored dir caused (git won't ignore a path with tracked descendants). slaunchpad's `.ai/bin/project-ai-validate` re-synced to the fixed version.
- `project-ai-validate` → now **0 FAIL, 0 WARN**.
