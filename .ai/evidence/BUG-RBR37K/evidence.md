# Evidence — BUG-RBR37K (SLP-104)

> Fix không tính lại giá shipping sau khi thay đổi address. Initial fix đã commit trong `92af2b325`; follow-up `Launchpad_Osc` ngày 2026-09-15 đang staged trên branch `dev/development/thangpham`.

## 1. Change Under Review

| File | Change |
|---|---|
| `app/code/Launchpad/Osc/view/frontend/web/js/action/shipping-address-dropdown.js` | Follow-up: gom `clearRateCache()`; invalidation cho Street, Country, Region, Ward/City và saved-address selection; clear cả new-address/customer-address keys; Street change force `estimateShippingMethod()` khi address đã đủ |
| `app/code/Secomm/AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js` | City/sub-city handler: update `quote.shippingAddress()` + merge `extension_attributes` → clear `shipping-rate-registry` (`getKey()` + `getCacheKey()`) → `shippingRateService.isAddressChange = true` + `estimateShippingMethod()`; bỏ dead branch `location.hash === '#shipping'` |
| `app/code/Secomm/Ahamove/view/frontend/web/js/model/shipping-rates-validation-rules/ahamove.js` | `postcode.required: true → false` (địa chỉ VN trống postcode) |

## 2. AI Pre-review — 2026-09-03

**Verdict: PASS WITH WARNINGS** (full output: phiên review 2026-09-03, đã report trong chat)

Static verification đã chạy:

- ✅ `estimateShippingMethod`/`isAddressChange` tồn tại trên OSC override `Mageplaza_Osc/view/frontend/web/js/model/shipping-rate-service.js:36-48` (same AMD id `Magento_Checkout/js/model/shipping-rate-service`, map qua requirejs-config) — Magento gốc KHÔNG có 2 method này.
- ✅ `isAddressChange` được OSC tiêu thụ: `Mageplaza/Osc/view/frontend/web/js/view/shipping.js:145-146` — không phải dead code.
- ✅ Cache key semantics: `new-customer-address.js:88-90` — `getCacheKey() = type + identifier` (frozen per instance, không chứa city) → clear sau khi mutate city vẫn invalidate đúng entry stale; POST payload đọc property fresh → city mới được gửi.
- ✅ Module surface: `shipping-address-dropdown.js` chỉ được reference từ `checkout_index_index.xml` → chỉ chạy trên OSC checkout; bỏ nhánh `#shipping` là dead-code removal, cart page không ảnh hưởng.
- ⚠️ Finding W1 (đã verify — xem §3): `sub_city` nằm ở `extension_attributes`, trong khi core payload `new-address.js:26-49` chỉ mang `custom_attributes`.

## 3. Manual Verification — Developer (2026-09-03)

Developer (Victor Pham) đã kiểm tra code và xác nhận hành vi OK sau fix ("đã check code okie hết rồi") — bao gồm Concern W1 (sub_city transport tới estimate) không còn là blocker.

> **Lưu ý**: đây là xác nhận của developer, không phải QC sign-off end-to-end.

## 4. Pending (còn lại trước khi close)

- [ ] Guest checkout: đổi Ward/City, Street, Country/Region → request estimate lại, rate thay đổi đúng
- [ ] Logged-in checkout: chọn saved address khác → customer-address cache không stale
- [ ] Verify Street không request khi thiếu Region/Ward và không duplicate request khi giá trị không đổi
- [ ] AC-005: place order end-to-end sau khi đổi address (Tier 2/L3 checkout QC)
- [ ] TL/SA Tier 2 review (checkout OSC + Secomm_AddressDropdown — AGENTS.md §12)
- [ ] Commit follow-up + PR (reference SLP-104 / BUG-RBR37K, kèm pre-review summary)

## 5. Static Verification — Follow-up 2026-09-15

- ✅ `Mageplaza_Osc/js/model/shipping-rate-service::estimateShippingMethod()` dispatch theo `quote.shippingAddress().getType()` sang new-address/customer-address processor.
- ✅ Magento new-address processor lookup/save cache bằng `address.getCacheKey()`; customer-address processor lookup/save bằng `address.getKey()`.
- ✅ Magento customer address model trả `getKey() = 'customer-address' + customerAddressId`; explicit key trong follow-up khớp contract này.
- ✅ Event Street và saved-address selection được namespace + `off(...).on(...)`, giảm nguy cơ duplicate binding khi component initialize lại.
- ⚠️ Chưa có browser/network evidence cho follow-up; không coi static verification là QC sign-off.
