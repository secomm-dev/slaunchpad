---
id: TASK-S6QPEY
type: task
title: Build Secomm UI Widget Foundation and Component Contracts
project_code: SLP
parent: {type: feature, id: FEAT-J06WXZ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec; canonical parent ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: medium
status: in_progress
created: 2026-08-24
updated: 2026-08-24
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-FEATJ06WXZ-001]
decision_assessment: none-material
components: []
source_areas: [app/code/Secomm/UiWidget/]
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-S6QPEY] Build Secomm UI Widget Foundation and Component Contracts

## Summary

Tạo foundation tối thiểu cho module và các contract đã được phê duyệt; chưa port component batch.

## Mini Spec

### Goal
Tạo module `Secomm_UiWidget`, một widget type `Secomm UI`, registry/schema/provenance và safe template resolution.

### Expected Behavior
Magento nhận widget type; registry chỉ resolve definition hợp lệ; unknown/duplicate component ID và arbitrary template path bị từ chối an toàn.

### Constraints / Rules
Không sửa vendor, không DB/new dependency, PHP 8.2+, strict types, README/CHANGELOG, vi/en translations, derive từ SPEC-FEAT-J06WXZ và DEC-FEATJ06WXZ-001.

### Out of Scope
Dynamic repeater UI, Banner A presentation, catalog provider và component batches.

### Acceptance Criteria
- AC-001: Module enable/setup/DI/XML validation pass.
- AC-002: Admin widget types có đúng `Secomm UI` declaration.
- AC-003: Registry resolve definition/provenance/schema deterministically và reject duplicate/unknown ID.
- AC-004: Template resolver chỉ dùng registered alias, không nhận path từ CMS.
- AC-005: Unit tests cover registry/resolver failure paths.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), steps 1–2.

## Implementation Notes

Implementation completed on 2026-08-24; awaiting TL code approval.

- Added the `Secomm_UiWidget` module and enabled it in `app/etc/config.php`.
- Declared one native Magento widget type labelled `Secomm UI` with `component` and hidden `schema_version` parameters.
- Added definition/registry/template-resolver contracts and DI wiring with an intentionally empty registry until the Banner A task.
- Added an Admin source model, bilingual translations, README/CHANGELOG and focused unit tests.
- No Hyvä UI template was copied and no dynamic Admin form or catalog provider was implemented in this task.
- Runtime validation passed on DDEV PHP 8.4. Existing PHP 8.4 deprecation warnings were emitted by unrelated legacy modules; none originated from `Secomm_UiWidget`.

## Verification

- [x] AC-001 — module enabled; `setup:upgrade` and DI compile passed.
- [x] AC-002 — Magento runtime reports `Secomm UI`, expected block type and parameters.
- [x] AC-003 — registry/definition unit tests pass.
- [x] AC-004 — template resolver rejects unregistered path; unit test passes.
- [x] AC-005 — 8 tests, 14 assertions pass; PHPCS Magento2 clean.
- Evidence: `.ai/runtime/evidence/TASK-S6QPEY/validation.md`

## Related records

- Parent: FEAT-J06WXZ
- Spec: SPEC-FEAT-J06WXZ
- Decision: DEC-FEATJ06WXZ-001
