# QC Test Cases — TASK-88NDV5 (Hyvä AddressDropdown module) + TASK-FMAN1B (Launchpad OSC)

> ⚠️ **LEGACY (Phase 1a):** TASK-88NDV5 portion (sections A–E) superseded by [`records/features/FEAT-YVN39K.md`](../records/features/FEAT-YVN39K.md) (§Test Summary). TASK-FMAN1B portion (sections A–D) not yet consolidated. Read-only; excluded from default context loading. Do not edit TASK-88NDV5 portion — update FEAT-YVN39K instead.

> Stack: Magento 2.4.8-p5 + Hyvä 3.x (theme `Secomm/launchpad`). VN data = **3 cấp** (country → region/tỉnh-thành phố → city/phường-xã; sub_city rỗng, auto-hide).
> Business rules: BR-001 (i18n vi/en), BR-002 (hierarchical cascade), BR-004 (Mageplaza OSC), BR-006 (ExtraFee + DeliveryTime).
> **Status note**: TASK-88NDV5 customer-form + i18n + cleanup = implemented (testable now). TASK-88NDV5 cart (AC-005) + resolver (AC-011) = **BLOCKED** (Step 4 / Q1 deferred). TASK-FMAN1B = **not yet implemented** (forward-looking, contingent on OSC research).

## Environment / Precondition (common)
- Theme `Secomm/launchpad` active; `Secomm_AddressDropdown` enabled (`address/general/enable = Yes`); `Secomm_VietNamAddress` enabled.
- VN data imported via `InstallVietNamAddressPatch` (`VN_Address_2Level.csv`): regions (tỉnh/thành phố) + cities (phường/xã); sub_city table empty.
- Store locales vi_VN (primary) + en_US configured.
- Test customer account with ≥1 saved VN address.

---

# TASK-88NDV5 — Hyvä-native AddressDropdown (MODULE layer)

## A. Customer address form — cascade (AC-001, BR-002)

### TASK-88NDV5-TC-001: Tạo address mới — cascade VN 3 cấp
- **Precondition**: Logged-in customer; Customer → Address Book → Add New Address; locale vi_VN.
- **Steps**:
  1. Chọn Country = Vietnam
  2. Chọn region (VD: Thành phố Hà Nội)
  3. Quan sát city dropdown; chọn phường/xã (VD: Phường Hoàn Kiếm)
- **Expected**: Region→city cascade load qua GraphQL (`GetListCity`); city options = phường/xã; **sub_city dropdown ẩn** (3 cấp); Save thành công.
- **Priority**: High | **Type**: Functional

### TASK-88NDV5-TC-002: Edit existing VN address — pre-fill đúng
- **Precondition**: Customer có 1 saved VN address (region + city/phường đã lưu).
- **Steps**: 1. Mở edit form address đó 2. Quan sát region + city pre-filled
- **Expected**: Region select = saved region; city dropdown tự load options + chọn saved phường/xã; không bị reset/trống.
- **Priority**: High | **Type**: Edge Case

### TASK-88NDV5-TC-003: Save persist đúng data (Tier 2 — customer data)
- **Precondition**: TC-001 done.
- **Steps**: 1. Save address với city = "Phường Hoàn Kiếm" 2. Kiểm tra DB `customer_address_entity`
- **Expected**: `city` = commune name; `sub_city` = NULL/empty; address hiển thị lại đúng ở Address Book + checkout.
- **Priority**: High | **Type**: Integration / Data Integrity

### TASK-88NDV5-TC-004: Đổi region → city reset
- **Steps**: TC-001 xong → đổi sang region khác
- **Expected**: City dropdown reload theo region mới; city/sub_city selection reset; sub_city vẫn ẩn.
- **Priority**: Medium | **Type**: Functional

### TASK-88NDV5-TC-005: GraphQL lỗi — fail gracefully
- **Precondition**: Mock/-block `/graphql` (VD: tắt network hoặc corrupt response)
- **Steps**: 1. Mở form 2. Chọn region
- **Expected**: City dropdown rỗng (không crash JS, không infinite loader); console log error; form vẫn submit được các field khác.
- **Priority**: Medium | **Type**: Negative / Integration

## B. i18n — generic vs VN module (AC-007, BR-001)

### TASK-88NDV5-TC-006: Label vi_VN đúng 3 cấp
- **Precondition**: locale vi_VN.
- **Steps**: Mở customer address form
- **Expected**: Region label = "Tỉnh/Thành phố"; city label = "Phường/Xã"; placeholder city = "Vui lòng chọn Phường/Xã"; region placeholder = "Vui lòng chọn Tỉnh/Thành phố.".
- **Priority**: High | **Type**: Business Rule (BR-001)

### TASK-88NDV5-TC-007: Label en_US đúng (VN market)
- **Precondition**: locale en_US; `Secomm_VietNamAddress` enabled.
- **Steps**: Mở customer address form
- **Expected**: Region label = "Province/City"; city label = "Ward/Commune"; placeholder city = "Select Ward/Commune".
- **Priority**: High | **Type**: Business Rule (BR-001)

### TASK-88NDV5-TC-008: Generic module không define storefront label (no conflict)
- **Steps**: 1. `grep -nE "^(City|Sub-City|State/Province|Please select a (city|sub-city))," app/code/Secomm/AddressDropdown/i18n/en_US.csv`
- **Expected**: 0 match (chỉ `Secomm_VietNamAddress` define → không load-order conflict).
- **Priority**: Medium | **Type**: Regression / Structural

### TASK-88NDV5-TC-009: vi placeholder KHÔNG phải "Quận/Huyện" (fix district bug)
- **Steps**: `grep "Please select a city" app/code/Secomm/VietNamAddress/i18n/vi_VN.csv`
- **Expected**: Value = "Vui lòng chọn Phường/Xã" (KHÔNG phải "Chọn Quận/Huyện" — district đã bị bỏ).
- **Priority**: Medium | **Type**: Business Rule / Regression

## C. Structure / cleanup (AC-006, AC-010, AC-012, AC-008)

### TASK-88NDV5-TC-010: No RequireJS/Knockout leak trên storefront (customer form)
- **Steps**: 1. Mở customer address form 2. View page source / Network → check JS loaded
- **Expected**: Không load `Secomm_AddressDropdown/js/*` (RequireJS) hay Knockout template của module trên form page; form render bằng Alpine + Magewire pattern.
- **Priority**: High | **Type**: Functional / Structural (AC-006)

### TASK-88NDV5-TC-011: Dead reference Ahamave gone
- **Steps**: `grep -riE "ahamove" app/code/Secomm/AddressDropdown` ; `bin/magento setup:static-content:deploy` (build OK)
- **Expected**: 0 hit Ahamove; build không error; file `default-mixin.js` orphan + `address-dropdown.js` đã xóa.
- **Priority**: Medium | **Type**: Regression (AC-010)

### TASK-88NDV5-TC-012: Default/Luma checkout code dormant — no leak
- **Precondition**: Store dùng Mageplaza OSC (không default checkout).
- **Steps**: Duyệt storefront các page (home, product, cart, customer)
- **Expected**: Default-checkout Knockout mixins của module không render/leak RequireJS lên storefront (Hyva không load `requirejs-config.js`).
- **Priority**: Medium | **Type**: Functional (AC-012)

### TASK-88NDV5-TC-013: Tailwind classes render (no purge)
- **Steps**: 1. `npm run build` trong `app/design/frontend/Secomm/launchpad/web/tailwind/` 2. Mở form, inspect city/region select
- **Expected**: Build pass; dropdown style = Tailwind (`form-select`, grid…); KHÔNG tạo `tailwind.config.js`.
- **Priority**: Medium | **Type**: Functional (AC-008)

## D. Admin regression (AC-009)

### TASK-88NDV5-TC-014: Admin CRUD City/Region/SubCity nguyên vẹn
- **Precondition**: Admin login.
- **Steps**: 1. Secomm → City/Region/SubCity grid 2. Add/Edit/Delete 1 record mỗi loại 3. CSV import/export sample
- **Expected**: CRUD + import/export hoạt động như trước; admin labels không vỡ (chấp nhận "City"→"Ward/Commune"/"Phường/Xã" do global i18n — verify layout OK).
- **Priority**: High | **Type**: Regression (AC-009)

## E. BLOCKED — pending implementation

### TASK-88NDV5-TC-015: ⛔ Cart shipping estimation cascade (AC-005)
- **Status**: BLOCKED — Step 4 chưa implement (cart vẫn dùng Knockout mixin cũ).
- **When unblocked**: cart page → chọn VN address → estimate cập nhật; same 3-tier + generic/VN i18n pattern.

### TASK-88NDV5-TC-016: ⛔ Resolver cache / N+1 (AC-011)
- **Status**: BLOCKED — Step 1 (Q1 Tier 2 SA) chưa chốt.
- **When unblocked**: cascade GraphQL có cache; không N+1 trên critical path.

---

# TASK-FMAN1B — Launchpad OSC Address Integration (forward-looking)

> Precondition khi test: TASK-FMAN1B đã implement + TASK-88NDV5 done (module expose interface). OSC = Mageplaza OSC (Knockout qua compat `hyva-themes/magento2-luma-checkout`).

## A. OSC cascade (AC-002, AC-003, BR-002, BR-004)

### TASK-FMAN1B-TC-001: OSC shipping — cascade VN 3 cấp
- **Precondition**: Cart có product; vào OSC checkout; locale vi_VN.
- **Steps**: 1. Shipping form: Country=VN 2. Chọn region 3. Chọn city (phường/xã)
- **Expected**: Cascade render Hyva-native trong OSC shipping region; sub_city ẩn; "set shipping information" thành công → shipping methods + Mageplaza TableRate load đúng.
- **Priority**: High | **Type**: Functional / Integration

### TASK-FMAN1B-TC-002: OSC billing — cascade + persist
- **Steps**: 1. OSC billing form: nhập VN address cascade 2. Place order
- **Expected**: Billing cascade hoạt động; billing address persist đúng vào order; `quote_address`/`sales_order_address` city = commune.
- **Priority**: High | **Type**: Functional / Data Integrity (Tier 2)

### TASK-FMAN1B-TC-003: Edit shipping address trong OSC
- **Steps**: TC-001 xong → đổi region/city
- **Expected**: Cascade reload; shipping quote update; không lỗi render Knockout↔Hyva seam.
- **Priority**: Medium | **Type**: Edge Case

## B. Regression — checkout totals/payment (AC-004, BR-006, BR-003)

### TASK-FMAN1B-TC-004: ExtraFee + DeliveryTime không regression
- **Precondition**: ExtraFee + DeliveryTime enabled (BR-006).
- **Steps**: 1. OSC checkout với VN address 2. Quan sát totals + DeliveryTime selector
- **Expected**: ExtraFee hiện đúng trong totals; DeliveryTime chọn + persist; không bị dropdown address làm vỡ.
- **Priority**: High | **Type**: Business Rule (BR-006) / Regression

### TASK-FMAN1B-TC-005: Mollie payment — place order thành công
- **Precondition**: Mollie active (BR-003).
- **Steps**: 1. OSC checkout đầy đủ VN address 2. Chọn Mollie 3. Place order
- **Expected**: Redirect Mollie OK; quay về thank-you page; order state `processing` sau webhook; address VN đúng.
- **Priority**: High | **Type**: Integration / Tier 2

## C. Boundary / architecture (AC-O1, AC-O2, DEC-8)

### TASK-FMAN1B-TC-006: Không coupling OSC trong module (DEC-8)
- **Steps**: `grep -riE "Mageplaza_Osc|Osc" app/code/Secomm/AddressDropdown`
- **Expected**: 0 hit trong module generic; toàn bộ OSC code nằm trong `app/design/frontend/Secomm/launchpad/` (Launchpad package).
- **Priority**: High | **Type**: Structural / Business Rule (DEC-8)

### TASK-FMAN1B-TC-007: Không edit Mageplaza in-place
- **Steps**: `git diff app/code/Mageplaza/Osc` (so với upstream)
- **Expected**: Không có thay đổi in-place; integration qua theme override/plugin/preference.
- **Priority**: High | **Type**: Structural (AC-O2)

### TASK-FMAN1B-TC-008: OSC i18n vi/en (AC-O3)
- **Steps**: OSC checkout ở vi_VN rồi en_US
- **Expected**: Label address field đúng (Province/City / Ward/Commune ở en; Tỉnh/Thành phố / Phường/Xã ở vi) — dùng cùng generic key + VN module dict.
- **Priority**: Medium | **Type**: Business Rule (BR-001)

## D. Integration / seam

### TASK-FMAN1B-TC-009: OSC submit mechanism — totals sync đúng
- **Steps**: 1. Chọn VN shipping address 2. Change city 3. Quan sát shipping methods + totals
- **Expected**: Magewire/Alpine sync address field với OSC set-shipping-information; totals + shipping rates update đúng; không double-submit/lỗi.
- **Priority**: High | **Type**: Integration (OSC seam — Q2/Q3)

### TASK-FMAN1B-TC-010: End-to-end checkout VN address → order
- **Steps**: Full flow: add cart → OSC → VN address cascade → shipping + payment → place order
- **Expected**: Order tạo thành công; address VN (region + phường/xã) đúng ở admin order detail + email; no regression checkout.
- **Priority**: High | **Type**: End-to-End / Tier 2

---

## Coverage checklist
- [x] Mỗi AC implemented (TASK-88NDV5: 001/005/006/007/008/009/010/011/012) có ≥1 TC (005/011 = BLOCKED)
- [x] Edge: edit existing (TC-002), region change (TC-004), GraphQL error (TC-005), empty/non-VN
- [x] Negative: GraphQL fail (TC-005), required validation (implicit TC-001)
- [x] Business rules: BR-001 (TC-006/007/008/009/SL2-008), BR-002 (TC-001/SL2-001), BR-004 (SL2 all), BR-006 (SL2-004)
- [x] Integration: GraphQL (TC-005), save persist DB (TC-003/SL2-002), OSC seam (SL2-009)
- [x] Multi-language vi/en (TC-006/007/SL2-008)
- [ ] Mobile/responsive: add nếu QC yêu cầu (Hyva responsive mặc định)

## Escalation flags
- **TASK-FMAN1B TC-009 (OSC seam)**: phụ thuộc research Q2/Q3 (OSC submit mechanism) — chưa chốt → TC có thể thay đổi sau research.
- **Global `City`→"Ward/Commune" trên admin** (TC-014): verify layout admin không vỡ; nếu không chấp nhận → đổi sang key specific (trade-off non-VN label).
- **Cart (TASK-88NDV5-TC-015) + resolver (TC-016)**: BLOCKED — chạy sau Step 4 + Q1.
- Tier 2 (customer data + checkout) → **TL review + QC L3** bắt buộc trước sign-off.
