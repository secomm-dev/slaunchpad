# Evidence — TASK-6TNKDH phase 2: authoritative dataset v1.0.0 bundled + validated (2026-09-11)

Dataset version **1.0.0** thay placeholder; validation end-to-end trên DB thật (`launchpad`,
mysql84:3307) **không cần GHN API**. Toàn bộ số liệu dưới đây là output lệnh thật, lưu kèm trong
thư mục này.

## 1. Files bundled (authoritative, untracked — chờ commit theo review flow)

| File | record_count | sha256 |
|---|---|---|
| `data/master/GHN_ADMIN_2025.csv` | 3,355 | `f31c1ed4822dafc2027429a8d59a26c97731ff43dd56b661b94b630e6015b05f` |
| `data/master/GHN_ADMIN_PRE_2025.csv` | 12,772 | `0712b1f7815211fab5a9044bce8efb6eae3d78e95d0a451f70e7535c76bc46e4` |
| `data/mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv` | 3,355 | `acc219a3cc5e664943d71fc5078976a0eebba2ae890fc58f581e9f446f6ee8ba` |
| `data/mapping/VN_ADMIN_PRE_2025_TO_GHN_ADMIN_PRE_2025.csv` | 10,794 | `fc2b74be354f497993f04f5d669121604e67b8aef713bb4e1032f2d440eb4cc0` |

Manifest: `dataset_version = 1.0.0`, `generated_at = 2026-09-11T00:00:00+00:00`,
`source_environment = sandbox` — 4/4 checksum khớp bytes thực tế.

Review artifacts (KHÔNG là bootstrap dependency, không nằm trong runtime import):
`data/review/GHN_ADDRESS_MAPPING_REVIEW_2025_RESOLVED.csv` + `_PRE_2025_RESOLVED.csv`.

## 2. Content integrity (file-level, trước khi đụng DB)

- 2025 mapping: 3,355 rows — APPROVED 3,355 · duplicate `secomm_unit_code` = 0 · duplicate
  `ghn_provider_key` = 0 · null identity = 0. Methods: NORMALIZED_EXACT 3,225 ·
  NORMALIZED_TONE_PLACEMENT 112 · NORMALIZED_SPACING_DASH 9 · PARENT_SCOPED_REVIEWED 7 ·
  PARENT_SCOPED_RESIDUAL_1TO1 1 · NORMALIZED_ORTHOGRAPHY 1.
- PRE_2025 mapping: 10,794 rows — APPROVED 10,794 · dup/null = 0. Methods đủ 10 loại khai báo
  (NORMALIZED_EXACT 10,615 · NORMALIZED_POLICY 98 · HIERARCHY_REVIEWED 25 ·
  OFFICIAL_SOURCE_REVIEWED 16 · NORMALIZED_SEMANTIC_NOTATION 15 · PARENT_SCOPED_RESIDUAL_1TO1 12 ·
  PARENT_SCOPED_REVIEWED 9 · PROVIDER_DUPLICATE_CURATED 2 · AI_REVIEWED_ALIAS 1 ·
  HISTORICAL_MERGE_PRIMARY 1).
- Coverage vs canonical `Secomm_VietNamAddress` (file + region tổng hợp): 2025 = 3,355/3,355
  (0 unmapped canonical); legacy vs `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv` = 10,794/10,794.
  Lưu ý: mapping legacy keyed SNAPSHOT_2024 — file legacy cũ sai lệch 232 codes (không dùng).
- Master legacy trung thực API: 12,772 = 65 + 726 + 11,981, 100% ACTIVE, **giữ nguyên provider
  duplicate thật của GHN** (việc curated thuộc mapping layer).

## 3. Integrity findings xử lý trong phase này

1. **Bundled master legacy bị chỉnh tay sau export** — 2 provider-duplicate row
   (`w:1745:90815` Thị Trấn Yên Sơn, `w:1953:91352` Xã Hòa Bình) bị flip ACTIVE→DISABLED để "giấu"
   duplicate ⇒ checksum lệch manifest (`e3cd4ce6…` ≠ `0712b1f7…`). Vi phạm §4 raw-master fidelity
   (raw snapshot phải trung thực API; duplicate được curated ở mapping layer). **Fix**: khôi phục
   byte-identical với export authoring (checksum khớp manifest); curated mappings giữ target
   higher-key `w:1745:910044` / `w:1953:910116` (PROVIDER_DUPLICATE_CURATED), lower key không bao
   giờ được map.
2. **`MappingCsv::METHODS` chỉ có 3 giá trị placeholder** (`EXACT_NAME/CURATED_ALIAS/MANUAL`) ⇒
   importer sẽ fail-loud trên toàn bộ APPROVED rows của dataset thật. **Fix**: mở rộng METHODS với
   13 method authoring (additive — 3 giá trị cũ giữ lại cho suggester workfile sau review).
3. **Audit enumerate canonical = 0 (first real run)** — DB provider
   `VnAddressUnitProvider::getChildren` query `parent_code = ''` nhưng seeded
   `secomm_vietnam_address_unit` có `parent_code = NULL` trên mọi child (2025: 3,355/3,355 NULL;
   legacy: 759 root NULL) — defect VietNamAddress-side đã ghi nhận từ trước, lần đầu lộ khi audit
   chạy với data thật ⇒ 14,149 stored mapping bị false-STALE dù `production_ready=YES` (stale
   không thuộc gate). **Fix (GHN-scoped, không sửa VietNamAddress)**: wire
   `CanonicalCsvProvider` (đã có cho suggester) vào `MappingMatcher` + `MappingAuditor`, và cho
   nó snapshot-awareness (PRE_2025 = file `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv`, khớp key
   của dataset v1.0.0 + reference DB sau patch GS78X2); virtual `SuggesterMatcher` redundant đã
   gộp. Defect `parent_code=NULL` vẫn thuộc owning stream VietNamAddress.

## 4. Bootstrap + import trên DB thật

- `bin/magento setup:upgrade` — **SUCCESS** (tables + patches; patch
  `RefreshVnAdminPre2025Snapshot2024` đã apply: reference units VN_ADMIN_2025 = 3,355,
  VN_ADMIN_PRE_2025 = 10,794).
- **Không có credentials**: 0 rows `core_config_data` LIKE `secomm_ghn/%` trong DB ⇒ bootstrap
  chứng minh không cần Token/ShopId/GHN API (AC-D5).
- `secomm:ghn:address:import --dry-run` → master 3,355 + 12,772 · mapping APPROVED sẽ activate
  3,355 + 10,794 · skipped 0/0/0.
- `secomm:ghn:address:import` (thật, bundled dir) → unit 16,127; mapping 14,149. UPSERT —
  re-run idempotent, không truncate.

## 5. DB counts sau import (query thật — `db-counts.txt`)

- `secomm_ghn_address_unit` = **16,127**: GHN_ADMIN_2025 depth1=34, depth2=3,321 (toàn ACTIVE);
  GHN_ADMIN_PRE_2025 depth1=65, depth2=726, depth3=11,981 (toàn ACTIVE).
- `secomm_ghn_address_mapping` = **14,149**: VN_ADMIN_2025 APPROVED 3,355; VN_ADMIN_PRE_2025
  APPROVED 10,794. Method distribution DB == file (aggregate khớp từng method).
- DB integrity: duplicate ghn target = 0 · duplicate canonical source = 0 · dangling ghn unit = 0 ·
  dangling canonical = 0 · null identity = 0.

## 6. Audit gate (`audit_vn_admin_2025.json`, `audit_vn_admin_pre_2025.json`)

| Scheme | total_canonical | mapped | unmapped | ambiguous | invalid | stale | duplicate | dangling | disabled | production_ready |
|---|---|---|---|---|---|---|---|---|---|---|
| VN_ADMIN_2025 | 3,355 | 3,355 (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | YES |
| VN_ADMIN_PRE_2025 | 10,794 | 10,794 (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | YES |

Provider-only rows trong GHN raw master không làm fail coverage — coverage đo từ canonical Secomm
units → GHN provider identity (auditor enumerate canonical, matching GHN side từ DB).

## 7. Resolver smoke (`resolver-smoke.txt` — 21/21 PASS)

Current mode (2025, `is_new_to_address=true`):
- Normal exact `VNA25-0003B2FA66` → ward `Xã Cảm Nhân`, province `Lào Cai` (verbatim, không legacy id).
- Reviewed `VNA25-4EA3ADA7E1` **Kỳ Lừa → Tân Thanh** (`Xã Tân Thanh`, Lạng Sơn —
  PARENT_SCOPED_RESIDUAL_1TO1 sau sibling reconciliation 65/65/64+1).

Legacy mode (PRE_2025, full triple):
- Normal exact `VNAP25-000046EF49` → `Xã Vĩnh Thái`, district_id 1861 (Huyện Vĩnh Linh), ward_code
  320318, province Quảng Trị (province_id 238).
- Reviewed `VNAP25-6A84B015D0` **Đông Thành → Nhân Thành** (`Xã Nhân Thành`, ward_code 291124,
  district_id 1846 Huyện Yên Thành, province_id 235 Nghệ An; HISTORICAL_MERGE_PRIMARY —
  lossy legacy mapping có chủ đích: Hợp Thành merged → Nhân Thành, new unit Đông Thành).
- Curated duplicate `VNAP25-32D9CD7A44` Hòa Bình (Kim Thành, Hải Dương) → ward_code **910116**
  (KHÔNG 91352); curated `VNAP25-B5E3BB1785` Yên Sơn (Yên Sơn, Tuyên Quang) → ward_code **910044**
  (KHÔNG 90815).

Lưu ý contractual: `GhnLocation` district chỉ mang **ID** (không district name) — key format
`w:<DistrictID>:<WardCode>` / `d:<ProvinceID>:<DistrictID>`; các expectation smoke đã đối chiếu
theo contract fetcher (verified developer.ghn.vn).

## 8. Tests (`integrity-test.txt`, suite summary)

- `BundledDatasetIntegrityTest`: **4/4 PASS, 0 skip** — placeholder-gate skip đã gỡ, gate active:
  manifest checksum **+ record_count** (mới), version cấm `0.0.0-empty` + `1.0.0-master-only`,
  portable identity, hierarchy parent-complete, APPROVED resolvable 2 phía (PRE_2025 canonical =
  SNAPSHOT_2024 file), 1:1 targets, full coverage + pins 3,355/10,794.
- Suite `Secomm_Ghn` (phpunit-secomm): **129 tests / 210,339 assertions / 0 failures / 0 errors /
  0 skips** (6 PHPUnit deprecations = baseline PHP 8.3 pre-existing).
- Full-suite baseline (parallel streams) xem `full-suite.txt` — tách bạch với Secomm_Ghn.

## 9. Refresh lifecycle + no-auto-mapping (code-path audit, `lifecycle-audit.txt`)

- Writer DUY NHẤT của `secomm_ghn_address_mapping` = `MappingImporter::upsert` (APPROVED-only,
  UPSERT không truncate, cache tag clean). Auditor/resolver/sync = read-only.
- Sync (`MasterDataSynchronizer` → `UnitPersister`) chỉ upsert units — không đụng mapping
  (DEC-FEATFQWEQ3-002 §5); import lại master không đổi mapping.
- MappingMatcher/MappingSuggester/AliasRepository/NameNormalizer: 0 persist call — suggester chỉ
  ghi review workfile CSV (REVIEW_REQUIRED, importer skip). Không tồn tại production path tự tạo
  APPROVED.
- Refresh e2e với API thật (export mới từ sandbox) chờ credentials owner cấu hình — như QC AC-L12
  đã ghi nhận từ GHN-B2; lifecycle offline đã phủ bởi `RoundTripTest` (suite PASS).

## 10. File changes (phase 2)

- `app/code/Secomm/Ghn/data/**` — dataset v1.0.0 (4 file runtime + 2 review + manifest).
- `Model/Address/Dataset/MappingCsv.php` — METHODS taxonomy authoring.
- `Model/Address/Mapping/CanonicalCsvProvider.php` — snapshot-aware canonical source + docblock.
- `etc/di.xml` — CanonicalCsvProvider vào MappingMatcher/MappingAuditor; gộp SuggesterMatcher.
- `Test/Unit/Model/Address/Dataset/BundledDatasetIntegrityTest.php` — gate active + count/coverage.
- Records: TASK-6TNKDH (Progress Log), SPEC-TASK-6TNKDH (§10 addendum), CURRENT_STATE/NEXT_TASK,
  memory.
