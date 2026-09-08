# TASK-7HVGAB — Generic language-agnostic location sort trong Secomm_AddressDropdown

**Type:** Refactor (boundary fix — remove locale-specific logic from generic module)
**Priority:** Medium
**Estimate:** ~3h
**Mode:** A
**Risk tier:** Tier 2 (per AGENTS.md §12: `Secomm_AddressDropdown` — custom address + GraphQL surface; + schema baseline change = DB migration category → **chờ TL review trước khi chạy `setup:upgrade`**)
**Author:** AI draft · **Date:** 2026-09-07
**Status:** In Progress
**Specification:** TASK-7HVGAB — Mini-Spec embedded (this file)
**Related:** TASK-M8D1CH (legacy cascade sort — sort expression của nó bị genericize bởi task này) · FEAT-2PZQKJ (hierarchy engine) · BUG-25XDH4 (cascade)

## Description

`Secomm_AddressDropdown` là module **global** — KHÔNG được chứa business rule riêng cho Việt Nam hay locale nào. Hiện có 2 điểm sort chứa Vietnamese-specific normalization (`REPLACE('Đ','D')`, `REPLACE('đ','d')` + `CONVERT ... COLLATE utf8mb4_unicode_ci` hard-code):

1. [LocationHierarchyProvider.php:270-277](../../app/code/Secomm/AddressDropdown/Model/LocationHierarchyProvider.php#L270-L277) — schema path (`addressLocations`)
2. [GetListCityGraphql.php:57-66](../../app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php) — legacy cascade (thêm 2026-09-07, TASK-M8D1CH)

Ngoài ra audit còn thấy các sort site không deterministic / thiếu fallback:

| Site | Hiện trạng |
|---|---|
| `CityLocaleCollection` | KHÔNG có ORDER BY → thứ tự tuỳ execution plan |
| `Helper/Address::getCityData()` | `order('n.name ASC')` — không fallback `default_name`, không tiebreak `city_id` |
| `GetRegionDataPlugin` | PHP `strcmp` theo name — deterministic (byte order) nhưng không collation-aware (region-level, ngoài scope) |
| `Query/CityName/GetListQuery` + `CityNameCollection` | **unused** (0 consumers) — không sửa |

## Audit result (charset/collation, dev DB 2026-09-07)

| Fact | Giá trị |
|---|---|
| MySQL dev | **5.7.39** (không phải 8.0 như stack doc) |
| DB default | `utf8mb4` / `utf8mb4_general_ci` |
| `directory_region_city.default_name` | **`utf8_general_ci`** — chưa utf8mb4 |
| `directory_region_city_name.name` | **`utf8_general_ci`** — chưa utf8mb4 |
| db_schema.xml | KHÔNG khai báo `charset`/`collation` cho 2 bảng |

→ Premise "đã dùng utf8mb4_unicode_ci" **chưa đúng**: schema baseline phải tự đảm bảo (không ép bằng query-level conversion).

---

## Mini Spec: TASK-7HVGAB (embedded, MINI)

| Field | Value |
|-------|-------|
| Specification ID | TASK-7HVGAB — Mini-Spec embedded, identity = chính ticket |
| Specification Level | MINI |
| Ticket | TASK-7HVGAB (file này) |
| Mode | A |
| Author | AI draft |
| Date | 2026-09-07 |
| Estimate | ~3h |

### Goal

Mọi list location/city từ `Secomm_AddressDropdown` trả về theo **một canonical generic sort rule**, không có logic language/country/locale-specific nào trong module.

### Expected Behavior

Canonical rule (áp ở mọi site trả list):

```sql
ORDER BY COALESCE(<localized name>, <default_name>) ASC, <city_id> ASC
```

- localized name (join theo locale) là sort key chính; fallback `default_name` khi thiếu;
- `city_id ASC` = deterministic tie-breaker (duplicate/equivalent names vẫn ổn định);
- không phụ thuộc insert order / natural DB order;
- behavior giống nhau trên local/staging khi cùng data + collation.

### Constraints / Rules

- **Cấm** trong `Secomm_AddressDropdown`: character mapping theo locale (`REPLACE('Đ','D')`…), hard-code `VN`/`vi_VN`, region cụ thể, `CONVERT(... USING utf8mb4)`/`COLLATE` hard-code trong query.
- Charset/collation là **environment concern** (Magento core convention: 0/85 core db_schema.xml khai báo) — KHÔNG khai báo trong db_schema; module chỉ yêu cầu "một Unicode collation bất kỳ + deterministic". Baseline do chuẩn tạo DB đảm bảo (xem Environment Baseline Requirement).
- Locale-specific sorter (nếu sau này cần) thuộc `Secomm_VietNamAddress` qua extension point — **KHÔNG implement trong task này**, không thêm abstraction khi chưa có use case.
- Không sort ở frontend JS; không refactor architecture khác; không đụng persistence/hierarchy.
- Sort PHP `usort` trong `AddressSchemaProvider` (levels theo `sort_order`) không phải location list — ngoài scope.

### Acceptance Criteria

- [ ] AC-1: City/location list luôn deterministic (ORDER BY có tiebreak id, không phụ thuộc insert order).
- [ ] AC-2: Sort theo localized display name; thiếu → fallback `default_name`.
- [ ] AC-3: Không còn ký tự/logic Vietnamese-specific trong `Secomm_AddressDropdown` (guard test assert expression ASCII-only).
- [ ] AC-4: db_schema.xml thuần structure (charset/collation do environment owns); environment baseline requirement được ghi lại trong ticket này cho DevOps.
- [ ] AC-5: GraphQL smoke cả 2 store views trả list deterministic.
- [ ] AC-6: Unit suites xanh; tests cover: localized name available / missing→fallback / same name→stable by city_id.

### Out of Scope

- VN-specific sorter extension point (chưa có use case được approve).
- `GetRegionDataPlugin` strcmp region sort; `GetListQuery`/`CityNameCollection` (unused).
- Quote hygiene `n.locale = '...'` trong `Helper/Address` (string interpolation an toàn vì locale từ resolver — ghi debt).
- Chạy `setup:upgrade` trên bất kỳ environment nào.

## Approach

1. `LocationHierarchyProvider::fetchChildren` — `ORDER BY COALESCE(n.name, c.default_name) ASC, c.city_id ASC` + comment generic (thay comment Vietnamese §8).
2. `CityLocaleCollection::_initSelect` — thêm canonical ORDER sau join → mọi consumer (GetListCityGraphql, CityData section) inherits; **resolver bỏ sort riêng**.
3. `GetListCityGraphql` — remove custom order + comment.
4. `Helper/Address::getCityData` — order = COALESCE + tiebreak.
5. `db_schema.xml` — **reverted 2026-09-07 sau review**: khai báo charset/collation bị bỏ (quyết định: environment owns collation). Xem Environment Baseline Requirement bên dưới.
6. Tests: rewrite `GetListCityGraphqlTest` (bỏ assert REPLACE); thêm collection test assert ORDER part + ASCII-only guard; smoke GraphQL.

### Open Questions

- Staging MySQL version + collation hiện tại cần verify trước khi apply environment baseline (TL/DevOps).

## Environment Baseline Requirement (owner: DevOps/DBA — ngoài scope code module)

**Facts (verified 2026-09-07):**
- Magento core convention: 0/85 core `db_schema.xml` khai báo charset/collation — charset là environment concern.
- MySQL 8.x tạo DB default = `utf8mb4` + **`utf8mb4_0900_ai_ci`** (KHÔNG tự động `utf8mb4_unicode_ci` — cần `COLLATE` tường minh khi `CREATE DATABASE`).
- Dev DB hiện drift toàn-DB (cả core): mọi bảng ở `utf8`/`utf8_general_ci`, DB default `utf8mb4_general_ci`.

**Yêu cầu:**
1. **Env mới**: `CREATE DATABASE <db> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` — team chốt 1 chuẩn collation (đề xuất `utf8mb4_unicode_ci`: có mặt cả MySQL 5.7 lẫn 8.x; `0900_ai_ci` chỉ có trên 8.x) và ghi vào deploy guide/env requirements.
2. **Env cũ đã drift (dev đã confirm)**: one-time migration do DBA chạy, toàn-DB (KHÔNG chỉ 2 bảng — tránh mixed-collation + lỗi MySQL 1267 "Illegal mix of collations" khi string JOIN giữa custom tables và core tables):
   ```sql
   ALTER DATABASE <db> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ALTER TABLE <t> CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- từng bảng
   ```
   Tier-2 — backup trước, chạy trong maintenance window.
3. **Verify staging**: MySQL version + collation của `directory_region_city*` trước khi quyết định có cần convert không.

## Risks

- Sau khi bỏ REPLACE: chữ Đ sort theo DB collation thuần — `utf8_general_ci`/`utf8mb4_unicode_ci` đều coi Đ là letter riêng (đặt sau D-group) → thứ tự VN không "hoàn hảo alphabet" nhưng **deterministic**. Trade-off chấp nhận theo boundary; VN-perfect order là việc của `Secomm_VietNamAddress` khi có yêu cầu.
- Environment convert `utf8_general_ci` → `utf8mb4_unicode_ci` (toàn DB, DBA-owned) đổi thứ tự sort trên data thực — chạy sau backup, verify lại sort smoke sau convert. Cho đến khi convert: sort vẫn deterministic theo collation hiện có (`utf8_general_ci`).
