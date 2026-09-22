# Evidence — TASK-TBM30R (Phase GHN-B2 — address dataset lifecycle)

Date: 2026-09-10 · Implementer: Claude (AI) · Spec: SPEC-TASK-TBM30R · DEC-FEATFQWEQ3-002

## 1. Scoped unit tests

Command: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'`

```text
OK, but there were issues!
Tests: 121, Assertions: 345, PHPUnit Warnings: 1, PHPUnit Deprecations: 6.
```

- 0 failures, 0 errors. Warnings/Deprecations không thuộc module: warning = file
  `GhtkAddressAdapterTest` của stream song song TASK-7AJ3K8 (file tồn tại chưa có class);
  deprecations = mock-generator notices (non-blocking, như GHN-A/B).
- Tests mới (27): `ManifestTest` (5) · `MasterDataExporterTest` (3) · `MasterDataImporterTest` (4) ·
  `MappingImporterTest` (11) · `MappingSuggesterTest` (2) · `RoundTripTest` (1) ·
  `MappingAuditorTest` +2 (dangling/production_ready + constructor mở rộng).
- GHN-B tests GIỮ NGUYÊN behavior: fetchers ×2, `NameNormalizerTest`, `AliasRepositoryTest`,
  `MappingMatcherTest`, `GhnMappingResolverTest` — green. `MasterDataSynchronizerTest` cập nhật
  constructor (UnitPersister) + fetchKeys count 4 (knownKeys + persister) — behavior business
  không đổi (AC-L10 "preserve unless intentionally changed").

## 2. Ma trận coverage directive §16 → tests

| Yêu cầu | Test |
|---|---|
| Deterministic GHN export (byte-identical) | `MasterDataExporterTest::testExportIsDeterministicAndOrdered` |
| Stable CSV schema/order | cùng test (assert header + 3 dòng depth-ASC/provider_key) |
| Bootstrap bundled master data | `MasterDataImporterTest::testBundledPlaceholderDatasetBootstrapsDeterministically` (đọc `data/` THẬT của module, manifest v0.0.0-empty) |
| Bootstrap bundled mappings | qua `secomm:ghn:address:import` flow (placeholder 0 rows — deterministic; RoundTrip phủ mapping import) |
| Mapping import stable identities | `MappingImporterTest::testApprovedRowsActivatedAndNonApprovedSkipped` |
| Same file → different entity IDs | `testSameFileImportsAcrossDifferentEntityIdDatabases` (501 vs 99999, cùng identity) |
| Duplicate detection | `testDuplicateCanonicalRowFailsLoud` |
| Unknown Secomm unit_code | `testUnknownCanonicalUnitFailsLoud` (dangling) |
| Unknown GHN provider | `testUnknownGhnProviderFailsLoud` (dangling) |
| Non-APPROVED not activated | `testApprovedRowsActivatedAndNonApprovedSkipped` + `testDryRunWritesNothing` |
| Refresh DISABLED-not-delete | `UnitPersister` disableMissing (dùng chung sync) + `RoundTripTest` re-import idempotent; sync path unchanged |
| Resolver uses imported APPROVED only | `GhnMappingResolverTest` (GHN-B giữ) + `RoundTripTest` resolve qua imported mapping |
| extension_names never approves | `MappingSuggesterTest::testWorkfileNeverContainsApprovedStatus` (matcher chỉ suggest; assert file không chứa 'APPROVED') |
| Audit detects dangling/incomplete | `MappingAuditorTest::testFullAuditCountsAndCoverage` (dangling=1, production_ready=false) + `testFullyMappedDatasetIsProductionReady` |
| Round-trip | `RoundTripTest` (export → import → mapping import → resolver triple 201/1442/90733) |

## 3. Legacy matcher classification (directive §15)

| Thành phần | Phân loại | Xử lý |
|---|---|---|
| `MappingMatcher` + `NameNormalizer` + `AliasRepository` | **A — candidate-generation tooling** | Giữ nguyên (tests giữ nguyên); chỉ phục vụ `MappingSuggester`; không thể ghi DB |
| `MappingGenerator` (auto-write APPROVED) | **C — redundant** | ĐÃ XÓA an toàn (0 caller sau khi suggester thay); `AddressMapping::rebuildScheme` (delete-by-scheme — trái policy no-truncate) cũng đã XÓA |

Kiểm chứng "đường ghi APPROVED duy nhất": grep `AddressMapping` writes — chỉ
`MappingImporter::import()` gọi `upsert()` (UPSERT, không delete). Runtime resolver chỉ đọc
`findApproved`.

## 4. Full scoped suite (regression)

```text
Tests: 1181, Assertions: 3927, Errors: 44
```

- `Secomm\Tracking` 7 errors — baseline pre-existing (CURRENT_STATE).
- `Secomm\Ghtk` 37 errors + 1 warning — stream song song TASK-7AJ3K8 đang in-flight
  (GhtkAddressMapImport/*, PickupAddressResolver, OrderSubmit…): KHÔNG phải scope FEAT này,
  xuất hiện giữa 2 lần chạy full-suite của tôi (trước đó Ghtk = 3 lỗi).
- **`Secomm\Ghn`: 0 error / 0 failure.**

## 5. Class-load + validators

- Class-load toàn module: **50/50 OK** (38 GHN-A/B + 12 mới GHN-B2); `php -l` toàn bộ pass.
- Validators: 0 FAIL trỏ tới FEAT-FQWEQ3 / TASK-TBM30R / DEC-FEATFQWEQ3-002; check-records về
  baseline 2 FAIL pre-existing.

## 6. Bundled dataset (placeholder) — lưu ý quan trọng

`data/` hiện là **placeholder header-only** (manifest `0.0.0-empty`, `generated_at: null`): đủ cho
bootstrap deterministic + import no-op, CHƯA phải dataset reviewed thật. Dataset thật theo quy trình
§17/§19: khi có sandbox credentials + MySQL → export 2 scheme → offline AI/manual review (đối chiếu
canonical Secomm CSVs) → import staging audit đạt production_ready → commit `data/` mới + bump
`dataset_version`. Ghi trong README + pre-review warnings.

## 7. Pending QC (AC-L12 — khi MySQL up)

setup:upgrade → bootstrap import → audit → export sandbox 2 scheme (cần credentials) → compare
schema/counts → import reviewed test mapping → audit (production_ready) → resolver test trên DB
thật; `db:status` + whitelist vẫn khớp.
