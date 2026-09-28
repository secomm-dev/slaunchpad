# Implementation Plan — TASK-WY6WP5 (Shipping Coverage P1 Admin UX + CoverageTarget abstraction)

| Specification | SPEC-FEAT-QA23PZ (../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md) + delta embedded trong TASK-WY6WP5 |
|---|---|
| **Work item** | TASK-WY6WP5 — slice tiếp theo của FEAT-QA23PZ (rework admin UX của TASK-G3K9V2, đang in_review) |
| **Mode** | A |
| **Depends** | TASK-G3K9V2 + TASK-R8WR1R (trên working tree, chưa commit — build trên nền đó) |
| **Risk** | medium — rename class trong DI chain, rebuild selector UI, thêm lifecycle controllers; runtime contracts + config paths FROZEN không đụng |
| **TL decisions** | Menu 3 cấp `Secomm → Shipping`; Implement Reset to Defaults (AskUserQuestion 2026-09-23) |

## Steps

1. **Abstraction `Model/CoverageTarget/`** — `CoverageTargetType` (CARRIER implemented,
   METHOD reserved; `all()/exists()/isImplemented()`), `CoverageTargetIdentity` (immutable,
   private ctor + `::carrier(code)`, `type()/code()/key()/equals()`),
   `Api/CoverageTarget/CoverageTargetInterface` + `Model/CoverageTarget/CoverageTarget`
   (identity+label VO), `CoverageTargetRegistry` (DI array `{type?, code, label}`;
   thiếu type → CARRIER; code rỗng skip; type lạ fail-fast; getAll sort type,code).
   Unit tests ×3.
2. **Adapter** — rename `Model/CarrierCoverage/PolicyConfig` → `CarrierCoverageConfigAdapter`;
   API nhận `CoverageTargetIdentity`; paths `carriers/<code>/*` byte-identical; thêm
   `load(): ?array` (null = 0 DEFAULT row), `hasExplicitConfig(): bool` (any-scope, 1
   SELECT `path IN (4)` thẳng `core_config_data`, memoize), `reset(): int`, 
   `nonDefaultScopeRows(): array`. XOÁ `CarrierRegistry`; migrate consumers
   (`CarrierZoneIndex` → registry CARRIER, `ZoneReferenceGuard`, `Validator` param
   identity, controllers, DataProviders, tests). XOÁ `CarrierOptions`.
3. **Coverage listing + lifecycle controllers** — `secomm_shippingcore_coverage_listing.xml`
   (Add Coverage button; cột Target/Type/Status/Availability/Zones/Actions; không filter
   toolbar; sortable=false) + `CoverageListingDataProvider` (stub collection hấp thụ
   framework calls; rows = registry × adapter) + `CoverageActions` column + source models
   `CoverageTargetTypeOptions`/`ConfigurationStatusOptions`. Controllers:
   `NewAction` (forward edit, create mode), `Edit` (identity resolve; METHOD → "reserved"
   error), `Save` (duplicate guard `is_create` + `hasExplicitConfig`), `Reset` (POST-only;
   scoped rows warning). Buttons `GenericButton`/`ResetButton` (confirm 3-arg
   `deleteConfirm(msg,url,{data:{}})` — POST). XOÁ `CarrierList` + `coverage/index.phtml`.
4. **Coverage form rework** — label "Shipping Coverage"; fields `applies_to` (disabled
   "Carrier"), `target_type` hidden CARRIER, `is_create` hidden, `target_code` (create =
   ui-select single registry-minus-configured qua meta; edit = disabled + 1 option),
   `destination_scope` + `switcherConfig` (ALL → hide zones; zone modes → show),
   `allowed_zone_codes` ui-select multiple `ReferencableZoneCodes`; rate-orchestration
   fieldset giữ nguyên. `CoverageFormDataProvider`: meta options + disabled edit;
   getData `[]` cho create/not-configured (XML defaults).
5. **Zone form geography-only + selector rebuild** — XOÁ `assigned_carriers` field +
   getData key; `include_province_codes` → ui-select multiple (`ProvinceOptions`);
   `include_ward_codes` → `js/form/element/ward-select` (subclass ui-select: optionsUrl +
   provincesValue listener + requestTag race guard + pruneToOptions — port từ component
   cũ); XOÁ `searchable-multiselect.js` + template .html; string sweep "Carrier Coverage"
   → "Shipping Coverage" (Zone/Delete, ZoneActions, massaction confirms, enabled notice).
6. **Menu/ACL/GHN** — menu container `Secomm_ShippingCore::shipping` "Shipping" (parent
   `MenuSecomm_Base::menu`, sortOrder 65); con zones(10) + coverage(20) title "Shipping
   Coverage"; acl label-only; `Secomm/Ghn/etc/di.xml` đăng ký `CoverageTargetRegistry`
   `{type=CARRIER, code=secomm_ghn, label="GHN (Giao Hàng Nhanh)"}`;
   `CoveragePointer` text/link mới (`target_type`/`target_code` params).
7. **Tests** — matrix theo directive §30 A–N + structural (O) + framework-call regression
   (P); migrate Zone controller tests constructor; xoá `PolicyConfigTest`/`CarrierRegistryTest`;
   mới `CoverageTarget/*Test` ×3, `CarrierCoverageConfigAdapterTest`,
   `CoverageListingDataProviderTest`, `Coverage\ResetTest`, mở rộng
   `Coverage\SaveTest`/`EditTest`/`CoverageFormDataProviderTest`.
8. **Verification** — 4 suites (ShippingCore/Ghn/Ghtk/VietNamAddress) + `setup:di:compile`
   + `cache:flush` + `bin/project-ai-validate --check-specs --check-records --check-identity`;
   grep gates (`searchable-multiselect|CarrierOptions|PolicyConfig|CarrierRegistry` → 0
   code hits) + invariant grep directive §34; CLI integration proof §31 (adapter save →
   reader/evaluator → reset → default ALL; DB thật, zone NOITHANH có sẵn + tạo HCM_INNER);
   browser smoke directive §32 (6 mục) hoặc `ADMIN_SMOKE = BLOCKED_BY_ENVIRONMENT`.
9. **Docs** — amend `address-shipping.md` §35.10/§35.11 (KHÔNG v11), USER_GUIDE, README,
   CHANGELOG ×2, FEAT record additive, COMPONENT_INDEX, CURRENT_STATE/SESSION_STATE;
   final report A–R.

## Test plan (mapping directive §30)

A/B `CoverageListingDataProviderTest` · C listing+form provider · D/E
`CoverageFormDataProviderTest::testMetaOptionsExcludeConfigured` · F `Coverage\SaveTest`
duplicate guard · G `CarrierCoverageConfigAdapterTest`+`SaveTest` · H form provider reload ·
I `ValidatorTest` ALL · J/K `ValidatorTest` zone modes · L/M `ReferencableZoneCodesTest` ·
N `ValidatorTest` deleted reject · structural O zone-form XML test · P listing regression.
Regression suites + compile + validator như step 8.
