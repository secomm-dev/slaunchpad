---
id: TASK-BE8X4X
type: task
title: Build Manual Catalog Providers and Cache Contract
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
source_areas: [app/code/Secomm/UiWidget/Model/DataProvider/, app/code/Secomm/UiWidget/Block/Adminhtml/Widget/]
changes_project_state: false
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-BE8X4X] Build Manual Catalog Providers and Cache Contract

## Summary

Tạo manual product/category chooser, provider batch-load và cache identities dùng chung cho B2.

## Mini Spec

### Goal
Resolve ordered manual catalog selections an toàn, store-aware và không N+1.

### Expected Behavior
Admin chọn/reorder product/category; storefront batch-load item hợp lệ theo store/status/visibility, giữ order và invalidate cache khi entity thay đổi.

### Constraints / Rules
Manual-only; explicit fields; no SELECT *, no loop repository load; cache key gồm mọi affecting parameter; no conditions builder.

### Out of Scope
Product rendering variants, search conditions, best-seller/new/sale auto rules.

### Acceptance Criteria
- AC-001: Product/category chooser persist ordered IDs.
- AC-002: Providers batch-load và preserve selection order.
- AC-003: Missing/disabled/not-visible entity bị bỏ an toàn theo documented policy.
- AC-004: Cache keys/identities store-aware và invalidate theo rendered entities.
- AC-005: Query-count/integration tests chứng minh không N+1.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 5.

## Implementation Notes

Not implemented.

## Verification

- [ ] AC-001..005 — evidence: `.ai/runtime/evidence/TASK-BE8X4X/`

## Related records

- Parent: FEAT-J06WXZ
- Decision: DEC-FEATJ06WXZ-001
