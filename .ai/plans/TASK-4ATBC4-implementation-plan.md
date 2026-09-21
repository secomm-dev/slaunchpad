# Implementation Plan: TASK-4ATBC4 — GHN-E2 Cancel + Return APIs

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-4ATBC4 (parent FEAT-FQWEQ3) — GHN-E2, lifecycle actions |
| Mode | A (provider mutation money-adjacent → Tier-2; plan approval = signoff) |
| Specification | Mini-Spec (embedded) trong [records/tasks/TASK-4ATBC4.md](../records/tasks/TASK-4ATBC4.md) — MINI, VALID |
| Contract source | `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §9 Cancel (batch all-or-nothing, reason_code enum, HTTP200+result:false) + §10 Return (per-order best-effort, eligible states) + sandbox cancel result:true ×7 |
| Out of Scope | Admin buttons + getTracking + label (E3) · polling · MQ · legacy cutover (F) · COD/refund/RMA · Magento order-state orchestration · ShippingCore edit |

## Approach

Domain services mirror `GhnCreateOutcome`/`GhnShipmentCreationService` precedent: resolve provider
identity từ `secomm_ghn_shipment` (SUBMITTED + ghn_order_code) → validate reason enum fail-closed →
POST qua `GhnApiClient` (mutation: KHÔNG retry) → đọc per-order `data[0].result` (HTTP 200 không
tự động success) → `GhnActionOutcome`. Trigger = CLI (mirror RetryShipmentCommand); sau cancel
SUCCESS chạy E1 reconcile (fetcher → processor) sync CANCELLED. KHÔNG schema change (audit =
GhnLogger; lifecycle = E1 pipeline).

## Files affected

| File | Change |
|------|--------|
| `Model/Client/GhnEndpoints.php` | += `RETURN_ORDER = 'v2/switch-status/return'` |
| `Model/Shipment/GhnActionOutcome.php` | new VO — SUCCESS/BUSINESS_REJECTED/TECHNICAL_FAILURE/UNKNOWN_RESULT + action + orderCode + reasonCode + message |
| `Model/Shipment/GhnCancelService.php` | new — resolve → validate → POST cancel → outcome; reason enum fail-closed |
| `Model/Shipment/GhnReturnService.php` | new — POST return → outcome; provider-state rejection normalize |
| `Console/Command/CancelShipmentCommand.php` + `ReturnShipmentCommand.php` + `etc/di.xml` | new — CLI cancel/return + cancel chạy E1 reconcile sau SUCCESS |
| `Test/Unit/Model/Shipment/{GhnCancelServiceTest,GhnReturnServiceTest,GhnActionOutcomeTest}.php` | new — §35 case matrix |

## Verification

Ghn+ShippingCore+VietNamAddress+Ghtk suites 0F/0E · compile · validator · grep gates (0 order
mutation / 0 tracking-state direct write / 0 payment inspection) · sandbox: cancel fresh order
(A success, B repeat idempotent, C invalid-state rejection), return eligible-state probe · E1
reconcile sau cancel → CANCELLED qua pipeline.
