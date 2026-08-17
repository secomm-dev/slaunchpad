# SL-013 — Apply VN 2-level address dropdown on admin Store Information + Shipping Origin config

**Type:** Feature (admin config surface — merged: Store Information + Shipping Origin)
**Priority:** Medium
**Estimate:** ~10–16h
**Mode:** B (standard feature — reassess → A nếu chạm config schema)
**Feature:** [FEAT-007](../records/features/FEAT-007.md) (parent — admin VN dropdown architecture) — extends CMP-ADDR ([FEAT-001](../records/features/FEAT-001.md)); related [FEAT-005](../records/features/FEAT-005.md)
**Spec ref:** [admin-vn-address-store-config](../specs/admin-vn-address-store-config.md) (mini-spec — Mode B; approach inject dependent dropdown vào core config field `city` qua `frontend_model`; Q1/Q2/Q3 resolved trong [plan](../plans/SL-013-implementation-plan.md); architecture = [DEC-025](../records/decisions/DEC-025.md)) — **drafted 2026-08-07 · pending TL approval**
**Risk tier:** Tier 1 (admin config; ảnh hưởng PDF/print origin + carrier/TableRate origin — reversible)
**Author:** AI draft · **Date:** 2026-08-03 · **Status:** Implemented 2026-08-07 (pending QC L3 + TL code review)
**Related:** [SL-011](SL-011-apply-address-dropdown-admin-customer-form.md) · [SL-012](SL-012-apply-address-dropdown-admin-order-form.md) · [SL-014](SL-014-apply-address-dropdown-admin-msi-source.md)

## Model (DEC-025 — data-driven levels)

VN = **2 cấp**: `region` (Tỉnh/Thành) → `city` (Phường/Xã = ward). `country==VN` → `city` thành ward dropdown; non-VN → generic (incl. `sub_city` nếu country có data 3 cấp). VN logic ở `Secomm_VietNamAddress`; generic mechanism ở `Secomm_AddressDropdown` (DEC-019/025).

## Description

Hai config surface admin chứa address origin, đều đang có `city` = **text input**:

1. **Store Information** (`Stores → Configuration → General → Store Information`, path `general/store_information`) — origin address trên **PDF** (invoice/shipment/creditmemo) + "Print Order" header + store identity.
2. **Shipping Origin** (`Stores → Configuration → Sales → Shipping Settings → Origin`, path `shipping/origin`) — origin cho **TableRate matrix** + carrier default origin.

Ticket (merged theo user 2026-08-03) = apply cascade 2-level region→city(ward) vào **cả 2** config form. Ward persist vào `core_config_data` (`{path}/city`).

## Acceptance Criteria

- [ ] AC-1: **Store Information** form — chọn VN → cascade region→`city`(ward) render (ward dropdown phụ thuộc region).
- [ ] AC-2: **Shipping Origin** form — tương tự AC-1 (path `shipping/origin`).
- [ ] AC-3: Lưu `region_id` + `city`(ward) vào `core_config_data` cho cả 2 path; load lại pre-select đúng.
- [ ] AC-4: **Non-VN country** → generic Magento default (region updater + city text; + `sub_city` nếu country 3 cấp — DEC-025); không leak VN.
- [ ] AC-5: **PDF/print** (invoice/shipment/creditmemo/order print) hiển thị region + ward đúng từ Store Information.
- [ ] AC-6: **TableRate/carrier origin** nhận ward đúng từ Shipping Origin (verify TableRate rate dùng ward nếu relevant).
- [ ] AC-7: **Regression** — các config field khác + frontend + PDF + TableRate hiện hành nguyên vẹn.

## Technical Notes

- Cả 2 surface là **system.xml config form** (KHÔNG phải ui_component) → khác cơ chế SL-011/012/014. Region đã có Magento dynamic updater; thêm `city`(ward) dropdown phụ thuộc = **custom `source_model`** (wards by region, `directory_region_city`) + **`frontend_model`/JS** cascade khi region change.
- **VN logic ở `Secomm_VietNamAddress`** (country==VN → city=ward, label "Phường/Xã"); generic mechanism (data-driven, country-agnostic) ở AddressDropdown (DEC-025).
- Ward option provider reuse `CityLocaleCollection` / `Selector\City` (share SL-011/012/014).

## Files/Areas Affected

- **NEW/extend**: `Secomm_AddressDropdown` và/hoặc `Secomm_VietNamAddress` `etc/adminhtml/system.xml` — override/add `general/store_information/city` + `shipping/origin/city` (frontend_model + source_model).
- **NEW**: ward source model + frontend model (dropdown by region) cho config.
- `Secomm_VietNamAddress/` (VN adapter: country==VN gate + label).
- **KHÔNG affect**: storefront, order/customer address, db_schema.

## Risks

- Inject dependent dropdown vào **core config field** (`general/store_information/city`, `shipping/origin/city`) khác tinh tế → verify không break Magento config render/cache.
- PDF origin address sai → tài liệu gửi khách sai → QC PDF.
- Region updater mặc định Magento có thể xung đột custom JS cascade.
- Shipping Origin sai → TableRate rate sai (nếu ward relevant) → verify.

## Open Questions

- Q1: Approach — override core `system.xml` field `.../city` (frontend_model) hay plugin vào config form render? — plan.
- Q2: Ward source model cho config = reuse `Selector\City`/`CityLocaleCollection` hay source model riêng cho config context? — plan.

## Dependencies

- Share ward source model + VN adapter với SL-011/SL-012/SL-014.
- Liên quan [FEAT-006](../records/features/FEAT-006.md) GHTK (Shipping Origin = carrier origin/pickup parity).
- Architecture: [DEC-025](../records/decisions/DEC-025.md).

## Definition of Done

- [ ] Mini-spec/approach note (Mode B) — Q1 chốt
- [ ] AI pre-review pass
- [ ] TL review approved
- [ ] QC: Store Information + Shipping Origin config (VN 2-level + non-VN) + PDF/print origin + TableRate origin + regression
- [ ] DEC-019/025 compliance
