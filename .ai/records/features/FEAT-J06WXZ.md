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
spec_status: DRAFT
specification_ref: ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: medium
status: proposed
created: 2026-08-24
updated: 2026-08-24
ticket_ref:
decisions:
  - DEC-FEATJ06WXZ-001
decision_assessment: material
decision_refs: [DEC-FEATJ06WXZ-001]
decision_approval_summary:
  total: 1
  pending_approval: [DEC-FEATJ06WXZ-001]
  approved: []
  rejected: []
  superseded: []
  last_synced: 2026-08-24
  verified_against_commit:
components: []
source_areas:
  - app/code/Secomm/UiWidget/
  - app/design/frontend/Secomm/*/Secomm_UiWidget/
  - vendor/hyva-themes/hyva-ui/components/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-J06WXZ] Secomm UI Widgets

## Context

Merchant cần sử dụng các Hyvä UI content component và data-backed component trong CMS Page, CMS Block, PageBuilder/WYSIWYG và Magento widget instances mà không phải chỉnh sửa layout XML hoặc template. Admin chỉ nhìn thấy một widget type `Secomm UI`, sau đó chọn component và cấu hình nội dung động bằng các field phù hợp.

Capability này phải dùng chung cho toàn bộ product theme dựa trên Hyvä. Hyvä UI là upstream/reference để import có kiểm soát; storefront không render trực tiếp từ package `hyva-themes/hyva-ui`, nhằm cô lập production khỏi rename/removal/breaking change của upstream.

## Specification

Canonical Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md).

Specification hiện ở trạng thái **DRAFT**. Feature chưa executable cho đến khi Full Spec được review, các open decision được đóng, `spec_status` chuyển thành `VALID`, và implementation plan được TL phê duyệt.

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
- Architecture proposal cần SA/TL duyệt: [DEC-FEATJ06WXZ-001](../decisions/DEC-FEATJ06WXZ-001.md).
- Không cài `Hyva_Widgets` hoặc `Hyva_CmsTailwindJit` làm runtime dependency trong baseline proposal.
- Component eligibility matrix và schema chi tiết được hoàn thiện trước khi task decomposition.

## Implementation Notes

Status: **proposed — chưa implement**. Chưa tạo `Secomm_UiWidget`, chưa copy Hyvä UI template và chưa thay đổi theme/runtime dependency.

## Test Summary

Status: chưa thực hiện. Test strategy canonical nằm trong Full Spec §9.

## Compatibility Conclusions

- **Themes:** Hyvä product themes — in scope; Luma/non-Hyvä — out of scope.
- **Modules affected:** module mới dự kiến `Secomm_UiWidget`; `Magento_Widget`, `Magento_Cms` và catalog services là dependencies/extension points, không modify core.
- **API contracts:** không có public REST/GraphQL API mới trong baseline.
- **Upgrade notes:** Hyvä UI upstream được theo dõi bằng provenance metadata và manual review/import.

## References

- Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md)
- Architecture decision: [DEC-FEATJ06WXZ-001](../decisions/DEC-FEATJ06WXZ-001.md)
- Hyvä UI local source: `vendor/hyva-themes/hyva-ui/`
- Magento Widget local source: `vendor/magento/module-widget/`
- Toolkit workflow: `.ai/workflow/workflow-profile.md`, `.ai/rules/spec-first.md`

