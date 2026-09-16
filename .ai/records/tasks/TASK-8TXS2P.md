---
id: TASK-8TXS2P
type: task
title: '[Checkout page] Điều chỉnh UI các section của Checkout + apply Hyvä UI cho social login ở checkout (SLP-203)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-203
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-16
updated: 2026-09-16
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only CSS trên OSC page (§12 checkout) — Tier-2 formality, không chạm flow/order/payment logic; follow precedent BUG-AMRBJR/TASK-EPJVGG (display-only → Mode B/C + e2e QC bắt buộc)
components:
  - app/code/Launchpad/Osc
  - app/code/Launchpad/MageplazaSocialLogin
source_areas:
  - mageplaza-osc-checkout
  - social-login-checkout-modal
  - ll-0011-luma-scope
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-16
supersedes: []
---

# [SLP][TASK-8TXS2P] [Checkout page] Điều chỉnh UI các section của Checkout + apply Hyvä UI cho social login ở checkout (SLP-203)

<!-- External ticket: SLP-203. 6 điểm display-only trên trang Mageplaza OSC checkout (desktop + mobile 375). Không đổi flow, không đổi template vendor, không đổi JS. TL đã confirm qua PM: (1) payment radio lệch baseline; (2) "3 khung" = nút −/input/+. của stepper; (3) social login = apply style SLP-160 lên checkout (checkout chạy luma scope nên style Hyvä chưa apply). -->

## Ticket + AC

[Checkout page] Điều chỉnh UI các section của Checkout. Social login đã style bằng Hyvä UI (SLP-160) nhưng chưa được apply ở checkout.

- **AC-1 (P1)**: Payment Methods — radio và label text thẳng baseline (tâm radio = tâm dòng chữ đầu, sai số đo được ≈ 0) trên mọi method row, kể cả row có logo.
- **AC-2 (P2)**: Order Summary — 3 khung của qty stepper (nút −, input số lượng, nút +) cùng kích thước (24×24) và thẳng hàng, không lệch to nhỏ.
- **AC-3 (P3, mobile)**: Subtitle checkout description không còn bị cắt bởi "line ngang" — root cause `margin-top: -21px` của `.opc-estimated-wrapper` (Luma `_estimated-total.less`) đè lên dòng cuối của description.
- **AC-4 (P4, mobile ≤480px)**: Các field form address thẳng lề trái với nhau (input hết float right 98% lẻ tẻ khi grid stack 1 cột).
- **AC-5 (P5, mobile)**: Payment list có padding trái hợp lý — radio không còn sát mép viewport (bỏ `margin-left: -15px` của Luma `_payments.less`).
- **AC-6 (S6)**: Modal social login mở từ auth-link trên checkout có style theo Hyvä UI (SLP-160): card trắng, heading panel đậm, bỏ blue bar #3399cc trùng lặp, nút social trắng viền + SVG logo brand (không FontAwesome), nút primary xanh theme (#14532d), stack 1 cột mobile.
- **AC-7**: Regression — flow checkout không đổi (đặt hàng, coupon, DeliveryTime, ExtraFee); trang khác (cart, account, login Hyvä) không bị CSS mới đụng (CSS load duy nhất qua handle `onestepcheckout_index_index`).

## Ngữ cảnh kỹ thuật (từ DOM inventory 2026-09-16, evidence `inventory*.json`)

- Checkout OSC chạy **Magento/luma scope** (LL-0011): theme `Secomm/launchpad` không load — stylesheet thực tế = `Magento/luma` + module CSS. Fix phải là **module-layer CSS** (pattern BUG-2MK37V `osc-discount-code.css` trong `Launchpad_Osc`).
- Local checkout render **raw Luma** (không có OSC design config xanh lá như demo — store data). Fix mang tính cấu trúc (size/alignment/overflow) nên transfer sang demo; QC visual cuối trên demo.
- Social login modal trên checkout = **jQuery modal luma** `.modal-popup.osc-social-login-popup` chứa `#social-login-popup` (template luma legacy: `.btn-social` FontAwesome, blue bar `.social-login-title` #3399cc) — khác hẳn modal Hyvä của SLP-160; handle `hyva_default.xml` không load ở luma scope nên template swap thuần không đủ (Tailwind classes dead) → **CSS-only restyle** theo target SLP-160, giữ nguyên mọi JS hook (`#mp-popup-social-content`, `.actions-toolbar.social-btn`, `socialProvider`).

## Root causes (đo được)

| # | Symptom | Root cause | Nguồn |
|---|---------|-----------|-------|
| P1 | radio lệch baseline | radio 13px + `margin: 2px 5px 0 0` vs label line 19px → tâm lệch 1px (demo lệch rõ hơn theo font design config) | Luma/OSC payment markup |
| P2 | 3 khung stepper lệch | `.button-action` 18×18 content-box (+1px border = 20) vs input 26×19, margin-top −3px | `Mageplaza_Osc .../summary/item/details.html` + luma css |
| P3 | text cắt bởi line ngang | `.opc-estimated-wrapper { margin: -21px -15px 15px }` — margin-top âm kéo bar đè description | `theme-frontend-luma/Magento_Checkout/.../_estimated-total.less` |
| P4 | field không thẳng hàng | `.row-mp .mp-6 input[...] { width:98%; float:right }` vs `.mp-clear → float:left` — khi grid stack ≤480px lề trái xen kẽ 30/36.3px | `Mageplaza_Osc css/style.css` ~line 993 |
| P5 | payment sát mép | `.checkout-payment-method .payment-methods { margin: 0 -15px }` (mobile) → radio x=8 ngoài section padding | `theme-frontend-luma/Magento_Checkout/.../_payments.less:182` |
| S6 | modal legacy | luma scope + `hyva_default` handle dead → template luma (`.btn-social`, FontAwesome, blue bar #3399cc) | `Mageplaza_SocialLogin` layout/template |

## Approach

1. `Launchpad_Osc`: CSS mới `view/frontend/web/css/osc-checkout-ui.css` (plain CSS — module CSS không qua Tailwind build) cho P1–P5; attach qua `<css src>` trong `view/frontend/layout/onestepcheckout_index_index.xml` (handle có sẵn).
2. `Launchpad_MageplazaSocialLogin` (module BUG-KQ5A1D): CSS mới `view/frontend/web/css/social-login-checkout.css` cho S6 + layout `onestepcheckout_index_index.xml` attach. Scope selector `#social-login-popup`/`.modal-popup.osc-social-login-popup` (id-specificity thắng inline/vendor css.phtml — pattern SLP-160).
3. Verify Playwright vi/en × 1280/375: đo before/after từng điểm + regression (auth-link, modal open/close, coupon, place-order path render).
4. e2e checkout QC + payment test = bắt buộc §7.1 → QC trên demo (local có pre-existing shipping errmsg F4 BUG-AMRBJR chặn place-order).

## Out of scope

- OSC design config (màu xanh demo), payment methods list, subtitle text = store data → Admin.
- Màu `--color-primary` compiled hiện oklch-blue (stale/generated) — dùng `#14532d` (green-900, fallback SLP-160 dùng, khớp visual demo); TL/design chốt token chính thức sau.
- Modal login Hyvä trên các trang khác (SLP-160 sở hữu).

## Verify (2026-09-16 — DONE, chi tiết `evidence/TASK-8TXS2P/RESULTS.md`)

- **vi desktop 1280 + mobile 375: 30/30 PASS** (Playwright, measured): P1 radio delta **−1.0 → 0**; P2 stepper **[20/26/20 lệch] → [24,24]×3 aligned**; P3 estTop 308.2 > subBottom 227.1 (hết overlap, margin-top 0); P4 input x **[30/36.3 xen kẽ] → [30]** duy nhất; P5 radio x **8 → 23**; S6 modal restyle 10/10 check (card 760, blue bar rgba(0,0,0,0), btn trắng #d1d5db radius 6, FA ẩn + SVG data-URI, primary rgb(20,83,45), stack mobile, mở/đóng OK)
- **en: 30/32** — 2 FAIL duy nhất = en store session không switch (pre-existing: store 2 `general/locale/code` = vi_VN + `?___store=` no-op — flag sẵn trong TASK-K14RVZ; CSS locale-agnostic byte-identical 2 locale) → QC en trên demo
- Regression: SLP-199 discount CSS + coupon input, SLP-139 extra-fee CSS, place-order control — PASS; console sạch (2× "Error fetching data" = ambient pre-existing baseline TASK-WXBQYZ)

## Findings khi verify (đã fix trong change set)

1. **Bug thật — nút close modal không click được**: header xẹp (h1 ẩn, vendor `header { padding: 0 !important }`) trong khi close absolute cao ~53px → `h2.login-title` vẽ đè intercept pointer (Playwright click timeout). Fix: header `min-height: 54px` + close `z-index: 5`.
2. **Close X trắng trên nền trắng**: vendor OSC css `action-close:before { color: #fff !important }` thiết kế cho blue bar đã bỏ — fix `body.checkout-index-index …::before { color: #020617 !important }` (0,5,2) > (0,5,1).
3. Cascade của css.phtml inline style beat 2 rule đầu bằng specificity (pattern SLP-160) — không cần !important ngoài trường hợp đối đầu với !important vendor (width card, màu X).

## Round 2 (09-16 — user feedback "input chưa thẳng hàng do có padding left")

P4 round 1 scope hẹp (≤480px). Probe 4 viewports phát hiện vendor `width:98%` + float xen kẽ
(`float:right` trên `.mp-6`, `float:left !important` trên `.mp-clear`) zigzag ở **mọi breakpoint**:
1280 (2 cột) cột 2 lệch +5.7px so nhãn; 768/600 (đã stack) lệch +6.7/+10.8px; 375 sót select
Quốc gia/Tỉnh float + w=308.7. Fix: rule all-widths scoped `.form-shipping-address`/`.row-mp` —
`float:none !important; width:100% !important` (gutter do `.col-mp` padding quản lý) + normalize
padding-left select 10→9px (cascade dropdown Phường/Xã inject ngoài `.control` → selector
`.field`-level). Verify `evidence/TASK-8TXS2P/alignment-round2.txt` + `probe-align2.js`:
boxOffset=0 mọi field × 4 viewport, textStart uniform, full suite vi 30/30 PASS.

## Round 3 (09-16 — user feedback 4 ảnh: gutter field ngang, mobile labels, nút Áp dụng −2px, extra fee)

1. **Mất gutter field ngang (desktop)** — round 2 `width:100%` làm 2 cột input chạm nhau (measured gutter=0, `.col-mp` padding 0 tại desktop). Fix: `width: calc(100% - 12px)` → gutter cố định **12px**.
2. **Mobile label không thẳng hàng** — vendor `label.label { padding-left: 2% }` (style.css ~1176) chỉ exempt `.mp-clear` → labels cột 2 ("Họ", "Số điện thoại") thụt **6.3px** so với cột 1. Fix: `.form-shipping-address label.label, .row-mp label.label { padding-left: 0 }` — 11/11 labels x=30.
3. **Nút Áp dụng margin-left −2px** — Luma `.abs-discount-code ... .primary .action { margin: 0 0 0 -2px }` (_checkout.less:96, pattern ghép nút-input); nút OSC chỉ có `.action.action-apply` (không `.primary`); OSC render nút ở flex row riêng → −2px lệch. Fix scoped discount section, `margin-left: 0 !important` (match qua `.actions-toolbar .action`).
4. **Extra fee label lệch** — cùng rule `label.label` 2%: "rrrrrr" x≈36 vs checkbox x=30. Fix `#mp-extra-fee label.label, #mp-extra-fee-billing label.label { padding-left: 0 }`.

Verify (probe-round3.js, evidence `round3-after2.txt`): gutter **0→12px**; labels **11/11 padL 0, x=30**; btn margin-left **−2px→0px**; extra-fee label x=30 = checkbox x=30; full suite vi **30/30 PASS**. Screenshots `round3-desktop-form.png`, `round3-mobile-discount.png`, `round3-mobile-extrafee.png`.

## Round 4 (09-16 — user feedback 2 ảnh mobile: billing không thẳng hàng + padding không đồng bộ giữa các section)

Chuẩn nội dung section trên mobile = **x=30** (shipping address form đạt được qua `.form-shipping-address { padding: 20px 15px }`). Đo hiện trạng: billing form content **x=15** (không indent), payment radios **x=15**, discount input **x=15** (section full-bleed `margin: 0 -15px` triệt tiêu 15px inset sẵn có của `.payment-option-content`). Fix (mobile ≤767, desktop untouched):
- `#checkout-step-billing { padding: left/right 15px }` → billing labels/inputs/toggle về x=30
- `.checkout-payment-method .payment-methods` thêm `padding: 0 15px` (cùng rule P5) → radio x=30
- `.opc-payment-additional.discount-code { margin: 0 }` — chỉ bỏ full-bleed; **thêm padding là thừa (double-indent 45px)** vì `.payment-option-content` đã có inset 15px
- Mở P4/P4b scope cho billing: inputs `float:none + width calc(100% - 12px)` qua `#checkout-step-billing .control` (ID-specificity) + `#checkout-step-billing label.label { padding-left: 0 }`

Verify (`probe-round4c.js`): shipping/billing labels padL 100% = 0px, labelX {30} + 2 invisible hidden-label 29/187 (w=1); inputs/.radio/discount/Áp dụng **đều x=30**; desktop billing 20 / discount 610.4 **không đổi**; full suite vi **30/30 PASS**. Screenshot `round4-mobile-billing-v2.png`.

## Round 5 (09-16 — user feedback "form social login không giống với ở ngoài home page")

So sánh trực tiếp modal home (Hyvä, SLP-160 hiện hành) vs modal checkout: modal home giờ dùng **màu config Mageplaza `style_management` (#3399cc)** cho banner title + primary button (SLP-160 round 9 tuân config) — checkout modal round đầu của tôi hardcode heading đen + primary `#14532d` (target round-3 cũ, lỗi thời). Fix `social-login-checkout.css`:
- **Banner title giữ màu config** (không đụng background của css.phtml inline) — chỉ shape: `width auto !important` (vendor stretch `200%` (1,4,2) tràn 2 cột), bo góc 8px, h2 18px/600 trắng, ẩn icon PNG legacy (h2 background 40px) + FA glyph
- **Primary button bỏ hardcode màu** — giữ màu config, chỉ shape radius/padding
- **Close button = vòng tròn 36px viền + X thuần CSS** (2 thanh rotate trên span): vendor `padding: 15px !important` (0,5,1) bóp content còn 6px → selector `body.checkout-index-index ... header .action-close` (0,6,1); glyph font-icons/`span` SVG data-URI đều render không ổn định trên trang này → pure-CSS ×
- Verify: **vi 34/34 PASS** (spec mới: banner config color rgb(51,153,204), h2 600, primary config color, banner ≤ cột trái, close radius 9999px + 30 check cũ); ảnh `final-home-1280/375.png` vs `final-checkout-1280/375.png` (evidence)

## Traps

- Static deploy quick-strategy lại bỏ qua refresh file đổi (SLP-160 F3) — cp tay artifact sang `pub/static/frontend/Magento/luma/{vi_VN,en_US}/Launchpad_*/css/`.
- Margin-top trên inline radio KHÔNG đổi được vị trí đo được (baseline = bottom margin edge) — dùng `vertical-align: <length>`.
- Discount input OSC tên `discount_code` (không phải `coupon_code` như core) — verify selector.

## Chờ TL review → QC (demo)

- QC demo: 6 điểm visual trên demo (design config xanh, payment VietQR/ZaloPay có logo, subtitle VI) + e2e place-order + payment test (§7.1) + QC en sau khi store-2 locale được khôi phục (TASK-K14RVZ flag).
