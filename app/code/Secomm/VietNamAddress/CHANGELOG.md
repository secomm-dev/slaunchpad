# Secomm_VietNamAddress — Changelog

## [Unreleased] — BUG-ZTGGYZ: canonical hierarchy — province→L2 edge (2026-09-14)
- **Fixed** hierarchy contract: `parent_code` là canonical portable parent relation nhưng unit
  snapshot trước giờ MẤT province→L2 edge — seed CSV để trống parent cho direct-province children
  (2025 wards, PRE_2025 districts) và `UnitSnapshotWriter` import verbatim (level derive
  `parent ? 3 : 2` đúng nhưng parent NULL) → `getChildren(province)` trả rỗng → name-bridge
  (`VnOperationalNameResolver`) UNMAPPED với mọi ward → chặn GHN-C/GHTK real checkout rate.
- **`UnitSnapshotWriter`**: synthesize `parent_code = region_code` cho unit có seed parent trống
  (region unit code == region_code); level vẫn theo RAW seed parent (empty→2, set→3); guard
  fail-loud khi region không tồn tại trong dataset batch. Áp cho MỌI import path (scheme importer,
  reference/snapshot importer, data patches — cùng writer).
- **Added** `Model\Import\HierarchyParentBackfill` + CLI `secomm:vietnam-address:hierarchy:repair`
  — repair existing DB: backfill `parent_code = region_code` cho `level=2 AND parent_code IS NULL`
  (join validate region L1 cùng scheme; idempotent; identity-preserving; orphan được report, không
  đoán). Current DB: 2025 backfilled 3,321; PRE_2025 backfilled 696; re-run = no-op; counts dataset
  không đổi (34+3,321 / 63+696+10,035); non-root parent NULL = 0.
- Tests: writer synthesis matrix (real rows Long Vĩnh 2025/PRE, district An Phú), backfill contract,
  `getChildren` parent_code-column lock. Cross-carrier: GHN-C QC CASE 1 real Quote path PASS
  (214,500 VND sandbox); GHTK name path hưởng lợi cùng bridge (không sửa code GHTK).
- Follow-up riêng (không thuộc BUG này): `VnSchemes::unitFile(PRE_2025)` trỏ
  `VN_ADMIN_PRE_2025_import.csv` (reference cũ) trong khi dataset hiện hành là
  `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv` — re-import PRE_2025 qua CLI sẽ đọc stale file.

## [Unreleased] — TASK-7AJ3K8: name-based operational↔canonical bridge (D5 name-entry) (2026-09-10)
- **Added** `Api\VnOperationalNameResolverInterface` + `Api\Data\VnOperationalNameResolutionInterface`
  + `Model\VnOperationalNameResolver` — sibling contract của bridge id-based (TASK-Q4B98P, KHÔNG
  sửa interface đã freeze): region-scoped ward name → canonical identity, match `name_vi` OR
  `name_en` (exact sau trim) trên reference layer `secomm_vietnam_address_unit` (sửa lỗi latent:
  match runtime `default_name` = name_en trong khi storefront submit tên vi); 0 match → UNMAPPED,
  >1 → AMBIGUOUS (candidates sort deterministically, KHÔNG auto-pick — D9), 1 → EXACT + compose
  runtime identity qua `VnOperationalAddressResolverInterface::resolveFromCanonical` (1 lookup
  path, không duplicate SQL). Active scheme theo cùng rule config+registry. Statuses REUSE
  `VnAddressResolutionInterface::STATUS_*` (alias constants, không parallel set).
- **Added** DI preference `VnOperationalNameResolverInterface`. Consumer đầu tiên:
  `Secomm_ShippingCore\RuntimeAddressContextBuilder` (Phase E-C1) → `Secomm_Ghtk`.
- **Tests** +11 (`VnOperationalNameResolverTest`): vi/en/trim match, ambiguous sorted candidates +
  never-pick, unmapped, non-VN, scheme drift (fresh-registry mock), region-level skip, runtime-row
  missing compose, invalid input no-query.

## [Unreleased] — TASK-NDSZ7V: canonical mapping seed PRE_2025→2025 + auto-import patch (2026-09-04)
- **Added** `Setup/Patch/Data/ImportVnAdminPre2025To2025MappingPatch` — seed canonical mapping baseline `VN_ADMIN_PRE_2025_TO_2025_mapping.csv` (10.064 edges: 63 region + 10.001 ward; MERGED_INTO 9.250 / SPLIT_INTO 627 / SAME_AS 146 / RENAMED_TO 41) qua `setup:upgrade`, tái dùng `VnMappingImporter` (validate ALL → upsert UNIQUE edge, idempotent, fail-loud). Deps `[ImportVnAdminPre2025ReferencePatch]` (orphan validation cần cả 2 unit datasets). Runtime/registry KHÔNG đổi (2025=CURRENT, PRE=HISTORICAL).
- **Data** 924/10.595 PRE wards không có edge (nguồn evidence không cover) — giữ là `UNMAPPED` hợp lệ, KHÔNG đoán/synthetic; 3.120 target có >1 legacy candidates — reverse resolution `AMBIGUOUS` (graph cardinality là nguồn sự thật, `relation_type` chỉ metadata).
- **Tests** +5 (scoped suite 214/589 xanh cho 2 address modules): resolver `1:N reverse → MAPPED` + `N:N topology → AMBIGUOUS/MAPPED theo cardinality (edge SPLIT_INTO vẫn thế)`; patch tests delegate/deps/fail-loud. Resolver + finder + validator KHÔNG đổi code. **Dev tooling**: thêm `dev/tests/unit/phpunit-secomm.xml` — config scoped (framework bootstrap + chỉ suite `app/code/Secomm/*`): chạy đúng generated-Factory mocks và tránh fatal PayPal helper double-declare của `phpunit.xml.dist` gốc khi chạy cả Secomm suite.
- **Verified trên dev** (2026-09-04): setup:upgrade exit 0; SQL counts khớp 100%; re-import CLI không duplicate; resolver live 5 cases (1:1/N:1/1:N/N:N/UNMAPPED) đúng semantic. Evidence `.ai/runtime/evidence/TASK-NDSZ7V/`.

## [Unreleased] — TASK-F9XJ5G: historical reference-only import (VN_ADMIN_PRE_2025 without runtime swap) (2026-09-04)
- **Added** `Model/Import/VnReferenceSchemeImporter` — orchestrator nhỏ cho reference-only import: read dataset (`VnDatasetReader`) → validate (`VnDatasetValidator`, STOP_ON_ERROR) → trong MỘT transaction: `UnitSnapshotWriter::write` + `SchemeRegistryUpdater::applyReference`. Không có dependency vào hierarchy import/config writer/membership/guards/caches → cấu trúc không thể đụng runtime (`directory_*`, `secomm_address_profile_location`, `active_scheme`, `address/profiles/mapping`).
- **Added** `SchemeRegistryUpdater::applyReference()` — registry upsert KHÔNG promote: status `HISTORICAL` cho scheme không đang CURRENT; scheme đang CURRENT giữ CURRENT (chỉ refresh metadata); không bao giờ đụng status của scheme khác. Idempotent qua PK `scheme_code`.
- **Changed** CLI `secomm:vietnam-address:import` — option `--reference-only` mới (mutually exclusive với `--swap`/`--rebuild`, kết hợp được `--dry-run`); help text + description ưu tiên canonical codes `VN_ADMIN_2025 | VN_ADMIN_PRE_2025` (legacy `vn_current|vn_legacy` vẫn bị reject kèm hint). `VnImportReport` thêm `referenceOnly` + `registryStatus` (CLI + `toArray()`).
- **Tests** 18 mới: `VnReferenceSchemeImporterTest` (7 — Case 1/2/3/5, rollback, dry-run), `SchemeRegistryUpdaterTest` +4 (missing→HISTORICAL, CURRENT keep, HISTORICAL keep, rollback), `ImportVnAddressSchemeCommandTest` mới (8 — Case 4 mutual exclusion, routing, legacy hint, validation failure, runtime-path regression). Suite module: 139 tests / 421 assertions xanh.
- **Added** `Setup/Patch/Data/ImportVnAdminPre2025ReferencePatch` — reference-only import tự chạy qua `setup:upgrade` (deps `[ImportVnAdmin2025SchemePatch]`, idempotent upsert, validation fail → setup fail loudly — cùng contract với bootstrap patch); deploy không cần chạy `--reference-only` bằng tay. +4 tests (`ImportVnAdminPre2025ReferencePatchTest`: delegate đúng scheme/CURRENT-importer structural guard/deps/fail-loudly) — suite 143 tests / 426 assertions xanh.
- **Verified trên dev DB** (2026-09-04): registry `VN_ADMIN_2025=CURRENT / VN_ADMIN_PRE_2025=HISTORICAL`; unit table 2025 = 34+3.321 (level 1+2), PRE_2025 = 63+699+10.595 (level 1+2+3); `active_scheme` vẫn `VN_ADMIN_2025`; runtime 34 regions / max_region_id 1224 không đổi qua 3 lần chạy CLI (PRE_2025 ×2 + CURRENT ×1) và qua `setup:upgrade` (patch applied, DB state identical); `--reference-only --swap|--rebuild` → exit 1 + reject message. Evidence `.ai/runtime/evidence/TASK-F9XJ5G/`.

## [Unreleased] — TASK-9EX975 Slice A: admin surfaces schema-driven + validation coverage (2026-09-03)
- **`view/adminhtml/web/js/admin/order/address-cascade.js` (order create/edit)** — rewrite schema-driven qua shared factory `Secomm_AddressDropdown/js/form/schema-cascade`: level count/labels/placeholders từ Address Profile (`addressSchema`), options từ `addressLocations`, stop-at-leaf; bỏ gate `country == 'VN'` (unmapped country → native city input). Giữ nguyên mọi order-form quirk: RegionUpdater `defaultValue` clearing (capture-phase), same-as-billing shipping lock + billing-city copy, hydration watcher ~30s (country|region key), `order.fillAddressFields` hook, MutationObserver scan, new-order fallback binder. Class `secomm-vn-ward-*` giữ cho layout compat — giờ host các generic cascade levels.
- **`view/adminhtml/web/js/form/element/source-city.js` + template `source-city.html` (MSI source)** — rewrite schema-driven bằng exports `loadCityLevels`/`loadLevelOptions` của factory; KO view-model `cityLevels` (options/selected/loading per level), level 0 luôn hiện (placeholder disabled khi chưa chọn province), deeper levels hiện khi có options (stop-at-leaf). Giữ behaviour cũ: stale stored name bị drop, không clear value khi hydration lần đầu (undefined → value) để Edit pre-fill sống sót qua KO imports.
- **Validation coverage (AC-003)** — 3 plugin mới mirror pattern `Plugin\Customer\Address\ValidateVietNamWard` (gate VN, generic collection, neutralise + warning log, non-fatal catch — `etc/adminhtml/di.xml`, storefront không đụng): `Plugin\Quote\ValidateVietNamWard` (`CartRepositoryInterface::beforeSave` — admin order create đi qua `quoteRepository->save`, validate cả billing + shipping), `Plugin\Sales\ValidateVietNamWard` (`OrderAddressRepositoryInterface::beforeSave` — order address edit), `Plugin\Inventory\ValidateVietNamWard` (`SourceRepositoryInterface::beforeSave` — MSI source; `getRegionId()` int). Unit tests 17 mới (suite 120/332 xanh).
- **Out-of-scope emergency fix (report)**: `Api/VnOperationalAddressResolverInterface.php` docblock chứa `"VNA25-*/VNAP25-*"` — chuỗi `*/` đóng docblock sớm → syntax error PHP, break toàn bộ `setup:di:compile`. Fix comment-only (thêm space) do chặn build chung; file thuộc TASK-Q4B98P (session song song) — đã flag cho TL.

## 1.6.0 (2026-09-03) — TASK-Q4B98P / DEC-FEATYA2C0W-004: operational ↔ canonical bridge + DI reference guards

- **Added** `Api/VnOperationalAddressResolverInterface` + `Model/VnOperationalAddressResolver` — bridge `region_id/city_id ↔ scheme_code/unit_code` thuần code-based (runtime rows đã mang dataset codes; không name join, không cache). Active scheme chỉ được tin khi registry `CURRENT` khớp config — drift → `scheme_not_active` (AC-9); `code` NULL → `runtime_code_missing`, không name-fallback (AC-10); scheme non-active → reverse KHÔNG fabricate runtime id (AC-4). Metadata DTO hydrate qua `VnAddressUnitProviderInterface` (reuse).
- **Added** `Api/Data/VnOperationalIdentityInterface` + `VnOperationalResolutionInterface` (+ Data classes) — immutable identity + outcome có machine-readable reason, không exception cho business miss.
- **Changed** `VnAddressSchemeImporter` — 2 const bảng carrier hardcode (`TABLE_GHN_MAPPING`/`TABLE_GHTK_MAPPING`) + `assertNoCarrierReferences()` bị thay bằng **DI extension point** `Api/DirectoryReferenceGuardInterface` (argument `directoryReferenceGuards`): mọi guard đăng ký từ module sở hữu bảng đều chạy, vi phạm aggregate thành MỘT exception ("Cannot swap: [Guard] …"), invalid guard entry bị skip + warning log. `dryRun()` bổ sung `guardViolations` vào report. **Import CLI behavior/output giữ nguyên** (parity check: cùng điều kiện chặn, cùng ngữ nghĩa message).
- **Dependency note**: module.xml/composer của module này KHÔNG đổi; `Secomm_GhnAddressMapper` + `Secomm_Ghtk` thêm `Secomm_VietNamAddress` vào sequence (đúng chiều `ShippingCore`-chain DEC-004 D1).

## 1.5.0 (2026-09-03) — TASK-6MKF0V / DEC-TASK6MKF0V-001: sub_city retirement (module slice)

- **Removed `Files/VN_Address.csv`** — legacy 3-level source dataset (cột `sub_city_*` filled), không còn code nào tham chiếu kể từ khi scheme importer tiếp quản (`VN_ADMIN_2025_import.csv` / `VN_ADMIN_PRE_2025_import.csv` là nguồn canonical; cấu trúc 3 cấp PRE_2025 render qua city depth-2 của profile engine, không còn sub_city).
- **Removed `Files/VN_Address_2Level.csv` + `Setup\Patch\Data\InstallVietNamAddressPatch` (TL decision, 2026-09-03)** — fresh installs không còn bootstrap qua legacy importer (`Secomm\AddressDropdown\Model\Import\AddressDropdown`) nữa. Lý do kỹ thuật: importer đó chạy `needColumnCheck = true` — sau khi slice 2 bóc sub_city khỏi `validColumnNames`, CSV còn thừa 2 cột header chết làm `validateSource` fail, và patch nuốt exception (`logger.error`) nên `setup:upgrade` vẫn exit success nhưng DB trống region/city trên fresh install (silent break của chuỗi bootstrap đã verify ở 1.4.1). Chuỗi fresh-DB mới: `ImportVnAdmin2025SchemePatch` (deps `[]`, tự bootstrap toàn bộ: regions + units + membership + `address/profiles/mapping` + `active_scheme`, một transaction) → `SeedVnProfileMembership` (deps `[ImportVnAdmin2025SchemePatch]`, idempotent belt-and-braces). DB cũ vẫn upgrade in place qua re-key bridges (`CurrentDatasetRekeyMatcher`) — matcher match DB rows, không đọc file đã xoá.
- **i18n**: bỏ toàn bộ chuỗi SubCity/sub-city khỏi `en_US.csv` / `vi_VN.csv` / `en_VN.csv`. Các chuỗi này chỉ tồn tại để dịch legacy SubCity admin CRUD của `Secomm_AddressDropdown` (được xoá ở phase kế tiếp của TASK-6MKF0V); đến lúc đó các trang admin legacy đó fallback English source — dead surface, 0 data.

## 1.4.1 (2026-08-28) — TASK-ADT94K: bootstrap từ empty-DB + region re-key bridge + all-or-nothing import

**Fix chính**: validator reject 34/34 region rows vì pattern cũ `/^\d{2}$/` (2-digit trần) trong khi CSV canonical dùng `VN-XX` → patch fail `"failed validation with 34 error(s)"` ngay từ bước validate (không đụng DB). Dataset giờ là seed source tự chứa thực thụ.

- **Region code pattern canonical**: `VnSchemes::REGION_CODE_PATTERN = '/^VN-\d{2}$/'` — validator check theo pattern này; regression test đọc 2 CSV shipped thật qua reader+validator (34/3.321 + 63/11.294 rows pass).
- **Region re-key bridge (mới)**: `rekeyExistingRegionRows()` + `CurrentDatasetRekeyMatcher::buildRegionIndex()/matchRegion()` — region legacy (code 2-digit trần theo đánh số chính phủ, vd `01`=Hà Nội, từ `InstallVietNamAddressPatch`/`VN_Address_2Level.csv`) được gán code dataset `VN-XX` (thứ tự alphabet, VN-01=An Giang — KHÔNG thể map bằng phép cộng tiền tố) bằng name matching (enKey primary / viKey fallback, ambiguity → skip + warning). Chạy TRƯỚC city bridge để city join thấy dataset codes → bảo toàn region_id + city_id; fresh-install (Install→Seed→Import) không còn tạo 34 region trùng.
- **All-or-nothing import**: toàn bộ write phase (purge/re-key/hierarchy/stale cleanup/snapshot/registry/membership/config) chạy trong MỘT transaction — nested chunk commit của `HierarchyImportService` flatten onto nó; fail giữa chừng = rollback toàn bộ, không còn hierarchy nửa vời, active_scheme không bị move (Magento `Pdo\Mysql` nested-transaction semantics đã verify).
- **Reconciliation (Case 3)**: stray-region cleanup (VN regions code không thuộc dataset — guard carrier FK như swap purge, cities cascade); orphan membership sweep (claims trỏ region/city id đã chết — scope VN profile codes + aliases; trước đây tích lũy vĩnh viễn vì reseed chỉ đụng id còn sống).
- **Error reporting**: `VnImportValidationException` nhúng 10 lỗi đầu + "(+N more)" vào message — `setup:upgrade` giờ thấy lý do thật thay vì chỉ count; CLI in counters mới (region re-key, stray regions, orphan claims).
- Report counters mới: `rekeyRegionMatched/Missed`, `staleRegionsRemoved`, `orphanMembershipRemoved` (+ toArray + CLI).
- Tests: 84 unit (was 74) — validator region pattern + shipped-CSV regression, region bridge ordering (region update trước city update), empty-DB bootstrap (regions trong import batch), hierarchy-failure rollback (`begin`→`rollback`, không commit), dry-run region simulation.
- Bootstrap từ empty DB đã verify end-to-end trên dev DB: `setup:upgrade` tạo đúng 34 regions + 3.321 VN_ADMIN_2025 units; rerun = 0 inserts; 68 orphan membership rows bị dọn.

## 1.4.0 (2026-08-27) — FEAT-YA2C0W / DEC-FEATYA2C0W-003: versioned VN_ADMIN_* schemes + historical + mapping layers

**BREAKING (pre-release CLI values)**: `--scheme` đổi sang canonical codes `VN_ADMIN_2025` / `VN_ADMIN_PRE_2025` (giá trị cũ `vn_current`/`vn_legacy` bị reject kèm gợi ý — CURRENT/LEGACY giờ chỉ là status label trong registry, không phải identity).

- **Versioned scheme model (DEC-003)**: catalog `Model/Scheme/VnSchemes` (identity bất biến + profile_code + level_count + files + counts + collision + legacy aliases); profiles rename `vn_admin_2025` / `vn_admin_pre_2025` (`address_profiles.xml`); alias map giúp DB đã seed `vn_current` upgrade thành same-scheme refresh (không purge, city_id bảo toàn).
- **Datasets canonical 7 cột** `region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en` — tự chứa tên region (reader tách region rows + enforce 1 biến thể tên/code); codes `VNA25-`/`VNAP25-` immutable, không chứa id DB-sinh. Validator: counts {34/3321/0, 63/699/10595}, code pattern theo scheme, collision 19 nhóm/38 rows (PRE), 2025 cấm depth-2, **chấp nhận name_en non-ASCII** (tên dân tộc), reject ký tự ẩn (dòng ZWSP sẽ fail đúng — sửa file nguồn).
- **Config**: `secomm_vietnam_address/general/active_scheme` (mới — system.xml + config.xml default VN_ADMIN_2025 + acl); backend model chặn admin flip sang scheme chưa cài; importer ghi active_scheme + `address/profiles/mapping` SAU import thành công (§7).
- **CLI `--rebuild`** (thêm chiều tối 2026-08-27, theo yêu cầu user): clean re-import cùng scheme — purge runtime + snapshot rows CỦA SCHEME ĐÓ (snapshot scheme khác giữ nguyên — bất biến tích lũy cross-scheme), skip re-key, import từ đầu (city_ids mới). Dùng khi thay thế file dataset nguồn.
- **Historical reference layer (TASK-9394A9)**: bảng `secomm_vietnam_address_scheme` (registry: status CURRENT/HISTORICAL/FUTURE — label, không phải identity) + `secomm_vietnam_address_unit` (portable: scheme_code+code UNIQUE, KHÔNG FK runtime, region rows level 1) — tích lũy mọi scheme từng import, swap không đụng. Services: `VnSchemeRegistry`, `SchemeRegistryUpdater` (import→CURRENT, cũ→HISTORICAL), `UnitSnapshotWriter`, API `VnAddressUnitProviderInterface`.
- **Mapping + resolution layer (TASK-J9AVGK, supersede TASK-X0XKH4/AP6YXP)**: bảng `secomm_vietnam_address_mapping` (directed edges source/target scheme+code, relation SAME_AS/RENAMED_TO/MERGED_INTO/SPLIT_INTO, UNIQUE edge); CLI `secomm:vietnam-address:import-mapping <file> [--dry-run]` + `validate-mapping [--file]` (orphan/same-scheme/bad-type = errors; reverse-ambiguity = warning); `VnAdminAddressResolverInterface` statuses **EXACT/MAPPED/AMBIGUOUS/UNMAPPED** (union outgoing+incoming edges; AMBIGUOUS KHÔNG auto-pick; reverse-merge A→C,B→C: forward MAPPED, backward AMBIGUOUS). Mapping DATA chờ nguồn cung cấp — model sẵn sàng.
- **Importer**: FK guard thêm `secomm_ghtk_address_map`; patch rename `ImportVnAdmin2025SchemePatch`; `SeedVnProfileMembership` sửa tại chỗ (uncommitted) sang vn_admin_2025.
- **Design notes phases E/F + §23 snapshot** (carrier capability/resolution ShippingCore, external mapping scheme-keyed, address unit-code columns): xem DEC-FEATYA2C0W-003 — implement task sau.
- Tests: 40+ unit (catalog, backend guard, registry/updater, snapshot writer, unit provider, resolver 8 case, mapping reader/validator, reader/validator/importer mới).
- **Deployment note**: sau deploy cần `rm -rf generated/code generated/metadata` (hoặc setup:di:compile) + chạy `secomm:vietnam-address:import --scheme VN_ADMIN_2025` 1 lần để heal profile config cũ (vn_current → vn_admin_2025).

## 1.3.0 (2026-08-27) — FEAT-YA2C0W / TASK-ADT94K: dual-scheme datasets + swap model (DEC-FEATYA2C0W-002)

> Superseded cùng ngày bởi 1.4.0 (DEC-003 đổi naming + CSV format); giữ làm lịch sử quá trình.
- **Swap model**: DB giữ MỘT scheme VN tại một thời điểm (supersede D-R2 prefix `L-` + D-R4 coexist của DEC-FEATYA2C0W-001). Đổi scheme = purge + re-import + config flip; refresh cùng scheme = upsert theo code (bảo toàn `city_id`).
- **Datasets**: `Files/VN_Address_VN_CURRENT_import.csv` (2 cấp, 34 regions + 3.321 wards, codes `VNC-…`) + `Files/VN_Address_VN_LEGACY_import.csv` (3 cấp, 63 + 699 + 10.595, codes `VNL-…`); định dạng 6 cột `entity_type,region_code,code,parent_code,name_vi,name_en` (region rows + tên sạch; 19 nhóm collision legacy giữ suffix). Identity = code; display name chỉ để hiển thị.
- **Import pipeline** (`Model/Import/`): `VnDatasetReader` (BOM/CRLF tolerant, NFC-normalise) → `VnDatasetValidator` (contract counts 34/3321, 63/699/10595, collision 19 nhóm/38 rows, không U+200B, name_en ASCII, parent cùng region) → `VnAddressSchemeImporter` (gate `--swap`, purge guard FK `secomm_ghn_address_mapping_location` + bảo vệ region `is_default=1`, re-key bridge qua `CurrentDatasetRekeyMatcher` — khớp 3311/3311 theo vi-name strip-prefix/en-name strip-type-word, ambiguity → error) → gọi generic `Secomm_AddressDropdown\Api\HierarchyAddressImportInterface` → membership reseed (profile active claim toàn bộ region VN) → set config `address/profiles/mapping` default `VN → scheme` (giữ mapping country khác) → clean cache `config/graphql_query/full_page/block_html`.
- **CLI**: `bin/magento secomm:vietnam-address:import --scheme <vn_current|vn_legacy> [--swap] [--dry-run]` — dry-run validate + mô phỏng không ghi; đổi scheme thiếu `--swap` bị từ chối; lỗi validate → exit nonzero, không ghi gì.
- **Data patch**: `ImportVnCurrentAddressDatasetPatch` — fresh install nhận dataset VN_CURRENT sạch (re-key + upsert); VN_LEGACY opt-in qua CLI. Patch cũ không đổi.
- **Validator hardening**: 3 `ValidateVietNamWard` (cart estimate / customer address / admin config) giờ match `default_name` HOẶC locale name — schema renderer submit tên vi (vd "Hoàn Kiếm") vẫn validate được.
- **Hiệu ứng cần biết (swap)**: mỗi lần swap = địa chỉ đã lưu mang tên/id scheme cũ sẽ không match validator khi re-submit (pre-launch chấp nhận). Known limitation: `generated/metadata` DI compile snapshot phải regenerate sau khi thêm DI mới (`rm -rf generated/code generated/metadata` trên dev).
- Tests: 34 unit (5 file mới + validator test cập nhật). Evidence: `.ai/runtime/evidence/TASK-ADT94K/`.

## 1.2.0 (2026-08-25) — FEAT-YA2C0W / TASK-R83FXW: VN address profiles
- **Address Profiles** `vn_current` + `vn_legacy` declared via `etc/address_profiles.xml` (generic Secomm_AddressDropdown engine, DEC-FEATYA2C0W-001): vn_current = region + city depth 1 (Ward/Commune); vn_legacy = region + city depth 1 (District/Town) + depth 2 (Legacy Ward/Commune). Labels/placeholders are translation keys — rendered through this module's i18n, never hard-coded.
- **Membership seeding**: new data patch `SeedVnProfileMembership` — vn_current subtree-claims the 34 current VN region roots in `secomm_address_profile_location` (excludes future legacy namespace `L-%`); idempotent upsert; sets default config `address/profiles/mapping` = `VN → vn_current` only when unset.
- **i18n**: new keys `Province/City`, `District/Town`, `Legacy Ward/Commune`, placeholders ×3 locales; `Ward/Commune` (vi_VN) cập nhật thành "Phường/Xã/Đặc khu" theo cấu trúc hiện hành 2025.
- Verified: getSchema 2/3 levels; resolve('VN') = vn_current; 34 membership rows, 0 orphan; upsert replay idempotent. See `.ai/runtime/evidence/TASK-R83FXW/`.

## 1.1.0 (2026-07-17)
- i18n rework (locale-based, supersede global overrides):
  - Added `en_VN.csv` — VN-English address labels (Province/City, Ward/Commune, placeholders, Select-a variants).
  - Reverted `en_US.csv` — removed global VN overrides → clean default (City, State/Province).
  - Cleaned `vi_VN.csv` storefront rows — fixed `Select a city` (district → Phường/Xã), normalized casing (Tỉnh/Thành phố).

## 1.0.0
- Initial: 2-level VN address data install (`Files/VN_Address_2Level.csv` via `InstallVietNamAddressPatch`) + `vi_VN.csv` / `en_US.csv` i18n.
