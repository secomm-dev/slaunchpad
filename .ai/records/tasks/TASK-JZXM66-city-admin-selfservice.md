---
id: TASK-JZXM66
type: task
title: 'TableRate City/Area admin self-service — cascading selector, City Reference CSV, Import Template, region/city validation'
project_code: SLP
parent: {type: feature, id: FEAT-GGTWXW}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: embedded-mini-spec
risk: low
status: in_progress
priority: medium
decision_assessment: none-material
decisions: []
components: [CMP-SHIPPING]
source_areas: [app/code/Launchpad/MageplazaTableRate/]
changes_project_state: true
created: 2026-09-18
updated: 2026-09-18
owner: [dev]
related_tickets: [TASK-5XQXZK, TASK-78PVR1]
---

# [SLP][FEAT-GGTWXW][TASK-JZXM66] TableRate City/Area admin self-service

## Embedded Mini-Spec

### Goal
Merchant tự cấu hình TableRate City/Area mà không cần biết/tự gõ `city_code`: cascading
selector (Country → Region → City/Area, searchable, wildcard), edit resolve label + stale-code
warning, Download City Reference CSV, Download Import Template, region/city validation.

### Scope
In: admin UX + CSV reference/template + validation + tests + README. Out (freeze): ShippingCore
contracts, fallback semantics/modes/policies, outcome flow, membership, outer seam, Mageplaza
calculation, external address lookup, zone engine.

### Approach
Reuse `Secomm_AddressDropdown` `directory_region_city` (code/name/region) — không bảng mới,
không dataset mới; rate form = preference subclass hiện có (`Block\...\Tab\Rate\CityForm`) nâng
cấp thành region+city selects (city list AJAX theo region từ AddressDropdown collection);
persist vẫn `city_code`; reference/template CSV = admin controller mới (ACL
`Mageplaza_TableRateShipping::method`), generate từ DB (UTF-8, escaping, region filter);
template columns = EXACT superset importer (`MptablerateImport::$_columnNames`); importer thêm
validate city thuộc region khi cả hai có điều kiện; wildcard `*`/empty giữ nguyên.

### Constraints
Không hardcode district_id/ward_id; label chỉ hiển thị, identity = `code`; stale stored code →
warning + giữ raw code; không fuzzy-match import; CSV identity = `city_code`; minimal
upgrade-safe extension (không rewrite Mageplaza form); legacy CSV không-city vẫn chạy.

### Rules
Tests: selector data (region-filtered/correct code/wrong-region reject), reference CSV
(UTF-8/escaping/filter), template columns == importer schema, import valid/wildcard/unknown/
mismatch/legacy; export→import round-trip giữ city mapping. README: manual + bulk workflows.
