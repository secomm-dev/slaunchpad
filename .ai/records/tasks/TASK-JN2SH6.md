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
status: done
created: 2026-08-24
updated: 2026-08-25
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

Implementation completed and approved by TL on 2026-08-25.

- Registered `banner_a` schema v1 with Hyvä UI 2.8.0 `banner/A-default` provenance.
- Added required title/mobile image/alt, optional desktop image and compound CTA, loading, alignment, safe tone/appearance enums, card and bounded gradient options.
- Ported a production template without upstream demo title/image or arbitrary CSS inputs; all CMS values are context escaped.
- Added responsive picture semantics, accessible heading/overlay link and unique per-instance DOM IDs.
- Proved `Secomm/launchpad_fashion` presentation override resolution with the same module-owned schema; the temporary fixture was removed before commit because no real theme-specific presentation is required yet.
- Registered module and fashion override sources for the shared Tailwind v4 build.
- Banner default/fashion sources pass an isolated Tailwind v4 production compile. The unrelated pre-existing `Snowdog_Menu` full-theme build failure is documented but explicitly outside this task scope and does not block Banner acceptance.
- TL code approval recorded on 2026-08-25.

## Verification

- [x] AC-001 — runtime Admin options contain `Banner A` and the expected dynamic schema fields.
- [x] AC-002 — responsive media, compound CTA and variants pass schema/render round-trip.
- [x] AC-003 — unsafe URL/enum/color input fails closed; output escaping verified at runtime.
- [x] AC-004 — Banner default/fashion Tailwind v4 production compile plus responsive/accessibility checks pass within task scope; unrelated Snowdog build failure is non-blocking.
- [x] AC-005 — module default and `Secomm/launchpad_fashion` override render the same payload/schema.
- [x] AC-006 — two identical directives render with two unique DOM IDs in CMS Page filter proof.
- Evidence: `.ai/evidence/TASK-JN2SH6/validation.md`

## Related records

- Parent: FEAT-J06WXZ
- Matrix: `banner_a` B1
