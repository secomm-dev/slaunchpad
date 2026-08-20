# CHANGELOG — Project AI Tool (Secomm Launchpad)

> Runtime log of changes to this project's `.ai/` AI-toolkit. Updated via the `update-project-ai-tool` function (governed by `.ai/rules/ai-tool-self-update.md` + `before-ai-tool-update` / `after-ai-tool-update` hooks). One entry per change; never delete.

## [2026-08-20 — later] — Legacy re-identification migration (DEC-027; supersedes DEC-026 điểm 2)

### Changed — 106 file content rewrite + 101 rename, một format ID duy nhất
- **Tickets** (22): `SL-001..SL-025` → `TASK-88NDV5`, `TASK-FMAN1B`, `TASK-FD6A9X`, `TASK-KCBDDT`, `TASK-YJENM2`, `TASK-BRKHN4`, `TASK-KV328X`, `TASK-4ZV5NG`, `TASK-SQY42T`, `TASK-8WSERX`, `TASK-2V0AEV`, `TASK-NDASAD`, `TASK-KM6YAT`, `TASK-86NX9T`, `TASK-Z132WA`, `TASK-E0SK0H`, `TASK-3R6X8E`, `TASK-33J3RP`, `TASK-5H8WKE`, `TASK-67GGPR`, `TASK-4HYX6Y`, `TASK-HPK1WZ`; mỗi ticket thêm dòng `**Legacy ID:** SL-0NN`.
- **Features** (8): `FEAT-001..008` → `FEAT-YVN39K`…`FEAT-JKZM68`; record upgrade new-model shape (`type`, `project_code: SLP`, `parent: null`, `legacy_ids`, H1 display `[SLP][FEAT-…] <title>`).
- **DEC** (22): đổi tên theo namespace `work_items[0]` mới — vd `DEC-010..015` → `DEC-FEATHEHJQ4-001..006`, `DEC-SL018-001/002` → `DEC-TASKZ132WA-001/002`, `DEC-FEAT008-001` → `DEC-FEATJKZM68-001`; thêm `legacy_ids`. DEC-016/DEC-026 exempt giữ nguyên; DEC-1..9 (ADR-era) giữ nguyên.
- **SPEC** (12): `SPEC-SL-0NN-<slug>` → `SPEC-TASK-…-<slug>`, `SPEC-FEAT-008-…` → `SPEC-FEAT-JKZM68-…` (filename + `Specification ID:` header đồng bộ).
- **PLAN** (15) + testcases (2) + evidence dirs (8 + inner files): rename theo ID mới; mọi tham chiếu chéo (work_items, decisions:, specification_ref, Plan: links, DECISIONS.md index, memory files, AGENTS.md, CHANGELOG) rewrite tương ứng.
- Toolkit `shared-core/rules/spec-first.md`: 4 citations thật cập nhật surgical (DEC-TASKZ132WA-001/002, DEC-TASKE0SK0H-001, TASK-3R6X8E) — hai phía md5-identical (không drift).

### Added
- `.ai/toolkit/legacy-id-map.yaml` — mapping machine-readable old→new (items + decisions + phantoms note). Resolver: exact new id → `legacy_ids` → map.
- `DEC-027` + DECISIONS.md index; DEC-026 điểm 2 superseded.

### Preserved (KHÔNG đổi)
- `addressdropdown-hyva.md` (slug-only legacy, superseded read-only) · phantoms `SL-004/005/006/026` (prose lịch sử) · app/code comments (ngoài scope `.ai`) · ADR block DEC-1..9.

### Notes
- Baseline commit ngay trước migration (user); script reviewed + dry-run (101 renames listed) rồi `--apply`. Validate sau migration + sau fixes: **0 FAIL / 0 WARN**. Rollback nếu cần: `git checkout` về baseline.

## [2026-08-20] — P2A Work-Item Identity Model (toolkit Entry (k); migration P2A_WORK_ITEM_IDENTITY; DEC-026)

### Added
- **`.ai/toolkit/project.yaml`** — canonical project identity SSOT: `name: Secomm Launchpad`, `code: SLP` (P2A bootstrap detect `SL` unambiguous từ 22 ticket filenames → TL workflow đổi `SL→SLP` kèm `code_history`; audit `--check-identity` clean; KHÔNG regenerate work-item IDs).
- **`.ai/bin/project-ai-idgen`** — collision-safe ID minter (`<TYPE>-<6 ký tự Crockford Base32>`, vd `TASK-4P8DX2`): 4-byte `/dev/urandom` → base32 uniform, existence-check re-mint, KHÔNG max+1 — an toàn multi-agent/multi-branch.
- **`.ai/rules/work-item-identity.md`** — canonical identity rule (project code §1, ID algorithm+regex §2, types+no-auto-Feature §3, parent metadata §4, display renderer §5, naming §6, spec mapping §7, DEC §8, external_refs/legacy_ids §9, migration policy §10, validation §11, training example §12).
- **`.ai/records/tasks/` + `.ai/records/spikes/`** — canonical homes cho TASK/SPIKE records mới.
- Templates mới: `task-record-template.md`, `spike-record-template.md`.

### Changed
- `.ai/bin/project-ai-validate` — thêm `--check-identity` (project code + PENDING flag, dual-format ids, duplicate canonical id, parent resolve, `project_code` match, display H1 == render, `legacy_ids` uniqueness); mọi ID regex chuyển dual format (legacy `SL-NNN`/`FEAT-NNN` + mới `TASK-4P8DX2`): work_items, DEC naming (suy prefix từ `work_items[0]` — chấp nhận `DEC-TASK4P8DX2-001`), SPEC owner (`SPEC-TASK-4P8DX2-slug.md`); records check covers tasks/spikes.
- 17 file toolkit-owned synced `--force` (rules/spec-first, no-duplicate-knowledge; functions/analyze-ticket, record-decision, create-feature-spec; templates ×10; registry) — identical-propagation từ toolkit (98/98 contract tests PASS).
- `.ai/AGENTS.md` §14 — bullet identity model (manual semantic follow-up, shared-owned).
- `toolkit-version.md` — stamp `P2A_WORK_ITEM_IDENTITY` + `phase2_capabilities` (registry sync).

### Preserved (KHÔNG đổi)
- Toàn bộ work items legacy được **re-identify sang ID mới trong cùng ngày** (xem entry DEC-027 bên dưới) — nội dung record không đổi, chỉ identity/filename/references; traceability qua `.ai/toolkit/legacy-id-map.yaml` + `legacy_ids` frontmatter. `applied_migrations: [P1A..P1F, P2A_WORK_ITEM_IDENTITY]`.

### Notes
- Applied via `bash secomm-production-ai-toolkit/bin/project-ai-upgrade --project /var/www/html/slaunchpad --apply --force` (idempotent; 4 ADD, 17 UPDATE --force, migration P2A bootstrap, 20 preserved). Validate sau retrofit: 0 FAIL / 0 WARN. Dry-run re-run: IN SYNC.
- Work item MỚI từ 2026-08-20: mint bằng `.ai/bin/project-ai-idgen`, record dưới `.ai/records/{tasks,bugs,spikes,features}/`, `parent` metadata nếu thuộc Feature, H1 display title `[SLP][…]`, spec `SPEC-<ITEM-ID>-<slug>.md`.

## [2026-08-19] — Spec-First ticket activation hardening (DEC-TASKZ132WA-002; toolkit-first backport)

### Changed
- `.ai/rules/spec-first.md` — thêm §"Ticket activation contract (DEC-TASKZ132WA-002)": ticket active cần embedded `## Mini Spec` (parent Full-Spec reference KHÔNG thay thế) + plan artifact (`## Approach` hoặc `Plan:` link resolve); cập nhật SpecificationGuard layer 4 + executable-task checklist.
- `.ai/bin/project-ai-validate` — SpecReadinessGuard ticket loop: FAIL khi active ticket thiếu Mini-Spec / thiếu plan artifact / Specification line vắng-hoặc-rỗng.
- Reconcile legacy: TASK-YJENM2/TASK-BRKHN4 stale "Ready" → "Dev complete" (shipped trong FEAT-AE761Z; surfacing khi hardening).
- Nguồn: toolkit canonical `secomm-production-ai-toolkit` (`shared-core/rules/spec-first.md` + `bin/project-ai-validate` + Test Q ticket-tier trong `bin/run-contract-tests` — 62/62 PASS); identical-propagation về project. Trigger audit: TASK-3R6X8E implement mà chưa có ticket-level spec/plan.

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
