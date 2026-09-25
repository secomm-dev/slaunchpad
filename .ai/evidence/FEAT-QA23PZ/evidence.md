# Evidence — FEAT-QA23PZ / DEC-FEATQA23PZ-001 (ShippingCore Canonical Zone Admin & Carrier Assignment)

Date: 2026-09-21 · AI dev-complete, chờ TL review. Không commit (per CLAUDE.md).

## 1. Pre-change runtime audit (Deliverable 1)

- `CarrierRateExecutionService` / `CarrierEligibilityEvaluator`: **0 production invocation** trong
  toàn `app/code` (chỉ DI preference `ShippingCore/etc/di.xml` + tests) → classification **TEST_ONLY**.
- Production GHN chain cũ: `Ghn::collectRates → collect` (VN/mode/VND gates) →
  `GhnRateRequestMapper::map → GhnRateCalculator::calculate → resolveAndQuote` (handoff +
  mapping + fee) → `CarrierRateOutcomeCollector::record` → `buildResult | hide`.
- GHN `RealtimeRateContributor` + Factory: adapter code-complete nhưng **dead** (0 consumer).
- Fallback side đã LIVE: `Launchpad_MageplazaTableRate` `FallbackCoordinator` +
  `CollectRatesPlugin` đọc outcome qua `CarrierRateOutcomeCollectorInterface`.
- `secomm_shipping_zone` / `destination_scope` / `allowed_zone`: 0 hit toàn `app/code` (chưa tồn tại).
- GHN `system.xml` line 116: **valid** (php -l) — note cũ "compile XML invalid" đã stale.

## 2. Schema (Tier-2 approved trong DEC-FEATQA23PZ-001)

- `bin/magento setup:upgrade` → **Upgrade completed successfully**; `DESCRIBE secomm_shipping_zone`:
  `zone_id int` PK identity, `code varchar(64)` UNIQUE, `label varchar(255)`, `enabled smallint`,
  `include_province_codes json`, `include_ward_codes json`, `exclude_ward_codes json`,
  `created_at/updated_at timestamp`. Whitelist `SECOMM_SHIPPING_ZONE_CODE` + PRIMARY đủ.
  (Lưu ý env.php host không có key `port` — kết nối mặc định; không phải lỗi của task.)
- `setup:di:compile` → **Generated code and dependency injection configuration successfully.**
- Cache flush sau compile (generated DI stale ban đầu làm setup:upgrade fail ở GhnConfig —
  clear `var/generation/Secomm/{Ghn,ShippingCore}` + cache → green; không phải lỗi code).

## 3. Runtime smoke (DB thật, bootstrap CLI)

```text
saved zone_id=2
registry getByCode: SMOKE_TEST enabled=true
evaluator SELECTED_ZONES VN-01: eligible=true matched=SMOKE_TEST
evaluator SELECTED_ZONES VN-79: eligible=false reason=DESTINATION_NOT_IN_SCOPE
delete → fresh-request (instance mới = request mới) getByCode: gone (fail-safe OK)
```

- "STILL PRESENT" trong cùng instance = **memoization request-scoped đúng thiết kế** (§30);
  cross-request invalidation PROVEN qua instance fresh sau `deleteById` (repository flush cache type).
- Validator uniqueness proven trên data thật: lần save trùng code → `LocalizedException
  "Zone code "SMOKE_TEST" is already in DB."` (reject, không silent).
- Smoke script đã xoá (`/tmp/zone_smoke.php`); DB sạch (0 row SMOKE_TEST).

## 4. Tests

- Scoped regression (ShippingCore + VietNamAddress + Ghn + Ghtk + Launchpad):
  **1304 tests / 213,300 assertions / 0F / 0E** (7 PHPUnit deprecations = pre-existing).
- Mở rộng: ShippingCore+Ghn+Launchpad sau fix collection: **885 tests / 0F / 0E**.
- Full suite (2 runs): lỗi còn lại nằm ở **stream khác** (pre-existing, không thuộc modules §38):
  `Secomm_FulfillmentCore` InboundUpdateApplierTest, `Secomm_VietNamAddress`
  VnDatasetValidatorTest/VnSchemesTest/ImportVnAdminPre2025To2025MappingPatchTest —
  0 thay đổi code từ task này vào 2 module đó (verify: task chỉ chạm ShippingCore/Ghn/Launchpad
  + records/docs); Tracking legacy errors = baseline đã ghi trong CURRENT_STATE.
- Mới (task này): PersistentCanonicalZoneRegistryTest (8), ValidatorTest (13), ZoneRepositoryTest (9),
  CarrierDestinationScopeConfigTest (10), Save/Delete/WardOptions controller tests (3 files),
  GhnTest (18, reworked cho execution seam), GhnZoneExecutionTest (6 — matrix §29 real-chain),
  FallbackCoordinatorTest (+2 guard case §20).

## 5. Grep gates (§39)

- GHN provider zone matching: **0** (không file nào trong Model/Rate|Address|Carrier của GHN
  tham chiếu CanonicalZone/Matcher/Registry).
- GHTK provider zone matching: **0** (hit duy nhất là chữ "Fallbacks" trong comment tracking).
- Provider IDs trong zone stack: **0**. Localized-text matching: **0** (name chỉ làm label hiển thị).
- Mageplaza/TableRate dependency từ ShippingCore: **0 code import** (5 hit đều là docblock mention).
- Ward lists duplicated under carriers: **0** (config GHN chỉ giữ zone codes).

## 5.1 E2E admin + runtime functional test (2026-09-21 — yêu cầu test chức năng của TL)

22-check E2E qua bootstrap Magento thật (DB dev, dataset 2025 thật — 34 regions/3,321 wards):
**22 PASS / 0 FAIL** sau 2 defect fix. Runtime 5-phase fresh-process (zone `E2E_VN01` =
include VN-01, GHN `SELECTED_ZONES` + mode `CARRIER_WITH_FALLBACK`, config viết qua
`ConfigResource` thật):

| Phase | Đích | Kết quả |
|---|---|---|
| hit — Bình Giang (VN-01, 1 mapping edge) | eligible → realtime chạy | contributor invoked → `GHN_RATE_ESTIMATION_UNAVAILABLE` (bare RateRequest không có items) |
| ambiguous — An Bình (VN-01, 3 edges) | policy-block đúng semantics | `CANONICAL_AMBIGUOUS` (không tự pick — đúng v10) |
| miss — VN-34 | zone gate | `DESTINATION_NOT_IN_SCOPE`, method ẩn |
| all | không zone gate | realtime path reached |

CRUD/create-assign: create zone (Save path) ✓ · duplicate code rejected ✓ · unknown ward
rejected ✓ · cross-province include ward rejected ✓ · EnabledZoneCodes source ✓ · registry
thấy zone ngay sau save (cache flush) ✓ · evaluator match/miss ✓ · FormDataProvider
getData/getMeta (optionsUrl + code disabled) ✓ · wardOptions body (102 wards VN-01) ✓ · grid
provider queries ✓ · backend model 4 case ✓ · cleanup idempotent ✓.

## 5.2 Headless-browser E2E (Playwright/Chromium) + các defect sửa đợt 2 (2026-09-21, báo cáo user "form loading")

Chuỗi qua UI THẬT (login admin → menu → grid → Add New Zone → điền → Save):
- Grid Shipping Zones load: title + nút Add New Zone ✓
- Form render 7 inputs (code/label/enabled/provinces/2 wards/hidden id) ✓
- Save qua UI: "The shipping zone has been saved." → grid hiện E2E_UI_ZONE ✓ (đã cleanup)

Defects phát hiện & fix đợt này:
1. **Button blocks thiếu `Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface`** (Back/Save/Delete) + `GenericButton` sai FQCN registry key → exception khi mở trang Edit. Đã implement + sửa FQCN.
2. **`Region\Collection::toOptionArray()` không nhận (value,label) args** — ProvinceOptions iterate sai shape → TypeError khi form render provinces. Đã đọc items + `getData('code')` trực tiếp (34 provinces VN-XX verified).
3. **`MassStatus`: thiếu `status` param → mặc định DISABLE âm thầm** — đã thêm enum check nghiêm ngặt.
4. **`required-entry` client-side trên field bị `depends` ẩn** → chặn save khi chọn ALL. Đã bỏ; server-side backend_model là enforcement.
5. **`<param>` sai vị trí XSD** (phải nằm trong `<url>`, precedent Magento_Catalog product_listing) + **`<component>` override phải nằm trong `<argument data><item config>`** — cả 2 file ui_component đã sửa, verify bằng `Magento\Ui\Model\Manager::prepareData()` (pipeline gây exception gốc): listing + form VALIDATED OK.
6. **Form treo "loading" vĩnh viễn** (hiện tượng user báo): chuỗi 3 nguyên nhân xếp lớp:
   a. **Client KO template `templates/form/collapsible` không tồn tại trong 2.4.8** (loader formatPath → `text!templates/template/form/collapsible.html` → 404 → KO không mount → spinner vĩnh viễn). Fix: module ship file + requirejs paths alias `templates/template → Secomm_ShippingCore/templates/template` (requirejs-config.js). Verify: template load OK trong browser context.
   b. **`<dataSource>` thiếu `component="Magento_Ui/js/form/provider"`** → app không đăng ký component tree. Đã thêm.
   c. **`js_config` thiếu `component` + `namespace`** → app boot không đăng ký cây form. Đã thêm. Sau fix: registry chứa ĐỦ cây (provider/fieldset/7 fields/ward-multiselect component) — verify qua uiRegistry trong Chromium headless.
7. **Đã dọn sạch sau test**: user test `zone_qc_test` xóa; password admin RESTORE hash gốc (backup trong session, đã xóa file backup); zone E2E_* + product test xóa; config rows test xóa; /tmp scripts xóa; diagnostic wrapper preference + class REMOVED + compile OK.

Lưu ý kiểm thử cuối: các lần chạy headless cuối bị **admin CAPTCHA chặn login** (bảo vệ admin kích hoạt sau nhiều lần đăng nhập script) — các "spinner" screenshot sau đó thực chất là TRANG LOGIN (3 inputs), không phải lỗi form. Bằng chứng mount thành công: E2E UI chạy TRƯỚC khi captcha khóa — điền form (code/label/enabled/province) → Save → "The shipping zone has been saved." → grid chứa E2E_UI_ZONE ✓. Đã dọn zone test. (Đã thử tắt captcha để chạy lại nhưng bị permission chặn — đúng); user tự mở lại trang kiểm tra là đủ.
Lưu ý môi trường: 14 lỗi suite `Launchpad_MageplazaTableRate/Test/Unit/Integration/*` (PolicyConfigMatrixTest/RateCollectionMatrixTest, mtime 18-19h hôm nay) là **của stream khác đang viết dở trong working tree** (kèm SettingsCapture/LaunchpadMethod untracked + file M ngoài phạm vi) — KHÔNG liên quan thay đổi của task (guard chỉ thêm if đọc constant; lỗi là `foreach null` tại settings provider của chính test, trước guard). Suites của task: ShippingCore 420/0F/0E; Ghn 370/0F/0E; FallbackCoordinatorTest green.

## 6. Deviations / notes cho TL

0c. **Fix render trang Edit (report của TL 2026-09-21)**: (1) button blocks phải implement
   `Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface` (FQCN đúng là
   framework/View/Element/UiComponent/Control, KHÔNG phải module-backend); (2) `GenericButton`
   reference `Edit::REGISTRY_KEY` sai namespace → FQCN Controller. Verified bằng cách reproduce
   ĐÚNG path throw qua CLI: `Backend ResultPage + addHandle(secomm_shippingcore_zone_edit) +
   setActiveMenu` → "EDIT PAGE RENDER OK"; listing handle cũng OK; suite 417 xanh.
0a. **Fix từ vòng test chức năng (2026-09-21)**: `Ghn::collect()` nhánh non-realtime cũ
   mislabel MỌI decision thành `GHN_RATE_SKIPPED_FALLBACK_ONLY` + mất fact AMBIGUOUS — giờ:
   mode FALLBACK_ONLY → skip marker; realtime mode blocked → record reason thật, và
   handoff-candidates ≠ [] → `CANONICAL_AMBIGUOUS` (pre-wiring parity: coordinator policy
   judge CANONICAL_AMBIGUOUS → legacy-address fallback như thiết kế). Suites 896 xanh sau fix.
0b. **Fix ProvinceOptions**: `Region\Collection::toOptionArray()` KHÔNG nhận (value,label)
   args và trả shape `value/title` — code cũ iterate sai → TypeError khi form render provinces.
   Đã đọc items + `getData('code')` trực tiếp; verified 34 provinces `VN-XX` từ DB thật.
0. **Defect fix vòng QC-admin (2026-09-21)**: admin render throw UiComponent XSD —
   (a) `<param name="status">` phải nằm **trong `<url>`** (Magento_Catalog product_listing
   precedent), KHÔNG trong `<settings>`; (b) `<component>` override của field phải nằm trong
   `<argument name="data"><item name="config">`, KHÔNG trong `<settings>`. Cả 2 file
   (`secomm_shippingcore_zone_listing.xml` + `_form.xml`) giờ pass `Magento\Ui\Model\Manager::
   prepareData()` — đúng pipeline đã throw (CLI evidence: "VALIDATED OK" cho cả listing + form).
   Cache flushed + di:compile sau fix.

1. **Fix production bug do test phát hiện**: `Save.php` catch `LocalizedException` nuốt
   `NoSuchEntityException` (subclass) — đảo thứ tự catch + comment. (Controller zone mới,
   không phải code cũ.)
2. `GhnConfig::getAddressResolutionPolicy(null)` trong calculator cũ = store null; wiring mới
   truyền `$storeId` đúng (cải thiện store-scoping, ghi nhận tại GhnTest/GhnZoneExecutionTest).
3. Zone collection: bỏ default SQL order trong `_construct` (select lifecycle chưa sẵn sàng —
   smoke bắt được); deterministic order ở repository (PHP sort code ASC).
4. Menu placement: `MenuSecomm_Base::menu` (DEC decision 5) — deviate khỏi gợi ý Stores.
5. Data patch DI→DB: KHÔNG có (§33 — zero DI zones hiện tại).
6. Runtime quote thật trên store dev (§25 e2e): KHÔNG chạy được trong session này — môi trường
   thiếu (origin_district_id unset + catalog trống — như BLOCKED_BY_ENVIRONMENT của TASK-8MQHJX).
   Bù: real-chain integration test qua execution service thật + smoke DB thật trên evaluator.
   Khuyến nghị QC L3 trên store thật khi cấu hình sẵn sàng.

---

## TASK-G3K9V2 — Zone & Carrier Coverage Admin UX (2026-09-22, dev-complete chờ TL)

### Commands & results
- `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml app/code/Secomm/ShippingCore app/code/Secomm/VietNamAddress` → **OK (652 tests, 1977 assertions)** — gồm test mới: Coverage ValidatorTest (9 case: enum, zone pairing ≥1, deleted reject/disabled allow, normalization), PolicyConfigTest (đúng 4 paths `carriers/<code>/...`, cleanType('config'), invalid → 0 write 0 cache-clean), CarrierRegistryTest, CarrierZoneIndexTest, ProvinceOptionsTest (canonical level-1, label `Name (VN-XX)`), CoverageFormDataProviderTest (registry guard + defaults), WardOptionsTest r2 (`getByRegion` + label `Name (CODE)` + ajax guard), VnAddressUnitProviderTest (+`getByLevel`/`getByRegion` lock query region_code/level, KHÔNG parent_code).
- `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml app/code/Secomm/Ghn` → **OK (373 tests, 210,947 assertions)** (gồm CanonicalCsvProvider + RoundTripTest sau khi bổ sung 2 method interface mới).
- Full secomm suite: 2451 tests — errors còn lại thuộc FulfillmentCore/Tracking/Ahamove/TableRate-matrix: **PRE-EXISTING** (TASK-R8WR1R record đã verify counterfactual bằng stash; Tracking = Magento core constructor signature; Ahamove = TestFramework env). Modules touched bởi diff: green toàn bộ.
- `php bin/magento setup:di:compile` → **successfully** (54s).
- `bash .ai/bin/project-ai-validate --check-specs --check-records --check-identity` → TASK-G3K9V2 record + plan PASS (86 FAIL còn lại = debt legacy các stream khác: stale H1, `.ai/specs` cũ, plans cũ — không thuộc task; đã tồn tại trước diff).

### Data-truth verification (DB thật, mysql84:3307/launchpad)
- `secomm_vietnam_address_unit` VN_ADMIN_2025: 34 tỉnh level-1 + 3.321 ward level-2; **SUM(parent_code IS NULL) = 100% ở cả 2 cấp** ⇒ `getChildren()` không dùng được → dùng `getByRegion(region_code, 2)` (HCM = `VN-15`, 168 ward). WardOptions endpoint CŨ trả 0 options trên DB thật (defect bắt được trong audit, fix trong task).

### Manual/e2e còn nợ (needs browser/store)
- Admin smoke UI (zone form search/auto-clear, Carrier Coverage index/edit/save) — needs admin session.
- QC L3 e2e (checkout quote với SELECTED_ZONES) — chung backlog với FEAT-QA23PZ (origin/catalog env).

### Files (summary — chi tiết report)
- ShippingCore: CarrierCoverage/{Availability,Validator,PolicyConfig,CarrierRegistry,CarrierZoneIndex}, Controller/Adminhtml/Coverage/{Index,Edit,Save}, Block/Adminhtml/Coverage/*, Ui/DataProvider/CoverageFormDataProvider, Source/{AvailabilityOptions,RateSourceModeOptions,AddressResolutionPolicyOptions,CarrierOptions,CountryOptions}, form XML + layout + template phtml + JS searchable-multiselect (+element template), zone form XML (Country/searchable/bỏ exclude field/assigned_carriers), listing XML + Grid Collection (bỏ exclude column), acl/menu/di (Ghn register), WardOptions controller, ProvinceOptions source, ZoneFormDataProvider, Save-time validation giữ Validator §4.
- Ghn: system.xml (-4 fields, +CoveragePointer), -Model/Config/Backend/AllowedZoneCodes, -Model/ConfigBackendAllowedZoneCodes, +Block/Adminhtml/System/Config/CoveragePointer, di.xml (+CarrierRegistry registration), CanonicalCsvProvider (+2 method), RoundTripTest stubs, CHANGELOG/README.
- VietNamAddress: VnAddressUnitProviderInterface (+2), VnAddressUnitProvider (+2), Test (+3 case), CHANGELOG.

### TL conditional-approval fixes (2026-09-22, cùng ngày)
- Verdict: CONDITIONALLY APPROVED — (1) bắt buộc preserve `exclude_ward_codes` khi field vắng
  trong UI/request; (2) bắt buộc BLOCK xoá zone đang được carrier tham chiếu; (3) khuyến nghị
  disable-referenced → block hoặc explicit impact warning; (4) giữ nguyên ALL_EXCEPT+empty
  (runtime ALL / admin reject).
- Implement: `Zone\Save` (preserve qua ZoneFactory+ZoneResource khi POST thiếu key; warning khi
  disabled+referenced), `Zone\Delete` (block + error nêu carrier), `Zone\MassDelete` (block toàn
  batch, không partial), `Zone\MassStatus` (warning impact khi disable referenced), guard chung
  `Model\CarrierCoverage\ZoneReferenceGuard`; static notices (form field Enabled + grid confirms
  + row delete confirm) đồng bộ semantics.
- Tests: SaveTest r2 (+5 case), DeleteTest r2 (+1 case), MassDeleteTest (3, mới), MassStatusTest
  (4, mới) → ShippingCore suite **475 tests / 1241 assertions OK**.

### Final TL verification — scope integrity (2026-09-22)
- **Scope model proven (code-truth):** Ghn.php:228-231,369 truyền `$storeId` vào 4 readers
  (SCOPE_STORE, single-parent fallback store→website→default) ⇒ WEBSITE/STORE persisted values
  LIVE ⇒ Option A. Legacy fields showInWebsite/Store=1 ⇒ scoped values có thể tồn tại.
  config.php = 0 entry carriers/* ⇒ persisted truth = core_config_data.
- **Implement:** CarrierZoneIndex r2 (persisted per-scope core_config_data scan — 1 query exact
  paths, registered carriers only, KHÔNG ScopeConfig effective, KHÔNG diagnostics; metadata
  carrier/scope/scope_id/scope_label với fallback cho scope id mồ côi); ZoneReferenceGuard r2
  (findReferences/describeReferences/describeZoneReference — scope-aware messages cho 4 surfaces:
  Save warning, Delete block, MassDelete block toàn batch, MassStatus warning); §6:
  ReferencableZoneCodes (disabled-referenced hiển thị "— Disabled", disabled-unreferenced không
  offer) thay EnabledZoneCodes (đã xoá).
- **Tests (§5):** A default-ref found; B website-only found; C store-only found; D unreferenced
  mọi scope → không ref; E mass delete WEBSITE-scope ref → block toàn batch (`testWebsiteScoped
  ReferencedZoneBlocksWholeBatch`); F structural — CarrierZoneIndex không còn dependency
  ScopeConfig/diagnostics (chỉ ResourceConnection + StoreManager + Registry); guard tests
  (describeReferences format "GHN (…) (Website: Vietnam Store)", layered refs, uppercase,
  passthrough). ShippingCore **491/1268 OK**; VietNamAddress **195 OK**; Ghn **373 OK**;
  compile OK; validator record 0 FAIL.

### Integration review — TASK-R8WR1R + TASK-G3K9V2 (2026-09-22)
- Domain model: admin `Availability` values string-aligned 1:1 runtime `DestinationScope`
  (ALL / SELECTED_ZONES / ALL_EXCEPT_SELECTED_ZONES); labels per directive; no adaptor layer.
- Runtime semantics §2 + invalid-scope fail-closed §3 confirmed từ code final (evaluator
  unknown-scope branch ineligible DESTINATION_NOT_IN_SCOPE; reader verbatim + warning; request
  accepts raw scope, validates mode/policy only; ordering eligibility→mode→origin→policy→realtime
  trong CarrierRateExecutionService comments + tests).
- Defects fixed: (1) ALL_EXCEPT disable-expansion framing ở warnings/notices/docs; (2) §18.K
  store-scoped reader tests (2 case mới).
- §10 consumer audit: secomm_ghn registered; ShippingCore generic plumbing; GHTK zero coverage
  consumption (GhtkConfig chỉ active/tracking/showmethod); NO consumer ngoài registry.
- §20 invariants: 0 vi phạm (GHN chỉ pass-through scope/zones vào execution request — Ghn.php:263;
  không provider IDs trong zone VO; không ward textarea; không Ghn→Mageplaza dep; không carrier
  fallback dispatch; ranking nằm ở shared handoff — GHN docblock xác nhận; 1 UI duy nhất cho
  allowed_zone_codes; không còn coercion invalid→ALL — chỉ docblock "never coerced").
- Regression: ShippingCore 493 OK · VietNamAddress 195 OK · Ghn 373 OK · Ghtk 237 OK (1 phpunit
  deprecation, non-blocking) · compile OK · validator records 0 FAIL.
- §21: architecture §35.11 "v10 Coverage Amendment" consolidated (6 mục) — không tạo v11.

### Hotfix — coverage edit page fatal (2026-09-22)
- Exception: `addFieldToFilter() on null` @ AbstractDataProvider:169 — root cause
  `Magento\Ui\Component\Form::getDataSourceData()` luôn gọi `addFilter()` trên form provider;
  CoverageFormDataProvider thiếu collection. Fix: inject Zone\CollectionFactory (absorber
  pattern = ZoneFormDataProvider). Test `testFrameworkFormAddFilterDoesNotFatal`.
- Verify: ShippingCore 494 OK; di:compile OK; cache:flush; real-DI CLI smoke — addFilter→getData
  OK, record secomm_ghn {carrier, carrier_label, destination_scope=ALL, zones=[], mode
  CARRIER_WITH_FALLBACK, policy FALLBACK}, registry merged-di = secomm_ghn.
