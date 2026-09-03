---
id: DEC-FEAT2PZQKJ-001
title: AddressDropdown refactor — recursive city hierarchy (parent_city_id) + Address Profile/Schema; bỏ fixed city→sub_city
status: accepted             # approved via user acting as SA/TL authority (chat 2026-08-25)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit:
supersedes: [DEC-FEATE2HM1J-001]   # thay thế point 3 "sub_city = generic 3rd level cố định"; points 1-2 (generic+adapter, persist per native store) được giữ lại/restated
superseded_by:
work_items: [FEAT-2PZQKJ, TASK-9AEAQQ, TASK-NW66H9, TASK-J49PRZ, TASK-3T3NSV, TASK-4F1K3N, TASK-CR4D1V, TASK-YQSS3M, TASK-9EX975, TASK-K09G8Y, TASK-ZHFVRH]
---

# Decision Record: Recursive city hierarchy + Address Profile/Schema cho Secomm_AddressDropdown

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). ACCEPTED 2026-08-25 (user acting as SA/TL; formal SA/TL name [TBD]) — từ architecture review 2026-08-25 (FEAT-2PZQKJ baseline audit). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Supersede DEC-FEATE2HM1J-001 point 3 (sub_city fixed 3rd level); giữ nguyên DEC-8 / DEC-FEATJSZQV3-001 / DEC-FEATJSZQV3-003 / DEC-TASKYJENM2-001 (VN ward = native city, canonical city_id). -->

## Context

`Secomm_AddressDropdown` hardcode 2 cấp dưới region (`directory_region_city` → `directory_city_sub_city`). DEC-FEATE2HM1J-001 đã chốt "data-driven multi-level" nhưng implementation chỉ đạt tối đa 2 cấp; review 2026-08-25 (FEAT-2PZQKJ) xác định thêm: label gắn cứng vào cấp vật lý ("City"/"Sub-City") phải vá bằng i18n locale lạ `en_VN`; identity theo tên thay vì `city_id` (carrier modules GHN/GHTK phải bridge ngược); SQL string interpolation [BLOCK]; cùng một country có thể cần nhiều representation (VN current 2 cấp sau cải cách 2025 vs legacy 3 cấp). DB hiện tại: `directory_city_sub_city*` = 0 rows (local dev; prod chưa scan).

Cần quyết định kiến trúc tổng quát thay thế fixed 2 cấp, giữ: generic module country-agnostic (DEC-FEATJSZQV3-001/003), VN adapter trong VietNamAddress, canonical `region_id`/`city_id`, FK GhnAddressMapper.

## Decision (proposed — từng điểm chờ SA/TL)

1. **Recursive hierarchy**: `directory_region_city` thêm `parent_city_id` (NULL = root dưới region, self-FK CASCADE) + `code` (identifier ổn định cho import/dedupe/carrier reference). `directory_city_sub_city*` mất vai trò → deprecated Phase 2, drop Phase 3 (gate: prod data scan + không còn reader). KHÔNG tạo domain concept country-specific (district/ward/…) trong generic module — label/level behavior đến từ Address Profile.
2. **Address Profile** (XML `etc/address_profiles.xml`, module-shipped, merge nhiều module): mỗi profile định nghĩa **Address Schema** — levels `entity_type ∈ {region, city}` + `depth`, `label` (translate-key), `placeholder`, `sort_order`, `required`. **KHÔNG BAO GIỜ suy label từ depth.** Country không map profile → native Magento behavior. Generic module chỉ ship profile `default` fallback tối giản.
3. **Profile resolution**: `resolveProfile(countryId, context)` đọc config country→default profile (+ optional per-context override). Không rules engine. Dataset membership qua bảng `secomm_address_profile_location` (subtree-claim + inheritance) — cùng country có nhiều representation (current/legacy) mà KHÔNG thêm concept valid_from/legacy lên hierarchy.
4. **Service contracts** (API stable cho external modules): `AddressProfileResolverInterface`, `AddressSchemaProviderInterface`, `LocationHierarchyProviderInterface` (getRootLocations / getChildLocations / hasChildren / getLocationPath). GraphQL mới `addressLocations`/`addressSchema`; resolvers cũ thành BC shim rồi retire.
5. **Canonical identity** giữ `directory_country_region.region_id` + `directory_region_city.city_id`; renderer chọn theo ID (không còn submit `default_name`); leaf node persist vào native `city` + full path vào extension attribute `location_path` (D2 đã chốt; DEC-TASKYJENM2-001 giữ cho VN).
6. **Phân tầng trách nhiệm không đổi**: VN_CURRENT/VN_LEGACY profiles, old↔new ward mapping, GHN/GHTK/Ahamove IDs, geocoding, historical boundaries → ngoài generic module (VietNamAddress / carrier modules / tương lai Secomm_VietnamAddress capabilities).
7. **Incremental 3 phase** (additive → cutover → removal gated) — chi tiết trong FEAT-2PZQKJ plan. Không destructive change trước khi dependency scan sạch.

### Đã chốt trong review (2026-08-25 — user acting as SA/TL)

- **D2 — Persist policy depth > 2**: **leaf → native `city` + full path → extension attribute mới `location_path`** (JSON array `{city_id, level}`) trên quote/order/customer address; GiaoHangNhanh readers chuyển sang đọc path (TASK-YQSS3M).
- **D4 — Profile storage**: **XML code-shipped** (`etc/address_profiles.xml`, merge nhiều module); không admin CRUD.
- **D5 — Membership semantics**: **subtree-claim + inheritance** (node không có entry inherit tư cách parent; ≈4k rows cho VN current+legacy thay vì 14k).
- **D6 — `code` column**: **CÓ** — thêm `code` VARCHAR(64) + UNIQUE `(region_id, parent_city_id, code)`; thay name-hack dedupe.

## Alternatives

- **Giữ fixed city→sub_city (DEC-FEATE2HM1J-001 hiện trạng):** rejected — không diễn đạt 3+ cấp; label-from-structure là gốc vấn đề mislabel ward/city; đã lệch giữa decision ("mấy tầng data có mấy tầng") và implementation (cap 2).
- **Thêm bảng cấp 3/4 riêng (sub_city → sub_sub_city…):** rejected — mỗi cấp mới = bảng + model + admin grid + i18n trùng lặp; không tổng quát hoá được.
- **Nested set / materialized path / closure table thay self-FK:** rejected cho scope này — chiều sâu thực tế ≤3, read path là getChildren(parent); adjacency list + index là đủ và đơn giản nhất cho declarative schema. Đề revisit nếu có query toàn-cây sâu (report/geocode).
- **Profile trong DB với admin CRUD:** rejected (đề xuất) — admin-editable profile mở cửa lệch code/data; profile là hành vi render, đúng chỗ sống cùng code.
- **Migrate ngay (big-bang drop sub_city):** rejected — 4 module consumer + GraphQL surface cần cửa sổ BC; incremental 3 phase an toàn hơn với Tier-2 gates.

## Consequences

- (+) Arbitrary depth một cấu trúc dữ liệu duy nhất; country khác (AU/US…) dùng được ngay không cần custom module; VN legacy 3 cấp import được khi cần.
- (+) Label/level từ schema — hết mislabel "City" cho ward; hết cơ chế i18n `en_VN`.
- (+) ID-canonical end-to-end; GHN/GHTK khỏi bridge tên→ID.
- (+) Đóng luôn nhóm [BLOCK] security + cache-key bug trong cùng luồng Phase 2.
- (−) Schema migration Tier-2 + 3 phase triển khai (~10 tickets); cần prod data scan trước drop.
- (−) Renderer/admin JS phải viết lại theo schema (port validation rule-by-rule) — regression surface rộng (customer form, OSC, admin 4 surface).
- (−) Cửa sổ BC: 2 representation schema song song (legacy + flagged schema) trong Phase 1-2.
- Follow-up: DEC-FEATE2HM1J-001 đánh dấu superseded (point 3); TASK-KCBDDT-style de-VN audit lặp lại sau Phase 2; update 09 module map + BR-002 (TASK-ZHFVRH).

## Affected components

`CMP-ADDR` (Secomm_AddressDropdown — recursive directory + profile/schema engine), `CMP-VNADDR` (Secomm_VietNamAddress — profile vn_admin_2025 [naming gốc vn_current, đổi theo DEC-FEATYA2C0W-003] + import + validation), `CMP-GHNMAPPING` (rời sub_city query), `CMP-GHN` (đổi nguồn sub_city/city_id reader), `CMP-GHTK` (WardIdBridge — không đổi, verify). Source: `app/code/Secomm/AddressDropdown/`, `app/code/Secomm/VietNamAddress/`, `app/code/Secomm/GhnAddressMapper/`, `app/code/Secomm/GiaoHangNhanh/`, `app/code/Secomm/Ghtk/`.

## Related records

> `work_items` (frontmatter) là canonical — validator parse. Mục này chỉ thêm narrative/link, KHÔNG được contradict `work_items`.

- Feature: FEAT-2PZQKJ (sub-tickets TASK-9AEAQQ…TASK-ZHFVRH)
- Supersedes: DEC-FEATE2HM1J-001 (point 3; points 1-2 restated tại Decision 1/5/6)
- Còn hiệu lực: DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001
- Precedent: FEAT-E2HM1J, TASK-KCBDDT, TASK-3F6QWZ
- DECISIONS.md index: DEC-FEAT2PZQKJ-001
