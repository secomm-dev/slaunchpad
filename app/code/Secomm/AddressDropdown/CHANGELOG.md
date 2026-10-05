# Secomm AddressDropdown

## [Unreleased] — TASK-37PS41: fix define/factory param misalignment phá customer address cascade (2026-10-02)
- **Bugfix `view/adminhtml/web/js/form/provider-mixin.js`**: `define` khai báo 4 deps nhưng
  factory chỉ nhận 3 params → param `schemaCascade` nhận nhầm `mage/validation`,
  `schema-cascade` thật bị load mà không gán → `createCascade is not a function` lúc bind →
  cascade customer address modal chết, 2 field City (select server + native input) hiện song
  song. Fix: xoá 2 dead deps (`mage/utils/wrapper`, `mage/validation` — không dùng trong body).
  Scan deps/params toàn bộ `web/js` của AddressDropdown/VietNamAddress/Launchpad_Osc: không còn
  file nào dính.

## [Unreleased] — TASK-Z6SK3T: GetListCity BC shim + FE migration sang addressLocations (2026-10-02)
- **Resolver shim (DEC-TASKZ6SK3T-001, accepted)**: `GetListCityGraphql` delegate
  `LocationHierarchyProvider::getRootLocations()` cho country có profile — **root-only**
  (`parent_city_id IS NULL`, không tái hiện flat mixed-tier list TASK-6MKF0V AC-3);
  unmapped country + `area=ADMINHTML` giữ legacy `CityLocaleCollection` (BC locale +
  non-profile dataset); region_id non-numeric/≤0 → `[]` không query; **schema.graphqls
  KHÔNG đổi** (không cần `graphql:dump`). Response trên data hiện tại **byte-identical**
  với trước shim (168/168 rows region 1205 — evidence `after_shim_getlistcity_r1205.json`).
  Unit: `GetListCityGraphqlTest` rewrite 7 test (mapped/unmapped/admin/invalid/null/switch-off).
- **FE migration — shared cache module mới `view/frontend/web/js/model/address-location-cache.js`**:
  memo page-session `addressSchema(country)` + `addressLocations("<profile>|<regionId>")`,
  in-flight Promise dedupe (shipping + billing cùng page = 1 POST), `clear()` khi đổi country.
  7 call sites migrate từ `GetListCity` (deprecated, uncached, POST mỗi region change):
  `address-dropdown.js` (Luma widget — đồng thời bỏ fire empty `region_id` lúc page load,
  giữ nguyên end-state local), `action/shipping-address-dropdown.js` + `billing` (default
  checkout), `view/cart/shipping-estimation-mixin.js`, 2 bản `Launchpad_Osc` copies,
  Hyvä legacy renderer `templates/hyva/address/edit.phtml` (inline Alpine memo — không
  RequireJS; renderer này chỉ active ở store `address/general/renderer=legacy`).
  OSC trùng `addressSchema` ×2 (shipping + billing) giờ dùng chung 1 POST qua memo.
- Không đổi: persistence `city` = `default_name` (BC carriers), GraphQL input/output
  contract, admin cascade (`schema-cascade.js` đã dùng `addressLocations` sẵn).
- Flag (out of scope, đã ghi nhận): `_loadRegions` trong `address-dropdown.js` vẫn reference
  `GetListRegion` — field không tồn tại trong schema từ trước (dead path country-change của
  Luma widget, lỗi cũ); default-mixin thiếu master-switch gate (billing-mixin có).
- Unit: AddressDropdown 109/109, VietNamAddress 202/202; `node --check` 7/7 JS files;
  `setup:di:compile` OK.

## [Unreleased] — TASK-Z6SK3T: city-data N+1 fix + helper single-fetch + `secomm_address_city` cache type (2026-10-02)
- **Helper single-fetch (TASK-Z6SK3T)**: `Helper\Address::getCityNameByDefaultName()` chạy select **1 lần** (trước: `fetchOne` gọi 2 lần cho cùng kết quả — truthy check double-executes), check miss tường minh (`false`/`null`), per-request memo theo `defaultName|regionId|locale`, thêm tie-break `d.city_id ASC` (dataset có ward trùng tên `Thanh An` ×2 — resolve deterministically). Consumers hưởng lợi tự động: `AddressRendererPlugin`, `DataProviderWithDefaultAddressesPlugin`. Tests: `Test/Unit/Helper/AddressTest.php` 5/5.
- **Perf (N+1)**: `CustomerData\CityData::getSectionData()` build dataset bằng **đúng 2 `fetchAll`** (trước: 1 region collection + 1 city collection **mỗi region** = 1.191 query trên cold cache; đo local: cold 525.9ms → 11.9ms, 34 region keys thay vì 1.190). Group + sort giữ nguyên output shape per-region (`name`/`label`/`city[default_name]` với `name` nullable) + canonical sort TASK-7HVGAB — **VN subset byte-identical** với output cũ (evidence `.ai/runtime/evidence/TASK-Z6SK3T/`).
- **Perf (N+1)**: `CustomerData\CityData::getSectionData()` build dataset bằng **đúng 2 `fetchAll`** (trước: 1 region collection + 1 city collection **mỗi region** = 1.191 query trên cold cache; đo local: cold 525.9ms → 11.9ms, SQL warm ~82ms single-query). Group trong PHP, giữ nguyên output shape per-region (`name`/`label`/`city[default_name]` với `default_name`+`name` nullable) + canonical sort TASK-7HVGAB — **VN subset byte-identical** với output cũ (evidence `.ai/runtime/evidence/TASK-Z6SK3T/`).
- **Data-derived scope**: region không có city (1.156 entry non-VN rỗng) bị bỏ khỏi payload — consumer-equivalent (mixins `city-data` đã fallback try/catch khi region không resolve được city). Payload 1.190 → 34 region keys.
- **New cache type `secomm_address_city`** (`etc/cache.xml` + `Model/Cache/Type.php`, house pattern `Secomm\GhnAddressMapper\Model\Cache\Type`): section cache trước đây save **không tag** trên default frontend → chỉ `cache:flush` purge được; giờ `cache:clean secomm_address_city` purge được (fix cửa sổ stale 1h sau scheme import). **Lưu ý deploy: type phải được enable trong env.php (`bin/magento cache:enable secomm_address_city`) — type mới mặc định disabled vì env.php `cache_types` là whitelist; nếu disabled, section rebuild mỗi lần fetch (graceful, không lỗi).**
- Constructor `CityData` đổi deps (bỏ `CityLocaleCollectionFactory`/`RegionCollectionFactory`/`TypeListInterface`/`CacheFrontendPool`; thêm `ResourceConnection`/`Model\Cache\Type`/`LocaleResolverInterface`/`App\State`) — đã `setup:di:compile`.
- Locale rule mirror `CityLocaleCollection::_initSelect` (admin area → DEFAULT_LOCALE).
- Tests: mới `Test/Unit/CustomerData/CityDataTest.php` (5 test: master switch, đúng 2 fetchAll + shape, store-scoped id + tag + TTL, cache hit skip DB, admin locale); suite 101/101 green.

## [Unreleased] — BUG-25XDH4: Hyva Address Book cascade Region → City (Alpine duplicate x-for key + stale-request race) (2026-09-04)
- **Staging crash**: `Uncaught TypeError: can't access property "after", O is undefined` (Alpine 3.14.3 x-for keyed reconciliation) khi chọn Region trên `customer/address/new|edit`. Root cause: city `x-for` dùng `:key="city.default_name"` — 8/34 VN regions có duplicate `default_name` (vd Dong Nai "Loc Thanh" x2, HCM "Thanh An" x2 — evidence `.ai/evidence/BUG-25XDH4/`) → `_x_lookup` collide. Commingled: không stale-request protection (đổi Region nhanh → response cũ overwrite `cities`), `@input.debounce` trên select (x-model của Alpine lắng nghe `change`), dual selection control (`x-model` + `:selected` cùng ghi selected state), `onRegionIdChange` không guard `availableRegions[selectedRegion]` (nổ khi `'0'`).
- **`templates/hyva/address/edit.phtml` (legacy)**: `:key="city.city_id"` (query thêm `city_id` — field đã expose sẵn, save contract giữ `:value="city.default_name"`); `:key="regionId"` cho region x-for; bỏ `:selected` (x-model là sole owner) + `reapplySelected('selectedRegion')` cho edit preselect (Alpine x-model KHÔNG re-apply value sau khi `<option>` render async — cùng pattern đã có cho city); `@change` thay `@input.debounce`; request token `cityRequestId` + `try/finally` (stale response không overwrite `cities`, chỉ request active clear `isLoadingCities`, `resetVnCascade` invalidate pending); `onRegionIdChange` defensive (String-coerce, optional region, guard `fields['region']`, region rỗng → reset cascade); `hyva.formValidation($el)` thay `$root`.
- **`templates/hyva/address/schema-edit.phtml` (cùng defect class — sibling renderer cùng trang)**: cùng bộ `$el`/`@change`/region `:key`/bỏ `:selected` + `reapplySelected`; token guard cho `loadSchemaForCountry` + `loadLevelOptions` (stale schema/level response không touch state; `changeCountry`/init prefill bỏ qua khi bị superseded); region rỗng → clear `cityLevels`.
- Không đổi: GraphQL surface, persistence (`region_id` + textual `city`), DB, dataset, admin forms, checkout. Debt ghi nhận: `GetListCity` deprecated (migrate `addressLocations` theo FEAT-2PZQKJ cutover); Alpine logic inline trong phtml (không unit-test được).

## [Unreleased] — TASK-9EX975 Slice A: admin surfaces schema-driven cascade (2026-09-03)
- **New shared factory `view/adminhtml/web/js/form/schema-cascade.js`** — generic, country-agnostic schema-driven city cascade cho admin address surfaces: level count / label / placeholder / required từ Address Profile (`addressSchema`), options per level từ `addressLocations` (region roots / `parent_city_id` children), stop-at-leaf semantics như storefront renderer (TASK-3T3NSV). Zero country-conditioned logic (DEC-FEATJSZQV3-003): unmapped country → `onSchemaResolved(false)` → surface giữ native `city` input. Level 0 = select có sẵn của surface; deeper levels inject sau nó; leaf được ghi qua callback `onLeaf(name)` — module không post gì (DEC-FEATE2HM1J-001). Exports: `createCascade`, `loadCityLevels`, `loadLevelOptions` (dùng lại bởi Secomm_VietNamAddress).
- **`form/provider-mixin.js` (customer address form, AJAX modal)** giữ nguyên quirks SL-011 (poll city_select, per-root bound guard, bounded region hydration wait ~3s, destroy cleanup) — internals cascade chuyển sang factory; prefill theo saved leaf name ở level 1.
- **`config/address-city.js` (Store Information + Shipping Origin)** giữ nguyên quirks config (region node bị thay bằng `innerHTML`, delegated events trên form, hydration watcher ~5min, fresh re-query) — internals cascade chuyển sang factory. Behaviour note: stale stored name không match option nào vẫn được giữ (input retain, mode select stays on) — value contract không đổi so với free-text fallback cũ.
- `templates/system/config/field/city.phtml` — chỉ docblock (select span giờ level-agnostic).
- Prefill contract (mọi admin surface): match leaf name ở level 1 — same documented limitation của storefront renderer (location path không được persist trên native stores; TASK-YQSS3M owns path contract). AC-002 của TASK-9EX975 ghi nhận deviation này.
- **Doc fix (stale profile codes)**: ví dụ trong `system.xml` (Address Profiles group + mapping comment), `etc/address_profiles.xml` header, docblock `AddressSchemaGraphql`/`AddressLocationsGraphql` còn dùng `vn_current`/`vn_legacy` — đã STALE từ TASK-ADT94K (profiles rename). Giờ chỉ mới là importer-side legacy aliases, KHÔNG resolve qua ProfilePool: copy nguyên ví dụ vào mapping config → warning log + fallback native (không cascade). Đổi ví dụ sang `vn_admin_2025` / `vn_admin_pre_2025` + note giải thích; smoke test explicit `profile_code:"vn_admin_pre_2025"` query OK.

## [Unreleased] — TASK-9EX975 Slice B: admin hierarchy add-child CRUD (2026-09-03)
- **Admin CRUD cho node city BẤT KỲ tầng**: grid `city_listing.xml` thêm cột `code`, `parent` (default_name của cha — computed server-side per page, bounded ancestor walk) và `level` (depth, kèm `⚠` khi vượt depth profile đang claim region); actions column thêm **Add child** → `*/city/new?region_id=X&parent_city_id=Y`.
- **Form**: `city_form.xml` thêm field `parent_city_id` (select phẳng toàn bộ cities của region, indent `—` theo depth, caption "— Directly under the region —" = NULL parent; `Model\OptionSource\CityParentOptions` — self + descendants excluded bằng BFS, cycle/bound-guarded) + field `code` (optional, max 64 chars). Mở form từ "Add child" → parent select disabled (readonly-context qua `CityDataProvider::getMeta()`).
- **Validation `Command\City\SaveValidator`** (mirror rules của `HierarchyImportService`, dùng chung const `HierarchyAddressImportInterface::MAX_DEPTH` / `::CODE_COLUMN_LIMIT` — const promoted từ service lên interface): region required; parent tồn tại + cùng region; chặn self-parent + cycle + broken chain (bounded walk); depth ≤ MAX_DEPTH; duplicate `(region_id, parent_city_id, code)` app-level (MySQL UNIQUE coi NULL phân biệt — depth-1 uniqueness enforce riêng, edit-exclude-self); code > 64 chars bị chặn. Hard failure → `LocalizedException` thân thiện (SaveCommand rethrow verbatim; `Controller\Adminhtml\City\Save` catch `LocalizedException` → flash message + redirect về form, giữ region_id/parent_city_id). Node vượt depth profile → vẫn lưu (hierarchy generic) nhưng warning log + grid cột Level có `⚠` (AC-B3).
- **Grid data**: `Query\City\GetListQuery` decorate `parent` (tên cha, 1 IN-lookup per page) + `level` (bounded walk, memoized; `⚠` từ max city depth của resolved profile — best-effort, lỗi config không chặn grid). `SaveCommand` normalise `'' → NULL` cho `parent_city_id`/`code` (form caption trống post `''`).
- **Contract**: `CityInterface`/`CityData` thêm `PARENT_CITY_ID` + `CODE` (+ grid decoration `PARENT_NAME`/`LEVEL`); `Controller\Adminhtml\City\Save` redirect bug fixed (`city_id` thay vì `region_id` trong nhánh edit — cần cho error UX AC-B2).
- Tests: 16 unit mới (`SaveValidatorTest` — self/cycle/broken-chain/cross-region/max-depth/duplicate/code-length/depth-coverage warnings) → suite 66 tests / 147 assertions xanh. QC end-to-end: `.ai/runtime/evidence/TASK-9EX975/qc_slice_b.php` (7/7 PASS) + GraphQL AC-B3 (`addressLocations` không trả node depth-2 dưới profile `vn_admin_2025`; BC shim `GetListCity` vẫn flat — documented TASK-6MKF0V AC-3).

## [Unreleased] — TASK-6MKF0V: sub_city layer removed (2026-09-03)
- **Breaking (pre-release surface)**: the entire sub_city layer is deleted — city hierarchy is served exclusively by the recursive `directory_region_city.parent_city_id` + Address Profile depth engine (FEAT-2PZQKJ). Removed: `Api\Data\SubCity*` contracts + Data models, `Command/SubCity`, `Mapper/SubCity*`, `Model\Customer\Address\Attribute\Source\SubCity`, `Config\Column\Selector\SubCity`, `Block\Form\SubCity*`, `Block\Adminhtml\SubCity`, `Block\Address\Field\SubCity`, admin `Controller/Adminhtml/SubCity`, `sub_city_listing` UI component, GraphQL `GetListSubCity` (field/input/type), the `sub_city` EAV attribute creator patch (applied — removal handled by the new `RemoveSubCityCustomerAttribute` data patch), and `Model\Constant::SUBCITY_CODE`.
- **DB schema**: `db_schema.xml` drops tables `directory_city_sub_city` + `directory_city_sub_city_name` and the `sub_city` columns on `quote_address` / `sales_order_address` / `customer_address_entity`. Whitelist note: the dropped objects must STAY listed in `db_schema_whitelist.json` for the drop to be registered (`Diff::canBeRegistered`), then the whitelist was regenerated to declarations-only after `setup:upgrade`. New data patch `Setup\Patch\Data\RemoveSubCityCustomerAttribute` removes the customer address EAV `sub_city` attribute (+ stored values) — the applied creator patch is never edited.
- **Frontend (Luma checkout + cart estimation + customer forms)**: sub_city selects/hidden inputs/validation and the `custom_attributes[sub_city]` / `extension_attributes.sub_city` payload fields removed from `shipping-address-dropdown.js`, `billing-address-dropdown.js`, `address-dropdown.js`, `shipping-estimation-mixin.js`, `checkout-data-resolver-mixin.js`, `shipping-save-processor` + `address-renderer` + `billing-address` mixins, and the `.html` templates. `set-shipping-information-mixin.js` / `set-billing-information-mixin.js` deleted (they existed only to inject the retired extension attribute). City cascade stops at the city level (`setupCityCascade` / `initializeCityCascadeElements`).
- **Hyvä customer form**: `templates/hyva/address/edit.phtml` — sub-city field + Alpine fetch/populate cascade removed; cascade region → city (VN depth handled by the profile engine).
- **Admin**: `config/address-city.js`, `form/provider-mixin.js`, grid `custom-provider.js`, `city.phtml` config renderer, `customer_address_form.xml` (`sub_city` field), `default-address.html` — sub_city plumbing removed; city grid View action (dead `addressdropdown/subcity/index` route) removed.
- **Server plumbing**: cart `LayoutProcessorPlugin` drops the `sub_city`/`custom_sub_city` fieldset attributes; import/export entity `address_dropdown` no longer carries sub_city columns; `Plugin\Customer\Address\Save` / `Plugin\Model\Shipping` / `AddressRendererPlugin` keep their non-sub_city logic only; `city-data` CustomerData section is region+city only.
- **i18n**: all sub-city strings removed from `en_US.csv`.

## [Unreleased] — TASK-ADT94K: hierarchy import slice (2026-08-27)
- **Generic code-identified import**: new `Api\HierarchyAddressImportInterface` + `Model\Import\Hierarchy\HierarchyImportService` (preference in `etc/di.xml`) — upserts regions by `(country_id, code)` and recursive city nodes by `(region_id, parent_city_id, code)` with explicit NULL-parent lookups (the composite unique index does not enforce uniqueness for depth-1). Order-independent (regions → depth-1 → worklist parent resolution by code, batch or pre-existing DB parents); per-region transaction chunks; parameterized queries; validation pass performs no writes; `HierarchyImportValidationException` carries row-level errors (STOP_ON_ERROR). Country adapters (e.g. `Secomm_VietNamAddress`) supply rows `{entity_type, region_code, code, parent_code, default_name, names{locale => name}}`. The legacy 9-column ImportExport entity (`address_dropdown`) is untouched.

## [Unreleased] — FEAT-2PZQKJ Phase 2 schema-driven Hyva renderer (2026-08-25)
- **TASK-3T3NSV**: new `hyva/address/schema-edit.phtml` — generic schema-driven customer address form. Region label/required/placeholder + every city level (count, label, placeholder, required, order) come from the resolved Address Profile via GraphQL `addressSchema`/`addressLocations`; unmapped country renders the native `city` text input. Zero country conditions, zero label-from-depth. Behind per-store flag `address/general/renderer` (`legacy` default | `schema`) — new `Model\OptionSource\RendererMode` + `Plugin\Frontend\CustomerAddressEditTemplate` (afterGetTemplate swap; no-op when module off / flag legacy).
- **GraphQL breaking (pre-release surface)**: `addressSchema` now returns wrapper `{profile_code, levels}` — the renderer needs the resolved code for subsequent `addressLocations` calls.
- **Submit contract (D2 applied, BC-first)**: selects are ID-canonical (`city_id`); the leaf select posts its `default_name` as `city` (validators/carriers keep working unchanged); canonical ids travel in `address_city_id` + `address_location_path` (JSON path) hidden inputs — available for TASK-YQSS3M consumers.
- Form-validation ported 1:1 from the legacy template (telephone, postcode+postCodeSpecs, VAT, street lines, per-country zip/region) — parity checklist 10/10.
- Default `legacy` via new `etc/config.xml` — no behaviour change until a store opts in.

## [Unreleased] — FEAT-2PZQKJ Phase 1 LocationHierarchyProvider + GraphQL (2026-08-25)
- **TASK-J49PRZ**: service contract `Api\LocationHierarchyProviderInterface` (getRootLocations / getChildLocations / hasChildren / getLocationPath) + DTO `Api\Data\LocationNodeInterface` (canonical `city_id`, locale-resolved name, depth, parent, region, structural has_children). Impl `Model\LocationHierarchyProvider`: single-statement listing queries (children + locale name JOIN + EXISTS has_children), membership per DEC-FEAT2PZQKJ-001 D5 — subtree-claim + nearest-entry inheritance + all-nodes BC mode for profiles without membership rows; bounded ancestor walks (cycle-guarded, MAX 16).
- **GraphQL mới**: `addressLocations(input: {region_id|parent_city_id, profile_code!})` + `addressSchema(input: {country_id!, profile_code})` — ID-canonical, cacheable, translated labels server-side (translation stays with the profile-declaring module). Resolvers validate input strictly (exactly one parent selector; unknown profile → GraphQlNoSuchEntityException; unmapped country → empty schema = native fallback).
- Resolvers cũ `GetListCity`/`GetListSubCity` giữ nguyên (BC shim ở TASK-YQSS3M).
- Tests: 12 unit mới (input validation + mapping shape) → suite 34 tests / 65 assertions xanh; integration smoke: depth-3 fixture, membership subtree vs node-only vs inheritance vs BC, EXPLAIN dùng cả 2 index (no full scan). See `.ai/runtime/evidence/TASK-J49PRZ/`.

## [Unreleased] — FEAT-2PZQKJ Phase 1 Address Profile / Schema engine (2026-08-25)
- **TASK-NW66H9 (DEC-FEAT2PZQKJ-001 D4: XML code-shipped profiles)**: new `etc/address_profiles.xml` config surface (XSD-validated, merged across modules by `<profile code>`), declaring Address Profiles + Address Schemas (`entity_type` region|city, `depth`, `label` translation key, `placeholder`, `sort_order`, `required`). Generic module ships only the `default` fallback profile with Magento-default labels (DEC-FEATJSZQV3-003); country adapters declare their own.
- Service contracts: `Api\AddressProfileResolverInterface` (config-driven `resolve(countryId, context)` — `address/profiles/mapping` store-scoped serialized map; unmapped/undeclared/corrupted config ⇒ native fallback + warning log, never an exception on the storefront path) + `Api\AddressSchemaProviderInterface` (`getSchema(profileCode)` sorted by `sort_order`, stable) + `Api\NoSuchProfileException` + DTOs `AddressProfileInterface` / `SchemaLevelInterface`.
- Internal: `Model\Profile\{Config\{SchemaLocator,Converter},ProfilePool}` (memoized, loud on config faults — duplicate (entity_type, depth) slot or city depth < 1 throws).
- Admin: system config group `Address → Profiles → Country to Profile Mapping` (serialized array backend).
- i18n: 2 new admin labels in `en_US.csv` (VN labels stay in Secomm_VietNamAddress per DEC-8).
- Tests: 20 new unit tests (converter defaults/faults, pool DTO mapping, resolver fallback paths incl. corrupted serialized config, schema sort + stability). See `.ai/runtime/evidence/TASK-NW66H9/`.

## [Unreleased] — FEAT-2PZQKJ Phase 1 recursive hierarchy schema (2026-08-25)
- **TASK-9AEAQQ (additive, no behavior change)**: `directory_region_city` + `parent_city_id` (NULL = root below region, self-FK `ON DELETE CASCADE`), + `code` VARCHAR(64) (stable identifier, D6), UNIQUE `(region_id, parent_city_id, code)`, index `(region_id, parent_city_id)` + `(parent_city_id)` — nền recursive hierarchy theo DEC-FEAT2PZQKJ-001.
- New table `secomm_address_profile_location` (profile membership, subtree-claim semantics D5): PK `(profile_code, location_type, location_id)` + index `(location_type, location_id)`; `location_id` polymorphic — deliberately no FK; `profile_code` references XML-declared profiles.
- `db_schema_whitelist.json` synced (regenerated + trimmed to actual DB object names).
- Verified: `setup:upgrade` clean; smoke tests (nested insert / unique / NULL-code semantics / cascade delete / membership PK) pass; 3.313 existing rows untouched; module unit tests green. See `.ai/runtime/evidence/TASK-9AEAQQ/`.

## [Unreleased] — SL-001 dual-theme + i18n + en_VN (2026-07-17)
- **Dual-theme support (DEC-9)**: module renders both Luma (jQuery/requirejs) + Hyva (Alpine + GraphQL) via `hyva_` layout handle (`hyva_customer_address_form.xml` → `templates/hyva/address/edit.phtml`). No PHP theme detection.
- **Hyva customer form**: `templates/hyva/address/edit.phtml` (Alpine `initCustomerAddressEdit()` + `fetch('/graphql')` cascade, Tailwind v4).
- **Luma customer form**: restored (`templates/address/edit.phtml` + `web/js/address-dropdown.js`, requirejs `directoryAddressDropdownUpdater`).
- **Cart estimation**: Luma Knockout mixin restored (Hyva = native `php-cart`, region-based).
- **i18n (DEC-8 boundary)**: generic module emits `__()` default keys (`City`, `State/Province`, `Sub-City`, placeholders); VN labels via `Secomm_VietNamAddress` (`en_VN`/`vi_VN`). Generic `i18n/en_US.csv` no longer defines storefront VN labels.
- **Ahamave cleanup (DEC-8)**: removed dead `Secomm_Ahamove` requirejs reference (project-leak) + orphan `default-mixin.js`.
- **VN 3-tier**: city = phường/xã (commune); sub_city auto-hidden (matches 3-tier VN data).

## 1.0.0 (first released)
- Apply new address structure for:
  + Customer address form.
  + Order address information in Frontend and Backend.
  + Checkout shipping form, billing form.
  + Checkout render address saved.
  + Render address in email
- View the address list in Backend

## [Unreleased] — TASK-Z6SK3T: fix sort Đ/đ rơi đáy list (2026-10-02)
- **Regression fix**: `d8bad508` (TASK-7HVGAB, 2026-09-07) xoá REPLACE normalize Đ/đ khỏi
  ORDER BY của `LocationHierarchyProvider` ("language-agnostic, collation-owned") — trên
  baseline `utf8mb4_general_ci`, Đ (U+0110) sort sau Z → mọi mục `Đ...` rơi đáy option list
  trên các surface không có client sort (address book schema-edit, Hyvä cart, admin cascade).
  Checkout OSC/default không lộ nhờ client `vnSortKey` (Target §8) — giữ nguyên như
  belt-and-braces.
- **Fix**: builder dùng chung mới `Model/ResourceModel/CitySort::expression()` áp đồng bộ
  **4 điểm sort**: `LocationHierarchyProvider::fetchChildren` (canonical addressLocations +
  shim GetListCity), `CityLocaleCollection::_initSelect`, `CustomerData\CityData`,
  `Helper\Address::getCityData()` — hết drift giữa 2 engine.
- **Semantics sort (user decision 2026-10-02, chốt sau 1 vòng)**: dùng **collation tiếng Việt
  thật của MySQL 8** (`utf8mb4_vi_0900_ai_ci`, require MySQL 8.0+ — verified 8.4.7) thay vì
  REPLACE Đ→D: **Đ là chữ số riêng sau TOÀN BỘ khối D** (đúng thứ tự chữ cái Việt …D, Đ, E…;
  tone marks fold qua ai_ci). Bản REPLACE (Đ interleave trong khối D) đã thử và bị thay sau
  khi user yêu cầu "D xong mới tới Đ".
- Verify: region 1205 (168 wards) — invariants PASS (trước Đ chỉ A-D; khối Đ liên tục;
  sau Đ không còn D/Đ): `Dầu Tiếng, Dĩ An, Diên Hồng → [Đất Đỏ…Đức Nhuận] → Gia Định…`;
  addressLocations ≡ shim GetListCity. Guard tests d8bad508 ("language-agnostic") thay bằng
  guard vi-collation; suites 110 + 202 green. Đảo chiều một phần quyết định TASK-7HVGAB.
