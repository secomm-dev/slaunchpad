# TASK-9JZNJ2 — Verify: `ko is not defined` trên MSI source form

## Root cause xác nhận

Console trace user dán khớp từng dòng file hiện hành:
`refreshCascade (source-city.js:91) → renderLevels (:104) → levels (:109)`.
Dòng 109 = `options: ko.observableArray([])` — bare `ko` không có trong `define([...])`.
Knockout 3.5.1 (`lib/web/knockoutjs/knockout.js`) chạy nhánh AMD trên adminhtml → không bao
giờ gán `window.ko` → `ReferenceError` ném trong `.then()` của GraphQL promise. Lỗi xảy ra
SAU khi `hasSchema(true)` đã set (ẩn native input) → City trắng, cascade chết.

## Đã verify (server-side, 2026-10-02)

| Check | Kết quả |
|---|---|
| Fix áp dụng | `define` thêm `'ko'` (param thứ 2) — `app/code/Secomm/VietNamAddress/view/adminhtml/web/js/form/element/source-city.js` |
| File static được serve đúng URL trong trace (`static/version1790916490/adminhtml/Magento/backend/en_US/...`) | HTTP 200, nội dung có `'ko'` trong define |
| `node --check` file JS | pass |
| Quét toàn bộ admin JS của `Secomm_VietNamAddress` + `Secomm_AddressDropdown` | chỉ duy nhất source-city.js dùng `ko` thiếu dep |
| Region select (mechanism + data) | stock `RegionSource` + `filterBy country_id`; DB `directory_country_region` VN = 34 rows (đúng cấu trúc 34 tỉnh/thành sau sáp nhập 2025) × 2 locale (`directory_country_region_name` = 68 rows); không có preference/mixin nào chặn → không đổi code region |
| GraphQL `addressSchema` | chạy tốt trước fix (lỗi ném BÊN TRONG `.then()` sau khi schema resolve) — data path đã được chính error chứng minh |
| Validator before/after | 56 FAIL → 56 FAIL (baseline pre-existing, không phát sinh) |
| MAGE_MODE | developer (static fallback tự phục vụ file mới) |

## Follow-up 2026-10-02 (sau user re-test)

**Kết quả re-test:** city cascade RENDER (level-0 placeholder "Phường/Xã/Đặc khu" + select
disabled hiện đúng thiết kế) → ko fix hoạt động, schema GraphQL load OK.

1. **Template error "Failed to load …source-city" — stale serving, không phải bug code.**
   Curl đầu tiên 404 là do tôi thiếu segment `template/` trong URL. URL chuẩn browser
   (`.../Secomm_VietNamAddress/template/form/element/source-city.html`) trả HTTP 200 + đúng
   nội dung (versioned + unversioned). Symlink `pub/static` tạo 14:53 hôm nay (static
   deploy). → cần hard-reload; không đổi code.

2. **Region row mất — root cause KHÁC: dangling `region_id`.** `inventory_source.default`
   giữ `region_id=1185` (scheme cũ) nhưng `directory_country_region` sau sync 2025 có id mới
   (34 tỉnh, 1191+; HCM = 1205/VN-15) → vendor `Magento_InventoryAdminUi/js/form/element/region`
   làm `indexedOptions[value].label` không guard → TypeError lúc hydrate → field không render.
   Đây là "region không load" gốc từ báo cáo đầu tiên. Audit DB: 51 dangling refs
   (source 1, customer 2, quote 35, order 13) + `shipping/origin/region_id=1185`.
   → Repair 2 UPDATE guarded (source + shipping origin → 1205) trong **TASK-H9ZT7G**;
   follow-up hệ thống **TASK-2FQJHX** (tbd, Tier 2).
