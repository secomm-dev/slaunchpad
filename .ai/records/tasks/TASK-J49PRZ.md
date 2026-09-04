---
id: TASK-J49PRZ
type: task
title: 'LocationHierarchyProvider + GraphQL addressLocations / addressSchema'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: completed
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001]
decision_assessment: material
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/Api/
  - app/code/Secomm/AddressDropdown/Model/
  - app/code/Secomm/AddressDropdown/etc/schema.graphqls
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-J49PRZ] LocationHierarchyProvider + GraphQL addressLocations / addressSchema

<!-- CANONICAL TASK RECORD — Phase 1. GraphQL API contract mới → Mode A. -->

## Summary

`LocationHierarchyProviderInterface` (getRootLocations / getChildLocations / hasChildren / getLocationPath) trên nền `CityRepository`/collections hiện có + membership filter; GraphQL mới `addressLocations(regionId|parentCityId, profile)` + `addressSchema(countryId, profile)`.

## Mini Spec

### Goal
Một API duy nhất, ID-canonical, không expose table structure, phục vụ renderer generic và carrier/integration modules.

### Expected Behavior
1. `getRootLocations(regionId, profileCode)` — city có `parent_city_id IS NULL`, lọc membership.
2. `getChildLocations(parentCityId, profileCode)` — children trực tiếp, lọc membership; membership semantics: node có entry → theo entry; không có entry → inherit tư cách parent; profile không có membership data nào → all-nodes (BC với data hiện tại).
3. `hasChildren(cityId, profileCode)`, `getLocationPath(cityId)` (root→node, cho edit-mode prefill).
4. DTO `LocationNodeInterface`: id, defaultName, name (locale-resolved), depth, parentCityId, regionId.
5. GraphQL: `addressLocations` / `addressSchema` — parameterized, locale từ context; **cacheable** theo profile+parent (khác resolvers cũ `@cache(cacheable: false)`).
6. `GetListCity` / `GetListSubCity` giữ nguyên hành vi (shim hoá ở TASK-YQSS3M).

### Constraints / Rules
- Không N+1: children query 1 statement có join name theo locale; membership check trong cùng query hoặc sub-select.
- Index `(region_id, parent_city_id)` từ TASK-9AEAQQ phải đủ cho 2 query chính — verify EXPLAIN.
- Không SELECT *; parameterized everything.

### Out of Scope
- Renderer (TASK-3T3NSV); sửa resolvers cũ (TASK-YQSS3M).

### Acceptance Criteria
- AC-001: Root/children/path đúng trên data VN hiện tại (3.313 nodes, depth 1) + fixture depth 2-3 tạo trong test.
- AC-002: Membership subtree-claim + inheritance đúng (unit + integration với fixture).
- AC-003: GraphQL query từ storefront anonymous OK; locale đúng theo store header.
- AC-004: EXPLAIN không full-scan trên 14k-node fixture.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 1, Step 3.

## Implementation Notes

Đã triển khai 2026-08-25 (chờ TL code review):

- **Pre-review fix 2026-08-26** (evidence: `PRE-REVIEW-2026-08-26-FEAT-2PZQKJ-YA2C0W.md` W1): `AddressLocationsGraphql` giờ validate `profile_code` qua `ProfilePool::getProfile()` trước khi gọi provider — profile chưa khai báo → `GraphQlNoSuchEntityException` đúng như contract đã ghi (trước đó provider xử lý profile lạ theo all-nodes BC mode ⇒ catch là dead code; unit test cũ mock-throw sai hành vi thật). Suite 37 tests / 72 assertions xanh.
- **TL code review approved 2026-08-26** → completed. HTTP QC multi-locale đã verify trong TASK-3T3NSV (W2).

- **Contract + DTO**: `Api\LocationHierarchyProviderInterface` (4 methods theo spec) + `Api\Data\LocationNodeInterface` (id-canonical; thêm `hasChildren` — mở rộng hữu ích superset của spec cho renderer stop-condition).
- **Impl** `Model\LocationHierarchyProvider`: listing = 1 statement (children + locale name LEFT JOIN + EXISTS has_children); membership semantics D5 — region claim / nearest-city-claim walk (cycle-guard 16) / all-nodes BC khi profile chưa có membership rows; filter nhánh claimed-only dùng 1 query IN. `getLocationPath` trả root→node, broken chain → [].
- **GraphQL**: `addressLocations` (bắt buộc `profile_code`, đúng 1 trong region_id/parent_city_id, id dương; NoSuchProfile → GraphQlNoSuchEntityException) + `addressSchema` (country default qua resolver hoặc explicit profile_code; unmapped → [] = native; label/placeholder dịch server-side qua `__()`). Cả hai `@cache(cacheable: true)`. Schema cũ giữ nguyên.
- di.xml + preference provider; 12 unit tests mới.

Findings trong lúc triển khai:
1. **Named bind trong JOIN ON không hoạt động qua direct fetch** của Magento DB adapter (pattern `:param` trong join chỉ chạy qua collection load path) — chuyển sang `quoteInto` (adapter-quoted, vẫn injection-safe). `quoteInto(null)` sinh `''` không phải `NULL` → root query phải tách nhánh `IS NULL` tường minh.
2. ReCaptcha GraphQl plugin chạy beforeResolve trên mọi resolver (đọc ResolveInfo->operation) — smoke test instantiate class trực tiếp thay vì OM Interceptor; production GraphQL path không bị ảnh hưởng.

## Verification

- [x] AC-001: path depth 1-3 + root/children đúng trên data thật (168 HCMC wards) + fixture depth-3 — evidence 01 (TEST 1-3)
- [x] AC-002: membership subtree-claim (children+grandchildren inherit) vs node-only (blocked) vs all-nodes BC — evidence 01 (TEST 2-4)
- [x] AC-003: GraphQL resolvers hoạt động qua direct call (shape output + schema 2 levels + translated-label passthrough); HTTP anonymous check thuộc QC TASK-3T3NSV — evidence 01 (TEST 5)
- [x] AC-004: EXPLAIN root query = ref `DIRECTORY_REGION_CITY_REGION_ID_PARENT_CITY_ID`, children = ref `DIRECTORY_REGION_CITY_PARENT_CITY_ID` — không full scan — evidence 01 (TEST 6)
- [x] Unit suite: 34 tests / 65 assertions xanh — evidence 02
- Data integrity: cleanup sạch về 3.313 rows sau fixture — evidence 01 (dòng cuối)
- Evidence: `.ai/runtime/evidence/TASK-J49PRZ/`

## Audit findings — 2026-08-28 (post-completion audit note)

Record đã completed 2026-08-26 — audit tĩnh 2026-08-28 ghi nhận 1 defect trên deliverable (**không mở lại record**; fix đề xuất theo TASK-YQSS3M findings):

- `Model/LocationHierarchyProvider.php:270` — `order('name ASC')` dùng collation bảng (`utf8_general_ci`) → sort tiếng Việt sai (Đ/đ sau Y; target §8 yêu cầu Đ==D). *(Correction 2026-08-28: ghi chú "DB experiment xác nhận `utf8_unicode_ci` đúng" ở trên là SAI — re-verify cho thấy không collation MySQL 5.7 nào cho Đ==D, kể cả `utf8_unicode_ci`.)* Ảnh hưởng mọi consumer của `getRootLocations`/`getChildLocations` (renderer TASK-3T3NSV, GraphQL shims, CascadingOptions). **Đã fix upstream 2026-08-28** trong `fetchChildren` (REPLACE Đ/đ→D/d trong ORDER BY + tiebreaker `city_id`) — chi tiết ở TASK-YQSS3M findings item 1.

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted — D5 membership semantics)
