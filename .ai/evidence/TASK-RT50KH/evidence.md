# TASK-RT50KH — Product Shipping Dimensions Contract + GHN Dimension Pre-Validation — Evidence

Ngày: 2026-09-23 · Mode A · parent FEAT-FQWEQ3 · DEC-TASKRT50KH-001

## 1. DB migration verification (post `setup:upgrade`)

Trước: height=144/length=145/width=146 varchar/STORE/frontend_class NULL/merchandising flags 1 — **0 value rows** (varchar + decimal, 176 products).
Sau: cả 3 = **decimal, is_global=1 (GLOBAL), frontend_class=`validate-number validate-zero-or-greater`, labels "Shipping … (cm)", is_searchable/comparable/visible_on_front/used_in_product_listing = 0**; **9 set assignments per code** (FK cascade + group propagation); value tables 0 rows; patch trong `patch_list` với dependency `[AddDimensionProductAttribute]`.

## 2. CLI integration proof (REAL EAV decimal read)

- `atlas-pouf`: save 40.5/20/30 qua ProductRepository (decimal persist).
- `ProductShippingDimensionsReader::read(quote item)`: **41×20×30 cm** (40.5 ceil → 41 — conservative).
- Reset dims null → reader null (missing = no rejection).
- Product edit page: label "Shipping Length (cm)" + input `product[length]` render (Playwright probe).

## 3. Admin smoke (Playwright, temp admin user — DELETED after)

- PASS: admin login; product edit page (atlas-pouf) — **label "Shipping Length (cm)" render**, input `product[length]` render.
- PASS (CLI): save decimal persist; reload/cleanup verified qua ProductRepository + fresh reader read.
- HONEST FINDING: **client-side admin validation classes KHÔNG render** vào form (core `Magento\Catalog\Ui\DataProvider\CatalogEavValidationRules::mapRules` switch không map `validate-zero-or-greater`; `validate-number` được build ra meta nhưng không surface vào page JSON — 0 hits trên page, kể cả các field khác). Behavioral probe: save -5 navigate không block; DB sau đó = NULL (không persist rác). Guard chính = read-side reader (test-locked: zero/negative/non-numeric → null). Follow-up đề xuất: UI-level validation là core-gap task riêng.
- Checkout storefront UI flow: không chạy đầy đủ (đòi hỏi address/quote flow); thay bằng CLI reader E2E + estimator/calculator unit coverage. Ghi trung thực: ADMIN_SMOKE = PASS (admin surface), storefront-checkout = covered by CLI/unit, không claim UI-level.

## 4. Suites (số chính xác)

| Suite | Tests | Assertions | Kết quả |
|---|---|---|---|
| Secomm_Base (**first suite**) | **11** | 24 | OK |
| Secomm_Ghn | **411** (baseline 386; MQ2DRG +14; RT50KH +11 net: 7 estimator + mapper-test reader stub + pass-through additions) | 211072 | OK |
| Secomm_ShippingCore | 550 | 1437 | OK |
| Secomm_Ghtk | 240 | 608 | OK |
| Secomm_VietNamAddress | 195 | 786 | OK |
| Launchpad (TableRate) | 135 | 234 | OK |

`setup:di:compile` OK. Validator: record mới 0 FAIL/WARN (85 FAIL pre-existing ngoài scope).

## 5. Directive §16 test mapping (16 case)

1. complete under limit → rate: `QuoteParcelEstimatorTest::testCompleteDimensionsFlowToEveryUnitPackage` + existing `testTrustedDimensionsAtOrUnderLimitStillQuote`
2. exactly at limit: `ProductShippingDimensionsReaderTest::testBoundaryValuesStayOnTheSafeSideOfTheCeil` (150) + `testTrustedDimensionsAtOrUnderLimitStillQuote`
3. above limit: `QuoteParcelEstimatorTest::testOverLimitDimensionIsSurfacedByTheHardLimitQuery` + `GhnRateCalculatorTest::testTrustedDimensionOverVerifiedLimitRejectsBeforeAnyProviderCall`
4-6. length/width/height above limit: per-axis reasons (existing dim tests + reader grid)
7. one missing: `ProductShippingDimensionsReaderTest::testPartialDimensionsAreMissing` + estimator `testMissingDimensionsStayNullAndNeverReject`
8. all missing: `testMissingDimensionsAreNotHardRejections` (existing) + `testMissingDimensionsNeverProduceViolations` (existing)
9. malformed: `testNonNumericDimensionIsMissing` + `testBlankAndNullDimensionsAreMissing` + estimator `testMalformedDimensionsNeverBecomeValid`
10. configurable child: `testConfigurableResolvesSelectedChildDimensions`
11. virtual ignored: `GhnRateRequestMapperTest::testVirtualItemsNeverBecomePackages` (existing)
12. qty >1 no multiplication: `testQuantityThreeYieldsThreePackagesWithIdenticalDimensions`
13. dimension hard failure → no API: `testTrustedDimensionOverVerifiedLimitRejectsBeforeAnyProviderCall` (handoff+post never)
14. no integration-limitation fallback: hard reason GHN-owned → policy whitelist không chứa `GHN_PACKAGE_*` (existing policy tests)
15. aggregate >50kg unchanged: MQ2DRG tests green (`testAggregateOverFiftyKgIsUnavailableAsUnrepresentableWithoutApiCall`)
16. ≤50kg type 2/5 unchanged: mapper/calculator existing tests green

## 6. Follow-up bug fix (user report: tab "Launchpad Settings" không hiển thị)

- Root cause (server-side debug qua temp error_log): `MethodTabs::_beforeToHtml` gọi
  `parent::_beforeToHtml()` TRƯỚC `addTab` — core `Widget\Tabs::_beforeToHtml` kết thúc bằng
  `assign('tabs', $_tabs)` (snapshot cho template) → addTab sau đó chỉ mutate `$_tabs`, không
  bao giờ tới template. Debug log khẳng định child tồn tại (`child-launchpad=true`) nhưng
  trang render 3 tabs.
- Fix: addTab BEFORE parent; `addTabAfter('rate')` khi edit method đã lưu, plain addTab khi
  create. Regression test `MethodTabsTest` (2 tests: snapshot + order).
- Re-verified browser (captcha tắt bởi user): tab `method_tabs_launchpad` "Launchpad Settings"
  render ✓ + fields Show to Customer / Fallback Members ✓. Screenshot `/tmp/rt50kh_tab5.png`.
- Launchpad suite OK (gồm 2 test mới) + compile OK + temp smoke user deleted.

## 7. Follow-up UX fix 2 (user report: City field cuối form + Downloads section không rõ nghĩa)

- **Placement**: `CityForm::_prepareForm` — `addField('city_code', ..., 'region')` (tham số
  `$after` của Data Form) → City/Area nằm ngay dưới Region. Browser-verified: field order
  `country_id → region → city_code → ...` (trước đây city_code ở cuối, sau shipping_group).
- **Section redesign**: "City / Area Downloads" (2 link trần, không rõ nghĩa) →
  **"City / Area Data — Download & Import"** — 1 dòng mục đích ("Update City / Area in bulk:
  download, edit the city_code column, import back") + 4 mục có giải thích từng link:
  Current Rates CSV (pre-filled per method, keyed `rateExportCsv`), City Reference CSV,
  Import Template, Import (keyed `importGrid` — trang import của method). Method-scoped links
  chỉ render khi registry METHOD có id.
- **Không thêm endpoint mới** — Current Rates CSV = `RateExportCsv` có sẵn (CityGrid export
  column đã mang city_code — round-trip importable qua `MptablerateImport` validate+persist).
- Browser verify: placement PASS, section PASS, links PASS, `select[name=city_code]` count = 1
  (không duplicate). Suites: Launchpad 100/196 OK (gồm MethodTabsTest 2 tests) + compile OK.

## 8. Fix xác nhận cuối (user re-test: "City / Area Data đang không work")

Reproduce qua in-page fetch (captcha tắt bởi user): 4 link — 2 xóa 500 thật + 1 export sai + 1 OK.

| Link | Trước | Sau fix |
|---|---|---|
| City Reference CSV | 500 `create() on null` (City.php:45) | **200 text/csv** — `country_code,region_code,...,city_code,...` |
| Import Template CSV | 500 | **200 text/csv** — 20 cột theo importer |
| Current Rates CSV | 200 text/html EMPTY (`_initMethod` đọc param `id`, link truyền `method_id` → return null) | **200 application/octet-stream** — header có cột `city_code` (machine name) |
| Import page (`importGrid?id=`) | — | **200** import grid của method |

Root causes: (1) `City` controller — `RawFactory` optional default-null → ObjectManager KHÔNG
autowire (giả định "Magento DI still injects" trong comment là SAI); (2) `_initMethod()` đọc
param `id`; (3) `CityGrid` export header dùng human label trong khi importer resolve theo
HEADER NAME. Fixes: required factory + forward ở 4 controllers; param `id` cho 2 links;
CityGrid export header machine-name (on-screen giữ human label) + test MethodTabs (tab fix
trước) — Launchpad **100/196 OK**, compile OK, temp user deleted.

## 9. Import Rates modal — sample link thay Google Drive (TASK-RT50KH follow-up)

- **Vấn đề**: modal Import Rates trỏ "Download Sample File" tới Google Drive folder của
  Mageplaza (`Import/Edit/Form.php:73` — schema cũ, thiếu `city_code`/`city_name`).
- **Fix**: preference `Mageplaza\TableRateShipping\Block\Adminhtml\Import\Edit\Form` →
  `Launchpad\...\Import\Edit\Form` override `_getDownloadSampleFileHtml()` — link mẫu =
  `launchpad_mptablerate/city/importTemplate` (ImportTemplateBuilder — 20 cột importer).
- **Browser-verified**: modal hiển thị link "Download Import Template (includes City / Area
  columns)" → fetch: 200 text/csv, body chứa `city_code` + `city_name`. Temp smoke user
  deleted. CHANGELOG Launchpad (TASK-RT50KH follow-up) ghi nhận.

## 10. Follow-up fix 3 (user report: Shipping Rates grid không data + sort City/Area lỗi 1054)

- Log: `SQLSTATE[42S22] Unknown column 'city_code' in 'order clause'` trên
  `SELECT main_table.* FROM mageplaza_tablerate_rate WHERE method_id=N ORDER BY city_code`.
- Root cause: `CityGrid::_prepareCollection` join SAU parent chain — `Extended::_prepareCollection`
  áp sort + `load()` BÊN TRONG parent → join sau load vô dụng (cả "no data" lẫn sort error).
- Fix: join chuyển sang override `setCollection()` (real method `Widget\Grid:196`, vendor gọi
  trước sort/load) — `CityGrid.php` (bỏ `_prepareCollection` override).
- Browser-verified (Playwright): grid 2 rate rows (wildcard + rate có `VNA25-625F2E6C89`);
  sort click City / Area → 0 new 1054; export CSV vẫn `city_code`; import grid page 200.
- Launchpad 102/199 OK · compile OK · temp user deleted.

## 11. Follow-up UX (2026-09-25): City / Area hiển thị NAME + vị trí sau Region

- Placement: `addField('city_code', ..., 'region')` ($after) — browser-verified:
  field order `country_id → region → city_code → ...` ✓
- Section "City / Area Data — Download & Import": 4 links (Current Rates CSV pre-filled /
  City Reference / Import Template / Import) — browser-verified ✓
- **Hiển thị NAME trên grid**: `city_area_display` = COALESCE(master.default_name, raw code) —
  SQL-verified trả "An Hoi Dong" cho rate gán city; browser display check chờ admin login
  khả dụng (captcha/lock môi trường chặn probe script — user tự verify 30 giây hoặc cấp lại
  temp user).
- Suites: Launchpad 102/199 OK · compile OK · temp smoke user deleted (2 lần).

## 12. Column position + name display verification

- SQL proof: `city_area_display` = COALESCE(master.default_name, raw code) — rate 15 → "An Hoi Dong", rate 14 → "" (không gán city).
- Code: `CityGrid::setCollection` join 2 bảng + select `city_code` + `city_area_display`; `_prepareColumns` addColumnsOrder + addColumnAfter region.
- Browser check (user tự verify — admin login bị ReCaptcha fatal chặn probe script):
  1. Rates tab → cột City / Area hiển thị tên (không phải VNA25-*)
  2. Cột position: ngay sau State/Region
  3. Sort cột City / Area → không lỗi
