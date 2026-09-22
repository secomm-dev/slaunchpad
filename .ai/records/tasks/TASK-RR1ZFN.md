---
id: TASK-RR1ZFN
type: task
title: 'Phase GHN-E — Tracking webhook (secret + dedup) + GhnStatusMapper + reconciliation qua ShippingCore pipeline'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec (slice reference; đặc biệt §23..§26, §45)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
risk: medium                  # ingress endpoint public + tracking pipeline có sẵn
status: proposed
priority: medium
decision_assessment: none-material   # shape theo SPEC + DEC-TASK3F6QWZ-002 (pipeline chung) đã accepted
decisions: [DEC-FEATFQWEQ3-001, DEC-TASK3F6QWZ-002]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-9Q5ZAK, TASK-8019VC]
---

# [SLP][FEAT-FQWEQ3][TASK-RR1ZFN] Phase GHN-E — Tracking webhook (secret + dedup) + GhnStatusMapper + reconciliation qua ShippingCore pipeline

**BLOCKED** tới khi GHN-D done (cần `ghn_order_code` + persistence để match webhook → shipment).

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §23 Webhook, §24 No Order Status Ownership, §25 Event Audit, §26 Reconciliation, §45 AC-TRACK)*

### Goal

Webhook ingress GHN mới (PascalCase payload, event types `create|switch_status|update_weight|
update_cod|update_fee|update_payment_type|cod|update_partial_return`), idempotent, dedup, translate
status qua `GhnStatusMapper` → `CarrierTrackingProcessorInterface` — KHÔNG mutate `sales_order`
state trực tiếp. Thay webhook legacy không-auth (R13).

### Expected Behavior

1. Endpoint `POST /secomm_ghn/webhook/...` (frontName riêng, không đụng `/ghn/...` legacy);
   validate secret header (verify GHN portal support lúc implement — nếu không có HMAC: residual
   risk ghi runbook + shop_id/order-code sanity check bắt buộc); CSRF-aware như pattern webhook
   khác nhưng có auth layer riêng.
2. Parse → validate shape → dedup theo `OrderCode + Type + Time` (webhook retry-safe);
   optional `secomm_ghn_event` (payload_hash, sanitized payload, processing_status) cho audit/
   reconciliation — không lưu raw payload vô thời hạn (SPEC §25).
3. `GhnStatusMapper implements CarrierStatusMapperInterface`: raw GHN status →
   `NormalizedTrackingStatus` (reuse MAP knowledge legacy: ready_to_pick→CREATED … delivered→
   DELIVERED, return*→RETURNING/RETURNED, cancel→CANCELLED); status lạ/terminal ngoài tập →
   UNKNOWN + raw status/reason luôn lưu (R7 sticky-terminal review).
4. Push `TrackingUpdateInterface` (source=webhook) qua `CarrierTrackingProcessorInterface` — dup
   no-op, sticky terminal, persist `secomm_carrier_tracking_state`; KHÔNG setState/setStatus trên
   `sales_order` (AC-TRACK-004).
5. Reconciliation: Order Info dùng cho manual refresh (admin) + missed-webhook recovery + scheduled
   reconciliation opt-in (SPEC §26) — không polling mọi order; source=api.

### Constraints / Rules

- Webhook 4xx drop retry GHN — phải trả 200 cho event đã nhận/dedup, 4xx chỉ cho payload sai.
- Không log token/PII; sanitized payload only.
- Không business order state transition trong module (orchestration layer-owned, SPEC §24).

### Out of Scope

Cutover webhook URL production (GHN-F) · Magento order state mapping (orchestration) · legacy
`ghn_webhook_track` migration (GHN-F, chỉ khi yêu cầu).

### Acceptance Criteria

- AC-E1: duplicate webhook cùng OrderCode+Type+Time xử lý 1 lần (idempotency test).
- AC-E2: status map test (golden payloads cho các event types); unknown status → UNKNOWN + raw lưu.
- AC-E3: TrackingUpdate đi vào ShippingCore processor; `sales_order` state không đổi trực tiếp.
- AC-E4: secret verification test (missing/wrong header → 4xx); reconciliation refresh từ Order Info test.
- AC-E5: phpunit scoped green; QC staging nhận webhook thật từ GHN portal config.
