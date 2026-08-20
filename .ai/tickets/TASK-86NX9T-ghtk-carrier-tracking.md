# TASK-86NX9T — GHTK carrier tracking: webhook primary + Tracking API fallback + normalized status (ShippingCore reusable)

**Legacy ID:** SL-017 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (tracking slice của GHTK integration — không tách FEAT)
**Priority:** High
**Estimate:** ~16–22h
**Mode:** A (Tier-2: shipping + external webhook endpoint + shipment state + DB schema — AGENTS §9/§11/§12)
**Placement:** `app/code/Secomm/ShippingCore/` (tracking contracts + processor + state store) + `app/code/Secomm/Ghtk/` (mapper, webhook, API, cron)
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-08-17 · **Status:** Dev complete *(2026-08-17: Tasks 1-6 done, 144 unit tests green, table created, DI compile OK — chờ TL code review (Tier 2) + QC sandbox webhook; evidence: [TASK-86NX9T-evidence](../runtime/evidence/TASK-86NX9T/TASK-86NX9T-evidence.md))*

## Description

Tracking lifecycle cho `Secomm_Ghtk` theo hướng lean + Magento-native + reusable: GHTK webhook là nguồn update chính; GHTK Tracking Status API là fallback/reconciliation; cả hai đi qua **một** status-processing pipeline trong `Secomm_ShippingCore`; trạng thái carrier được normalize về model dùng chung; raw carrier status giữ lại. **Không** biến Ghtk thành OMS: carrier status ≠ Magento order status — không tự cancel/refund/creditmemo.

> Invariant: `Secomm_Ghtk` đồng bộ **carrier shipment state**; không sở hữu Magento order lifecycle. Webhook primary; API fallback — không aggressive polling.

## Acceptance Criteria

### ShippingCore tracking contracts (reusable cho GHN/Ahamove)
- [x] **AC-1 (Contracts):** `Api\Tracking\{NormalizedTrackingStatus (11 statuses), TrackingUpdateInterface, CarrierTrackingProcessorInterface, CarrierStatusMapperInterface}` — không chứa GHTK status code.
- [x] **AC-2 (Single pipeline):** `ShipmentTrackingProcessor::process(TrackingUpdateInterface)` là đường xử lý DUY NHẤT: find shipment track (carrier_code + tracking_number) → idempotency → out-of-order guard → normalize persist → native display update → optional domain events. Webhook + API + cron đều gọi processor này.
- [x] **AC-3 (Idempotent + ordering):** duplicate update (same normalized + carrier code + occurredAt) → no-op; **sticky terminal** (DELIVERED/RETURNED/CANCELLED không bao giờ bị downgrade); timestamp-priority khi payload có timestamp; DELIVERY_FAILED → IN_TRANSIT reattempt được phép (GHTK giao lại).
- [x] **AC-4 (Persistence):** bảng `secomm_carrier_tracking_state` (ShippingCore): carrier_code, tracking_number (UNIQUE cùng carrier_code), shipment_entity_id, normalized_status, carrier_status_code, carrier_status_message, carrier_status_updated_at, last_synced_at, source. Justification: native Track/sales không có status column; extension attribute persistent trên sales entity cũng cần table riêng; cron reconciliation cần query theo normalized_status + staleness — không query được trên track.description.

### GHTK tracking
- [x] **AC-5 (Track đã có):** TASK-KM6YAT đã persist track (carrier ghtk, number = tracking_code/label_id) — giữ nguyên; processor lookup theo đó.
- [x] **AC-6 (Status mapper):** `GhtkStatusMapper` map numeric GHTK status → normalized; unknown code → UNKNOWN + giữ raw + warning log; mapping tập trung một chỗ (không rải trong controller).
- [x] **AC-7 (Webhook):** `Controller/Webhook/Index.php` (route `ghtk/webhook/index`, POST, CSRF-exempt theo precedent Ahamove): Receive → Validate (structure + optional `webhook_secret` trong URL — GHTK không có documented signature, Q-EXT) → Parse → dispatch processor → **luôn respond 200 nhanh** (không bao giờ để GHTK retry vô hạn); không log secret/full payload customer data.
- [x] **AC-8 (Edge handling):** invalid payload / unknown tracking / shipment không tồn tại / duplicate / repeated status / out-of-order — tất cả xử lý graceful (200 + log level phù hợp), không uncontrolled exception.
- [x] **AC-9 (Tracking API fallback):** `GhtkApiClient::getOrderStatus(labelId)` + `TrackingRefreshService` (same processor); cron reconciliation nhẹ `Cron/RefreshTracking`: chỉ shipment carrier=ghtk, normalized_status NOT IN (DELIVERED, RETURNED, CANCELLED), last_synced cũ hơn threshold (config; cron **default OFF** — opt-in).
- [x] **AC-10 (No order-state mutation):** RETURNED/CANCELLED/DELIVERY_FAILED KHÔNG tự cancel/refund/creditmemo/recreate Magento order. Chỉ: tracking state + Track.description + shipment comment (visible admin) + domain events.
- [x] **AC-11 (Events):** emit `secomm_shipping_tracking_updated` (generic) + `secomm_shipment_carrier_delivered|returned|delivery_failed` cho downstream module (Magento EventManager — không subscriber nào trong scope này).
- [x] **AC-12 (Native UX):** admin thấy Carrier/Tracking/Status/Last updated qua **native** shipment view: Track.description được processor cập nhật ("GHTK: DELIVERED — … (17/08 15:00)") + comment ở key transitions. Không custom dashboard/timeline/map.
- [x] **AC-13 (Logging):** log fields: carrier, tracking number, shipment id, old→new normalized, carrier code, source (webhook/api); masked — không secret/PII.
- [x] **AC-14 (Tests):** theo §17: track-created (TASK-KM6YAT có sẵn — assert processor lookup), webhook valid→update, duplicate no-op, out-of-order giữ DELIVERED, unknown→UNKNOWN+raw, API fallback cùng path, returned/cancelled KHÔNG đụng order. Suite 110+ tests giữ green.
- [x] **AC-15 (Docs):** CHANGELOG ×2, README, i18n vi+en, evidence, webhook setup guide (GHTK dashboard URL + secret).

## Out of Scope (explicit)

- Auto cancel/refund/creditmemo/return workflow/re-ship (module khác consume events).
- Custom tracking timeline/dashboard/map, admin "Refresh Tracking" button (follow-up UI nếu cần — cron + webhook là cơ chế cập nhật).
- `ORDER_ID_EXIST` reconcile, GHTK label PDF fetch (giữ follow-up từ TASK-KM6YAT).
- GHN/Ahamove migration sang contracts này (chỉ đảm bảo reusable).
- Aggressive polling / queue consumer.

## Risks

- Q-EXT: GHTK webhook payload contract + status codes + Tracking API endpoint (`/services/shipment/v2/{label_id}`) chưa verify sandbox — mapper/parsers defensive, QC verify (gate go-live).
- Webhook auth: GHTK không có documented HMAC — secret-in-URL là pragmatic mitigation (bật qua config); flag security review.
- DB schema mới trong ShippingCore (Tier 2) — db_schema + whitelist, không đụng table có sẵn.
- Cron off mặc định — merchant cần opt-in (document).

## Related

- Plan: [TASK-86NX9T plan](../plans/TASK-86NX9T-implementation-plan.md) · Spec: [ghtk-carrier-tracking.md](../specs/SPEC-TASK-86NX9T-ghtk-carrier-tracking.md) · Decision: [DEC-TASK86NX9T-001](../records/decisions/DEC-TASK86NX9T-001.md) (accepted)
- Builds on: TASK-KM6YAT (track persistence, API client) + TASK-NDASAD (ShippingCore) · DEC-TASKKV328X-001 (webhook-first inbound — phần kept-deferred giờ hiện thực) · DEC-TASKKM6YAT-001 (no order-state mutation)
