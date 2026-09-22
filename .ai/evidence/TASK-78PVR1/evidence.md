# Evidence — TASK-78PVR1: bridge adaptation to ShippingCore v10 (2026-09-18)

## Suites
- Secomm_ShippingCore: **309 tests OK** (policy v10 matrix +6 asserts)
- Launchpad_MageplazaTableRate: **38 tests OK** (FallbackCoordinatorTest 16 = §8 matrix mới:
  CARRIER_ONLY+technical→no · FALLBACK_ONLY→eligible trực tiếp · AMBIGUOUS+STRICT→no ·
  AMBIGUOUS+FALLBACK→yes · PROVIDER_MAPPING_MISSING→yes (v10) · SUCCESS suppress giữ)
- Secomm_Ghn: **333 tests OK** (GHN v10 contracts intact)
- `setup:di:compile` PASS

## Contracts consumed (frozen v10)
- `Api\Rate\RateSourceMode` (CARRIER_ONLY | CARRIER_WITH_FALLBACK | FALLBACK_ONLY)
- `Api\Address\AddressResolutionPolicy` (STRICT | FALLBACK | PICK_PRIMARY) — chỉ ĐỌC config;
  selection/ranking nằm ở shared handoff/selector (GHN pass-through, bridge không inspect)
- `Api\Fallback\FallbackEligibilityPolicyInterface` → `SafeDegradationEligibilityPolicy`
  (updated §35.5: PROVIDER_MAPPING_MISSING = INTEGRATION_LIMITATION → eligible; auth/config
  configurable default OFF)
- `Api\Rate\CarrierRateOutcomeCollectorInterface` — KHÔNG bị supersede; carriers vẫn cần

## Ownership check
- Bridge KHÔNG tự quyết TECHNICAL/AMBIGUOUS/MAPPING eligibility — chỉ gate mode/policy config
  (shared paths `carriers/<code>/{rate_source_mode,address_resolution_policy}`) rồi delegate
  ShippingCore policy contract.
- Legacy: bridge production code 0 reference LegacyRateStrategy/DIRECT_FALLBACK/
  MAP_THEN_FALLBACK (grep sạch); compatibility symbols chỉ còn trong ShippingCore (v9 supersede).
- FALLBACK_ONLY: không cần synthetic failure — coordinator coi member mode FALLBACK_ONLY là
  eligible trực tiếp (§35.6); carrier thật (GHN v10) short-circuit API + record SKIPPED outcome.

## Architecture
`address-shipping.md` thêm v10 bridge-adaptation note (ownership + delta), đặt trên Revision
v10 block.
