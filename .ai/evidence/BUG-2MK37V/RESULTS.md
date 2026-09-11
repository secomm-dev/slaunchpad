# BUG-2MK37V (SLP-199) — Evidence & Results — 2026-09-11

Fix: align apply-discount-code section on OSC checkout — CSS-only, injected qua
`Launchpad_Osc` module (checkout chạy Magento/luma scope, LL-0011 — theme CSS không load).

## Files changed (toàn bộ footprint)

- `app/code/Launchpad/Osc/view/frontend/web/css/osc-discount-code.css` — **mới**
- `app/code/Launchpad/Osc/view/frontend/layout/onestepcheckout_index_index.xml` — thêm `<head><css src="Launchpad_Osc::css/osc-discount-code.css"/>`
- `.ai/records/bugs/BUG-2MK37V.md`, thư mục evidence này

0 file Mageplaza / theme. Deploy: `cache:flush` (layout cache); developer mode materialize static on-demand.

## Baseline (trước fix — `probe-baseline.js`, SUFFIX mặc định)

Playwright guest checkout (`/joust-duffle-bag.html` → add to cart → `/onestepcheckout/`), viewport 1280 + 375, vi store.

| Metric | 1280 | 375 |
|---|---|---|
| misalign dTop (button − input) | **+12px** | +12px |
| misalign dBottom | **+12px** | +12px |
| input width | 250.5px (vendor `width:98% !important` → control 255.7) | **29.5px — sập** |
| inner row height | 60px (label clip 1×1 chiếm ~14px + `.control` margin-bottom 10px) | 36 |
| toolbar padding-top | 12px (Luma) | — |
| button bg | `rgb(1,0,127)` Luma | — |

Cơ chế: `.payment-option-inner` (vendor) = flex space-between, `align-items: normal` (stretch);
Luma `.actions-toolbar` padding-top 12px; label ẩn (1×1) vẫn chiếm dòng; ở 375 cột payment của OSC
chỉ rộng **172.5px** (`#checkout-step-payment` bên trong `.mp-col-2` vẫn flex row — OSC mobile
không stack) + `form#discount-form` `display:table` shrink-wrap → input sập.

Ảnh: `discount-vi-1280-before.png`, `discount-vi-375-before.png` (input sập còn chữ "N"), `checkout-vi-1280-before.png`.

## Sau fix (`after3-output.txt`, SUFFIX=after3)

| Metric | 1280 | 375 |
|---|---|---|
| misalign dTop / dBottom | **0 / 0** ✓ | wrap 2 hàng (thiết kế): input full-width 172.5px hàng trên, button 140px hàng dưới, không chồng lấn ✓ |
| input width | 246.7px (control flex `1 1 170px`) | 172.5px (= full cột) ✓ |
| inner row height | 36px (label `display:none`) | 2×36 + gap 12 |

Ảnh: `discount-vi-1280-after3.png`, `discount-vi-375-after3.png`, `checkout-vi-1280-after3.png`.

## Behavior regression (`probe-behavior.js` — coupon FREESHIP, vi store 1280)

- Apply `FREESHIP` → "Mã giảm giá đã được áp dụng thành công." — button → `action-cancel`
  "Hủy mã giảm giá", input `.disabled` ✓ (applied-state layout nằm gọn trong row mới)
- Cancel → "Mã giảm giá đã được xóa thành công." — button về `action-apply` ✓
- Bogus code → "Mã giảm giá không hợp lệ. Vui lòng kiểm tra lại mã và thử lại." ✓ (translation
  BUG-5NR0PD batch 2 intact)
- Sau cả 3 state: **dTop = 0, dBottom = 0** ✓
- Ảnh: `discount-vi-applied-beh.png`

## Console (behavior probe + `probe-en-fresh.js`)

5–6 issues, **đều ambient pre-existing, không liên quan CSS** (stylesheet không thể gây fetch/JSON
error hay jQueryUI warning): `Unrecognized feature: 'web-share'` (×2, Chromium), `Error fetching
data: SyntaxError … "<!doctype"` (fetch nhận HTML — pre-existing OSC ambient), `Fallback to JQueryUI
Compat activated` (vendor), 404 resource (không tái hiện ở run sau, failedRequests=0).
0 error mới từ thay đổi.

## En store — ghi nhận (ngoài scope)

`?___store=launchpad_en&___from_store=default` trên local env **không switch store** (page vẫn load
locale vi_VN — static path `Magento/luma/vi_VN/`, string VI). Là behavior site-level của local env,
pre-existing, không phải hệ quả của CSS (không có dimension locale trong thay đổi). Layout check
trên các page load này: dTop/dBottom = 0 ✓. Browser QC trên en store (qua UI switcher demo) nằm
trong checklist TL/QC dưới.

## Findings ngoài scope (flag TL/QC)

1. **Màu button/header là Luma `rgb(1,0,127)`** — screenshot ticket thể hiện button + header GREEN
   (brand theme). Checkout chạy luma scope nên không có brand styling; nếu design yêu cầu green →
   là scope theming checkout riêng (ticket mới), không gộp vào bug căn hàng.
2. **OSC mobile không stack cột**: ở 375px, `.mp-col-2` giữ flex row → cột payment (và section
   discount) chỉ ~172px dù viewport 375 (rule `@media (max-width:768px)` của OscUltimate không
   thắng). Pre-existing, ảnh hưởng toàn khu payment — đề xuất bug theo dõi riêng.
3. Store switch `?___store` local env — xem mục En store.

## QC checklist (TL/QC trên demo)

- [ ] vi + en store (UI switcher): section discount thẳng hàng 1280 + usable 375
- [ ] Apply/cancel coupon thật của demo → message + button state đúng
- [ ] Coupon sai → error message VI/EN đúng
- [ ] Khu vực payment methods + order summary không đổi style (scoped selectors)
- [ ] Xác nhận hướng xử lý màu (finding 1) + bug mobile stack cột (finding 2)
