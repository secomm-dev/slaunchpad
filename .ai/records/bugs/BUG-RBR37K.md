---
id: BUG-RBR37K
type: bug
title: "[Ahamove][Checkout] Không tính lại giá shipping sau khi thay đổi address"
project_code: SLP
parent:
external_refs:
  ticket: SLP-104
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-03
updated: 2026-09-03
ticket_ref:
affects_version: Magento 2.4.8-p5 + Mageplaza OSC + Secomm_AddressDropdown + Secomm_Ahamove
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/AddressDropdown
  - app/code/Secomm/Ahamove
source_areas:
  - checkout
  - shipping
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-03
supersedes: []
---

# [SLP][BUG-RBR37K] [Ahamove][Checkout] Không tính lại giá shipping sau khi thay đổi address

<!-- External ticket: SLP-104 -->

## Summary

Trên checkout (Mageplaza OSC), khi khách đổi **City** hoặc **Sub-city (ward)** ở form shipping address, giá shipping (Ahamove và các carrier khác) **không được tính lại** — rate methods vẫn giữ phí theo địa chỉ cũ. Nguyên nhân gộp: (1) handler city/sub-city không update `quote.shippingAddress`/rate pipeline mà chỉ đụng DOM và chỉ gọi `setShippingInformationAction()` khi `location.hash === '#shipping'` (không đúng trên OSC checkout); (2) Magento cache rates trong `shipping-rate-registry` theo address cache key không đổi → re-estimate vẫn trả rate stale; (3) validation rule của Ahamove bắt buộc `postcode` trong khi địa chỉ VN thường trống postcode → rate Ahamove bị chặn không estimate.

## Mini Spec

### Goal

- Đổi City hoặc Sub-city ở checkout phải trigger re-estimate shipping rates với địa chỉ **mới**, cho mọi carrier (không riêng Ahamove).
- Rate cache phải bị vô hiệu hoá đúng key trước khi estimate lại (không trả rate stale).
- Ahamove không bị chặn validation khi postcode trống.

### Expected Behavior

- Đổi City → `quote.shippingAddress().city` cập nhật, rate registry clear, `estimateShippingMethod()` gọi lại → phí ship mới theo city mới.
- Đổi Sub-city → `extension_attributes.sub_city` cập nhật (merge, không mất attr khác), registry clear, re-estimate.
- Postcode trống (địa chỉ VN) → Ahamove vẫn xuất hiện trong list shipping methods với phí đúng.

### Constraints / Rules

- `estimateShippingMethod()` / `isAddressChange` chỉ tồn tại trên **Mageplaza OSC override** của `Magento_Checkout/js/model/shipping-rate-service` (`app/code/Mageplaza/Osc/view/frontend/web/js/model/shipping-rate-service.js:36-48`) — same AMD id, map qua requirejs-config. KHÔNG tồn tại trên file Magento gốc — fix này chỉ hợp lệ trong context OSC checkout.
- Không sửa file `Mageplaza_Osc` in-place (third-party — §7.1).
- Validation rules của Ahamove chỉ nới `postcode` — không đụng `country_id`/city required.

### Out of Scope

- Refactor flow address/rate của OSC (Tier 2 area) — chỉ sửa đúng 2 handler + 1 validation rule.
- Cart page (`#shipping` anchor) flow — hành vi cũ giữ nguyên.
- Carrier-level auto-fallback / phí quy đổi currency (BUG-YQT1FW).

### Acceptance Criteria

- **AC-001**: Đổi City ở OSC checkout → POST `estimate-shipping-methods` fire lại, phí ship các method cập nhật theo city mới.
- **AC-002**: Đổi Sub-city → re-estimate tương tự; `extension_attributes` khác của address không bị mất.
- **AC-003**: Không còn rate stale: sau khi đổi address, giá hiển thị ≠ giá của address cũ.
- **AC-004**: Địa chỉ VN không có postcode → shipping method Ahamove vẫn hiển thị và có giá đúng.
- **AC-005**: Console checkout không có JS error; luồng place order end-to-end vẫn pass (Tier 2 checkout QC).

## Steps to Reproduce

1. Vào checkout OSC, nhập địa chỉ VN (chọn city/ward), xem phí ship Ahamove.
2. Đổi City hoặc Sub-city sang giá trị khác.

## Expected Behavior

Phí ship tính lại theo địa chỉ mới.

## Actual Behavior (Before Fix)

Phí ship giữ nguyên theo địa chỉ cũ (rate không re-estimate hoặc trả từ cache stale).

## Root Cause Analysis

1. **Handler không fire rate pipeline trên OSC**: `shipping-address-dropdown.js` cũ chỉ gọi `setShippingInformationAction()` khi `window.location.hash === '#shipping'` (cart page anchor) — trên OSC checkout hash không match → không bao giờ trigger lại shipping information; và `quote.shippingAddress().city` không bao giờ được update (chỉ DOM), nên pipeline dù chạy cũng dùng city cũ.
2. **Rate cache stale**: Magento cache rates trong `shipping-rate-registry` theo `address.getKey()`/`getCacheKey()`; đổi city như một property trên cùng object address không đổi key → `new-address` processor trả rate từ cache. Fix phải `rateRegistry.set(key, null)` cho cả 2 key trước khi estimate lại.
3. **Postcode required chặn Ahamove**: `shipping-rates-validation-rules/ahamove.js` set `'postcode': {'required': true}` trong khi địa chỉ VN capture qua hierarchical dropdown thường không có postcode → validation fail → Ahamove không được estimate sau khi rates chạy lại.

## Affected Files

- [`app/code/Secomm/AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js`](app/code/Secomm/AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js) — update quote address + clear rate registry + force `estimateShippingMethod()` ở cả city-change và sub-city-change handler; merge `extension_attributes` thay vì replace; indentation/formatting cleanup.
- [`app/code/Secomm/Ahamove/view/frontend/web/js/model/shipping-rates-validation-rules/ahamove.js`](app/code/Secomm/Ahamove/view/frontend/web/js/model/shipping-rates-validation-rules/ahamove.js) — `postcode.required: true` → `false`.

## Callers (blast radius)

- `Mageplaza_Osc` `shipping-rate-service.js` (OSC override) — cung cấp `estimateShippingMethod`/`isAddressChange` mà fix phụ thuộc (read-only dependency, không sửa).
- `Magento_Checkout/js/model/shipping-rate-registry` — cache được clear theo key (Magento API chuẩn).
- Các carrier khác (GHN, TableRate) dùng chung rate pipeline → hưởng lợi từ re-estimate, không bị đổi hành vi riêng.
- Fix đã staged trên branch `dev/development/thangpham`, chờ pre-review → TL Tier 2 → QC.

## Verification & Test Results

- **AI pre-review (2026-09-03): PASS WITH WARNINGS** — static verification pass (OSC override API, cache-key semantics, module surface); warning W1 (`sub_city` transport tới estimate POST) đã được developer verify OK.
- Developer manual check (2026-09-03): OK.
- Chi tiết: `.ai/evidence/BUG-RBR37K/evidence.md`. Còn pending: AC-005 place order end-to-end + TL Tier 2.

## Notes for TL Review (Tier 2 — checkout + address)

- Fix chạm **checkout flow (OSC)** + **Secomm_AddressDropdown** — cả hai đều là high-risk Tier 2 (AGENTS.md §12) → cần TL/SA review trước khi merge.
- Phụ thuộc hard vào OSC override của `shipping-rate-service` — nếu sau này gỡ/thay OSC, AMD id này quay về bản Magento gốc (không có `estimateShippingMethod`) → JS sẽ TypeError. Chấp nhận được trong scope hiện tại (OSC là BR-004), chỉ flag để review biết.
- Mode: small-bug Mode C theo precedent BUG-YQT1FW; nếu TL đánh giá đây là checkout-flow change đủ lớn → reassess Mode B/A.
