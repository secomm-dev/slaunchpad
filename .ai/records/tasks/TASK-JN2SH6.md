---
id: TASK-JN2SH6
type: task
title: Deliver Banner A Vertical Slice
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
source_areas: [app/code/Secomm/UiWidget/view/frontend/templates/components/banner/]
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-JN2SH6] Deliver Banner A Vertical Slice

## Summary

Proof toàn bộ framework bằng component `banner_a` từ Admin tới storefront và theme override.

## Mini Spec

### Goal
Deliver một component production-ready chứng minh registry, dynamic fields, media, validation, Tailwind và override contract.

### Expected Behavior
Admin cấu hình title/subtitle/images/CTA/alignment/appearance; storefront render responsive Banner A không có demo data và theme có thể override presentation.

### Constraints / Rules
Port từ Hyvä UI 2.8.0 có provenance; escape theo context; enum→static class; unique instance; no vendor modification.

### Out of Scope
Component khác, product/category provider và automatic upstream sync.

### Acceptance Criteria
- AC-001: `banner_a` xuất hiện trong component select và fields đúng schema.
- AC-002: Mobile/desktop media, CTA và variants persist/render đúng.
- AC-003: Invalid URL/color/enum không tạo unsafe output.
- AC-004: Tailwind production build và responsive/a11y checks pass.
- AC-005: Module default và một Hyvä theme override đều render cùng schema.
- AC-006: Hai Banner A trên cùng page không xung đột.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 3.

## Implementation Notes

Not implemented. Đây là gate trước Batch 1.

## Verification

- [ ] AC-001..006 — evidence: `.ai/runtime/evidence/TASK-JN2SH6/`

## Related records

- Parent: FEAT-J06WXZ
- Matrix: `banner_a` B1
