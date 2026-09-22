# Implementation Plan: TASK-MZ2TCB — Phase GHN-B dual-scheme master data + mapping

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-MZ2TCB (parent FEAT-FQWEQ3) |
| Mode | A (shipping + DB schema → Tier-2) |
| Specification | [specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md](../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md) — FULL, VALID (đặc biệt §3/§5/§6/§7/§8/§42) |
| Decision | [DEC-FEATFQWEQ3-001](../records/decisions/DEC-FEATFQWEQ3-001.md) (dual-scheme create → sync cả 2 scheme) · DEC-FEATYA2C0W-004 (mapping keyed scheme_code+unit_code) |
| API basis | **Verified developer.ghn.vn 2026-09-10**: new-model `GET /shiip/public-api/v3/master-data/province/all` (offset/limit≤200; 34 tỉnh) + `GET …/v3/master-data/ward/all-by-province-id` (province_id, offset, limit; data rỗng = province_id sai) — fields `_id/name/extension_names/type/parent_id/status` (1=active, 2=disabled, 10=deleted); `name` verbatim = `to_province_name`/`to_ward_name` với `is_new_to_address=true`. Legacy: `GET master-data/{province,district,ward}` — fields `ProvinceID/ProvinceName`, `DistrictID/DistrictName`, `WardCode/WardName` (verified từ docs + legacy `Console/Generate*Command.php`) |
| Contract basis | `VnAddressUnitProviderInterface` (canonical units mọi scheme) · `GhnApiClientInterface` (+bổ sung `get()` cho v3 GET endpoints) |
| Risk | High — Tier-2 DB schema + external sync + mapping data quality |

## Approach

Hai bảng Tier-2 theo plan FEAT đã duyệt: `secomm_ghn_address_unit` (UNIQUE scheme+provider_key —
tránh NULL-unique MySQL; parent self-FK RESTRICT; status ACTIVE/DISABLED soft) +
`secomm_ghn_address_mapping` (UNIQUE secomm_scheme_code+secomm_unit_code; FK unit CASCADE).
Sync = fetcher per scheme (v3 GET cho 2025, legacy master-data cho PRE_2025) → upsert theo
(scheme, provider_key), row biến mất → DISABLED (không DELETE). Mapping generator chỉ ghi row
APPROVED (exact name match trong cùng parent chain / curated alias CSV) vào bảng mapping —
UNMAPPED/AMBIGUOUS KHÔNG có row (bảng bridge chỉ chứa bridge thật); audit tính live bằng đối chiếu
canonical units ↔ mapping rows. Resolver fail-closed + cache type riêng.

## Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `etc/db_schema.xml` + `db_schema_whitelist.json` | new | 2 bảng (Tier-2) |
| `etc/cache.xml` | new | cache type `secomm_ghn_mapping` |
| `Model/Cache/MappingCache.php` | new | TagScope cache type (legacy GhnAddressMapper pattern) |
| `Model/Client/GhnEndpoints.php` | modify | sửa path số ít (verify docs) + v3 new-model paths |
| `Api/Client/GhnApiClientInterface.php` + `Model/Client/GhnApiClient.php` | modify | + `get()` (v3 GET endpoints) |
| `Model/Address/Sync/Pre2025MasterDataFetcher.php` | new | legacy p/d/w fetch + normalize |
| `Model/Address/Sync/Admin2025MasterDataFetcher.php` | new | v3 province/all + ward paging fetch + normalize |
| `Model/Address/Sync/MasterDataSynchronizer.php` | new | upsert + disable-missing + dry-run counts |
| `Model/ResourceModel/AddressUnit.php` | new | insertOnDuplicate + select/disable theo scheme (parameterized) |
| `Model/Address/Mapping/NameNormalizer.php` | new | NFC/trim/collapse/lower/punctuation |
| `Model/Address/Mapping/AliasRepository.php` | new | đọc `Files/ghn_mapping_aliases_{scheme}.csv` |
| `Model/Address/Mapping/MappingGenerator.php` | new | exact + curated → APPROVED rows (rebuild in TX) |
| `Model/Address/Mapping/GhnLocation.php` | new | VO: legacy triple + 2025 names |
| `Model/Address/Mapping/GhnMappingResolver.php` | new | (scheme, unit_code) → GhnLocation; fail-closed; cache APPROVED |
| `Model/Address/Mapping/MappingAuditor.php` | new | mapped/unmapped/ambiguous/invalid/disabled + coverage % |
| `Model/ResourceModel/AddressMapping.php` | new | upsert/delete-by-scheme/lookup (parameterized) |
| `Console/Command/SyncAddressCommand.php` | new | `secomm:ghn:address:sync --scheme --dry-run` |
| `Console/Command/AuditAddressCommand.php` | new | `secomm:ghn:address:audit --scheme --format` |
| `Files/ghn_mapping_aliases_*.csv` | new | curated alias seed (ban đầu rỗng có header) |
| `etc/di.xml` + `i18n/*` + README/CHANGELOG | modify | wiring + strings + docs |
| `Test/Unit/**` (7 files) | new | fetcher/synchronizer/normalizer/generator/alias/resolver/auditor |

## Steps

1. db_schema + whitelist + cache.xml — risk: **high (Tier-2 — TL review point)** — deps: none
   - verify: `php -l` + whitelist JSON match (schema diff tool khi DB up).
2. Client `get()` + endpoints path fix + tests — risk: medium — deps: none
   - verify: phpunit query-string/header assertions.
3. Fetchers + Synchronizer + AddressUnit resource — risk: medium — deps: 2
   - verify: phpunit fixture payload → normalized rows; idempotent upsert; disable-on-missing.
4. NameNormalizer + AliasRepository + MappingGenerator + AddressMapping resource — risk: high — deps: 3
   - verify: phpunit exact/curated/ambiguous/unmapped; rebuild trong TX; không auto-approve.
5. GhnMappingResolver + cache + Auditor — risk: medium — deps: 4
   - verify: phpunit triple walk (PRE_2025 ward), fail-closed, DISABLED miss, cache hit.
6. CLI + di + i18n + docs — risk: low — deps: 5
   - verify: `bin/magento` list thấy 2 command (cần DB up cho full bootstrap — nếu DB còn down,
     verify qua class-load + note evidence).

## Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| DB schema sai trên MySQL 8.4 | high | UNIQUE tránh NULL; FK RESTRICT/CASCADE tường minh; AC4 pre-check staging (memory TASK-ND6AZ2) |
| Mapping sai do name match | high | chỉ exact/curated APPROVED; ambiguity → PENDING (không row); audit coverage gate |
| Sync dập dữ liệu Approve thủ công | medium | manual/curated chỉ qua alias CSV versioned → rebuild an toàn; dry-run trước |
| Client `get()` làm hỏng `post()` | low | tests hiện hữu giữ nguyên + thêm test riêng |

## Test approach

- Unit: fetcher fixtures (payload thật theo docs), synchronizer upsert/disable, normalizer,
  generator (exact/curated/ambiguous/unmapped), resolver (hit/miss/DISABLED/triple), auditor counts.
- Integration khi DB up: `setup:upgrade` + sync sandbox 2 scheme + audit coverage (evidence).
- L3: không chạm checkout ở phase này (GHN-C).

## Out of scope

Rate/leadtime (GHN-C) · create/webhook (GHN-D/E) · admin mapping UI · legacy seed tự động ·
sửa ShippingCore/VietNamAddress · E-B v2/VietMap/NO_MATCH (ngoài FEAT — nhận export audit JSON).

## Open questions / Escalation

- Naming bảng: `secomm_ghn_address_unit`/`secomm_ghn_address_mapping` theo SPEC §5/§6 (spike R12
  hỏi `…_location` — SPEC mới supersede; chốt ở TL review schema).
- v3 paging `limit` cho `province/all`: docs cho phép omit = all; implement vẫn loop paging
  limit=200 để an toàn nếu GHN enforce cứng về sau.
