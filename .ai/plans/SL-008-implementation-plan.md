# Implementation Plan: SL-008 — GHTK address mapping

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-008-ghtk-address-mapping.md |

> Mode A · **Plan only — chưa viết code** (Hard Gate 3: No Code Without Plan).
> Tier-2 (shipping data + admin upload + address data — §12) → escalate SA/TL; code review trước/sau.
> Parent: [FEAT-006](../records/features/FEAT-006.md). Ticket: [SL-008](../tickets/SL-008-ghtk-address-mapping.md). Spec: [ghtk-address-mapping](../specs/SPEC-SL-008-ghtk-address-mapping.md).

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-008](../tickets/SL-008-ghtk-address-mapping.md) |
| Spec | [ghtk-address-mapping](../specs/SPEC-SL-008-ghtk-address-mapping.md) |
| Feature | [FEAT-006](../records/features/FEAT-006.md) |
| Author | AI draft |
| Reviewer (TL) | user (acting as SA/TL) — **pending approval** |
| Workflow Mode | A |
| Date | 2026-07-30 |
| Decisions | DEC-020 (accepted, path B) · DEC-021 (accepted) · DEC-018 · DEC-019 · DEC-8 |
| Validation level | **L3** (address data + admin upload — §8.6) |

## 1. Approach

**Carrier-owned mapping, reuse data (DEC-018/019):** bảng `secomm_ghtk_address_map` + `DestinationAddressResolver` nằm trong `Secomm_Ghtk`; **reuse** `Secomm_AddressDropdown`/`VietNamAddress` data (read-only, không sửa generic).

- **Canonical key `(country_id, region_id, ward_id)`** trên `city_id` (DEC-020; VN 2-level → ward = city level). **Path B bridge**: legacy payload (chỉ `ward_name`) → resolve `city_id` từ `directory_region_city` deterministic theo `(region_id, default_name)` (city table có sẵn region_id). Không expose/persist `city_id` ở generic (path A = long-term).
- **Best-effort fallback** (DEC-020): mapping miss → vi_VN names từ data layer; **không assert "API luôn work"** — GHTK có thể reject; caller (SL-009) xử lý graceful.
- **Hardened CSV importer (lean service, replace-all)**: full-file validate → transactional replace-all (CSV = complete state; stage+swap hoặc delete-all+insert) → summary + audit. Không bắt buộc Magento Import Framework.

**Lý do chọn:** path B = lean, tôn trọng DEC-019/DEC-8 (không sửa generic); best-effort fallback chính xác → không che gap; importer transactional → không corrupt bảng.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `Secomm/Ghtk/registration.php` + `etc/module.xml` | new | module bootstrap; sequence AddressDropdown/VietNamAddress/Directory/Config |
| `Secomm/Ghtk/etc/db_schema.xml` + `etc/db_schema_whitelist.json` | new | bảng `secomm_ghtk_address_map` + unique key (AC-1) |
| `Secomm/Ghtk/Model/GhtkAddressMap.php` + `Model/ResourceModel/GhtkAddressMap*` (resource + collection) | new | persistence model (AC-1) |
| `Secomm/Ghtk/Model/Address/DestinationAddressResolver.php` | new | mapping-first + best-effort (AC-2) |
| `Secomm/Ghtk/Model/Address/WardIdBridge.php` | new | path B: name→`city_id` (AC-2) |
| `Secomm/Ghtk/Model/Address/BestEffortViVnResolver.php` | new | fallback vi_VN từ AddressDropdown data (AC-2/5) |
| `Secomm/Ghtk/Model/GhtkAddressMapImport/Importer.php` (+ Validator + Summary) | new | hardened transactional **replace-all** (AC-3) |
| `Secomm/Ghtk/Controller/Adminhtml/Ghtk/Upload.php` + `SampleCsv.php` | new | upload (ACL+form key+MIME+size) + sample download (AC-3/6) |
| `Secomm/Ghtk/files/sample_address_map.csv` | new | maintained sample CSV streamed by `SampleCsv` (download template; replace/expand nội dung khi cần) |
| `Secomm/Ghtk/Model/Address/ResolverCache.php` (hoặc wrap cacheInterface trong resolver) | new | cache theo canonical key + invalidate (AC-4) |
| `Secomm/Ghtk/etc/adminhtml/system.xml` (+ `acl.xml`) | new | GHTK section upload field + ACL resource (AC-3/6) |
| `Secomm/Ghtk/view/adminhtml/...` (config upload field + form) | new | admin UI (AC-6) |
| `Secomm/Ghtk/i18n/vi_VN.csv` + `en_US.csv` | new | admin labels vi/en (AC-6) |
| `Secomm/Ghtk/etc/di.xml` (preferences + cache config) | new | DI wiring + cache type/tag |
| **Không sửa** `Secomm_AddressDropdown/*`, `Secomm_VietNamAddress/*` | — | generic country-agnostic (DEC-019); reuse read-only |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Module bootstrap + schema** — risk: low — deps: none
   - `registration.php` + `module.xml` (sequence); `db_schema.xml` bảng `secomm_ghtk_address_map` (cột + `UNIQUE(country_id, region_id, ward_id)` + audit timestamps) + whitelist.
   - verify: `setup:upgrade` tạo bảng; unique key hiện; `bin/magento module:enable Secomm_Ghtk`.
2. **Persistence (model/resource/collection + repo)** — risk: low — deps: 1
   - `GhtkAddressMap` model + `ResourceModel` + collection; `findActive(country_id, region_id, ward_id)`.
   - verify: integration test insert/find/upsert theo unique key.
3. **WardIdBridge + BestEffortViVnResolver** — risk: medium — deps: 2
   - bridge: `resolveWardId(region_id, ward_name)` query `directory_region_city` (city_id by region_id + default_name; city table has region_id directly); log warning khi >1 match (non-deterministic). best-effort: province (`directory_country_region_name` vi_VN) / ward (`directory_region_city_name` vi_VN theo city_id) (reuse AddressDropdown resource, read-only).
   - verify: bridge deterministic cho (region_id, ward_name) unique; best-effort trả province+ward vi_VN; DB thiếu → empty (không throw).
4. **DestinationAddressResolver + cache** — risk: medium — deps: 3
   - `resolve(country_id, region_id, ward_id|null, ward_name|null)` → hit row | best-effort; cache theo canonical key; `is_active` gate; no-throw (log masked warning khi miss).
   - verify: hit/miss/legacy-bridge; cache hit + invalidate; miss không throw.
5. **Hardened CSV importer (replace-all)** — risk: high — deps: 2
   - `Importer`: parse (header canonical) → **full-file validate** (header, required fields, type, duplicate-in-file) → **transactional replace-all** (CSV = complete mapping state; stage new set → swap, hoặc delete-all + insert) → Summary `inserted/updated/removed/skipped/failed`; **admin confirm** trước replace + audit (before/after count + admin identity). UTF-8/BOM strip; MIME/extension/size check ở controller.
   - verify: seed hợp lệ → replace-all + summary đúng (incl. removed); bad-format → rollback toàn bộ, bảng nguyên; oversize/MIME sai → reject; re-upload CSV nhỏ hơn → rows dư bị removed.
6. **Admin UI (upload + sample + ACL + i18n)** — risk: medium — deps: 5
   - `system.xml` GHTK section upload field; controller `Upload` (ACL resource `Secomm_Ghtk::manage_map` + form key) + `SampleCsv` download; `acl.xml`; admin labels vi/en.
   - verify: admin upload (có ACL) → replace-all + summary; user không ACL → denied; sample CSV download đúng header.
7. **Cache invalidate hook** — risk: low — deps: 5,6
   - sau importer commit → clean cache tag `secomm_ghtk_address_map`.
   - verify: resolve cached → upload → resolve reflect data mới.
8. **Tests + QC + evidence** — risk: low — deps: 4,5,6,7
   - integration: schema/resolver/importer; QC matrix (mapping hit/miss/legacy, CSV seed/bad-format/duplicate/oversize, ACL, cache invalidate).
   - evidence → `.ai/runtime/evidence/FEAT-006/` (SL-008).

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Sửa nhầm generic AddressDropdown/VietNamAddress | high | reuse read-only; review diff; không modify generic tables |
| CSV corrupt bảng mapping | high | full-file validate trước commit + transactional (rollback) |
| Bridge non-deterministic (ward name trùng) | medium | key `(region_id, default_name)`; log warning; long-term path A |
| Fallback che gap → kỳ vọng "luôn work" | medium | log masked; QC test miss = best-effort (có thể no-rate ở SL-009) |
| DB migration lệch (db_schema vs whitelist) | medium | generate whitelist + `setup:upgrade` verify |

## 5. Test approach

- Unit/Integration: schema + unique key; `DestinationAddressResolver` (hit/miss/legacy-bridge/best-effort/no-throw); importer (seed/bad-format/duplicate/oversize/MIME/UTF-8-BOM/transactional rollback); cache invalidate.
- QC (L3): mapping hit→GHTK names; miss→best-effort vi_VN; CSV upload replace-all + bad-format rollback + re-upload-smaller removes dư; ACL gate; non-VN country ignored ở mapping scope.
- High-risk: address data read path (không mutate generic); admin upload (ACL + transactional).

## 6. Out of scope

- PickupAddressResolver + carrier `collectRates` + fee API + weight + rate composition (SL-009).
- Order sync / `pick_money` / COD (SL-010, PARKED).
- Path A (expose+persist `city_id` (ward_id) ở generic) — long-term follow-up.
- GraphQL/REST mapping surface; auto-sync mapping từ GHTK master.

## 7. Open questions / Escalation

- ~~Q1: CSV mode — upsert-only hay replace-all?~~ **RESOLVED 2026-07-30 (user): replace-all** — CSV = complete mapping state; transactional (stage+swap hoặc delete-all+insert); full-file validate + admin confirm + audit before/after.
- Q2: Cache backend (Magento framework cache đủ, hay Redis tag?) — Dev; Redis = prod infra (DEC-2 TBD).
- **Tier-2 escalation required** trước code (shipping data + admin upload).
