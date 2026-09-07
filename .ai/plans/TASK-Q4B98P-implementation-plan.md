# Implementation Plan: TASK-Q4B98P — Operational ↔ canonical identity bridge + DirectoryReferenceGuard

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-Q4B98P (parent FEAT-YA2C0W) |
| Mode | A (Tier-2 address domain) |
| Specification | [specs/SPEC-FEAT-YA2C0W-canonical-identity-bridge.md](../specs/SPEC-FEAT-YA2C0W-canonical-identity-bridge.md) — FULL, **Approved** 2026-09-03 |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) — accepted 2026-09-03 (D5 bridge, D7 guard) |
| Risk | High (touch import/swap path + 3 modules) — mitigate bằng guard-parity + dry-run test |
| Schema change | **NONE** (pure lookup; no table, no column, no whitelist) |

## Approach

Cơ chế đã verify: runtime tables mang sẵn canonical codes ⇒ bridge là code-lookup. Tách 2 mối quan tâm:
(1) bridge service thuần đọc trong `VietNamAddress`; (2) guard extension — contract nằm
`VietNamAddress`, concrete guards nằm module sở hữu bảng (`GhnAddressMapper`, `Ghtk`), DI array merge.
Thứ tự trong cùng một PR: contract + guards DI trước → xoá hardcode sau (protection liên tục).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Contracts + DTO | `VietNamAddress/Api/VnOperationalAddressResolverInterface.php`, `Api/DirectoryReferenceGuardInterface.php`, `Api/Data/VnOperationalIdentityInterface.php`, `Model/Data/VnOperationalIdentityData.php` | immutable DTO; reason constants (`scheme_not_active`, `runtime_row_missing`, `runtime_code_missing`, `not_vn_region`, `invalid_input`) |
| 2 | Resolver impl | `VietNamAddress/Model/VnOperationalAddressResolver.php` | op→canon: `directory_region_city.code` scoped `city_id` + join region (country=VN guard); canon→op: query ngược chỉ khi scheme active; scheme = `active_scheme` config, drift-check registry `status=CURRENT` (AC-9); metadata hydrate qua `VnAddressUnitProviderInterface` |
| 3 | Guard wiring trong importer | `VietNamAddress/Model/Import/VnAddressSchemeImporter.php` + `etc/di.xml` | constructor + array argument `directoryReferenceGuards`; `assertNoCarrierReferences` → loop guards, **collect rồi throw 1 exception aggregate**; `dryRun()` report guards; xoá `TABLE_GHN_MAPPING`/`TABLE_GHTK_MAPPING` (bước này sau bước 4–5) |
| 4 | GHN guard | `GhnAddressMapper/Model/Import/DirectoryReferenceGuard.php`, `etc/di.xml`, `etc/module.xml`, `composer.json` | move logic bảng `secomm_ghn_address_mapping_location` (region_id, city_id); `module.xml` += `Secomm_VietNamAddress`; composer require `secomm/module-vietnam-address` |
| 5 | GHTK guard | `Ghtk/Model/Import/DirectoryReferenceGuard.php`, `etc/di.xml`, `etc/module.xml` | move logic bảng `secomm_ghtk_address_map` (region_id, ward_id); `module.xml` += `Secomm_VietNamAddress` (không có composer.json) |
| 6 | i18n | `GhnAddressMapper/i18n/{vi_VN,en_US}.csv`, `Ghtk/i18n/{vi_VN,en_US}.csv` (tạo dir nếu chưa) | guard message translated; message cũ trong `VietNamAddress/i18n` xoá nếu không còn dùng |
| 7 | Tests | `VietNamAddress/Test/Unit/Model/VnOperationalAddressResolverTest.php`, `.../Import/VnAddressSchemeImporterTest.php` (update), `GhnAddressMapper/Test/.../GuardTest.php`, `Ghtk/Test/.../GuardTest.php` | matrix §7 của spec: 2025/PRE_2025 2 chiều; edge (invalid id, NULL code, non-VN, drift, non-active); guard zero/one-safe/one-blocking/multi-aggregate; spy "no name lookup" |
| 8 | Docs | README + CHANGELOG ×3 module | DEC-FEATYA2C0W-004 reference; GHTK ACL doc-drift (P2) chỉ ghi chú, không sửa ngoài scope |
| 9 | Validation | `vendor/bin/phpunit` module tests · `.ai/bin/project-ai-validate --check-records --check-specs` · `grep -rEi 'ghn\|ghtk\|ahamove' app/code/Secomm/VietNamAddress --include='*.php'` (AC-6) | grep chỉ được còn hit ở contract comments/tests của guard contract |

## Test plan

- Unit (AAA): theo §7 spec — happy + edge + error; guard aggregation qua fake guards.
- Parity check: trước/sau refactor, `secomm:vietnam-address:import --dry-run` trên DB local phải chặn
  cùng điều kiện (giữ 2 bảng carrier có data fixture → dry-run FAIL như cũ).
- Regression: cart estimate + admin order address cascade không bị ảnh hưởng (không đổi template/plugin).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Guard message đổi làm mất translation | copy message vào i18n module nhận; grep verify |
| Edge tuần tự DI merge sai thứ tự | aggregation không phụ thuộc thứ tự (test 2 guards) |
| Drift check sai khi registry trống (fresh install) | null-safe registry reads (pattern `VnSchemeRegistry` hiện có) |

Rollback: revert commits — không DB state, không config, không data migration.

## Exit

AC-1..AC-10 green + validator pass → pre-review → TL review (Tier-2). Sau task: spec kế tiếp =
ShippingCore Carrier API Profile + Destination Orchestration.
