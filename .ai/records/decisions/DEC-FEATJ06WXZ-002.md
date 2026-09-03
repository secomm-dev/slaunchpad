---
id: DEC-FEATJ06WXZ-002
title: Trusted rich text uses native Magento CMS authoring and filtering
status: accepted
owners: [tl]
author: Tuấn Lê
decision_type: security
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit:
supersedes: []
superseded_by:
work_items: [FEAT-J06WXZ, TASK-ZQ9ZE1]
---

# Decision Record: Trusted rich text uses native Magento CMS authoring and filtering

## Context

Một số Secomm UI content component cần nội dung định dạng động tương tự HTML content trong Magento CMS. Plain textarea không đáp ứng authoring UX; tự xây sanitizer riêng sẽ tạo policy khác Magento CMS và tăng maintenance surface.

## Decision

Tuấn Lê phê duyệt ngày 2026-08-25:

1. Chỉ field khai báo explicit type `trusted-rich-text` được phép nhận rich HTML; title, label, URL, media và plain text vẫn escape theo context.
2. Admin render `trusted-rich-text` bằng native Magento WYSIWYG configuration, không thêm editor dependency.
3. Storefront xử lý nội dung qua `Magento\Cms\Model\Template\FilterProvider::getBlockFilter()` trước khi render, tương đương trust model của Magento CMS block và hỗ trợ CMS directives.
4. Không thêm custom HTML sanitizer trong baseline. Quyền chỉnh widget/CMS là security boundary; nội dung từ nguồn không tin cậy không được đưa vào field này.
5. Template phải đánh dấu output đã filter là intentional raw output; mọi field không phải `trusted-rich-text` tiếp tục escape bắt buộc.
6. Embed/video giữ component và validation contract riêng, không đi qua rich-text field.

## Consequences

- Authoring nhất quán với Magento CMS và không có dependency mới.
- Rich content có cùng khả năng và rủi ro với CMS content; ACL CMS/widget và review nội dung là bắt buộc.
- Component definition phải opt-in từng field, giúp reviewer nhận diện raw-output boundary.
- Nếu sau này cần ingest nội dung không tin cậy, phải có decision mới về sanitizer/allowlist trước khi implement.

## Related records

- Parent architecture: [DEC-FEATJ06WXZ-001](DEC-FEATJ06WXZ-001.md)
- Full Spec: [SPEC-FEAT-J06WXZ](../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md)
- Active task: [TASK-ZQ9ZE1](../tasks/TASK-ZQ9ZE1.md)
