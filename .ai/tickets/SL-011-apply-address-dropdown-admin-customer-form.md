# SL-011 — Apply/verify VN 2-level address dropdown on admin customer-address form (+ migrate VN logic → VietNamAddress)

**Type:** Task (verify + migrate) — DEC-019 compliance
**Priority:** Medium
**Estimate:** ~6–10h
**Mode:** A (chạm customer/PII data → Tier-2; migrate VN logic ra generic module)
**Feature:** [FEAT-007](../records/features/FEAT-007.md) (parent — admin VN dropdown architecture) — extends CMP-ADDR ([FEAT-001](../records/features/FEAT-001.md)); related [FEAT-005](../records/features/FEAT-005.md)
**Spec ref:** [admin-vn-address-customer-form](../specs/SPEC-SL-011-admin-vn-address-customer-form.md) — **APPROVED 2026-08-03** (user acting as SA/TL; Q1/Q2/Q3 resolved in [plan](../plans/SL-011-implementation-plan.md)); architecture = [DEC-025](../records/decisions/DEC-025.md)
**Risk tier:** Tier 2 (customer data / PII — AGENTS §9 L398, L421)
**Author:** AI draft · **Date:** 2026-08-03 · **Status:** Proposed
**Related:** [SL-012](SL-012-apply-address-dropdown-admin-order-form.md) · [SL-013](SL-013-apply-address-dropdown-admin-store-information.md) · [SL-014](SL-014-apply-address-dropdown-admin-msi-source.md)

## Model (DEC-025 — data-driven levels)

Cascade **data-driven theo country**. VN = **2 cấp**: `region` (Tỉnh/Thành) → `city` (Phường/Xã = ward; `directory_region_city`, DEC-020); VN adapter **không render `sub_city`**. **`sub_city` = generic 3rd level (KHÔNG deprecate)** — country non-VN có data 3 cấp vẫn dùng (DEC-025). VN logic chỉ ở `Secomm_VietNamAddress`; generic `Secomm_AddressDropdown` country-agnostic (DEC-019).

## Description

Admin customer-address modal đã wire `city_select` + `sub_city` **trong `Secomm_AddressDropdown`** ([customer_address_form.xml:4](../../app/code/Secomm/AddressDropdown/view/adminhtml/ui_component/customer_address_form.xml#L4); [provider-mixin.js](../../app/code/Secomm/AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js)) — **vi phạm DEC-019** (VN/level logic leak vào generic module; giống storefront mà SL-007 đang dọn).

Ticket = (1) **migrate VN behaviour sang `Secomm_VietNamAddress`** (country==VN → `city`=ward dropdown, 2-level), (2) giữ cascade + `sub_city` ở generic (country-agnostic, data-driven), (3) verify end-to-end Add + Edit.

## Acceptance Criteria

- [ ] AC-1: Audit hiện trạng admin customer-address cascade — baseline (VN logic hiện trong AddressDropdown, cơ chế data-driven hiện tại, gap).
- [ ] AC-2: **Add new** customer address (VN) → cascade country→region→`city`(ward) render đúng; ward persist vào `customer_address_entity.city`.
- [ ] AC-3: **Edit existing** → region + `city`(ward) **pre-select đúng**.
- [ ] AC-4: **Migrate VN logic ra `Secomm_VietNamAddress`** — `Secomm_AddressDropdown` không còn `country=='VN'`/ward-specific behaviour ở admin customer form (DEC-019/025); generic cascade country-agnostic.
- [ ] AC-5: **`sub_city` generic giữ nguyên** — non-VN country có data 3 cấp vẫn render `sub_city`; VN (2 cấp) không render `sub_city` (data-driven, DEC-025).
- [ ] AC-6: **Server-side validate** ward ∈ province khi VN (mirror `ValidateVietNamWard`); invalid → log, không crash save.
- [ ] AC-7: **Non-VN country** → generic Magento default; không leak VN.
- [ ] AC-8: Address **display/format** (address book, order, email) hiển thị region + ward (VN).
- [ ] AC-9: **Regression** — storefront customer form (Luma+Hyva) + cart estimate + admin data CRUD nguyên vẹn; migrate không break storefront.

## Technical Notes

- `customer_address_form.xml`: giữ `city_select` (generic, options `Selector\City`) + `sub_city` (generic) — nhưng **country-agnostic**; VN gate/ward-data move sang VietNamAddress adapter.
- `provider-mixin.js`: gỡ VN-specific; tương tác country/region/city generic.
- VN adapter (VietNamAddress): khi country==VN, `city_select` load wards theo region (reuse `CityLocaleCollection`); validate ward∈province; label "Phường/Xã".
- Persist: `customer_address_entity.city` (ward VN); `sub_city` persist cho country non-VN 3-level (legacy data không break).
- Mirror frontend cart pattern (SL-003 `shipping.phtml` + `ValidateVietNamWard`).

## Files/Areas Affected

- `Secomm_AddressDropdown/view/adminhtml/ui_component/customer_address_form.xml` (generalize — gỡ VN-specific)
- `Secomm_AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js` (gỡ VN-specific)
- **NEW** `Secomm_VietNamAddress/` — admin customer-address VN adapter (render ward dropdown when VN + validate)
- `Secomm_AddressDropdown/Model/Customer/Address/Config/Selector/City.php` (generic wards by region — reuse)
- **KHÔNG affect**: storefront `view/frontend/*`, data layer, `db_schema` (`sub_city` column giữ).

## Risks

- Tier-2 customer/PII: QC end-to-end + TL review.
- Migrate VN logic phải preserve behavior chính xác; không break storefront đang dùng (align SL-007).
- `provider-mixin.js` selector hard-code có thể miss form Add-new.
- Country non-VN 3-level: verify `sub_city` vẫn hoạt động sau migrate.

## Open Questions

- Q1: Cơ chế VN adapter cho admin customer form — plugin/layout override hay ui_component customization? — mini-spec (Mode A).
- Q2: Label ward = "Phường/Xã" qua `Secomm_VietNamAddress` i18n (DEC-019)? — SA.

## Dependencies

- Share `Selector\City` + ward data với SL-012/013/014.
- Coordinate [SL-007](SL-007-generalize-addressdropdown-remove-vn-leak.md) (storefront generic cleanup — cùng boundary).
- Architecture: [DEC-025](../records/decisions/DEC-025.md).

## Definition of Done

- [ ] Audit note (AC-1)
- [ ] Mini-spec + plan (Mode A) approved
- [ ] AI pre-review pass
- [ ] TL review approved (Tier 2)
- [ ] QC: admin customer address Add + Edit (VN 2-level + non-VN incl. 3-level sub_city) + validate + display + regression storefront
- [ ] DEC-019/025 compliance (no VN leak trong generic admin surface)
