---
id: DEC-TASKKV328X-001
legacy_ids: [DEC-023]
title: Idempotent async order/shipment sync via cron-backed outbox (lean) — never call GHTK order API inside the shipment-save transaction; deterministic partner order ID
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-07-30
created: 2026-07-30
last_verified: 2026-07-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-KV328X, FEAT-AE761Z]
---

# Decision Record: Idempotent async order sync architecture

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). ACCEPTED 2026-07-30 — approved by user acting as SA/TL. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Replaces FEAT-AE761Z AC-007 "POST /services/shipment/order khi tạo shipment" (synchronous, inside save). -->
<!-- Refinement 2026-07-30 (user): webhook-first — inbound status sync (GHTK→Magento) via webhook = release-1 Core; outbound order creation keeps idempotent outbox/cron; MQ queue processing deferred ("queue xử lý sau"). -->
<!-- Audit 2026-07-30: no RabbitMQ configured (env.php queue = consumers_wait_for_messages only); no Magento MQ usage in app/code; Secomm_ZaloPay uses cron-backed retry (RefundCronjob) as in-project precedent. -->

## Context

FEAT-AE761Z AC-007 hiện mô tả "gọi `POST /services/shipment/order` khi tạo shipment" — ngầm hiểu synchronous, bên trong transaction tạo Magento shipment. Đây là anti-pattern:

- GHTK order API là external call chậm/flaky; gọi trong transaction save → hold DB lock, kéo dài checkout/admin, crash nếu timeout.
- Không có idempotency → retry có thể tạo nhiều GHTK orders cho cùng 1 shipment (double label).
- Không có retry có kiểm soát; không có manual retry; không reconcile với trạng thái/tracking đã tồn tại.

**Audit async infrastructure (2026-07-30):** RabbitMQ **không** được cấu hình (chỉ có `consumers_wait_for_messages`); không có publisher/consumer nào trong `app/code`. In-project có precedent **cron-backed retry**: `Secomm_ZaloPay` (`RefundCronjob` mỗi 15 phút + cleanup hàng tháng).

Tier-2 (order management + external API + DB schema — §12) → Level-2 architecture decision, SA/TL.

## Decision (accepted 2026-07-30 — user as SA/TL)

> **Webhook-first (refinement 2026-07-30):** **inbound status sync (GHTK→Magento) qua webhook = release-1 Core** (GHTK gọi Magento endpoint khi status đổi → update `secomm_ghtk_shipment` + Magento shipment track/status; endpoint return 200 nhanh, processing sync hoặc cron now). **Outbound order creation (Magento→GHTK)** giữ **idempotent outbox/cron** (theo requirement: KHÔNG gọi order API trong shipment-save). **MQ queue (RabbitMQ)** xử lý payload async → **deferred** ("queue xử lý sau").

1. **Outbound — async outbox flow** (KHÔNG gọi GHTK order API trong shipment-save transaction):
   ```
   Magento shipment save thành công
   → ghi sync record (outbox) trong cùng transaction (DB only, no external call)
   → cron-backed consumer gọi GHTK order API (precedent ZaloPay; MQ queue = deferred)
   → persist trạng thái + GHTK label/tracking code
   → retry có kiểm soát
   → hỗ trợ manual retry (admin)
   ```
2. **Inbound — webhook status sync (release-1 Core):** GHTK webhook endpoint nhận status change → reconcile với `secomm_ghtk_shipment` (idempotent qua `partner_order_id`) → update Magento shipment track/status. Webhook processing hiện sync/cron; **migrate sang MQ queue sau.**
3. **Persistence model `secomm_ghtk_shipment`:** `entity_id`, `shipment_id`, `order_id`, `partner_order_id`, `ghtk_label`, `status`, `attempt_count`, `request_hash`, `last_error_code`, `last_error_message`, `synced_at`, `created_at`, `updated_at`. Constraints `UNIQUE(shipment_id)`, `UNIQUE(partner_order_id)`.
4. **State machine tối thiểu:** `pending → processing → synced` | `retryable_error → (retry) → processing` | `failed` | `cancelled`.
5. **Idempotency rules:**
   - Cùng 1 shipment không tạo nhiều GHTK orders (guard trên `UNIQUE(shipment_id)` + state check).
   - Retry **reuse deterministic `partner_order_id`** (không sinh ID mới mỗi retry) → GHTK nhận diện duplicate.
   - `ORDER_ID_EXIST` từ GHTK **không mặc định xem là fatal** → reconcile với trạng thái/tracking đã tồn tại (fetch status; nếu đã synced → update local, không tạo mới).
   - Webhook (inbound) + outbox (outbound) share reconcile path (cùng `partner_order_id`).
   - `request_hash` phát hiện retry cùng dữ liệu (idempotent skip) vs thay đổi material (re-submit).
6. **Lean choice:** **webhook (inbound) + cron outbox (outbound)** cho release đầu (match precedent ZaloPay, không cần RabbitMQ). Magento MQ là upgrade path khi throughput yêu cầu; **không over-engineer microservice.**

## Alternatives

- **Synchronous call trong shipment-save** — rejected: hold DB lock, fragile, không retry, double-order risk.
- **Magento Message Queue (RabbitMQ) ngay** — rejected (now): RabbitMQ chưa cấu hình; thêm ops dependency. Cron outbox lean + đủ cho release đầu. (Giữ làm upgrade path.)
- **Không outbox, fire-and-forget** — rejected: mất retry/idempotency; không reconcile.

## Consequences

- (+) Không block shipment-save; retry có kiểm soát; không double-order; manual retry; reconcile an toàn (cả webhook inbound + outbox outbound).
- (+) Webhook status sync = release-1 Core → trạng thái/threshold tracking theo thời gian thực hơn cron poll.
- (+) Reuse precedent ZaloPay (cron retry) → pattern quen thuộc, ít ops overhead.
- (−) Thêm bảng `secomm_ghtk_shipment` + cron consumer + **webhook endpoint** + admin manual-retry UI.
- (−) Webhook processing chưa qua MQ → endpoint phải return 200 nhanh + offload processing (sync/cron now); MQ là upgrade.
- **DEC-TASKKV328X-001 (architecture) accepted** → TASK-KV328X architecture unblocked; **TASK-KV328X vẫn `proposed`** cho đến khi business rules DEC-TASKKV328X-002 (B1/B2/B4/B8/B14...) approved.

## Affected components

- `CMP-GHTK` — `Secomm_Ghtk` (`secomm_ghtk_shipment` schema, outbox writer, cron consumer, retry/manual-retry).
- `Secomm_ZaloPay` — pattern precedent (cron retry), không sửa.
- Sub-tickets: TASK-KV328X.

## Related records

- Features: [FEAT-AE761Z](../features/FEAT-AE761Z.md)
- Decisions: [DEC-TASKBRKHN4-001](DEC-TASKBRKHN4-001.md) (pickup resolver — order request) · [DEC-TASKBRKHN4-002](DEC-TASKBRKHN4-002.md) (weight — order request) · [DEC-TASKKV328X-002](DEC-TASKKV328X-002.md) (order sync business rules)
- DECISIONS.md index: DEC-TASKKV328X-001
