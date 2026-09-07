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
status: done
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

Implementation completed and approved by TL on 2026-08-24.

- Selected native Magento helper-block renderer with server-embedded registry schemas and `x-magento-init`; no custom endpoint or dependency.
- Added canonical JSON/Base64URL format v1 with 16 KiB encoded, depth 6 and 50 collection-row limits.
- Added field normalization for scalar, select, yes/no, numeric, URL, media and nested collection schemas.
- Added native media chooser integration and collection add/remove/reorder UI.
- Added server validation before CMS directive generation, before widget-instance save and again at storefront render.
- Three CMS editor surfaces share Magento's `LoadOptions`/`BuildWidget` directive path. Full browser save/reopen proof remains paired with the first registered `banner_a` component in TASK-JN2SH6; no production component is intentionally exposed by this infrastructure task.
- TL code approval recorded on 2026-08-24.

## Verification

- [x] AC-001 — registry schemas drive the selected component fields; renderer runtime diagnostic passes.
- [x] AC-002 — deterministic format v1 and byte/depth/item limits covered by unit tests.
- [x] AC-003 — media chooser change event and ordered collection controls persist into one payload; codec order tests pass.
- [x] AC-004 — common Magento editor directive path verified structurally; browser save/reopen fixture is explicitly carried into TASK-JN2SH6 once `banner_a` exists.
- [x] AC-005 — invalid version, root type, required/type/URL and oversized payload paths fail closed with tests.
- [x] AC-006 — renderer mechanism and limits documented in solution design and module README.
- Evidence: `.ai/evidence/TASK-P0BP58/validation.md`

## Related records

- Parent: FEAT-J06WXZ
- Spec: SPEC-FEAT-J06WXZ
