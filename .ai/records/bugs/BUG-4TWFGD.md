---
id: BUG-4TWFGD
type: bug
title: 'Translate thank-you page (checkout/onepage/success)'
project_code: SLP
parent:
external_refs:
  ticket: SLP-121
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: done          
created: 2026-08-26
updated: 2026-08-26
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x (parent theme Hyva/default) + Mageplaza_Osc
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n
source_areas:
  - theme-i18n
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][BUG-4TWFGD] Translate thank-you page (checkout/onepage/success)

<!-- External ticket: SLP-121. Trang Thank You sau đặt hàng đang hiện English trên store vi_VN. -->

## Summary
Trang `checkout/onepage/success` (handle `checkout_onepage_success` + handle Hyvä `hyva_checkout_onepage_success` của Mageplaza_Osc) hiển thị chuỗi English trên store mặc định vi_VN. Theme `Secomm/launchpad` không override template nào của trang này — nội dung render từ `Hyva/default` (`vendor/hyva-themes/magento2-default-theme/Magento_Checkout/`) + block thêm của Mageplaza_Osc. Fix: thêm 12 phrase vào theme dictionary (`vi_VN.csv` + mirror `en_US.csv`) — 7 phrase success page + 5 phrase survey do TL bổ sung trực tiếp.

## Mini Spec

### Goal
- Mọi chuỗi UI thuộc trang success hiển thị tiếng Việt trên store mặc định (vi_VN); store en (launchpad_en) không đổi.

### Constraints / Rules
- Dictionary-only — không sửa template/layout/code; không đụng flow checkout (AGENTS.md §12: checkout là vùng high-risk, change này không đổi logic)
- BR-001: mirror `en_US.csv`, 2 file cân bằng số dòng
- Thuật ngữ theo dictionary hiện có: "Mã đơn hàng" (Order ID), "Địa chỉ email" (Email Address)

### Out of Scope
- **OSC Survey block — phần string còn lại** (`Add your own answer`, `Add`, `Remove`, `Something went wrong. Please try again.`, `You need to choose at least one answer.`, `Thank you for your feedback!`) — block hiện không render (`osc/display_configuration/is_enabled_survey = 0` trong DB). **TL đã tự thêm 5 string survey** (`Question`, `Add an option...`, `Submit Answers`, `Submitting...`, `Thank you for completing our survey!` — 4 từ hyva template + 1 từ `Controller/Survey/Save.php` response) vào CSV ngày 2026-08-26 → 5 string này thuộc scope commit; phần còn lại optional follow-up nếu bật survey
- **Static CMS block** (`osc.static-block.success`) — nội dung admin-managed (data), không dịch qua CSV
- **Browser tab title "Success Page"** — `<head><title>` trong `checkout_onepage_success.xml` không có `translate="true"`, không resolve qua dictionary; dịch được thì cần theme layout override (mở rộng scope — TL quyết định nếu cần)
- `Continue Shopping` — đã có theme CSV (dòng 120)
- `Create an Account` (registration block) — đã resolve qua `app/code/Mageplaza/SocialLogin/i18n/vi_VN.csv` ("Tạo một tài khoản")

### Acceptance Criteria
- AC-001: H1 trang success (layout `translate="true"`) = "Cảm ơn bạn đã mua hàng!"
- AC-002: Cả 2 path hiển thị mã đơn hàng tiếng Việt — guest (`Your order # is: <span>%1</span>.`) và đã login (`Your order number is: %1.`); dòng email confirmation dịch
- AC-003: Guest registration block: dòng track-status + "Địa chỉ email:" dịch; nút Create an Account dịch (module CSV)
- AC-004: Nút print = "In đơn hàng"
- AC-005: `en_US.csv` mirror đủ 12 phrase (BR-001); 2 file cân bằng 274/274

## Root Cause
Magento core + Hyva/default không ship vi_VN; các phrase của `Magento_Checkout` success page chưa từng được thêm vào theme dictionary (theo chuỗi SLP-106/114/116/118 chỉ phủ product review, social login, email, login/customer pages).

## Fix
+12 phrase vào [vi_VN.csv](app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv) + mirror [en_US.csv](app/design/frontend/Secomm/launchpad/i18n/en_US.csv) (CRLF, append cuối file):

| Phrase (source) | vi_VN |
|---|---|
| `Thank you for your purchase!` | `Cảm ơn bạn đã mua hàng!` |
| `Your order number is: %1.` | `Mã đơn hàng của bạn là: %1.` |
| `Your order # is: <span>%1</span>.` | `Mã đơn hàng của bạn là: <span>%1</span>.` |
| `We'll email you an order confirmation with details and tracking info.` | `Chúng tôi sẽ gửi email xác nhận đơn hàng kèm thông tin chi tiết và theo dõi đơn.` |
| `You can track your order status by creating an account.` | `Bạn có thể theo dõi trạng thái đơn hàng bằng cách tạo tài khoản.` |
| `Email Address:` | `Địa chỉ email:` |
| `Print receipt` | `In đơn hàng` |
| `Question` *(survey — TL thêm)* | `Câu hỏi` |
| `Add an option...` *(survey — TL thêm)* | `Thêm tùy chọn...` |
| `Submit Answers` *(survey — TL thêm)* | `Gửi câu trả lời` |
| `Submitting...` *(survey — TL thêm)* | `Đang gửi...` |
| `Thank you for completing our survey!` *(survey — TL thêm, từ `Controller/Survey/Save.php`)* | `Cảm ơn bạn đã hoàn thành khảo sát của chúng tôi!` |

Sau đó `bin/magento cache:flush` (translation dictionary cached).

## Verification

- ✅ `bin/magento cache:flush` xong; dictionary resolve qua store emulation (frontend area, `PART_TRANSLATE`): store 1 (vi_VN) 14/14 OK — 12 phrase mới + `Continue Shopping` (có sẵn) + `Create an Account` (module CSV Mageplaza_SocialLogin); store 2 (en_US) 14/14 identity. Output thô tại `.ai/runtime/evidence/BUG-4TWFGD/verify.txt` (script: `verify-dictionary.php`)
- ✅ 2 CSV cân bằng 275/275 dòng, mọi dòng terminate (BR-001) — gồm 7 phrase AI + 5 phrase survey TL bổ sung; đã fix thiếu trailing CRLF cuối cả `vi_VN.csv` + `en_US.csv` do append tay (loại corruption đã gặp ở BUG-NTQ0H2)
- Còn lại cho QC browser: đặt test order (Mollie test mode) qua 2 path (guest + logged-in) để confirm on-page — trang success không curl được trực tiếp (SuccessValidator yêu cầu checkout session)

## Notes cho TL review
- ✅ **TL chốt (2026-08-26)**: "Mã đơn hàng" (order #/order number), "In đơn hàng" (Print receipt), "Cảm ơn bạn đã mua hàng!" — đúng như đã implement trong CSV
- Ticket thuộc danh nghĩa vùng checkout (Mageplaza OSC gắn handle vào trang) nhưng change là i18n dictionary-only — không đổi flow/logic/payment; Mode C + TL code review
- Tab title "Success Page" (browser tab) không dịch được qua CSV — xem Out of Scope; nếu cần thì follow-up theme layout override nhỏ
- Working tree cùng 2 file CSV đang chứa work chưa commit của BUG-NTQ0H2 (SLP-118) — phần thêm của ticket này là append cuối file, không đè
