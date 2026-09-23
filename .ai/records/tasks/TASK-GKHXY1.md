---
id: TASK-GKHXY1
type: task
title: 'Phase GHN-E1 — Tracking + Webhook + Status Normalization (lifecycle-only)'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec dưới đây — reuse ShippingCore tracking pipeline có sẵn (audit r2/r3)
plan: ../../plans/TASK-GKHXY1-implementation-plan.md
risk: high                    # public unauthenticated-transport endpoint + provider lifecycle (Tier-2)
status: in_progress           # activated 2026-09-15
priority: medium
decision_assessment: material
decisions: [DEC-TASK9Q5ZAK-001]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-15
updated: 2026-09-15
owner: [dev]
related_tickets: [TASK-FMBBSD, TASK-9Q5ZAK]
---

# [SLP][FEAT-FQWEQ3][TASK-GKHXY1] Phase GHN-E1 — Tracking + Webhook + Status Normalization

**Prerequisites**: GHN-C closed (QC r3 PASS) · GHN-D closed (r2/r3 + sandbox) · ShippingCore
tracking pipeline tồn tại (`TrackingUpdate`/`NormalizedTrackingStatus`/`ShipmentTrackingProcessor`/
`secomm_carrier_tracking_state`/`TrackingReconciliationService` — REUSE 0 edit) · GHN-D đã gắn
native Track (`secomm_ghn` : ghn_order_code) trên shipment.

## Progress Log

- **2026-09-15 — dev-complete, chờ TL review.** Đã implement: `GhnStatusMapper` (23 statuses → 11
  normalized explicit; unknown→UNKNOWN; damage/lost/scrap→DELIVERY_FAILED; exception→UNKNOWN),
  `WebhookPayloadParser` (Type-aware: chỉ switch_status; sanitize raw bỏ ShipperPhone/PodURL/ShopID),
  `Controller/Webhook/Tracking` (POST /secomm_ghn/webhook/tracking — secret header hash_equals
  fail-closed 401; 200 ack/drop; 500 internal → GHN retry; không order mutation),
  `GhnTrackingFetcher` + `TrackingRefreshService` + cron 30' opt-in (cùng mapper với webhook),
  `GhnShipmentRepository::findByGhnOrderCode`. Reuse 100% ShippingCore pipeline (0 edit).
  **Runtime matrix trên dev store (order/shipment thật, track L8TKYG):** đúng secret → matched:true
  + state row DELIVERED/delivered/webhook; duplicate → idempotent; sai secret → 401; OrderCode lạ →
  matched:false; create-type → ack không xử. Log 0 PII/token. Tests: Ghn **248 / 0F / 0E** (+45);
  cross-module 927/0F/0E. Ghi chú: GHN KHÔNG có webhook signature — secret-possession model
  documented (matrix §11/D8); docs re-fetch: SPA không curl được, vocabulary từ matrix (docs-fetched
  2026-09-11) + legacy 22 + scrap (terminal list) = 23 values.

- **2026-09-16 — r2 (TL review corrections) dev-complete.** (1) Security wording chính xác: GHN
  KHÔNG sign callbacks; portal hỗ trợ merchant custom headers; X-Secomm-Ghn-Secret = shared-secret
  auth (constant-time), KHÔNG provider signature verification — wording sửa ở controller docblock/
  system.xml/evidence. (2) Dedupe identity = OrderCode+Type+Time: ShippingCore `shouldApply()` 
  occurrence-aware (same status+code + occurredAt KHÁC stored → distinct event re-applied; exact
  re-send → no-op) — smallest correction, Ghtk null-occurredAt giữ nguyên. Runtime PROVEN: 
  delivery_fail @16:00 → @17:00 = distinct (re-applied), exact resend → no-op. (3) Taxonomy +=
  LOST/DAMAGED (terminal + commentable): lost→LOST, damage→DAMAGED, scrap→DAMAGED (compat), 
  delivery_fail giữ riêng. Fetcher tests lost/damage qua CÙNG mapper. Events: generic 
  tracking-updated only (restraint). (4) Controller: Type pre-gate → `unsupported_event_type` 
  (tách khỏi invalid_payload); parser const public + KNOWN_TYPES. (5) scrap provenance = docs 
  terminal list, flag re-verify. ShippingCore CHANGELOG += taxonomy/dedupe delta. Tests: 
  ShippingCore +5 (occurrence ×2, LOST/DAMAGED terminal); Ghn mapper/fetcher/controller updated.
  **Cross-module 927+ / 0F / 0E.**

- **2026-09-16 — r2 final verification GREEN, sẵn sàng TL sign-off.** Gates: Ghn **273/0F/0E**
  (248 + 25: E1 +45, E2 +21, r2-taxonomy +4); cross-module Ghn+ShippingCore+VietNamAddress+Ghtk
  **959/0F/0E**; 6 PHPUnit deprecations = pre-existing module khác (PromotionMaxDiscount/Tracking/
  ExtraFeeFix — non-static data providers), không phải stream này. **N-Defect ghi nhận (ngoài
  scope, KHÔNG vá chéo):** `setup:di:compile` fail 2 lỗi tại LEGACY `Secomm_GiaoHangNhanh` —
  working-tree diff chưa commit của stream khác thêm `LoggerInterface $logger` vào
  `AbstractDataBuilder::__construct` nhưng `ShippingDetailsDataBuilder` + `SynchronizeOrderDataBuilder`
  chưa truyền qua; 0 lỗi ở Secomm/Ghn + ShippingCore + VietNamAddress + Ghtk. **Validator:**
  32 FAIL pre-existing thuộc records stream khác (TASK-0F96X5/ZR2ZNS/AEZTTB/78PVR0/BS91A3, BUG-*,
  SPEC naming, plans cũ) — **0 reference TASK-GKHXY1/4ATBC4**. (6) r2 bonus — mutation taxonomy
  áp nhất quán cho E2: 5xx → UNKNOWN_RESULT (applied-vs-not không kết luận được), 429 →
  TECHNICAL_FAILURE (tách qua `ProviderRateLimitException`), transport/connect conservative →
  UNKNOWN_RESULT (client không chứng minh before-send — không fabricate certainty); chi tiết tại
  TASK-4ATBC4 progress log.

## Embedded Mini-Spec

### Goal

GHN tracking lifecycle boundary: webhook endpoint + GHN status mapper + reconciliation fetcher →
normalized ShippingCore tracking event. KHÔNG mutate Magento order business state.

### Expected Behavior

1. `POST /secomm_ghn/webhook/tracking` (frontName `secomm_ghn` — legacy chiếm `ghn`): secret
   header `X-Secomm-Ghn-Secret` so `hash_equals` với config `carriers/secomm_ghn/webhook_secret`;
   secret chưa configured hoặc sai → 401 (fail-closed — anti-defect legacy D8: GHN KHÔNG có
   signature, merchant tự định nghĩa custom header).
2. Payload PascalCase: chỉ `Type=switch_status` với `OrderCode`+`Status` đẩy lifecycle qua
   `GhnStatusMapper` (23 statuses explicit; unknown → UNKNOWN + warning) → `TrackingUpdate`
   (carrier `secomm_ghn`, tracking_number = OrderCode, occurredAt = strtotime(Time), source
   webhook, raw sanitized) → `CarrierTrackingProcessorInterface::process()` (dedupe/terminal/
   timestamp guards có sẵn). Type khác (create/update_weight/update_cod/update_fee/
   update_payment_type/cod/update_partial_return) → ack 200, không xử lifecycle.
3. Response: luôn JSON — 200 `{ok:true,matched:bool}` / `{ok:false,error:invalid_payload}`;
   401 invalid_secret; 500 internal_error (GHN retry backoff 30s→12h; 4xx non-408/429 bị drop).
4. Reconciliation: `GhnTrackingFetcher` (GET `v2/shipping-order/detail?order_code=` → status →
   CÙNG mapper → TrackingUpdate source `api`) + virtualType reconciliation + opt-in cron 30'
   (mirror Ghtk). Một mapper phục vụ cả webhook + fetcher (§30).
5. Unknown OrderCode → processor matched:false → 200 `{ok:true,matched:false}` + log — KHÔNG
  Magento mutation. KHÔNG `$order->setState/setStatus`, không invoice/refund/notification.

### Constraints / Rules

- Không dùng frontName `ghn` (legacy); không log secret/phone/address/raw PII payload.
- `isTrackingAvailable`/`getTracking()` giữ FALSE/defer (E3) — E1 chỉ domain service.
- Reuse ShippingCore pipeline — 0 ShippingCore edit; KHÔNG tạo VO/tracking table mới.
- GHN status vocabulary re-verify docs (23 values) tại Bước 0; mapping explicit theo matrix.
- Polling automation riêng OUT (cron chỉ là reconciliation fetcher của pipeline E).

### Out of Scope

Cancel API + Return API calls (E2) · label/native tracking UI + `getTracking()` (E3) · legacy
cutover (F) · MQ async · polling automation · RATE/COD · Magento order-state orchestration.

### Acceptance Criteria

- AC-1: 23 GHN statuses map explicit + test full table; unknown → UNKNOWN + warning.
- AC-2: webhook secret sai/thiếu → 401; malformed → 200 invalid_payload; matched/unmatched → 200.
- AC-3: duplicate webhook idempotent (processor absorbs); stale/older không regress; terminal sticky.
- AC-4: fetcher + webhook dùng CÙNG `GhnStatusMapper` (một nguồn sự thật).
- AC-5: 0 `setState(`/`setStatus(` trên Order trong GHN lifecycle code (grep gate); 0 payment inspection.
- AC-6: regression Ghn/ShippingCore/VietNamAddress/Ghtk green; compile + validator.
