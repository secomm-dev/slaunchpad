# Changelog — Launchpad_MageplazaTableRate

## Unreleased — 2026-09-18 (TASK-JZXM66 — City/Area admin self-service)

- Rate form `City / Area` upgraded from raw text input to a cascading select
  (Region → City/Area, options feed `launchpad_mptablerate/city/options`, AJAX-only,
  ACL `Mageplaza_TableRateShipping::method`); field name stays `city_code`, wildcard = empty.
  Edit resolves the stored code to its label; an unresolvable stored code keeps its raw value
  with a red warning (never silently wildcarded).
- Downloads on the rate form: City Reference CSV (`city/referenceCsv`, columns
  `country_code, region_code, region_name, city_code, city_name, parent_city_code,
  parent_city_name`, live from `directory_region_city`, optional region filter) and Import
  Template (`city/importTemplate`, importer column superset + informational `city_name`,
  example + wildcard rows). UTF-8 + BOM.
- Import template columns locked to `MptablerateImport::templateColumns()`; the importer
  tolerates the extra `city_name` column and strips it before persistence.
- Region/city consistency validation: rate-save plugin rejects a `city_code` that does not
  belong to the row's concrete region; the importer enforces the same per row
  ("Row …: City / Area code … does not belong to region …").
- `MethodSettingsProvider`: `cityRegionId`, `cityLabel`, `cityBelongsToRegion`,
  `fetchCityOptionsByRegion`, `wildcardOption`; `SettingsPersister` delegates.
- Tests: 29 new unit tests (provider readers, template-column contract, importer
  region/city validation, reference/template CSV builders + escaping/UTF-8); suite 67 green.

## 0.2.0 — 2026-09-15 (TASK-5XQXZK / DEC-TASK5XQXZK-001)

### Changed — per-method fallback composition (BREAKING: removes unused global mode)
- Reworked `Model\FallbackRateProvider`: composition entry `calculate(methodId, RateRequest)`
  uses the REAL Magento RateRequest (destCity/destRegionId intact → city dimension works) and
  package_* scalars; the ShippingCore pool contract stays (`mptr_<method_id>` bucket codes).
- Runtime model: Magento owns carrier execution; carriers report normalized outcomes into the
  ShippingCore collector; `Plugin\Shipping\CollectRatesPlugin` (sole outer seam on
  `Magento\Shipping\Model\Shipping::collectRates`) filters per-method visibility and appends
  eligible fallback rates after the normal collection completes.
- Per-method capabilities via `launchpad_mptablerate_method_setting`
  (`show_to_customer` / `use_as_fallback`); membership via
  `launchpad_mptablerate_method_member` with pair identity (carrier_code, method_code) and
  `UNIQUE(method_id, carrier_code, method_code)`; FK ON DELETE CASCADE. No settings row =
  native Mageplaza behavior (zero regression).
- REMOVED (deprecated by DEC): global config `launchpad_mptablerate/general/mode`,
  `Model\Config`, `Model\Source\Mode`, mode-based `Plugin\Carrier\TableRate`,
  DI `methodMapping`/`labels`. `carriers/mptablerate/active` remains Mageplaza's own
  carrier activation only.

### Added — City/Area dimension + admin UX
- `launchpad_mptablerate_rate_city` (rate_id FK, stable `city_code`);
  `Plugin\Rate\CollectionFilterPlugin` + `Model\City\CityRateScopeResolver` — 3-tier
  location precedence (exact node → region+wildcard → broader) narrowing ONLY location scope;
  unresolved destinations never match city rows; no city rows = 100% legacy matching.
- `Model\City\DestinationCityResolver` — resolves (regionId, destCity text) → unit code via
  `Secomm_VietNamAddress` `VnOperationalNameResolverInterface`; never guesses.
- Admin: "Launchpad Settings" tab (preference subclass of Mageplaza Tabs), settings/members
  persistence plugin on Save, dynamic member options (`Model\Source\ShippingMethod`,
  mptablerate excluded), rate form/grid/export "City / Area" (preference subclasses),
  CSV importer (preference subclass) with `city_code` + round-trip fix
  (postcode/shipping_group columns), vi_VN/en_US.

### Tests
- FallbackCoordinator (eligibility matrix §9), MethodVisibilityFilter (4 capability combos),
  CityRateScopeResolver (precedence + zero-regression + SUM/MIN/MAX-in-tier),
  FallbackRateProvider (bucket codes + internal pipeline); full module suite 32 green.

## 0.1.0 — 2026-09-08 (TASK-NQT782 / LT-BRIDGE-1)

### Added — ShippingCore fallback provider bridge
- `Model\FallbackRateProvider` — implements
  `Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface` against the Mageplaza
  internal calculation pipeline (`Method::isActive` → `Rate\Collection::filterByRequest` with
  a bridge-local RateRequest + scalar cart dimensions → `Rate::calculatePrice` →
  `CalculateRule` combination). No-match/no-mapping/STANDALONE → null; missing/inactive
  configured method → `Model\Exception\FallbackConfigurationException`; calculated zero
  remains a valid explicit rate; delivery estimate null (SLA belongs to service-level
  presentation).
- `Model\Config` — mode via system config (`launchpad_mptablerate/general/mode`, default
  FALLBACK_ONLY); service-level → method_id mapping + labels via DI composition arrays.
- `Model\Source\Mode` — FALLBACK_ONLY / STANDALONE.
- `Plugin\Carrier\TableRate` — defense-in-depth: suppresses the normal Mageplaza checkout
  carrier in FALLBACK_ONLY mode (warns when `carriers/mptablerate/active = 1` by
  misconfiguration); STANDALONE proceeds unchanged.
- `etc/di.xml` — registers the provider into the ShippingCore `FallbackRateProviderPool`
  (sole provider for Launchpad).
- Unit tests (+16: config, provider mapping/match/zero/invalid/translation, suppression
  plugin branches).

### Notes
- No vendor/Mageplaza source modified; no Secomm_ShippingCore modification; no carrier code.
- Known boundaries (SPEC §R1/§R2): `Method::calculatePrice()` is items-driven and is
  orchestrated through `Rate::calculatePrice` + the method's CalculateRule instead;
  `Method::isActive()` consults the ambient customer session (consistent on the storefront
  checkout path).
