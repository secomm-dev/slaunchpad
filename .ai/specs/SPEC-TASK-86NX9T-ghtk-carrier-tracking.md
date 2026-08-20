# Spec: GHTK Carrier Tracking — Webhook Primary + API Reconciliation (TASK-86NX9T)

Specification ID: SPEC-TASK-86NX9T
Feature ID: NONE
Specification Level: FULL

> **Status:** Implemented — retro-canonical (distilled from approved requirement 2026-08-17 + DEC-TASK86NX9T-001 + delivery evidence; verified against working tree 2026-08-18).
> **Mode A** · Tier 2 · Tickets: TASK-86NX9T · Decision: DEC-TASK86NX9T-001 (accepted)

# Purpose

Tracking lifecycle cho GHTK: lưu tracking sau label submit, nhận trạng thái vận chuyển từ webhook (primary), fallback bằng Tracking Status API (reconciliation), normalize về status model dùng chung trong `Secomm_ShippingCore` — reusable cho GHN/Ahamove. Không biến Ghtk thành OMS.

# Scope

- ShippingCore: tracking contracts + single processing pipeline + `secomm_carrier_tracking_state`.
- Ghtk: webhook receiver + status mapper + Tracking API client method + refresh service + cron (opt-in).

# Out of Scope

Auto cancel/refund/creditmemo/return workflow/re-ship (downstream module consume events) · custom tracking dashboard/timeline/map · admin Refresh button · aggressive polling · GHN/Ahamove migration.

# Actors / Context

GHTK webhook (gọi store khi status đổi) · GHTK Tracking Status API · merchant (xem trạng thái qua native UI) · downstream module (consume domain events).

# Business Rules

- BR-TR-01: **Webhook là primary**; Tracking API chỉ reconciliation/fallback — không aggressive polling.
- BR-TR-02: Webhook + API + cron đi qua **một** status-processing pipeline duy nhất (không duplicate logic).
- BR-TR-03: **Carrier status ≠ Magento order state** — KHÔNG tự cancel/refund/creditmemo/recreate order. Chỉ: tracking state + Track.description + shipment comment + domain events.
- BR-TR-04 (Idempotency + ordering): duplicate update = no-op; **sticky terminal** (DELIVERED/RETURNED/CANCELLED không downgrade); timestamp-priority khi payload có timestamp; DELIVERY_FAILED → IN_TRANSIT reattempt được phép.
- BR-TR-05 (Unknown status): → UNKNOWN + giữ raw code/message + warning log — không fail integration.

# System Behaviour

- Normalized statuses (11): CREATED, PICKING, PICKED_UP, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, DELIVERY_FAILED, RETURNING, RETURNED, CANCELLED, UNKNOWN; raw carrier status lưu song song (debug/reconciliation).
- GHTK status map tập trung một chỗ (`GhtkStatusMapper`) — không rải mapping.
- Webhook `POST /ghtk/webhook/index`: Receive → Validate (structure + optional secret URL) → Parse → dispatch processor → **luôn HTTP 200 nhanh** (không để GHTK retry vô hạn); không log secret/PII.
- Cron reconciliation (*/30, **default OFF**): chỉ shipment non-terminal + stale quá threshold.
- Admin thấy Carrier/Tracking/Status/Last updated qua **native** UI (Track.description + Comments History).

# Main Flows

```
Label submit (TASK-KM6YAT) → tracking number persisted (native track)
GHTK webhook → parser → mapper → TrackingUpdate → Processor
Tracking API / cron → cùng mapper → cùng Processor
Processor: find track → idempotency/ordering → persist state → Track.description + comment → events
```

# Edge Cases

Invalid JSON / thiếu field / unknown tracking / sai secret / shipment không tồn tại → 200 graceful + log · out-of-order (webhook cũ sau DELIVERED) → không rollback · repeated same status → no-op.

# Contracts / Invariants

> `Secomm_Ghtk` chịu trách nhiệm đồng bộ carrier shipment state, không sở hữu Magento order lifecycle.
> Webhook primary; API reconciliation; một pipeline chung; không carrier code trong ShippingCore.

Events: `secomm_shipping_tracking_updated` + `secomm_shipment_carrier_{delivered,returned,delivery_failed}`.

# Failure Behaviour

Processor không throw ra ngoài webhook (luôn 200); cron per-item failure → log + continue; API failure không crash cron.

# Acceptance Criteria

AC-1..15 theo [ticket TASK-86NX9T](../tickets/TASK-86NX9T-ghtk-carrier-tracking.md) (contracts; single pipeline; idempotent+ordering; persistence justification; mapper; webhook + edges; API fallback; no order-state mutation; events; native UX; logging; tests; docs). Evidence: `.ai/runtime/evidence/TASK-86NX9T/`.

# Related Decisions

DEC-TASK86NX9T-001 (accepted) · DEC-TASKKM6YAT-001 (track persistence, no order mutation) · DEC-TASKKV328X-001 (webhook-first inbound — phần kept-deferred giờ hiện thực).
