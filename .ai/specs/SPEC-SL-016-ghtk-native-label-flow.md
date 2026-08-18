# Spec: GHTK Order Submit qua Magento Native Shipping-Label Flow (SL-016)

Specification ID: SPEC-SL-016
Feature ID: NONE
Specification Level: FULL

> **Status:** Implemented — retro-canonical (distilled from approved requirement 2026-08-17 + DEC-SL016-001 + delivery evidence; verified against working tree 2026-08-18).
> **Mode A** · Tier 2 · Tickets: SL-016 (redirect SL-010) · Decision: DEC-SL016-001 (accepted, supersedes DEC-023 outbound)

# Purpose

GHTK order submission tận dụng 100% Magento native shipment + shipping-label lifecycle — không tạo parallel Online/Offline framework, không outbox. Trigger duy nhất là merchant check **Create Shipping Label**.

# Scope

- `Ghtk extends AbstractCarrierOnline`; `requestToShipment()` override (aggregated single submission).
- `OrderSubmitService` + COD resolver + order payload/response mappers + label PDF generator.
- `GhtkApiClient::submitOrder()` (POST, single attempt).

# Out of Scope

Webhook inbound status sync (SL-017 thực hiện riêng) · GHTK label PDF fetch (dùng generated PDF) · `ORDER_ID_EXIST` auto-reconcile · COD allocation engine / split fulfillment · Ahamove refactor.

# Actors / Context

Merchant (admin shipment create + package popup) · GHTK API (sandbox verify — Q-EXT) · carrier label flow native (LabelGenerator/Labels).

# Business Rules

- BR-LB-01: **Normal shipment (không label) = Magento-only, ZERO GHTK API call.** Không observer `shipment_save_after`, không auto-push.
- BR-LB-02: **Label flow**: native flow → submit trước khi shipment save → fail ⇒ shipment KHÔNG được save (no false success — native semantics).
- BR-LB-03 (COD): `CodAmountResolverInterface` tách khỏi mapper — prepaid/non-COD → `pick_money = 0`; COD (method ∈ config list) → `base_total_due` (deposit/partial-payment-safe). KHÔNG hard-code `grand_total`.
- BR-LB-04 (is_freeship): luôn `1` — Magento đã charge shipping tại checkout; GHTK không thu thêm tại cửa (không double-charge).
- BR-LB-05 (Partial + COD): fail-fast `LocalizedException` — không gửi full-order COD nhiều lần; KHÔNG build allocation engine.
- BR-LB-06 (Origin): qua SL-015 chain (`fromShipment()` → provider); shipper contact từ native `Shipment\Request` (admin user + store_information).
- BR-LB-07: Rate calculation độc lập shipment creation — dùng GHTK rate ≠ tạo GHTK order.

# System Behaviour

- `pick_money` = resolved COD; `is_freeship = 1`; `value` = subtotal item trong shipment; weight = Σ package weights (fallback product weights, gram — DEC-022); partner ORDER_ID = `ghtk-{order_increment}-{seq}` (seq = shipments đã save + 1 — deterministic, shipment-id-independent).
- POST single-attempt (no auto-retry — no duplicate-order risk); `ORDER_ID_EXIST` → error rõ + hướng dẫn manual check.
- Success → tracking + label PDF qua native persist (`sales_shipment_track` + `shipping_label`); snapshot (partner/label/pick_money/weight) vào shipment comment — sau này không re-derive COD từ `grand_total`.

# Main Flows

```
Create Shipment (không label)  → chỉ Magento Shipment, no GHTK call
Create Shipment + label        → native → OrderSubmitService → GHTK → tracking/label native
```

# Edge Cases

Half-COD-partial · deposit đã trả một phần (thu phần còn lại) · GHTK success nhưng thiếu label/tracking (abort + cảnh báo check dashboard trước khi retry) · thiếu store_information (native precondition error) · multi-package admin UI → 1 GHTK order (Σ weight).

# Contracts / Invariants

> Carrier API creation gắn với Magento shipping-label flow, không chỉ việc shipment được save.
> `Secomm_Ghtk` đồng bộ carrier shipment state — không sở hữu Magento order lifecycle.

# Failure Behaviour

API fail/reject/timeout → `LocalizedException` → shipment không save, admin thấy error rõ, không track/label rác; timeout ≤ ~5s (config); masked log.

# Acceptance Criteria

AC-1..15 theo [ticket SL-016](../tickets/SL-016-ghtk-native-label-flow.md) (native carrier online; normal-no-API; submit đúng payload; no false success; native persistence; origin qua provider; COD resolver; is_freeship; partial fail-fast; partner id; tests; config; docs). Evidence: `.ai/runtime/evidence/SL-016/`.

# Related Decisions

DEC-SL016-001 (accepted — supersedes DEC-023 outbound) · DEC-SL015-001 · DEC-022 (weight) · DEC-024 partial-resolve (B1/B3/B4/B6/B7/B8/B14).
