# SL-008 — GHTK address mapping (table-first, canonical key, hardened CSV upload, best-effort vi_VN fallback)

**Type:** Task (sub-ticket of feature [FEAT-006](../records/features/FEAT-006.md))
**Priority:** High (làm trước — data layer cho carrier)
**Estimate:** ~8–14h (tăng so với ước lượng cũ do canonical key gap + hardened importer)
**Mode:** A (Tier-2: shipping data + admin upload + address data dependency)
**Feature:** [FEAT-006](../records/features/FEAT-006.md) (GHTK carrier)
**Placement:** `app/code/Secomm/Ghtk/`
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-07-30 · **Status:** Ready *(gating met: DEC-020 accepted, path B; CSV contract + fallback semantics chốt)*

## Description

Tạo **bảng mapping GHTK** (`secomm_ghtk_address_map`) làm **nguồn chính** cho mọi giá trị địa chỉ đưa vào GHTK API. Mọi call API (fee/order ở SL-009/010) đều đi qua **resolver** đọc từ bảng này. Bảng update được qua **CSV upload trong admin config** (hardened). Khi bảng thiếu mapping, resolver **fallback** lấy tên **vi_VN chuẩn** từ data layer để build **best-effort request** (KHÔNG đảm bảo GHTK nhận diện — chỉ đảm bảo có dữ liệu hợp lệ để build request).

### Requirement changes 2026-07-30 (so với bản cũ)

1. **Canonical key đổi** từ `(region_id, ward_name)` → `(country_id, region_id, ward_id)` (DEC-020). `ward_id` = `directory_region_city.city_id` (VN 2-level ward = city level; sub-city là mức 3 cũ, không dùng).
2. **Fallback semantics sửa**: bỏ claim "fallback đảm bảo API luôn hoạt động". Fallback = best-effort, có thể bị GHTK reject; failure phải graceful (no crash, no rate, log masked).
3. **CSV importer siết (replace-all)**: ACL + form key + MIME/extension + UTF-8/BOM + max size + full-file validate before commit + transactional replace-all (CSV = complete state; stage+swap) + row validate + duplicate detect + admin confirm + audit before/after + summary + sample download.
4. **Resolver split pointer**: SL-008 xây **mapping + destination resolver foundation**; pickup resolver tách (DEC-021, SL-009).

## Mapping design

**Table `secomm_ghtk_address_map`** (DEC-020):
| Column | Type | Note |
|---|---|---|
| `map_id` | int PK | |
| `country_id` | varchar(2) | ISO country (VN) — part of canonical key |
| `region_id` | int | project province (FK `directory_country_region`) |
| `ward_id` | int | project ward = `directory_region_city.city_id` (2-level ward; stable PK) |
| `source_province_name` | varchar | province display name (trace/import support, NOT join key) |
| `source_ward_name` | varchar | ward display name (trace/import, NOT join key) |
| `ghtk_province` | varchar | tên province GHTK (cho API) |
| `ghtk_district` | varchar NULL | optional |
| `ghtk_ward` | varchar | tên ward GHTK (cho API) |
| `is_active` | smallint | enable/disable row |
| `created_at` / `updated_at` | timestamp | audit |

UNIQUE `(country_id, region_id, ward_id)`.

> **ward_id availability gap (DEC-020):** ward_id = `directory_region_city.city_id` tồn tại ở DB schema, nhưng GraphQL surface (`GetListCity`) và persistence lưu ward dưới dạng name string → `city_id` không có sẵn trực tiếp trên payload. Path chốt (SA/TL): **(A)** expose+persist `city_id` ở address data layer (generic change, ticket riêng, DEC-019 boundary) HOẶC **(B)** GHTK-internal name→`city_id` bridge resolver (compatibility/tech-debt tạm). Chọn trước khi SL-008 ready.

**Destination resolver** (`Secomm\Ghtk\Model\Address\DestinationAddressResolver`):
```
resolve(country_id, region_id, ward_id):
  row = map(country_id, region_id, ward_id)         // canonical key, prefer stable ID
  if row && row.is_active: return (row.ghtk_province, row.ghtk_district, row.ghtk_ward)   // mapping hit
  // best-effort vi_VN fallback (NOT "API always works")
  province_vi = directory_country_region_name(region_id, locale=vi_VN)
  ward_vi     = city-level name by city_id (= ward_id) (or bridge by name — path B)
  return (province_vi, null, ward_vi)               // GHTK may still reject
```
Name-normalized fallback chỉ khi request cũ không có `ward_id` (compatibility). Pickup resolver tách riêng (SL-009, DEC-021).

## Acceptance Criteria

- [ ] **AC-1 (Schema):** `etc/db_schema.xml` bảng `secomm_ghtk_address_map` (cột + unique `(country_id, region_id, ward_id)` + `is_active` + audit timestamps); `db_schema_whitelist`.
- [ ] **AC-2 (Resolver — destination, mapping-first):** `DestinationAddressResolver->resolve(country_id, region_id, ward_id)` — mapping hit (is_active) → GHTK names; miss → best-effort vi_VN names (province via `directory_country_region_name` vi_VN; ward via sub_city name vi_VN). Prefer stable `ward_id`; name fallback chỉ cho legacy request. Reuse data `Secomm_AddressDropdown`/`VietNamAddress` (không duplicate). **Xử lý ward_id gap (DEC-020 path A hoặc B).**
- [ ] **AC-3 (CSV upload admin — hardened, replace-all):** config section GHTK có field upload CSV; importer service có ACL resource + form key + extension/MIME validation + UTF-8/BOM handling + max file size; **full-file validation trước commit**; **transactional replace-all** (CSV = complete mapping state; rows không có trong CSV bị remove; stage+swap hoặc delete-all+insert trong 1 transaction); row-level validation; duplicate detection; admin confirm + audit before/after; summary `inserted/updated/removed/skipped/failed`; sample CSV download; audit timestamp + admin identity. Canonical CSV header: `country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active`.
- [ ] **AC-4 (Cache):** resolver cache kết quả theo canonical key (tránh lookup lặp); invalidate khi CSV upload.
- [ ] **AC-5 (Robustness — fallback semantics đúng):** mapping miss → fallback vi_VN **không throw** (log warning masked); API có thể vẫn reject — caller xử lý graceful (SL-009). **KHÔNG** assert "API luôn nhận giá trị hợp lệ". API không nhận giá trị rỗng cho required field nếu DB có data.
- [ ] **AC-6 (i18n/ACL):** admin upload form label vi/en; ACL resource cho upload + sample download.
- [ ] **AC-7 (No master-data duplication):** không duplicate VN address master data trong `Secomm_Ghtk`; reuse `Secomm_AddressDropdown`/`VietNamAddress` (read-only).

## Technical Notes

- **Reuse data:** fallback vi_VN đọc từ bảng address của `Secomm_AddressDropdown` (VN data do `Secomm_VietNamAddress` import). KHÔNG duplicate master data; KHÔNG sửa generic module bằng carrier-specific mapping (DEC-019).
- **CSV upload lean:** importer service nhỏ testable transactional phù hợp hơn Magento Import Framework — KHÔNG bắt buộc Import Framework. (Pattern hiện có của AddressDropdown là ImportExport entity + non-transactional — đây là pattern MỚI strict hơn.)
- **CSV key:** canonical = `ward_id`. Importer hỗ trợ `ward_id`; không dùng `ward_name` làm primary key.
- **DEC-019:** bảng mapping + resolver owned bởi `Secomm_Ghtk`; generic module không đổi.
- Phụ thuộc FEAT-005: ward đến từ address payload; ward_id gap (DEC-020) phải chốt trước ready.

## Files/Areas Affected (planned)

- `app/code/Secomm/Ghtk/etc/db_schema.xml` (+ whitelist) — NEW
- `app/code/Secomm/Ghtk/Model/Address/DestinationAddressResolver.php` — NEW
- `app/code/Secomm/Ghtk/Model/Address/` (ward_id bridge if path B) — NEW
- `app/code/Secomm/Ghtk/Model/ResourceModel/GhtkAddressMap*` — NEW (resource + collection)
- `app/code/Secomm/Ghtk/Model/GhtkAddressMapImport*` (importer service: validate + transactional replace-all + summary + audit) — NEW
- `app/code/Secomm/Ghtk/Controller/Adminhtml/Ghtk/Upload.php` + ACL + sample download — NEW
- `app/code/Secomm/Ghtk/view/adminhtml/...` (config upload field + form) — NEW
- `app/code/Secomm/Ghtk/etc/adminhtml/system.xml` (upload field trong GHTK section; token/partner ở SL-009)
- **Reuse, không sửa:** `Secomm_AddressDropdown`, `Secomm_VietNamAddress`.

## Risks

- Tier-2: chạm address data + admin upload → escalate (SA/TL).
- **ward_id gap (DEC-020)** — block ready cho đến khi path A/B chốt.
- ward name trùng trong fallback → canonical key theo `city_id` giải quyết; bridge name→id (path B) phải deterministic.
- CSV sai format → full-file validate trước commit + transactional → không corrupt bảng.

## Open Questions

- ~~Q1 (SA/TL): ward_id path — (A) expose+persist `city_id` (generic change, ticket riêng) hay (B) GHTK-internal name→id bridge (tech-debt tạm)?~~ **RESOLVED 2026-07-30 (DEC-020 accepted):** path **B** — GHTK-internal name→`city_id` bridge (không sửa generic module; path A = long-term follow-up).
- Q2 (Dev): cache level cho resolver (request-scope vs identifier)?
- ~~Q3 (SA): CSV replace-all hay upsert-only?~~ **RESOLVED 2026-07-30 (user): replace-all** — CSV = complete mapping state; transactional (stage+swap); full-file validate + admin confirm + audit before/after.

## Definition of Done

- [ ] Bảng + destination resolver (AC-1/2) — mapping hit + miss (best-effort fallback, semantics đúng) test
- [ ] Hardened CSV upload (AC-3) — transactional + summary test
- [ ] ward_id path (DEC-020) chốt
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] QC: upload CSV seed → resolver hit ra GHTK names; xóa row → fallback vi_VN (best-effort, log masked); API field không rỗng nếu DB có data; CSV bad-format → rollback, không corrupt
- [ ] Evidence `.ai/runtime/evidence/FEAT-006/` (SL-008)

## Related

- Spec: [ghtk-address-mapping](../specs/ghtk-address-mapping.md) · Plan: [SL-008 plan](../plans/SL-008-implementation-plan.md)
- Feature: [FEAT-006](../records/features/FEAT-006.md) · Decisions: [DEC-020](../records/decisions/DEC-020.md) (canonical key+fallback) · [DEC-021](../records/decisions/DEC-021.md) (resolver split) · [DEC-018](../records/decisions/DEC-018.md) · [DEC-019](../records/decisions/DEC-019.md)
