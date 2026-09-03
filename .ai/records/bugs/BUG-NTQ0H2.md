---
id: BUG-NTQ0H2
type: bug
title: Translate login/create/forgot popups and default Hyva login page; fix validation error layout overflow
project_code: SLP
parent:
external_refs:
  ticket: SLP-118
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
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Mageplaza_SocialLogin + Snowdog_Menu
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n
  - app/design/frontend/Secomm/launchpad/web/tailwind
source_areas:
  - theme-i18n
  - theme-css
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][BUG-NTQ0H2] Translate login/create/forgot popups and default Hyva login page; fix validation error layout overflow

<!-- External ticket: SLP-118. Screenshot: form create SocialLogin — dịch đã chạy (SLP-114) nhưng error message dài tràn mép field. -->

## Summary
(1) Dịch nốt các popup login/create/forgot của SocialLogin + trang login mặc định Hyva (page chrome, captcha, newsletter, form login). (2) Sửa style dòng error validation đang tràn rộng hơn field (nguyên nhân: `max-width: fit-content` trên `.field-error .messages`).

## Mini Spec

### Goal
- Trang `/customer/account/login/` không còn chuỗi English (trừ CMS-block content thuộc Admin)
- Validation error wrap trong biên field, không tràn mép form/popup

### Constraints / Rules
- Không sửa `app/code/` third-party (Snowdog fix phải ở tầng theme)
- BR-001 mirror `en_US.csv`; Tailwind v4 CSS-first (không tạo tailwind.config.js)

### Out of Scope
- CMS block DB content ("Creating an account has many benefits: check out faster…", "Sample Customer Accounts") — cần đổi trong Admin
- Popup SocialLogin đã dịch ở SLP-114 (chỉ rà sót)

### Acceptance Criteria
- AC-001: Login page — mọi chuỗi audit (Customer Login, Skip to Content, My Cart, Cart is empty, Subtotal, Continue Shopping, Checkout, Sku, captcha x2, JS-notice x2, demo notice, search placeholder, password toggle, New Customers, newsletter x4) hiển thị tiếng Việt
- AC-002: `.field-error .messages` có `max-width:100%` + `overflow-wrap:anywhere` trong CSS build
- AC-003: `npm run build` Tailwind thành công
- AC-004: `en_US.csv` mirror đủ (BR-001)

## Root Cause

**Dịch**: kế thừa SLP-114 đã dịch validation + SocialLogin; còn sót page chrome (title, skip-link, JS notice, minicart strings, captcha, search placeholder, password toggle, New Customers, newsletter box) — không có trong theme CSV.

**Error layout (root cause đúng — theo chẩn đoán TL, đã hiệu chỉnh)**: các field trong form popup SocialLogin + login form Hyva **không có class `field-reserved` trong markup** → `advanced-form-validation.js` (`fieldWrapperClassName: 'field field-reserved'`) không tìm thấy container qua `closest()` → gọi `createMessageContainer()` **rip input khỏi DOM nhét vào `div.field.field-reserved` mới** (`insertBefore(wrapper, el); wrapper.appendChild(el)`) — DOM restructure này + margin `--reserved-space` của class gắn động phá layout popup (CSS float legacy của module).

*(Bản ghi trước đổ cho `max-width: fit-content` — sai: đó là stylingDesign có chủ ý của theme cho message ngắn 1 dòng. CSS fix tạm (`fit-content` → `100%`) đã revert.)*

**Build break (có sẵn, lộ ra khi rebuild)**: `Snowdog/Menu/view/frontend/tailwind/tailwind-source.css` dùng cú pháp Tailwind v3 (`@layer utilities` + plain class rồi `@apply hover:snowdog-menu-text-hover`) — v4 chỉ cho `@apply` utility đăng ký bằng `@utility`. Module được thêm vào `hyva-themes.json` (working tree, trước session này) nhưng chưa từng rebuild → build đầu tiên fail.

## Fix

1. **+25 phrase** vào [vi_VN.csv](app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv) + mirror [en_US.csv](app/design/frontend/Secomm/launchpad/i18n/en_US.csv): page chrome (Customer Login, Skip to Content, JS notice x2, demo notice, search placeholder), minicart (My Cart, Cart is empty, Subtotal, View and Edit Cart, Continue Shopping, Checkout, Sku), captcha x2, login form (If you have an account…, Password hidden/shown, Show/Hide Password, New Customers), newsletter x4 (Enter your email address, Newsletter, Subscribe to Newsletter, Subscribe)
2. **Field markup (root-cause fix)**: 5 theme override — copy template gốc, thêm `field-reserved` vào từng `class="field …"` để validation dùng container hiện có, **không** wrap DOM động:
   - `Mageplaza_SocialLogin/templates/hyva/popup/form/{authentication,create,forgot,email}.phtml`
   - `Magento_Customer/templates/form/login.phtml` (login form mặc định Hyva)
3. **Snowdog unblock (theme-level)**: `@utility snowdog-menu-text-hover` trong [utilities/index.css](app/design/frontend/Secomm/launchpad/web/tailwind/utilities/index.css) — không sửa source module; giữ color sync với module
4. `npm run build` (Tailwind v4) + `bin/magento cache:flush`
5. **Đã revert (theo TL)**: sửa CSS `.field-error .messages` (`fit-content` giữ nguyên — không phải nguyên nhân)

## Verification

- Login page sau flush: ✅ toàn bộ chuỗi English audit đã biến mất (Customer Login, My Cart, Reload captcha, New Customers, Skip to Content, Enter your email address, Subscribe to Newsletter…) — bản vi hiển thị đầy đủ (kể cả attribute HTML-encoded: placeholder search/newsletter, aria password toggle)
- Field markup: ✅ login page render `field field-reserved …` trên mọi field của login form + popup authentication/forgot/email/create (verify bằng grep markup sau flush)
- Build: ✅ `npm run build` thành công (sau @utility fix)
- Popup create/login/forgot: dịch từ SLP-114 đã xác nhận qua screenshot của ticket
- Còn lại cho QC browser: confirm **visual** — submit form popup rỗng, error message nằm gọn trong field (giờ JS dùng container sẵn, không wrap DOM)

> Raw debug output → `.ai/runtime/evidence/BUG-NTQ0H2/` (verify.txt)

## Notes cho TL review

**→ ADDENDUM (2026-08-26, TL báo "customer/account/createpassword/ chưa dịch"):** trang createpassword render **template core** `resetforgottenpassword.phtml` (Hyva có layout nhưng không có template) → (a) theme override kèm `field-reserved` (4 field — cùng cơ chế error-layout); (b) +5 phrase (Set a New Password, New Password, Confirm New Password, Password Strength, No Password); (c) sweep luôn register + forgotpassword cùng họ: +4 phrase (Personal Information, Sign-in Information, Confirm password hidden, confirm password shown). Dictionary 980+ entries, CSVs 284/284 cân bằng. Nhãn strength-meter JS (Very Weak…Strong — RequireJS widget) ngoài cơ chế CSV. QC qua link reset-password thật trong email.

- **Customer account pages sweep (thêm 2026-08-26 theo yêu cầu TL "còn thiếu translate trong các trang customer")**: audit theo source 172 phrase từ Hyva templates (Customer account/address + Sales + Newsletter + Wishlist + Downloadable + account nav labels); 45 đã dịch qua module CSV; **+127 phrase** vào theme CSVs (dashboard, sổ địa chỉ, lịch sử/chi tiết đơn hàng, invoice/shipment/refund, wishlist, newsletter, downloadable). Không module core nào ship vi_VN. Dictionary 962 entries — mọi phrase resolve.
- **Đã sửa corruption CSV**: một dòng thêm trước đó thiếu CRLF cuối file → đợt append sau dính 2 row vào một dòng (`Creating an account has many benefits: check out faster…` + ` Example: `) — đã tách lại sạch ở cả 2 file. CSVs cân bằng 262/262 (BR-001).
- **`Email` identity**: dictionary loader bỏ qua row key==value — hiển thị "Email" vốn đúng tiếng Việt, vô hại.
- **QC browser pending**: các trang cần đăng nhập (dashboard, address book, orders, wishlist, newsletter, downloadable) — curl login bị chặn bởi Hyva client-side form_key; đề nghị QC walkthrough.
- **`styles.css` diff sẽ lớn**: rebuild gồm (a) pre-existing rebuild chưa commit từ trước session, (b) styles của Snowdog_Menu lần đầu được compile (do `hyva-themes.json` thêm module), (c) rule `.messages` mới. Cần review kỹ trước khi commit.
- **generated/hyva-source.css** +3 dòng `@source` (Snowdog) — cũng là thay đổi working-tree từ lần generate này.
- CMS-block content tiếng Anh cần xử lý qua Admin (danh sách trong evidence).
- Thuật ngữ TL đã chốt: "Giỏ hàng của tôi", "Tổng phụ", "Khách hàng mới", "Đăng ký nhận bản tin", "Sổ địa chỉ", "Đặt lại" (Reorder), "SL" (Qty), "Lô hàng" (Shipment), "Đơn hàng của tôi".
