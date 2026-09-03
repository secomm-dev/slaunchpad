---
id: FEAT-J06WXZ
type: feature
title: Secomm UI Widgets
project_code: SLP
parent: null
external_refs: {}
legacy_ids: []
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: medium
status: in_progress
created: 2026-08-24
updated: 2026-08-29
ticket_ref:
  - TASK-S6QPEY
  - TASK-P0BP58
  - TASK-JN2SH6
  - TASK-ZQ9ZE1
  - TASK-BE8X4X
  - TASK-TMRZT1
  - TASK-XY9RZF
decisions:
  - DEC-FEATJ06WXZ-001
  - DEC-FEATJ06WXZ-002
decision_assessment: material
decision_refs: [DEC-FEATJ06WXZ-001, DEC-FEATJ06WXZ-002]
decision_approval_summary:
  total: 2
  pending_approval: []
  approved: [DEC-FEATJ06WXZ-001, DEC-FEATJ06WXZ-002]
  rejected: []
  superseded: []
  last_synced: 2026-08-24
  verified_against_commit:
components: [CMP-SECOMM-UI]
source_areas:
  - app/code/Secomm/UiWidget/
  - app/design/frontend/Secomm/*/Secomm_UiWidget/
  - vendor/hyva-themes/hyva-ui/components/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-29
supersedes: []
---

# [SLP][FEAT-J06WXZ] Secomm UI Widgets

## Context

Merchant cần sử dụng các Hyvä UI content component và data-backed component trong CMS Page, CMS Block, PageBuilder/WYSIWYG và Magento widget instances mà không phải chỉnh sửa layout XML hoặc template. Admin chỉ nhìn thấy một widget type `Secomm UI`, sau đó chọn component và cấu hình nội dung động bằng các field phù hợp.

Capability này phải dùng chung cho toàn bộ product theme dựa trên Hyvä. Hyvä UI là upstream/reference để import có kiểm soát; storefront không render trực tiếp từ package `hyva-themes/hyva-ui`, nhằm cô lập production khỏi rename/removal/breaking change của upstream.

## Specification

Canonical Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md).

Specification ở trạng thái **VALID**, được phê duyệt ngày 2026-08-24. Phase 1 / Batch 1 đã hoàn thành; phần manual catalog providers và Batch 2 vẫn thuộc Full Spec nhưng được deferred cho đến khi Product/TL mở lại scope.

## Requirements

- AC-001: Magento Admin hiển thị đúng một widget type có label `Secomm UI`.
- AC-002: Sau khi chọn type, admin chọn được component từ allowlist các component thuộc nhóm content độc lập và data-backed đã được phê duyệt.
- AC-003: Form hiển thị field động theo component; dữ liệu được validate phía server trước khi render.
- AC-004: Product/category component sử dụng manual chooser; conditions builder ngoài scope.
- AC-005: Widget dùng được trong CMS Page, CMS Block, WYSIWYG/PageBuilder và `Content > Elements > Widgets` ở các surface Magento hỗ trợ.
- AC-006: Module cung cấp template mặc định và cho phép mọi product theme Hyvä override presentation bằng Magento theme inheritance.
- AC-007: Component ID và schema đã phát hành giữ backward compatibility với widget directive/content đang lưu.
- AC-008: Update `hyva-themes/hyva-ui` không tự thay đổi component runtime; component upstream mới không tự xuất hiện trong Admin.
- AC-009: Tailwind production build chứa đủ class của component được đăng ký mà không yêu cầu admin nhập raw Tailwind class.
- AC-010: Product/category loading tôn trọng store, status, visibility và thứ tự manual; không tạo N+1 query.
- AC-011: Nội dung, URL, attribute, media và style values được validate/escape theo context; arbitrary template path bị từ chối.
- AC-012: Module hỗ trợ Hyvä-only; Luma/non-Hyvä fallback ngoài scope.

## Approach & Decisions

- User-confirmed scope: dynamic content, manual product/category selection và Hyvä-only.
- Phase decision: chỉ deliver Batch 1 trong giai đoạn hiện tại; Batch 2/catalog providers được giữ làm proposed follow-up.
- Architecture decision đã được phê duyệt ngày 2026-08-24: [DEC-FEATJ06WXZ-001](../decisions/DEC-FEATJ06WXZ-001.md).
- Không cài `Hyva_Widgets` hoặc `Hyva_CmsTailwindJit` làm runtime dependency trong baseline proposal.
- Component eligibility matrix và schema chi tiết được hoàn thiện trước khi task decomposition.
- Research: [RESEARCH_NOTES](../../project-context/memory/RESEARCH_NOTES.md) — entry 2026-08-24.
- Component matrix: [FEAT-J06WXZ component eligibility](../../specs/FEAT-J06WXZ-component-eligibility-matrix.md).
- Solution Design: [FEAT-J06WXZ solution design](../../specs/FEAT-J06WXZ-solution-design.md).
- Implementation Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md) — approved by Tuấn Lê on 2026-08-24.

## Implementation Notes

Status: **Phase 1 / Batch 1 complete; feature remains in progress for deferred Batch 2**.

- `Secomm_UiWidget` đã cung cấp một widget type, explicit registry, dynamic Admin authoring, versioned payload validation và 21 component Batch 1.
- Module-owned templates giữ Hyvä UI 2.8.0 layout/visual behaviour và hỗ trợ product-theme presentation override qua Magento inheritance.
- Theme override contract đã được proof bằng fixture local; không ship override POC khi chưa có presentation requirement thực tế.
- `TASK-BE8X4X` và `TASK-TMRZT1` chưa implement theo phase decision, vì vậy AC catalog/manual provider vẫn pending.

## Test Summary

Batch 1 pass 86 unit tests / 504 assertions after removal of the non-shipped theme fixture, plus PHP/XML/JavaScript, DI, Tailwind production build, CMS/Admin and responsive browser QA. Tracked evidence nằm trong `.ai/evidence/TASK-*/`.

## Compatibility Conclusions

- **Themes:** Hyvä product themes — in scope; Luma/non-Hyvä — out of scope.
- **Modules affected:** `Secomm_UiWidget`; `Magento_Widget` và `Magento_Cms` là extension points, không modify core. `Magento_Catalog` providers vẫn deferred cùng Batch 2.
- **API contracts:** không có public REST/GraphQL API mới trong baseline.
- **Upgrade notes:** Hyvä UI upstream được theo dõi bằng provenance metadata và manual review/import.

## References

- Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md)
- Decisions: [DEC-FEATJ06WXZ-001](../decisions/DEC-FEATJ06WXZ-001.md), [DEC-FEATJ06WXZ-002](../decisions/DEC-FEATJ06WXZ-002.md)
- Tasks: TASK-S6QPEY, TASK-P0BP58, TASK-JN2SH6, TASK-ZQ9ZE1, TASK-BE8X4X, TASK-TMRZT1, TASK-XY9RZF
- Hyvä UI local source: `vendor/hyva-themes/hyva-ui/`
- Magento Widget local source: `vendor/magento/module-widget/`
- Toolkit workflow: `.ai/workflow/workflow-profile.md`, `.ai/rules/spec-first.md`
