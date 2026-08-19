# Kế hoạch triển khai: SL-012 — Apply VN 2-level address dropdown trên admin order form

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-012-admin-vn-address-order-form.md |

> Mode A · **Chỉ plan, chưa viết code** (Hard Gate: no code without plan).
> Tier-2 (order + customer/PII data, §12) → cần SA/TL sign-off trước khi implement.
> Parent: [FEAT-007](../records/features/FEAT-007.md). Ticket: [SL-012](../tickets/SL-012-apply-address-dropdown-admin-order-form.md). Spec: [admin-vn-address-order-form](../specs/SPEC-SL-012-admin-vn-address-order-form.md).

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-012](../tickets/SL-012-apply-address-dropdown-admin-order-form.md) |
| Spec | [admin-vn-address-order-form](../specs/SPEC-SL-012-admin-vn-address-order-form.md) |
| Feature | [FEAT-007](../records/features/FEAT-007.md) |
| Author | AI draft |
| Reviewer (TL) | user (acting as SA/TL) — pending approval |
| Workflow Mode | A |
| Date | 2026-08-03 |
| Decisions | DEC-025 · DEC-020 · DEC-019 · DEC-17 |
| Validation level | **L3** (order + PII + admin save path) |

## 1. Hướng tiếp cận

**Reuse-first, admin-order-owned wiring.** Mình sẽ giữ `Secomm_AddressDropdown` ở vai trò generic data/mechanism và đẩy behavior VN vào `Secomm_VietNamAddress` theo DEC-019/025. Với admin order form, không port trực tiếp provider-mixin của customer modal vì surface này là `Magento_Sales` block-based + `window.order` bootstrap, không phải `Magento_Ui/js/form/provider`.

### Chiến lược khuyến nghị

- **Không tạo schema / storage mới.**
- **Không sửa generic `Secomm_AddressDropdown` admin mixin cho task này.**
- **Dùng lại form metadata hiện có** cho `adminhtml_customer_address` nếu order create/edit đã render được cùng field set.
- **Thêm admin JS adapter riêng cho order page** trong `Secomm_VietNamAddress`, gắn vào `sales_order_create_index` và `sales_order_edit_index`, rồi bind vào `window.order` và các field `order[billing_address][...]` / `order[shipping_address][...]`.
- **Same as billing** phải copy cả `region` + `city` (ward), không chỉ region.
- **Display path** ưu tiên reuse `AddressRendererPlugin` + `customer_address_format` event hiện có; chỉ sửa thêm nếu QC chỉ ra gap thật.

### Lý do chọn

- Order create/edit đã có `AdminOrder` global và các field container rõ ràng, nên một adapter JS mỏng sẽ ít rủi ro hơn việc override nặng template/form object.
- `sales_order_edit_index` inherit từ `sales_order_create_index`, nên có thể giữ một luồng adapter chung cho create + edit.
- Display order address đã đi qua `customer_address_format` / renderer pipeline, nên có thể kiểm bằng regression thay vì mở rộng code ngay lập tức.

## 2. Files affected

| File | Loại thay đổi | Lý do / AC |
|------|---------------|------------|
| `app/code/Secomm/VietNamAddress/view/adminhtml/requirejs-config.js` | new | nạp order-adapter JS cho admin order pages |
| `app/code/Secomm/VietNamAddress/view/adminhtml/web/js/order/address-cascade.js` | new | bind `window.order`, country gate VN, load ward dropdown, same-as-billing sync |
| `app/code/Secomm/VietNamAddress/view/adminhtml/layout/sales_order_create_index.xml` | new | inject asset / init hook cho order create page |
| `app/code/Secomm/VietNamAddress/view/adminhtml/layout/sales_order_edit_index.xml` | new | reuse same adapter cho order edit page |
| `app/code/Secomm/VietNamAddress/i18n/vi_VN.csv` | modify | label VN cho ward / validation message |
| `app/code/Secomm/VietNamAddress/i18n/en_US.csv` hoặc file EN hiện có | modify | English fallback cho admin labels |
| `app/code/Secomm/VietNamAddress/Plugin/AdminOrder/...` nếu cần | conditional | chỉ thêm nếu JS không đủ để hydrate/preserve data |
| `app/code/Secomm/AddressDropdown/Plugin/AddressRendererPlugin.php` | giữ nguyên (verify) | display pipeline đã tồn tại; chỉ patch nếu QC thấy gap |
| `app/code/Secomm/AddressDropdown/Observer/Order/Address/SaveSubCity.php` | giữ nguyên (verify) | không muốn chạm nếu order path không overwrite `city` |
| `app/code/Secomm/AddressDropdown/Observer/Quote/Address/SaveSubCity.php` | giữ nguyên (verify) | giống trên; chỉ chạm nếu quote parity bị hỏng |
| `app/code/Secomm/AddressDropdown/Model/Customer/Address/Config/Selector/City.php` | reuse | ward options theo region |

## 3. Steps

### 3.1 Audit baseline và xác nhận entry points

- Xác nhận order create/edit dùng đúng handle `sales_order_create_index` và `sales_order_edit_index`.
- Xác nhận form address hiện tại vẫn render được field set qua `adminhtml_customer_address`.
- Xác nhận field name thực tế trên DOM: `order[billing_address][country_id]`, `order[billing_address][region_id]`, `order[billing_address][city]`, `order[shipping_address][same_as_billing]`.
- Xác nhận display path order address đi qua `Magento\Sales\Model\Order\Address\Renderer` → `customer_address_format`.
- Output mong đợi: baseline note + selector map, không code change.

### 3.2 Xây admin order adapter cho VN cascade

- Tạo JS module riêng trong `Secomm_VietNamAddress` để:
  - phát hiện `window.order` và các fieldset billing/shipping;
  - gate theo `country_id === 'VN'`;
  - load wards theo `region_id` qua GraphQL `GetListCity` hiện có;
  - render ward select và giữ `city` dưới dạng ward name;
  - clear ward khi đổi region/country;
  - rebind khi block address được refresh bởi order create form.
- Không reuse trực tiếp `Secomm_AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js` vì file đó chỉ phục vụ customer-address modal.
- Nếu order page không nạp đủ field markup từ metadata hiện có, chỉ lúc đó mới thêm một patch mỏng để inject field vào form object, không override toàn template ngay.

### 3.3 Đồng bộ same-as-billing và preselect khi edit

- Hook vào luồng `order.setShippingAsBilling(true/false)` để copy đủ `region`, `region_id`, `region_code`, `city`, `country_id`.
- Khi edit order address, hydrate lại ward option list trước rồi mới set selected ward để tránh reset giá trị.
- Với non-VN, giữ Magento default và không ép ward dropdown.

### 3.4 Persist path và parity

- Xác nhận `sales_order_address_save_before` và `sales_quote_address_save_before` không ghi đè `city` khi save từ admin.
- Nếu admin save path làm rơi `city`, thêm patch tối thiểu ở observer/service layer để bảo toàn raw ward value.
- Không thêm logic mới nếu current save path đã giữ nguyên `city`; ở task này ưu tiên verify rồi mới sửa.

### 3.5 Display verification

- Verify order view, PDF, email và frontend order detail dùng renderer hiện có để show region + ward.
- Nếu renderer không show ward cho một path cụ thể, patch đúng formatter/renderer layer đó, không copy lại logic ở nhiều nơi.

### 3.6 Tests, QC, evidence

- Viết/điều chỉnh test theo các điểm save/display quan trọng.
- Chạy QC manual L3 trên admin order create/edit.
- Ghi evidence vào `.ai/runtime/evidence/FEAT-007/` hoặc folder tương ứng của SL-012.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Admin order form không bind đúng vào `window.order` / `AdminOrder` | high | Tách adapter riêng, test trên create và edit; không port provider-mixin customer modal |
| "Same as billing" copy làm mất ward | high | Test riêng flow same-as-billing, copy đủ `city` + `region` fields |
| Edit path reset selected ward sau reload | medium | Load options trước, set selected sau; rebind sau fragment refresh |
| Persist path ghi đè `city` hoặc `region` | high | Verify admin save controller + observers trước khi patch |
| Display path không qua renderer chuẩn | medium | Reuse `customer_address_format`; patch đúng layer nếu thiếu |
| Tier-2 order/PII data sai | high | TL review + L3 QC bắt buộc |

## 5. Test approach

- **Admin Order Create - billing:** `country = VN` → render province → ward; submit giữ `order[billing_address][city]` là ward name.
- **Admin Order Create - shipping:** same-as-billing copy giữ ward.
- **Admin Order Edit:** preselect region + ward đúng từ data đã lưu.
- **Non-VN:** default Magento behavior, không leak VN UI.
- **Persist:** verify `sales_order_address.city` và parity `sales_quote_address`.
- **Display:** order view, PDF, email, frontend order detail show region + ward.
- **Regression:** checkout/OSC, customer address book, admin data CRUD, existing order save flow.

## 6. Out of scope

- Customer-address admin flow (`SL-011`).
- Store Information + Shipping Origin (`SL-013`).
- MSI Source (`SL-014`).
- Storefront generic cleanup / VN leak cleanup (`SL-007`).
- Canonical `ward_id` / `city_id` persistence path A.
- Schema changes và bảng mới.
- VNPAY / checkout / payment work.

## 7. Open questions / Escalation

- Q1: Nên bind order adapter bằng asset + `window.order` hay thêm template override mỏng cho `sales_order_create_index`?
- Q2: Có cần patch `sales_order_edit_index` riêng hay chỉ cần inherit từ create handle là đủ sau khi QC?
- Q3: Persist path admin có giữ nguyên `city` trong mọi case không, hay cần observer/service patch tối thiểu?
- **Escalation:** Tier-2 order/PII → SA/TL review bắt buộc trước code.

## Review + Fix (2026-08-04 — code review phát hiện 2 bug)

Review `address-cascade.js` + render form thực tế (PHP bootstrap render order-create billing form). Kết luận:

**Bug 1 (critical — cascade KHÔNG BAO GIỜ chạy):** module dùng `require([...], factory)` ở top-level thay vì `define`. Phtml làm `require(['Secomm_VietNamAddress/js/admin/order/address-cascade'], function(init){ init(); })` nhưng module không `define` → RequireJS export `undefined` → `init()` throw TypeError → `scan()`/`observe()` không bao giờ được gọi → ward dropdown không xuất hiện trên order form. **Fix: `require` → `define`** (line 1). Parse-check OK (factory giờ export `init`).

**Bug 2 (region hydration race — song song SL-011):** order form render `region_id` dưới dạng `<select>` do **Prototype `regionUpdater`** (`mage/adminhtml/form`, KHÔNG phải jQuery region-updater — chỉ tồn tại frontend) quản lý: populate province options + set saved region_id **async**. Ngoài ra "Select from existing customer addresses" (`order.selectAddress`) và "Same as billing" (`order.setShippingAsBilling`) set country/region_id **programmatically** — không dispatch jQuery `change`. Hệ quả: `refreshRoot` đọc `region_id.val()` trước khi hydrate → rỗng → không load ward; edit/pre-fill/same-as-billing không recovery. **Fix: hydration watcher** trong `bindRoot` — poll `(country|region)` key ~30s, re-load ward khi thay đổi, share `lastKey` với change handler để không double-trigger.

**Đã verify (render thực):** order-create billing form có `<select name="order[billing_address][region_id]">` + Prototype `regionUpdater` + VN region JSON (34 tỉnh, region_id 1157–1190). Backend GraphQL `GetListCity(area=adminhtml)` OK (SL-011). `escapeGraphQlValue` đã có sẵn (an toàn injection). 0 VN sales_order_address (cascade chưa từng capture ward cho order).

**Pre-review (§8.3):** 1 file (`address-cascade.js`) · parse OK · no hardcoded URL/cred · error paths có `.catch` · watcher có bound 30s · scope chỉ admin order JS (không chạm storefront/customer form/observer/db). **Chờ QC L3** (create billing+shipping, same-as-billing, edit pre-select, persist `sales_order_address.city`, non-VN fallback) + TL review (Tier-2 order).

### Architecture correction (2026-08-04 — JS error lộ cơ chế thật)

**Phần "Review + Fix" phía trên BỊ THAY THẾ** — chẩn đoán `address-cascade.js` (VietNamAddress) là sai cơ chế. JS error thực tế: `Script error for "Secomm_AddressDropdown/js/admin/order/address-city"`.

**Cơ chế THẬT** (generic, đúng DEC-019) cho city/ward trên admin order form = `Secomm_AddressDropdown`:
- `Plugin/Adminhtml/OrderAddressFormPlugin.php` trên `Magento\Sales\...\Order\Create\Form\Address`: `afterGetFormValues` (hydrate `region_id` từ region text) + `aroundToHtml` (set `Block\Adminhtml\Order\Address\Renderer\City` làm renderer cho city field).
- `Block/.../Renderer/City.php` render `templates/order/address/renderer/city.phtml` (city input + `city_select` + `sub_city`) + `data-mage-init` config (cityInputId/citySelectId/...).
- `web/js/order/address-city.js` = cascade generic hoàn chỉnh (toggle input/select, load ward theo region qua `GetListCity(area=adminhtml)`, pre-select, sub_city, country/region change).

**Bug đã fix:**
1. **Path mismatch** (root cause JS error): `Renderer/City.php:79` require path `Secomm_AddressDropdown/js/admin/order/address-city` nhưng file ở `js/order/address-city.js` → requirejs 404 → script error → cascade không chạy. Fix: path → `js/order/address-city`. Verify serve HTTP 200.
2. **VN cascade redundant** (`Secomm_VietNamAddress/.../address-cascade.js` + phtml + 2 layout XML) là implementation trùng — nếu chạy cùng Renderer sẽ 2 ward select → conflict. Đã xóa 2 layout XML (`sales_order_create_index.xml`, `sales_order_address.xml` trong VietNamAddress) → VN cascade không load trên order page. (File `address-cascade.js`/phtml giờ unused — dọn sau.)
3. **City label thiếu `admin__field-label`** (city.phtml) → lệch styling. Fix: thêm `admin__field-label` cho cả city + sub-city label.
4. **Country change không reset/reload City** (address-city.js đọc region_id đồng bộ = stale vì Prototype regionUpdater update async). Fix: hydration watcher `(country|region_id)` ~5min + clear city trên user change.
5. **State sai default khi đổi country** (vd US→VN hiện "Cần Thơ"): regionUpdater set region_id theo `def` (region text). Fix: trên user country change, reset `region_id`+`region` text → "Please select".

Pre-review: 3 file (`Renderer/City.php`, `city.phtml`, `address-city.js`) + xóa 2 VN layout XML · parse OK · scope chỉ admin order generic · không chạm storefront/customer/observer/db. **Chờ QC L3** (create VN+non-VN, country switch reset, edit pre-select, same-as-billing) + TL review (Tier-2 order).

