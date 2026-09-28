# Evidence — Step 1 Closure Gate (Batch A + B1)

Ngày: 2026-09-21 · TASK-SEC-AUDIT

## 1.1 PRE-2025 upgrade safety — DONE

- `patch_list` history: mọi environment đi qua chain `ImportVnAdmin2025 → Pre2025Reference → Mapping(baseline) → RefreshSnapshot2024` — Refresh (wipe + snapshot re-import) chạy SAU mapping seed nên env đã-apply đều ở snapshot units + 10,418 mapping (đối chiếu DB sống: đúng 10,418).
- **r2 (REOPEN fix — count là identity sai):** phiên đầu dùng count-based no-op — một DB có ĐÚNG 10,418 rows nhưng SAI content sẽ bị bỏ qua, và 41 stale edges của baseline cũ không bị xóa bởi upsert-only → KHÔNG hội tụ. Đã thay bằng **exact keyset sync** (`VnSnapshotMappingResync`, 5 unit tests): (1) upsert toàn bộ snapshot edges; (2) delete CHÍNH XÁC 41 keys bundled-ownership được ghi trong manifest `VN_ADMIN_PRE_2025_SNAPSHOT_2024_mapping_manifest.json` (kèm sha256 của 2 bundled files — checksum guard fail-loud). Custom edges (keys ngoài cả 2 bundled datasets) không bao giờ nằm trong scope xóa. Idempotent + hội tụ (lần 2 = cùng bounded statements, zero changes). Patch chain/identity giữ nguyên.
- Patch đã phát hành KHÔNG bị đổi identity — chỉ const MAPPING_FILE của patch seed trỏ thẳng snapshot cho fresh-install (env cũ không re-run nên không bị đổi behavior).

## 1.2 Mapping reconciliation — CLEAN

`mapping-reconciliation-report.txt` (tool: `reconcile_mapping_tool.php`, SELECT-only):

| Chỉ số | Giá trị |
|---|---|
| CSV snapshot edges / DB edges | 10,418 / 10,418 |
| DB extra vs CSV / missing | 0 / 0 |
| CSV duplicate tuples | 0 |
| Changed relation type | 0 |
| vs superseded baseline (10,064): baseline-only / snapshot-added | 41 / 395 |
| Orphan source / target codes | 0 / 0 |
| Ambiguous sources (merge 2-target) | 297 (hợp lệ — resolver AMBIGUOUS, không auto-pick) |
| Multi `is_primary=1` sources | 0 |
| Coverage | 10,103/10,794 units có edge; 691 legit-unmapped |

→ Snapshot CSV = DB, khớp architecture contract. Không chỉnh test count để "hợp thức hóa".

## 1.3 Factory verification — CORRECTED (r2)

**Corrected premise:** Magento DOES generate `*Factory` classes — for any referenced class
name ending in `Factory` whose base class exists, the object-manager code generator emits a
factory (at `setup:di:compile` for production stacks, and on demand in developer mode). The
generated implementation is functionally identical to a handwritten
`ObjectManager::create(BaseClass::class, $data)` factory.

### Classification of each restored factory

| Factory | Target class | Magento can generate? | Handwritten behavior? | Why a source class is needed | Decision |
|---|---|---|---|---|---|
| `Model\RegionFactory` | `Model\Region` | YES | none beyond create() | None beyond test bootstrap | **KEPT for now, flagged** — 100% identical to a generated factory; TL may ask to delete it and let codegen provide it (see note) |
| `Model\CityModelFactory` | `Model\CityModel` | YES | none | same | KEPT, flagged as above |
| `ResourceModel\CityModel\CityCollectionFactory` | `CityCollection` | YES | none | same | KEPT, flagged |
| `ResourceModel\CityModel\CityLocaleCollectionFactory` | `CityLocaleCollection` | YES | none | same | KEPT, flagged |
| `ResourceModel\CityNameModel\CityNameCollectionFactory` | `CityNameCollection` | YES | none | same | KEPT, flagged |
| `ResourceModel\RegionModel\CollectionFactory` | `RegionModel\Collection` | YES | none | same | KEPT, flagged |
| `Model\Directory\CountryCollectionFactory` | `Magento\Directory\…\Country\Collection` | The *referenced name* `Magento\…\CollectionFactory` itself would ALSO be generated (base Collection exists) | none | The vendor name was always generated-on-demand; the real pre-existing defect was only the missing type-hint target for standalone/unit context | KEPT (module-owned name, explicit dependency, no vendor edit; alternative — reverting Helper type-hint to the Magento name and relying on generation — is viable and noted) |

**Honest reasoning for keeping the six handwritten factories** (reversible on TL call):
1. The module already shipped handwritten factories historically (`RegionFactory` etc. existed
   in git history) — restoring them follows the module's own established API, not a new
   convention invented for tests.
2. Sources make the dependency resolvable in ANY context (unit bootstrap, static analysis)
   instead of relying on codegen side effects.
3. They are pure builders — zero logic that could drift from a generated twin.
Cost of the alternative (delete + unit-test-local doubles in every constructor-consuming
test) is mechanical but broad; recorded as a TL decision, not silently chosen.

### Verification (as previously run, still valid)
- Controlled cleanup: `rm -rf generated/code/Secomm generated/code/Launchpad` → `setup:di:compile` OK → no duplicate generated factory classes for these names (grep = 0).
- Developer-mode CLI bootstrap OK; `MAGE_MODE=production bin/magento info:adminuri` OK.
- AddressDropdown targeted suite 96/223 OK; GraphQL resolver construction covered by resolver unit suites (constructor-level); admin CRUD construction covered via command suites.
- `CountryCollectionFactory` behavioral parity: it is a pure `ObjectManager::create(Country\Collection::class, $data)` builder — resource model, filters, store/locale behavior, returned type and lifecycle are entirely the Magento collection's own; no semantics changed.

## 1.4 POST delete runtime proof — code-chain verified; browser = BLOCKED_BY_ENVIRONMENT

- `globals.js deleteConfirm(message, url, postData)`: `postData !== undefined → postData.action = url; dataPost().postData(postData)`.
- `mage/dataPost.js postData()`: inject `form_key` từ `input[name="form_key"]` vào `params.data` → build `<form method="post">` → submit.
- **Magento 2.4.8 core dùng y hệt third-arg `{data: {}}`** (vendor/magento/module-catalog Attribute Set Main.php:130 `{data: {}}`) → pattern của tôi đúng API hiện hành.
- Controllers: POST-only interface + explicit `isPost()` guard (GET → reject, 0 mutation). Browser click-through thực tế: `BLOCKED_BY_ENVIRONMENT` (không browser automation).

## 1.5 CanonicalDataGuard inventory

| Write surface | Guard |
|---|---|
| Region/City SaveCommand | CanonicalDataGuard (VN reject, non-VN pass) |
| Region/City DeleteByIdCommand | CanonicalDataGuard |
| City/Region MassDelete | Đi qua DeleteByIdCommand (City MassDelete re-wired) |
| Import entities (Save/DeleteAddressImport) | Đi qua chính Save/Delete commands → guarded |
| Direct resource save/delete | Không có path nào ngoài commands (audit grep) |
| Setup/data patch của AddressDropdown | Không có data patch ghi VN rows |
| **HierarchyImportService** (direct SQL writes) | Trusted-consumers-only: consumer duy nhất = `VnAddressSchemeImporter` (Secomm_VietNamAddress — canonical workflow). Không consumer AddressDropdown admin/public. Ghi chú: nếu API interface được consumer bên ngoài sau này → phải thêm guard (backlog) |
| VietNamAddress CLI importer | Trusted canonical feeder (by design) |
| Hierarchy repair CLI | Không thuộc AddressDropdown (VietNamAddress owns, chỉ sửa bảng của nó) |
| GraphQL | Chỉ queries (không mutation) — schema.graphqls |

Trusted bypass = explicit composition dependency (VietNamAddress importer), KHÔNG global flag/area-code bypass. Exception paths: guard throws TRƯỚC write → không state lưa vữa; CouldNot* pass-through giữ message gốc.

## 1.6 Master-switch

- Server-side gates (không thể bypass): 3 GraphQL resolvers (return []), `city-data` section ([]), Cart LayoutProcessor (no-op) — store-scoped qua `ScopeInterface::SCOPE_STORE` default store context của section/resolver runtime.
- Client-side: 6 mixins + 2 components inert khi flag false (không request, không mutation) — flag từ `checkoutConfig.secommAddressDropdownEnabled` (DefaultConfigProvider store-scoped).
- **Defect fix tìm thấy khi verify multi-store**: `city-data` cache key cũ GLOBAL `city_data_cache_key` → store B ăn dataset store A. Đã store-scope cache id (`city_data_cache_key_<storeId>`); disabled path không đụng cache.

## 1.7 Command results

```text
Secomm_AddressDropdown  96 tests / 223 assertions  (baseline 76)
Secomm_VietNamAddress  189 tests / 519 assertions  (baseline 182; +5 tests từ stream 0.19.0-era)
setup:di:compile        OK
MAGE_MODE=production bootstrap OK
```

## Blocker còn lại

- Browser click-through cho A3/A5: BLOCKED_BY_ENVIRONMENT.
- `setup:upgrade` fresh + upgrade-run thực: chưa chạy trong phiên này (cần DB sạch + time; logic patch đã unit-proven) → BLOCKED_BY_ENVIRONMENT (partial).
