---
id: DEC-FEATJ06WXZ-001
title: Secomm UI Widgets architecture boundary, registry contract and upstream ownership
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-24
created: 2026-08-24
last_verified: 2026-08-24
verified_against_commit:
supersedes: []
superseded_by:
work_items: [FEAT-J06WXZ]
---

# Decision Record: Secomm UI Widgets architecture boundary, registry contract and upstream ownership

## Context

Hyvä UI 2.8.0 là source library, không phải Magento runtime module. Feature cần expose nhiều content/data-backed component qua một Magento widget type, hỗ trợ dynamic fields và dùng chung trên nhiều product theme Hyvä. CMS directives đã lưu trở thành persistent contract; việc scan/render trực tiếp từ vendor sẽ khiến Composer update có thể thay đổi storefront ngoài kiểm soát.

User đã xác nhận business/scope inputs: dynamic content, manual product/category selection, Hyvä-only và capability dùng như core trên toàn bộ product theme.

## Decision

Đã phê duyệt kiến trúc sau ngày 2026-08-24:

1. Tạo shared module `Secomm_UiWidget` sở hữu Magento widget integration, component registry, field schema, validation/normalization, data providers và default templates.
2. Chỉ expose một Magento widget type `Secomm UI`; component select lấy từ explicit registry/allowlist, không scan filesystem/vendor tại runtime.
3. Port từng Hyvä UI component được phê duyệt vào code do Secomm sở hữu. Hyvä UI lưu vai trò upstream/reference; Composer update không tự sync runtime code.
4. Stable component ID + schema version là persisted compatibility contract. Breaking change cần adapter/migration hoặc component version mới.
5. Theme Hyvä được override presentation qua Magento theme inheritance; registry/schema/data contracts không bị fork theo theme.
6. Product/category component dùng manual chooser và batch/store-aware data providers; không triển khai conditions builder.
7. Tailwind classes nằm trong code-owned templates và được build qua module/theme source registration; không cần CMS Tailwind JIT trong baseline.
8. Không thêm `Hyva_Widgets` làm runtime dependency. Source package chỉ được dùng như development reference nếu cần và phải qua dependency approval trước khi require.
9. Baseline không thêm database schema; repeated item persistence phải được proof và giữ backward compatibility trước khi implementation batch.

## Alternatives

- **Render trực tiếp từ `vendor/hyva-themes/hyva-ui`:** bị loại vì Hyvä UI không phải module, component có thể rename/remove/change incompatibly, và storefront sẽ phụ thuộc dev/reference package.
- **Scan toàn bộ Hyvä UI và tự populate select:** bị loại vì expose cả layout/system component không CMS-safe, phình CSS và làm Admin khó sử dụng.
- **Mỗi component là một Magento widget type:** không đáp ứng UX đã yêu cầu là một type `Secomm UI`; làm danh sách widget type quá lớn.
- **Cài và dùng nguyên `Hyva_Widgets`:** có thể tham khảo nhưng widget taxonomy/UX không khớp contract một type + component registry và tạo runtime dependency mới.
- **Cho theme sở hữu toàn bộ implementation:** bị loại vì schema/data contract sẽ drift giữa product themes và không còn core capability dùng chung.
- **CMS Tailwind JIT/raw utility input:** bị loại khỏi baseline vì merchant không nên cấu hình implementation classes và templates đã nằm trong code build.

## Consequences

- Positive: update Hyvä UI không tự phá runtime; product themes dùng chung behaviour contract; component onboarding có review gate rõ ràng.
- Positive: security boundary rõ — CMS parameters không được chọn template path/class tùy ý.
- Negative: component mới/fix upstream không tự cập nhật; maintainer phải review/diff/import thủ công.
- Negative: dynamic Admin form và repeated items cần framework nội bộ, làm vertical slice ban đầu lớn hơn widget tĩnh.
- Obligation: duy trì provenance metadata, component eligibility matrix, schema compatibility fixtures và two-theme regression tests.
- Obligation: quay lại SA/TL nếu repeated-item proof dẫn tới database schema hoặc dependency mới.

## Affected components

- New source area: `app/code/Secomm/UiWidget/`.
- Theme extension point: `app/design/frontend/Secomm/*/Secomm_UiWidget/`.
- Upstream reference: `vendor/hyva-themes/hyva-ui/components/` — read/copy reference only, never modified/runtime included.
- Magento extension points: `Magento_Widget`, `Magento_Cms`, `Magento_Catalog`.

## Related records

- Feature: [FEAT-J06WXZ](../features/FEAT-J06WXZ.md)
- Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md)
- DECISIONS.md index: `.ai/project-context/memory/DECISIONS.md`
