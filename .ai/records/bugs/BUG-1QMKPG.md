---
id: BUG-1QMKPG
type: bug
title: Fix mixed-language validation messages and missing social login translations
project_code: SLP
parent:
external_refs:
  ticket: SLP-114
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
affects_version: Magento 2.4.8-p5 + Hyva theme-module + Mageplaza_SocialLogin (source-committed)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n
source_areas:
  - theme-i18n
  - mageplaza-sociallogin
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][BUG-1QMKPG] Fix mixed-language validation messages and missing social login translations

<!-- External ticket: SLP-114. -->

## Summary

Form validation của Hyva hiển thị chuỗi lai tiếng Việt/tiếng Anh (vd "mật khẩu field is required.") và các form social login (popup authentication / email / create / forgot) thiếu một số chuỗi tiếng Việt. Root cause: 26 phrase validation của Hyva theme-module không được dịch ở bất kỳ đâu (Magento không ship language pack vi_VN), và `Mageplaza_SocialLogin/i18n/vi_VN.csv` thiếu 13 chuỗi dùng trong các template Hyva.

## Mini Spec

### Goal

(a) Thông báo validation hiển thị tiếng Việt hoàn chỉnh trên mọi form Hyva; (b) các chuỗi thiếu trong form social login (kể cả popup "Enter password" — form authentication khi social email trùng tài khoản hiện có) được dịch.

### Expected Behavior

- Submit form rỗng → "Trường Mật khẩu là bắt buộc." (không còn "mật khẩu field is required.")
- Các label/heading social login popup hiển thị tiếng Việt

### Constraints / Rules

- Không sửa source `app/code/Mageplaza/` (third-party) — theme dictionary **override** module dictionary
- BR-001: mirror mọi dòng vào `en_US.csv`
- Key CSV phải khớp nguyên văn phrase nguồn, gồm cả typo "creat" trong `Please complete your information below to creat an account.`
- Giữ nguyên placeholder `%0`/`%1`/`%2`/`%3` và dấu `"` trong phrase (escape `""` theo chuẩn CSV)

### Out of Scope

- Sửa typo "creat" trong source template của Mageplaza (third-party; key phải khớp để dịch được)
- Bản dịch cho các module khác ngoài validation Hyva + SocialLogin
- JS i18n mechanism của Hyva (chỉ dịch phrase, không đổi cơ chế)

### Acceptance Criteria

- AC-001: Inline JS `advanced-form-validation` trên các trang có form render phrase tiếng Việt (verify: `'Trường %1 là bắt buộc.'` xuất hiện thay vì `'%1 field is required.'`)
- AC-002: Dictionary frontend (area frontend, theme Secomm/launchpad, locale vi_VN) resolve đủ 26 phrase validation + 13 chuỗi SocialLogin
- AC-003: Trang login hiển thị label tiếng Việt (Mật khẩu / Đăng nhập / Địa chỉ email)
- AC-004: `en_US.csv` có đủ các dòng mirror (BR-001)
- AC-005: Logic đăng nhập social không đổi (chỉ i18n display)

## Steps to Reproduce

1. Vào trang có form Hyva (contact, login, review…), submit field bắt buộc để trống → thông báo "…field is required" lai ngôn ngữ
2. Mở popup social login (flow require-enter-password với email trùng tài khoản) → một số label English (First Name, Email Address…)

## Expected Behavior

Thông báo + label tiếng Việt hoàn chỉnh.

## Actual Behavior

Chuỗi lai "mật khẩu field is required." + 13 chuỗi social login English.

## Root Cause

1. [advanced-form-validation.phtml](vendor/hyva-themes/magento2-theme-module/src/view/frontend/templates/page/js/advanced-form-validation.phtml) chứa 26 phrase `__()` render server-side vào inline JS — không có trong theme CSV; Magento core không có language pack vi_VN (vendor chỉ có de/en/es/fr/nl/pt/zh) → phrase giữ English, trong khi label field dịch qua `SocialLogin/i18n/vi_VN.csv` → chuỗi lai
2. `Mageplaza_SocialLogin/i18n/vi_VN.csv` thiếu 13 chuỗi dùng trong 6 template Hyva của module (Check out faster, Checkout as a new customer, Checkout using your account, Close panel, Creating an account has many benefits:, Date of Birth, Email Address, First Name, Last Name, Please complete your information below to creat an account., See order and shipping status, The information below is required for social login, Track order history)

## Fix

Thêm 39 dòng vào theme dictionary (theme row override module row cho cùng key):

- [i18n/vi_VN.csv](app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv): +39 dòng (26 phrase validation Hyva — **global cho mọi form Hyva** — + 13 chuỗi SocialLogin)
- [i18n/en_US.csv](app/design/frontend/Secomm/launchpad/i18n/en_US.csv): +39 dòng mirror identity (BR-001)
- `bin/magento cache:flush`

Lưu ý thuật ngữ: validation dùng "Trường %1 là bắt buộc." — %1 nhận label đã dịch ("Mật khẩu") → câu hoàn chỉnh tự nhiên.

## Verification

- Dictionary framework-level: ✅ 39/39 phrase resolve (script boot thật, theme + locale vi_VN; 790 entries nạp, CSV parse sạch)
- Live page: ✅ contact page (sau flush) inline JS: `hyva.str('Trường %1 là bắt buộc.', …)` + `'Trường này là bắt buộc.'` (escapeJs-encoded `ư…`) — thay cho English
- Live page: ✅ login page: Mật khẩu x6, Đăng nhập x19, Địa chỉ email x2
- Regression: ✅ chỉ dictionary — không đụng logic auth; en_US = identity mapping
- Còn lại cho QC manual: trigger validation thật trên popup social login (flow require-enter-password) + 1-2 form khác (OSC checkout) vì phrase validation là global

> Raw debug output → `.ai/runtime/evidence/BUG-1QMKPG/` (live-page-extract.txt)

## Notes cho TL review

- 26 phrase validation là **global** — sau deploy, mọi form (checkout OSC, review, contact…) đổi sang thông báo tiếng Việt mới. Term "Trường %1 là bắt buộc." — TL chốt thuật ngữ.
- `Close panel` dịch "Đóng" (a11y label, ngắn — nhất quán với "Close"→"Đóng" có sẵn).
- Key có typo "creat" giữ nguyên theo source Mageplaza — nếu sau này upgrade module sửa typo thì key này phải đổi theo.
