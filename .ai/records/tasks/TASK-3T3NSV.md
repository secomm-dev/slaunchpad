---
id: TASK-3T3NSV
type: task
title: 'Hyva generic schema-driven address renderer (behind config flag) + form-validation port'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: high
status: in_progress
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001, DEC-7]
decision_assessment: material
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/view/frontend/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-3T3NSV] Hyva generic schema-driven address renderer (behind config flag) + form-validation port

<!-- CANONICAL TASK RECORD — Phase 2. Checkout-adjacent customer form → Tier-2, QC L3 (AGENTS §7.1: address changes validate VN dropdown end-to-end). -->

## Summary

Renderer Hyva mới render levels động từ resolved schema (GraphQL `addressSchema`/`addressLocations`), thay template fork 574 dòng `hyva/address/edit.phtml`. Behind config flag per-store; template cũ vẫn default đến khi cutover.

## Mini Spec

### Goal
Một component Alpine generic duy nhất: country → resolveProfile → schema → render region + city levels theo `sort_order`; label/placeholder/required từ schema; KHÔNG `if country == 'VN'`, KHÔNG label-from-depth.

### Expected Behavior
1. Flag `address/general/renderer` (legacy | schema) — default `legacy`; store nào bật `schema` dùng renderer mới.
2. Runtime: country change → fetch schema → render; region select → `addressLocations(regionId)`; city select → `addressLocations(parentCityId)`; lặp tới hết schema HOẶC `hasChildren == false` (profile-defined leaf).
3. Edit-mode prefill qua `getLocationPath`; select value = **city_id** (ID-canonical), display = locale name; submit map leaf → native `city` + path → extension attribute theo D2.
4. Port ĐỦ form-validation từ template hiện tại: postcode rule (postCodeSpecs), telephone minlength, VAT, street lines, zip-required per country — không mất rule nào.
5. i18n: label keys từ schema dịch qua module khai báo profile; generic strings vào cả `vi_VN.csv` + `en_US.csv` (BR-001).

### Constraints / Rules
- Hyva patterns: Alpine `x-data`/`x-model`, Tailwind v4 (không tailwind.config.js), CSP `registerInlineScript`.
- No Knockout/RequireJS mới (DEC-7 Strategy B); KO stack cũ untouched (gỡ ở TASK-K09G8Y).
- Templates không chứa business logic.

### Out of Scope
- Admin surfaces (TASK-9EX975); OSC deep integration (SPEC-TASK-FMAN1B — feature riêng); Luma template.
- Cart estimate surface (`Secomm_VietNamAddress::hyva/php-cart/shipping.phtml` — labels key cũ hard-code; chuyển data source + label theo schema thuộc TASK-YQSS3M. QC 2026-08-26: cart EN vẫn "State/Province" trong kỳ này — đã biết, không phải regression).

### Acceptance Criteria
- AC-001: VN (`vn_current` sau TASK-4F1K3N, hoặc default profile với data depth-1) render region + 1 city level, label đúng theo profile; AU-style depth-2 fixture render 2 city levels từ cùng component.
- AC-002: Prefill edit mode chọn đúng chuỗi leaf đã lưu (dùng path API).
- AC-003: Form validation parity — checklist rule-by-rule so template cũ (evidence).
- AC-004: Flag tắt → template legacy hoạt động nguyên vẹn (A/B trên cùng build).
- AC-005: QC L3 customer address form + cart estimate + OSC trên renderer mới; payment test theo gate checkout.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 2, Step 4.

## Implementation Notes

Đã triển khai 2026-08-25 (chờ TL code review + **QC L3 thật** — xem AC-005):

- **GraphQL shape change (pre-release, miễn phí)**: `addressSchema` trả wrapper `{profile_code, levels[]}` — renderer cần profile_code cho mọi lời gọi `addressLocations` sau đó. Unit tests + schema.graphqls cập nhật theo.
- **Flag + plugin**: `address/general/renderer` (select legacy|schema, default `legacy` qua `etc/config.xml` mới) + `Model\OptionSource\RendererMode` + `Plugin\Frontend\CustomerAddressEditTemplate::afterGetTemplate` (no-op khi module off/legacy; swap sang `hyva/address/schema-edit.phtml` khi schema). frontend/di.xml đăng ký plugin.
- **Template** `schema-edit.phtml` (637 dòng): fork legacy 574 dòng — giữ nguyên contact/street/VAT/postcode/region-native/directory-data/defaults + validation rules; thay cascade: region label/required theo schema; city levels động (x-for theo `cityLevels`), dừng khi hết schema HOẶC không còn children (leafIndex); unmapped country → native city text input; submit disable khi schema mode chưa chọn leaf.
- **D2 áp dụng theo hướng BC-first (cần TL xác nhận khi review)**: select state theo `city_id` (ID-canonical); giá trị POST `city` vẫn là `default_name` của leaf — mọi consumer hiện tại (ValidateVietNamWard, shipping dest_city translation, GHTK name-resolve, address display) hoạt động không đổi; ids mới đi qua hidden `address_city_id` + `address_location_path` (JSON) — TASK-YQSS3M/GiaoHangNhanh là consumer.
- **Prefill**: depth-1 profiles chính xác (name match, id match khi có address_city_id); depth >1 legacy data (chỉ có leaf name) prefill level 1 + yêu cầu chọn lại chuỗi dưới — **known limitation** cho đến khi location_path được persist (TASK-YQSS3M/CR-side).
- i18n en_US +3 (labels source model + Address Renderer) — vi_VN trống theo DEC-8 boundary.

Fix-in-flight: frontend/di.xml edit làm lệch 1 thẻ đóng (`</argument>` vs `</arguments>`) — bắt qua `config:show` XML validate; system.xml từng khai backend_model select không tồn tại — bỏ (select không cần). **Resolved + XML re-verified 2026-08-26** (pre-review).

2026-08-26: Pre-review PASS (evidence `PRE-REVIEW-2026-08-26-FEAT-2PZQKJ-YA2C0W.md`); W2 verified qua HTTP trên 2 store views. Renderer flag `address/general/renderer = schema` bật trên dev default scope + `cache:flush` để QC browser (revert: `bin/magento config:set address/general/renderer legacy`).

2026-08-26: **TL code review approved** — gate còn lại: QC browser AC-001/002 (+ empty-region W4) + QC L3 AC-005 (human gate). Task giữ `in_progress` đến khi QC xong.

2026-08-28 (fix trước QC — theo audit findings dưới + TL directive "fix lại cho đúng trước sau đó mới chuyển QC"):
- **Theme gate (finding 1) — FIXED**: `CustomerAddressEditTemplate::afterGetTemplate` giờ chỉ swap khi template layout-resolved là legacy Hyva (`::hyva/address/edit.phtml` — chỉ được set qua handle `hyva_customer_address_form`); Luma template (`::address/edit.phtml`) và child-theme override → no-op. +2 unit tests (`testSchemaModeKeepsLumaTemplate`, `testSchemaModeKeepsOverriddenTemplate`). Case QC "flag schema trên store Luma" giờ pass-by-design (vẫn chạy để verify thực tế).
- **Sorting (finding 2) — FIXED ở engine** (`LocationHierarchyProvider::fetchChildren`, trước dòng `:270` cũ): thay `order('name ASC')` bằng REPLACE-expression `REPLACE(REPLACE(CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci,'Đ','D'),'đ','d') ASC, c.city_id ASC`. Basis: **không có collation MySQL 5.7 nào cho Đ==D** (claim audit v1 "utf8_unicode_ci đúng" đã disproved trên data thật — chi tiết ở TASK-YQSS3M findings item 1). Verify DB trên region 1171 TP.HCM (44 ward Đ-initial): khối Đ interleaved đúng vào D, deterministic qua tiebreaker `city_id`.
- Unit suite: **50 tests / 114 assertions OK** (từ 48 — +2 theme-gate tests).
- **QC browser bug #1 (AC-002 flow, báo cáo bởi user) — FIXED**: `onLevelChange` trong `schema-edit.phtml:574` gọi `this.onChange({})` với placeholder object → mixin `hyva-advanced-form-validation.js` (`onChange(event)` đọc `event.target.dataset`/`.name`) ném `TypeError: can't access property "dataset"` **mỗi lần select ward** (unhandled promise rejection; cascade vẫn chạy vì throw ở câu cuối). Fix: pass real Alpine event — binding `@change="onLevelChange(idx, $event)"` + `onLevelChange(idx, event)` + `this.onChange(event)` — đúng pattern legacy (`onCityChange`/sub_city select đều pass event thật). Audit thêm: mọi binding khác trong template (`@input.debounce="onChange|changeCountry|onRegionIdChange"`) đều nhận DOM event thật, không còn fake-event call nào. `php -l` PASS; `cache:clean block_html full_page` đã chạy — chờ user re-test browser.
- **QC browser bug #2 (cùng flow, console đã sạch) — FIXED**: lần chọn ward ĐẦU TIÊN không stick (lần 2 thì được). Root cause: `<option :selected="level.selectedId === option.city_id">` — so sánh **string** (x-model ghi `el.value`) với **number** (GraphQL `city_id`) bằng `===` → luôn false → khi user chọn, Alpine reactive flush re-run effect và set `option.selected = false` trên chính option vừa chọn → select rơi về placeholder; lần 2 state không đổi → effect không re-run → DOM giữ selection. Fix: `:selected="String(level.selectedId) === String(option.city_id)"` + đồng bộ kiểu ở prefill (`first.selectedId = String(option.city_id)`). Phụ-product: `:selected` (đã fix kiểu) chạy trong lúc x-for render option → cover luôn race x-model không re-apply sau khi options populate async (pattern `reapplySelected` của legacy template). `php -l` PASS; cache cleaned — chờ user re-test.

## Verification

- [x] AC-003: validation parity checklist 10/10 rule khớp legacy (`addRule telephone/postcode`, postCodeSpecs, street lines, VAT, zip/region-required per country, validateCountryDependentFields) — evidence 01
- [x] AC-004: flag default `legacy` runtime-verified (bootstrap getValue = 'legacy'); plugin unit tests 3/3 (module off / legacy / schema swap); template legacy không đổi — evidence 02
- [x] Unit suite: 37 tests / 71 assertions xanh (3 plugin mới + resolver wrapper refactor)
- [x] AC-001 (partial — automated): GraphQL `addressSchema(VN)` trả đúng profile + 2 levels; depth-2 render cùng component đảm bảo bởi cấu trúc x-for (không code level-specific) — **QC browser xác nhận visual**
- [ ] AC-001 (QC browser): VN render region + 1 city level label "Tỉnh/Thành phố" / "Phường/Xã/Đặc khu" (vi_VN store); depth-2 profile fixture render 2 city levels
- [x] QC (automated, HTTP 2026-08-28 — evidence `QC-AUTO-2026-08-28.md`): AC-001 labels vi/en PASS; **sort Đ==D PASS** (168 ward region 1171 — Đ-names interleave D-group, hết khối Đ sau Y); W4 data-side PASS (region lạ → `[]`); theme gate case Luma PASS-by-unit (dev không có store view Luma). Lưu ý: dataset `vn_admin_pre_2025` chưa import (0 rows `parent_city_id`) → depth-2 visual chờ fixture (TASK-ADT94K).
- [ ] AC-002 (QC browser): prefill edit-mode chọn đúng leaf đã lưu
- [ ] AC-005 (QC L3 — human gate): customer address form + cart estimate + OSC + payment test trên store bật flag `schema` (checklist trong FEAT plan §5)
- [x] QC (pre-review 2026-08-26, W2): multi-locale cache variance — **verified 2026-08-26 qua HTTP** `POST /graphql` trên `fashion_en` (en_VN) vs `fashion_vi` (vi_VN): `addressSchema` + `addressLocations` trả label/name đúng locale riêng từng store (resolver cache varies theo Store header — không có lỗi sai locale)
- [ ] QC (pre-review 2026-08-26, W4): schema-mode region thiếu dataset rows → các select rỗng + submit disabled, không fallback — xác nhận behavior mong muốn / cần fallback UX
- Evidence: `.ai/runtime/evidence/TASK-3T3NSV/`

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — bổ sung cho QC gate (task giữ `in_progress`, không đổi status):

1. **Theme gate anti-pattern**: `Plugin/Frontend/CustomerAddressEditTemplate` swap sang `hyva/address/schema-edit.phtml` **không check theme** đang chạy — store Luma bật flag `schema` sẽ vỡ (template Hyva-only), chưa có counterpart `schema-edit.phtml` cho Luma; mâu thuẫn với comment boundary trong `etc/frontend/di.xml:17-19`. → QC AC-005 cần thêm case "flag schema trên store Luma"; fix theo hướng theme-check trong plugin hoặc giới hạn scope config (TL decide). → **FIXED 2026-08-28** (xem Implementation Notes).
2. **Sorting kế thừa defect**: dropdown city levels của renderer lấy data từ `addressLocations` → `Model/LocationHierarchyProvider.php:270` sort `name ASC` theo collation `utf8_general_ci` — Đ/đ đứng sau Y (sai target §8). Fix thuộc engine — xem TASK-YQSS3M findings (audit findings 2026-08-28); đưa "thứ tự dropdown đúng Đ==D" vào checklist QC AC-001. → **FIXED 2026-08-28** (xem Implementation Notes).
3. **Label hardcode legacy path**: `'Ward/Commune'` hardcode trong `view/frontend/web/js/action/shipping-address-dropdown.js:166,184` + `billing-address-dropdown.js:176` — thuộc Luma/OSC legacy path, xử lý ở TASK-YQSS3M (labels từ schema) / TASK-K09G8Y (removal); không thuộc scope task này.

## Related records

- Parent feature: FEAT-2PZQKJ
- Decisions: DEC-FEAT2PZQKJ-001 (accepted — D2 persist policy), DEC-7 (Hyva-native, accepted)
