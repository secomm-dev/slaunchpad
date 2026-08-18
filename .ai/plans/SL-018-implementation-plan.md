# Implementation Plan: SL-018 — Spec-First Governance (toolkit + project)

| Field | Value |
|---|---|
| Specification | Embedded Mini-Spec — SL-018 |
| Author | AI draft + user-implemented toolkit side |
| Reviewer (TL) | pending (rule wording) |
| Workflow Mode | A |
| Date | 2026-08-18 |

> **Status:** Dev complete — retro plan (work đã thực thi; plan ghi lại cho traceability). Mini-Spec + AC xem [ticket SL-018](../tickets/SL-018-spec-first-governance.md).

## 1. Approach

Enforce `NO SPEC → NO IMPLEMENTATION` tại 4 layer (rule file, gates/state model, entry-point functions/skills/agents, machine validator `--check-specs`), toolkit-first rồi sync project. Hai mức specification: Mini-Spec embedded (5 sections chuẩn) cho task nhỏ; Full Spec canonical cho feature (đứng trước ticket decomposition). Legacy: enforce-on-activate + backfill truthful.

## 2. Phân công thực thi

| Việc | Bởi | Kết quả |
|---|---|---|
| Toolkit 47 files (validator SpecReadinessGuard + Q tests + gates/state/functions/templates/agents/skills/checklists/AGENTS.base/CHANGELOG-i) | **User** | uncommitted working tree |
| `shared-core/rules/spec-first.md` (file canonical — validator grep nó) | AI | viết mới + align semantics (invariant + `Constraints / Rules`) |
| Toolkit contract tests | AI verify | 50 PASS / 0 FAIL — ALL GREEN |
| Sync project (`project-ai-upgrade --apply --force`) | AI | ADD=1 UPDATE=18 |
| AGENTS.md §7.1/§8.2/§9 mirror AGENTS.base | AI | escape "or ticket with AC" removed |
| Backfill: 12 plans `\| Specification \|` + 4 FEAT spec frontmatter | AI | truthful (specs thật / legacy note) |
| Records: DEC-SL018-001 + SL-018 ticket + evidence + memory | AI | accepted 2026-08-18 |

## 3. Files affected

Toolkit: xem git status (47 files uncommitted). Project: `.ai/AGENTS.md`, `.ai/rules/spec-first.md` + 18 synced items, 12 plans backfill, FEAT-001/005/006/007 frontmatter, tickets/DEC/evidence/memory records.

## 4. Verification

- `bash bin/run-contract-tests` → **ALL GREEN (50 PASS)** — gồm Q-series (valid-mini/missing-spec/full-ref/plan-spec).
- `.ai/bin/project-ai-validate` (full + `--check-specs`) → **0 FAIL / 0 WARN — VALID**.
- Follow-up: TL review `spec-first.md` wording; human commit 2 repo.
