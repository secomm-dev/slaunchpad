# TASK-QX93G3 (SLP-158) — Quick Cart drawer enhancement — Verification Results

**Date**: 2026-09-10 · **Env**: local (apache + docker mysql/opensearch restarted after WSL reboot) · **Runner**: Playwright chromium (`NODE_PATH=/tmp/pw-cal/node_modules`, run as root)

## Final tally — 34 PASS / 0 FAIL (5 suites, sau review round 2)

| Suite | Checks | Result |
|---|---|---|
| verify-vi.js (desktop 1280, store vi) | 14 | 14 PASS |
| verify-en.js (desktop 1280, store launchpad_en) | 7 | 7 PASS |
| verify-mobile.js (375x812, vi) | 5 | 5 PASS |
| verify-e2e.js (coupon → OSC) | 3 | 3 PASS |
| verify-sync.js (coupon sync cart page ↔ drawer) | 5 | 5 PASS |

Full raw log: `results.log`. Re-run order: `setup-test-data.php` → `verify-vi/en/mobile/e2e/sync.js`.

## Review round 2 (feedback user 09-10) — coupon không đồng bộ giữa cart page và drawer

| Direction | Root cause | Fix |
|---|---|---|
| Cart page → drawer | `hyva.postCart` chỉ replace `#maincontent`, **không refresh customer-data sections** → drawer giữ state cũ | Override `cart-main.phtml` (verbatim + 1 diff): postCart dispatch `reload-customer-section-data` trong `.finally` (không phải sau replace — replace có thể gãy, drawer vẫn phải sync) |
| Drawer → cart page | Cart page chỉ re-render qua `checkCartShouldUpdate` so qty/subtotal — **coupon/discount không đổi 2 giá trị này** → không bao giờ trigger | Cart page coupon form override (`php-cart/coupon.phtml`, verbatim + 1 diff): `submitForm()` dùng **native round-trip** (`$form.submit()` → couponPost → redirect) thay `hyva.postCart`; drawer coupon success trên cart page → `location.reload()`. Deterministic, đúng UX Magento gốc |
| **Root blocker (cả 2 chiều)** | `hyva.replaceDomElement` trên cart page **luôn abort**: script SocialLoginPro modal có top-level `let popup` → re-execution khi replace ném `Identifier already declared` (cùng lớp lỗi: `optionIsDisabled is not defined`, swatch renderer override cũ) — cơ chế fetch+replace của chính Hyvä đã hỏng sẵn trên trang này | Override `Mageplaza_SocialLoginPro/templates/hyva/header/modal.phtml` (verbatim + **1 token**: `let popup` → `var popup` — redeclare hợp lệ sloppy mode → script idempotent). Fix cả native postCart flows. 2 lỗi còn lại (`optionIsDisabled`, swatch) = follow-up ticket riêng (script hygiene cart page) |

**Kết quả sync verify (5/5)**: drawer apply → cart page hiện mã + label rule trong totals; cart cancel → drawer state xóa; cart apply → drawer state applied; console sạch (sau fix popup).

**Quyết định UX (round 2)**: coupon trên **cart page** dùng round-trip/reload (khác các trang khác nơi drawer vẫn AJAX no-reload) — vì cơ chế replace của trang này hỏng bởi lỗi vendor pre-existing; AC "qty/remove không reload page" vẫn giữ nguyên cho drawer (đã verify).

## Review round 1 (feedback user 09-10) — 3 finding → 3 fix → 29/29 PASS

| Finding | Root cause | Fix |
|---|---|---|
| Qty apply chậm (~1.5s) | `@change.debounce.500ms` — change chỉ fire khi blur/Enter + debounce 500ms nữa | Bỏ debounce trên `@change` (immediate); thêm `@input.debounce.1500ms` (apply sau khi ngừng gõ, không cần blur); dedupe guard `qtySubmitted[item_id]` chặn double-submit khi input-debounce và change cùng fire; sync map theo server trong `setCartItems` (retry sau stock error vẫn POST) |
| UI coupon chưa theo Hyvä | template tự chế (underline link + chip) | Rewrite theo Hyvä cart-page pattern (`php-cart/coupon.phtml`): `<details>` + sparkles icon `text-primary` + chevron rotate, `form-input w-full`, `btn btn-secondary w-full`, applied = input disabled với mã + "Hủy mã giảm giá" (`vi-06-coupon-applied-settled.png`) |
| Subtotal không đổi sau apply | Magento cart section không có discount — drawer chỉ có subtotal TRƯỚC discount (native semantics, giống cart page) | Plugin expose thêm `discount_amount` (tổng `quote_address.discount_amount` đã persist — giá trị ÂM; **không** collect totals trong section request — đã thử `getTotals()['discount']` = 0 do section không có request context để collect). Drawer render row "Chiết khấu −190,18 US$" dưới subtotal (`x-if="cart.discount_amount"` + `hyva.formatPrice`) |

**Đã xác nhận settled state**: buttons = ["Hủy mã giảm giá"], input disabled = QCQUICK10, discount -190.18 — probe 14.

## AC coverage

- **AC-001** qty/subtotal: `vi-01` subtotal 679,20 US$ → qty+ → **1.358,40 US$** (2x, đúng giá 679,20), **no page navigation** (URL unchanged across toàn bộ flow). EN sync PASS.
- **AC-002** clamp/stock: qty=0 → blur → debounce → **clamp về 1** rồi POST; qty=9999 → per-item error VI **"Không đủ sản phẩm để bán"** render trong item (`vi-04-qty-error.png`), cart không hỏng.
- **AC-003** remove: trash → empty state VI, không reload (`vi-05-empty.png`).
- **AC-004** coupon: sai → inline error VI **"Mã giảm giá không hợp lệ. Vui lòng kiểm tra lại mã và thử lại."** (module dict, LL-0012(4)); đúng **QCQUICK10** → chip mã + subtotal; cancel → chip mất; **EN error identity** "The coupon code isn't valid. Verify the code and try again."; **state persists across navigation** (chip count=1 sau goto trang khác — section `coupon_code` từ plugin).
- **AC-005** promo CMS: block `cart-drawer-promo` render trong drawer cả 2 store (`vi-01`, VI text "Giảm thêm 10% với mã QCQUICK10…").
- **AC-006** states: empty/loading/error hoạt động sau override (native regression).
- **AC-007** mobile 375: drawer **x=0 w=360** (fit viewport sau fix `max-w-full`), stepper tap được (qty→2), không tràn ngang, subtotal + CTA visible; EN aria identity "Decrease quantity of product …".

## E2E light (coupon → OSC)

Coupon apply trong drawer → CTA "Thanh toán" → land `/onestepcheckout/` → OSC summary render row **"Chiết khấu (TASK-QX93G3 test rule — delete after QC) -190,18 US$"** (stack với auto rules mẫu) + ExtraFee rows + grand total. **Full place-order không chạy local** — bị chặn bởi pre-existing shipping errmsg (BUG-AMRBJR F4) → QC demo env.

## Fixes phát hiện trong quá trình verify (đã sửa trong change set)

1. **Drawer tràn mobile**: vendor `w-[480px]` không cap → drawer cắt ~85% ở 375px. Fix `max-w-full` trên dialog (diff #3 của override). Trước/sau: `mobile-01-drawer-fixed.png`.
2. **Coupon POST lệch store** dưới `?___store=` mode: fetch không mang store param → hit default store (VI quote). Fix: coupon form append `___store` từ `location.search` vào POST body. Production cookie-mode không ảnh hưởng (fetch gửi cookie store).

## Env findings (pre-existing, không thuộc change set)

- **Store cookie không bao giờ được set** khi dùng `?___store=` (F3 BUG-NY0M3S — vẫn còn sau cache:flush). Guest quote theo store → ATC/POST phải kèm param. Test EN phải submit form ATC với action+`?___store=launchpad_en`.
- Console "Error fetching data" trên PDP = ExtraFee fetch fail pre-existing (BUG-KFJ49A) — đã filter có ghi chú.
- OSC page lần đầu load có thể treo spinner + "Khóa biểu mẫu không hợp lệ" (pre-existing, ExtraFee AJAX 302) — summary render lại OK.
- REST `/rest/V1` guest-carts "product doesn't exist" cho sku hợp lệ khi có `Store: default` header — không điều tra thêm (ngoài scope).

## Test data (local, cleanup sau QC)

- salesrule id=11 "QuickCart QC 10%" coupon **QCQUICK10** (from_date 2026-09-01 — validator date filter chạy behind local date, từ_date=hôm nay bị loại)
- cms_block id=20 identifier **cart-drawer-promo** (store 0)
- Script: `setup-test-data.php` (idempotent)
