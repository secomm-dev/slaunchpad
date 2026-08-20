# Feature Spec — GHTK address mapping (TASK-YJENM2)

Specification ID: SPEC-TASK-YJENM2
Feature ID: NONE
Specification Level: FULL

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho ticket TASK-YJENM2 (fee scope). Parent: FEAT-AE761Z. Mode A · Tier-2. -->
<!-- Decisions: DEC-TASKYJENM2-001 (accepted, path B) · DEC-TASKBRKHN4-001 (accepted) · DEC-FEATJSZQV3-002/019/DEC-8. -->
<!-- Audit 2026-07-30; corrected 2026-07-31: VN 2-level → ward = CITY level. ward_id = directory_region_city.city_id (stable PK); ward name vi_VN = directory_region_city_name. Sub-city (3rd level) NOT used. GetListCity sources the Ward (FEAT-JSZQV3 U3); persistence stores ward as name string → ward_id gap → path B bridge. -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)

## Feature Overview

**Feature name**: GHTK address mapping data layer + DestinationAddressResolver

**Ticket reference**: [TASK-YJENM2](../tickets/TASK-YJENM2-ghtk-address-mapping.md) · Parent [FEAT-AE761Z](../records/features/FEAT-AE761Z.md)

**Feature type**: New (data layer cho GHTK carrier)

**Priority**: P1 (High — làm trước, data layer cho TASK-BRKHN4)

**Scope note (2026-07-30)**: fee-first. Order sync (TASK-KV328X) PARKED. Spec này chỉ bao phủ mapping + destination resolver (dùng cho rate ở TASK-BRKHN4).

---

## User Stories

- **US-001**: As a merchant admin, I want to seed/update the GHTK address mapping via a hardened CSV upload so that GHTK receives correct province/ward names without editing code.
- **US-002**: As a developer (TASK-BRKHN4), I want a single `DestinationAddressResolver` that maps a Magento address `(country_id, region_id, ward_id)` → GHTK names so that `collectRates` gets a deterministic, best-effort value.
- **US-003**: As a TL/SRE, I want mapping misses to fall back to best-effort vi_VN names (not crash, not assert "API always works") so that rate estimation degrades gracefully and logs enough masked data to grow the mapping.

---

## Acceptance Criteria

> Mỗi AC = một test case. Chi tiết lần theo ticket TASK-YJENM2 AC-1..AC-7.

- [ ] **AC-001 (Schema):** `etc/db_schema.xml` định nghĩa `secomm_ghtk_address_map` (`map_id` PK, `country_id`, `region_id`, `ward_id`, `source_province_name`, `source_ward_name`, `ghtk_province`, `ghtk_district` NULL, `ghtk_ward`, `is_active`, `created_at`, `updated_at`) với `UNIQUE(country_id, region_id, ward_id)`; khai báo `db_schema_whitelist.json`.
- [ ] **AC-002 (DestinationAddressResolver — mapping-first, path B):** `resolve(country_id, region_id, ward_id)` → hit `is_active` row → `(ghtk_province, ghtk_district, ghtk_ward)`; miss → best-effort vi_VN (province via `directory_country_region_name` vi_VN; ward via city-level name vi_VN). **ward_id bridge (path B):** resolver nhận `(region_id, ward_name)` từ legacy payload → bridge lookup `city_id` (= ward_id) trong `directory_region_city` deterministic theo `(region_id, default_name)`. Prefer stable `ward_id`; name fallback chỉ cho request cũ không có `ward_id`. Reuse data `Secomm_AddressDropdown`/`VietNamAddress` (KHÔNG duplicate, KHÔNG sửa generic).
- [ ] **AC-003 (Hardened CSV importer — replace-all):** admin upload field + importer service: ACL resource + form key + extension/MIME validation + UTF-8/BOM handling + max file size; **full-file validation trước commit**; **transactional** (all-or-nothing); row-level validation; duplicate detection; **replace-all semantics** (CSV = complete mapping state; rows không có trong CSV bị remove) — thực hiện trong **một DB transaction** (stage new set → swap, hoặc delete-all-in-scope + insert); summary `inserted/updated/removed/skipped/failed`; sample CSV download; audit timestamp + admin identity. Canonical CSV header: `country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active`.
- [ ] **AC-004 (Cache):** resolver cache kết quả theo canonical key (tránh lookup lặp); invalidate khi CSV upload.
- [ ] **AC-005 (Best-effort fallback semantics — đúng):** mapping miss → fallback vi_VN **không throw** (log warning masked). **KHÔNG** assert "API luôn nhận giá trị hợp lệ / luôn work" — caller (TASK-BRKHN4) xử lý GHTK reject graceful. Required field không rỗng nếu DB có data.
- [ ] **AC-006 (i18n + ACL):** admin upload form label vi/en; ACL resource cho upload + sample download.
- [ ] **AC-007 (No master-data duplication):** không duplicate VN address master data trong `Secomm_Ghtk`; chỉ reuse `Secomm_AddressDropdown`/`VietNamAddress` (read-only).

---

## Technical Notes

- **Module**: `app/code/Secomm/Ghtk/` (NEW). `registration.php` + `etc/module.xml` (sequence: `Secomm_AddressDropdown`, `Secomm_VietNamAddress`, `Magento_Directory`, `Magento_Config`).
- **Canonical key** `(country_id, region_id, ward_id)` (DEC-TASKYJENM2-001). `ward_id` = `directory_region_city.city_id` (VN 2-level ward = city level). `ward_name` chỉ display/audit/import.
- **Path B bridge**: `WardIdBridge::resolveWardId(region_id, ward_name)` → `SELECT city_id FROM directory_region_city WHERE region_id=? AND default_name=?` (deterministic trong region; city table có sẵn region_id). Đây là compatibility/tech-debt tạm; path A (expose+persist `city_id`) = long-term follow-up.
- **DestinationAddressResolver flow**:
  ```
  resolve(country_id, region_id, ward_id|null, ward_name|null):
    wid = ward_id ?: bridge(region_id, ward_name)          // path B cho legacy payload
    row = repo->findActive(country_id, region_id, wid)
    if row: return GhtkAddress(row.ghtk_province, row.ghtk_district, row.ghtk_ward)   // hit
    return bestEffortViVn(region_id, wid)                  // miss → graceful, có thể bị GHTK reject
  ```
- **bestEffortViVn**: province = `directory_country_region_name(locale=vi_VN)`; ward = localized city-level name (`directory_region_city_name` vi_VN, fallback `directory_region_city.default_name`) theo `city_id` (= ward_id). Reuse `Secomm_AddressDropdown` resource models (read-only).
- **CSV importer (lean service, replace-all — KHÔNG bắt buộc Magento Import Framework)**: `Model/GhtkAddressMapImport/Importer` — parse → **validate toàn file trước khi commit** → **replace-all** trong **một DB transaction** (CSV = complete mapping state): stage new rows → swap (truncate + insert staged) hoặc delete-all + insert; bất kỳ row lỗi → rollback toàn bộ, bảng nguyên. Summary `inserted/updated/removed/skipped/failed`. Controller `Adminhtml/Ghtk/Upload` (ACL + form key + `$_FILES` MIME/size) + `Adminhtml/Ghtk/SampleCsv` (download template).
- **Replace-all risk mitigation**: vì CSV incomplete có thể wipe mapping tốt → full-file validate bắt buộc + **admin confirm** trước replace + ghi audit (admin identity + timestamp + row count before/after). Cache invalidate sau commit.
- **Cache**: tag-based cache (`Magento\Framework\App\Cache`) key `ghtk_addr_map_{country}_{region}_{ward}`; clean tag `secomm_ghtk_address_map` sau import (replace-all).
- **Reuse, KHÔNG sửa**: `Secomm_AddressDropdown` (tables `directory_country_region(_name)`, `directory_region_city(_name)` — VN 2-level province + ward; sub-city level NOT used), `Secomm_VietNamAddress` (data). DEC-FEATJSZQV3-003: carrier mapping owned by `Secomm_Ghtk`.

---

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| DEC-TASKYJENM2-001 (canonical key + path B) | decision | accepted | unblocks TASK-YJENM2 |
| DEC-TASKBRKHN4-001 (dest/pickup resolver split) | decision | accepted | destination resolver = TASK-YJENM2; pickup = TASK-BRKHN4 |
| FEAT-JSZQV3 / TASK-FD6A9X (VN 2-level address) | feature | proposed | ward đến từ address payload (name string hiện tại) |
| `Secomm_AddressDropdown` data tables | data | current | reuse read-only; `directory_region_city.city_id` (2-level ward) source of truth |

---

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| ward name trùng trong region → bridge không deterministic | L | M | bridge key `(region_id, default_name)` + log warning khi >1 match; long-term path A |
| CSV sai format corrupt bảng | L | H | full-file validate trước commit + transactional (rollback toàn bộ) |
| Replace-all: CSV incomplete wipe mapping tốt | M | H | full-file validate + admin confirm + audit (before/after count); transactional rollback nếu lỗi |
| Fallback vi_VN che gap mapping → rate sai "âm thầm" | M | M | log masked đủ dữ liệu; QC test miss rõ "best-effort, có thể no-rate" |
| Generic module bị sửa nhầm (DEC-FEATJSZQV3-003) | L | H | review diff; reuse read-only |

---

## Out of Scope

- PickupAddressResolver + carrier `collectRates` + fee API + weight + rate composition (→ TASK-BRKHN4).
- Order sync / `pick_money` / COD (→ TASK-KV328X, PARKED).
- Path A (expose+persist `city_id` (ward_id) ở generic module) — long-term follow-up, ticket riêng.
- GraphQL/REST surface cho mapping (admin CSV upload only release-1).

---

## Test Notes

- **Schema**: `bin/magento setup:db:declaration:generate-whitelist` + `setup:upgrade`; verify bảng + unique key.
- **Resolver**: mapping hit → GHTK names; miss → best-effort vi_VN (không throw); legacy payload (chỉ `ward_name`) → bridge; required field không rỗng nếu DB có data.
- **CSV importer (replace-all)**: seed hợp lệ → replace-all summary (inserted/updated/removed/skipped/failed); file bad-format → rollback toàn bộ, bảng không corrupt; re-upload nhỏ hơn → rows dư removed; UTF-8/BOM; oversize → reject; ACL gate; admin confirm + audit before/after.
- **Cache**: hit cache sau resolve; invalidate sau upload.
- Evidence → `.ai/runtime/evidence/FEAT-AE761Z/` (TASK-YJENM2).
