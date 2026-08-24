---
id: TASK-ZQ9ZE1
type: task
title: Deliver Batch One Content Components
project_code: SLP
parent: {type: feature, id: FEAT-J06WXZ}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec; canonical parent ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: medium
status: proposed
created: 2026-08-24
updated: 2026-08-24
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-FEATJ06WXZ-001]
decision_assessment:
components: []
source_areas: [app/code/Secomm/UiWidget/view/frontend/templates/components/]
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-ZQ9ZE1] Deliver Batch One Content Components

## Summary

Port/adapt toàn bộ component B1 đã duyệt bằng contracts được proof bởi Banner A.

## Mini Spec

### Goal
Cung cấp các content/manual-collection widgets trong matrix B1 với dynamic fields và quality nhất quán.

### Expected Behavior
Mỗi B1 component đăng ký explicit, persist/render dữ liệu động, không demo fallback, responsive/a11y và hỗ trợ theme override.

### Constraints / Rules
Chỉ B1 matrix; implement reviewable slices; provenance/schema version; repeater limits; static Tailwind classes; vi/en strings.

### Out of Scope
B2 providers/components, excluded/deferred inventory và thay đổi foundation contract không qua review.

### Acceptance Criteria
- AC-001: Mọi B1 row được implement hoặc có TL-approved defer reason ghi trong matrix.
- AC-002: Schema/required/default/validation documented cho từng component.
- AC-003: Repeater components giữ order và pass payload limits/round-trip.
- AC-004: Multi-instance Alpine/Tailwind/a11y/security regression pass.
- AC-005: No vendor modification/demo URL/raw class input.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 4.

## Implementation Notes

Not implemented.

## Verification

- [ ] AC-001..005 — evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/`

## Related records

- Parent: FEAT-J06WXZ
- Matrix: B1 table
