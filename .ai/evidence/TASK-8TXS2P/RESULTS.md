# TASK-8TXS2P (SLP-203) — Verify Results

Ngày verify: 2026-09-16 · Env: local (slaunchpad.localhost, MAGE_MODE=developer) · Tool: Playwright (`/tmp/pw-cal`, host-resolver-rules MAP 127.0.0.1)

## Kết quả

| Suite | Kết quả | Ghi chú |
|-------|---------|---------|
| vi × desktop 1280 + mobile 375 (30 checks) | **30/30 PASS** | `final-vi-vi-results.log` |
| en × desktop 1280 + mobile 375 (32 checks) | **30/32 PASS** | 2 FAIL = en store session (`lang=vi`) — env pre-existing, không thuộc change set (xem §Env notes) |
| Regression SLP-199 (discount-code CSS) | PASS | stylesheet vẫn load, coupon input render |
| Regression SLP-139 (extra-fee CSS) | PASS | |
| Console | sạch | 2× "Error fetching data" ambient = pre-existing baseline (chứng minh TASK-WXBQYZ) |

## Chi tiết đo từng điểm (số liệu)

### P1 — Payment radio baseline (AC-1)
- Before (inventory.json): radio center 378.6 vs label center 379.6 → delta **−1.0px** (radio 13px, margin-top 2px, baseline alignment)
- After: **delta = 0** cả desktop lẫn mobile (`vertical-align: -1px`; margin-top không đổi được offset vì bottom-margin-edge đặt trên baseline)
- File: `Launchpad_Osc/.../osc-checkout-ui.css` rule P1

### P2 — Qty stepper 3 khung (AC-2)
- Before: minus/plus 20×20 (18 content-box + 1px border, margin-top −3px), input 26×19 position:absolute top −16% trong `.qty-wrap`
- After: **[24,24] × 3, tops lệch < 1px** — `.qty-wrap` position:static + input position:static, flex align-items:center, gap 4px; input viền #111 radius 4 khớp look nút
- Root cause vendor: `Mageplaza_Osc css/style.css` ~297–320 (`.qty-wrap` relative + input absolute), `.button-action` 18×18

### P3 — Subtitle bị cắt "line ngang" (AC-3, mobile)
- Root cause đo được: Luma `_estimated-total.less` → `.opc-estimated-wrapper { margin: -21px -15px 15px }` — bar kéo lên đè dòng cuối description (border-top 1px #ccc = "line ngang" trong screenshot ticket)
- After: `margin-top: 0` (mobile ≤767) → estTop 308.2 > subBottom 227.1, **không overlap** (measured)

### P4 — Form address thẳng hàng (AC-4, mobile)
- Root cause đo được: `Mageplaza_Osc css/style.css` ~993: `.row-mp .mp-6 input[...] { width: 98%; float: right }` vs `.mp-clear → float: left` — grid stack ≤480px (grid-mageplaza.css `.col-mp { width: 100% }`) giữ nguyên float → lề trái xen kẽ x=30 / x=36.3
- After: **tất cả input x = 30 (1 unique value)** — `.control` input/select/textarea `width: 100%; float: none` ≤480px

### P5 — Payment list padding (AC-5, mobile)
- Root cause đo được: Luma `_payments.less:182` mobile: `.checkout-payment-method .payment-methods { margin: 0 -15px }` → radio x=8, ngoài section padding (container x=23)
- After: `margin: 0` → **radio x = 23** = left edge của section (khớp header block)

### S6 — Social login modal apply Hyvä UI (AC-6)
- Before (`before-desktop-social-modal.png`): jQuery modal luma legacy — blue bar #3399cc trùng title, nút `.btn-social` FontAwesome (Facebook solid navy, Google viền đỏ), modal 600px
- After (`final-vi-vi-desktop-modal.png`): card trắng 760px (desktop) / full-width 359px (mobile 375), h1 duplicate ẩn, heading panel h2 đậm 20px ink, "Hoặc Đăng nhập bằng" divider 2 đường kẻ, nút social trắng viền #d1d5db radius 6 + logo SVG data-URI (FontAwesome display:none), primary #14532d (green-900), nút close X ink (vendor set trắng cho blue bar cũ), stack 1 cột ≤639px, modal mở/đóng PASS
- Beat-injection được xử lý bằng specificity (pattern SLP-160):
  - `#social-login-popup .social-login .social-login-title` (1,2,0) > css.phtml inline (1,1,0)
  - `#social-login-popup .social-login button#bnt-social-login-authentication` (2,1,1) > css.phtml (2,1,0)
  - `body.checkout-index-index … .modal-inner-wrap` (0,4,0)+!important > OSC `width: 600px !important` (0,3,0)
  - `body.checkout-index-index … .action-close::before` (0,5,2)+!important > OSC white-X (0,5,1)

## Fix trúng bug thật phát hiện khi verify
- **Nút close bị content đè (không click được)**: header xẹp 24px (h1 ẩn) trong khi close button absolute cao ~53px → `h2.login-title` intercept pointer events trên vùng close (Playwright click timeout ×58 retry). Fix: header `min-height: 54px` + close `z-index: 5`.

## Env notes (as-found, không thuộc change set)
- **en store session không switch được local**: `?___store=launchpad_en` no-op (curl → `lang="vi"`, không set store cookie), store cookie `launchpad_en` cũng bị drop (LL-0026 mở rộng / BUG-NY0M3S F3 "cookie store-switch hỏng sau cache:flush"). CSS locale-agnostic (byte-identical deploy 2 locale, 0 string mới) → QC en trên demo.
- Local OSC render **raw Luma** — không có OSC design config (màu xanh demo) + payment list khác demo (Check/Money order + VNPAY; demo thêm VietQR/ZaloPay) = store data. Fix mang tính cấu trúc → demo QC cuối.
- `osc/general/description` local = EN ("Please enter your details below…") — subtitle VI demo là store data.
- Static deploy quick-strategy bỏ qua refresh file đã đổi (SLP-160 F3) → cp tay artifact `pub/static/frontend/Magento/luma/{vi_VN,en_US}/Launchpad_*/css/*` sau SCD.

## Chưa verify (QC demo)
- AC-1/P1 với row có logo (ZaloPay/VietQR — không có ở local), font/size design config demo
- E2E place-order + payment test (§7.1 bắt buộc với change chạm OSC) — local chặn bởi pre-existing shipping errmsg (BUG-AMRBJR F4)
- Social login OAuth thật (credentials local dummy)
- Modal create/forgot tab visual (chỉ verify tab login; cùng selectors đã restyle)


## Round 2–5 (user feedback iterations, 2026-09-16)

- **Round 2**: P4 mở rộng all-widths — vendor 98%+float zigzag mọi breakpoint (1280 +5.7px, 768/600 +6.7/+10.8). `probe-align2.js`: boxOffset=0 ×4 viewport.
- **Round 3**: gutter 2 cột `calc(100% - 12px)` (width 100% làm 2 cột chạm); `label.label` padL 2%→0 (Họ/SĐT lệch 6.3px); nút Áp dụng margin-left −2px→0 (Luma `_checkout.less:96`); extra-fee label 2%→0. `round3-after2.txt`.
- **Round 4**: sync padding mobile — billing form/payment radios/discount x=15→30 như shipping (`probe-round4c.js`: mọi content x=30; desktop untouched).
- **Round 5**: modal social login khớp modal home hiện hành — banner title + primary dùng màu config Mageplaza (`style_management` #3399cc, như SLP-160 round 9), bỏ hardcode xanh lá; banner hết tràn 2 cột (vendor width 200%); close = vòng tròn 36px + X pure-CSS (vendor padding 15px !important cần beat bằng (0,6,1)); icon PNG/FA legacy ẩn. Suite vi **34/34 PASS** (spec mới + 30 check cũ). Ảnh so sánh: `final-home-{1280,375}.png` / `final-checkout-{1280,375}.png`.
