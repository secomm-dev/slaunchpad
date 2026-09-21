# Evidence — TASK-JZXM66: City/Area admin self-service (2026-09-18)

- Launchpad suite: **67 tests / 137 assertions OK** (38 baseline + 29 mới: provider city
  helpers 10, importer templateColumns+region/city validation 9, CityReferenceBuilder 6 —
  UTF-8+BOM/escaping/parent chain/filter, ImportTemplateBuilder 4 — header == importer
  schema + city_name).
- `setup:di:compile` PASS (0 errors, độc lập verify).
- Dependency fences sạch (khôngMageplaza/Secomm source edits; không carrier deps).
- Runtime fallback paths KHÔNG đụng: coordinator/policy/outer seam/city matching nguyên vẹn.

## Deliverables (chi tiết trong report TASK record)
- Selector: `city_code` text→select, AJAX `launchpad_mptablerate/city/options?region=`
  (ACL `Mageplaza_TableRateShipping::method`, wildcard "All / *" luôn đầu tiên, edit preselect
  + stale-code warning giữ raw code), JS `view/adminhtml/web/js/city-selector.js` scope per-modal.
- Reference CSV: `city/referenceCsv[?region=]` — `country_code,region_code,region_name,
  city_code,city_name,parent_city_code,parent_city_name`, UTF-8+BOM, fputcsv, bỏ row không code.
- Import Template: `city/importTemplate` — header EXACT importer superset + `city_name`
  (importer strip defensively); example row theo region + wildcard demo row.
- Validation: unknown code (đã có) + city∉region (form save + import, wildcard/non-numeric
  region bỏ qua — legacy-compatible).
- README: manual + bulk flows + identity contract.

## TL review round (2026-09-18) — RECOMMENDED FIX applied
`ResourceRateSavePlugin`: validation (unknown code + region/city consistency) moved to
`beforeSave` — rejection ABORTS the Mageplaza rate save, **no partial-save**; `afterSave`
persists the validated constraint only. +5 plugin tests (mismatch aborts / unknown aborts /
valid persists / wildcard inert / no-key inert). Suite **72 tests / 143 assertions OK**;
compile PASS. Partial-save semantics no longer exist.
