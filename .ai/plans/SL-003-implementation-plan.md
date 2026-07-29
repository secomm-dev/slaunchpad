# Implementation Plan: SL-003 — VN 2-level Cart "Estimate Shipping and Tax" (Luma + Hyvä)

> Mode A · **Plan only — chưa viết code** (Hard Gate 3: No Code Without Plan).
> Tier-2 (shipping estimate + address/customer data — §12) → escalate SA/TL; code review trước/sau.
> Parent: [FEAT-005](../records/features/FEAT-005.md). Spec: [vn-cart-shipping-estimate](../specs/vn-cart-shipping-estimate.md).

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-003](../tickets/SL-003-hyva-cart-estimate-city-cascade.md) |
| Spec | [vn-cart-shipping-estimate](../specs/vn-cart-shipping-estimate.md) (Draft) |
| Feature | [FEAT-005](../records/features/FEAT-005.md) |
| Author | AI draft |
| Reviewer (TL) | user (acting as SA/TL) — approved 2026-07-29 |
| Workflow Mode | A |
| Date | 2026-07-29 |
| Decisions | DEC-017 · DEC-018 · DEC-019 · DEC-8 |
| Validation level | **L3** (address data — §8.6) |

## 1. Approach

**Reuse-first, VN-owned (DEC-017/019):** mở rộng `Secomm_VietNamAddress` (data-only → +`view/frontend`) để owns hành vi VN; **reuse** `Secomm_AddressDropdown` (data/GraphQL `GetListCity`/`Helper`/cart rate plugin) — **không sửa generic** (cleanup leak VN = SL-007 riêng).

- **Hyva (implement chính):** override `Magento_Checkout::php-cart/shipping.phtml` qua handle `hyva_checkout_cart_index` (auto-add, chạy sau base) → `referenceBlock checkout.cart.shipping setTemplate` (ifconfig). Phtml = bản gốc + Ward `<select name="city">` + `setCity()` + `city` trong payload. Province = region native (`directory-data`); Ward = `GetListCity(region_id)`. Gate `countryId==='VN'`. Không RequireJS/Knockout. Pattern tiền lệ: `hyva_customer_address_form.xml` + `hyva/address/edit.phtml`.
- **Luma (REUSE — U2):** generic đã inject `custom_city`(ward)+`custom_sub_city`(auto-hide) qua `Plugin/Cart/LayoutProcessorPlugin.php` + mixin. VN chỉ thêm label i18n + mixin mảnh (nếu cần). Không reinject.
- **Server-side validation (backend rule #3):** plugin validate province∈VN + ward∈province; reuse `Helper/Address.php`.
- **Restore + async-safe:** `reapplySelected` pattern; request-id guard; single rate request.

**Lý do chọn:** reuse giảm scope; handle `hyva_` = pattern chuẩn Hyva (zero-PHP, FPC-safe); không schema/endpoint/storage/district mới.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `Secomm_VietNamAddress/view/frontend/layout/hyva_checkout_cart_index.xml` | new | Hyva handle → setTemplate cart shipping (AC Hyva) |
| `Secomm_VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml` | new | bản gốc + Ward select + setCity + payload city + restore + guards (AC Hyva) |
| `Secomm_VietNamAddress/etc/frontend/di.xml` + `Plugin/Cart/...` (validate) | new | server-side VN validation (backend rule #3) |
| `Secomm_VietNamAddress/i18n/vi_VN.csv` + `en_*.csv` | modify | VN labels + validation msg (BR-001) |
| `Secomm_VietNamAddress/etc/module.xml` | verify | sequence `Secomm_AddressDropdown` (đã có — confirm) |
| `Secomm_VietNamAddress/view/frontend/requirejs-config.js` + mixin mảnh (Luma) | new (nếu cần) | label/required VN cho Luma (U2 — chỉ nếu generic chưa đủ) |
| **Không sửa** `Secomm_AddressDropdown/*` | — | generic country-agnostic (DEC-019); leak → SL-007 |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **VN labels i18n** — risk: low — deps: none
   - thêm Province/City, Ward/Commune, "Please select a Ward/Commune", "Vui lòng chọn Phường/Xã." vào `vi_VN.csv` + en; special-zone từ data.
   - verify: storefront cart VN hiển thị đúng label vi/en.
2. **Server-side VN validation** — risk: medium — deps: -
   - plugin (trên estimate/rate flow) validate province∈VN, ward∈province (theo `region_id`), codes hợp lệ; reject invalid không break cart.
   - verify: test payload ward không thuộc province → rejected/ignored; valid → pass.
3. **Hyva cart adapter** — risk: high — deps: 1,2
   - layout handle `hyva_checkout_cart_index.xml` (referenceBlock setTemplate ifconfig).
   - phtml: copy gốc + Ward `<select name="city">` (sau region) + Alpine state `selectedWard`/`wards`/`isLoadingWard` + `setCity()` (set `cartData.address.city`+`shippingAddressFromData.city` → `fetchShippingMethods()`) + `loadWards(regionId)` (GetListCity) + restore (`reapplySelected`) + out-of-order guard (request-id) + clear-on-province-change + gate `countryId==='VN'`.
   - verify: Hyva cart VN — Province→Ward cascade, rate recollect 1 lần, restore saved, non-VN native, no RequireJS error.
4. **Luma verify + mixin mảnh** — risk: medium — deps: 1
   - verify generic `custom_city`=ward render cho VN; thêm mixin mảnh nếu cần required/label.
   - verify: Luma cart VN (cần Luma theme — U4).
5. **Tests** — risk: low — deps: 3,4
   - integration test server-side validation; manual 9-matrix.
6. **QC + evidence** — risk: low — deps: 5
   - 9-matrix QC; evidence `.ai/runtime/evidence/FEAT-005/` (SL-003 portion).

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Hyva cart estimator break (override phtml) | high | keep native logic; diff-minimal; gate VN-only; test non-VN parity |
| Estimate payload change (add city) | medium | verify TableRate + carrier consume; non-VN payload unchanged |
| Double/stale rate request | medium | single fetch + request-id guard |
| Mageplaza OSC regression | high | OSC out of scope; smoke-test OSC unchanged |
| Generic module touched accidentally | medium | **không sửa generic**; review diff |
| Luma untestable (Hyva-only) | medium | flag; QC với Luma theme riêng |

## 5. Test approach
- Unit/Integration: server-side VN validation (province/ward relationship).
- QC (L3): 9-matrix (Luma/Hyvä × guest/logged-in × VN/non-VN, country switch, error handling, regression incl. OSC + customer address book + admin + payment).
- High-risk: address data persistence (quote→order `sub_city`/`city`); rate recollect.

## 6. Out of scope
- District; carrier code mapping (SL-004/005/006); OSC; generic VN-leak cleanup (SL-007); schema/endpoint/storage mới; resolver hardening (Q1); destructive district migration.

## 7. Open questions / Escalation
- Q-locale EN (en_US vs en_VN) — SA (Tier 1).
- `GetListCity` hardening timing (Q1/AC-011) — SA (Tier 2); accept risk now.
- Multi-store scope (DEC-1) — SA.
- **Tier-2 escalation required** trước code (shipping + address data).
