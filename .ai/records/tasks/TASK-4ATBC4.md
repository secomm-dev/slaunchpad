---
id: TASK-4ATBC4
type: task
title: 'Phase GHN-E2 — Cancel + Return APIs (lifecycle actions)'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec dưới đây — reuse E1 pipeline + GhnApiClient, 0 ShippingCore edit
plan: ../../plans/TASK-4ATBC4-implementation-plan.md
risk: high                    # provider mutation (money-adjacent) — Tier-2; plan approval = signoff
status: in_progress           # activated 2026-09-16
priority: medium
decision_assessment: material
decisions: [DEC-TASK9Q5ZAK-001]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-16
updated: 2026-09-16
owner: [dev]
related_tickets: [TASK-GKHXY1, TASK-9Q5ZAK, TASK-FMBBSD]
---

# [SLP][FEAT-FQWEQ3][TASK-4ATBC4] Phase GHN-E2 — Cancel + Return APIs

**Prerequisites**: GHN-C closed · GHN-D closed · GHN-E1 closed · address-shipping.md v6 SSOT.

## Progress Log

- **2026-09-16 — dev-complete, chờ TL review.** `GhnCancelService` + `GhnReturnService` +
  `GhnActionOutcome` VO + CLI `secomm:ghn:shipment:cancel|return` + endpoint RETURN_ORDER.
  Sandbox runtime: create L8TAKR (122,100 VND) → cancel SUCCESS (GHN-CO003) → repeat cancel
  idempotent-positive (provider result:true lần 2 — không duplicate harm) → return trên
  cancelled-order → BUSINESS_REJECTED "Trạng thái đơn hàng không hợp lệ" (§25 negative ✓).
  Mutation-uncertainty split: timeout/Remote → UNKNOWN_RESULT; 5xx → TECHNICAL_FAILURE;
  auth/invalid → BUSINESS_REJECTED. KHÔNG blind retry; KHÔNG order/tracking mutation
  (grep 0); log PII-safe. Tests: Ghn 269/0F/0E (+21); cross-module 955/0F/0E. Admin UI defer E3
  (decision §17); 0 schema change (audit = log; lifecycle = E1).

- **2026-09-16 — r2 same-day delta (taxonomy alignment với TASK-GKHXY1 r2).** Phân loại đột biến
  r2 áp cho cả 2 mutation services: **5xx → UNKNOWN_RESULT** (trước đây TECHNICAL_FAILURE — 5xx
  để mở applied-vs-not-applied, không được khẳng định not-applied), **429 → TECHNICAL_FAILURE**
  (throttle = rejected TRƯỚC xử lý — definitively not-applied; exception mới
  `ProviderRateLimitException` tách khỏi `ProviderServiceUnavailableException` tại
  `GhnErrorTranslator`), **transport/connect (timeout/Remote) → UNKNOWN_RESULT** với documented
  limitation: HTTP client message-based không chứng minh before-send → conservative fallback,
  không fabricate certainty (refinement client-level để backlog). Sandbox evidence (L8TAKR) giữ
  nguyên hiệu lực — chỉ branch phân loại đổi. Tests +4: cancel (connect-failure → UNKNOWN; 429 →
  TECHNICAL), return (5xx → UNKNOWN; 429 → TECHNICAL); translator 429 case đổi expectation.
  Final: Ghn **273**/0F/0E; cross-module **959**/0F/0E. Compile fail 2 lỗi legacy
  `Secomm_GiaoHangNhanh` = external pre-existing (ghi nhận tại TASK-GKHXY1 r2 final entry).

## Embedded Mini-Spec

### Goal

Provider lifecycle actions Cancel + Return qua reusable domain services (`GhnCancelService`/
`GhnReturnService`), normalized outcome (`GhnActionOutcome`), trigger = CLI (admin buttons defer
E3). KHÔNG mutate Magento order business state; lifecycle state owned bởi E1 pipeline.

### Expected Behavior

1. `GhnCancelService::cancel(Shipment, reasonCode, reason='')`: resolve `secomm_ghn_shipment`
   (SUBMITTED + ghn_order_code; thiếu → BUSINESS_REJECTED) → validate reason_code enum
   (GHN-CO001|GHN-CO002|GHN-CO003|GHN-CANCEL-OTHER) fail-closed → POST `v2/switch-status/cancel`
   `{order_codes:[code], reason_code, reason?}` (single-code; batch là transport internal) →
   `data[0].result` true → SUCCESS; false → BUSINESS_REJECTED (provider message); uncertain
   (timeout/5xx/malformed/empty data) → UNKNOWN_RESULT. KHÔNG auto-retry (mutation).
2. `GhnReturnService::requestReturn(Shipment)`: POST `v2/switch-status/return`
   `{order_codes:[code]}` → per-order result; result:false → BUSINESS_REJECTED (provider state
   không eligible); repeat an toàn (provider từ chối); uncertain → UNKNOWN_RESULT.
3. CLI `secomm:ghn:shipment:cancel <shipment_id> <reason_code> [--reason=]` +
   `secomm:ghn:shipment:return <shipment_id>` — cùng services; sau cancel SUCCESS chạy E1
   reconcile (fetcher → processor) sync CANCELLED.
4. KHÔNG `$order->cancel/setState/setStatus`, không invoice/refund/restock/notification, KHÔNG
   viết `secomm_carrier_tracking_state` trực tiếp, KHÔNG log PII/raw payload.

### Constraints / Rules

- 0 schema change (action audit = GhnLogger; lifecycle state = E1 pipeline).
- 0 ShippingCore edit; KHÔNG admin UI (defer E3); KHÔNG MQ/polling.
- reason_code/Return eligibility: provider + technical validation only — business policy upstream.
- GHN không có signature/idempotency token cho cancel/return — repeated action an toàn nhờ
  provider per-order result; local state KHÔNG bao giờ tự báo success từ HTTP 200.

### Out of Scope

Admin buttons + getTracking + label (E3) · polling automation · MQ · legacy cutover (F) ·
COD/refund/RMA policy · Magento order-state orchestration.

### Acceptance Criteria

- AC-1: cancel sandbox SUCCESS trên order cancellable (reason_code hợp lệ).
- AC-2: repeat cancel → normalized an toàn (BUSINESS_REJECTED/SUCCESS per provider), không
  inconsistent local state.
- AC-3: `result:false` trên HTTP 200 ⇒ BUSINESS_REJECTED (không bao giờ success) — unit-locked.
- AC-4: return probe ghi nhận đúng provider behavior (eligible-state check thuộc provider).
- AC-5: timeout/5xx/malformed/connect-failure → UNKNOWN_RESULT (conservative, reconcile qua E1);
  429 → TECHNICAL_FAILURE (definitively not-applied); không blind retry.
- AC-6: 0 order mutation + 0 tracking-state direct write (grep gate) + regression green.
