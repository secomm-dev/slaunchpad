---
id: TASK-T0DWZ5
type: task
title: Move product review reCAPTCHA before submit button
project_code: SLP
parent: null
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: completed
created: 2026-08-26
updated: 2026-08-26
external_refs:
  tickets: SLP-44
legacy_ids: []
ticket_ref:
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/Magento_Review
source_areas:
  - product-review-form
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][TASK-T0DWZ5] Move product review reCAPTCHA before submit button

<!-- External ticket: SLP-44. -->

## Summary

Điều chỉnh thứ tự hiển thị trong product review form của theme `Secomm/launchpad`: reCAPTCHA phải nằm sau các field review và ngay trước nút `Submit Review`.

## Mini Spec

### Goal

Đặt reCAPTCHA gần hành động submit để luồng nhập và gửi review rõ ràng hơn.

### Expected Behavior

Khi reCAPTCHA cho `product_review` được bật, phần tử reCAPTCHA hiển thị trong `review_form`, sau các field rating/nickname/summary/review và ngay trước nút `Submit Review`. Khi reCAPTCHA bị tắt, form giữ nguyên hành vi hiện tại và không tạo khoảng trống thừa.

### Constraints / Rules

- Chỉ override template trong theme `Secomm/launchpad`; không sửa `vendor/`.
- Giữ nguyên `ReCaptcha::RECAPTCHA_FORM_ID_PRODUCT_REVIEW`, token field, validation JS và GraphQL submit flow.
- Không thay đổi nội dung, validation hoặc styling ngoài việc đổi thứ tự DOM.
- Không thêm dependency mới.

### Out of Scope

- Thay đổi loại reCAPTCHA, site key/secret hoặc cấu hình Admin.
- Thay đổi server-side/GraphQL review validation.
- Thiết kế lại product review form.

### Acceptance Criteria

- AC-001: Template theme chỉ render `getInputHtml(ReCaptcha::RECAPTCHA_FORM_ID_PRODUCT_REVIEW)` đúng một lần.
- AC-002: reCAPTCHA nằm sau review fields và ngay trước nút `Submit Review` trong DOM.
- AC-003: Validation JS, legal notice và GraphQL `X-ReCaptcha` header vẫn giữ nguyên.
- AC-004: Không sửa file trong `vendor/`; theme build và kiểm tra syntax/template liên quan pass.

## Approach

Tạo theme override cho `Magento_Review::form.phtml` từ phiên bản Hyvä đang cài đặt, chỉ di chuyển lệnh render reCAPTCHA từ đầu form tới action area ngay trước submit button. Sau đó so sánh override với template gốc để xác nhận diff ngữ nghĩa chỉ là thao tác di chuyển, chạy syntax check, theme build và validator của project.

## Implementation Notes

- Tạo `app/design/frontend/Secomm/launchpad/Magento_Review/templates/form.phtml` làm theme override từ đúng template Hyvä đang cài.
- Di chuyển duy nhất lệnh render reCAPTCHA tới action area, ngay trước submit button.
- Giữ nguyên validation JS, legal notice, GraphQL submit flow và `X-ReCaptcha` header.

## Verification

- [x] AC-001–AC-004 verified — evidence: `.ai/evidence/TASK-T0DWZ5/`

## Related records

- External ticket: SLP-44
