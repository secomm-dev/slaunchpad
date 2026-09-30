# SPEC — ShippingCore Canonical Zone Admin & Carrier Assignment

```yaml
Specification ID: SPEC-FEAT-QA23PZ
Feature ID: FEAT-QA23PZ
Specification Level: FULL
Status: PROPOSED (chờ TL/SA approve — DB schema Tier-2 + shipping Tier-2)
Created: 2026-09-21
Requirements source: TL directive "ShippingCore Canonical Zone Admin & Carrier Assignment" (40 section, 2026-09-21)
Architecture anchor: .ai/project-context/architecture/address-shipping.md §35 (v10) — operational completion, KHÔNG tạo v11
```

---

## 0. Pre-change runtime audit (code-truth, 2026-09-21)

### 0.1 Classification

| Hạng mục | Classification | Evidence |
|---|---|---|
| `CarrierRateExecutionServiceInterface` production consumer | **TEST_ONLY** (0 invocation trong toàn bộ `app/code`) | Chỉ DI preference `ShippingCore/etc/di.xml` + 2 test files |
| `CarrierEligibilityEvaluator` | **TEST_ONLY** (consumer production duy nhất = execution service) | `Model/Rate/CarrierEligibilityEvaluator.php:28` |
| GHN `RealtimeRateContributor` + Factory | **TEST_ONLY / dead code** (adapter code-complete, không DI registration, không consumer) | `Secomm/Ghn/Model/Rate/RealtimeRateContributor.php:45` |
| Fallback orchestration (`FallbackCoordinator` + `CollectRatesPlugin`) | **RUNTIME_WIRED** qua `CarrierRateOutcomeCollectorInterface` | `Launchpad/MageplazaTableRate/Model/FallbackCoordinator.php:66` |
| Zone persistence | **NOT_WIRED** (`secomm_shipping_zone` = 0 hit; `canonicalZones` DI array rỗng) | `ShippingCore/etc/di.xml` |
| Carrier destination-scope config | **NOT_WIRED** (0 hit `destination_scope`/`allowed_zone` trong Ghn/Ghtk/ShippingCore XML) | grep 2026-09-21 |

### 0.2 Production GHN RATE chain hiện tại (exact)

```text
Magento CarrierFactory
→ Secomm\Ghn\Model\Carrier\Ghn::collectRates()            (Ghn.php:138; active gate)
  → Ghn::collect()                                         (Ghn.php:170)
    ├─ VN country gate                                     (Ghn.php:173)
    ├─ RateSourceMode gate (FALLBACK_ONLY → skip)          (Ghn.php:182-187)
    ├─ VND currency gate                                   (Ghn.php:191-201)
    └─ GhnRateRequestMapper::map() → QuoteParcelEstimator  (Ghn.php:204)
       → GhnRateCalculator::calculate() → resolveAndQuote() (GhnRateCalculator.php:120)
         ├─ hard-limit gate (quoteWithHandoff cũng có — duplicated by design)
         ├─ RuntimeAddressContextBuilder::build()          (ShippingCore)
         ├─ CarrierAddressHandoffService::handoffContextForOperation() (policy)
         ├─ GhnMappingResolver::resolve() (PRE_2025)       → GhnApiClient::post(CALCULATE_FEE)
         └─ CarrierRateOutcome (success/unavailable/technical)
  → Ghn::recordOutcome() → CarrierRateOutcomeCollector     (Ghn.php:161)
  → buildResult() | hide()
```

**KHÔNG hop nào** chạm `CarrierRateExecutionService`/`CarrierEligibilityEvaluator`. GHN hardcode
destination scope = "ALL + VN-only" qua entry gates riêng.

### 0.3 Kết luận audit (Deliverable 1)

Production RATE path **bypass** `CarrierRateExecutionService`. Fix = **composition/wiring gap
nhỏ nhất**: route GHN carrier entry qua execution service (contributor seam đã sẵn sàng), KHÔNG
redesign carrier internals — `quoteWithHandoff` giữ nguyên là tail chung.

---

## 1. Goal

Canonical carrier eligibility trở thành **operationally configurable** bởi merchant/admin
(không cần code/DI change). Target flow:

```text
Admin Shipping Zones → persistent CanonicalZone definitions
→ carrier DestinationScope / AllowedZoneCodes config
→ CarrierRateExecutionService → CarrierEligibilityEvaluator
→ realtime/fallback participation gating
```

Feature = **carrier participation / service area**, KHÔNG phải rate pricing.

## 2. Domain semantics — CanonicalZone giữ nguyên (v10 §35.2)

Contract hiện có **không đổi** (`Api/Address/CanonicalZoneInterface`): `code / label / enabled /
includeProvinceCodes[] / includeWardCodes[] / excludeWardCodes[]` — canonical VN_ADMIN_2025
codes (`VN-XX` province, `VNA25-*` ward) only. P1 = static canonical geography:
province include + ward include + ward exclude. Forbidden: GHN district_id/ward_code, GHTK
identifiers, localized names, polygon/lat-lng/radius/postcode-DSL/distance/origin-relative
rules, generic rule engine (INTERPROVINCE dynamic).

Matching precedence (đã implement trong `CanonicalZoneMatcher`, lock lại bằng test):

```text
1. !enabled                          → false
2. includeProvinces ≠ [] và province ∉ → false
3. includeWards ≠ [] và (ward = null hoặc ward ∉) → false   (null ward fail positive ward restriction)
4. includeWards = []                  → không ward restriction
5. ward ∈ excludeWards                → false (exclude wins)
6. else true
```

## 3. Zone persistence (TASK-1EK2MW)

### 3.1 Schema `secomm_shipping_zone` (declarative, module Secomm_ShippingCore)

| Column | Type | Constraint |
|---|---|---|
| `zone_id` | int unsigned | PK, IDENTITY |
| `code` | varchar(64) | NOT NULL, UNIQUE |
| `label` | varchar(255) | NOT NULL |
| `enabled` | smallint (boolean) | NOT NULL, default 1 |
| `include_province_codes` | json | NOT NULL (array of `VN-XX`) |
| `include_ward_codes` | json | NOT NULL (array of `VNA25-*`) |
| `exclude_ward_codes` | json | NOT NULL (array of `VNA25-*`) |
| `created_at` / `updated_at` | timestamp | NOT NULL, default CURRENT_TIMESTAMP / on update |

JSON column (MySQL 8) — đủ cho P1; KHÔNG over-engineer relational geography tables. Whitelist
entry thêm đúng 1 table (rule: giữ entry khi drop — không drop trong task này).

### 3.2 Classes

- `Model/Zone` (AbstractModel, `IdentityInterface` không cần — cache qua cache type) +
  `Model/ResourceModel/Zone` + `Model/ResourceModel/Zone/Collection`.
- `Api/Address/CanonicalZoneRepositoryInterface`:
  `getByCode(string): ?CanonicalZoneInterface` (domain VO, không model leak),
  `getEnabledByCodes(array): array<CanonicalZoneInterface>` (thứ tự input),
  `getList(SearchCriteria): ZoneSearchResult` (admin grid — hoặc Collection trực tiếp qua
  DataProvider; quyết định cuối theo pattern PancakeBridge: listing DataProvider đọc Collection,
  repository dùng cho save/get/delete),
  `save(ZoneInterface): void`, `deleteById(int): void`, `newZone(): ZoneInterface`.
- `Model/Address/PersistentCanonicalZoneRegistry` — implement
  `CanonicalZoneRegistryInterface`, là DI preference MỚI thay cho plain registry:
  - **Source precedence (nghị quyết §7)**: persisted zone **authoritative** cho mọi code tồn tại
    trong DB; DI/static zone chỉ fallback khi **không có** persisted zone cùng code. Duplicate
    persisted-vs-DI = deterministic (persisted thắng), KHÔNG throw.
  - Duplicate code **trong nội bộ** từng nguồn vẫn fail-fast như hiện tại (LogicException).
  - Lazy-load lần đầu `getAll()/getByCode()/getEnabled()` được gọi trong request (KHÔNG query
    lúc DI compile/construction).
  - Zero persisted zone + zero DI zone = valid state (§12).

### 3.3 Cache (§30)

Cache type mới `secomm_shippingcore_zones` (`etc/cache.xml`, tag riêng). Registry đọc qua
`CacheInterface`; repository `save/delete` → clean cache type. Invalidate đúng 4 sự kiện:
create / update / enable-disable / delete. KHÔNG index subsystem.

## 4. Canonical validation (§10) — `Model/Zone/Validator` (reject trước persist)

1. `code`: non-empty, normalized (trim; pattern `^[A-Z0-9_\-]+$` sau uppercase), unique.
2. Tất cả province codes tồn tại trong active scheme (`VnSchemes::VN_ADMIN_2025`, level-1)
   qua `VnAddressUnitProviderInterface::getUnit()`.
3. Tất cả ward codes (include + exclude) tồn tại trong active scheme.
4. Khi `includeProvinceCodes ≠ []`: mọi **include ward** phải thuộc một included province
   (ward `region_code` ∈ includeProvinceCodes) — ward thuộc province không include sẽ không
   bao giờ match ⇒ reject. **Exclude ward**: chỉ yêu cầu tồn tại; cross-province exclude là
   no-op hợp lệ (documented, không reject — tránh blocking khi merchant đổi province set).
5. Không silently drop invalid codes; không chấp nhận provider IDs.
6. Sửa zone (đổi province set) chạy lại toàn bộ validation trên giá trị mới.

Lưu ý: active scheme đổi (VN scheme swap) có thể làm zone stale — validate-on-save là P1
boundary; stale-code runtime behavior = "no match + diagnostic" (§8.3), không crash.

## 5. Carrier admin config (§13–§15, TASK-BYT2WK wiring + TASK-ZA10BT fields)

Config paths (generic pattern, GHN consumer đầu tiên):

```text
carriers/secomm_ghn/destination_scope    select  ALL (default) | SELECTED_ZONES
carriers/secomm_ghn/allowed_zone_codes   multiselect (enabled shared zones)
```

- ShippingCore cung cấp **shared config reader**
  `Api/Config/CarrierDestinationScopeConfigInterface`:
  `getDestinationScope(carrierCode, ?storeId): string` (unknown value → fail-closed `ALL`...
  KHÔNG — **fail-closed đúng nghĩa §22**: unknown scope không thể coi là ALL khi nó do merchant
  persist sai ⇒ reader validate enum; value rỗng/missing = ALL (chưa cấu hình);
  value persist không hợp lệ = `SELECTED_ZONES`-fail-closed? Quyết định: rỗng → ALL;
  không thuộc enum → ALL + warning log (mirror pattern `GhnConfig::getRateSourceMode`),
  `getAllowedZoneCodes(carrierCode, ?storeId): string[]` (comma/multiselect array, trim, dedupe).
- **Diagnostics (§16/§22)**: khi scope = SELECTED_ZONES, reader check từng configured code qua
  registry: unknown → `logger.warning` "carrier X references unknown zone Y (ignored)";
  disabled → warning "references disabled zone Z (not matching)". Evaluator giữ nguyên hành vi
  skip-unknown (domain pure, không đọc config/logger — §17).
- **UX dependency (§15)**: `Destination Scope = ALL` → `Allowed Zones` ẩn (system.xml
  `<depends>`); `SELECTED_ZONES` → hiện + required ≥1. Validate server-side tại save của
  **carrier config**: scope SELECTED_ZONES + rỗng zones → error (backend_model).
- Zone reference **chỉ lưu code** — KHÔNG duplicate province/ward lists vào carrier config.
- GHTK: KHÔNG thêm field trong task này (provider chưa wired — field sẽ là dead config);
  giữ regression-only (§26). Pattern generic sẵn sàng cho GHTK sau.

## 6. Runtime wiring GHN → CarrierRateExecutionService (§3/§18/§19, TASK-BYT2WK)

### 6.1 Composition mới trong `Ghn::collect()`

Giữ nguyên: active gate, VN country gate, VND currency gate, catch-all, `hide()`/`buildResult()`,
`recordOutcome()` seam, `GhnRateAdjuster` (buffer sau SUCCESS). Thay thế: mode gate + calculator
entry → execution service:

```text
1. destinationScope + allowedZoneCodes  ← GhnConfig (delegate shared reader, store-scoped)
2. canonical destination scalars        ← VnOperationalAddressResolverInterface::resolveFromRuntime
                                           (regionId, cityId) → identity (regionCode VN-XX,
                                           unitCode VNA25-*); unresolved → '' / null (fail-closed
                                           cho SELECTED_ZONES; ALL proceeds — handoff sẽ classify
                                           UNMAPPED như hiện tại)
3. resolutionContext                    ← RuntimeAddressContextBuilder::build(...) (giống
                                           calculator gọi hiện tại — name-bridge fallback giữ nguyên)
4. shippingContext                      ← ShippingContextFactory::fromRateRequest(request, code)
5. realtimeContributor                  ← RealtimeRateContributorFactory::create(request) (dead → LIVE)
6. mode/policy                          ← GhnConfig (accessor có sẵn)
7. capability                           ← GhnRateCapabilityAdapter (có sẵn)
→ CarrierRateExecutionRequest (factory) → CarrierRateExecutionService::execute()
→ post-decision: ineligible → record unavailable(DESTINATION_NOT_IN_SCOPE) + warning log + hide
                 FALLBACK_ONLY + eligible → record unavailable(REASON_RATE_SKIPPED_FALLBACK_ONLY)
                                            (diagnostic giữ nguyên; coordinator FALLBACK_ONLY branch
                                            mở fallback per §35.6) + hide
                 realtime → record decision outcome → adjust+buildResult | hide
```

Parcel estimation (`GhnRateRequestMapper::map`) chuyển vào contributor (đã implement);
hard-limit gate đã duplicated trong `quoteWithHandoff` — không mất gate nào. `GhnRateCalculator::
calculate()/resolveAndQuote()` thành standalone path không còn consumer production — giữ nguyên
code + test (không xoá, không sửa semantics), thêm docblock note.

### 6.2 Shared contract delta (ShippingCore — additive)

- `ShippingFailureReason::DESTINATION_NOT_IN_SCOPE = 'DESTINATION_NOT_IN_SCOPE'` — reason
  canonical MỚI (owner duy nhất), string-aligned với
  `CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE`. KHÔNG thêm vào
  `SafeDegradationEligibilityPolicy` defaults (fail-closed — policy KHÔNG đổi).
- `FallbackCoordinator` (Launchpad bridge) — **1 guard** trong `isMemberEligible()` trước các
  nhánh mode: `failureReason === DESTINATION_NOT_IN_SCOPE → return false` (§20: zone miss
  KHÔNG được mở fallback kể cả FALLBACK_ONLY; bridge đọc fact có cấu trúc — đúng triết lý
  hiện có, không parse message, không đổi policy/pattern khác).

### 6.3 Invariants giữ nguyên

- Origin-readiness gate (bước 3 của service): `shipping/origin/country_id` có Magento default
  `US` (vendor module-shipping config.xml:12) → gate luôn pass với store cấu hình chuẩn ⇒
  §25 "ALL → behavior unchanged" đảm bảo; origin explicitly rỗng = fail-closed INVALID_
  CONFIGURATION (documented, đúng §19 fulfillment-context ordering).
- Eligibility TRƯỚC mode TRƯỚC origin/policy/contributor (frozen service order — không đổi).
- Carrier contributor không thấy zone internals (chỉ nhận final handoff) — không đổi.
- SELECTED_ZONES + 0 configured zones → evaluator loop exhausted → ineligible (fail-closed,
  có sẵn) + diagnostic (reader warning "configured with no allowed zones").

## 7. Zero-zone / disabled / deleted (§12/§16/§22)

| Trạng thái | Matching | Giao tiếp |
|---|---|---|
| 0 zone tồn tại | ALL: eligible bình thường (không zone lookup block); SELECTED_ZONES: ineligible | Docs; không seed default zones |
| Zone disabled | Không match (`getEnabled`/matcher gate) | Reader warning runtime; form save vẫn hợp lệ |
| Zone bị xoá (dangling carrier ref) | Bỏ qua (getByCode null → skip) | Reader warning runtime |
| Configured code unknown | No match | Reader warning; save-time validation chặn code lạ mới |

## 8. Admin Zone CRUD (§8–§10, §31, TASK-ZA10BT)

- **Menu** (theo Secomm convention — mọi grid Secomm gắn `MenuSecomm_Base::menu`):
  `MenuSecomm_Base::menu → Secomm_ShippingCore::zones` ("Shipping Zones"). *Deviation khỏi
  gợi ý "Stores/Sales/Shipping" trong directive — cần TL chốt (Secomm menu là pattern hiện hữu;
  Ahamove là precedent duy nhất dùng Stores nhưng chỉ là config link).*
- **ACL** (`etc/acl.xml`, root `Magento_Backend::admin`):
  `Secomm_ShippingCore::zones` (View — list) → `::zones_manage` (Create/Edit/Enable/Disable/
  Delete). Controllers dùng `public const ADMIN_RESOURCE` (pattern PancakeBridge/GHN-E3).
- **Grid** (UiComponent, pattern PancakeBridge WarehouseMap): Code, Label, Enabled, Province
  Count, Included Ward Count, Excluded Ward Count, Updated At (counts qua `JSON_LENGTH()` trong
  grid collection select). Actions: Create, Edit, Delete, mass Enable/Disable/Delete.
- **Form**: Code (read-only khi edit), Label, Enabled, Province multiselect (options =
  `VnAddressUnitProviderInterface` level-1, active scheme `VN_ADMIN_2025`), Included Wards +
  Excluded Wards multiselect constrained theo selected provinces qua AJAX endpoint
  `secomm_shippingcore/zone/wardOptions?provinces=…` (precedent:
  `Launchpad_MageplazaTableRate/Controller/Adminhtml/City/Options` + `city-selector.js`;
  isAjax guard + ACL). Không map UI.
- Validation ở save path (Validator §4) — reject + error message, không silent drop.

## 9. Out of Scope (§37 + boundary)

Rate pricing per zone; TableRate City/Area reuse hay dependency (Launchpad_MageplazaTableRate
↛ ShippingCore zone; TableRate giữ pricing-only — check §23); distance/lat-lng/polygon/
INTERPROVINCE dynamic; GHTK adoption (provider regression-only); GHN Type-5/CREATE/mapping/
buffer semantics; RateSourceMode/AddressResolutionPolicy/PICK_PRIMARY/fallback policy redesign;
ServiceLevelRateOrchestrator; MSI/store routing; geocoding; DI→DB auto-persist migration
(zero DI zones hiện tại — §33).

## 10. Acceptance Criteria (mapping Definition of Done)

| # | AC | Verify |
|---|---|---|
| AC1 | Production RATE qua `CarrierRateExecutionService` (GHN real path) | GHN integration test + grep consumer ≥1 production |
| AC2 | Zone CRUD không cần code/DI change | Admin test + manual path |
| AC3 | Zone identity = canonical codes only | Validator + grep gates §39 |
| AC4 | Zone shared, không duplicate per carrier | Config chỉ lưu code refs |
| AC5–6 | ALL / SELECTED_ZONES hoạt động per §5 | Unit + integration |
| AC7 | ALL + 0 zones OK | Test |
| AC8 | SELECTED_ZONES + 0 zone → fail-closed ineligible + diagnostic | Test |
| AC9 | Zone miss → 0 realtime + 0 fallback + 0 policy + 0 mapping + 0 API | GHN integration (mock API client call counter) |
| AC10 | FALLBACK_ONLY không bypass eligibility | Coordinator guard test + GHN test |
| AC11 | GHN provider 0 zone matching | grep gate |
| AC12 | TableRate City/Area pricing-only, 0 dependency | grep gate + no module dep |
| AC13 | Admin validation reject invalid canonical codes | Validator tests |
| AC14 | Disabled/deleted fail-safe + diagnostic | Tests |
| AC15 | Cache invalidate trên 4 sự kiện | Repository tests |
| AC16/17 | GHN Type-5 + GHTK không regress | Full-suite regression §38 |
| AC18 | `setup:upgrade` + `setup:di:compile` + `bin/project-ai-validate --check-specs` green | Gates |
| AC19 | USER_GUIDE + architecture §35 updated | Docs diff |

## 11. Risks / deviations cần TL chú ý

1. **DB schema mới** (`secomm_shipping_zone`) — Tier-2. Declarative, additive, whitelist đủ.
2. **FallbackCoordinator guard** — chỉnh `Launchpad_MageplazaTableRate` (bridge, KHÔNG pricing);
   1 guard line + tests. Bắt buộc để giữ §20 cho FALLBACK_ONLY.
3. **`ShippingFailureReason::DESTINATION_NOT_IN_SCOPE`** — additive shared contract constant.
4. **Menu placement** — Secomm menu (convention) vs Stores/Sales/Shipping (gợi ý directive).
5. **`GhnRateCalculator::calculate()` trở thành standalone-only** (không còn production caller)
   — giữ code+test, docblock note; KHÔNG xoá trong task này.
6. Eligibility dùng **active-scheme (2025) identity** — zone stale khi scheme swap = no-match +
   warning (P1 documented boundary, không auto-migrate zone data).

---

## 12. Delta 2026-09-22 (TASK-G3K9V2 — ShippingCore Zone & Carrier Coverage Admin UX)

TL directive "ShippingCore Zone & Carrier Coverage Admin UX" (16 section, 2026-09-22) mở rộng
§5 + §8; runtime semantics KHÔNG đổi trong task này (giá trị scope thứ 3 runtime thuộc
TASK-R8WR1R, dev-complete cùng working tree):

1. **Carrier Coverage screen** (thay fields §5 trong GHN system.xml): Secomm menu mới, ACL
   `::carrier_coverage(_manage)`; Availability `ALL | SELECTED_ZONES | ALL_EXCEPT_SELECTED_
   ZONES` + Zones (enabled only) + Rate Source Mode + Address Resolution Policy. Ghi qua
   WriterInterface cùng paths `carriers/<code>/...` (§5 paths preserved — ZERO migration),
   DEFAULT scope P1, `cleanType('config')`. Carrier registry DI-array (`CarrierRegistry`);
   GHN tự đăng ký `secomm_ghn`. Validation mirror backend model cũ (zone mode cần ≥1 zone
   TỒN TẠI — deleted reject, disabled allowed; admin chặt hơn runtime một điểm: ALL_EXCEPT +
   list rỗng bị chặn ở save dù runtime coi ≡ ALL).
2. **Zone form (§8 delta)**: Country = fixed display "Vietnam (VN)" (không persist — mã
   canonical đã mang VN identity); Province + Included Wards là searchable multiselect
   (`Secomm_ShippingCore/js/form/element/searchable-multiselect`); ward options sửa sang
   `VnAddressUnitProviderInterface::getByRegion()` (region_code + level — seeded DB có
   `parent_code = NULL` trên mọi cấp con nên `getChildren()` trả 0 row; `getByLevel()` cho
   province options); **bỏ field Excluded Wards khỏi UI** (§5-adjacent P1 decision — loại trừ
   địa chỉ về carrier-coverage scopes; contract `exclude_ward_codes` + grid expression UI
   column removed, matcher/validator/schema giữ nguyên); đổi province → auto-clear ward
   không còn thuộc tỉnh (deterministic; Validator vẫn là correctness boundary); thêm
   read-only "Carriers Referencing This Zone" (derive từ carrier config paths — một source
   of truth, edit chỉ ở Carrier Coverage).
3. **VietNamAddress additive API**: `getByLevel()` + `getByRegion()` (read-only, Order name_vi;
   không đụng import/GraphQL) — CANONICAL_CSV provider của GHN (implements interface) bổ sung
   cùng semantics.
