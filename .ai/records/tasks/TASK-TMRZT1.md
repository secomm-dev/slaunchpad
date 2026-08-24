---
id: TASK-TMRZT1
type: task
title: Deliver Batch Two Context Backed Components
project_code: SLP
parent: {type: feature, id: FEAT-J06WXZ}
mode: A
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
decision_assessment: none-material
components: []
source_areas: [app/code/Secomm/UiWidget/view/frontend/templates/components/, app/code/Secomm/UiWidget/Model/DataProvider/]
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-TMRZT1] Deliver Batch Two Context Backed Components

## Summary

Implement các B2 component vượt qua dependency/context/cache gate; defer minh bạch component coupling quá cao.

## Mini Spec

### Goal
Cung cấp catalog/context-backed widgets phù hợp CMS trên providers/contracts đã kiểm chứng.

### Expected Behavior
Selected B2 components render đúng manual entities/context, cache/security/privacy/a11y; component không đạt gate không xuất hiện trong registry.

### Constraints / Rules
Chỉ B2 matrix, manual selection, no checkout/order changes, no dependency/API key addition tự động, fail closed khi prerequisite thiếu.

### Out of Scope
Conditions builder, excluded system components, product review/accorditabs nếu context isolation không đạt, checkout success component.

### Acceptance Criteria
- AC-001: B2 row có kết quả implement hoặc documented defer/gate evidence.
- AC-002: Catalog components dùng shared providers/cache identities, không duplicate load logic.
- AC-003: Map/newsletter prerequisites được validate trước enable; privacy/ReCaptcha/CSP được review nếu implement.
- AC-004: Product components không phụ thuộc implicit current product khi manual product đã chọn.
- AC-005: Multi-store/cache/security/a11y tests pass cho component được enable.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 6.

## Implementation Notes

Not implemented.

## Verification

- [ ] AC-001..005 — evidence: `.ai/runtime/evidence/TASK-TMRZT1/`

## Related records

- Parent: FEAT-J06WXZ
- Matrix: B2 table
