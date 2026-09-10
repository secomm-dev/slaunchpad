# Evidence — BUG-KFJ49A (SLP-183) — [UI][product detail] Translate

Ngày verify: 2026-09-09 · Sau `cache:flush` · local `slaunchpad.localhost` (store 1 = vi_VN, store 2 = launchpad_en)

## 1. Framework-level (curl HTML sau flush — `pdp_vi.html` / `pdp_en.html`)

| Marker | vi | en |
|---|---|---|
| `x-data="initProductCartForm"` + `@submit="onSubmit"` trên `#product_addtocart_form` | ✓ | ✓ |
| `data-validate='{"validate-one-required": true}'` + `data-validation-container` trên radio swatch | ✓ (7 radio; 0 radio còn `required` standalone) | ✓ |
| Container `#attribute-messages-<id>` (2 fieldset) | ✓ | ✓ |
| Qty: `required` + `data-validate='{"validate-number": true}'` + min/max/pattern giữ nguyên | ✓ | ✓ |
| Form KHÔNG có attr `novalidate` tĩnh (runtime-set — không-JS giữ native) | ✓ | ✓ |
| Phrase `Thấp nhất là` / `As low as` (label giá tier) | ✓ | ✓ |
| Phrase `Thông tin thêm` / `More Information` (tab attr) | ✓ | ✓ |
| Phrase `Sản phẩm liên quan` / `Related Products` | ✓ | ✓ |
| Message rule VI trong emitted JS (`\u`-decoded): `Vui lòng nhập một số.` + `Vui lòng chọn một trong các tùy chọn.` | ✓ | — |
| Message rule EN identity: `Please enter a number.` (space = ` ` do escapeJs) + `Please select one of the options.` | — | ✓ |
| VI leak trên en | — | CLEAN |

## 2. Runtime (Playwright headless Chromium — `verify-pdp-validation.js`)

| Case | Kết quả |
|---|---|
| AC-001 vi: form `novalidate` sau Alpine init | **PASS** |
| AC-001 vi: submit chưa chọn option → không navigate + **2 message** `Vui lòng chọn một trong các tùy chọn.` (dưới fieldset Kích thước + Màu sắc) | **PASS** (count=2) |
| AC-002 vi: chọn đủ option, qty=0 → `Trường Số lượng phải chứa giá trị lớn hơn hoặc bằng "1".` | **PASS** |
| AC-002 vi: qty rỗng → required message VI | **PASS** |
| AC-003 vi: option hợp lệ + qty 1 → submit (redirect `/onestepcheckout/` — OSC redirect config) | **PASS** |
| AC-005 vi (simple atlas-pouf): qty=0 → min message VI | **PASS** |
| AC-005 vi: qty `e` (badInput/sanitize rỗng) → `Vui lòng nhập một số.` hoặc required fallback | **PASS** |
| AC-004 en: novalidate + submit trống → `Please select one of the options.` ×2 | **PASS** |
| AC-004 en: qty=0 → `Quantity field must contain a value greater than or equal to "1".` (configurable + simple) | **PASS** ×2 |

**13/13 PASS.** Screenshot: `evidence-pdp-invalid-full.png` (message VI dưới 2 fieldset, label `Thấp nhất là`, layout không vỡ).

## 3. Quan sát phụ (pre-existing — không phải regression của ticket này)

1. **Console error `Error fetching data: SyntaxError … <!doctype`** trên PDP — nguồn: `Mageplaza_ExtraFee/templates/hyva/product/view/extra-fee.phtml:53` (override có sẵn từ task trước, fetch endpoint fail trên local). Xuất hiện cả khi không đụng form add-to-cart. Baseline: trang chủ console CLEAN → error chỉ trên PDP. Flag cho TL/QC — ngoài scope ticket này (file không nằm trong change set).
2. **Category page `/living-room.html` → 500** — pre-existing OpenSearch `catalog_product` index (đã flag Tier-2 từ 08-25, CURRENT_STATE Blocked).
3. Attribute labels bảng thông tin (`SKU`, `Color`, `Size`, `Material` trên local) — **store data** (attribute store label per store view) — demo đã đặt label VI, local chưa → Admin. Cùng nhóm với mojibake mô tả sản phẩm (`bouclÃ©`/`â€"` — double-encoding trong data) và tên category/option.
4. Redirect sau add-to-cart = `/onestepcheckout/` (OSC config redirect-to-checkout) — behavior như cũ.

## 4. Chưa verify tự động (chuyển QC)

- Repro message server `The requested qty exceeds the maximum qty allowed in shopping cart` (cần cart đạt max_sale_qty trên browser — key đã verify có trong dict vi/en; message sinh ở frontend area nên theme CSV áp dụng).
- Cart configure popup (`checkout/cart/configure` — cùng template set, tự hưởng validation mới).
- badInput thực bằng bàn phím thật (thu hẹp sau Chrome 120+ nhưng vẫn đạt qua paste/ký tự `e`/`-`).

---

# Addendum đợt 2 — fix UI message qty (2026-09-09, sau QC feedback)

## 5. Defect đo được trước fix (screenshot `evidence-qty-row-before-fix.png`)

| # | Defect | Đo |
|---|---|---|
| 1 | Input qty lệch xuống 4px so với button | wrapper runtime `.field` có `margin-top:4px` → delta = **-4px** |
| 2 | Message ép vào cột hẹp giữa giá + button, wrap 2 dòng | `ul.messages` width **250px** (cột flex của wrapper), 2×20px |
| 3 | Row giá/qty/button giãn khi message xuất hiện | row height 42px → **86px**, price/button xô lệch |

Root cause chung: qty không có `data-validation-container` → lib tự wrap input vào
`div.field.field-reserved` trong flex row (`product-info.phtml`) → wrapper thành flex item.

## 6. Fix

- `product-info.phtml` (override SLP-109 có sẵn): div `#qty-messages-<productId>` dưới nguyên row (điều kiện `isSaleable()`).
- `quantity.phtml`: `data-validation-container="#qty-messages-<productId>"` trên input → lib hết tự tạo wrapper.

## 7. Verify sau fix (Playwright `verify-round2-ui-fix.js` — **15/15 PASS**)

| Nhóm | Kết quả |
|---|---|
| UI-01: không runtime wrapper quanh qty | PASS (`field-reserved` không tồn tại) |
| UI-02: input thẳng hàng button | PASS (delta = 0) |
| UI-03: row không giãn | PASS (h = 44) |
| UI-04: message dưới row, full width 1 dòng | PASS (w = 539px, y=694 > rowBottom=678) |
| Regression AC-001/002/004/005 vi+en (radio ×2, qty 0/rỗng, VI/EN) | PASS |
| AC-003: add hợp lệ → success message + item trong `/checkout/cart` | PASS |
| Console | PASS (0 pageerror) |

Screenshot sau fix: `evidence-qty-row-after-fix.png`.

## 8. Quan sát pre-existing mới (không thuộc change set — flag TL/QC)

- Message session **"Khóa biểu mẫu không hợp lệ. Vui lòng tải lại trang."** trên PDP xuất hiện **kể khi không submit lần nào**: load PDP → AJAX `mpextrafee/product/extrafee` trả 302 → error message vào session → hiển thị ở lần load full-page kế tiếp (cả khi add-to-cart thành công, message chồng với message success). Chứng minh: context Playwright sạch, chỉ load 2 PDP liên tiếp không đụng form → message vẫn hiện. Cùng nhóm pre-existing với console error `extra-fee.phtml:53`.
- Redirect sau add-to-cart về product page (uenc) — config redirect-after-add không set → Magento default; không phải regression.
