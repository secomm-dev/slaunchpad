---
id: BUG-25XDH4
type: bug
title: "Hyva Address Book cascade Region → City: Alpine x-for duplicate key + race condition (TypeError .after on staging)"
project_code: SLP
parent:
external_refs:
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-04
updated: 2026-09-04
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva theme-module (Alpine 3.14.3) + Secomm_AddressDropdown
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address
source_areas:
  - customer-address
  - hyva-frontend
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-04
supersedes: []
---

# [SLP][BUG-25XDH4] Hyva Address Book cascade Region → City: Alpine x-for duplicate key + race condition (TypeError .after on staging)

## Summary

Trang **My Account > Address Book** (`customer/address/new` + `customer/address/edit`) trên Hyvä storefront: chọn Region thỉnh thoảng không load City, kèm `Uncaught TypeError: can't access property "after", O is undefined` (stack qua `populateCities → loadCities → onRegionIdChange → Alpine`). Chỉ xảy ra trên staging, local bình thường. DB local/staging giống nhau — không phải data issue.

**Audit findings (trước khi sửa):**

1. **Template render theo config** — layout `hyva_customer_address_form.xml` set `Secomm_AddressDropdown::hyva/address/edit.phtml` cho block `customer_address_edit` (chung cho cả new + edit qua handle `customer_address_form`); plugin `CustomerAddressEditTemplate` swap sang `schema-edit.phtml` khi `address/general/renderer = schema`. **Local đang chạy `renderer=schema` (schema-edit.phtml), staging chạy legacy edit.phtml** (stack trace `populateCities/loadCities` chỉ tồn tại ở legacy) → giải thích "local OK, staging lỗi". Theme `Secomm/launchpad` không override template nào trong 2.
2. **Root cause `.after`**: Alpine 3.14.3 `x-for` keyed reconciliation chứa `S.after(O),O._x_currentIfEl&&O.after(O._x_currentIfEl)` với `O` lấy từ `el._x_lookup[key]`. City x-for dùng `:key="city.default_name"` — 2 city trùng `default_name` trong cùng region làm lookup collide → `O` undefined → TypeError.
3. **Race condition**: `populateCities` không có stale-request protection — đổi Region nhanh → 2 fetch overlap → response về sau (kể cả stale) overwrite `cities`; `isLoadingCities` không dùng try/finally.
4. **Dual selection control**: Region `<option>` có cả `x-model="selectedRegion"` lẫn `:selected="selectedRegion === regionId"` — hai effect cùng ghi selected state.
5. **`@input.debounce` trên `<select>`**: handler bị delay, đọc state sai thời điểm; Alpine x-model của select lắng nghe `change` (alpine3.js:2832) nên `@change` mới đúng pairing.
6. **`onRegionIdChange` thiếu guard**: `availableRegions[this.selectedRegion].name` nổ khi `selectedRegion='0'` hoặc region không có trong map; `validateField(this.fields['region'])` không guard field chưa register.
7. **`GetListCity` resolver đã trả `city_id`** (schema.graphqls + GetListCityGraphql.php) — frontend chỉ chưa query thêm.
8. **Alpine x-model KHÔNG re-apply value sau khi `<option>` insert async** (sync effect alpine3.js:2869 chỉ phụ thuộc model value) → `:selected` hiện là thứ giữ edit-mode region preselect; bỏ nó phải bù bằng pattern `reapplySelected()` module đã dùng cho city.

## Mini Spec

### Goal

Cascade Region → City trên Address Book (new + edit) chạy ổn định trên cả legacy template lẫn schema template: không còn Alpine `.after`/duplicate-key error, city list luôn khớp region đang chọn, loading state không kẹt, edit mode vẫn preselect đúng.

### Expected Behavior

- Chọn Region → City list load và khớp region chọn cuối cùng (kể cả đổi nhanh liên tục).
- Region có city trùng tên display vẫn render đủ option, chọn đúng option.
- Loading: city select disable khi đang fetch, enable khi xong; fetch fail không kẹt loading.
- Edit address hiện có: region + city preselect đúng sau refresh.
- Region rỗng/placeholder → city list reset.
- Save vẫn lưu `region_id` + `city` textual (không đổi persistence contract).

### Constraints / Rules

- Key x-for: city dùng `city_id` (query thêm từ `GetListCity`), region dùng `regionId`; persistence giữ `:value="city.default_name"`.
- Stale protection: request token — request mới invalidate request cũ; stale response không overwrite `cities`; chỉ request active được clear `isLoadingCities`; `try/finally` cho loading state; `resetVnCascade` invalidate pending.
- Region select: `@change` (không debounce), x-model là sole owner của selected state (bỏ `:selected`, bù `reapplySelected('selectedRegion')` cho edit preselect).
- `onRegionIdChange` defensive: String-coerce regionId, optional-chain region name, guard `fields['region']` trước `validateField` (theo pattern có sẵn trong `validateCountryDependentFields`).
- Không đổi: DB schema, dataset VietNamAddress, persistence format, giá trị `city`, `region_id` behavior, GraphQL surface (`city_id` đã expose sẵn), admin forms.

### Out of Scope

- Migrate `GetListCity` (deprecated) → `addressLocations` — technical debt, ghi nhận riêng.
- Sửa Luma template `address/edit.phtml` (jQuery cascade — stack khác, staging là Hyva).
- Checkout OSC cascade (`Launchpad_Osc` shipping/billing-address-dropdown.js — requirejs/Knockout, không phải Alpine component này).
- Trích Alpine logic ra JS module riêng để unit-test (refactor ngoài scope).

### Acceptance Criteria

- AC-001: Không còn TypeError `.after`/undefined DOM node khi thao tác Region → City (legacy + schema template)
- AC-002: Đổi Region nhanh liên tục: `cities` luôn correspond region chọn cuối, không stale
- AC-003: Region có duplicate city display name: render đủ option, key x-for ổn định
- AC-004: Loading state consistent qua try/finally; fetch error không kẹt disable
- AC-005: Edit address: region + city preselect đúng (region qua `reapplySelected`, city qua `reapplySelected` có sẵn)
- AC-006: Save vẫn submit `region_id` + `city` textual — không đổi save contract
- AC-007: Non-VN country flow không regress (city select vẫn reset khi đổi country)

## Steps to Reproduce

1. Staging, Hyva storefront, đăng nhập, vào Account > Address Book > Edit/New address
2. Chọn Region (có lúc City load, có lúc không); đổi Region nhanh liên tục
3. Console: `Uncaught TypeError: can't access property "after", O is undefined` qua `populateCities → loadCities → onRegionIdChange`

## Root Cause

**CONFIRMED (không phải data issue — DB local/staging giống nhau, đúng như ghi nhận):**

1. **Duplicate x-for key (nguồn crash `.after`)**: Alpine 3.14.3 keyed `x-for` reconciliation (`alpine3.min.js`) chứa `S.after(O),O._x_currentIfEl&&O.after(O._x_currentIfEl)` với `O = el._x_lookup[key]`. `:key="city.default_name"` — **8/34 VN regions có duplicate `default_name`**: Dong Nai "Loc Thanh" x2, Dong Thap "My Tho" x2 + "Tan Thanh" x2, Hai Phong "Cam Giang" x2, **HCM "Thanh An" x2**, Quang Ngai "Ba To"/"Son Ha" x2, Tay Ninh "Tan Thanh" x2, Thai Nguyen "Van Lang" x2, Thanh Hoa "Dong Tien" x2 — mỗi cặp KHÁC `city_id`. Key trùng → 1 lookup entry cho 2 nodes → lần re-render sau, entry đã bị delete → `O` undefined → TypeError. Giải thích "lúc được lúc không": chỉ region có duplicate names (hoặc rời region đó) mới crash.
2. **Không stale-request protection**: `populateCities` ghi `this.cities = await fetchCities(...)` không token — 2 fetch overlap (đổi Region nhanh / staging chậm) → response stale overwrite.
3. **`@input.debounce` trên `<select>`**: Alpine x-model của select lắng nghe `change` (alpine3.js:2832) — `@input.debounce` delay handler, đọc state sai thời điểm.
4. **Dual selection control**: `x-model="selectedRegion"` + `:selected="selectedRegion === regionId"` cùng ghi selected state (non-deterministic render order).
5. **`onRegionIdChange` không guard**: `availableRegions['0'].name` nổ khi placeholder; `validateField(this.fields['region'])` không guard field chưa register.
6. **Alpine x-model không re-apply sau async `<option>` insert** (sync effect alpine3.js:2869 chỉ phụ thuộc model value) → `:selected` là thứ giữ region preselect của edit mode; bỏ nó phải bù `reapplySelected()` (pattern module đã dùng cho city).

Evidence: `.ai/evidence/BUG-25XDH4/duplicate-name-analysis.txt`, `getlistcity-region-1191.json`, `getlistcity-all-dup-analysis-source.json`.

## Fix

- [edit.phtml](app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml) (legacy — template staging đang chạy): `$root`→`$el`; `@input.debounce`→`@change` region select; bỏ `:selected` region option + `reapplySelected('selectedRegion')` cho edit preselect; `:key="regionId"` region x-for; city x-for `:key="city.city_id"` + query thêm `city_id`; `cityRequestId` token + `try/finally` (stale response không overwrite `cities`, chỉ request active clear `isLoadingCities`, `resetVnCascade` invalidate pending); `onRegionIdChange` defensive + reset khi region rỗng
- [schema-edit.phtml](app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/schema-edit.phtml) (sibling renderer cùng trang — local đang chạy): cùng defect class → `$root`→`$el`; `@change`; bỏ `:selected` + `reapplySelected`; `:key` region; token guard `loadSchemaForCountry`/`loadLevelOptions` (stale response không touch state; `changeCountry`/init prefill bỏ qua khi superseded); region rỗng → clear `cityLevels`

## Verification

- **GraphQL smoke (local, vhost `fashion-launchpad.localhost`, store `fashion_en`)**: `GetListCity` trả `city_id` trong 100% rows (3321 cities / 34 regions) — fix key có dữ liệu để dùng; duplicate-name analysis → evidence dir
- **Static checks**: `php -l` PASS cả 2 template; inline JS extract → `node --check` PASS cả 2
- **Diff review** theo checklist từng fix item (7 items legacy + 5 items schema) — trong PR body
- **Không thể tự động hoá test Alpine** (không có JS unit-test infra; logic inline trong phtml) — QC manual theo matrix dưới

### Manual regression (QC)

1. New address: chọn Region → City load; save → `region_id` + `city` textual đúng
2. Edit address: region + city preselect đúng sau refresh (region qua `reapplySelected` mới, city như cũ)
3. Đổi Region chậm: list khớp region chọn
4. Đổi Region nhanh liên tục (đặc biệt qua **HCM, Dong Nai, Dong Thap** — có duplicate names): không console error, list luôn khớp region cuối
5. Region trùng tên city (HCM "Thanh An"): render đủ 2 option, chọn được từng option
6. VN → country khác → VN: city reset, chọn lại OK
7. Chọn placeholder region (rỗng): city list reset + disable
8. Network throttling Slow 3G + đổi region nhanh: stale response không overwrite (thêm log `cityRequestId` nếu cần trace)
9. Non-VN country (US): region optional flow không đổi; native city behavior giữ nguyên

## Notes cho TL review

- **2 env render 2 template khác nhau** (local `renderer=schema`, staging legacy) — fix cả 2 để tránh crash class tái xuất khi cutover schema; đây là cùng reusable component trên cùng page.
- Secomm_AddressDropdown thuộc §12 (Tier 2) — nhưng task này không đụng GraphQL surface (chỉ query thêm field đã expose), không đụng persistence/data → giữ Mode C, escalation qua TL review của record.
- Alpine x-model không re-apply sau async option insert (Alpine 3.14.3) — `reapplySelected` là pattern chính thức của module (đã dùng cho city); giờ áp cho region luôn.
- Technical debt ghi nhận: (1) `GetListCity` deprecated → cần migrate `addressLocations` (ID-canonical) theo FEAT-2PZQKJ cutover; (2) Alpine logic inline trong phtml — không unit-test được, cần tách JS module khi có JS test infra; (3) Luma template jQuery cascade cùng thiếu stale protection — chưa sửa (out of scope).
