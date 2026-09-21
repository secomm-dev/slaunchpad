# Evidence — TASK-GS78X2 (RefreshVnAdminPre2025Snapshot2024)

Ngày: 2026-09-08 · Mode C · patch implemented + fail-loud/rollback PROVEN; apply-path BLOCKED trên data finding (§F).

## Baseline (trước patch)

```text
secomm_vietnam_address_unit  VN_ADMIN_2025       L1=34     L2=3,321
secomm_vietnam_address_unit  VN_ADMIN_PRE_2025   L1=63  L2=699  L3=10,595
secomm_vietnam_address_mapping  PRE→2025 = 10,064 edges
directory_country_region VN = 34 · directory_region_city = 3,321
patch_list: 3 legacy patches applied (2025Scheme, Pre2025Reference, Pre2025To2025Mapping)
```

## Snapshot files (đã có trong Files/, đặt 2026-09-08)

| File | Rows | Verify script-level |
|---|---|---|
| `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv` | 10,732 (1 header + 10,731 units) | 63 regions + 696 depth-1 + 10,035 depth-2; 0 dup code; 0 sai prefix VNAP25; collision 36 rows/18 groups |
| `VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv` | 10,419 (1 header + 10,418 edges) | format chuẩn 5 cột |

Tên file theo convention module (`*_import.csv`, `*_TO_*_mapping.csv`) — KHÁC 2 tên trong
directive (ghi nhận deliverable #8).

## F — FINDING (blocker cho apply-path): snapshot vi phạm dataset uniqueness contract

`setup:upgrade` → patch fail-loud đúng thiết kế với 6 duplicate `(region, parent_code, name_vi)`
triples — đều là cặp **đơn vị cấp huyện trùng tên trong cùng tỉnh** (cấu trúc hành chính thật
cuối 2024: TP/thị xã + huyện cùng tên), mà file cũ (63/699/10,595) chỉ giữ 1 trong mỗi cặp:

| Tên | Tỉnh (mã snapshot) | Codes (snapshot) |
|---|---|---|
| Cao Lãnh | VN-20 Đồng Tháp | `VNAP25-FC22E31B3D` (L2453) vs `VNAP25-972B3E8BC8` (L2467) |
| Hồng Ngự | VN-20 Đồng Tháp | `VNAP25-5D463493F3` (L2499) vs `VNAP25-2DF5650FDE` (L2507) |
| Kỳ Anh | VN-25 Hà Tĩnh | `VNAP25-0A8E03F3D3` (L3841) vs `VNAP25-3DB954072E` (L3853) |
| Long Mỹ | VN-28 Hậu Giang | `VNAP25-58979A3067` (L4348) vs `VNAP25-612DA6DBE7` (L4358) |
| Cai Lậy | VN-58 Tiền Giang | `VNAP25-B0616F9627` (L9910) vs `VNAP25-015A774261` (L9927) |
| Duyên Hải | VN-59 Trà Vinh | `VNAP25-B51E245DA5` (L10117) vs `VNAP25-C5B8541622` (L10125) |

Contract bị vi phạm: `VnDatasetValidator::checkUnits` — UNIQUE(region|parent|name_vi)
(anti-drift, thiết kế theo file cũ không có cặp trùng). Identity KHÔNG bị đụng (code unique ✓,
UNIQUE(scheme,code) vẫn thỏa). Chỉ minh bạch ghi nhận: **directive cũng KHÔNG liệt kê check này
trong "Validation bắt buộc"** — nó đến từ reuse existing validator (đúng yêu cầu reuse).

**Không tự xử** (theo đúng scope): không sửa CSV (canonical source), không silently skip check.
Hai options chờ TL/SA:
- **(A) Regenerate snapshot file** với district-name disambiguation suffix (pattern ward
  collisions hiện có: `"Cao Lãnh (Thành phố)"` / `"Cao Lãnh (Huyện)"`) → cập nhật collision
  override trong di.xml theo số mới; không đụng codes.
- **(B) Phê duyệt nới contract cho snapshot**: thêm override flag cho phép duplicate
  (region|parent|name_vi) ở depth-1 của PRE_2025 snapshot (data thật 2024) — identity vẫn code.

## Verify đã chạy

```text
$ php bin/magento setup:di:compile → Generated code and dependency injection configuration successfully.
$ php bin/magento setup:upgrade
  → patch FAIL-LOUD: VnImportValidationException — 6 duplicate (region, parent, name_vi)
    + "Region rows: expected 63, got 63 / Depth-1: 696/696 / Depth-2: 10035/10035 /
       Collision rows: 36/36" (counts override hoạt động đúng)
$ Sau failure — DB UNCHANGED (transaction rollback proven):
  unit PRE = 63/699/10,595 · mapping = 10,064 · unit 2025 = 34/3,321
  directory region = 34 · city = 3,321 · patch_list: patch KHÔNG được ghi (NOT APPLIED)
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter VietNamAddress
  → OK: 148 tests / 442 assertions (0 failure; 5 PHPUnit deprecations pre-existing)
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml
  → 964 tests, 15 errors = 8 PromotionMaxDiscount + 7 Tracking (pre-existing/other-task debt;
    0 liên quan VietNamAddress)
```

## Deliverables

| File | Thay đổi |
|---|---|
| `Setup/Patch/Data/RefreshVnAdminPre2025Snapshot2024.php` | MỚI — orchestration: tx-wrapped cleanup (DELETE mapping toàn bảng + DELETE units scheme-scoped) → snapshot unit import → snapshot mapping rebuild → post-import validation (counts L1/L2/L3, dup codes, orphan parents, edge count, DB-mode mapping re-validate) |
| `Model/Import/VnDatasetValidator.php` | Thêm DI `$expectedOverrides` (mirror pattern `$files` của reader) — backward compatible, mặc định rỗng |
| `etc/di.xml` | 3 virtual types (`VnSnapshot2024DatasetReader`/`VnSnapshot2024DatasetValidator`/`VnSnapshot2024ReferenceImporter`) + wire vào patch |
| `.ai/records/tasks/TASK-GS78X2.md`, evidence này | governance |

Reuse: `VnDatasetReader` (file override), `VnDatasetValidator`, `VnReferenceSchemeImporter`,
`UnitSnapshotWriter`, `SchemeRegistryUpdater`, `VnMappingImporter`/`VnMappingReader`/
`VnMappingValidator`, `VnSchemes`, `ComponentRegistrarInterface` — 0 parser/importer mới.
