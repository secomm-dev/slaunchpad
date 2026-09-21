# SPEC-TASK-5XQXZK — Mageplaza TableRate fallback composition (Magento-orchestrated outcomes + per-method groups + city dimension)

> FULL spec cho FEAT-GGTWXW / TASK-5XQXZK. Quyết định nguồn: DEC-TASK5XQXZK-001 (directive
> SA/TL 2026-09-15, 22 điểm). Architecture base: address-shipping.md + audit 2026-09-15.

## 1. Goal

Hoàn thiện `Launchpad_MageplazaTableRate` thành Core composition/bridge:

- **Magento owns carrier execution** — không fan-out registry, không duplicate discovery.
- **Carriers report** normalized outcomes (`SUCCESS`/`UNAVAILABLE`/`TECHNICAL_FAILURE` +
  structured `ShippingFailureReason`) vào `CarrierRateOutcomeCollectorInterface` (ShippingCore),
  identity pair `(carrier_code, method_code)`.
- **Bridge (Launchpad_MageplazaTableRate)** trigger fallback tại outer seam
  `Magento\Shipping\Model\Shipping::collectRates()` (preference duy nhất của
  `RateCollectorInterface`): begin → proceed (carriers chạy) → evaluate per Mageplaza method →
  append fallback rates + filter visibility.
- **Mageplaza Method = fallback grouping identity** với per-method capabilities
  `show_to_customer` / `use_as_fallback` + membership N realtime methods.
- **City/address-unit dimension** cho TableRate matching (stable `city_code`, optional,
  zero-regression).

## 2. Scope

**In**: ShippingCore collector + failure reasons mới + eligibility policy; GHN/GHTK report
outcomes; Launchpad schema (3 extension tables) + coordinator + outer plugin + visibility +
city matching + admin UX + CSV import/export + tests + docs.

**Out**: carrier fan-out orchestration; business service enum (EXPRESS/SAME_DAY/STANDARD);
auto-dedup visibility; carrier ranking/cheapest selector; SLA inference; retry framework;
OMS; duplicate address hierarchy; non-VN city seeding; vendor source edits.

## 3. Approach

Thứ tự: (1) ShippingCore `CarrierRateOutcomeCollectorInterface` + `Model` (request-scoped,
bracket `beginCollection`/`endCollection`, không persist, last-wins per pair) +
`ShippingFailureReason` +`CANONICAL_AMBIGUOUS`,`CANONICAL_UNMAPPED`,`INVALID_CONFIGURATION` +
`FallbackEligibilityPolicyInterface`/`SafeDegradationEligibilityPolicy` (DI override được).
(2) GHN/GHTK inject collector, record tại classify points (GHN: calculator outcome; GHTK:
outcome factory sites); phân biệt AMBIGUOUS/UNMAPPED từ handoff status. (3) Launchpad
declarative schema 3 bảng + whitelist. (4) Outer plugin `Shipping::collectRates`:
visibility filter + fallback coordinator (per-method evaluation; membership là kiến thức
bridge; eligibility qua ShippingCore policy; append `RateResult\Method` carrier `mptablerate`
method `method_id`, label = method title per store). (5) City: dest resolve
(`VnOperationalNameResolverInterface`), precedence 3 tier CHỈ narrow location scope tại
plugin `Rate\Collection::filterByRequest`. (6) Admin: method form tab (plugin `Tabs`),
save plugin, rate form/grid/export city field, member multiselect dynamic
(`CarrierFactory::getAllCarriers`, exclude `mptablerate`). (7) CSV: preference
`Mageplaza\Model\Import` subclass superset columns (+city, +postcode/shipping_group round-trip
fix), unknown `city_code` fail-loud. (8) Deprecate `mode` config + `methodMapping`/`labels`.
(9) Tests + validator + evidence.

## 4. Constraints

- Dependency: `Launchpad_MageplazaTableRate → Secomm_ShippingCore → Mageplaza_TableRateShipping
  (+ Secomm_VietNamAddress/AddressDropdown cho address integration)`; FORBIDDEN
  `ShippingCore → Mageplaza/carrier`, `Launchpad → carrier`, carrier → Mageplaza/Launchpad.
- Không modify `vendor/` hay source `app/code/Mageplaza/` — chỉ plugin/preference/UI/layout
  extension + extension tables (Launchpad declarative schema + whitelist).
- No `ObjectManager` direct; `strict_types`; PHP 8.2–8.4; no N+1 (settings/membership load
  batched per collection); no SELECT *; controller chỉ validate+delegate.
- Collector: không DB/session/cache persistence; isolate từng lần collectRates; record() an toàn
  khi không begin (buffer-orphan, không leak).
- Member không cài/disabled/không tham gia collection ≠ UNAVAILABLE/TECHNICAL → không tự trigger
  fallback. Any participating member SUCCESS → suppress. Eligibility chỉ qua policy (không parse
  message; không convert AMBIGUOUS → TECHNICAL).
- City: `city_code` = `directory_region_city.code`; không city mapping = wildcard (legacy rows
  nguyên vẹn); AMBIGUOUS/UNMAPPED/non-VN → không guess; precedence không phá SUM/MIN/MAX.
- Storefront strings vi_VN + en_US; scope discipline §8 directive.

## 5. Rules / Acceptance

1. 4 tổ hợp capabilities hoạt động đúng (standalone/fallback-only/both/neither) qua outer seam —
   fallback-only không lộ storefront/REST/GraphQL.
2. Eligibility matrix: TECHNICAL_FAILURE(+GHTK UNAVAILABLE)→eligible; CANONICAL_AMBIGUOUS(+…)
   →eligible; UNMAPPED(+…)→no; SUCCESS member→suppressed; all-UNAVAILABLE→no; member không tham
   gia→no.
3. Visibility không đổi khi membership đổi; member method vẫn visible độc lập.
4. City: exact > region+wildcard > broader; no-city row = trước; SUM/MIN/MAX không regress;
   unresolved dest không match city-rows; CSV valid/invalid/round-trip.
5. Method/Member/Rate-city rows cascade khi Mageplaza delete; membership stale fail-soft.
6. Tests unit + integration theo §21 directive; validator `--check-specs --check-records` pass;
   evidence `.ai/evidence/TASK-5XQXZK/`.
7. Docs: README/CHANGELOG bridge + architecture doc amend (§9, §15–§18, §12, runtime diagram,
   §29 checklist).
