# TASK-M8D1CH — Fix sort by city name trên legacy GetListCity cascade (Vietnamese-correct + fallback default_name)

**Type:** Bug fix (small standalone change)
**Priority:** Medium (user-visible trên staging: city dropdown không sort đúng cho một vài region/store view)
**Estimate:** ~2h
**Mode:** A
**Risk tier:** Tier 2-lite (AddressDropdown; KHÔNG đụng payment/checkout-core/shipping/order/PII — chỉ ORDER BY của 1 GraphQL query legacy)
**Author:** AI draft · **Date:** 2026-09-07
**Status:** Completed
**Specification:** TASK-M8D1CH — Mini-Spec embedded (this file)
**Related:** commit `702315f8` ("Sort by city name" — bản naive cần vá) · BUG-25XDH4 (cascade Region→City) · FEAT-2PZQKJ (schema path đã sort đúng — tham chiếu pattern)

> **Update 2026-09-07:** Sort expression Vietnamese-specific của task này đã bị **supersede bởi [TASK-7HVGAB](TASK-7HVGAB-generic-location-sort.md)** — generic module không được chứa locale rule. AC-3/AC-4 của task này vẫn giữ nguyên hiệu lực qua test của TASK-7HVGAB.

## Description

Commit `702315f8` thêm `$cityCollection->setOrder('name', 'ASC')` vào [GetListCityGraphql.php](../../app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php). Sort này **không đúng** với 3 lý do (đã verify trên DB dev `fashion_launchpad` 2026-09-07):

1. **NULL-blind theo locale**: key `name` đến từ LEFT JOIN `directory_region_city_name` theo locale. DB chỉ có `vi_VN` (3.321/3.321 rows); `en_US` = **0 rows** → trên store view `fashion_en` và mọi form admin (locale mặc định `en_US`) key sort toàn NULL → `ORDER BY name` thành no-op, thứ tự trả về tuỳ execution plan (MySQL) → "vài region không sort".
2. **Collation không Vietnamese-correct**: cột `name` là `utf8_general_ci`; đã chứng minh 2 ORDER BY (plain vs chuẩn hoá Đ→D + `utf8mb4_unicode_ci`) cho thứ tự khác nhau. Không collation utf8 nào sort Đ như D — đã verify 2026-08-28 (comment [LocationHierarchyProvider.php:270-274](../../app/code/Secomm/AddressDropdown/Model/LocationHierarchyProvider.php#L270-L274)).
3. **Sort key ≠ label hiển thị**: resolver trả label `name ?? default_name` nhưng sort chỉ theo `name` — mọi city thiếu locale name tụt nhóm NULL lên đầu ASC không thứ tự.

---

## Mini Spec: TASK-M8D1CH (embedded, MINI)

| Field | Value |
|-------|-------|
| Specification ID | TASK-M8D1CH — Mini-Spec embedded, identity = chính ticket |
| Specification Level | MINI |
| Ticket | TASK-M8D1CH (file này) |
| Mode | A |
| Author | AI draft |
| Date | 2026-09-07 |
| Estimate | ~2h |

### Goal

Legacy cascade `GetListCity` (renderer `legacy`, template `edit.phtml`) phải trả city list **sort theo đúng label hiển thị**, thứ tự Vietnamese-correct, cho mọi locale/store view/area — thay cho `setOrder('name', 'ASC')` đang no-op hoặc sai.

### Expected Behavior

- Sau chọn region, city dropdown sắp xếp theo label (`COALESCE(locale name, default_name)`) tăng dần, Đ/đ xếp chung D/d, không phân biệt dấu thanh; tiebreak `city_id` cho deterministic.
- Trên locale thiếu name rows (`en_US`, admin) — sort theo `default_name` (label fallback), không còn thứ tự tuỳ plan.
- Kết quả nhất quán với path schema (`addressLocations` → `LocationHierarchyProvider::fetchChildren()`).

### Constraints / Rules

- Sort expression phải mirror [LocationHierarchyProvider.php:275-277](../../app/code/Secomm/AddressDropdown/Model/LocationHierarchyProvider.php#L270-L280): `REPLACE(REPLACE(CONVERT(key USING utf8mb4) COLLATE utf8mb4_unicode_ci, 'Đ','D'),'đ','d') ASC` + tiebreak id — KHÔNG đổi collation bảng, KHÔNG đụng schema DB.
- Sort key = `COALESCE(rname.name, main_table.default_name)` — đúng label resolver trả về.
- Chỉ sửa legacy resolver [GetListCityGraphql.php](../../app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php); KHÔNG sửa `CityLocaleCollection` (tránh ảnh hưởng `CustomerData/CityData` customer-data cache + admin `Selector/City`), KHÔNG đụng path schema.
- Sort áp cho cả nhánh không có `region_id` (admin config field load all).
- Giữ hành vi label fallback `name ?? default_name` nguyên vẹn.

### Acceptance Criteria

- [ ] AC-1: Given store `fashion_vi`, When load cities cho 1 region bất kỳ, Then thứ tự label VI đúng alphabet VN (Đ/đ như D/d, bỏ dấu thanh) — incl. các name chứa Đ giữa từ.
- [ ] AC-2: Given store `fashion_en` hoặc area admin (locale `en_US`, name NULL), Then list sort theo `default_name` ổn định (không còn thứ tự tuỳ plan).
- [ ] AC-3: Path schema (`addressLocations`) không đổi hành vi; `CustomerData/CityData` + admin `Selector/City` không đổi.
- [ ] AC-4: Unit test mới assert ORDER BY expression + label fallback; toàn bộ unit tests `Secomm_AddressDropdown` xanh.

### Out of Scope

- Data artifact `"1 Bảo Lộc"` (prefix số trong `directory_region_city_name.name`) — cleanup ở import, làm ticket riêng.
- Sort cho admin `Selector/City` + `CityData` customer-data.
- Deprecate/remove path `GetListCity` (chuyển surface sang schema renderer — d domain TASK-3T3NSV/YQSS3M).

## Approach

- ORDER BY functional (REPLACE+CONVERT) không dùng index → filesort; chấp nhận: tập con theo region nhỏ, query đã `@cache(cacheable: false)` và path là `@deprecated`.
- Aliases `rname`/`main_table` do `CityLocaleCollection::_initSelect` tạo — dùng trong expression tại resolver là safe (join luôn chạy).
- Risk thấp nhất: sai cú pháp SQL → regression query. Mitigate bằng unit test + smoke GraphQL trên dev.

### Open Questions

- (không có — approach đã chốt theo review 2026-09-07)
