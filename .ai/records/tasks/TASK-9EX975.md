---
id: TASK-9EX975
type: task
title: 'Admin address surfaces switch to schema-driven renderer + admin hierarchy add-child CRUD'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-08-25
updated: 2026-09-03
decisions: [DEC-FEAT2PZQKJ-001, DEC-FEATE2HM1J-001]
decision_assessment: material
components:
  - CMP-ADDR
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/AddressDropdown/Plugin/Adminhtml/
  - app/code/Secomm/AddressDropdown/view/adminhtml/
  - app/code/Secomm/VietNamAddress/view/adminhtml/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-03
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-9EX975] Admin address surfaces switch to schema-driven renderer + admin hierarchy add-child CRUD

<!-- CANONICAL TASK RECORD — Phase 2. Admin forms đụng customer/PII + order → Mode A, Tier-2. Follow-up của FEAT-E2HM1J surfaces. -->

## Summary

Các admin surface đã có cascade (customer address form qua provider-mixin, order create/edit form, Store/Shipping Origin config city, MSI Source — per DEC-FEATE2HM1J-001) chuyển cơ chế render sang schema-driven: đọc resolved schema thay hardcode region→city→sub_city; admin cascade JS dùng GraphQL mới.

## Mini Spec

Task gồm 2 slice: **Slice A** — cascade surfaces switch schema-driven (spec gốc); **Slice B** — admin hierarchy add-child CRUD (TL confirm mở rộng scope 2026-09-03, từ review audit §5/§6/§19 + TL review 2026-09-03).

### Goal

**Slice A**: Admin render theo profile giống hệt frontend — cùng schema, cùng GraphQL, cùng dừng-ở-leaf semantics; loại bỏ nhánh render song song thứ hai.
**Slice B**: Admin CRUD tạo/sửa node city BẤT KỲ tầng ("chọn option → add child"); depth là implicit qua chuỗi `parent_city_id` — không lưu cột depth.

### Expected Behavior

**Slice A**
1. Admin cascade JS (VietNamAddress `address-cascade.js`, `source-city.js`, AddressDropdown `provider-mixin.js`/`address-city.js`) gọi `addressSchema` + `addressLocations`; số level render theo schema của country.
2. AJAX-gating admin form (insertForm async — pattern SL-011): cascade root-scope + hydrate sau khi form load, giữ nguyên correction hiện có.
3. Store Information + Shipping Origin `frontend_model` city field: options theo profile hierarchy.
4. Mỗi surface persist đúng native store của nó (customer_address_entity / sales_order_address(+quote) / core_config_data / inventory_source) — nguyên tắc DEC-FEATE2HM1J-001 giữ.
5. Server-side validation theo profile: plugin validate leaf ∈ path hợp lệ (mở rộng pattern `ValidateVietNamWard` — rule sống ở adapter).

**Slice B — admin add-child CRUD** (UX chốt TL 2026-09-03: select phẳng + indent theo depth, KHÔNG tree UI)
1. Contract: `CityInterface` + `CityModel` + mapper + `GetListQuery` thêm `PARENT_CITY_ID` (+ `code`); grid có filter theo parent cho action Add-child.
2. Form `city_form.xml` thêm: `parent_city_id` — MỘT select phẳng load toàn bộ subtree của region qua `addressLocations` (canonical, đã hỗ trợ `parent_city_id`; các tầng fetch đệ quy theo depth — bounded ≤ MAX_DEPTH), option đầu "— trực tiếp dưới Region —" = NULL parent; indent `—` lặp theo depth. Kèm input `code` (optional; unique per region+parent).
3. Grid `city_listing.xml` thêm cột `code`, `parent` (default_name của cha), `level` (depth, computed server-side khi list — ancestor walk bounded, không thêm cột DB); actions column thêm **Add child** → `*/city/new?region_id=X&parent_city_id=Y` (form mở với parent đã khoá hiển thị readonly-context).
4. `SaveCommand` validation (MIRROR rules của `HierarchyImportService`, dùng chung const/logic — không copy): parent tồn tại + cùng `region_id`; chặn self-parent + cycle (walk ancestors bounded); depth kết quả ≤ MAX_DEPTH; trùng `(region_id, parent_city_id, code)` → lỗi thân thiện (không raw MySQL exception). Đổi parent khi edit đi qua cùng validation.
5. Data-quality note: node vượt depth của profile đang claim region → vẫn lưu được nhưng grid + form hiện cảnh báo (schema renderer sẽ không render node đó) + warning log.

### Constraints / Rules
- Cơ chế injection khác nhau per surface (ui_component / block-form / system.xml) — mini-spec per surface khi triển khai nếu lệch.
- Tier-2: customer/PII + order surfaces cần TL review + QC đầy đủ.
- Không leak country-specific vào generic JS (DEC-FEATJSZQV3-003).
- **Slice B: KHÔNG thêm cột `depth` vào DB** — depth derived (DEC-FEAT2PZQKJ-001); KHÔNG đụng bảng/cột nào ngoài `directory_region_city(+)`; validation tái dùng const của `HierarchyImportService`.
- Slice B là directory data (không payment/order) nhưng vẫn Mode A — TL review khi code.

### Out of Scope
- Storefront (TASK-3T3NSV); removal sub_city UI legacy (TASK-K09G8Y).
- Slice B: tree UI (TL chốt select phẳng); seed/import dataset (TASK-9394A9/ADT94K); chính sách lọc depth cho cascade forms (thuộc Slice A AC-001).
- Bug phụ ghi nhận 2026-09-03 — ticket riêng, không nhét vào đây: `City/Save.php` nhánh exception-edit redirect dùng `region_id` làm `city_id`; `SaveCommand::checkAndDeleteCityNames` string-interpolated WHERE (pattern SQLi — họ audit YA2C0W); `CityLocaleCollection` join cứng en_US (audit §5).

### Acceptance Criteria
- AC-001: 4 admin surface render đúng theo `vn_current` (2 levels) + fixture depth-2 profile; label từ schema.
- AC-002: Prefill edit mode đúng qua path API trên mọi surface (kể cả form AJAX load).
- AC-003: Save persist đúng native store từng surface; validation chặn leaf sai province/path.
- AC-004: QC admin regression: customer address modal, order create/edit, config save, MSI source save.
- **AC-B1**: Add child từ grid row → form prefill parent (readonly-context); save OK; cột Level hiện parent+1.
- **AC-B2**: Đổi parent cùng region OK; parent khác region / chính nó / node con cháu (cycle) → blocked, message rõ.
- **AC-B3**: Node vượt depth profile → lưu được + cảnh báo; `addressSchema`/`addressLocations` không trả node đó vào render path.
- **AC-B4**: Trùng `(region_id, parent_city_id, code)` → lỗi thân thiện.
- **AC-B5**: Unit tests SaveCommand validation (self/cycle/cross-region/max-depth/duplicate-code) xanh; `setup:di:compile` + validator pass.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 2, Step 8 (Slice A). Slice B: implement sau Slice A hoặc độc lập (không phụ thuộc code nhau — Slice B chỉ cần `addressLocations` đã có).

## Implementation Notes

Chưa triển khai (proposed — phụ thuộc TASK-J49PRZ + 3T3NSV pattern).

2026-08-26 — User prioritization: **defer sau TASK-YQSS3M (cart) + TASK-FMAN1B (OSC)**; admin surfaces giữ hành vi legacy cho đến khi task này được chọn triển khai (không phải regression).

2026-09-03 — TL review (sau khi TASK-6MKF0V drop sub_city): **confirm mở rộng scope** — audit findings §1–§3/§8 + review mới trở thành **Slice B (admin add-child CRUD)**, mini-spec đã thêm (UX chốt: parent select phẳng + indent theo depth, không tree UI). Điểm mới so với audit: grid action Add-child khoá parent; parent select load qua `addressLocations` (canonical) thay vì GetListCity deprecated; validation Slice B mirror `HierarchyImportService` dùng chung const. Review cũng xác nhận `BestEffortViVnResolver` (Secomm_Ghtk) chỉ còn nhắc "sub-city" trong docblock — query đúng, không phải residue.

2026-09-03 — **Slice B implemented + QC done** (Slice A AC-001..004 chưa triển khai — task giữ `in_progress`). Files: `CityInterface`/`CityData` (contract `parent_city_id` + `code` + grid decoration), `Api\HierarchyAddressImportInterface::CODE_COLUMN_LIMIT` (promoted từ service), `Command\City\SaveValidator` (mới — mirror import rules, `LocalizedException` thân thiện + depth-coverage warnings), `Command\City\SaveCommand` (validator + normalise `'' → NULL` cho parent/code), `Controller\Adminhtml\City\Save` (redirect `city_id` fix + catch `LocalizedException` — AC-B2 error UX), `Query\City\GetListQuery` (decorate `parent`/`level`, `⚠` beyond profile, bounded+memoized walk), `Model\OptionSource\CityParentOptions` (mới — select phẳng, self+descendants excluded BFS), `city_form.xml` (parent select + code field), `city_listing.xml` (cột code/parent/level), `CityBlockActions` (Add child action), `CityDataProvider` (prefill parent + disable select khi mở từ Add child). Deviations ghi ở `.ai/runtime/evidence/TASK-9EX975/qc-slice-b-2026-09-03.md` §4: (1) parent options load qua `CityCollection` server-side thay vì `addressLocations` (admin PHP context cần thấy TOÀN BỘ structural nodes, không filter membership); (2) form-side live depth warning CHƯA có (grid `⚠` + warning log đủ; client-side check thuộc mặt đường Slice A) — chờ TL quyết; (3) `Save.php` redirect bug fix dù Out-of-Scope (AC-B2 đi qua nhánh này); 2 bug còn lại (`checkAndDeleteCityNames` SQLi + `CityLocaleCollection` en_US join) GIỮ NGUYÊN chờ ticket riêng.

2026-09-03 — **Slice A implemented + QC (data-layer) done**. Shared factory `Secomm_AddressDropdown/view/adminhtml/web/js/form/schema-cascade.js` (mới — `createCascade`/`loadCityLevels`/`loadLevelOptions`; level 0 = select có sẵn của surface, deeper levels inject, leaf ghi qua `onLeaf` vào native input — DEC-FEATE2HM1J-001; unmapped country → `onSchemaResolved(false)` → native input fallback). 4 surface rewrite dùng factory, GIỮ NGUYÊN quirks riêng + entry point wiring: `provider-mixin.js` (SL-011 AJAX modal), order `address-cascade.js` (RegionUpdater defaultValue clearing, same-as-billing lock + billing copy, hydration watcher, `fillAddressFields` hook, MutationObserver), MSI `source-city.js` + template (KO `cityLevels` view-model, stale name drop như cũ), config `address-city.js` (region node bị replace → delegated events + fresh re-query). AC-003 validation: 3 plugin mới adminhtml-scoped (`Plugin/Quote`, `Plugin/Sales`, `Plugin/Inventory` — neutralise+log non-fatal như các plugin hiện có) + 17 unit tests. QC evidence `.ai/runtime/evidence/TASK-9EX975/qc-slice-a-2026-09-03.md`. Deviations (§4): AC-002 prefill = level-1 NAME match chứ không path API (path không persist trên native stores — TASK-YQSS3M owns path contract); AC-004 browser regression CHƯA chạy (môi trường không có browser — cần QC/TL manual pass); validation là neutralise chứ không hard-block (consistency contract hiện hành — TL quyết nếu muốn hard-block). Out-of-scope emergency fix: docblock `Api/VnOperationalAddressResolverInterface.php` (TASK-Q4B98P, session song song) chứa `*/` giữa chuỗi codes → syntax error break `setup:di:compile` toàn project — fix comment-only.

## Verification

- [x] **AC-B1**: Add child từ grid → form prefill parent + select disabled; save depth-2 OK (city_id 3322, parent=1 "An Bien"); cột Level hiện `2 ⚠` — evidence `qc_slice_b.php` 7/7 PASS
- [x] **AC-B2**: self-parent / cross-region / cycle / broken-chain / parent-không-tồn-tại → blocked với message thân thiện (16 unit tests + QC script); error UX redirect giữ region+parent
- [x] **AC-B3**: node depth-2 lưu được + warning log + grid `⚠`; GraphQL `addressLocations` KHÔNG trả node (102 roots, không có 3324); `addressSchema` VN = 1 city level; BC shim `GetListCity` vẫn flat (documented TASK-6MKF0V AC-3) — evidence `qc-slice-b-2026-09-03.md` §3
- [x] **AC-B4**: duplicate `(region_id, parent_city_id, code)` → message thân thiện; depth-1 dùng null-parent filter (MySQL UNIQUE coi NULL phân biệt)
- [x] **AC-B5**: `SaveValidatorTest` 16 tests; suite AddressDropdown 66/147 + VietNamAddress 120/332 xanh (tính cả 17 test Slice A); `setup:di:compile` clean; QC script 7/7
- [x] **AC-001** (data-layer QC): 4 surface schema-driven qua factory — `addressSchema` VN 2025 = 1 city level, pre-2025 fixture = 2 city levels (label/placeholder/required từ schema), US = null → native input fallback; `addressLocations` roots depth-1 + children depth-2 fixture đúng — evidence `qc-slice-a-2026-09-03.md` §3 (browser render pass gộp vào AC-004)
- [x] **AC-002** (DEVIATION documented §4.1): prefill = match leaf name level-1 trên cả 4 surface (không phải path API — path không persist trên native stores; TASK-YQSS3M owns path contract; AJAX modal surface đã qua bounded hydration wait)
- [x] **AC-003**: leaf persist đúng native store từng surface qua `onLeaf` → native input (không đổi payload); validation chặn leaf sai province = neutralise+log cho CẢ 6 surface admin (3 có sẵn + 3 plugin mới Quote/OrderAddress/Source — unit 24/24 xanh; semantics non-fatal như contract hiện hành, TL quyết nếu muốn hard-block)
- [ ] **AC-004**: browser regression manual PASS chưa chạy (môi trường không có browser) — static checks + GraphQL contract + unit tests + DI compile xong (evidence `qc-slice-a-2026-09-03.md` §2–3); checklist manual ở §4.3, cần QC/TL

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — ngoài việc switch surface (scope hiện có), audit target §5/§6/§19 cho thấy admin CRUD của hierarchy thiếu năng lực generic. Đề xuất gộp vào task này (hoặc ghép với TASK-K09G8Y khi thay UI SubCity) — **cần TL confirm mở rộng scope**:

1. **`city_form.xml` thiếu field**: `view/adminhtml/ui_component/city_form.xml` chỉ có `city_id` (hidden :44), `region_id` (hidden :56 — không chọn được), `default_name` (:68), dynamicRows (:88) — **không có `parent_city_id` + `code`** (target: Region required + Parent City optional + Code + Name).
2. **Zero validation**: `Command/City/SaveCommand.php:106-159` không validate gì (đối chiếu `Command/Region/SaveCommand.php:91` có `validate()`). Cần: parent exists + same-region + không self + không cycle; uniqueness depth-1 (`region_id` + `code` khi parent NULL) phải enforce app-level (MySQL UNIQUE coi NULL phân biệt).
3. **Listing thiếu cột**: `city_listing.xml` không hiện parent/code.
4. **Depth coverage MISMATCH (§19)**: admin order cascade (`Secomm_VietNamAddress/view/adminhtml/web/js/admin/order/address-cascade.js`) + MSI `form/element/source-city.js` chỉ render 1 level; admin customer form 2 level fixed (`provider-mixin.js`). Switch schema-driven của task này chính là chỗ thu hẹp — dùng làm evidence AC-001.
5. **Admin locale join cứng en_US**: `Model/ResourceModel/CityModel/CityLocaleCollection.php:63-68` join `Resolver::DEFAULT_LOCALE` — 3.321 city names 100% vi_VN → admin label rỗng; cần theo admin user locale (read path — cross-ref TASK-YQSS3M findings).
6. **ACL thiếu khai báo**: `etc/acl.xml` không khai `Secomm_AddressDropdown::config` / `::AddressDropdown` / `::country` trong khi menu/system.xml trỏ tới → resource orphan; `::subcityname` (:15) dead (gỡ ở TASK-K09G8Y).
7. **CityBlockActions sai target**: `Ui/Component/Listing/Column/CityBlockActions.php:32` — View mở subcity index (cross-ref TASK-K09G8Y).
8. **§27 predictability**: CRUD cho phép edit node có con → UI cần note rõ overwrite semantics của import (ghi đè theo code) — thêm vào mini-spec per-surface khi triển khai.

## Related records

- Parent feature: FEAT-2PZQKJ
- Decisions: DEC-FEAT2PZQKJ-001 (accepted), DEC-FEATE2HM1J-001 (superseded — surfaces/persist principles vẫn áp dụng)
- Related: FEAT-E2HM1J (admin surfaces gốc)
