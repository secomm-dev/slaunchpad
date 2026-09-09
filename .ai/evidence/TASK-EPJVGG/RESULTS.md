# TASK-EPJVGG (SLP-198) — Verification Results

**Date**: 2026-09-09 · **Env**: local `slaunchpad.localhost` (Magento 2.4.8-p5, developer mode, file cache) · **Method**: Playwright headless Chromium (fresh context per store, 1504×940) + computed-style measurement.

## Change under test

| File | Change |
|---|---|
| `app/code/Launchpad/MageplazaExtraFeeFix/view/frontend/web/css/extra-fee-checkout.css` | Extend 2 rules SLP-139 thêm selector `#mp-extra-fee` (summary/place-order block): collapse `.mp-description` rỗng (`:has(span:not(:empty))` guard) + cap margin 4px khi có description. Header comment cập nhật. **Duy nhất 1 file code đổi.** |

Deploy steps: `bin/magento cache:flush` (as secomm) + purge 3 stale static copies (xem F1). Không cần `setup:static-content:deploy` ở dev mode (router serve on-demand từ module dir sau khi purge).

## Block vị trí render (xác nhận đúng area trong screenshot ticket)

`#mp-extra-fee` nằm trong: `#opc-sidebar > .col-mp > #co-place-order-area > .osc-addition-content-wrapper > .osc-place-order-block.checkout-addition-block` — cột order summary, trên khối newsletter/gift-wrap + Place Order (`ancestorChain` trong `baseline.json`).

## AC results

### AC-001 — gap title-rule → option đầu tiên, OSC `/onestepcheckout/`: PASS

| Store | Baseline | After fix |
|---|---|---|
| vi | 45px | **5px** |
| en | 45px | **5px** |

Target tham chiếu trang shopping cart (Hyvä): **13.5px** — OSC sau fix còn 5px (compact hơn cả target, ngang mức demo Mageplaza ~0–10px). Files: `baseline.json` (45px + rects/computed culprit), `after-final.json` (5px).

Culprit (rects + computed, `baseline.json`): `.mp-description` rỗng (`descSpanText: ""`) render line box 20px (rect 1419→1439 trên vi) + margin 10px×2 → 41px thừa giữa label bottom và rule top; sau fix `display: none`.

### AC-002 — scope: PASS

- Cart page `/checkout/cart/` (template Hyvä `hyva/cart/extra-fee.phtml`): gap **13.5px giữ nguyên** cả 2 store, baseline = after (`baseline.json`, `after-final.json`).
- Stylesheet chỉ attach handle `onestepcheckout_index_index` (SLP-139, không đổi) — không thể load trên trang khác.

### AC-003 — regression SLP-139 (`#mp-extra-fee-billing`): PASS

Rule #1 flip `area` 3→1 (fixture có chủ đích) + harness đầy đủ SLP-139 (`probe-extrafee3.js` — fill address + cascade dropdown): gap `#mp-extra-fee-billing` = **9px** (= giá trị sau fix SLP-139). Probe tối giản không fill address đo sai 427px do payment step reflow (xem record §Notes F2). File: `billing-regress-slp139-harness.json`.

### AC-004 — mechanism: PASS

Computed `.mp-description` (en store, `after-debug-en.json`): `descDisplay: "none"`, `descMargin: "4px 0px"` (fallback cap có `!important`, beat inline style KO template); stylesheet serve có 3 reference `#mp-extra-fee ` (`cssHasMpExtraFeeRule: 3`).

### AC-005 — fixture restore: PASS

Sau round AC-003, rule #1 trả về **`area=3`** (giống trạng thái trước session) + re-measure: OSC 5px / cart 13.5px / billing hidden cả 2 store (`after-restore-area3.json`).

## Findings (cho team)

- **F1 — stale static copy (bổ sung F1 BUG-NY0M3S)**: `pub/static/frontend/Magento/luma/en_US/...` + `Magento/blank/{en_US,vi_VN}/...` của CSS này là **regular-file copy** (materialize 09:03 lúc deploy SLP-139) — source đổi + `cache:flush` KHÔNG cập nhật được; chỉ `luma/vi_VN` là symlink nên live. Dẫn tới lần verify đầu: vi 5px (PASS) nhưng en vẫn 45px (stale). Fix: `rm` 3 bản copy (as secomm) → dev-mode router serve fresh từ module dir. Đề xuất: khi sửa asset đã từng deploy, check `ls -la pub/static/...` xem symlink hay copy trước khi kết luận "fix không ăn".
- **F2 — đo spacing payment step cần page state đầy đủ**: probe không fill địa chỉ → gap billing đo 427px (KO reflow giữa lúc payment step dựng layout). Chỉ harness đầy đủ (fill address + cascade, như `probe-extrafee3.js`) cho số ổn định (9px).
- Fixture rule #1 `rrrrrr` = area=3 sau session (SLP-139 cần area=1, ticket này + SLP-144 cần area=3 — xem F2 record BUG-NY0M3S: QC chia rule hoặc set area trước mỗi round).
- Console không assert trong đợt này (CSS-only; ambient errors pre-existing BUG-KFJ49A: PDP `mpextrafee/product/extrafee` 302).

## Probe scripts

`probe-baseline.js` (baseline + after cả 2 trang, 2 store), `probe-debug.js` (stylesheet/sheet-rules/computed introspection — phát hiện F1), `probe-billing.js` (billing đo nhanh — dùng kèm cảnh báo F2), tái sử dụng `../BUG-NY0M3S/probe-extrafee3.js` (harness đầy đủ cho AC-003).

**QC trên demo env cần**: rule ExtraFee area=Cart (3) tương tự local; so sánh gap khối "Extra_fee_Cart" trong order summary trước/sau deploy; regression block payment (SLP-139) + cart page.
