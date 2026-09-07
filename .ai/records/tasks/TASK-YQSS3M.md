---
id: TASK-YQSS3M
type: task
title: 'Carrier + GraphQL consumer alignment — GhnAddressMapper CascadingOptions, GiaoHangNhanh readers, GraphQL shims'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: B
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001]
components:
  - CMP-ADDR
  - CMP-GHNMAPPING
  - CMP-GHN
  - CMP-GHTK
source_areas:
  - app/code/Secomm/GhnAddressMapper/Controller/Adminhtml/Ajax/CascadingOptions.php
  - app/code/Secomm/GiaoHangNhanh/Model/Service/Request/
  - app/code/Secomm/AddressDropdown/Model/Resolver/
  - app/code/Secomm/VietNamAddress/view/
changes_project_state: true
changes_architecture: false
changes_integration: true
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-YQSS3M] Carrier + GraphQL consumer alignment — GhnAddressMapper CascadingOptions, GiaoHangNhanh readers, GraphQL shims

<!-- CANONICAL TASK RECORD — Phase 2. Đưa consumer modules + GraphQL cũ sang contract mới; giữ BC. -->

## Summary

(1) `GhnAddressMapper CascadingOptions` bỏ query trực tiếp `directory_city_sub_city` → dùng `LocationHierarchyProvider`; (2) GiaoHangNhanh DataBuilders chuyển nguồn `sub_city`/`city_id` sang canonical path theo D2; (3) `GetListCity`/`GetListSubCity` thành BC shim delegate sang provider mới + flag deprecation; (4) 4 GraphQL caller JS trong VietNamAddress chuyển sang query mới.

## Mini Spec

### Goal
Sau task này không còn code nào ngoài module generic đọc `directory_city_sub_city*` — điều kiện tiên quyết cho Phase 3 removal.

### Expected Behavior
1. `CascadingOptions.php` ('ward' case): query qua `LocationHierarchyProviderInterface::getChildLocations` (profile theo config) — không đổi response shape (BC cho admin UI GHN).
2. `ShippingDetailsDataBuilder` / `SynchronizeOrderDataBuilder`: đọc leaf/ward theo persist policy D2 (native `city` + `location_path`/ID sẵn có); giữ fallback đọc `sub_city` cũ trong cửa sổ BC.
3. GraphQL cũ: `GetListCity` map → `getRootLocations`; `GetListSubCity` map → `getChildLocations` theo parent name (BC lookup-by-name giữ nguyên response shape); đánh dấu deprecation date trong schema doc.
4. VietNamAddress JS callers (admin order cascade, source-city, Hyva cart shipping) → query `addressLocations`; cart template giữ hành vi region→ward hiện tại về **luồng dữ liệu**, nhưng **label/placeholder render từ `addressSchema`** theo profile của country đang chọn — bỏ key hard-code `State/Province` / `Ward/Commune` / `Please select a region, state or province.` trong `hyva/php-cart/shipping.phtml` (mở rộng 2026-08-26: QC TASK-3T3NSV phát hiện gap UX — customer form schema hiển thị "Province/City" trong khi cart EN vẫn "State/Province"; thu hẹp trong cùng task chạm cart, không đợi feature OSC).
5. GhnAddressMapper `PopulateCityId` / CLI / UI options: không đổi (đọc `directory_region_city` — vẫn canonical).
6. GiaoHangNhanh: thêm `sequence` declaration với GhnAddressMapper (fix phụ thuộc gián tiếp chưa khai báo).

### Constraints / Rules
- Không sửa `app/code/Mageplaza/*`; không đổi rate logic carrier (chỉ nguồn address data).
- Response shape GraphQL cũ KHÔNG đổi (BC contract).
- Mỗi module đổi: CHANGELOG riêng.

### Out of Scope
- Rate calculation, GHN API payload format (ngoài nguồn ward); TASK-K09G8Y drop bảng.

### Acceptance Criteria
- AC-001: Grep toàn app/code: không còn reference `directory_city_sub_city` ngoài AddressDropdown (evidence).
- AC-002: Admin GHN mapping cascade hoạt động như trước (QC).
- AC-003: GraphQL cũ trả cùng kết quả trên data hiện tại (so sánh response trước/sau).
- AC-004: GHN rate + order sync payload chứa ward đúng (QC L3 carrier path).
- AC-005 (mở rộng 2026-08-26): Cart estimate (Hyva) trên country VN hiển thị label/placeholder theo profile active — khớp customer form schema renderer (EN: "Province/City" / "Ward/Commune"; VI: "Tỉnh/Thành phố" / "Phường/Xã/Đặc khu") — QC browser.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 2, Step 7.

## Implementation Notes

Chưa triển khai (proposed — phụ thuộc TASK-J49PRZ + D2 chốt).

2026-08-28 (bắt đầu implement — cart estimate consumer, được trigger khi user QC TASK-3T3NSV mở rộng sang cart):
- **Fix crash x-for** (bug báo bởi user trên `checkout/cart`): `TypeError: can't access property "after", O is undefined` từ `loadWards` — template `VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml` key ward bằng `:key="ward.default_name"`; dataset 2025 (region 1171 TP.HCM sau merge) có **tên ward trùng** (`Thanh An` ×2 — city_id 1452/1453, verify DB) → duplicate `:key` làm Alpine x-for reconciliation chết, ward không render. Fix: query + key theo `city_id` (unique).
- **Chuyển cart estimate sang schema-driven** (phần consumer của task này): `addressSchema(VN)` → profile_code + labels (region/ward + placeholder, fallback i18n khi unmapped); `addressLocations(region_id, profile_code)` thay `GetListCity` legacy → hưởng luôn sort Đ==D + tên locale + profile membership. Persist `city` vẫn là `default_name` (BC payload không đổi với carriers). `php -l` PASS; `cache:clean` đã chạy; guest GraphQL smoke test PASS (schema + locations).
- Còn lại của task (shims `GetListCity`/`GetListSubCity`, admin collections sort, GHN/Ghtk readers) — chưa làm.

## Verification

- [ ] AC-001..005 — evidence: `.ai/runtime/evidence/TASK-YQSS3M/`

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — bổ sung sorting + carrier verdicts vào scope:

1. **Sorting (root cause — phần engine ĐÃ FIX 2026-08-28, pulled forward vào QC readiness của TASK-3T3NSV)**. Target §8 yêu cầu sort tiếng Việt Đ/đ == D/d. Hiện trạng:
   - `Model/ResourceModel/CityModel/CityLocaleCollection.php:59-78` + `Model/ResourceModel/SubCityModel/SubCityLocaleCollection.php` — **KHÔNG có ORDER BY** → `Model/Resolver/GetListCityGraphql.php:50-64` trả physical order InnoDB (verify qua DB 2026-08-28: thứ tự vô nghĩa). *(chưa fix — áp cùng expression dưới đây khi làm shims của task này)*
   - `Model/LocationHierarchyProvider.php:270` — `order('name ASC')` theo collation bảng `utf8_general_ci` → khối Đ đứng sau Y. **[Correction 2026-08-28 — claim audit v1 SAI]** experiment đầu kết luận "utf8_unicode_ci / utf8_unicode_520_ci cho đúng Đ==D" — re-verify trên data thật (region 1171 TP.HCM, 44 ward Đ-initial) cho thấy **mọi collation MySQL 5.7 đều giữ Đ là letter riêng đứng sau D** (`utf8_general_ci`, `utf8_unicode_ci`, `utf8_unicode_520_ci`, `utf8mb4_unicode_ci`; `utf8mb4_0900_*` không khả dụng; không có collation `%vn%`). Fix đúng = normalise trong ORDER BY expression: `REPLACE(REPLACE(CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci,'Đ','D'),'đ','d') ASC, c.city_id ASC` (unicode_ci đã ignore tone marks; `CONVERT … USING utf8mb4` an toàn cho cả utf8/utf8mb4 install; tiebreaker `city_id` deterministic). **Đã áp dụng trong `fetchChildren` 2026-08-28** — verify DB: Đất Đỏ về đầu nhóm D, đ-names interleaved đúng (đat < dau < di < dong < duc).
   - `Plugin/GetRegionDataPlugin.php:19-21` — `strcmp` byte-sort.
   - Luma JS `shipping-address-dropdown.js:441-443` / `billing-address-dropdown.js:450-452` — **[Correction 2026-08-28]** `localeCompare('vi', {sensitivity:'base'})` **không** sort đúng như ghi nhận ban đầu: ICU collation `vi` cũng giữ Đ là letter riêng (verify PHP intl `Collator('vi_VN')` 2026-08-28: `compare('Đừng','Dung') = 1`, `compare('Dắc','Đác') = -1`). *(legacy path — sẽ remove ở TASK-K09G8Y; sau fix engine, server order là chuẩn)*
   → **TL approved 2026-08-28**: fix ở SQL layer; KHÔNG theo đuổi DB-wide collation migration. Đã thực thi phần engine (LocationHierarchyProvider); phần còn lại của task này = shims + admin collections dùng cùng expression.
2. **Carrier verdicts (§29)**: GiaoHangNhanh = MISMATCH — owns `district` dropdown riêng (`view/frontend/templates/address/edit.phtml:114-126`) + cột `district` trên core `quote_address` (db_schema của carrier) + đọc `sub_city` ext attr (`Model/Service/Request/SynchronizeOrderDataBuilder.php:99`, `ShippingDetailsDataBuilder.php:63`) → mở rộng item 2: bỏ district dropdown/cột theo profile schema (đụng cột core `quote_address` → TL gate). Ghtk / Ahamove / ShippingCore = MATCH (không cần đổi).
3. **GhnAddressMapper raw SQL**: `Model/ResourceModel/LocationMapping.php:82,93-95,121-123,249-251,360-367` — SQL trực tiếp trên AD tables thay vì qua provider/OptionSource (evidence bổ sung cho item 1; admin options nên consume provider).
4. **Name-keying risk cho shim `GetListSubCity`**: PRE_2025 có 19 collision group ward trùng tên trong cùng region (suffix type) → BC lookup-by-name có thể ambiguous; shim phải trả ambiguity rõ ràng, không pick-first.

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted — D2)
- Related: TASK-3F6QWZ (carrier modules precedent)
