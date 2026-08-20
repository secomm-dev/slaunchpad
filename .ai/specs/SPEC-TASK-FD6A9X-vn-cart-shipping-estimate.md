# Feature Spec: VN 2-level Cart "Estimate Shipping and Tax" (TASK-FD6A9X)

Specification ID: SPEC-TASK-FD6A9X
Feature ID: NONE
Specification Level: FULL

<!-- Spec cho ticket TASK-FD6A9X · Mode A · Stack: Magento 2.4.8-p5 + Hyvä 3.x (+ Luma support) -->
<!-- AI draft — chờ SA/TL signoff (spec-approval, Level 2). Tier-2 (shipping + address data). -->
<!-- Parent feature: FEAT-JSZQV3 (VN shipping address support). Decisions: DEC-FEATJSZQV3-001 / DEC-FEATJSZQV3-002 / DEC-FEATJSZQV3-003 / DEC-8. -->

## Metadata

| Field | Value |
|-------|-------|
| Spec ID | TASK-FD6A9X |
| Author | AI draft |
| Status | ✅ Approved 2026-07-29 (user acting as SA/TL) |
| Date | 2026-07-29 |
| Related Ticket(s) | [TASK-FD6A9X](../tickets/TASK-FD6A9X-hyva-cart-estimate-city-cascade.md) |
| Parent Feature | [FEAT-JSZQV3](../records/features/FEAT-JSZQV3.md) |
| Workflow Mode | A (Tier-2: shipping estimate + address data) |
| Decisions | DEC-FEATJSZQV3-001 (generic→VietNamAddress) · DEC-FEATJSZQV3-002 (per-carrier→Launchpad) · DEC-FEATJSZQV3-003 (label/code ownership) · DEC-8 (boundary) |

## 1. Objective

Thêm hierarchy admin **2 cấp VN** (Country → **Province/City (Tỉnh/Thành phố)** → **Ward/Commune (Phường/Xã)**) vào form **"Estimate Shipping and Tax"** ở Shopping Cart, trên **cả Luma và Hyvä**, để customer có thể ước tính phí ship chính xác theo địa chỉ VN. Phần change thuộc sở hữu `Secomm_VietNamAddress` (mở rộng từ data-only), **reuse** `Secomm_AddressDropdown` (generic, country-agnostic). **Không có district.** Non-VN giữ nguyên Magento default.

## 2. User Stories

### US-001: Guest ước tính ship cho địa chỉ VN (Hyva & Luma)
**AC:**
- [ ] Given cart có item + country=VN, When chọn Province/City, Then Ward/Commune dropdown hiện + disabled đến khi Province selected.
- [ ] Given Province selected, When chọn Ward, Then shipping rates recollect **một lần** với payload chứa province + ward.
- [ ] Given country≠VN, Then form giữ native Magento (country/region/postcode), không field VN, không validation VN.

### US-002: Logged-in customer restore quote VN (Hyva & Luma)
**AC:**
- [ ] Given quote có saved VN address, When mở cart, Then Province + Ward restore đúng + Ward options load theo saved Province.
- [ ] Given đổi Province, Then Ward cũ clear + Ward options reload theo Province mới; không stale option (out-of-order async safe).

### US-003: Country switch
**AC:**
- [ ] Given VN → other country, Then stale province/ward IDs/codes/labels clear; native estimator restore.
- [ ] Given other country → VN, Then 2-level hierarchy init đúng.

## 3. Scope

### In Scope
- VN 2-level (Province/City → Ward/Commune) trên cart estimate — **Luma + Hyvä**.
- VN labels (i18n vi_VN + en) + special-zone label từ data import.
- Server-side validation: province∈VN, ward∈province, codes hợp lệ.
- Restore saved VN quote; rate refresh đúng (single request, no duplicate).
- Gate theo country=VN; clear stale state khi switch.
- Guest cart + logged-in cart.

### Out of Scope
- **District** (không render, không require — data VN đã 2-level).
- **Carrier code mapping** (GHN/Ahamove/TableRate codes) → Launchpad per-carrier (SL-004/005/006, DEC-FEATJSZQV3-002). Cart chỉ thu thập admin identifier.
- **Mageplaza OSC** (trừ shared address logic reuse không mở scope).
- **Generic VN-leak cleanup** → TASK-KCBDDT (DEC-FEATJSZQV3-003).
- Schema/extension_attribute/GraphQL **mới**; resolver hardening (FEAT-YVN39K AC-011/Q1).
- Destructive migration district data (không có active district data).

## 4. Business Rules

| Rule ID | Rule | Test Approach |
|---------|------|---------------|
| BR-001 | i18n vi_VN + en; label từ data (special-zone) | QC cả 2 locale |
| BR-002 | Cascade Province → Ward (2 cấp VN, no district) | QC cascade + restore |
| BR-VN-CART | country=VN → Province + Ward required cho rate estimate | QC required + rate trigger |
| BR-NONVN | country≠VN → native Magento, không field/validation VN | QC non-VN unchanged |
| BR-005 | TableRate consume address (carrier code = Launchpad, out of scope) | rate recollect verification |

## 5. Technical Approach

### 5.1 Architecture Impact
- Mở rộng `Secomm_VietNamAddress` (data-only → +`view/frontend` cart adapter + validation). **Không đổi** `Secomm_AddressDropdown` (generic, country-agnostic — DEC-FEATJSZQV3-003).
- Dependency direction giữ: `VietNamAddress → AddressDropdown` (đã có, [module.xml:6](../../app/code/Secomm/VietNamAddress/etc/module.xml#L6)).
- Mapping (DEC-FEATJSZQV3-003): Province = Magento `region_id`/`region`; Ward = `default_name` → native `city`. **Không** carrier code trong cart.

### 5.2 Implementation Notes
**Hyvä (implement chính):** handle **`hyva_checkout_cart_index`** (auto-add bởi `AddLayoutHandles`, chạy sau base) → `referenceBlock checkout.cart.shipping` `setTemplate` (ifconfig) tới phtml mở rộng trong VietNamAddress. Phtml = bản gốc `php-cart/shipping.phtml` + :
- Province dropdown = region native (source `directory-data` section — VN provinces đã ở `directory_country_region`).
- Ward `<select name="city">` sau region, source GraphQL `GetListCity(region_id)`; disabled đến khi Province selected; gate `x-if="countryId==='VN'"`.
- Method `setCity(value)`: set `cartData.address.city` + `shippingAddressFromData.city` → `fetchShippingMethods()` (single request).
- Restore: `reapplySelected` pattern (async options — đã fix ở [edit.phtml](../../app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml)).
- Out-of-order guard: request-id/token khi đổi Province nhanh; clear Ward cũ.
- Pattern tiền lệ: [hyva_customer_address_form.xml](../../app/code/Secomm/AddressDropdown/view/frontend/layout/hyva_customer_address_form.xml) + [hyva/address/edit.phtml](../../app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml). **Không RequireJS/Knockout.**

**Luma (REUSE generic — U2):** `Secomm_AddressDropdown` đã inject `custom_city`(SELECT=ward)+`custom_sub_city`(auto-hide) qua [Plugin/Cart/LayoutProcessorPlugin.php](../../app/code/Secomm/AddressDropdown/Plugin/Cart/LayoutProcessorPlugin.php) + mixin. VN chỉ thêm **label i18n** + (nếu cần) mixin mảnh ép required/ward→`city`. Không reinject/own.

**Server-side validation (backend rule #3):** plugin (VietNamAddress) validate province∈VN + ward∈province + codes hợp lệ; reuse `Secomm_AddressDropdown/Helper/Address.php` + `Helper/Data.php`. Không trust browser.

### 5.3 Database Changes
**None.** VN 2-level data đã import ([VN_Address_2Level.csv](../../app/code/Secomm/VietNamAddress/Files/VN_Address_2Level.csv) → `directory_country_region` + `directory_region_city`). Không district. Không storage/code mới (DEC-FEATJSZQV3-003: ward = `default_name`).

### 5.4 API Changes
**Reuse** GraphQL `GetListCity(input:{region_id})` ([schema.graphqls:2](../../app/code/Secomm/AddressDropdown/etc/schema.graphqls#L2)) cho Ward. Estimate REST payload (`/V1/.../estimate-shipping-methods`) gains `city` (frontend). **No new endpoint.**

### 5.5 Integration Impact
Shipping carriers consume province + ward qua `RateRequest` ([Plugin/Model/Shipping.php:57-107](../../app/code/Secomm/AddressDropdown/Plugin/Model/Shipping.php#L57-L107) đã mutate `dest_city`/`sub_city`). **Carrier code mapping = Launchpad** (DEC-FEATJSZQV3-002, out of scope).

## 6. UI/UX
- country=VN: 2 dropdown Province/City + Ward/Commune; Ward disabled đến khi Province selected; required cho rate.
- country≠VN: native Magento estimator.
- Labels vi_VN (Quốc gia / Tỉnh/Thành phố / Phường/Xã) + en (Country / Province/City / Ward/Commune); special-zone từ data.

## 7. Dependencies
- `Secomm_AddressDropdown` (data, GraphQL `GetListCity`, `Helper/Address`, cart rate plugin) — provided.
- Hyva `AddLayoutHandles` observer (`hyva_checkout_cart_index` handle) — provided.
- `directory-data` customer-data section (Province options) — provided.

## 8. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `GetListCity` `@deprecated`+`@cache(false)` → N+1 | M | M | Accept (U3); fallback `CustomerData/CityData` (cached) |
| Out-of-order async (đổi Province nhanh) | M | H | request-id guard; clear Ward |
| Double rate request | M | M | single `fetchShippingMethods()` sau ward valid |
| Luma untestable (Hyva-only project) | H | M | QC cần Luma theme riêng (U4) |
| Locale EN label sai (en_US vs en_VN) | M | M | confirm locale strategy; map City→Ward/Commune cho cart |
| 2 bug tiền tồn (`SaveToQuote.php:36` int cast; `GetListSubCityGraphql.php:69`) | L | M | flag; fix riêng nếu chạm flow |

## 9. Test Approach
9-matrix (spec user): Luma/Hyvä × guest/logged-in × VN/non-VN, country switch, error handling (API fail, empty list, rapid change, out-of-order), regression (cart item CRUD, coupon, shipping-method select, checkout nav, **Mageplaza OSC**, customer address book, admin order view, payment). L3 (address data). Luma cần theme riêng.

## 10. Assumptions
- [ ] VN 2-level data đã import đúng (Province→`directory_country_region`, Ward→`directory_region_city`, sub_city trống).
- [ ] Luma generic cart estimator đã render Province→Ward cho VN (verify Phase 2).
- [ ] Province options cho VN có trong `directory-data`.
- [ ] Store locale = vi_VN + en_US (BR-001).

## 11. Open Questions
- [ ] Q-locale: EN label cho VN store qua en_US hay en_VN? (SA)
- [ ] Q-graphql: hardening `GetListCity` (Q1/AC-011) trước hay sau task này? (SA — accept risk now)
- [ ] Multi-store theme scope (DEC-1).

## 12. Estimation

| Task | Estimate | Actual |
|------|----------|--------|
| VN labels i18n | 1–2h | |
| Server-side VN validation | 3–5h | |
| Hyva cart adapter (phtml + Alpine + payload + restore + guards) | 6–10h | |
| Luma verify + mixin mảnh | 2–4h | |
| Tests + QC + evidence | 4–6h | |
| **Total** | **~16–27h** | |

## Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| SA/TL | user (acting as SA/TL) | 2026-07-29 | ✅ Approved |
