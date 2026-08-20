# TASK-KV328X — GHTK order/shipment sync (idempotent async outbox, persistence model, business rules)

**Legacy ID:** SL-010 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (sub-ticket of feature [FEAT-AE761Z](../records/features/FEAT-AE761Z.md); scope gate)
**Priority:** Medium (sau TASK-YJENM2/009)
**Estimate:** ~16–24h
**Mode:** A (Tier-2: order/shipment lifecycle + external API + COD/payment-interaction + DB schema)
**Feature:** [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) (GHTK carrier)
**Placement:** `app/code/Secomm/Ghtk/`
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-07-30 · **Status:** 🔁 Redirected 2026-08-17 → [TASK-KM6YAT](TASK-KM6YAT-ghtk-native-label-flow.md) (native shipping-label flow, DEC-TASKKM6YAT-001 supersedes DEC-TASKKV328X-001 outbound outbox)

> **REDIRECT 2026-08-17:** order submit hiện thực theo **Magento native shipping-label flow** (label-triggered, sync trong label request) — xem TASK-KM6YAT + DEC-TASKKM6YAT-001. Outbox/cron/webhook inbound của ticket này KHÔNG build; webhook status sync vẫn deferred như cũ. Các business rule DEC-TASKKV328X-002 được resolve mặc định trong DEC-TASKKM6YAT-001 (B3 is_freeship=1, B8 partner id, B14 trigger=label; B1/B4/B6/B7 defaults; B9 cancel vẫn out-of-phase).



> **PARKED (user 2026-07-30):** order sync KHÔNG hiện thực phase này — chỉ làm phần **fee** (TASK-YJENM2 + TASK-BRKHN4) trước. Ticket này giữ requirement/design parked để resume sau. DEC-TASKKV328X-001 (accepted) + DEC-TASKKV328X-002 (proposed) không block fee delivery. Khi resume: chốt DEC-TASKKV328X-002 B1/B4/B8/B14 + COD/pick_money tension.

## Description

Đồng bộ Magento shipment → GHTK order (`POST /services/shipment/order`) theo pattern **idempotent async outbox** (DEC-TASKKV328X-001): KHÔNG gọi GHTK order API bên trong transaction tạo Magento shipment. Ghi sync record trong cùng transaction (DB only) → async cron-backed consumer gọi GHTK → persist trạng thái + label/tracking → retry có kiểm soát + manual retry. Business rules (COD/declared value/freeship/partial/cancel/trigger) pending approval (DEC-TASKKV328X-002).

## Flow (DEC-TASKKV328X-001 — accepted 2026-07-30, webhook-first)

**Outbound (Magento → GHTK):**
```
Magento shipment save thành công
→ ghi sync record (outbox) trong cùng transaction (DB only, no external call)
→ cron consumer gọi GHTK order API (precedent: Secomm_ZaloPay RefundCronjob; MQ queue = deferred)
→ persist status + GHTK label/tracking code
→ retry có kiểm soát (state machine) + hỗ trợ manual retry (admin)
```
**Inbound (GHTK → Magento) — release-1 Core (webhook):**
```
GHTK status change → gọi webhook endpoint Magento
→ reconcile với secomm_ghtk_shipment (idempotent qua partner_order_id)
→ update Magento shipment track/status
→ endpoint return 200 nhanh; processing sync/cron now, MQ queue sau
```

## Persistence model

`secomm_ghtk_shipment` (DEC-TASKKV328X-001):
| Column | Type | Note |
|---|---|---|
| `entity_id` | int PK | |
| `shipment_id` | int | Magento shipment (UNIQUE) |
| `order_id` | int | Magento order |
| `partner_order_id` | varchar | deterministic, stable qua retry (UNIQUE) |
| `ghtk_label` | varchar/null | label returned |
| `status` | varchar | state machine |
| `attempt_count` | int | retry counter |
| `request_hash` | varchar | detect same-data retry vs material change |
| `last_error_code` | varchar/null | GHTK error code |
| `last_error_message` | varchar/null | masked |
| `synced_at` | timestamp/null | success time |
| `created_at` / `updated_at` | timestamp | |

Constraints: `UNIQUE(shipment_id)`, `UNIQUE(partner_order_id)`.

**State machine (tối thiểu):** `pending → processing → synced` | `retryable_error → (retry) → processing` | `failed` | `cancelled`.

## Acceptance Criteria

### Async + idempotency (DEC-TASKKV328X-001)
- [ ] **AC-1 (No sync call in shipment-save):** shipment save KHÔNG gọi GHTK order API; chỉ ghi outbox record trong cùng transaction (DB only).
- [ ] **AC-2 (Async consumer):** cron-backed consumer (precedent `Secomm_ZaloPay`) gọi GHTK order API cho record `pending`/`retryable_error`; persist trạng thái. (Magento MQ là upgrade path nếu RabbitMQ cấu hình sau — KHÔNG over-engineer.)
- [ ] **AC-3 (Idempotency):** cùng shipment không tạo nhiều GHTK orders (`UNIQUE(shipment_id)` + state guard). Retry **reuse deterministic `partner_order_id`**. `ORDER_ID_EXIST` từ GHTK **không mặc định fatal** → reconcile (fetch status; nếu synced → update local, không tạo mới). `request_hash` phát hiện retry cùng dữ liệu (idempotent skip) vs thay đổi material (re-submit).
- [ ] **AC-4 (Label/tracking persistence):** persist `ghtk_label` + tracking vào `secomm_ghtk_shipment` + Magento shipment track.
- [ ] **AC-5 (Retry + manual retry):** retry có kiểm soát theo state machine (`attempt_count`, backoff đề xuất); admin manual retry (tái submit cùng `partner_order_id`).
- [ ] **AC-5b (Inbound webhook status sync — release-1 Core, DEC-TASKKV328X-001):** GHTK webhook endpoint nhận status change → reconcile idempotent (`partner_order_id`) → update `secomm_ghtk_shipment` + Magento shipment track/status. Endpoint return 200 nhanh; processing sync/cron now (MQ queue deferred). Webhook + outbox share reconcile path.

### Order request building (reuses TASK-YJENM2/009 resolvers + weight)
- [ ] **AC-6 (Request builder):** build order payload dùng `DestinationAddressResolver` (TASK-YJENM2) + `PickupAddressResolver` (TASK-BRKHN4) + `ShipmentWeightCalculator` (TASK-BRKHN4). street/hamlet từ address field (DEC-TASKKV328X-002 B5).

### Business rules (DEC-TASKKV328X-002 — partial)
- [ ] **AC-7 (Business rules implemented per approval):** COD source (B1, **pending** — kèm COD/pick_money tension) · ~~prepaid `pick_money` (B2)~~ **deferred** · ~~`is_freeship` (B3)~~ **deferred** (implement sau nếu khách yêu cầu) · declared value (B4, **pending**) · partial shipment (B6, **pending**) · multi-package (B7, **pending**) · partner order ID format (B8, default, **pending**) · ~~cancel boundary (B9)~~ **out-of-phase (để ngỏ)** · label persistence (B10, **pending**) · order edit/recreate (B12, **pending**) · return/RMA out-of-scope (B13) · sync trigger timing (B14, **pending**). Mỗi rule pending implement theo default **chỉ khi approved**; pending → ghi pending, KHÔNG đoán. Release-1 gửi default `pick_money=0`/`is_freeship=0` (trừ khi COD B1 approved với basic pick_money).
- [ ] **AC-8 (External contract verify):** order-API field names/types (COD, `pick_money`, `is_freeship`, declared value, label return) verify từ GHTK doc VN/sandbox trước hiện thực mapper (DEC-TASKKV328X-002 Q-EXT). KHÔNG bịa field/behavior.

### Security (DEC + AGENTS §7.2/§7.4)
- [ ] **AC-9 (Logging):** mask Token · phone · email · full address · customer PII; **không log raw order payload/decrypted token**. Production log: `correlation_id` · Magento order/shipment ID · HTTP status · GHTK error code/message · masked destination · retry attempt · `partner_order_id`.

## Technical Notes

- **Base URI:** `https://services.giaohangtietkiem.vn`; endpoint `POST /services/shipment/order`. Headers `Token` + `X-Client-Source` (reuse TASK-BRKHN4 client abstraction).
- **Async infra:** RabbitMQ **chưa** cấu hình → cron-backed outbox (lean, match precedent ZaloPay). KHÔNG microservice.
- **partner_order_id (B8 default):** deterministic `ghtk-{magento_order_increment}-{shipment_id}` (stable qua retry).
- **COD/payment interaction:** COD amount từ payment method/order total (B1, pending); prepaid → `pick_money=0` (B2). Chạm payment boundary → escalate.
- Depends on TASK-YJENM2 (destination resolver) + TASK-BRKHN4 (pickup resolver, weight, client, mask logger).

## Files/Areas Affected (planned)

- `app/code/Secomm/Ghtk/etc/db_schema.xml` (`secomm_ghtk_shipment`) — NEW (append to TASK-YJENM2 schema)
- `app/code/Secomm/Ghtk/Model/GhtkShipment*` (model/resource/collection) — NEW
- `app/code/Secomm/Ghtk/Model/OrderSync/OutboxWriter.php` (write record in shipment-save transaction) — NEW
- `app/code/Secomm/Ghtk/Model/OrderSync/RequestBuilder.php` (payload, business rules) — NEW
- `app/code/Secomm/Ghtk/Model/OrderSync/ResponseMapper.php` (order response → label/status) — NEW
- `app/code/Secomm/Ghtk/Cron/OrderSyncConsumer.php` (cron outbox) — NEW
- `app/code/Secomm/Ghtk/etc/crontab.xml` — NEW
- `app/code/Secomm/Ghtk/Controller/Ghtk/Webhook.php` (inbound status endpoint — release-1 Core) — NEW
- `app/code/Secomm/Ghtk/Controller/Adminhtml/Ghtk/RetrySync.php` (manual retry) — NEW
- observer/plugin on Magento shipment save (write outbox record) — NEW
- **Reuse, không sửa:** `Secomm_ZaloPay` (pattern precedent).

## Risks

- Tier-2: chạm order/shipment lifecycle + COD/payment-interaction + external API + DB schema → escalate (SA/TL).
- Double-order risk nếu idempotency guard hổng (AC-3 mitigates).
- COD/declared value đoán sai → rủi ro tài chính (AC-7 pending-approval mitigates).
- Cron latency vs real-time (chấp nhận cho shipment sync, không phải critical-path checkout).

## Open Questions

- Q1–Q14 = DEC-TASKKV328X-002 B1–B14 (business rules) — **B2/B3 deferred**, **B9 out-of-phase**; **block ready** tối thiểu **B1 (COD) / B4 / B8 / B14**
- Q-EXT (external verify): order-API field contract COD/declared value/label (DEC-TASKKV328X-002) — still open
- **COD/pick_money dependency (RESOLVED 2026-07-30):** COD payment = **backlog (giai đoạn BA)** → release-1 **prepaid-only** (`pick_money=0` default). `pick_money`/COD amount (B1/B2) **blocked trên COD BA outcome** (order status flow + COD tiền mặt vs chuyển khoản cho shop). Khi resume: track COD BA → chốt B1/B2.

## Definition of Done

- [ ] Outbox + async cron consumer (AC-1/2)
- [ ] Idempotency + `ORDER_ID_EXIST` reconcile (AC-3)
- [ ] Label/tracking persistence (AC-4)
- [ ] Retry + manual retry (AC-5)
- [ ] Request builder (AC-6)
- [ ] Business rules (AC-7) — per approval; pending items ghi pending đúng chuẩn
- [ ] External contract verify (AC-8)
- [ ] Mask logging (AC-9)
- [ ] DEC-TASKKV328X-001 (architecture) accepted — webhook-first inbound + outbox outbound hiện thực; DEC-TASKKV328X-002 business rules: B2/B3 deferred, B9 out-of-phase; tối thiểu **B1 (COD, kèm COD/pick_money tension) / B4 / B8 / B14** approved hoặc pending đúng chuẩn
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] QC: shipment → outbox → cron → GHTK → label; retry; manual retry; duplicate submit → idempotent (1 GHTK order); `ORDER_ID_EXIST` → reconcile; COD/prepaid declared value đúng (per approval)
- [ ] Evidence `.ai/runtime/evidence/FEAT-AE761Z/` (TASK-KV328X)

## Related

- Feature: [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) · Depends on [TASK-YJENM2](TASK-YJENM2-ghtk-address-mapping.md) · [TASK-BRKHN4](TASK-BRKHN4-ghtk-carrier-rate.md) · Decisions: [DEC-TASKKV328X-001](../records/decisions/DEC-TASKKV328X-001.md) (async/idempotency) · [DEC-TASKKV328X-002](../records/decisions/DEC-TASKKV328X-002.md) (business rules) · [DEC-TASKBRKHN4-001](../records/decisions/DEC-TASKBRKHN4-001.md) · [DEC-TASKBRKHN4-002](../records/decisions/DEC-TASKBRKHN4-002.md)
