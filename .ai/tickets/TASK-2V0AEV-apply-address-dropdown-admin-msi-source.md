# TASK-2V0AEV — Apply VN 2-level address dropdown on admin MSI Source form

**Legacy ID:** SL-014 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Feature (admin ui_component surface)
**Priority:** Medium
**Estimate:** ~8–14h
**Mode:** B (standard feature — reassess nếu Source dùng cho shipping-origin/rate logic)
**Feature:** [FEAT-E2HM1J](../records/features/FEAT-E2HM1J.md) (parent — admin VN dropdown architecture) — extends CMP-ADDR ([FEAT-YVN39K](../records/features/FEAT-YVN39K.md)); related [FEAT-JSZQV3](../records/features/FEAT-JSZQV3.md)
**Spec ref:** [admin-vn-address-msi-source-form](../specs/SPEC-TASK-2V0AEV-admin-vn-address-msi-source-form.md) (mini-spec — approach extend `inventory_source_form`; drafted 2026-08-07, pending TL review)
**Risk tier:** Tier 1 (admin source form; address data inventory source — low blast) — *Tier 2 nếu Source feed shipping rate logic*
**Author:** AI draft · **Date:** 2026-08-03 · **Status:** Proposed
**Related:** [TASK-4ZV5NG](TASK-4ZV5NG-apply-address-dropdown-admin-customer-form.md) · [TASK-SQY42T](TASK-SQY42T-apply-address-dropdown-admin-order-form.md) · [TASK-8WSERX](TASK-8WSERX-apply-address-dropdown-admin-store-information.md)

## Model (DEC-FEATE2HM1J-001 — data-driven levels)

VN = **2 cấp**: `region` (Tỉnh/Thành) → `city` (Phường/Xã = ward); VN adapter không render `sub_city`. **`sub_city` = generic 3rd level (KHÔNG deprecate)** — non-VN 3 cấp vẫn dùng (DEC-FEATE2HM1J-001). VN logic ở `Secomm_VietNamAddress`; generic country-agnostic (DEC-FEATJSZQV3-003).

## Description

**MSI Source** (`Stores → Inventory → Sources` → source form, ui_component `inventory/source_form`) chứa address mỗi source (country/region/city/postcode/street/phone) — dùng làm **fulfillment origin** cho multi-source inventory + có thể feed shipping/distance + pickup parity. Hiện region = Magento default, `city` = text input → không có ward dropdown.

Ticket = apply cascade 2-level region→city(ward) vào Source form.

> **Precondition MET (user 2026-08-03)**: MSI được dùng → ticket hợp lệ. (Số source VN — verify khi implement.)

## Acceptance Criteria

- [ ] AC-1: Source form — chọn VN → cascade region→`city`(ward) render (ward dropdown phụ thuộc region, reload khi đổi region).
- [ ] AC-2: Lưu `region_id` + `city`(ward) vào source record (`inventory_source`); edit pre-select đúng.
- [ ] AC-3: **Non-VN country** → Magento default; generic (DEC-FEATJSZQV3-003).
- [ ] AC-4: **Regression** — các field source khác + inventory/SSO/checkout flow nguyên vẹn; source address dùng cho shipping/distance vẫn đúng.

## Technical Notes

- `inventory/source_form` là **ui_component** (giống cơ chế TASK-4ZV5NG customer form) → extend field `city` thành select + JS cascade country/region/city. Reuse pattern `customer_address_form.xml` + option provider.
- Ward option provider reuse `Model\Customer\Address\Config\Selector\City` (wards by region) — share TASK-4ZV5NG/012/013.
- Label ward (Phường/Xã) → `Secomm_VietNamAddress` (DEC-FEATJSZQV3-003).
- Source form của core `Magento_InventoryAdminUi` — extend (không sửa core).

## Files/Areas Affected

- **NEW/extend**: `Secomm_AddressDropdown/view/adminhtml/ui_component/` override `inventory_source_form` (add `city` select + cascade) HOẶC module riêng extends source form.
- **NEW**: JS cascade cho source form (reuse provider-mixin pattern hoặc component riêng).
- Reuse `Model/Customer/Address/Config/Selector/City.php` (share).
- `Secomm_VietNamAddress/` (label).
- **KHÔNG affect**: storefront, order/customer address, db_schema (source address schema core).

## Risks

- Override ui_component core `inventory/source_form` — verify không break MSI form render/validte.
- Nếu Source address feed **shipping rate/distance** (SSO, GHTK pickup parity) → sai ward = sai rate → reassess Tier-2.
- MSI không used (single-source) → công việc moot — **xác nhận precondition trước**.

## Open Questions

- ~~Q1: MSI có được dùng không?~~ **RESOLVED 2026-08-03 (user): MSI used** → ticket hợp lệ. (Số source VN — verify khi implement.)
- Q2: Approach — override `inventory_source_form` ui_component hay plugin? — plan.
- Q3: Source address có feed shipping rate/pickup (GHTK) không? → quyết định Tier. — SA.

## Dependencies

- Share ward source model + VN label với TASK-4ZV5NG/TASK-SQY42T/TASK-8WSERX.
- Liên quan [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) GHTK nếu Source = pickup origin (OQ-3).

## Definition of Done

- [ ] **Precondition verify**: MSI used + VN sources (OQ-1) — nếu moot → close
- [ ] Mini-spec/approach note (Mode B) — Q2 chốt
- [ ] AI pre-review pass
- [ ] TL review approved
- [ ] QC: Source form (VN 2-level + non-VN) + inventory/checkout regression
- [ ] DEC-FEATJSZQV3-003 compliance

## Planning Record

- **Spec**: [admin-vn-address-msi-source-form](../specs/SPEC-TASK-2V0AEV-admin-vn-address-msi-source-form.md)
- **Plan**: [TASK-2V0AEV-implementation-plan](../plans/TASK-2V0AEV-implementation-plan.md)
- **Approval**: Pending TL review (Level-2). Implementation must not be merged or released before approval and QC.
