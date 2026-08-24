---
id: TASK-P0BP58
type: task
title: Build Dynamic Widget Form and Versioned Parameter Codec
project_code: SLP
parent: {type: feature, id: FEAT-J06WXZ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec; canonical parent ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: high
status: proposed
created: 2026-08-24
updated: 2026-08-24
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-FEATJ06WXZ-001]
decision_assessment: none-material
components: []
source_areas: [app/code/Secomm/UiWidget/view/adminhtml/, app/code/Secomm/UiWidget/Model/Parameter/]
changes_project_state: false
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-P0BP58] Build Dynamic Widget Form and Versioned Parameter Codec

## Summary

Xây form options theo component schema và codec an toàn cho scalar/repeated data trên Magento CMS editor surfaces.

## Mini Spec

### Goal
Cho admin chọn component và cấu hình đúng fields động; dữ liệu round-trip có version/limits và được server validate.

### Expected Behavior
Component selection render schema fields; save/reopen/edit giữ nguyên dữ liệu/thứ tự trên CMS Page, CMS Block và PageBuilder/TinyMCE; malformed/oversized payload fail closed.

### Constraints / Rules
Server definition là source of truth; no PHP object serialization, no DB/new library, Admin ACL/form key, không log payload đầy đủ.

### Out of Scope
Catalog chooser implementation, component visual batches và arbitrary raw Tailwind/template fields.

### Acceptance Criteria
- AC-001: Dynamic fields thay đổi đúng theo selected component schema.
- AC-002: Codec deterministic, versioned và enforce byte/depth/item limits.
- AC-003: Media và repeater add/remove/reorder persist đúng.
- AC-004: Three editor surfaces round-trip không mất/corrupt data.
- AC-005: Invalid version/payload/field types bị reject/fail closed với tests.
- AC-006: Chọn và document Admin renderer mechanism cuối cùng; không thay approved behaviour.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 2.

## Implementation Notes

Not implemented; proof outcome là gate cho task batch.

## Verification

- [ ] AC-001..006 — evidence: `.ai/runtime/evidence/TASK-P0BP58/`

## Related records

- Parent: FEAT-J06WXZ
- Spec: SPEC-FEAT-J06WXZ
