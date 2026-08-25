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
status: in_progress
created: 2026-08-24
updated: 2026-08-25
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-FEATJ06WXZ-001, DEC-FEATJ06WXZ-002]
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

Implementation started on 2026-08-25 after TL approval of TASK-JN2SH6.

Slice 1 approved on 2026-08-25: establish the trusted rich-text contract and deliver `generic_content_a` as its first B1 consumer. Rich HTML is limited to explicit `trusted-rich-text` fields, authored with Magento WYSIWYG and rendered through the CMS block filter.

## Verification

- [ ] AC-001..005 — evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/`
- [x] Slice 1 unit suite: 25 tests, 55 assertions on PHP 8.4 DDEV (`--no-extensions`).
- [x] Slice 1 static checks: PHP syntax, JavaScript syntax, XML syntax and `git diff --check` pass.
- [ ] Browser verification of Magento WYSIWYG lifecycle and `generic_content_a` storefront output remains before B1 task completion.
- [ ] Project spec validator is currently unavailable because `.ai/bin/project-ai-validate` has a pre-existing unmatched quote near line 831; no validator file was changed in this slice.
- [x] Independent sub-agent review findings addressed: overflow now clears the persisted payload and shows a blocking server-validation path; editor IDs are root-scoped and teardown removes the correct TinyMCE event registration.

## Related records

- Parent: FEAT-J06WXZ
- Matrix: B1 table
