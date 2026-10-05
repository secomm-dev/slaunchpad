---
id: TASK-Z6SK3T
type: task
title: 'GetListCity performance remediation — CityData N+1, GraphQL BC shim, addressLocations JS migration'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-10-02
updated: 2026-10-02
decisions: [DEC-TASKZ6SK3T-001]
decision_assessment: material
related_work_items: [TASK-YQSS3M, FEAT-YVN39K, TASK-6MKF0V]
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/CustomerData/CityData.php
  - app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php
  - app/code/Secomm/AddressDropdown/Helper/Address.php
  - app/code/Secomm/AddressDropdown/view/frontend/web/js/
  - app/code/Launchpad/Osc/view/frontend/web/js/action/
  - app/code/Secomm/VietNamAddress/Model/Import/VnAddressSchemeImporter.php
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: 2026-10-02
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-Z6SK3T] GetListCity performance remediation — CityData N+1, GraphQL BC shim, addressLocations JS migration

<!-- Perf audit 2026-10-02: SQL không phải nút thắt (0.7ms warm/query); nút thắt = N+1 CityData + GetListCity uncached end-to-end + 0 client memo. Hoàn thiện phần còn lại của TASK-YQSS3M (shims + JS callers) + fix CityData/Helper. Ghi nhận rủi ro defer của FEAT-YVN39K AC-011/Q1. -->

## Summary

Audit hiệu năng "get list city từ region" xác định 5 root causes: (1) `CityData::getSectionData()` N+1 — 1.190 query/3.321 ORM object trên cache miss; (2) `GetListCity` uncached end-to-end — `@cache(cacheable: false)`, resolver ORM collection filesort, mọi call site POST /graphql (FPC bỏ qua), 0 client memo → 1.6s/request developer mode; (3) `Helper\Address::getCityNameByDefaultName()` chạy query 2 lần; (4) OSC gọi trùng `addressSchema` ×2 (shipping + billing); (5) `ValidateVietNamWard` COUNT(*) mỗi rate collection. Fix: CityData về 2 fetchAll + cache type có tag; helper 1 fetch + memo; GetListCity thành BC shim qua `LocationHierarchyProvider` (hoàn thiện TASK-YQSS3M items 3-4); 6 JS call sites migrate sang `addressLocations` + shared cache module.

## Mini Spec

### Goal
Sau task này: mỗi region change trên storefront trả đúng **1 network request có memo** (page-session), section `city-data` build bằng **2 query** thay vì 1.191+, và không còn ORM collection trên hot path của GetListCity (mapped country). Điều kiện cho việc bỏ legacy path (TASK-K09G8Y).

### Expected Behavior
1. **CityData**: `getSectionData()` dùng đúng 2 `fetchAll` (cities JOIN name theo locale; regions `WHERE region_id IN (SELECT DISTINCT region_id FROM directory_region_city)`), group trong PHP. **Output shape byte-identical** (mixins try/catch fallback — tương đương 100% với region không có city). Cache: giữ store-scoped key + TTL 3600s; thêm cache type `secomm_address_city` + save với tag để `cache:clean` purge được.
2. **Importer CACHE_TYPES**: thay entry `graphql_query` (type không tồn tại trong install — `cache:status` xác nhận) bằng type thật (`graphql_query_resolver_result`); hiện tại `cleanType()` throw trong try/catch → toàn bộ clean sau import bị skip lặng lẽ.
3. **Helper**: 1 `fetchOne` duy nhất (check `!== false && !== null` thay truthy), per-request memo theo `defaultName|regionId|locale`, tie-break `d.city_id` (dataset có ward trùng tên `Thanh An` ×2).
4. **GetListCity shim**: giữ `@deprecated` + schema không đổi (không `graphql:dump`). `region_id` String → int (non-numeric/≤0 → `[]`, không throw). Country có profile → `LocationHierarchyProvider::getRootLocations()` (root-only — KHÔNG tái hiện flat mixed-tier list, TASK-6MKF0V AC-3), map node → legacy shape. Country không có profile hoặc `area=ADMINHTML` → giữ legacy `CityLocaleCollection` (BC locale + non-profile dataset). Không wire resolver cache ở đợt này (follow-up optional SA).
5. **JS migration**: module mới `Secomm_AddressDropdown/view/frontend/web/js/model/address-location-cache.js` (Map theo `<profile>|<regionId>` + in-flight Promise dedupe + memo `addressSchema(countryId)` + `clear()` từ bindCountryChange). Migrate 6 call sites: `address-dropdown.js`, `action/shipping-address-dropdown.js`, `action/billing-address-dropdown.js`, `view/cart/shipping-estimation-mixin.js` (Secomm) + 2 bản `Launchpad_Osc` copies. Bỏ fire empty `region_id` lúc page load trong `address-dropdown.js`. Post-condition: grep `GetListCity` trong app/code chỉ còn resolver + schema + tests.

### Constraints / Rules
- **Không sửa schema.graphqls** — response shape `ListCityGraphqlOutput` giữ nguyên (BC contract).
- Không sửa `app/code/Mageplaza/*`; bản JS trong `Launchpad_Osc` là project-owned (DEC-TASKFMAN1B-001).
- Persist giá trị `city` vẫn là `default_name` (BC payload carriers) — chỉ đổi nguồn đọc, không đổi contract lưu.
- Mỗi module đổi: CHANGELOG riêng.
- **Tier-2 gate**: steps shim + JS migration (OSC checkout flow + GraphQL surface) chỉ code sau khi TL/SA duyệt Mini-Spec này. Steps CityData + Helper (server-side, không đổi API) được duyệt scope trong plan 2026-10-02.
- Master switch TASK-SEC-A5 giữ nguyên ở mọi server path mới.

### Out of Scope
- Wire `Magento_GraphQlResolverCache` identity cho `addressLocations` (follow-up SA); parallelize 3 roundtrip prefill `schema-edit.phtml`; dead `GetListRegion` reference trong `address-dropdown.js` (flag riêng); default-mixin thiếu master-switch gate (flag riêng); Magefan autoload 500 (đã fix riêng ngoài task, 2026-10-02).

### Acceptance Criteria
- AC-001: Cold section load `city-data` chạy đúng 2 query (proof `dev:query-log`, trước: 1.191+) — evidence.
- AC-002: Output `city-data` byte-identical với trước (so JSON trước/sau cùng store) — evidence.
- AC-003: `GetListCity` trên region VN trả cùng shape + thứ tự như trước (root-only), non-numeric region_id → `[]`, `area=ADMINHTML` giữ DEFAULT_LOCALE — unit test + so response trước/sau.
- AC-004: OSC checkout vi_VN: đúng 1 request/region/page (DevTools), 0 duplicate `addressSchema`, cascade + saved-address restore + place order (TableRate) OK — QC L3.
- AC-005: Cart estimate, address book (2 renderer), Luma checkout, admin customer/order form cascade hoạt động như trước — QC.
- AC-006: `cache:clean secomm_address_city` purge được section cache; import scheme không còn để stale cache 1h — evidence CLI.
- AC-007: Grep `GetListCity` trong `app/code` chỉ còn resolver + schema.graphqls + tests — evidence.

## Approach

Plan đã duyệt 2026-10-02 (plan mode): CityData 2 fetchAll + cache type + importer fix → Helper fix → **[GATE Tier-2]** shim + JS migration → tests + đo trước/sau. Baseline đã đo: GetListCity POST = 1.617s (developer mode, file cache); N+1 SQL = 263ms/1.190 query vs 81ms single-query.

## Implementation Notes

2026-10-02: Bắt đầu. Fix môi trường Magefan autoload 500 (`composer dump-autoload`) để đo
được — ngoài scope task, đã ghi evidence.

2026-10-02: **Steps 1-2 + 5 hoàn thành trong cửa sổ plan approval** (server-side, không đổi
API surface):
- CityData: 1.191 → 2 fetchAll; cold 525.9ms → 11.9ms; payload 1.190 → 34 region keys,
  VN subset byte-identical (compare_citydata.php).
- New cache type `secomm_address_city` (+ `cache.xml` + `Model/Cache/Type.php`) — đã
  `cache:enable`; importer `CACHE_TYPES` fix `graphql_query` (type không tồn tại, clean bị
  skip lặng lẽ) → `graphql_query_resolver_result` + thêm `secomm_address_city`.
- Helper `getCityNameByDefaultName`: 1 fetchOne + memo + tie-break `d.city_id`.
- ValidateVietNamWard (Plugin\Cart): collection+COUNT → 1 fetchOne + per-request memo.
- Tests: +3 file (CityData 5, Helper Address 5, Ward Validator 5) — AddressDropdown 106,
  VietNamAddress 202, all green; `setup:di:compile` OK; validator = baseline 56 FAIL.

**GATE Tier-2: ĐÃ MỞ** — DEC-TASKZ6SK3T-001 **ACCEPTED** 2026-10-02 (user approval qua
`/approve`; DECISIONS.md index updated).

2026-10-02: **Steps 3-4 hoàn thành**:
- Resolver shim: mapped country → `LocationHierarchyProvider::getRootLocations()` (root-only);
  unmapped + admin → legacy collection; response byte-identical pre/post (168/168 rows);
  schema.graphqls không đổi. `GetListCityGraphqlTest` rewrite (7 test).
- FE: module mới `address-location-cache.js` (page-session memo + in-flight dedupe +
  addressSchema memo) — 7 call sites migrate (4 Secomm + 2 Launchpad_Osc + edit.phtml inline
  Alpine memo); OSC addressSchema ×2 → 1 POST/page; empty-region fire bỏ ở Luma widget.
- Post-condition AC-007 đạt: grep `GetListCity(` chỉ còn schema.graphqls:2.
- Suites 109 + 202 green; node --check 7/7; DI compile OK; validator = baseline 56 FAIL.

**Còn mở:**
- AC-004/005/006 (QC L3 browser + CLI import invalidation proof): PENDING — GET pages vẫn
  500 do Mageplaza_RMA schema outdated (blocker môi trường, chờ quyết setup:upgrade/disable).
- Follow-up flag: `GetListRegion` dead reference trong address-dropdown.js; default-mixin
  thiếu master-switch gate; resolver-cache identity cho addressLocations (SA optional).

2026-10-02: **Sort regression fix (Đ/đ đáy list) — user report sau steps 3-4.** Root cause
KHÔNG phải migration: `d8bad508` (TASK-7HVGAB, 2026-09-07) đã xoá REPLACE normalize Đ/đ
khỏi provider ORDER BY ("language-agnostic") → trên utf8mb4_general_ci, Đ sort sau Z; các
surface có client `vnSortKey` che được, các surface còn lại lộ. Fix: builder dùng chung
`Model/ResourceModel/CitySort::expression()` (nguyên dạng proven trước d8bad508) áp đồng bộ
4 điểm sort (provider / CityLocaleCollection / CityData / Helper). Verify: Đ index 163-167 →
55 (region 1205), monotonic normalized key, addressLocations ≡ shim. Guard tests d8bad508
đảo thành guard normalize. **Đảo chiều một phần quyết định TASK-7HVGAB theo yêu cầu user
(tác giả commit).** Suites 110 + 202 green; validator = baseline 56.
