# Implementation Plan: TASK-GKHXY1 — GHN-E1 Tracking + Webhook + Status Normalization

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-GKHXY1 (parent FEAT-FQWEQ3) — GHN-E1, lifecycle-only |
| Mode | A (public endpoint + security + provider lifecycle → Tier-2; plan approval = signoff) |
| Specification | Mini-Spec (embedded) trong [records/tasks/TASK-GKHXY1.md](../records/tasks/TASK-GKHXY1.md) — MINI, VALID (reuse ShippingCore tracking pipeline có sẵn — audit r2/r3) |
| Prerequisites | GHN-C closed · GHN-D closed (Track `secomm_ghn`:ghn_order_code gắn sẵn) · address-shipping.md v6 |
| Reuses | ShippingCore pipeline: `TrackingUpdate` VO · `NormalizedTrackingStatus` (11 statuses, terminal sticky) · `CarrierTrackingProcessorInterface`/`ShipmentTrackingProcessor` (dedupe/terminal/timestamp guards + `secomm_carrier_tracking_state` + events) · `CarrierStatusMapperInterface` · `CarrierTrackingFetcherInterface` + `TrackingReconciliationService` (virtualType) · Ghtk webhook/fetcher/cron precedent |
| Contract source | `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §11 webhook (PascalCase; Type ∈ create/switch_status/update_*; KHÔNG signature — merchant custom header; dedup OrderCode+Type+Time; 2xx=success, 4xx-drop) + §6 Order Info (`v2/shipping-order/detail?order_code=` → status) |

## Approach

Mirror Ghtk webhook/fetcher precedent, đúng ShippingCore pipeline có sẵn (0 edit):

1. `Model/Tracking/GhnStatusMapper implements CarrierStatusMapperInterface` — 23 GHN statuses →
   11 normalized explicit; unknown → UNKNOWN. (exception → UNKNOWN vì ambiguous; damage/lost/scrap
   → DELIVERY_FAILED — materially delivery-side failure, code giữ trong carrier_status_code.)
2. `Model/Tracking/WebhookPayloadParser` — PascalCase payload → `TrackingUpdate`: gate
   `Type=switch_status` + `OrderCode` + `Status` non-empty; occurredAt = strtotime(Time); raw
   sanitized (chỉ giữ OrderCode/ClientOrderCode/Status/Type/Time/Reason/ReasonCode). Non-status
   Type → null (ack-and-ignore).
3. `Controller/Webhook/Tracking` (frontName `secomm_ghn`, POST-only, CSRF-exempt in-controller —
   validated bằng secret thay thế): secret check `hash_equals` header `X-Secomm-Ghn-Secret` vs
   config (`carriers/secomm_ghn/webhook_secret`, obscure; empty = webhook off → 401 fail-closed —
   anti-defect legacy D8) → parser → processor → JSON response (200 ok/matched; 401 invalid_secret;
   200 invalid_payload — GHN drop permanent; 500 internal_error → GHN retry). Không order mutation.
4. `Model/Tracking/GhnTrackingFetcher implements CarrierTrackingFetcherInterface` — GET
   `v2/shipping-order/detail?order_code=` → status → cùng mapper → TrackingUpdate(api).
5. `Model/Tracking/TrackingRefreshService` + `Cron/RefreshTracking` + `etc/crontab.xml` (30',
   opt-in `tracking_refresh_enabled` default 0, threshold 6h) + di.xml virtualType
   `GhnTrackingReconciliation` (carrierCode `secomm_ghn`, batchSize 50).
6. `Model/Config` += `getWebhookSecret` + refresh getters; `GhnShipmentRepository::findByGhnOrderCode`; carrier docblock update (tracking UI = E3).

## Files affected

| File | Change |
|------|--------|
| `Model/Tracking/GhnStatusMapper.php` | new — 23-status map + UNKNOWN fallback |
| `Model/Tracking/WebhookPayloadParser.php` | new — PascalCase → TrackingUpdate (Type-aware) |
| `Model/Tracking/GhnTrackingFetcher.php` | new — Order Info API → TrackingUpdate(api) |
| `Model/Tracking/TrackingRefreshService.php` + `Cron/RefreshTracking.php` + `etc/crontab.xml` | new — opt-in reconciliation |
| `Controller/Webhook/Tracking.php` + `etc/frontend/routes.xml` | new — POST /secomm_ghn/webhook/tracking |
| `Model/Config.php` + `etc/config.xml` + `etc/adminhtml/system.xml` + `i18n/*` + `etc/di.xml` | modify — webhook_secret + refresh config + di virtualTypes |
| `Model/Shipment/GhnShipmentRepository.php` | modify — `findByGhnOrderCode` |
| `Model/Carrier/Ghn.php` | modify — docblock tracking defer E3 |
| `Test/Unit/**` | new — mapper/parser/fetcher/controller suites |

## Verification

Ghn+ShippingCore+VietNamAddress+Ghtk suites 0F/0E · compile · `--check-specs` · grep gates (0
setState/setStatus trên Order; 0 fallback/VietMap/Ghtk; secret không xuất hiện trong log) · runtime
local endpoint test: order+shipment thật (GHNS track) → curl POST switch_status delivering → state
OUT_FOR_DELIVERY; duplicate → no-op; delivered → terminal; unknown status → UNKNOWN; unknown
OrderCode → matched:false; secret sai → 401.
