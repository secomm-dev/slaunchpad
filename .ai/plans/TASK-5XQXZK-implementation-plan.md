# Implementation Plan: TASK-5XQXZK — Mageplaza TableRate fallback composition (Magento-orchestrated outcomes + per-method groups + city dimension)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-5XQXZK (parent FEAT-GGTWXW) |
| Mode | A (ShippingCore contract + DB schema + checkout behavior → Tier-2) |
| Specification | [specs/SPEC-TASK-5XQXZK-mptablerate-fallback-composition.md](../records/specs/SPEC-TASK-5XQXZK-mptablerate-fallback-composition.md) — FULL, VALID |
| Decision | [DEC-TASK5XQXZK-001](../records/decisions/DEC-TASK5XQXZK-001.md) — 22-point directive SA/TL 2026-09-15 |
| Audit basis | Audit 2026-09-15 (FEAT-GGTWXW): Mageplaza v4.0.8 seams, ShippingCore contracts, address identity layer |
| Out of scope | fan-out registry · business service enum · auto-dedup · ranking · retry · OMS · vendor edits · non-VN city seeding |

## Approach

9 phase theo directive §20 — chi tiết trong SPEC §3. Outer seam đã verify:
`Magento\Shipping\Model\Shipping` là preference duy nhất của `RateCollectorInterface`
(vendor `module-shipping/etc/di.xml:9`); `collectRates()` trả `$this`, kết quả trong
`getResult()` (accumulator `Magento\Shipping\Model\Rate\Result`), `destCity` từ quote address
không bị đụng (collectRates chỉ set origin fields khi `!getOrig()`). Plugin `around
Shipping::collectRates` = 1 seam duy nhất cover storefront/REST/GraphQL/admin (mọi channel đi
qua `Quote\Address::requestShippingRates` → `RateCollectorInterface`). Execution isolation:
mỗi lần `collectRates` = 1 collector execution (bracket begin/end trong plugin — không giả định
1 request = 1 collection; multi-address checkout gọi lặp lại tuần tự, factory `create()` cho
Shipping instance riêng mỗi lần nên accumulator cũng riêng).

## Files affected

### Phase 1 — Secomm_ShippingCore (collector + policy + reasons)

| File | Change | Lý do |
|------|--------|-------|
| `Api/Rate/CarrierRateOutcomeCollectorInterface.php` | new | `beginCollection()/record(carrierCode, methodCode, CarrierRateOutcomeInterface)/getOutcomes()/endCollection()` — pair identity |
| `Model/Rate/CarrierRateOutcomeCollector.php` | new | request-scoped (shared instance, per-execution buffer), last-wins per pair, orphan-record an toàn khi chưa begin |
| `Api/Failure/ShippingFailureReason.php` | modify | + `CANONICAL_AMBIGUOUS`, `CANONICAL_UNMAPPED`, `INVALID_CONFIGURATION` |
| `Api/Fallback/FallbackEligibilityPolicyInterface.php` | new | `isFallbackEligible(status, failureReason): bool` — policy owner |
| `Model/Fallback/SafeDegradationEligibilityPolicy.php` | new | default map + DI array override; không parse message |
| `etc/di.xml` | modify | preference collector + policy |
| `Test/Unit/Model/Rate/CarrierRateOutcomeCollectorTest.php`, `Test/Unit/Model/Fallback/SafeDegradationEligibilityPolicyTest.php` | new | §21 matrix |

### Phase 2 — Secomm_Ghn / Secomm_Ghtk (report outcomes)

| File | Change | Lý do |
|------|--------|-------|
| `Secomm/Ghn/Model/Carrier/Ghn.php` | modify | inject collector (proxy), record tại collectRates theo calculator outcome (map handoff status AMBIGUOUS/UNMAPPED → reasons mới) |
| `Secomm/Ghn/Model/Rate/GhnRateCalculator.php` | modify (nhỏ) | handoff status → `CANONICAL_AMBIGUOUS`/`CANONICAL_UNMAPPED` thay reason generic `CANONICAL_UNRESOLVED` khi phân biệt được |
| `Secomm/Ghtk/Model/Carrier/Ghtk.php` | modify | inject collector, record theo `GhtkRateOutcomeFactory` outcome |
| Tests | modify | assert record được gọi đúng pair/status |

### Phase 3 — Launchpad_MageplazaTableRate (schema + runtime)

| File | Change | Lý do |
|------|--------|-------|
| `etc/db_schema.xml` + `etc/db_schema_whitelist.json` | new | `launchpad_mptablerate_method_setting` (PK method_id FK CASCADE; show_to_customer=1; use_as_fallback=0), `launchpad_mptablerate_method_member` (UNIQUE(method_id,carrier_code,method_code); enabled), `launchpad_mptablerate_rate_city` (PK rate_id FK CASCADE; city_code varchar(64); INDEX) |
| `etc/module.xml`, `etc/di.xml`, `etc/config.xml`, `etc/adminhtml/system.xml` | modify | sequence + wiring + deprecate mode/methodMapping/labels (config.xml xóa default mode; system.xml bỏ field) |
| `Model/MethodSettingsProvider.php` | new | settings + membership + fallback-methods load (batched, per-request cache), fail-soft stale members |
| `Plugin/Shipping/CollectRatesPlugin.php` | new | outer seam: begin collector → proceed → visibility filter (`show_to_customer=0` remove khỏi Result) → fallback coordinator append (per-method: participating members; SUCCESS suppress; policy eligible → provider internal) |
| `Model/FallbackCoordinator.php` | new | per-method evaluation + build `RateResult\Method` (carrier `mptablerate`, method = method_id, title = method title per store) |
| `Plugin/Rate/CollectionFilterPlugin.php` | new | city precedence 3-tier trên `Rate\Collection::filterByRequest` (removeItemByKey loser tiers) |
| `Model/City/DestinationCityResolver.php`, `Model/City/CityRateScopeResolver.php` | new | dest (regionId, destCity) → unit_code qua `VnOperationalNameResolverInterface`; precedence narrow location scope |
| `Model/FallbackRateProvider.php`, `Model/Config.php`, `Plugin/Carrier/TableRate.php` | rework | provider interpret `mptr_<method_id>` + dùng runtime RateRequest (có destCity) qua coordinator; Config bỏ mode/mapping/labels; guard plugin mode-based REMOVE (thay bằng outer plugin) |
| `Model/Exception/FallbackConfigurationException.php` | keep | fail-loud misconfiguration |

### Phase 4 — Admin UX + CSV

| File | Change | Lý do |
|------|--------|-------|
| `Plugin/Adminhtml/MethodTabsPlugin.php`, `Block/Adminhtml/Method/Edit/Tab/Launchpad.php` | new | tab "Launchpad Settings" trên method edit (legacy Tabs block) |
| `Controller/Adminhtml/Method/SavePlugin.php` (plugin after) | new | persist settings + members |
| `Model/Source/ShippingMethod.php` | new | dynamic options từ `CarrierFactory::getAllCarriers`, exclude `mptablerate` |
| `Plugin/Adminhtml/RateFormPlugin.php`, `Plugin/Adminhtml/RateSavePlugin.php`, `Plugin/Adminhtml/RateGridPlugin.php` | new | City/A field form + save (launchpad_rate_city map) + grid column + export column |
| `Model/MptablerateImport.php` (preference `Mageplaza\TableRateShipping\Model\Import`) | new | superset columns (+city_code, +postcode_*/shipping_group round-trip fix), resolve/validate `city_code` fail-loud, write rate_city cùng transaction |

### Phase 5 — Tests/docs/evidence

Unit + integration theo SPEC §5; README/CHANGELOG; architecture doc amend
(§9 safe degradation, §15–§18 replace mapping/mode, §12 deprecate `CarrierServiceLevelInterface`
nếu unused, runtime diagram, §29 checklist); `bin/project-ai-validate --check-specs
--check-records`; evidence `.ai/evidence/TASK-5XQXZK/`.

## Risks

- Mageplaza `Rate\Collection::filterByRequest` plugin chạy cho mọi instance collection — chỉ 2
  production callers đã xác nhận (carrier + bridge); admin grid không gọi.
- `Method::isActive()` đọc ambient session (đã documented TASK-NQT782 §R2) — REST/GraphQL dùng
  guest group; giữ limitation, REPORT.
- FK cross-module (Launchpad table → Mageplaza table): declarative schema hỗ trợ; whitelist
  Launchpad-only; Mageplaza mass-delete/xóa method → CASCADE DB-level (không truncate).
- Preference `Mageplaza\Model\Import` là class preference toàn cục — subclass giữ behavior cũ
  khi không có city column (BC), risk thấp.
