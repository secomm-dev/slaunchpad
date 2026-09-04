# Evidence — BUG-RBR37K (SLP-104)

> Fix không tính lại giá shipping sau khi thay đổi address. Branch `dev/development/thangpham`, fix **staged** (chưa commit — AI không commit/push theo AGENTS.md).

## 1. Change Under Review

| File | Change |
|---|---|
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

- [ ] AC-005: place order end-to-end sau khi đổi address (Tier 2 checkout QC)
- [ ] TL/SA Tier 2 review (checkout OSC + Secomm_AddressDropdown — AGENTS.md §12)
- [ ] Commit + PR (reference SLP-104 / BUG-RBR37K, kèm pre-review summary)
