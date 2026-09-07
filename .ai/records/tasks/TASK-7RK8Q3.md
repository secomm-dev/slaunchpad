---
id: TASK-7RK8Q3
type: task
title: 'Integration tests — membership isolation (current vs legacy), profile levels, resolver paths + QC evidence'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-25
decisions: [DEC-FEATYA2C0W-001]
components:
  - CMP-VNADDR
  - CMP-ADDR
source_areas:
  - app/code/Secomm/VietNamAddress/Test/
  - app/code/Secomm/AddressDropdown/Test/
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-7RK8Q3] Integration tests — membership isolation (current vs legacy), profile levels, resolver paths + QC evidence

<!-- CANONICAL TASK RECORD — chạy sau tất cả task code. Data-level isolation test (provider runtime thuộc TASK-J49PRZ, FEAT-2PZQKJ). -->

## Summary

Integration tests trên DB thật (fixture controlled): membership isolation 2 chiều giữa vn_current/vn_legacy; profile levels đúng spec; resolver end-to-end với relation data thật; QC regression checklist storefront/admin.

## Mini Spec

### Goal
Chứng minh current-only nodes không lọt vào legacy profile và ngược lại — ở tầng data (membership + hierarchy query semantics sẽ dùng TASK-J49PRZ).

### Expected Behavior
1. Fixture: DB dev có cả 2 datasets + memberships + relations seeded (output các task trước).
2. Test isolation: query root cities theo membership vn_current trong 1 region → không node legacy; theo vn_legacy → không node current (data-level query mô phỏng provider semantics: claim region + subtree + inheritance).
3. Test profile: getSchema('vn_current') 2 levels / ('vn_legacy') 3 levels qua OM thật.
4. Test resolver end-to-end: 1 current region có 1 legacy relation → RESOLVED; Hanoi (nhiều legacy province gộp) → AMBIGUOUS; ward chưa có relation → NOT_FOUND.
5. QC checklist: storefront customer form VN, cart estimate, admin order form, 3 validator plugin — regression-zero (evidence log).

### Constraints / Rules
- Test không phụ thuộc thứ tự chạy khác; cleanup fixture sau run.
- Evidence lưu `.ai/runtime/evidence/TASK-7RK8Q3/`.

### Out of Scope
- GraphQL mới / renderer mới (FEAT-2PZQKJ Phase 2).

### Acceptance Criteria
- AC-001: Isolation tests pass 2 chiều.
- AC-002: Profile level tests pass.
- AC-003: Resolver end-to-end đủ 3 status trên data thật.
- AC-004: QC checklist regression-zero có evidence.

## Approach

Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md) — Step 5.

## Implementation Notes

Chưa triển khai (proposed — blocked-by R83FXW/ADT94K/X0XKH4/AP6YXP).

## Verification

- [ ] AC-001..004 — evidence: `.ai/runtime/evidence/TASK-7RK8Q3/`

## Related records

- Parent feature: FEAT-YA2C0W
