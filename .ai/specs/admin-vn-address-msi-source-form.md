# Mini-Spec: SL-014 - VN City dropdown cho admin MSI Source form

> **Project**: Secomm Launchpad | **Stack**: Magento 2.4.8-p5 + Hyva 3.x
> **Mode**: B | **Risk tier**: Tier 1 (reassess Tier 2 neu Source duoc dung lam shipping/pickup origin)
> **Status**: Draft - cho TL review

## Feature Overview

**Feature name**: VN 2-level address dropdown tren admin MSI Source form

**Ticket reference**: [SL-014](../tickets/SL-014-apply-address-dropdown-admin-msi-source.md)

**Feature type**: Enhancement

**Priority**: P2

MSI Source (`Stores -> Inventory -> Sources`) luu dia chi fulfillment trong
`inventory_source`. Form core `inventory_source_form` hien dung `city` input tu
do. Khi country la `VN`, admin can chon ward dung theo province; ward van phai
persist vao native `city`, khong them schema hoac field luu tru moi.

## User Stories

- **US-001**: As an inventory admin, I want Source dia chi VN co province -> city dropdown so that fulfillment origin dung ward.
- **US-002**: As an inventory admin, I want Source da luu duoc preselect dung province va city khi edit so that khong bi mat du lieu.
- **US-003**: As a maintainer, I want non-VN Source giu City input Magento mac dinh so that khong lam regress MSI o quoc gia khac.

## Acceptance Criteria

- [ ] **AC-1**: Khi country Source = `VN` va chon `region_id`, field `city` hien select, load options tu `GetListCity(region_id, area=adminhtml)`, va reload khi doi province.
- [ ] **AC-2**: Khi create/save Source VN, ward duoc ghi vao `inventory_source.city` va `region_id` vao `inventory_source.region_id`; edit Source preselect lai dung city da luu.
- [ ] **AC-3**: Khi country khac `VN`, `city` giu Magento native text input; khong ep ward dropdown hay thay doi data city hien co.
- [ ] **AC-4**: Source fields khac, Source create/edit, inventory assignment va checkout/MSI selection khong regress.
- [ ] **AC-5**: Response GraphQL stale sau khi doi province nhanh khong duoc ghi de len options cua province hien tai.

## Technical Notes

- Surface la Magento UI Component `inventory_source_form`, data scope address la `data.general`.
- Merge field `address.city` trong `Secomm_VietNamAddress`; dung component rieng, khong sua XML/JS core `Magento_InventoryAdminUi`.
- Component gate bang `country_id === 'VN'`, observe `region_id`, va bind ca input/select vao cung observable native `data.general.city`.
- Ward options reuse GraphQL resolver va data layer `Secomm_AddressDropdown`; khong duplicate VN master data.
- VN 2-level: `region` = province, `city` = ward. Khong render `sub_city` cho Source VN (DEC-025).
- Non-VN giu native City input. Label surface nay giu `City` theo Magento locale, khong hard-code Ward/Commune.
- Persist qua Source API/repository core; khong them column, observer save hay mapper neu native field da duoc data provider serialize dung.

## Dependencies

| Dependency | Type | Status | Notes |
|---|---|---|---|
| [DEC-025](../records/decisions/DEC-025.md) | Architecture | Accepted | VN 2-level, ward persist native city |
| DEC-019 / DEC-020 | Architecture | Accepted | Generic data boundary, ward data model |
| `Magento_InventoryAdminUi` | Magento module | Available | Owns `inventory_source_form` and source save flow |
| `Secomm_AddressDropdown` | Internal module | Available | Provides `GetListCity` data resolver |

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| UI component merge sai scope lam city khong persist | M | H | Bind input/select cung `data.general.city`; QC create, save, reload |
| Async region/city hydration reset city edit | M | M | Preserve gia tri initial; clear chi khi admin doi country/region; request ID guard |
| Source duoc dung lam pickup/shipping origin | M | H | Verify source flow inventory/checkout; reassess Tier 2 neu rate logic doc city |
| Non-VN bi doi thanh select | L | M | Country gate `VN` duy nhat; QC US native input |

## Out of Scope

- Customer address, order address, Store Information va Shipping Origin.
- `sub_city` generic cho cac country 3 cap.
- Canonical `ward_id` persistence, database migration va GraphQL schema change.
- Carrier/rate algorithm changes.

## Test Notes

- Create Source VN: country -> province -> city, save, reload va preselect.
- Edit Source VN: city luu tru hop le va city khong thuoc province dang chon.
- Source US: City van text input va save/reload binh thuong.
- Doi country/province lien tuc: chi options cua province cuoi cung duoc hien.
- Verify source assignment, inventory source listing va checkout/MSI selection sau save.
