---
id: BUG-KFJ49A
type: bug
title: "[UI][product detail] Translate — CSV phrase còn thiếu + native HTML5 validation bubble trên PDP"
project_code: SLP
parent:
external_refs:
  tickets: SLP-183
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-09
updated: 2026-09-16
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Hyva Theme Module (hyva.formValidation)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/view/product-form.phtml (mới)
  - app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/view/quantity.phtml (mới)
  - app/design/frontend/Secomm/launchpad/Magento_Swatches/templates/product/swatch-item.phtml (mới)
  - app/design/frontend/Secomm/launchpad/Magento_Swatches/templates/product/view/renderer.phtml (mới)
  - app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/product/view/product-info.phtml (override có sẵn từ SLP-109 — đợt 2 thêm div #qty-messages-<id>)
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv + en_US.csv
source_areas:
  - storefront-ui
  - catalog-product
  - i18n
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-09
supersedes: []
---

# [SLP][BUG-KFJ49A] [UI][product detail] Translate — CSV phrase còn thiếu + native HTML5 validation bubble trên PDP

<!-- External ticket: SLP-183. Scope: presentation layer của trang chi tiết sản phẩm (PDP) trên theme Secomm/launchpad. Không đụng controller, POST endpoint `checkout/cart/add`, server-side validation, quote/cart logic. -->

## Summary

Ticket SLP-183 yêu cầu translate trang product detail. Điều tra (09-09) chia evidence thành 3 nhóm:

1. **CSV phrase còn thiếu (4 key)** — `As low as` (Hyvä `price.phtml` `__()`), `More Information` (layout `catalog_product_view.xml` argument `translate="true"`), `Related Products` (`items.phtml` `__()`), `The requested qty exceeds the maximum qty allowed in shopping cart` (server-side `Magento_CatalogInventory\Model\StockStateProvider::__()`, render ở frontend area). Child theme KHÔNG override template Magento_Catalog/Magento_Swatches nào → key giữ nguyên như vendor; dict theme 889/842 dòng chưa có 4 key này.
2. **Native browser bubbles (3 message)** — `Please select one of these options.` / `Value must be greater than or equal to 1.` / `Please enter a number.` KHÔNG phải Magento phrase (grep toàn vendor không có) — là **HTML5 constraint validation bubble của trình duyệt** (text theo locale trình duyệt, không dịch qua CSV được): radio `required` của swatch options (`swatch-item.phtml:107`) và `type="number" min="1"` của qty (`quantity.phtml:116,120`). User quyết định (09-09): **tắt native validation, chuyển sang tự verify** — áp pattern BUG-5S2Z25 (SLP-115): wire `hyva.formValidation` (component tự set `novalidate`) → message render VI/EN từ theme CSV.
3. **Store data — out of scope code** — mojibake `bouclÃ©`/`â€"` trong mô tả sản phẩm (double-encoding UTF-8 trong **data** sản phẩm; attribute value "Bouclé" ở bảng attr thì đúng), tên category `Living Room`, giá trị attribute/configurable option (`Cognac`, `Leather`, `Black`, `55 Cm`, `2 Seat`…) → Admin sửa per store view (pattern đã ghi CURRENT_STATE mục "Còn thiếu").

## Mini Spec

### Goal

PDP validate client-side bằng `hyva.formValidation` của Hyvä (message VI/EN theo store qua theme CSV, không còn native bubble theo locale trình duyệt) và 4 phrase còn thiếu được dịch đầy đủ cả 2 store.

### Expected Behavior

- Submit add-to-cart khi chưa chọn swatch option → message VI `Vui lòng chọn một trong các tùy chọn.` (key có sẵn CSV:202) render **dưới fieldset của từng attribute** (không phải trong chip), KHÔNG có native bubble, không navigate.
- Qty `0` → message VI `Vui lòng nhập giá trị lớn hơn hoặc bằng "1".` (key có sẵn CSV:59); Qty rỗng → `Trường Số lượng là bắt buộc.` / fallback `Trường này là bắt buộc.` (key có sẵn CSV:50/51); Qty badInput (`e`, `-`…) → `Vui lòng nhập một số.` (phrase mới).
- Nhập hợp lệ + đủ option → POST `checkout/cart/add` như cũ (full-page submit, `$form.submit()` không fire submit event — không loop).
- Label giá tier render `Thấp nhất là:` (vi) / `As low as:` (en); tab attr `Thông tin thêm`; heading `Sản phẩm liên quan`; server trả lỗi vượt max qty → message VI.
- en_US: message identity (EN), không leak VI.
- Không-JS: form KHÔNG có `novalidate` (attr do component set lúc Alpine init) → giữ đúng behavior native như trước khi change.

### Constraints / Rules

- Không sửa `vendor/hyva-themes/*` in-place — override trong child theme `Secomm/launchpad` (chỉ thị BUG-GJT6C1).
- **Không tạo layout file** (gotcha BUG-5S2Z25 §Gotcha layout — template-only).
- `hyva_form_validation` handle đã load global qua Mageplaza ExtraFee/SocialLogin compat `hyva_default.xml` → `hyva.formValidation` có sẵn trên PDP (đã xác nhận marker lib trong rendered HTML).
- Rule discovery của `hyva.formValidation` tự map attr native (`required`/`min`/`max`/`step`/`pattern`) + `data-validate` JSON → giữ nguyên attr native của qty (min/max/step/pattern); radio thì **bỏ `required`** vì message `required` built-in sẽ lấy tên từ label chip ("Trường 2 Seat là bắt buộc." — sai nghĩa) → dùng custom rule `validate-one-required` + message key có sẵn.
- Qty input nằm ngoài DOM form (association qua attr `form=`) nhưng `formElement.elements` (HTMLFormControlsCollection) vẫn include → được validate bình thường.
- Layered nav dùng chung `swatch-item.phtml` (block `product.swatch.item`) nhưng inputs không thuộc form nào có `hyva.formValidation` → `data-validation-container` không bao giờ bị query → vô hại.
- String mới bổ sung vào **cả** `vi_VN.csv` + `en_US.csv` (BR-001).

### Out of Scope

- Store data nhóm 3 (mojibake mô tả, tên category/attribute option) — đề xuất Admin/PM xử lý theo CURRENT_STATE "Còn thiếu".
- `Magento_ConfigurableProduct/templates/.../configurable.phtml` (select dropdown cho attribute không phải swatch — không xuất hiện trên product demo; message select wrap trong div cha là chấp nhận được).
- Các form khác ngoài `product_addtocart_form` (list-page quick add, wishlist, cart configure popup dùng chung template → tự hưởng validation mới; không QC riêng trừ khi QC yêu cầu).
- Key JS `$t()` Osc/OscPro còn EN (đã flag trong BUG-HVMB4G).

### Acceptance Criteria

- AC-001: PDP configurable (`meridian-modular-sofa.html`, store vi): submit chưa chọn option → không native bubble, message `Vui lòng chọn một trong các tùy chọn.` dưới fieldset Kích thước + Màu sắc, không navigate, form có `novalidate` sau Alpine init.
- AC-002: Qty `0` → message `Vui lòng nhập giá trị lớn hơn hoặc bằng "1".` cạnh qty; qty rỗng → required message VI; không native bubble.
- AC-003: Chọn đủ option + qty 1 → add-to-cart thành công như cũ (product vào cart), console không có JS error mới.
- AC-004: 4 phrase CSV render đúng: `Thấp nhất là:` (label giá tier), `Thông tin thêm` (tab attr), `Sản phẩm liên quan` (heading), và lỗi vượt max qty hiển thị VI (server message) — store en identity sạch.
- AC-005: Simple product (`atlas-pouf.html`): qty validation như AC-002, add-to-cart thành công.

## Root Cause

1. 4 phrase chưa từng được thêm vào theme dict (các đợt translate trước chỉ chạm list/category, my account, flash messages, checkout).
2. Hyvä `swatch-item.phtml` đặt `required` trên radio và `quantity.phtml` dùng `type="number" min="1"` mà form không wire `hyva.formValidation`/`novalidate` → Chrome render native bubble theo locale trình duyệt — bản chất không thể dịch qua CSV.

## Approach

1. Child theme `Magento_Catalog/templates/product/view/product-form.phtml` = bản parent + `x-data="initProductCartForm"` + `@submit="onSubmit"`; `initProductCartForm` = đăng ký 2 custom rule (`validate-number`: bắt `validity.badInput` — bubble "Please enter a number." cũ; `validate-one-required`: nhóm radio — message key `Please select one of the options.` có sẵn) rồi `return hyva.formValidation(this.$el)` (built-in `onSubmit`: validate → `$el.submit()`); `HyvaCsp::registerInlineScript()`.
2. Child theme `Magento_Swatches/templates/product/swatch-item.phtml` = bản parent, radio: bỏ `required`, thêm `data-validate='{"validate-one-required": true}'` + `data-validation-container="#attribute-messages-<attributeId>"`.
3. Child theme `Magento_Swatches/templates/product/view/renderer.phtml` = bản parent, thêm `<div id="attribute-messages-<attributeId>"></div>` sau container swatch trong fieldset (message container cho rule radio; container không tồn tại → lib throw — luôn tạo trong cùng fieldset nên luôn tồn tại trên PDP).
4. Child theme `Magento_Catalog/templates/product/view/quantity.phtml` = bản parent, thêm `required` + `data-validate='{"validate-number": true}'` trên input qty.
5. CSV: +5 phrase/file (As low as / More Information / Related Products / The requested qty exceeds… / Please enter a number.) — vi + en identity, chèn đúng vị trí sort.
6. Verify: cache:flush → curl vi/en (marker + phrase trong HTML) → Playwright runtime (novalidate, message VI/EN, không navigate, add-to-cart thành công, console sạch) → framework-level dictionary check theo memory `magento-cli-phrase-verify`.

## Test Plan & Evidence

- curl vi/en PDP configurable + simple: assert phrase + `data-validation-container` + `x-data="initProductCartForm"`.
- Playwright headless (pattern BUG-5S2Z25): AC-001→AC-003 + AC-005 cho cả 2 store; evidence `.ai/evidence/BUG-KFJ49A/`.
- AC-004 lỗi max-qty server message: repro browser cần cart đạt max (QC); AI verify framework-level key tồn tại trong dict vi/en.
- QC manual: badInput qty (`e`/`-` — Chrome không cho gõ chữ vào number input qua bàn phím thông thường), cart configure popup.

## Notes

- Danh sách store-data nhóm 3 truyền PM/Admin: mô tả `sofa-meridian` chứa `bouclÃ©`/`â€"` (double-encoding, sửa tại admin product editor per store view); breadcrumb `Living Room`; option labels `2 Seat`…`Ottoman`, `Black`…; attr values `Cognac`, `Leather`, `55 Cm`.
- Wording VI đề xuất chờ TL duyệt như các đợt translate trước.

## Implementation (done 2026-09-09)

- `app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/view/product-form.phtml` (mới): form add-to-cart + `x-data="initProductCartForm"` + `@submit="onSubmit"`; script đăng ký 2 custom rule (`validate-number` bắt `validity.badInput`; `validate-one-required` cho nhóm radio super_attribute) rồi `return hyva.formValidation(this.$el)` (built-in `onSubmit`: preventDefault → validate → `$el.submit()` không fire submit event — không loop); `HyvaCsp::registerInlineScript()`.
- `Magento_Swatches/templates/product/swatch-item.phtml` (mới): radio bỏ `required`, thêm `data-validate='{"validate-one-required": true}'` + `data-validation-container="#attribute-messages-<attributeId>"`.
- `Magento_Swatches/templates/product/view/renderer.phtml` (mới): div `#attribute-messages-<attributeId>` sau container swatch trong fieldset (message container; lib throw nếu selector không tồn tại → container render cùng fieldset nên luôn có trên PDP).
- `Magento_Catalog/templates/product/view/quantity.phtml` (mới): input qty thêm `required` + `data-validate='{"validate-number": true}'`; attr native min/max/step/pattern giữ nguyên (lib tự map rule).
- CSV `vi_VN.csv` + `en_US.csv`: +5 phrase/file (As low as, More Information, Related Products, The requested qty exceeds…, Please enter a number.) — 897/850 dòng, en identity sạch (regex check 0 ký tự VI).
- **Không có thay đổi layout** (gotcha BUG-5S2Z25). Mỗi override = bản copy parent + diff tối thiểu (đã `php -l` + diff-vs-parent từng file).

## Implementation đợt 2 — fix UI message qty (2026-09-09, sau QC screenshot)

QC feedback: message lỗi qty render **ép vào cột hẹp giữa giá và button**, wrap 2 dòng; input qty
lệch 4px so với button; row giá giãn 42→86px khi message xuất hiện.

**Root cause:** qty input không có `data-validation-container` → `hyva.formValidation` tự tạo
wrapper `<div class="field field-reserved">` quanh input NGAY TRONG flex row
(`div.flex.gap-2` của product-info.phtml): (1) `.field{margin-top:var(--spacing)}` đẩy input xuống
4px so với button; (2) wrapper thành flex item → message bị giới hạn width cột qty, wrap;
(3) row giãn theo message.

**Fix (cùng pattern message container đã dùng cho radio swatch):**
- `Mageplaza_ExtraFee/templates/hyva/product/view/product-info.phtml` (override có sẵn từ
  SLP-109): render `<div id="qty-messages-<?= (int)$product->getId() ?>"></div>` NGAY DƯỚI row
  giá/qty/button (điều kiện `isSaleable()` — trùng điều kiện render qty). Deviation khỏi nguyên
  tắc "verbatim copy" của file này đã ghi chú trong header comment.
- `Magento_Catalog/templates/product/view/quantity.phtml`: thêm
  `data-validation-container="#qty-messages-<productId>"` trên input → lib không còn tạo
  runtime wrapper; message render dưới nguyên row, full width, input thẳng hàng button.
- An toàn context: quantity.phtml chỉ được dùng bởi block `product.info.quantity` — con của
  product-info.phtml (duy nhất trên `catalog_product_view`, gồm cart configure + wishlist
  configure) → container luôn tồn tại khi input qty có mặt; lib không bao giờ rơi vào
  `containerNotFound`.

## Verification Evidence (2026-09-09 — `.ai/evidence/BUG-KFJ49A/`)

**Framework (curl vi/en sau cache:flush):** marker template đủ cả 2 store; 0 radio còn `required` standalone; form không `novalidate` tĩnh; 4 phrase render đúng vi/en; 2 message rule VI trong emitted JS (`\u`-decoded) + EN identity; en không leak VI.

**Runtime (Playwright — `verify-pdp-validation.js`):** **13/13 PASS** — bảng chi tiết trong `.ai/evidence/BUG-KFJ49A/RESULTS.md`. Điểm chính: message radio VI/EN render dưới đúng 2 fieldset (screenshot `evidence-pdp-invalid-full.png`, layout không vỡ); qty 0/rỗng/badInput đều có message VI/EN; add-to-cart hợp lệ submit bình thường (redirect `/onestepcheckout/`).

**Quan sát pre-existing (flag TL/QC, không thuộc change set):** console error PDP từ `Mageplaza_ExtraFee/templates/hyva/product/view/extra-fee.phtml:53` (fetch fail trên local, có từ task trước); category page 500 (OpenSearch — Blocked từ 08-25); attribute labels/option names/mojibake mô tả = store data → Admin.

**Đợt 2 (2026-09-09) — verify sau fix UI message qty (Playwright, `verify-round2-ui-fix.js`): 15/15 PASS.**
- UI-01→04: KHÔNG còn runtime wrapper quanh qty; input thẳng hàng button (delta=0); row giữ h≈44; message render trong `#qty-messages-<id>` dưới row, full width (539px, 1 dòng — trước: 250px wrap 2 dòng).
- Regression: message radio ×2 fieldset VI/EN như cũ; qty 0/rỗng → min/required VI; en identity; add-to-cart hợp lệ → success message + item vào cart (assert `/checkout/cart`); console không pageerror.
- Screenshot A/B: `evidence-qty-row-before-fix.png` / `evidence-qty-row-after-fix.png`.
- **Quan sát pre-existing MỚI (chứng minh không do change set):** message session "Khóa biểu mẫu không hợp lệ. Vui lòng tải lại trang." xuất hiện trên PDP **kể khi không submit lần nào** (chỉ load PDP → AJAX `mpextrafee/product/extrafee` trả 302 → message lỗi vào session, hiển thị ở lần load full-page kế tiếp). Cùng nhóm pre-existing với console error extra-fee ở trên. Redirect sau add-to-cart về product page (uenc) = Magento default (config redirect không set), không phải regression.

**Chuyển QC:** repro max-qty server message trên browser; cart configure popup; badInput bàn phím thật.

---

## Đợt 3 — upsell heading PDP "We found other products you might like!" (2026-09-16, QC screenshot demo)

### Điều tra (chỉ định scope, không suy đoán)

Screenshot QC demo chỉ 1 chuỗi EN trên PDP: heading block upsell. Trace nguồn: PDP Hyvä **không** dùng vendor `items.phtml` cho upsell — block `upsell` (Hyvä `Magento_Catalog/layout/catalog_product_view.xml:163`) render template `Magento_Catalog::product/slider/product-slider.phtml` với **title = layout argument `translate="true"`** (`:167`) — cùng mechanism với block `related` kề bên (`Related Products`, đợt 1 đã fix qua CSV). Vậy fix = **1 key dict, không override template/layout**.

Phrases cùng khu vực đã inventory: `Press to skip carousel`, `View more about %1` (product-slider.phtml) — có sẵn dict. `select all` / `Check items to add to the cart or` — chỉ trong `items.phtml` Luma (section related) → không render PDP Hyvä → out. `More Choices:` (crosssell cart page) → out scope PDP, flag follow-up. Tên sản phẩm "Living Room Essentials Bundle" = store data (nhóm 3 cũ).

### Mini-spec đợt 3

- **Goal**: heading upsell PDP render VI/EN theo store qua theme dict (BR-001); 0 thay đổi template/layout/JS.
- **AC-006**: PDP `meridian-modular-sofa.html` store vi — heading upsell = `Chúng tôi tìm thấy một số sản phẩm khác bạn có thể thích!` (không còn EN); store en — identity EN (sau khi khôi phục locale store 2 — flag TASK-K14RVZ); `Related Products` (đợt 1) không regression.
- **Out of scope**: `More Choices:` (cart crosssell — cần chốt template Hyvä cart dùng gì, đề xuất tách ticket), `select all`/`Check items…` (Luma-only), store data.

### Implementation (2026-09-16)

- `i18n/vi_VN.csv` + `i18n/en_US.csv`: **+1 key/file** — `"We found other products you might like!"` → vi `Chúng tôi tìm thấy một số sản phẩm khác bạn có thể thích!` / en identity; chèn sau `"Related Products"` (dòng 49 — cùng nhóm PDP-slider đợt 1). Wording VI chờ TL duyệt như các đợt trước.

### Verification (evidence: `.ai/evidence/BUG-KFJ49A/RESULTS.md` mục 12–15)

- HTTP vi store (pre `pdp-vi-prefix-dot3.html` / post `pdp-vi-post-dot3.html`): pre = heading EN đúng vị trí; post = heading VI ×1 + 0×EN; regression `Sản phẩm liên quan` sạch. Sau `cache:flush` as secomm.
- Dict file-level: `en_US.csv` 877 rows parse sạch — key present, identity EXACT, 0 VI-char leak toàn file; `vi_VN.csv` 927 rows — key → VI đúng (method "en identity" của đợt 1).
- CLI dict per pattern SLP-225 (explicit theme `frontend/Secomm/launchpad` + `$translate->setLocale()`, script `verify-phrase-dot3.php`): vi_VN — dict **1952** entries, key → VI + Phrase render VI, control đợt 1 PASS; en_US — dict **124** entries (module packs; **theme `en_US.csv` không load trong CLI** — observation pre-existing, output-neutral với row identity vì fallback = identity) → render fallback identity EN đúng.
- **Trap ghi nhận (tinh chỉnh memory `magento-cli-phrase-verify`)**: setLocale phải gọi trên **object Translate**, KHÔNG phải `Locale\ResolverInterface` (gọi nhải chỗ → dict vẫn theo locale cũ); emulation thuần không đủ — phải setDesignTheme explicit (SLP-225) nếu không dict rỗng (store 1 miss cả control key).

### Ghi nhận (pre-existing, không thuộc change set)

- store 2 `launchpad_en` locale = vi_VN — đã flag TASK-K14RVZ (2026-09-16) chờ khôi phục `general/locale/code` store 2 (TL/DevOps); vì vậy live-en identity chuyển QC demo sau deploy.
- Demo = HEAD: bằng chứng pre-deploy = screenshot ticket; QC demo sau commit + deploy (vi + en).
