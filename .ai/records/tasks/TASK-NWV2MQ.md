---
id: TASK-NWV2MQ
type: task
title: '[Checkout] Đồng bộ style popup social login ở checkout với popup Hyvä của theme (SLP-211)'
project_code: SLP
parent: null
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-25
updated: 2026-09-25
external_refs:
  tickets: SLP-211
legacy_ids: []
ticket_ref:
decisions: []
decision_assessment: display-only CSS trên OSC page (§12 checkout) — Tier-2 formality, không chạm flow/order/payment/auth logic; precedent TASK-8TXS2P (SLP-203) + resolve TL flag #1 của TASK-E1YAHT (SLP-259) theo hướng (a)
components:
  - app/code/Launchpad/MageplazaSocialLogin
source_areas:
  - social-login-checkout-modal
  - ll-0011-luma-scope
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 4d5fcf8f
last_verified: 2026-09-25
supersedes: []
---

# [SLP][TASK-NWV2MQ] [Checkout] Đồng bộ style popup social login ở checkout với popup Hyvä của theme (SLP-211)

<!-- External ticket: SLP-211 "apply social login style của theme hyva sang popup login của trang checkout". -->

## Summary

Popup social login (Mageplaza) ở các trang theme là **popup Hyvä** (`dialog.wrap-modal-login`, style bởi theme overlay `web/tailwind/theme/social-login.css` — SLP-160 → SLP-259 → global style SLP-246). Trang checkout OSC chạy **Magento/luma scope** (LL-0011) nên popup là **luma fallback** (`.modal-popup.osc-social-login-popup` + legacy `#social-login-popup`), style bởi module CSS `Launchpad_MageplazaSocialLogin::css/social-login-checkout.css` (SLP-203). Sau SLP-203, popup Hyvä đã đổi (SLP-259: title bỏ banner; SLP-246: DS token, input/button/close mới) → 2 popup lệch nhau. TASK-E1YAHT đã flag TL (flag #1); SLP-211 = hướng (a) — đồng bộ checkout theo popup Hyvä hiện hành.

Đo Playwright (Chrome 150, vi, 1280 + 375, 3 view) — lệch chính: title banner `#3399cc` (home: heading ink trần); block-title có gạch dưới; label fw500/#020617 (home fw600/ink #101828); input 42px radius 4 (home 44px radius 6, fs 16, padding 10/14); nút primary 36px radius 4 fw600 (home 42px radius 8 fw500); close 36px viền xám cách mép 27px (home không viền, glyph #4a5565, cách mép 12px); header thừa → title cách mép trên 104px (home 39px); form thụt 25px (block padding 20/25 — home 20/0), view create/forgot mobile còn thụt thêm; nút social full width trên mobile (home co theo nội dung, lệch trái 15px).

## Mini Spec

### Goal
Popup social login ở checkout (view Đăng nhập, Tạo tài khoản, Quên mật khẩu; desktop + mobile) trông đồng bộ với popup Hyvä ở các trang khác: bố cục, spacing, màu, shape của title, block-title, label, input, nút primary, link, cột social, nút close.

### Expected Behavior
- Mở popup từ auth-link checkout → cùng hình học với popup home (sai số ≤ 4px cho vị trí/kích thước các phần tử chính trong card), cùng màu/border/radius/weight.
- Chuyển view login ↔ create ↔ forgot vẫn hoạt động (vendor JS nguyên trạng); link "Quên mật khẩu?" thẳng hàng (center) với nút "Đăng nhập".

### Constraints / Rules
- **Giữ font-family của trang checkout** (Open Sans luma) — KHÔNG mang Inter sang; typography family sẽ làm lại khi có design (user chốt 2026-09-25). Cỡ chữ/weight/line-height theo popup Hyvä.
- CSS-only trong `social-login-checkout.css` (load duy nhất qua handle `onestepcheckout_index_index`) — không sửa template/JS/vendor, giữ mọi hook (`#social-login-popup`, `#mp-popup-social-content`, `.actions-toolbar.social-btn`, `.btn-social`, `.action.*.primary`).
- Màu nút primary tiếp tục lấy từ config `style_management` (vendor `css.phtml` inject) — không đè.
- Màu khác dùng token theme theo convention overlay: `var(--color-*, <hex resolved trên popup home>)` (luma scope không có token → fallback hex = giá trị token thật), không tạo màu mới.
- Magento/static ops chạy as `secomm`.

### Out of Scope
- Font Inter trên checkout; popup Hyvä ở trang theme (không đổi); `#request-popup` (TL flag #2 E1YAHT); các section khác của checkout (SLP-203).

### Acceptance Criteria
- AC-1: Title 3 view = heading trần (nền trong suốt, chữ ink #101828, 18px/600), không còn banner `#3399cc`.
- AC-2: Block-title "Khách hàng đăng ký" không gạch dưới, ink 12.8px/500; label 14px/600 ink; input cao 44px, radius 6px, padding 10/14, border `#d1d5dc`, chữ 16px.
- AC-3: Nút primary (Đăng nhập / Tạo tài khoản / Gửi) cao 42px, radius 8px, 12.8px/500, màu config giữ nguyên; link phụ 12.8px, căn giữa dọc với nút trên desktop; mobile (<768px) nút full width + link căn giữa bên dưới.
- AC-4: Nút close 36px, không viền/nền, glyph `#4a5565`, cách mép card 12px; title cách mép trên card ≈39px (desktop) — header thừa không còn.
- AC-5: Cột social: divider "Hoặc Đăng nhập bằng" + nút social nền `#fcfefd`, viền `#d0d5d1`, radius 6, shadow DS xs, spacing như home; mobile stack 1 cột với cùng inset như home.
- AC-6: Regression — mở/đóng popup, chuyển 3 view, validation message VI (SLP-203 round 6), không tràn/scroll ngang ở 1280/768/375; CSS không ảnh hưởng trang khác (handle-scoped).

## Approach

1. Sửa `social-login-checkout.css` theo từng section (card/header/close, title, form, toolbar, social column, mobile) — target lấy từ DOM + computed style popup home (`.ai/evidence/TASK-NWV2MQ/before-*`).
2. Deploy static luma scope (as secomm) + probe Playwright before/after 3 view × 1280/375 so với home; screenshot.
3. Cập nhật CHANGELOG + README module; ghi kết quả vào record.
4. TL review (Tier-2 formality) → QC demo (e2e checkout theo §7.1).

## Implementation Notes

**File đổi (CSS-only, 0 template/JS/vendor):**
- `app/code/Launchpad/MageplazaSocialLogin/view/frontend/web/css/social-login-checkout.css` — viết lại theo số đo popup Hyvä: card (surface, shadow, gutter 16px mobile), header collapse, close 36px không viền/glyph muted cách mép 12px, layout flex 2 cột (form flex 1 + social 240px, gap 32, padding 24/16), title heading trần, block/field/label/input/primary/link/newsletter/note, cột social (divider, nút surface + viền gray-300 + shadow DS xs, offset 15/27px như Hyvä), breakpoint 767 (card thay modal-slide) + 640 (stack).
- `README.md` (section mới "Checkout popup style") + `CHANGELOG.md` (entry 2026-09-25).
- Static luma: `cp` tay vào `pub/static/frontend/Magento/luma/vi_VN/Launchpad_MageplazaSocialLogin/css/` (quick-strategy không refresh file đổi — SLP-160 F3; en_US materialize on-demand ở developer mode).

**Root cause các chênh lệch (vendor rule phải beat):**

| Symptom | Rule vendor | Cách beat |
|---|---|---|
| input/nút radius 4px dù đã set 6/8 | OSC design css `button, input, select, textarea { border-radius: 4px !important }` (page-wide) | id-anchored `!important` |
| link "Quên mật khẩu?" lệch lên trên nút; mobile đè lên nút (nguyên nhân thật của nudge 6px→2px) | `.modal-content .secondary a.action { margin: -20px 0 25px !important; float: right !important }` | `margin: 0 !important; float: none !important` + toolbar flex `align-items: center` → bỏ hẳn rule nudge 6px/2px |
| link mobile không cách nút 24px | `.actions-toolbar > .secondary:last-child { margin-top: 0 !important }` | `!important` trong media ≤767 |
| title cao hơn 15px | inline `padding-top: 15px` thua `.col-mp { padding: 0 !important }` | `padding: 15px 0 0 !important` |
| form thụt 25px | legacy `.block-container .block { padding: 20px 25px !important }` | `padding: 20px 0 !important` (1,3,0) |
| cột social mobile mất padding 10 | `.col-mp { padding: 0 !important }` | `padding: 10px !important` |
| title quên mật khẩu hẹp 80% | legacy `.forgot-pass-title { width: 80% }` | h2 `width: auto` |
| card lệch phải + full-height ở 641–767 | luma modal-slide `left: 44px` + `_inner-scroll min-height: 100%` | breakpoint 767 (scoped `.osc-social-login-popup`) |
| popup không flex được | OSC `.mfp-hide { display: block !important }` | `#social-login-popup.white-popup { display: flex !important }` scoped trong modal |

**Quyết định (user 2026-09-25):** target = popup Hyvä đang chạy ở trang khác; **giữ font-family checkout** (Open Sans) — chờ design; nudge 6px→2px (WIP chưa commit của user) bỏ vì link đã căn giữa theo flex; scope gồm cả Tạo tài khoản + Quên mật khẩu.

**Deviation còn lại (chấp nhận):** do font Open Sans rộng/hẹp khác Inter — cột social desktop hẹp hơn 12px (min-content của chữ nowrap) → form rộng hơn 12px; label/link cao 18–21px thay 15–20px → cộng dồn ≤7px vị trí dọc. Checkbox newsletter giữ native (Hyvä dùng Tailwind forms checkbox) — cần style checked-state riêng, không làm trong ticket.

## Verification

Evidence: `.ai/evidence/TASK-NWV2MQ/` (Playwright `/tmp/pw-cal` + Chrome 150, local `slaunchpad.localhost`, store vi).

- [x] AC-1 — title bg `rgba(0,0,0,0)`, h2 `rgb(16,24,40)` 18px/600 cả 3 view × 4 viewport (`regression-vi-results.txt`).
- [x] AC-2 — block-title không border, 12.8px/500 ink; label 14px/600 ink; input 44px r6 padding 10/14 border `#d1d5dc` 16px (`compare-home-vs-checkout.txt`: label/input "OK" hoặc chỉ khác font-metric).
- [x] AC-3 — primary 42px r8 12.8px/500, bg config `rgb(51,153,204)`; link căn giữa dòng nút (dy ≤ 3px ở 1280/768); mobile nút full width + link dưới (`geometry-home-vs-checkout.txt`: link x/y lệch ≤ 1–2px so với home).
- [x] AC-4 — close 36px, không viền, glyph `#4a5565`, 12px từ mép; h2 cách close-anchor 27px (desktop) / 39px (mobile) = home.
- [x] AC-5 — nút social surface `#fcfefd` + SVG logo, FontAwesome ẩn; divider; mobile stack cùng inset (x −254 như home).
- [x] AC-6 — `regression.js` vi **99/99 PASS** (1280/768/745/375 × login/create/forgot: chuyển view, không tràn ngang, validation message VI hiện + không đè field kế, close đóng popup, 3 CSS checkout vẫn load, 0 console error trên trang checkout).
- en store: không switch được ở local (pre-existing — TASK-K14RVZ; store 2 `general/locale/code` = vi_VN). Thay bằng `en-text-overflow.js`: bơm chuỗi EN vào popup → **3/3 PASS** không tràn ở 1280/745/375. QC en trên demo.
- Ghi chú evidence: `before-metrics.json` key `checkout-forgot-*` thực chất đo view create (probe before click "Quên mật khẩu" fail vì view create đang mở) — screenshot tương ứng đã xoá; số đo after là đúng view.

**Chờ:** TL review (Tier-2 formality, display-only checkout) → QC demo (e2e checkout theo §7.1 + visual 3 view desktop/mobile + en).

## Related records

- TASK-8TXS2P (SLP-203) — restyle gốc popup checkout
- TASK-E1YAHT (SLP-259) — title bỏ banner ở popup Hyvä; TL flag #1 → ticket này
- TASK-7P5RJP (SLP-160) — style popup Hyvä
