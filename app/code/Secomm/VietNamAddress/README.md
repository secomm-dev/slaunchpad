# Secomm_VietNamAddress

Versioned Vietnam administrative datasets, historical reference and scheme mapping/resolution for `Secomm_AddressDropdown` (and, from Phase E on, the shipping carriers). Vietnam-specific data lives HERE; the dropdown/hierarchy engine stays generic (DEC-8 boundary).

## Scheme model (DEC-FEATYA2C0W-003)

- **Immutable identities**: `VN_ADMIN_2025` (2 levels: province → ward/commune) and `VN_ADMIN_PRE_2025` (3 levels: province → district → ward/commune). A future reform adds `VN_ADMIN_<year>` — never renames an existing scheme.
- **Status ≠ identity**: `CURRENT` / `HISTORICAL` / `FUTURE` are labels in the scheme registry (`secomm_vietnam_address_scheme`), rendered in config/UI, never used as codes.
- **Unit codes**: dataset-supplied, immutable (`VNA25-…` / `VNAP25-…`); internal Secomm identity — not government codes, not carrier codes, never parsed for the scheme.
- **Runtime = swap model (DEC-002, retained)**: `directory_country_region` + `directory_region_city` hold exactly ONE scheme at a time; `secomm_vietnam_address/general/active_scheme` says which. Manual admin flips are rejected (backend model) unless the target scheme is installed.
- **Historical reference layer**: `secomm_vietnam_address_unit` keeps EVERY scheme ever imported (portable codes, no FKs to runtime tables) — old administrative knowledge survives runtime swaps.
- **Mapping layer**: `secomm_vietnam_address_mapping` — directed edges between scheme unit codes (`SAME_AS|RENAMED_TO|MERGED_INTO|SPLIT_INTO`); resolution via `VnAdminAddressResolverInterface` returns `EXACT|MAPPED|AMBIGUOUS|UNMAPPED` and never auto-picks (a merge A→C + B→C is deterministic forward, AMBIGUOUS backwards with both candidates).

## Datasets (`Files/`)

Canonical 7-column format, self-contained (region names embedded; no DB-generated ids):

```
region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en
VN-01,An Giang,An Giang,VNA25-3D6A6CF4D0,,An Biên,An Bien
VN-01,An Giang,An Giang,VNAP25-B70EDA95D6,,An Phú,An Phu
VN-01,An Giang,An Giang,VNAP25-515AFEF59D,VNAP25-B70EDA95D6,An Phú,An Phu
```

- `region_code` is the dataset region identity `VN-XX` (`VnSchemes::REGION_CODE_PATTERN`) — alphabetical sequence per scheme (VN-01 = An Giang), NOT the bare official government numbering (`01` = Hà Nội) used by the legacy sources.
- `VN_ADMIN_2025_import.csv` — 34 regions + 3.321 wards (all depth-1). Default for fresh installs.
- `VN_ADMIN_PRE_2025_import.csv` — 63 regions + 699 districts + 10.595 wards; 19 collision groups keep the type suffix (e.g. `Yên Viên (Thị trấn)` / `Yên Viên (Xã)`).
- Name hygiene: cleaned of administrative type prefixes; `name_en` MAY legitimately be non-ASCII (ethnolinguistic names); invisible/zero-width characters are rejected by the validator.
- Fresh installs bootstrap entirely from `VN_ADMIN_2025_import.csv` via `ImportVnAdmin2025SchemePatch` (regions + units + membership + `address/profiles/mapping` + `active_scheme` in one transaction); `SeedVnProfileMembership` runs after it as an idempotent belt-and-braces pass. The legacy `VN_Address_2Level.csv` source and its `InstallVietNamAddressPatch` bootstrap were removed with the sub_city retirement (TASK-6MKF0V / DEC-TASK6MKF0V-001) — databases that still carry the legacy-format rows keep upgrading in place through the re-key bridges (`CurrentDatasetRekeyMatcher`, region_id/city_id preserved). The 3-level `VN_Address.csv` (sub_city dataset) was removed in the same retirement; the pre-2025 3-level structure is now sourced from `VN_ADMIN_PRE_2025_import.csv` (depth-2 city rows, no sub_city).

## CLI

```bash
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_2025 [--dry-run]   # refresh (idempotent upsert)
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap    # switch scheme
bin/magento secomm:vietnam-address:import-mapping <file> [--dry-run]           # mapping edges (data TBD)
bin/magento secomm:vietnam-address:validate-mapping [--file <path>]            # orphans + reverse-ambiguity report
```

- `--dry-run` khi sẽ cần swap: report thêm `guard_violations` — danh sách external-reference guard (carrier mapping tables đăng ký qua DI, DEC-FEATYA2C0W-004 D7) còn tham chiếu runtime rows, để operator thấy bảng chặn TRƯỚC khi swap thật.

- Same scheme → refresh by code (no purge; `region_id`/`city_id` preserved via the re-key bridges on the first run).
- Other scheme → refused without `--swap`; with `--swap`: purge all VN runtime data (FK-guarded against GHN/GHTK mapping tables), import, snapshot to the historical layer, registry transition (old → HISTORICAL), reseed membership, flip `active_scheme` + `address/profiles/mapping` — only AFTER import success — and clean caches.
- `--dry-run` validates the dataset contract and simulates (no writes).
- **Empty VN tables bootstrap fine**: the dataset is the seed source — region rows travel inside the hierarchy import batch and regions are created when absent (`directory_country_region` gets `code = VN-XX`, `default_name = region_name_en`, plus vi_VN/en_US locale names).
- **Legacy-format bridge** (fresh installs / pre-upgrade DBs): regions carry bare official codes (`01` = Hà Nội) that cannot be prefix-transformed into `VN-XX` (different numbering) — they are matched on normalised names (English type words / Vietnamese type prefixes stripped) and re-coded in place before the city bridge runs, so ids survive. Unmatched strays (regions without a dataset code, cities with `code IS NULL`) and orphaned membership claims are reconciled after the import (FK-guarded, reported in the CLI output).
- **All-or-nothing**: the whole write phase (purge → bridges → hierarchy import → cleanup → snapshot → registry → membership → config) runs in ONE transaction — a mid-import failure rolls back everything; `active_scheme` never moves on a failed import.

## Ward validation

Cart estimate / customer address / admin store+origin validators match the submitted ward against `default_name` OR the locale-resolved name. **Swapping schemes resets saved-address name matching** (pre-launch accepted; the §23 unit-code snapshot design in DEC-003 is the future fix).

## Operational ↔ canonical bridge + external-reference guards (DEC-FEATYA2C0W-004)

- `VnOperationalAddressResolverInterface` — bridge `region_id/city_id ↔ scheme_code/unit_code` thuần code-based: runtime rows mang dataset codes từ lúc import (`directory_country_region.code = VN-XX`, `directory_region_city.code = VNA25-* / VNAP25-*`); active scheme chỉ được tin khi registry `CURRENT` khớp; non-active scheme → reverse KHÔNG fabricate runtime id.
- `DirectoryReferenceGuardInterface` — scheme-swap safety giờ là **DI extension point**: carrier mapping tables (`secomm_ghn_address_mapping_location`, `secomm_ghtk_address_map`) tự đăng ký guard từ module sở hữu; `VietNamAddress` orchestrate + aggregate vi phạm, không hardcode tên bảng carrier nào.

## Requirements

- `Secomm_AddressDropdown` (generic hierarchy + profile engine + `HierarchyAddressImportInterface`).
- `Secomm_VietNamMarket` (registers the `en_VN` locale).

## Related

- Architecture records: DEC-FEATYA2C0W-001/002/003, SPEC-FEAT-YA2C0W (`.ai/`).
- Phases E (ShippingCore carrier capability/resolution + GHN reference) and F (GHTK/Ahamove) — design notes in DEC-003; separate tasks.
