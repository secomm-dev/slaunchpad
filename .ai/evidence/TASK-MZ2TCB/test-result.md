# Evidence — TASK-MZ2TCB (Phase GHN-B dual-scheme master data + mapping)

Date: 2026-09-10 · Implementer: Claude (AI) · Environment: WSL2, PHP 8.3.31, PHPUnit 10.5.64

## 1. Nguồn API đã verify (không invent)

| Endpoint | Method | Fields | Nguồn |
|---|---|---|---|
| `v3/master-data/province/all` | GET (offset/limit ≤200) | `_id, name, extension_names, type, parent_id, status` (1=active, 2=disabled, 10=deleted) | developer.ghn.vn "Get Province (New)" (fetch 2026-09-10) |
| `v3/master-data/ward/all-by-province-id` | GET (province_id + paging) | như trên; data rỗng = province_id sai; `name` verbatim = `to_ward_name` | developer.ghn.vn "Get Ward (New)" (fetch 2026-09-10) |
| `master-data/province` | GET (no params) | `ProvinceID, ProvinceName, CanUpdateCOD` | developer.ghn.vn "Get Province" + legacy `Console/GenerateProvinceCommand.php:67-68` |
| `master-data/district?province_id` | GET | `DistrictID, DistrictName` | legacy `Console/GenerateRegionCommand.php:90-104` |
| `master-data/ward?district_id` | GET | `WardCode, WardName` | legacy `Console/GenerateWardCommand.php:94-98` |

Docs note (new-model page): "The returned `name` is exactly what you pass as `to_province_name`/
`to_ward_name` … with `is_new_to_address: true`" — khớp DEC-FEATFQWEQ3-001.

## 2. Scoped unit tests

Command: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'`

```text
OK, but there were issues!
Tests: 94, Assertions: 260, PHPUnit Deprecations: 6.
```

- 0 failures, 0 errors (94 = 59 GHN-A + 35 GHN-B). Deprecations: mock-generator notices, non-blocking.
- Test mới (GHN-B): `Admin2025MasterDataFetcherTest` (3) · `Pre2025MasterDataFetcherTest` (3) ·
  `MasterDataSynchronizerTest` (4) · `NameNormalizerTest` (8) · `AliasRepositoryTest` (3) ·
  `MappingMatcherTest` (4) · `GhnMappingResolverTest` (6) · `MappingAuditorTest` (2) ·
  `GhnApiClientTest` +3 (get()).

## 3. Full scoped suite (regression)

```text
Tests: 1170, Assertions: 3980, Errors: 7
```

- 7 errors = toàn bộ `Secomm\Tracking\EventNormalizerTest` — **baseline pre-existing** đã ghi trong
  CURRENT_STATE ("7 errors full-suite = Secomm_Tracking pre-existing").
- (Lần chạy trước có 3 lỗi `Secomm_Ghtk\GhtkDestinationResolverTest` của stream song song
  TASK-7AJ3K8 — lần chạy này đã tự hết, bên đó đang active.)
- **Secomm_Ghn: 0 error / 0 failure.**

## 4. Schema/whitelist verification (thay thế `setup:upgrade` khi DB down)

- Script đối chiếu `db_schema.xml` ↔ `db_schema_whitelist.json`: 2 bảng, toàn bộ column/index/
  constraint đều có entry whitelist — OK.
- Class-load toàn module: **38/38 classes load OK** dưới unit bootstrap.
- `php -l`: toàn bộ file pass.
- **Pending khi DB up** (MySQL container đang down): `bin/magento setup:upgrade` (tạo 2 bảng thật),
  `bin/magento secomm:ghn:address:sync --scheme=... --dry-run` + chạy thật với sandbox token,
  `secomm:ghn:address:audit` → coverage report thật. Đây là QC checklist, không block code review.

## 5. Bug thật đã bắt được qua test (fix trong lần chạy này)

1. **`MappingMatcher::decide()` dùng array union `$base + [...]`** — key `'status'` tồn tại sẵn
   trong `$base` nên mọi nhánh quyết định đều trả 'unmapped' (union giữ phía trái). Sửa sang
   `array_merge`. Đây là bug chức năng nghiêm trọng mà test bắt được ngay lần chạy đầu.
2. **`MasterDataSynchronizer` dry-run report hardcode `disabled = 0`** — biến `$disabled` đã tính
   nhưng không truyền vào report. Sửa.
3. **`Admin2025MasterDataFetcher` output không parent-first** — ward được append ngay sau province
   cha (interleave), vi phạm contract depth-ASC của `MasterDataFetcher`; sửa thành 2 pass
   (provinces rồi wards).
4. Stale mapping rows (canonical code không còn trong matcher) trước đây không được kiểm tra
   invalid/disabled — đã thêm `inspectStoredRow` cho stale rows trong `MappingAuditor`.
5. PHPUnit double-stub (`method()` gọi 2 lần trên cùng method — stub đầu thắng) gây 5 test sai —
   chuyển sang property-based callback; không phải bug production.

## 6. Artifacts

- Code: `Model/Address/Sync/*` (3+interface), `Model/Address/Mapping/*` (7), `Model/ResourceModel/*` (2),
  `Model/Cache/MappingCache`, `Console/Command/*` (2), `etc/db_schema.xml` + whitelist + `etc/cache.xml`,
  `Files/ghn_mapping_aliases_*.csv` (2 seed), di.xml fetchers/commands wiring, i18n +22 chuỗi × 2 file.
- Plan: `.ai/plans/TASK-MZ2TCB-implementation-plan.md` (dòng `| Specification |` đầy đủ).
