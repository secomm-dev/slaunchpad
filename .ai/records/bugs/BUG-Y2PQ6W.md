---
id: BUG-Y2PQ6W
type: bug
title: Translate "New Account Without Password" email for vi_VN storefront
project_code: SLP
parent:
external_refs:
  ticket: SLP-116
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-08-26
updated: 2026-08-26
ticket_ref:
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n
source_areas:
  - theme-i18n
  - email-templates
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][BUG-Y2PQ6W] Translate "New Account Without Password" email for vi_VN storefront

<!-- External ticket: SLP-116. -->

## Summary

Email "New Account Without Password" (`customer_create_account_email_no_password_template`) gửi từ store view vi_VN hiển thị toàn bộ tiếng Anh. Fix: thêm 11 phrase của template (kèm footer) vào theme dictionary — email render dưới store emulation nên `{{trans}}` resolve qua theme CSV.

## Mini Spec

### Goal

Email gửi từ store vi_VN (store 1 `default`) hiển thị tiếng Việt; store en_US (store 2 `launchpad_en`) giữ tiếng Anh. Link đặt mật khẩu (có `rp_token`) hoạt động nguyên vẹn.

### Expected Behavior

- Subject: "Chào mừng bạn đến với <store name>"
- Body tiếng Việt; link createPassword với token đúng; header/footer template config giữ nguyên

### Constraints / Rules

- Không sửa `vendor/` hay `app/code/Mageplaza/`
- BR-001: mirror `en_US.csv`
- Không đổi logic gửi mail / transport (Mageplaza SMTP không đụng)

### Out of Scope

- 8 email customer còn lại (account_new, confirmation, confirmed, password_new, password_reset… + order emails) — **đề xuất follow-up ticket**, làm theo đúng pattern này
- Bản dịch các email adminhtml area

### Acceptance Criteria

- AC-001: Render template với design store 1 → subject + body tiếng Việt
- AC-002: Render với store 2 → tiếng Anh (không regression)
- AC-003: URL createPassword + token render đúng trong bản vi
- AC-004: `en_US.csv` mirror đủ 11 dòng (BR-001)

## Steps to Reproduce

1. Tạo customer không password (admin tạo customer, hoặc social login với email chưa có account)
2. Email nhận được toàn tiếng Anh khi gửi từ store vi_VN

## Expected Behavior

Email tiếng Việt.

## Actual Behavior

Email tiếng Anh.

## Root Cause

Magento 2.4.8 **không có cơ chế locale-file cho email template**: `RulePool::createEmailTemplateFileRule()` chỉ có pattern `<theme_dir>/<module_name>/email/<file>` và `<module_dir>/view/<area>/email/<file>` — **không có `<locale>`** (khác belief phổ biến từ tài liệu cũ về `email/<locale>/`). Cơ chế dịch email duy nhất của core là **`{{trans}}` directive** → `__($text, $params)` resolve qua dictionary của area frontend dưới store emulation (theme + locale của store gửi). Theme CSV của project chưa có phrase nào của email này.

**Lesson (quan trọng cho các ticket dịch email sau)**: đừng tạo `email/vi_VN/*.html` — dead code, không bao giờ được resolve. Đường đúng: thêm phrase vào `i18n/vi_VN.csv` của theme.

## Fix

Thêm 11 phrase vào theme dictionary:

- [i18n/vi_VN.csv](app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv) (+11): `Welcome to %store_name` (subject), `Welcome to %store_name.`, `%name,`, `To sign in to our site and set a password, click on the <a href="%create_password_url">link</a>:`, `Email:`, `When you sign in to your account, you will be able to:`, `Proceed through checkout faster`, `Check the status of orders`, `View past orders`, `Store alternative addresses (for shipping to multiple family members and friends)`, `Thank you, %store_name` (footer template)
- [i18n/en_US.csv](app/design/frontend/Secomm/launchpad/i18n/en_US.csv): +11 mirror identity
- `bin/magento cache:flush`

Đã thử và **loại bỏ** approach locale-file (`Magento_Customer/email/vi_VN/account_new_no_password.html`) — không được resolve trong 2.4.8 (xem Root Cause).

## Verification

- Render framework-level (flow thật `processTemplate()` với design store): ✅
  - Store 1 (vi_VN): SUBJECT "Chào mừng bạn đến với Main Website Store"; body tiếng Việt toàn bộ; password link `customer/account/createPassword/?token=…` render đúng
  - Store 2 (en_US): giữ English nguyên vẹn (không regression)
- Còn lại cho QC: gửi email thật qua SMTP (tạo customer test) — cơ chế render đã được verify bằng flow giống hệt send thật
- Lưu ý: greeting "Chào <name>," trong script verify hiển thị name rỗng do harness CLI dùng legacy customer model — flow thật truyền DataModel có name đầy đủ

> Raw debug output → `.ai/runtime/evidence/BUG-Y2PQ6W/` (email-render-both-stores.html)

## Notes cho TL review

- Nội dung email là client-facing — TL duyệt bản dịch (đã mirror cấu trúc core, không đổi tone)
- Header/footer email dùng default template của `Magento_Email` — footer đã dịch "Thank you, %store_name"; các email khác sau này làm theo pattern này cần kèm phrase footer riêng của chúng
- **Follow-up đề xuất**: ticket dịch 8 email customer còn lại + order emails (cùng cơ chế, ước ~3-5h cho toàn bộ)
