# SL-018 — Spec-First governance: NO SPEC → NO IMPLEMENTATION (toolkit + project enforce)

**Type:** Task (toolkit governance — process change, không phải code Magento)
**Priority:** High
**Estimate:** ~10–14h
**Mode:** A (workflow/governance change ảnh hưởng mọi coding task — Tier 2 process)
**Placement:** `secomm-production-ai-toolkit/` (canonical) → sync `slaunchpad/.ai/`
**Risk tier:** Tier 2 (governance)
**Author:** AI draft · **Date:** 2026-08-17 · **Status:** Dev complete *(2026-08-18: toolkit side user-implemented 47 files + AI completion — rule file, sync, backfill; 50 contract tests ALL GREEN; project validate 0 FAIL/0 WARN — chờ TL review rule wording; evidence: [SL-018-evidence](../runtime/evidence/SL-018/SL-018-evidence.md))*

## Mini Spec (embedded — dogfood rule mới)

### Goal
Bịt mọi lối "Ticket → Plan → Implement" không có specification; mọi code change (kể cả bug fix nhỏ, hotfix, direct command, agent delegation) phải bắt đầu từ behavioral specification hợp lệ (Full Spec reference hoặc embedded Mini-Spec).

### Expected Behavior
- Sau deploy rule: mọi ticket ở trạng thái executable có `spec_status = VALID` (Full reference hoặc Mini-Spec đủ sections: Goal/Expected Behavior/Rules/OoS/AC).
- Plan mới luôn có dòng `Specification:`; implementation entry point (implement-task/fix-bug/refactor-code/developer agent) từ chối task không có valid spec với error rõ ràng.
- Validator `--check-specs` báo WARN (soft) / FAIL (`SPEC_GATE_HARD=1`) cho ticket executable thiếu spec hoặc Mini-Spec thiếu section critical; plan thiếu spec reference tương tự.
- Legacy ticket khi được activate → check → thiếu spec → stage tạo spec, chưa implement.

### Constraints / Rules
- Không weaken cho speed/quick fix/bug fix/direct command/agent delegation (yêu cầu gốc).
- Không over-document: small task = Mini-Spec embedded (không file riêng); feature = MỘT canonical Full Spec shared giữa tickets.
- Spec > Plan > implementation assumptions khi conflict.
- Toolkit-first rồi sync project (workflow đã thiết lập).
- Mode D: inline short-spec (expected vs actual + AC) trước code; retro Mini-Spec ≤24h.

### Out of Scope
- Migration/backfill toàn bộ legacy tickets (chỉ enforce khi activate).
- Hard-flip validator mặc định (follow-up sau backfill — như Phase 3 DEC_WORK_ITEMS).
- Navigator/HUMAN_RESPONSE interpreter changes (ngoài references).

### Acceptance Criteria
- [x] **AC-1 (Canonical rule):** `shared-core/rules/spec-first.md` tồn tại (invariant + MINI/FULL + classification triggers + executable-task definition + 4 enforcement layers); planning-first.md + ai-operating-principles.md bước 3 không còn escape "or ticket with AC".
- [x] **AC-2 (Gates/state):** Gate 1 (DoR) Mode C/D rows cập nhật (Mini-Spec required / retro ≤24h); Gate 3 thêm spec requirement; WORKFLOW_STATE_MODEL entry criteria có `spec_status = VALID` ở Task Created/Planning/Implementing; không transition NEW→IN_PROGRESS khi spec invalid.
- [x] **AC-3 (Entry points):** implement-task, analyze-ticket, fix-bug, refactor-code, developer agent, task/spec skills, pre-task checklist — đều check valid spec trước; error message chuẩn "IMPLEMENTATION BLOCKED — No valid specification found…".
- [x] **AC-4 (Templates):** ticket-template Spec field REQUIRED + skeleton `## Mini Spec`; mini-spec-template có `Goal / Expected Behavior / Rules & Constraints / Out of Scope / Acceptance Criteria`.
- [x] **AC-5 (Validator + tests):** `--check-specs` implement; contract fixtures S1 (ticket+mini → pass), S2 (ticket only → hard fail/soft warn), S3 (mini thiếu Expected Behavior → fail), S4 (feature tickets không reference shared spec → fail), S5 (direct implement no spec → blocked by rule docs), S6 (plan không có `Specification:` → warn). Project validate clean hoặc chỉ WARN legacy đã note.
- [x] **AC-6 (Workflow guides):** mode-b (mini-spec embedded allowed), mode-c (embedded Mini-Spec required), mode-d (inline short-spec + retro), decision-tree (thêm node classification → MINI/FULL).
- [x] **AC-7 (Verification loop):** post-task checklist + testcase skill yêu cầu ghi spec implemented + AC passed + invariants verified.
- [x] **AC-8 (Project sync + docs):** AGENTS.base §7.1/§8.2 + project AGENTS.md + rules/functions/templates/checklists/validator synced; CHANGELOG entry (i); DEC-SL018-001 accepted trước apply; evidence file.

## Related

- Plan: [SL-018 plan](../plans/SL-018-implementation-plan.md) · Decision: [DEC-SL018-001](../records/decisions/DEC-SL018-001.md) (proposed)
- Hardening kế thừa: [DEC-SL018-002](../records/decisions/DEC-SL018-002.md) (accepted 2026-08-19 — ticket activation contract: Mini-Spec + plan artifact bắt buộc ở tầng ticket; trigger audit SL-020)
- Audit source: prompt SA/TL 2026-08-17 (§17 report trong plan Part 1)
