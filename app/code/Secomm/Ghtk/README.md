# Secomm_Ghtk

Magento shipping carrier for **GHTK (Giao Hàng Tiết Kiệm)** —
`https://services.giaohangtietkiem.vn`. Rate collection + **Magento-native
shipping-label order submission** (SL-016): checking "Create Shipping Label"
submits the order to GHTK before the shipment is saved (failure aborts the
shipment); a normal shipment makes no GHTK API call.

## Features

- Carrier `ghtk`, method `ghtk_standard`; VN-only gate (AC-014).
- Rate collection: destination via SL-008 address mapping (`secomm_ghtk_address_map`,
  canonical key `(country_id, region_id, ward_id)` — DEC-020) with best-effort
  vi_VN fallback; weight via `ShipmentWeightCalculator` (DEC-022 — unit from
  config, normalized to grams); displayed amount via `RateComposer`
  (base `fee.fee` + admin multi-select `rate_include`).
- **Origin via `Secomm_ShippingCore` contract (SL-015 / DEC-SL015-001):**
  `ShippingContext` → `GhtkOriginProvider` (legacy `carriers/ghtk/pick_*`
  override chain; empty → inner `OriginProviderInterface` = Magento Shipping
  Origin) → `PickupAddressResolver` (strict DEC-021 gate; `pick_address_id`
  metadata preferred; regionId-based origins normalized to GHTK names through
  the same mapping machinery as destinations).
- API client `GhtkApiClient`: transport-only (HTTP/auth/timeout/retry, masked
  logging); payload assembly in `Model\Fee\FeeRequestMapper`.
- Reliability: graceful failure (never throws out of `collectRates`), short-TTL
  rate cache keyed on every fee-affecting parameter, admin connectivity check
  (`TestConnection`) using the same origin chain as checkout.
- Address-map CSV import (replace-all) with sample file, ACL resource
  `Secomm_Ghtk::config`.

## How it works (rate flow)

```
collectRates(RateRequest)
 → ShippingContext (Secomm_ShippingCore factory)
 → GhtkOriginProvider: legacy pick_* set? → legacy Origin (+ ghtk.pick_address_id
   metadata) : inner OriginProviderInterface (default: Magento Shipping Origin)
 → PickupAddressResolver: Origin → ?PickupAddress (metadata pick_address_id |
   regionId+ward normalize | names as-is | null → carrier hides)
 → DestinationAddressResolver → GhtkAddress
 → FeeRequestMapper → params → GhtkApiClient.getFee(params, storeId)
 → FeeResponseMapper → RateComposer → Rate\Result
```

Rate and label-submit resolve the origin through the same
`OriginProviderInterface` (`ShippingContextFactory::fromRateRequest()` /
`fromShipment()`) — no rate/submit origin drift. COD (`pick_money`) is resolved
by `CodAmountResolverInterface` (prepaid → 0; COD → `base_total_due`; partial
+ COD → fail-fast); `is_freeship = 1` (Magento charged shipping at checkout).
Submitted snapshot (partner id / label / pick_money / weight) is stored on the
shipment comment; tracking + label PDF persist through the native flow.

## Configuration

`Stores → Configuration → Sales → Delivery Methods → GHTK`. The four pickup
fields (`pick_address_id`, `pick_province`, `pick_district`, `pick_ward`) are a
**legacy override**: when all four are empty the origin falls back to the
Magento Shipping Origin (normalized to GHTK names). A partially filled override
invalidates the pickup (carrier hidden) rather than falling back silently.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --testsuite Magento_Unit_Tests_App_Code --filter 'Secomm\\Ghtk'
```

Covers: pickup resolver branches (metadata priority / id-based normalization /
names-only / strict invalid), origin provider BC chain, fee request mapping
(metadata + fallback address), extension point (custom origin provider drives
the carrier without modifying it), destination resolution, ward bridge, weight
calculator, fee response mapper, rate composer, CSV import.

## Address resolution — TEXT_NATIVE (TASK-7AJ3K8 r1 / DEC-TASK7AJ3K8-002)

Canonical Vietnamese names (`name_vi`) là representation MẶC ĐỊNH gửi GHTK (fee + create order
+ pickup name-path dùng chung `GhtkAddressAdapter`):

```
Magento address (region_id + native-city ward name)
  → VietNamAddress name-based bridge (canonical identity, AMBIGUOUS = candidates, không pick)
  → ShippingCore canonical orchestration (RuntimeAddressContextBuilder → handoffContext)
  → GhtkAddressAdapter (TEXT_NATIVE):
      canonical name_vi (ward unit + region unit)  ← DEFAULT
      override secomm_ghtk_address_map (exception rows, canonical-keyed)  ← optional
      AMBIGUOUS / UNMAPPED → null (KHÔNG gửi guessed address — fail closed)
  → GhtkAddress(province, district?, ward)
```

`secomm_ghtk_address_map` là **exception/override table** (không phải source of truth VN
identity, không mandatory): key canonical `(scheme_code, province_code, ward_code)`, các cột
override nullable, dataset RỖNG mặc định. Chỉ import exception rows khi evidence sandbox cho
thấy unit cụ thể cần text khác (CSV: `scheme_code,province_code,ward_code,ghtk_province,
ghtk_district,ghtk_ward,is_active,note`; validator kiểm tra canonical qua VietNamAddress
contracts). Bảng không còn runtime references → không cần scheme-swap guard.

Observability: `CANONICAL_ADDRESS_UNRESOLVED` / `GHTK_ADDRESS_INVALID` (rate-hidden reasons) ·
`GHTK_OVERRIDE_APPLIED` (debug). Log chỉ chứa unit codes, không street/telephone.

Transport HTTP dùng shared ShippingCore client (DEC-TASK7AJ3K8-001); fee GET retry theo
`retry_max`, create-order single attempt.

## Dependencies

`Secomm_ShippingCore` (origin contract), `Secomm_AddressDropdown` (ward data
tables), `Secomm_VietNamAddress` (VN scheme-swap reference guard contract —
this module registers `secomm_ghtk_address_map` via `Model/Import/DirectoryReferenceGuard`,
DEC-FEATYA2C0W-004), Magento Backend/Config/Directory/Shipping.

## Tracking (SL-017)

- **Webhook (primary):** `POST /ghtk/webhook/index` — configure in the GHTK
  dashboard; append `?secret=<value>` matching the `webhook_secret` config when
  set (GHTK provides no request signing). Always responds HTTP 200; duplicates,
  out-of-order and unknown statuses are handled gracefully by the shared
  ShippingCore pipeline.
- **Status mapping:** `Model\Tracking\GhtkStatusMapper` (single table; unknown
  GHTK codes → UNKNOWN, raw retained).
- **Reconciliation fallback:** `GhtkApiClient::getOrderStatus()` +
  `TrackingRefreshService` + `Cron\RefreshTracking` — opt-in via
  `tracking_refresh_enabled` (default off; webhook is primary). Only stale,
  non-terminal shipments are re-checked.
- Statuses surface natively: shipment track description + comments history.
  Magento order state is never auto-changed (no auto cancel/refund) —
  downstream modules may consume the dispatched domain events.
