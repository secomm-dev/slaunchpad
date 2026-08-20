# TASK-E0SK0H — Spec Naming propagation: SPEC-<OWNER-ID>-<slug>.md canonical (toolkit → project)

**Legacy ID:** SL-019 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (toolkit governance — naming only, KHÔNG đổi folder/workflow/lifecycle/enforcement)
**Priority:** High
**Estimate:** ~4–6h
**Mode:** A (governance change ảnh hưởng artifact generation mọi project)
**Placement:** `secomm-production-ai-toolkit/` (canonical) → upgrade → mọi Project Tool
**Risk tier:** Tier 2 (governance)
**Author:** AI draft · **Date:** 2026-08-18 · **Status:** Dev complete *(toolkit-first applied; contract tests R-series; evidence: [TASK-E0SK0H-evidence](../runtime/evidence/TASK-E0SK0H/TASK-E0SK0H-evidence.md))*

## Mini Spec (embedded)

### Goal
Convention naming Spec đã được duyệt (proposal 2026-08-18) trở thành **canonical shared rule** trong toolkit và tự động propagate tới Project Tool qua generation + upgrade.

### Expected Behavior
- Mọi nơi sinh Full Spec (spec skill, create-feature-spec, template) resolve owner TRƯỚC → filename `SPEC-<FEATURE-ID>-<slug>.md` / `SPEC-<TICKET-ID>-<slug>.md`, Specification ID `SPEC-<OWNER-ID>`; không sinh generic (SPEC-{NNN}/SPEC.md…) khi có owner.
- Mini-Spec: embedded, Specification ID = Ticket ID, không file riêng (`MINI-{NNN}` deprecated khỏi template).
- Fresh project nhận rule qua generation; existing project nhận qua `project-ai-upgrade` (spec-first.md đã trong manifest).
- Validator `--check-specs`: file `SPEC-<OWNER>-…` phải có header `Specification ID:` khớp — mismatch → FAIL; legacy slug-only grandfathered.

### Constraints / Rules
- KHÔNG đổi: folder structure, workflow states, ticket/plan lifecycle, Spec-First enforcement behavior, artifact ownership, project layout.
- Một canonical rule (spec-first.md §Spec Naming) — các nơi khác chỉ pointer, không duplicate.
- KHÔNG bulk rename historical specs (rename-on-touch qua safe workflow).

### Out of Scope
Migration/rename 12 spec slug-only hiện có · validator rewrite lớn · workflow restructuring.

### Acceptance Criteria
- [x] **AC-1:** Canonical rule nằm trong `shared-core/rules/spec-first.md` §Spec Naming (upgrade-copied; generator references nó).
- [x] **AC-2:** Templates: feature-spec-template bỏ `SPEC-{NNN}` → `Specification ID SPEC-{OWNER-ID}` + Feature ID/Level rows; mini-spec-template bỏ `MINI-{NNN}` row → Specification ID = ticket.
- [x] **AC-3:** Producers: spec SKILL + create-feature-spec naming steps (owner-first; cấm generic).
- [x] **AC-4:** Generator pointers: output-file-rules (không duplicate) + AGENTS.base một câu (spec-first pointer).
- [x] **AC-5:** Validator naming consistency check (focused, grandfathered legacy).
- [x] **AC-6:** Contract tests R1–R4 (rule present; templates placeholder gone; valid names pass + mismatch blocked với ví dụ chuẩn `SPEC-FEAT-AE761Z-ghtk-shipping-carrier.md` + `SPEC-BUG-042-fix-cod-calculation.md`; fresh project inheritance qua upgrade) — ALL GREEN cùng 59 tests cũ.
- [x] **AC-7:** Project sync qua `project-ai-upgrade --apply` + project validate VALID + DEC-TASKE0SK0H-001 + CHANGELOG (j).

## Related
- Plan: n/a (task nhỏ — Mini-Spec này là spec; các bước thực thi trong evidence) · Decision: [DEC-TASKE0SK0H-001](../records/decisions/DEC-TASKE0SK0H-001.md) (accepted)
- Builds on: DEC-TASKZ132WA-001 (spec-first; spec-first.md là canonical home) · naming proposal duyệt 2026-08-18
