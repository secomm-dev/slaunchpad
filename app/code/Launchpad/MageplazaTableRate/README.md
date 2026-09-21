# Launchpad_MageplazaTableRate

TASK-5XQXZK (DEC-TASK5XQXZK-001) — Launchpad-specific composition around
`Mageplaza_TableRateShipping` v4.0.8: **Mageplaza TableRate Method = fallback grouping
identity** with per-method capabilities, realtime-membership gating and an optional
City/Area dimension, on top of the Magento-native carrier orchestration.
TASK-JZXM66 adds the City/Area admin self-service flows below.

Dependency direction (never reversed):

```text
Launchpad_MageplazaTableRate → Secomm_ShippingCore      (collector + eligibility policy + fallback contracts)
Launchpad_MageplazaTableRate → Mageplaza_TableRateShipping (internal calculation engine)
Launchpad_MageplazaTableRate → Secomm_VietNamAddress    (city identity resolution)

FORBIDDEN: ShippingCore → Mageplaza · Launchpad → any carrier module · carriers → Mageplaza
```

## Runtime model

```text
Magento Shipping Framework (sole RateCollectorInterface preference)
        │  normal carrier collection (GHN / GHTK / … as installed & enabled)
        │       each carrier RECORDS its normalized outcome into
        │       Secomm_ShippingCore CarrierRateOutcomeCollector (per-collection bracket)
        ▼
Plugin\Shipping\CollectRatesPlugin  (the ONE outer seam — storefront + REST + GraphQL + admin)
        ├── MethodVisibilityFilter      — hides show_to_customer=0 methods
        └── FallbackCoordinator         — per fallback group (use_as_fallback=1):
              no participating member   → no fallback
              any member SUCCESS        → suppressed
              ≥1 policy-eligible outcome → internal TableRate price appended
```

Carriers report FACTS (status + structured `ShippingFailureReason`); ShippingCore owns the
POLICY (`SafeDegradationEligibilityPolicy`: TECHNICAL_FAILURE + CANONICAL_AMBIGUOUS eligible;
UNMAPPED / unsupported / config errors NOT). No fan-out registry, no carrier ranking, no
message parsing. Membership NEVER changes carrier visibility (directive §10).

## Per-method settings (admin)

Sales → Table Rate Methods → edit → **Launchpad Settings** tab:

| Field | Meaning |
|---|---|
| Show to Customer | standalone checkout exposure (Mageplaza carrier must be active) |
| Use as Fallback | method is a fallback group whose members gate the emergency price |
| Fallback Members | realtime Magento shipping methods (dynamic options; `mptablerate` excluded) |

Methods without a settings row behave natively (visible, never fallback) — zero regression.
The legacy global `launchpad_mptablerate/general/mode` config and the DI
service-level→method mapping were REMOVED (no production data existed).

## City / Area dimension

- Optional constraint per rate row: extension table `launchpad_mptablerate_rate_city`
  (`rate_id`, stable `city_code` = `directory_region_city.code` / Secomm unit code).
- Precedence narrows LOCATION scope only; rows inside the winning tier combine with the
  method's own SUM/MIN/MAX exactly as Mageplaza does. No city mapping anywhere → 100% legacy
  behavior. Unresolvable destination (AMBIGUOUS/UNMAPPED/non-VN) → city rows never match,
  wildcard tiers untouched.
- Rate form field "City / Area", grid column, CSV column `city_code` (unknown code → row
  fails validation loudly). The importer (preference subclass) also accepts
  `postcode`/`postcode_from`/`postcode_to`/`shipping_group`, fixing Mageplaza's own
  export→import round-trip gap.

## City / Area setup (admin self-service)

TASK-JZXM66 — merchants configure the City/Area dimension themselves; `city_code` is the
stable system identity (Secomm address-node code) that admins normally never type manually.

### Manual flow (single rate rows)

1. Sales → Table Rate Methods → edit a method → **Add TableRate row**.
2. Pick **Country** and **State/Region** — the **City / Area** select fills automatically
   (AJAX `launchpad_mptablerate/city/options`, ACL `Mageplaza_TableRateShipping::method`).
3. Select a City/Area node, or keep **All / \\*** (wildcard, empty `city_code`) to apply the
   row to the whole region; fill the pricing fields; **Save**.
4. On edit, the stored code resolves to its display label. A stored code that no longer
   exists in the address hierarchy keeps its raw value and shows a red warning
   ("Stored City/Area code … no longer exists — please re-select or clear") — it is never
   silently turned into a wildcard.

Save-time validation (rate form and importer): unknown `city_code` → row rejected; a coded
city that does not belong to the row's concrete region → rejected
("City / Area … does not belong to the selected region" / "Row …: … does not belong to
region …").

### Bulk flow (CSV import)

1. In the rate form, download **City Reference CSV**
   (`launchpad_mptablerate/city/referenceCsv`, optional `?region=<region_id>`): the live
   `directory_region_city` identity list — columns exactly
   `country_code, region_code, region_name, city_code, city_name, parent_city_code,
   parent_city_name` (UTF-8 + BOM; rows without a stable code are never exported).
2. Download **Import Template** (`launchpad_mptablerate/city/importTemplate`): the importer's
   exact column superset plus a trailing informational `city_name` column, pre-filled with a
   worked example row (real city code of the requested region) and a wildcard demonstration
   row.
3. Fill pricing per row, keep `city_code` (or leave it empty together with `region=\\*` for
   wildcard rows) and import through the Mageplaza CSV import; mismatches fail per row and
   are listed in the import result.

### Identity contract

- CSV identity is `city_code` (stable, portable across datasets/locales) — `city_name` is
  display-only, never persisted, and stripped by the importer.
- Wildcard = empty `city_code` (form) or empty + `region=\\*` (CSV) — unchanged semantics.

## Tables (Launchpad-owned, FK ON DELETE CASCADE into Mageplaza entities)

```text
launchpad_mptablerate_method_setting (method_id PK/FK, show_to_customer, use_as_fallback)
launchpad_mptablerate_method_member  (member_id, method_id FK, carrier_code, method_code, enabled; UNIQUE triple)
launchpad_mptablerate_rate_city      (rate_id PK/FK, city_code)
```

## Engineering rule

> The bridge calculates fallback PRICE only — ShippingCore policy + Magento outcome facts
> decide WHEN. Mageplaza TableRate is a fallback PRICE source, never a service-eligibility
> engine; carrier methods are never hidden by membership; admin decides the grouping.
