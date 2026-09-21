# Implementation Plan: TASK-TBM30R — Phase GHN-B2 address dataset lifecycle

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-TBM30R (parent FEAT-FQWEQ3) |
| Mode | A (shipping data lifecycle → Tier-2) |
| Specification | [specs/SPEC-TASK-TBM30R-secomm-ghn-address-dataset-lifecycle.md](../specs/SPEC-TASK-TBM30R-secomm-ghn-address-dataset-lifecycle.md) — FULL, VALID (chuẩn hóa từ TL/SA directive 2026-09-10) |
| Decision | [DEC-FEATFQWEQ3-002](../records/decisions/DEC-FEATFQWEQ3-002.md) (lifecycle — auto-mapping demoted) · DEC-FEATFQWEQ3-001 · DEC-FEATYA2C0W-004 |
| Reuses | GHN-B fetchers (verified contract), `AddressUnit::upsert/disableMissing/fetchByScheme`, `AddressMapping::upsert/findApproved/fetchByScheme`, `MappingMatcher/NameNormalizer/AliasRepository`, `MappingAuditor`, resolver (không đổi core) |
| Risk | High — activation path của production mapping; nhưng 0 schema change, 0 thay đổi runtime resolver |

## Approach

Dataset layer mới quanh nền GHN-B sẵn có: **Exporter** (fetchers → deterministic CSV + manifest) ·
**MasterDataImporter** (CSV → upsert/DISABLED — cùng semantic với API sync nhưng nguồn file) ·
**MappingImporter** (CSV APPROVED-only, validate fail-loud, UPSERT không truncate) ·
**MappingSuggester** thay `MappingGenerator` (matcher → workfile REVIEW_REQUIRED/AMBIGUOUS/UNRESOLVED,
không bao giờ APPROVE). Bundled `data/` ship header-only placeholders + manifest (dataset v0.0.0
"empty") — deterministic bootstrap ngay từ lần build đầu; dataset thật được tạo bằng
export → offline review → commit lại `data/` (quy trình §19 directive). Audit thêm
dangling/duplicate/production_ready. CLI 4 lệnh (`sync` giữ nguyên, `export`/`import`/`suggest` mới)
+ 1 field system.xml hiển thị installed dataset version.

## Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `data/master/GHN_ADMIN_2025.csv` + `data/master/GHN_ADMIN_PRE_2025.csv` | new | bundled master (placeholder header-only, refresh bằng export thật) |
| `data/mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv` + `VN_ADMIN_PRE_2025_TO_GHN_ADMIN_PRE_2025.csv` | new | bundled reviewed mapping (placeholder) |
| `data/manifest.json` | new | dataset_version/generated_at/counts/sha256 |
| `Model/Address/Dataset/Manifest.php` | new | read/validate/write manifest + checksum |
| `Model/Address/Dataset/DatasetPaths.php` | new | resolve module `data/` + canonical mapping file names |
| `Model/Address/Export/MasterDataExporter.php` | new | fetchers → deterministic CSV + manifest (AC-L1) |
| `Model/Address/Import/CsvReader.php` | new | UTF-8/BOM/header-strict fgetcsv dùng chung |
| `Model/Address/Import/MasterDataImporter.php` | new | master CSV → AddressUnit upsert + disableMissing |
| `Model/Address/Import/MappingImporter.php` | new | mapping CSV → APPROVED-only upsert, validate fail-loud |
| `Model/Address/Mapping/MappingSuggester.php` | new | matcher → workfile CSV (thay MappingGenerator) |
| `Model/Address/Mapping/MappingGenerator.php` | **delete** | auto-write APPROVED — classification C (DEC-002) |
| `Model/Address/Mapping/MappingAuditor.php` | modify | +dangling/duplicate/production_ready (+ VnAddressUnitProviderInterface) |
| `Model/ResourceModel/AddressUnit.php` | modify | +`fetchUnitByKey(scheme, providerKey)` cho importer validate |
| `Console/Command/ExportAddressCommand.php` / `ImportAddressCommand.php` / `SuggestMappingCommand.php` | new | CLI lifecycle |
| `Console/Command/AuditAddressCommand.php` | modify | in production_ready |
| `etc/di.xml` | modify | commands + services wiring (không preference mới) |
| `etc/adminhtml/system.xml` + `Model/Config/Frontend/DatasetInfo.php` | new | read-only installed-dataset info (§13 "see") |
| `i18n/*`, `README.md`, `CHANGELOG.md` | modify | strings + docs |
| `Test/Unit/**` | new/modify | Exporter/Importer×2/Suggester/Manifest/RoundTrip + Auditor mở rộng |

## Steps

1. Dataset layer: Manifest + DatasetPaths + CsvReader — risk: low
   - verify: phpunit manifest checksum/count; BOM/header strict.
2. MasterDataExporter — risk: medium — deps: 1
   - verify: phpunit deterministic bytes/order/verbatim/extension JSON/no entity_id + manifest.
3. MasterDataImporter + `fetchUnitByKey` — risk: medium — deps: 1
   - verify: phpunit parent resolution; DISABLED-not-delete; checksum mismatch fail; bootstrap từ placeholder data/ (0 rows, repeatable).
4. MappingImporter — risk: high (activation path) — deps: 1
   - verify: phpunit ma trận AC-L3/L4 (APPROVED-only, duplicate, unknown×2, conflict, dangling, malformed, cross-entity-id, non-APPROVED skip).
5. MappingSuggester (xóa MappingGenerator) — risk: medium — deps: 2
   - verify: phpunit workfile statuses; không tồn tại APPROVED trong output; grep: đường ghi APPROVED duy nhất = MappingImporter.
6. Audit mở rộng + resolver regressions — risk: low — deps: 4
   - verify: phpunit dangling/duplicate/production_ready; resolver tests GHN-B giữ nguyên green.
7. CLI + di + system.xml info + i18n + docs — risk: low — deps: 6
   - verify: `php -l` + class-load; commands đăng ký (khi DB up: bin/magento list).
8. Validators + full suite + evidence + pre-review → TL review (activation path + bundled-artifact policy).

## Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Xóa MappingGenerator phá gì | medium | không caller nào khác (di không register); tests mới bọc suggester |
| Import sai đè mapping reviewed | high | UPSERT-only file rows; không delete; sync tách bạch; tests AC-L5 |
| Placeholder data/ gây nhầm "đã có mapping" | medium | manifest version 0.0.0 + audit production_ready=false + README warning rõ |

## Test approach

- Unit: ma trận directive §16 (export determinism, stable schema, bootstrap, import stable identity,
  cross-entity-id, duplicate, unknown codes, non-APPROVED skip, DISABLED-not-delete, resolver
  import-only, extension_names, audit dangling/incomplete) + round-trip
  exporter→file→importer→resolver (mocked DB, CSV thật qua temp files).
- QC (AC-L12) khi MySQL up: setup:upgrade → bootstrap → audit → export sandbox 2 scheme →
  compare → import reviewed test mapping → audit → resolver trên DB thật.

## Out of scope

SPEC §13 (rate/ShippingCore/VietMap/checkout/orchestration/carrier khác/admin import UI/versioning platform).

## Open questions / Escalation

- Dataset version convention cho bundled artifacts (đề xuất: `YYYY.MM.DD` của lần review cuối,
  bump mỗi lần refresh `data/` sau offline review) — chốt ở TL review.
- Có cần gắn exit-code audit = non-zero khi production_ready=false? (đề xuất: giữ exit 0, chỉ in
  cờ — cron/tooling bọc ngoài quyết định).
