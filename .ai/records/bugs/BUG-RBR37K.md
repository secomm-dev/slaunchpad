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
updated: 2026-09-15
ticket_ref:
affects_version: Magento 2.4.8-p5 + Mageplaza OSC + Launchpad_Osc + Secomm_AddressDropdown + Secomm_Ahamove
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Launchpad/Osc
  - app/code/Secomm/AddressDropdown
  - app/code/Secomm/Ahamove
source_areas:
  - osc-checkout-address
  - shipping-rate-recalculation
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-15
supersedes: []
---

# [SLP][BUG-RBR37K] [Ahamove][Checkout] Không tính lại giá shipping sau khi thay đổi address

<!-- External ticket: SLP-104 -->

## Summary

Trên checkout Mageplaza OSC, thay đổi shipping address có thể không làm mới giá Ahamove và các carrier khác; danh sách method tiếp tục dùng rate cache của địa chỉ cũ. Initial fix ngày 2026-09-03 đã xử lý City/Ward trong `Secomm_AddressDropdown` và nới validation postcode của Ahamove. Sau khi OSC coupling được tách sang `Launchpad_Osc`, follow-up hiện tại mở rộng invalidation cho Street, Country, Region, Ward và thao tác chọn saved address trong file OSC-owned.

## Mini Spec

### Goal

- Mọi thay đổi shipping address có thể ảnh hưởng rate (Country, Region, City/Ward, Street hoặc saved address) phải dùng dữ liệu địa chỉ mới khi tính shipping.
- Rate cache phải bị vô hiệu hoá đúng key cho cả new address và customer address trước khi rate pipeline chạy lại.
- Ahamove không bị chặn validation khi postcode trống.

### Expected Behavior

- Đổi Ward/City hoặc Street sau khi các field address bắt buộc đã đủ → registry clear, `shippingRateService.isAddressChange = true`, `estimateShippingMethod()` chạy lại với address hiện tại.
- Đổi Country/Region hoặc chọn saved address → cache của address cũ bị clear trước khi OSC/core rate pipeline estimate method tiếp theo.
- Customer address cache key `customer-address{customerAddressId}` và các key do `getKey()`/`getCacheKey()` cung cấp đều được invalidation an toàn.
- Postcode trống (địa chỉ VN) → Ahamove vẫn xuất hiện trong list shipping methods với phí đúng.

### Constraints / Rules

- `estimateShippingMethod()` / `isAddressChange` chỉ tồn tại trên **Mageplaza OSC override** của `Magento_Checkout/js/model/shipping-rate-service` (`app/code/Mageplaza/Osc/view/frontend/web/js/model/shipping-rate-service.js:36-48`) — same AMD id, map qua requirejs-config. KHÔNG tồn tại trên file Magento gốc — fix này chỉ hợp lệ trong context OSC checkout.
- Không sửa file `Mageplaza_Osc` in-place (third-party — §7.1).
- OSC-specific behavior phải nằm trong `Launchpad_Osc`; `Secomm_AddressDropdown` giữ generic/default-checkout boundary theo `DEC-TASKFMAN1B-001`.
- Event handler phải namespaced/rebind idempotent; không tạo duplicate rate requests sau khi component initialize lại.
- Street chỉ trigger estimate khi Region và Ward/City đã có giá trị; cùng một giá trị không trigger lặp.
- Validation rules của Ahamove chỉ nới `postcode` — không đụng `country_id`/city required.

### Out of Scope

- Billing address recalculation; ticket chỉ xử lý shipping address.
- Refactor/rewrite toàn bộ OSC address cascade hoặc shipping-rate service.
- Default Magento checkout và Hyvä cart estimate; follow-up hiện tại chỉ nằm trong `Launchpad_Osc`.
- Carrier-level auto-fallback / phí quy đổi currency (BUG-YQT1FW).

### Acceptance Criteria

- **AC-001**: Đổi Ward/City ở OSC checkout → POST estimate shipping methods fire lại và giá hiển thị phản ánh address mới.
- **AC-002**: Đổi Street khi Region + Ward/City đã đủ → re-estimate; không request khi address còn thiếu hoặc giá trị street không đổi.
- **AC-003**: Đổi Country/Region hoặc chọn saved address → request tiếp theo không lấy rate stale từ `shipping-rate-registry`, bao gồm customer-address key.
- **AC-004**: Địa chỉ VN không có postcode → shipping method Ahamove vẫn hiển thị và có giá đúng.
- **AC-005**: Guest + logged-in checkout không có duplicate request/JS error; place order end-to-end vẫn pass (Tier 2 checkout QC).

## Steps to Reproduce

1. Vào checkout OSC với giỏ hàng có Ahamove; nhập đủ Province/City và Ward, ghi nhận phí ship.
2. Lần lượt đổi Ward, Street, Region/Country hoặc chọn một saved address khác có mức phí khác.
3. Quan sát network estimate-shipping request và giá shipping hiển thị.

## Expected Behavior

Rate cache cũ bị invalidation; phí ship được tính lại theo shipping address mới.

## Actual Behavior (Before Fix)

Phí ship giữ nguyên theo địa chỉ cũ: một số address-change path không invalidation registry, hoặc estimate processor tái sử dụng cache key cũ.

## Root Cause Analysis

1. **Initial OSC handler gap**: implementation cũ chưa đồng bộ City/Ward vào `quote.shippingAddress()` và chưa force rate pipeline đúng trong OSC context. Initial fix 2026-09-03 đã xử lý nhánh này.
2. **Rate cache invalidation không bao phủ mọi address-change path**: core processors cache new address bằng `getCacheKey()` và customer address bằng `getKey()` (`customer-address{customerAddressId}`). OSC-owned copy chỉ clear rate trong Ward handler; Street, Country, Region và saved-address selection có thể đi qua processor khi cache cũ vẫn còn.
3. **Postcode required chặn Ahamove**: validation rule cũ bắt buộc `postcode`, trong khi địa chỉ VN thường trống field này. Initial fix đã nới riêng rule postcode.

## Fix

### Follow-up 2026-09-15 (current staged change)

- `app/code/Launchpad/Osc/view/frontend/web/js/action/shipping-address-dropdown.js`
  - Tách `clearRateCache()` dùng chung, guard `getKey()`/`getCacheKey()` và clear explicit customer-address key khi có `customerAddressId`.
  - Bind idempotent Street `change`; chỉ estimate khi Region + Ward/City đã đủ và giá trị thực sự thay đổi.
  - Invalidate cache khi chọn saved address, đổi Country, Region hoặc Ward/City.
  - Giữ `estimateShippingMethod()` defensive bằng type guard để không phát sinh JS error nếu service không cung cấp method.

### Initial fix 2026-09-03 (committed in `92af2b325`)

- `app/code/Secomm/AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js` — update quote address, clear rate registry và force estimate ở City/Ward handler.
- `app/code/Secomm/Ahamove/view/frontend/web/js/model/shipping-rates-validation-rules/ahamove.js` — `postcode.required: true` → `false`.

## Callers (blast radius)

- `Mageplaza_Osc` `shipping-rate-service.js` (OSC override) — cung cấp `estimateShippingMethod`/`isAddressChange` mà fix phụ thuộc (read-only dependency, không sửa).
- Magento `shipping-rate-processor/new-address` — cache bằng `address.getCacheKey()`.
- Magento `shipping-rate-processor/customer-address` — cache bằng `address.getKey()`; customer model trả key `customer-address{customerAddressId}`.
- Các carrier khác (GHN, TableRate) dùng chung rate pipeline → hưởng lợi từ re-estimate, không bị đổi hành vi riêng.
- Follow-up hiện đang staged trên branch `dev/development/thangpham`, chờ pre-review → TL/SA Tier 2 → QC L3.

## Verification & Test Results

- **Initial fix**: AI pre-review 2026-09-03 PASS WITH WARNINGS; developer manual check OK. Chi tiết trong `.ai/evidence/BUG-RBR37K/evidence.md`.
- **Follow-up 2026-09-15**: static diff xác nhận cache invalidation bao phủ `getKey()`, `getCacheKey()` và customer-address key; runtime/browser QC chưa chạy.
- Pending: AC-001..005 trên guest + logged-in OSC, Ahamove response thật/mock có rate khác nhau theo address, place order end-to-end và TL/SA Tier-2 review.

## Notes for TL Review (Tier 2 — checkout + address)

- Fix chạm **checkout flow (OSC)** + **shipping rate/address data** — high-risk Tier 2 (AGENTS.md §11–§12) → cần TL/SA review và QC L3 trước khi merge.
- `estimateShippingMethod()` là OSC-specific API; follow-up đã guard method trước khi gọi. Nếu sau này thay/gỡ OSC, cần xóa hoặc thay adapter này thay vì coi core shipping-rate service là contract tương đương.
- Record giữ Mode C theo canonical item đã tạo cho SLP-104; Tier-2 approval vẫn là hard gate do change chạm checkout/shipping.
