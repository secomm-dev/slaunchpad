# SLP-264 follow-up bug 2026-10-01 — staging: 1 click ATC → +2 item (double-add)

Env verify: local `http://slaunchpad.localhost/` (curl `--resolve`), Playwright + Chrome 150
(`/tmp/pw-cal/probe-264-*.js`, copy trong thư mục này), DB Docker mysql `slaunchpad`.

## Root cause (đã chứng minh bằng reproduce, không phải suy đoán)

TASK-Y23EMS thiết kế PDP AJAX dựa trên DB row `checkout/options/ajax_add_to_cart_selectors
= .product_addtocart_form` (chỉ có ở local; **không** nằm trong `config.php`). Row này là
thủ công TASK-Z3DAH5 (Scope Extension 2) — biến mất trên staging:

- `Monsoon_HyvaAjaxAddToCart/etc/config.xml:15` — default = `.product_addtocart_form,
  #product_addtocart_form` (chứa **id của PDP form**).
- Staging thiếu row → fallback default → `window.setAjaxCart()` bind thêm listener `submit`
  trên PDP form `#product_addtocart_form`, **song song** với path Alpine `onSubmit`
  (product-form.phtml) → 1 click → cả 2 handler gọi `window.ajaxSubmitCart()` → **2 POST
  `checkout/cart/add` = +2 item**. Guard `isSubmitting` không chặn (2 handler khác context,
  `ajaxSubmitCart` không có re-entrancy check).

Đúng class root cause TASK-Z3DAH5 đã ghi ("song song fetch Monsoon → 2 POSTs, count ×2"),
tái phát qua config drift sau khi PDP chuyển AJAX (TASK-Y23EMS).

## Reproduce (PRE-FIX, local flip DB về giá trị module default = điều kiện staging)

`UPDATE core_config_data SET value='.product_addtocart_form, #product_addtocart_form'
WHERE path='checkout/options/ajax_add_to_cart_selectors'` + `cache:flush` →
`probe-264-double-add.js`:

| Test | posts | atcCalls | qty |
|---|---|---|---|
| T1 PDP simple (`joust-duffle-bag`) 1 click | **2** | 2 | 0→**2** |
| T2 PDP configurable (`chaz-kangeroo-hoodie`, đủ 2 nhóm option) 1 click | **2** | 2 | 2→**4** |

`selectorConfig` trong page xác nhận `.product_addtocart_form, #product_addtocart_form`
đang effective → double-bind xảy ra đúng cơ chế chẩn đoán.

## Fix (theme-layer, 1 file, +24 dòng — `fix.diff`)

`app/design/frontend/Secomm/launchpad/Monsoon_HyvaAjaxAddToCart/templates/hyva/script/addtocart.phtml`:

1. **Lớp 1 — `setAjaxCart` skip form `#product_addtocart_form`**: PDP có path AJAX riêng
   (product-form.phtml `onSubmit`); không bind thêm listener bất kể selectors config —
   loại phụ thuộc vào DB row (env-proof).
2. **Lớp 2 — re-entrancy guard per form trong `ajaxSubmitCart`**: flag
   `form.dataset.ajaxInFlight` set tại entry, clear ở **đầu `finally`** (trước nhánh
   early-return cookie — guard không bao giờ kẹt) → mọi double-call trong lúc in-flight
   collapse về 1 POST. Flag nằm trên form element, không serialize vào body POST.

## Verify POST-FIX (config VẪN = module default, chưa restore)

| Test | posts | atcCalls | qty |
|---|---|---|---|
| T1 PDP simple 1 click | **1** | 1 | 0→**1** |
| T2 PDP configurable 1 click | **1** | 1 | 1→**2** |

Guard-isolation (`probe-264-guard-only.js` — mô phỏng đúng listener staging đã bind bằng
`addEventListener` tay + click): `atcCalls=2` (cả 2 handler gọi) nhưng **posts=1, qty 0→1**
→ lớp 2 collapse double-call đúng thiết kế (chống mọi double-bind tương lai).

## Regression full (restore config local as-found `.product_addtocart_form` + `cache:flush`)

`regression-full.json` — bộ probe-double-msg.js (AC-001→AC-005 + AC-008):

| Test | Kết quả |
|---|---|
| T1 PDP simple ×3 | mỗi click **1 POST**, qty +1, drawer mở, **1 message** |
| T2 PDP configurable ×2 | mỗi click **1 POST**, qty +1, 1 message |
| T3 configurable thiếu option | **0 POST**, inline field-error (validation giữ nguyên) |
| T4 PLP card ×2 (`/gear/bags.html`) | mỗi click **1 POST**, 1 message |
| T5 Quick View ×2 | mỗi click **1 POST**, drawer mở, 1 message |

`php -l` PASS. ENV as-found: DB đã restore `.product_addtocart_form` (diff 0 so với đầu
session). Scope: 1 file thay đổi; `product-form.phtml` không đụng.

## Staging verify (SAU DEPLOY — thuộc DevOps/QC, không thuộc change set)

1. Chẩn đoán env: `SELECT scope, scope_id, value FROM core_config_data WHERE
   path='checkout/options/ajax_add_to_cart_selectors';` — expect row thiếu hoặc chứa
   `#product_addtocart_form` (confirm root cause thật trên staging).
2. Deploy + `cache:flush` full → probe `probe-264-double-add.js` (đổi `BASE` + executablePath
   theo staging): T1/T2 = 1 POST, qty +1.
3. Không bắt buộc set DB staging về `.product_addtocart_form` nữa (fix đã env-proof) —
   nếu muốn đồng bộ config giữa các env thì là quyết định TL/DevOps riêng.
