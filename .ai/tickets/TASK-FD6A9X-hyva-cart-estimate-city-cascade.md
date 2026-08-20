# TASK-FD6A9X — Hyva cart "Estimate Shipping & Tax": thêm dropdown City/Sub-city

**Legacy ID:** SL-003 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Change Request (slice của feature FEAT-YVN39K — mở rộng AC-005)
**Priority:** Medium
**Estimate:** ~4–8h (sau khi D1 confirm)
**Mode:** A (Tier-2: shipping estimate + address data — §12; hiện thực nhỏ nhưng area Tier-2)
**Feature:** [FEAT-JSZQV3](../records/features/FEAT-JSZQV3.md) (ticket đầu — cart estimate; FEAT-YVN39K AC-005 để region-based, FEAT-JSZQV3 mở rộng cho VN)
**Spec:** [.ai/specs/SPEC-TASK-FD6A9X-vn-cart-shipping-estimate.md](../specs/SPEC-TASK-FD6A9X-vn-cart-shipping-estimate.md) (Draft — chờ SA/TL)
**Plan:** [.ai/plans/TASK-FD6A9X-implementation-plan.md](../plans/TASK-FD6A9X-implementation-plan.md) (Mode A, Plan only)
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-07-29 · **Status:** Proposed — D1 answered (cần); chờ quyết định structure + module placement

## Description

Trên storefront Hyva, block **"Estimate Shipping & Tax"** ở Shopping Cart **không có dropdown City** (chỉ Country / Province / Zip). Đây KHÔNG phải bug — [FEAT-YVN39K](../records/features/FEAT-YVN39K.md) AC-005 **cố tình** scope Hyva cart estimate là *"region-based"* (native `php-cart/shipping.phtml`); chỉ Luma nhận city cascade (Knockout mixin) và customer address form (Hyva) đã có cascade đầy đủ. Ticket này **mở rộng AC-005**: thêm cascade VN **Province → City → Sub-city** vào cart estimate trên Hyva để estimate phí ship chính xác theo city.

**Bằng chứng (audit 2026-07-29):**
- `vendor/hyva-themes/magento2-default-theme/Magento_Checkout/templates/php-cart/shipping.phtml`: chỉ render `country_id`/`region_id`/`region`/`postcode`; **0 match `city`, 0 match `sub_city`**.
- Module's cart city integration = RequireJS mixin trên `Magento_Checkout/js/view/cart/shipping-estimation` ([requirejs-config.js:20-22](../../app/code/Secomm/AddressDropdown/view/frontend/requirejs-config.js#L20-L22)) = **component Luma**. Hyva **không instantiate** nó trên cart → mixin dead code.
- [checkout_cart_index.xml](../../app/code/Secomm/AddressDropdown/view/frontend/layout/checkout_cart_index.xml) set `jsLayout` cho `checkout.cart.shipping`, nhưng Hyva block này là **block PHP thường** (không phải UI component) → `jsLayout` inert.
- Không có override `php-cart/shipping.phtml` ở theme hay module.

## Gating Question — D1: ANSWERED (2026-07-29, user direction)

**Có, cần.** Với country=VN, KHÔNG chỉ TableRate mà **mọi shipping method** (TableRate + GHN + Ahamove…) đều yêu cầu **country + city/province + ward/commune** đầy đủ (zip/postal có thể bỏ). Do đó cart estimate trên VN **phải** thu thập full hierarchy Province → City → Ward → ticket **proceed** (không còn nhánh wontfix).

**Tái cấu trúc scope (user 2026-07-29) — chưa finalize, chờ 2 quyết định:**
- Change này thuộc về **module VN-specific** (VN-market / VietNamAddress), KHÔNG thuộc generic `Secomm_AddressDropdown` (DEC-8 boundary) → placement TBD.
- Đây là phần đầu của capability lớn **VN shipping address support** (full hierarchy cho cart estimate + customize từng shipping-method extension cho VN).
- Thực trạng module: `Secomm_VietNamAddress` = **data-only** (chỉ `InstallVietNamAddressPatch`); city/sub_city **data + GraphQL + dropdown UI** đang nằm trong `Secomm_AddressDropdown`.
- GHN/Ahamove **chưa cài** (chỉ TableRate enabled) → customize sau khi add carrier.

## Acceptance Criteria

- [ ] **AC-1** (Cascade): Country=VN + Province selected → dropdown **City** (Alpine + GraphQL `GetListCity`); City → dropdown **Sub-city** (`GetListSubCity`). `.phtml`, pattern như `hyva/address/edit.phtml`. (BR-002)
- [ ] **AC-2** (Estimate payload): `city`/`sub_city` có trong request `/V1/{carts|guest-carts}/estimate-shipping-methods` (qua `shippingAddressFromData`) → TableRate trả rate chính xác (sau D1).
- [ ] **AC-3** (Restore selection): cart estimate khôi phục city/sub_city đã chọn sau re-render — apply `reapplySelected` (async-options) đã fix ở [edit.phtml](../../app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml).
- [ ] **AC-4** (i18n — BR-001): label City/Sub-city vi_VN + en_US đúng.
- [ ] **AC-5** (Non-VN): quốc gia khác VN → native behaviour, không break.
- [ ] **AC-6** (Luma unaffected): Luma cart estimate không đổi.
- [ ] **AC-7** (No leak / Tailwind v4 / ifconfig): class trong `@source` scope; không RequireJS; tôn trọng `address/general/enable`.

## Technical Notes

- **Placement (DEC-FEATJSZQV3-001 — accepted):** code sống trong **`Secomm_VietNamAddress`** (mở rộng `view/frontend`). Override `Magento_Checkout::php-cart/shipping.phtml` **conditional country=VN**, qua layout của `VietNamAddress` (`etc/module.xml` sequence thêm `Secomm_AddressDropdown`). `VietNamAddress` phụ thuộc `AddressDropdown` để reuse data/GraphQL.
- **Reuse:** GraphQL `GetListCity`/`GetListSubCity` + Alpine cascade + `reapplySelected` từ `Secomm_AddressDropdown/.../hyva/address/edit.phtml`. Đưa `city`/`sub_city` vào `shippingAddressFromData` mà phtml xây cho estimate API.
- Backend extension attribute `sub_city` đã có (FEAT-YVN39K) → chỉ cần frontend payload.

## Files/Areas Affected

- `app/code/Secomm/VietNamAddress/view/frontend/` (NEW — layout override `php-cart/shipping.phtml` + templates, conditional country=VN) — per DEC-FEATJSZQV3-001.
- `app/code/Secomm/VietNamAddress/etc/module.xml` (sequence: add `Secomm_AddressDropdown`).
- `vendor/hyva-themes/magento2-default-theme/Magento_Checkout/templates/php-cart/shipping.phtml` (template gốc bị override).
- `app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml` (pattern reference — reuse, không sửa).
- `Mageplaza_TableRateShipping` (rate VN → SL-004, ticket riêng).
- **KHÔNG affect**: `Secomm_AddressDropdown` generic code, backend `Api/`/`Model`, admin, OSC, customer address form.

## Risks

- Tier-2 (§12): shipping estimate + address data → escalate SA/TL.
- Payload estimate thay đổi (add city/sub_city) — verify TableRate consumer.
- D1 chưa confirm → có thể wontfix.
- Luma parity (AC-6) khó test trên project Hyva-only.

## Resolved decisions (U1–U6, 2026-07-29 — see FEAT-JSZQV3)

- ~~D1~~ → ANSWERED: VN cần full hierarchy cho mọi shipping method.
- ~~Placement~~ → DEC-FEATJSZQV3-001 (extend `Secomm_VietNamAddress`).
- **U1 (ward code)** → per-carrier (DEC-FEATJSZQV3-002/019): cart chỉ thu thập admin identifier (ward=`default_name`→native `city`); carrier code ở Launchpad.
- **U2 (Luma cart)** → REUSE generic (`custom_city`=ward, `custom_sub_city` auto-hide) + VN thêm label i18n; không reinject/own.
- **U3 (ward source)** → REUSE GraphQL `GetListCity(region_id)`; accept N+1 risk, fallback `CustomerData/CityData`.
- **U4 (Luma QC)** → CONSTRAINT: cần Luma theme riêng (Hyva-only project).
- **U5 (generic VN-leak)** → ticket riêng [TASK-KCBDDT](TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md); không trong task này.
- **U6 (labels)** → DEC-FEATJSZQV3-003: generic=Magento default; VN labels ở VietNamAddress.
- Open (SA, future): move city/sub_city data+GraphQL từ `AddressDropdown` sang `VietNamAddress`? Multi-store scope (DEC-1).

## Definition of Done

- [ ] D1 confirm (proceed / wontfix)
- [ ] Code complete + matches approach
- [ ] AI pre-review pass
- [ ] TL review approved (Tier 2)
- [ ] QC verified: cart estimate VN vi/en; non-VN unchanged; Luma unchanged
- [ ] FEAT-YVN39K AC-005 cập nhật (region-based → city-based) sau khi Done
