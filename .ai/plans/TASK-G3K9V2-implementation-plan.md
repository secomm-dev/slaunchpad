# Implementation Plan — TASK-G3K9V2 (ShippingCore Zone & Carrier Coverage Admin UX)

| Specification | SPEC-FEAT-QA23PZ (../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md) + delta embedded trong TASK-G3K9V2 |
|---|---|
| **Work item** | TASK-G3K9V2 — slice D của FEAT-QA23PZ (admin UX extension) |
| **Mode** | A |
| **Depends** | TASK-1EK2MW + TASK-ZA10BT + TASK-BYT2WK (đã dev-complete trên working tree) |
| **Risk** | medium — đụng GHN system.xml (relocate UI, giữ runtime readers), VietNamAddress additive API |

## Steps

1. **VietNamAddress additive read API** — `VnAddressUnitProviderInterface`: + `getByLevel(scheme,
   level)` (provinces level-1), + `getByRegion(scheme, regionCode, level)` (wards theo
   `region_code`, level-2). Impl SQL: WHERE scheme/level[/region_code] ORDER name_vi ASC —
   tránh hoàn toàn `parent_code` (seeded DB defect: NULL trên mọi row con). Unit tests.
2. **Zone form UX (ShippingCore)** —
   - `ProvinceOptions` source: đổi sang `getByLevel(VN_ADMIN_2025, 1)` (bỏ Magento directory
     region dependency); label `name (VN-XX)`; sort label.
   - `WardOptions` controller: đổi sang `getByRegion(..., 2)`; label `name (CODE)`.
   - JS `searchable-multiselect.js` + template `templates/form/element/searchable-multiselect.html`:
     search box filter client-side, select size lớn, selected summary; giữ `optionsUrl`/
     `provincesValue` behavior (AJAX cascade) + AUTO-CLEAR stale values khi đổi provinces.
   - Form XML: + Country (disabled select, fixed `Vietnam (VN)` — display only, không persist);
     provinces + wards dùng component mới; REMOVE `exclude_ward_codes` field.
   - `ZoneFormDataProvider`: optionsUrl chỉ cho include_ward_codes; + read-only
     `assigned_carriers` (disabled multiselect, derive từ CarrierRegistry × ScopeConfig raw).
   - Listing XML + Grid Collection: bỏ `exclude_ward_count` column/expression (UI only).
3. **Carrier Coverage screen (ShippingCore)** —
   - `Model/CarrierCoverage/CarrierRegistry` (DI array carriers code→label).
   - `Model/CarrierCoverage/Availability` (admin enum: ALL | SELECTED_ZONES |
     ALL_EXCEPT_SELECTED_ZONES — KHÔNG đụng runtime `Api\Address\DestinationScope`).
   - `Model/CarrierCoverage/PolicyConfig` (admin-layer read ScopeConfig + save qua
     WriterInterface paths `carriers/<code>/{destination_scope,allowed_zone_codes,
     rate_source_mode,address_resolution_policy}` DEFAULT scope + `cleanType('config')`).
   - `Model/CarrierCoverage/Validator` (availability enum; zone pairing ≥1 + tồn tại;
     RateSourceMode/AddressResolutionPolicy enums qua `all()`).
   - Source models: `AvailabilityOptions`, `RateSourceModeOptions`, `AddressResolutionPolicyOptions`
     (labels mirror text GHN system.xml cũ).
   - Controllers `Coverage/{Index,Edit,Save}` (ACL const `::carrier_coverage_manage` cho
     edit/save; `::carrier_coverage` cho index), layout index (template table đơn giản) + edit
     (UI form `secomm_shippingcore_coverage_form.xml`, DataProvider đọc registry + config);
     ACL + menu XML.
   - di.xml ShippingCore: registry argument mặc định rỗng; Ghn/etc/di.xml đăng ký `secomm_ghn`.
4. **GHN relocation** — system.xml: REMOVE 4 fields (rate_source_mode, address_resolution_policy,
   destination_scope, allowed_zone_codes), thay note trỏ sang Secomm → Carrier Coverage; DELETE
   `Model/Config/Backend/AllowedZoneCodes.php` + `Model/ConfigBackendAllowedZoneCodes.php` (bản
   copy PSR-4-mismatch, không reference); readers `GhnConfig` giữ nguyên (runtime). CHANGELOG/README.
5. **Tests** — ProvinceOptions (canonical level-1), WardOptions (region filter + label +
   ajax guard), Coverage Validator (cả reject paths), PolicyConfig (paths + cache clean +
   default scope), CarrierRegistry, ZoneFormDataProvider assigned_carriers, ValidatorTest case
   stale ward (nếu thiếu). Chạy suites ShippingCore + Ghn + VietNamAddress.
6. **Docs/records** — SPEC-FEAT-QA23PZ append "Delta 2026-09-22 (TASK-G3K9V2)"; USER_GUIDE
   ShippingCore + README/CHANGELOG (ShippingCore, Ghn, VietNamAddress); architecture/
   address-shipping.md §35 delta; CURRENT_STATE/NEXT_TASK; evidence.

## Validation

`vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "..."; bin/magento
setup:di:compile; bin/magento cache:flush; bin/project-ai-validate --check-specs --check-records
--check-identity`; admin smoke: zone form + coverage screen.

## Risks

- TASK-R8WR1R (song song) đã add runtime ALL_EXCEPT_SELECTED_ZONES cùng working tree — không
  conflict file (runtime files vs admin files tách bạch); admin surface CHẶT hơn runtime một
  điểm: zone mode + 0 zone bị reject ở save (runtime ALL_EXCEPT + rỗng ≡ ALL).
- Coverage UI ghi DEFAULT scope (fields cũ store-scopable) — P1 single-store; documented.
- UI save zone không còn posting excludes → excludes=[] trên mỗi UI save (contract giữ, data
  không có trong production — feature uncommitted).
