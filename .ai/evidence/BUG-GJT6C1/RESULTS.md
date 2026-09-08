# BUG-GJT6C1 (SLP-146) — Evidence: render-path finding + Phase A live verification

**Date**: 2026-09-07 · **Env**: local `slaunchpad.localhost` (Magento 2.4.8-p5, developer mode, file cache) · **Method**: Playwright headless Chromium (fresh context per store) + static-dictionary inspection.

---

## FINDING (2026-09-07) — approach ban đầu của record nhầm render path

Ban đầu (09-04) record fix flatpickr qua theme override `Secomm/launchpad/Mageplaza_DeliveryTime/templates/hyva/checkout/delivery-information.phtml`. Verify render framework PASS 5/5 — nhưng **template đó không bao giờ render trên trang checkout thật**:

1. **Checkout page = Magento/luma scope, KHÔNG phải Secomm/launchpad (Hyvä)** — [probe-scope.js](probe-scope.js): homepage 11/11 assets từ `Secomm/launchpad`; checkout **453/453 assets từ `Magento/luma`** (OSC = checkout Knockout/Luma-scope; Hyvä chỉ chiếm phần storefront ngoài checkout).
2. **Block Delivery Time user thấy = Knockout component** (`checkout_index_index.xml` jsLayout → template `Mageplaza_DeliveryTime/web/template/container/delivery-information.html`) — input `#mp-delivery-date`, class `hasDatepicker`, **jQuery UI datepicker** (binding `mpdatepicker`, `view/frontend/web/js/view/delivery-information.js:106`) — [probe-blocks.js](probe-blocks.js) / [probe-address.js](probe-address.js) (cả trước lẫn sau khi fill địa chỉ guest).
3. **Block Hyvä/Magewire (flatpickr) là dead layout** — `hyva_checkout_components.xml` referenceContainer `checkout.shipping.section` **không tồn tại ở bất kỳ layout/template nào khác trên site** (grep toàn `app/code` + `vendor`: chỉ DeliveryTime + ExtraFee tự tham chiếu); live: `window.flatpickr === undefined`, 0 `.flatpickr-calendar`.
4. **Label "Delivery Date" English live** vì js dictionary của checkout = `frontend/Magento/luma/vi_VN/js-translation.json` (40 keys,scope luma KHÔNG có theme CSV của Secomm/launchpad trong fallback chain). Literal `'Delivery Date'` chỉ nằm trong module Mageplaza_DeliveryTime — module chỉ có `en_US.csv` → không có value vi. ("Delivery Time" vi được vì `Mageplaza_Osc/i18n/vi_VN.csv` có sẵn key đó.)
5. jQuery UI trên page chỉ có `$.datepicker.regional = ["", "en", "en-US"]` — **không có regional vi** (Magento không ship `datepicker-vi`) → popup calendar English.

**Side-finding cho TL (ngoài scope SLP-146):** BUG-5NR0PD (SLP-150) — (a) coupon phrases + (b) shipping-errmsg fix theo theme layer (`Secomm/launchpad/Mageplaza_*` overrides + theme CSV) có nguy cơ **cùng dead trên trang checkout luma-scope** (luma dict 40→45 keys không chứa các phrase đó; theme override không load). Chưa verify live (cần QC browser) — cần rework cùng cơ chế nếu confirm.

---

## Phase A (implement 2026-09-07, revised cùng ngày) — label/placeholder vi qua module `Secomm_MageplazaTranslate`

**Change:** module mới **`app/code/Secomm/MageplazaTranslate`** (`registration.php`, `etc/module.xml`, `i18n/vi_VN.csv`, README, CHANGELOG) + `module:enable` (`app/etc/config.php`). `i18n/vi_VN.csv` mirror 46/46 keys `en_US.csv` của `Mageplaza_DeliveryTime`, 8 phrase storefront mirror value theme CSV SLP-128.

> **Revision (chỉ thị user 09-07):** bản đầu tạo `app/code/Mageplaza/DeliveryTime/i18n/vi_VN.csv` trực tiếp — user yêu cầu tách vào module Secomm vì "viết vào Mageplaza sẽ khó update module". File tạm đã move vào `Secomm_MageplazaTranslate`; vendor dir nguyên vẹn. Cơ chế không đổi: i18n CSV của MỌI enabled module feed dictionary mọi theme scope (luma gồm trong đó).

**Deploy**: `setup:static-content:deploy -f vi_VN en_US` — KHÔNG regenerate `js-translation.json` đã tồn tại (LL-0008(3)) → phải `rm pub/static/frontend/Magento/luma/vi_VN/js-translation.json` rồi `-f vi_VN en_US` → dict luma 40 → **45 keys** (value từ `Secomm_MageplazaTranslate/i18n/vi_VN.csv`). Sau deploy: chown `secomm:secomm` file mới (shell root, vhost secomm — BUG-SDZPCD) + `cache:flush`.

### Live verification — [verify-live-labels.js](verify-live-labels.js) → [verify-live-labels-results.json](verify-live-labels-results.json)

| Check | vi store (lang=vi) | en store (lang=en, cookie `store=launchpad_en`) |
|---|---|---|
| Block trên trang | Knockout/luma-scope | Knockout/luma-scope |
| Title Delivery Date | **Ngày giao hàng** ✅ | Delivery Date ✅ |
| Title Delivery Time | **Thời gian giao hàng** ✅ | Delivery Time ✅ |
| Title House Security Code | **Mã bảo mật nhà** ✅ | House Security Code ✅ |
| Title Delivery Comment | **Ghi chú giao hàng** ✅ | Delivery Comment ✅ |
| Placeholder time select | **-- Vui lòng chọn thời gian giao hàng--** ✅ | -- Please select a delivery time-- ✅ |
| Calendar popup (jQuery UI) | English `Su Mo Tu We Th Fr Sa`, month "September" — **Phase B pending** | English ✅ (0 regression) |
| Console errors | 5/5 ambient (CSP/adobedtm pre-existing), 0 real | 5/5 ambient, 0 real |

> Lưu ý test en store: `?___store=launchpad_en` chỉ request-scoped (không set cookie) — session en phải inject cookie `store=launchpad_en` trước khi thêm giỏ (quote theo store view). Đã fix trong script.

---

## Phase B (IMPLEMENT 2026-09-07 đợt 2 — mixin trong `Secomm_MageplazaTranslate`)

**Change:** `view/frontend/requirejs-config.js` (mixin `Mageplaza_DeliveryTime/js/view/delivery-information`) + `view/frontend/web/js/delivery-date-locale-mixin.js` — trước `_super()` của `initialize`, khi `<html lang>` startsWith `vi`: đăng ký `$.datepicker.regional.vi` (monthNames "Tháng chín"…, dayNamesMin `CN T2…T7`, `firstDay: 1`, không chứa dateFormat) + `$.datepicker.setDefaults`. En store bỏ qua (gate theo lang).

**Deploy:** rm dict + `requirejs-config.js` (luma + launchpad × vi/en) → `-f vi_VN en_US` → mixin `delivery-date-locale-mixin` **REGISTERED** trong aggregate; chown + flush.

### Live verify — [verify-live-phaseb.js](verify-live-phaseb.js) → [verify-live-phaseb-results.json](verify-live-phaseb-results.json)

| Check | vi store | en store |
|---|---|---|
| Calendar month header | **Tháng chín 2026** ✅ | September 2026 ✅ |
| Weekday row | **T2 T3 T4 T5 T6 T7 CN** (Thứ 2 đầu tuần) ✅ | Su Mo Tu We Th Fr Sa ✅ |
| Prev/Next aria | (regional vi) | Prev / Next ✅ |
| `$.datepicker.regional.vi` | registered ✅ | **không apply** (gate) ✅ |
| Console real errors | 0 | 0 |

## Update đợt 2 — +3 phrase (yêu cầu user 09-07)

- `Comments` / `Enter your comment here` (OSC Knockout `review/comment.html`): +3 row vào `Secomm_MageplazaTranslate/i18n/vi_VN.csv` + mirror theme CSV pair → dict luma + launchpad đều có (`Comments` → "Bình luận", placeholder → "Nhập bình luận của bạn tại đây"). **Lưu ý: OSC order-comment block đang DISABLED trong config → chưa verify-live (không render); khi bật block tự vi.**
- `Leave a message for the extra fee.`: là **default value** field `message_title` (Admin ExtraFee rule form, `General.php:236`) — string PHP thuần nên KHÔNG vào js-dictionary (đúng cơ chế); value vi trong module CSV áp dụng cho render PHP (admin scope). **Frontend label = `rule.message_title` trong DB — rule #1 (`rrrrrr`) đang lưu English → cần Admin sửa per store view (store data).**

### Phụ lục — options Phase B đã cân nhắc (lưu trữ quyết định)

Calendar popup (`#ui-datepicker-div`) English vì jQuery UI không có regional vi trên page. Override JS **không thể đặt trong theme `Secomm/launchpad`** (checkout chạy scope luma → theme JS override dead — cùng lý do flatpickr override ban đầu dead). Đơn vị requirejs-config cấp **module** luôn load ở mọi theme scope. Options đã cân nhắc — **chọn option 1** (user directive 09-07: module Secomm, không ghi vào vendor):

| # | Option | Ưu | Nhược | Kết quả |
|---|---|---|---|---|
| 1 | requirejs mixin vào **`Secomm_MageplazaTranslate`** | Không sửa vendor; module đã có sẵn; một module gánh cả i18n + datepicker locale | TL approval ở Code Gate | **ĐÃ IMPLEMENT + verify PASS** |
| 2 | Thêm requirejs-config mixin vào `Secomm_Base` sẵn có | Không tạo module mới | Động vào shared base module (scope creep) | Không chọn |
| 3 | Chỉ giữ Phase A (label vi), calendar để English | 0 code thêm | Không đạt AC-001 | Không chọn |

Kèm quyết định dọn dẹp còn mở cho TL: **xóa hay giữ** theme override `Secomm/launchpad/Mageplaza_DeliveryTime/templates/hyva/checkout/delivery-information.phtml` (dead layout — khuyến nghị xóa; tương tự 2 JS override dead `Mageplaza_Osc/web/js/action/{set-coupon-code,cancel-coupon}.js` từ BUG-5NR0PD tự ghi nhận).

## Probe artifacts

- `probe-scope.js` — asset scope home vs checkout (bằng chứng luma-scope)
- `probe-blocks.js` / `probe-address.js` — cấu trúc DOM block trước/sau fill địa chỉ guest
- `probe-calendar-open.js` — mở datepicker jQuery UI thật: weekday `Sunday=Su…Saturday=Sa`, `$.datepicker.regional` không có `vi`
- `probe-i18n-state.js` — span EN/VI hỗn hợp + dictionary script = `Magento/luma/vi_VN`
- `verify-live-labels.js` + `verify-live-labels-results.json` — kết quả bảng trên
- `verify-calendar.js` — script verify flatpickr ban đầu (không dùng nữa — dead path)
