# TASK-FMAN1B — Launchpad OSC Address Integration (Mageplaza OSC)

**Legacy ID:** SL-002 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (project-specific integration) — Feature
**Priority:** High
**Estimate:** ~16–32h (sau research OSC seam — SA/TL confirm)
**Mode:** A
**Spec:** [launchpad-osc-address.md](../specs/SPEC-TASK-FMAN1B-launchpad-osc-address.md) (backfilled 2026-08-18 — draft; giữ TBD research points)
**Specification:** [../specs/SPEC-TASK-FMAN1B-launchpad-osc-address.md](../specs/SPEC-TASK-FMAN1B-launchpad-osc-address.md)
**Decisions:** [DEC-7](../project-context/memory/DECISIONS.md) (Strategy B) · [DEC-8](../project-context/memory/DECISIONS.md) (module/Launchpad boundary) · [DEC-TASKFMAN1B-001](../records/decisions/DEC-TASKFMAN1B-001.md) (Launchpad_Osc module separation 2026-08-28)
**Depends on:** [TASK-88NDV5](TASK-88NDV5-apply-hyva-theme-addressdropdown.md) (module phải expose Hyvä address component/dữ liệu)
**Approval:** ✅ Approved 2026-07-16 (user as SA/TL) — AC testable; **caveat**: plan contingent on OSC seam research (state 3)
**Author:** AI draft · **Date:** 2026-07-16 · **Risk tier:** Tier 2 (§12 — Mageplaza OSC checkout)

## Description

Tích hợp hierarchical VN address dropdown (Hyvä-native, từ `Secomm_AddressDropdown` module) vào **Mageplaza One Step Checkout** (shipping + billing address) trong **package Launchpad** (project layer: `app/design/frontend/Secomm/launchpad/` + code project-owned). Vì OSC là third-party + choice theo project, integration này **không nằm trong module tái dụng** (DEC-8) — chỉ Launchpad được coupling OSC.

## Acceptance Criteria

- [ ] **AC-002** (OSC — shipping address): customer ở OSC checkout → VN address dropdown render **Hyvä-native** trong OSC shipping form, cascade hoạt động, "set shipping information" thành công → shipping methods + Mageplaza TableRate load đúng. (BR-002, BR-004)
- [ ] **AC-003** (OSC — billing address): tương tự cho billing; billing address persist đúng khi place order. (BR-004)
- [ ] **AC-004** (OSC — no regression): ExtraFee (BR-006) + DeliveryTime (BR-006) + Mollie payment vẫn render + hoạt động — không regression do integration. (BR-003/BR-006)
- [ ] **AC-O1** (Boundary): KHÔNG sửa/thêm code coupling OSC vào module `Secomm_AddressDropdown` — toàn bộ nằm trong Launchpad (theme override `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` hoặc project integration module). (DEC-8)
- [ ] **AC-O2** (No in-place edit Mageplaza): extend Mageplaza OSC qua **theme override/plugin/preference** — KHÔNG edit `app/code/Mageplaza/*` in-place. (04 §Areas to Avoid)
- [ ] **AC-O3** (i18n — BR-001): chuỗi mới có entry `vi_VN.csv` + `en_US.csv`.

## Technical Notes

- OSC hiện là **Knockout-based** (35 template `.html` + `requirejs-config.js`), chạy qua compat `hyva-themes/magento2-luma-checkout` 1.1.7; **dispatch JS events riêng** (không trùng Hyva/Luma).
- **2026-08-26 update**: dependency TASK-88NDV5 giờ được thỏa bởi **schema-driven renderer TASK-3T3NSV** (sau TL review) — component Hyvä `schema-edit.phtml` + GraphQL `addressSchema`/`addressLocations` chính là interface để Launchpad wire vào OSC. QC baseline ghi nhận: OSC legacy đang hiển thị label key cũ ("State/Province"; "City" dịch "Ward/Commune") + City nằm TRÊN Region (core checkout order) — sau integration phải là region → ward theo profile cascade. User prioritization: cart (TASK-YQSS3M) + OSC (task này) trước, admin (TASK-9EX975) defer.
- - **2026-08-28 audit note**: xác nhận mixins legacy là **live path** trong OSC (requirejs mixins + engine `address-dropdown.js` qua `<update handle="checkout_index_index"/>`); mixin sort bằng `localeCompare('vi')` *(correction cùng ngày: ICU `vi` GIỮ Đ là letter riêng sau D — verify PHP intl — nên localeCompare KHÔNG cho Đ==D; xem TASK-YQSS3M findings item 1)* nhưng label hardcode `'Ward/Commune'` (`Secomm_AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js:166,184`, `billing-address-dropdown.js:176`) + data từ `GetListCity`/`GetListSubCity` name-keyed, unsorted. Integration Option A thay vùng này bằng schema cascade — sau integration, các mixin legacy rơi vào scope removal của TASK-K09G8Y (bổ sung làm gate).
- **2026-08-28 quick-fix (legacy path — tạm thời đến khi Option A)**, trigger từ QC user: (1) i18n `Ward/Commune` thiếu key vi (label hiển thị EN giữa form vi) → thêm `"Ward/Commune","Phường/Xã"` vào `Secomm_VietNamAddress/i18n/vi_VN.csv` (module VN adapter — giữ boundary DEC-8, không đưa VN string vào generic module); (2) sort 2 mixin (`shipping-address-dropdown.js`, `billing-address-dropdown.js`) normalize `Đ/đ→D/d` trước `localeCompare` (khớp REPLACE-expression server-side). Đã verify `GetListCity(1158 Bắc Ninh)` trả 99 wards — data path legacy vẫn sống; `cache:clean translate` đã chạy. Option A (schema cascade Alpine thay jQuery inject) vẫn là hướng chính thức — cần implement theo plan (estimate 16–32h).
- **2026-08-28 quick-fix vòng 2** (user QC: label flip EN sau khi chọn tỉnh + sai thứ tự field + "không lấy được address profile"):
  - **Root cause label EN**: `updatePostcodePlaceholderByDom` (shipping :166 / billing :176) gán `$cityLabel.text('Ward/Commune')` **literal** (không qua `$.mage.__`) trong `setInterval` 200ms → ghi đè label đã dịch ngay sau khi chọn region. Fix: `self.wardLabel()`.
  - **Schema labels**: cả 2 mixin thêm `ensureVnSchema()` (`addressSchema(VN)` 1 lần, in-flight guard, `false` = unmapped/failure) + `wardLabel()`/`wardPlaceholder()` (schema trước, i18n fallback) + `applySchemaToDom()` (re-apply khi fetch về sau khi DOM built). Label/placeholder dùng ở setup, region-change reset, `updateCityDropdown`.
  - **Field order**: ward select giờ neo **sau region field** (`$(REGION_SELECTOR).closest('.field').after(cityDiv)`, fallback neo cũ) → thứ tự Quốc gia → Tỉnh/Thành phố → Phường/Xã thay vì ward nằm cạnh Quốc gia.
  - `node --check` PASS cả 2 file; `cache:clean full_page block_html translate` đã chạy. Native city text input khi chưa chọn region (label hack `City,Phường/Xã` vi_VN.csv:35) — giữ nguyên legacy behavior, Option A sẽ thay. *(→ superseded cùng ngày: round 3 xử lý native city trong bản `Launchpad_Osc`, xem dưới.)*
- **2026-08-28 module separation (DEC-TASKFMAN1B-001 — user acting as SA/TL)**: toàn bộ coupling OSC tách sang module project layer **`Launchpad_Osc`** (`app/code/Launchpad/Osc/`) — `Secomm_AddressDropdown` giữ default checkout (user: *"Secomm module không phụ thuộc extension nào"*). Cơ chế: `onestepcheckout_index_index.xml` của module mới ghi đè CÙNG key jsLayout bằng bản copy OSC-tuned của 2 component cascade; thứ tự merge trong `app/etc/config.php` (Mageplaza_Osc 413 < Secomm_AddressDropdown 416 < Launchpad_Osc 440) → đúng 1 instance mỗi form. **Soft coupling** (OSS 2.4.8 không có `soft="true"` — Adobe-only): sequence chỉ xếp thứ tự khi hiện diện + mọi behaviour scope theo handle `onestepcheckout_index_index` (OSC tắt ⇒ module inert) + composer `suggest` + runtime `ModuleManager` gate bắt buộc cho PHP tương lai (Option A). **Round-3 quick fix** nằm trong bản Launchpad_Osc: VN luôn ẩn native City field (gỡ `required`/`aria-required` khi ẩn — tránh Chrome *"not focusable"* block submit; restore cho non-VN) + hiện ward dropdown ngay cả khi chưa chọn region → thứ tự luôn Quốc gia → Tỉnh/Thành phố → Phường/Xã. Bản copy TẠM THỜI — Option A (Alpine cascade, thực hiện trong Launchpad_Osc) thay rồi remove theo TASK-K09G8Y. Đã `module:enable` + `setup:upgrade` + `cache:clean` + static deploy (version `1787918603`).
- **2026-08-28 quick-fix vòng 4** (QC ảnh sau module separation — merge đã verify live: native city biến mất + label schema render qua bản Launchpad_Osc): ward dropdown chưa hiện khi CHƯA chọn region — root cause `initialize` shipping chỉ chạy `setupCitySubCity` khi `$(REGION_SELECTOR).val()` CÓ GIÁ TRỊ (legacy gate) → đổi điều kiện sang `$(REGION_SELECTOR).length` (+ bail sau 120 lần poll), `loadCities` vốn no-op khi region rỗng → ward select (placeholder schema) hiện ngay cả khi chưa chọn region. Áp CẢ 2 bản copy (Launchpad_Osc + AddressDropdown — sync rule DEC-TASKFMAN1B-001). `node --check` PASS; static version `1787920095`. Còn lại (cosmetic, chờ DevTools probe): ô trống cạnh Quốc gia — nghi hidden native city wrapper vẫn chiếm slot (KO `visible` binding đè jQuery hide, hoặc CSS grid đặt chỗ).
- **Research cần làm (state 3):**
  - **OSC seam**: OSC render shipping/billing address form ở component/layout nào (`onestepcheckout_index_index.xml` + `Osc/view/frontend`) → chốt điểm cắm Hyvä/Magewire component.
  - **OSC submit mechanism**: cơ chế set shipping/billing information của OSC → Magewire sync address field mà không break totals/payment.
- Strategy B (DEC-7): address UI Hyvä-native (Alpine/Magewire/.phtml) từ TASK-88NDV5; Launchpad wire vào OSC address region.
- KHÔNG edit Mageplaza in-place — theme override trong `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...`.

## Files/Areas Affected

- `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` — theme override address templates/layout.
- Có thể: project integration code (theme-level Magewire/.phtml) wire module's address component vào OSC.
- i18n `vi_VN.csv` + `en_US.csv`.
- **KHÔNG affect**: `app/code/Secomm/AddressDropdown` (module giữ reusable, DEC-8), `app/code/Mageplaza/*` (in-place edit forbidden).

## Risks

- **Tier 2 (§12)**: Mageplaza OSC checkout flow → escalate SA/TL; **QC end-to-end checkout + payment bắt buộc** (BR-004).
- OSC seam khó/không sạch (Knockout qua compat) → fallback theme override.
- OSC JS events lệch → submit/totals/payment regression (AC-004).

## Open Questions (Level 2 — research state 3)

- ~~**OSC seam**: address form render ở đâu → điểm cắm Hyvä component?~~ **ANSWERED 2026-08-26** (xem Research Findings)
- ~~**OSC submit mechanism**: sync address với set shipping/billing info thế nào?~~ **ANSWERED 2026-08-26**
- Module expose gì cho Launchpad wire → **ANSWERED 2026-08-26**: renderer `schema-edit.phtml` (TASK-3T3NSV) + GraphQL `addressSchema`/`addressLocations` chính là interface; tái dùng logic Alpine cascade.

## Research Findings — OSC seam (state 3, 2026-08-26, read-only)

**Q1 — Address form render ở đâu:**
- Handle `onestepcheckout_index_index` có `<update handle="checkout_index_index"/>` → **tái sử dụng toàn bộ jsLayout checkout chuẩn**, kể cả injection `sub_city` của module (`checkout_index_index.xml` + `Plugin/Checkout/LayoutProcessorPlugin` — OSC block cũng qua `LayoutProcessor::process` nên afterProcess apply). Đây là lý do cascade legacy hiện diện trong OSC mà không đụng Mageplaza.
- Chuỗi render shipping form: `checkout.root` → `Mageplaza_Osc/js/view/shipping.js` (extends `Magento_Checkout/js/view/shipping`) → template `Mageplaza_Osc/container/address/shipping-address.html` → `container/address/shipping/form.html` render region **`additional-fieldsets`** (các field attribute-merger chuẩn + sub_city) qua template OSC (`container/form/field` + `container/form/element/*` khi Material Design; ngược lại dùng template core `${$.$data.template}`). Region element: `Mageplaza_Osc/js/view/form/element/region.js` extends core `Magento_Ui/js/form/element/region`. Billing tương tự (`billing-address.js` + `container/address/billing`).

**Q2 — Submit mechanism:**
- OSC dùng **standard checkout actions**: `Magento_Checkout/js/action/set-shipping-information`, `address-converter`, `select-billing/shipping-address` + OSC-specific: `set-checkout-information`, `payment-total-information`, `reload-order-summary`, `reload-payment-method`, `model/shipping-rate-service`. Data contract = **standard quote shippingAddress/billingAddress model** (city / region_id / custom_attributes).
- Cascade legacy gắn qua **requirejs mixins** (`Secomm_AddressDropdown/view/frontend/requirejs-config.js`): `set-shipping-information-mixin`, `set-billing-information-mixin`, `shipping-save-processor/default-mixin`, `view/shipping-mixin`, `billing-address-mixin`, address-renderer mixins + engine `js/address-dropdown.js` — apply vì OSC extend cùng component chuẩn.

**Seam khuyến nghị (Option A — vào plan Mode A):**
- Theme override `Mageplaza_Osc/container/address/shipping/form.html` (+ billing tương tự) trong `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` — thay vùng `additional-fieldsets` bằng Alpine schema cascade (reuse logic từ `schema-edit.phtml`), **sync selection vào input ẩn bound KO** (city / region_id / custom_attributes) → giữ nguyên submit contract, validators, OSC rates/payment reload. Không sửa Mageplaza in-place (AC-O2 ✓), không cần Magewire bridge.
- Option B (full Alpine form + bridge `setShippingInformation`) — invasive hơn, rủi ro rates/payment reload; chỉ chọn nếu A bó hẹp.
- Lưu ý plan: D2 hidden fields (`address_city_id`, `address_location_path`) cần đi vào `custom_attributes` qua mixin payload (mở rộng mixin `set-shipping-information-mixin` hiện có). Label + thứ tự field theo profile giải quyết cả 2 QC baseline issue (label cũ + City-trên-Region).

## Dependencies

- **TASK-88NDV5** (module Hyvä address component — phải hoàn thành/tr expose interface trước).
- Mageplaza OSC (`Osc/OscPro/OscUltimate`), `hyva-themes/magento2-luma-checkout` 1.1.7 (compat).
- Magewire 1.13, core Magento GraphQL (Country/Region) + module GraphQL (City/SubCity).

## Definition of Done

- [ ] Code complete + matches approved plan
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] Tests pass + Hyvä build OK
- [ ] **QC end-to-end checkout**: OSC VN address dropdown (shipping+billing) + place order Mollie + ExtraFee/DeliveryTime không regression (Mode A)
- [ ] Evidence `.ai/evidence/TASK-FMAN1B/`
- [ ] project-context updated

## Implementation Notes — quick-fix rounds (bản copy Launchpad_Osc, temporary)

### Round 4 — VN row layout (2026-09-03, user direction; Mini-Spec embedded per DEC-TASKZ132WA-001)

- **Goal**: OSC address form (shipping + billing) flow VN: `[Quốc gia | Tỉnh/Thành phố]` → `[Phường/Xã]` → `[Mã Zip/Bưu Chính]` → `[Công ty | SĐT]`.
- **Expected Behavior**: (1) move `.field` của `region_id` ra ngay sau `.field` country (2 field `mp-6` chung hàng) trong `setupCitySubCity`, trước khi anchor ward; (2) toggle `mp-clear` (new-row marker, `Mageplaza_Core grid-mageplaza.css:91`) trên wrapper postcode trong `cityVisible`: VN = add, non-VN = remove (giữ cặp native `[City | Postcode]`).
- **Constraints / Rules**: chỉ bản copy Launchpad_Osc (DEC-TASKFMAN1B-001, AC-O2 — không edit Mageplaza in-place); temporary đến Option A; giữ nguyên behavior rounds 1–3; không string mới.
- **Out of Scope**: Option A cascade; OSC admin config `osc/field/position` (default JSON tại `Mageplaza_Osc/etc/config.xml:77` — chưa override DB); billing sub-city width; cart/customer form.
- **AC**: AC-1 VN shipping layout đúng; AC-2 non-VN `[Country | Region] [City | Postcode]`; AC-3 billing mirror; AC-4 cascade prefill edit-mode + validation + submit không đổi.
- **Files**: `app/code/Launchpad/Osc/view/frontend/web/js/action/{shipping,billing}-address-dropdown.js` (+ sync pub/static en_US/vi_VN × Secomm/launchpad + Hyva/default; CHANGELOG Round-4 entry).
- **Known boundary** (pre-existing, shared rounds 1–3): KO re-render của fieldset (vd toggle "New Address" modal) tái tạo native field mà không re-run cascade — expose như hiện trạng, xử lý ở Option A.
- **QC**: verify trên dev flag `schema` (fashion_en/fashion_vi) cùng batch re-QC TASK-3T3NSV.

### Cross-ref — TASK-6MKF0V slice thực thi trong module này (2026-09-03, user direction)

- sub_city đã bị loại khỏi cả 2 bản copy Launchpad_Osc (record + decision: `TASK-6MKF0V` / `DEC-TASK6MKF0V-001`): bỏ sub-city select, `GetListSubCity`, hidden input `sub_city`, sync `custom_attributes[sub_city]`/`extension_attributes.sub_city`; cascade = Country → Region → Ward; rename `setupCitySubCity`→`setupCityCascade`, `initializeCitySubCityElements`→`initializeCityCascadeElements`. Round-4 layout (mp-clear/move region) giữ nguyên. TL review TASK-FMAN1B sẽ thấy phần này trong cùng changeset — rủi ro submit contract nằm dưới TASK-6MKF0V (project mới, 0 data sub_city).
