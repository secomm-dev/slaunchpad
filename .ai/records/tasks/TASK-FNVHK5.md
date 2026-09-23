---
id: TASK-FNVHK5
type: task
title: 'GHTK CANCEL lifecycle — cancel API client + typed result (CANCELLED/ALREADY_CANCELLED/BUSINESS_REJECTION/TECHNICAL_FAILURE) + application service (không auto-wire Magento cancel)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed task 2026-09-14; CANCEL contract theo official docs (SPIKE-A1DGPY §12)
specification_ref: Embedded Mini-Spec
risk: medium                  # mutation operation mới nhưng chưa auto-wired vào Magento cancel flow; single-attempt; fail-closed classification
status: dev-complete          # implemented 2026-09-14 (scoped 620 tests — 0 failure trong scope; evidence .ai/evidence/TASK-FNVHK5/); method/message runtime verify qua TASK-44F7V7; chờ Tier-2 review
priority: high
decision_assessment: none-material # capability/service mới theo official docs; không thay đổi decision cũ; POST-vs-GET discrepancy documented NEEDS_RUNTIME_VERIFICATION
decisions: [DEC-TASK7AJ3K8-002, DEC-TASKBE5YD2-001]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [SPIKE-A1DGPY, TASK-KCXKVR, TASK-6YG3HP, TASK-W8SH0N, TASK-BE5YD2, TASK-44F7V7]
---

# [SLP][FEAT-YA2C0W][TASK-FNVHK5] GHTK CANCEL lifecycle

## Embedded Mini-Spec

### Goal

Implement provider cancel capability: `GhtkApiClient::cancelShipment(identifier)` +
typed `GhtkCancelResponse` (CANCELLED / ALREADY_CANCELLED / BUSINESS_REJECTION / TECHNICAL_FAILURE)
+ application service `CancelShipmentService` (carrier-owned, KHÔNG auto-wire Magento order cancel
qua observer — §19). KHÔNG address handoff, KHÔNG CarrierRateOutcome, KHÔNG sửa
ShippingCore/VietNamAddress, KHÔNG freeze capability, KHÔNG pickup/TestConnection.

### Expected Behavior

1. Client: `cancelShipment(string $providerIdentifier)` — POST
   `/services/shipment/cancel/{rawurlencode(identifier)}` (official Endpoint section; samples
   dùng GET — discrepancy documented, POST = strongest official evidence,
   NEEDS_RUNTIME_VERIFICATION); auth headers/profile/base URL reuse; **single attempt** —
   KHÔNG dùng `RetryPolicy::safeRead` (mutation; §13/§17).
2. Identifier: GHTK label (primary, từ CREATE identity `OrderSubmitResult.labelId`) hoặc
   `partner_id:{code}` variant; non-empty precondition; rawurlencode (§37).
3. Typed result theo official response `{success, message, log_id}` (KHÔNG có error_code field):
   `success=true` → CANCELLED; `success=false` + documented already-cancelled message
   ("Đơn hàng đã đã ở trạng thái hủy") → ALREADY_CANCELLED (benign — cancellation intent
   satisfied, §7/§14); `success=false` khác (state rejection "Đơn đã lấy hàng…", unknown
   shipment) → BUSINESS_REJECTION; transport category NETWORK/SERVER_ERROR/TIMEOUT/
   INVALID_RESPONSE → TECHNICAL_FAILURE; CLIENT_ERROR (403/400/404) / RATE_LIMIT →
   BUSINESS_REJECTION (auth/config/unknown — non-retry, §10/§11).
4. Message matching cho ALREADY_CANCELLED là documented-message contract (GHTK không cung cấp
   error_code ở cancel response — khác RATE/CREATE); exact string NEEDS_RUNTIME_VERIFICATION.
5. Service KHÔNG mutate Magento order/shipment/payment/refund state (§20) — caller quyết lifecycle;
   tracking sau cancel vẫn qua pipeline (status -1 → CANCELLED qua GhtkStatusMapper — §21,
   không fake tracking events).
6. Logging masked: operation=CANCEL, identifier, classification, message/log_id.

### Constraints / Rules

- KHÔNG: CarrierRateOutcome; address handoff/adapter; auto wire Magento cancel → GHTK;
  automatic POST retry; Magento order/payment/refund mutation; ShippingCore/VN diff; capability
  freeze; pickup/TestConnection; COD changes.
- Không có staging token → runtime verification của method/shape = NEEDS_RUNTIME_VERIFICATION
  (TASK-44F7V7).

### Out of Scope

Magento cancel-flow wiring (OrderOperations/bridge — follow-up) · pickup/TestConnection ·
address freeze · CREATE/RATE/tracking behavior changes.

### Acceptance Criteria

AC-1: valid cancel → CANCELLED (test). AC-2: already-cancelled → ALREADY_CANCELLED benign,
không exception (test). AC-3: state rejection → BUSINESS_REJECTION no-retry (test). AC-4:
400/403/unknown-shipment → BUSINESS_REJECTION (tests). AC-5: timeout/network/5xx/invalid JSON →
TECHNICAL_FAILURE, single attempt (tests). AC-6: manual retry scenario — attempt 1 technical,
attempt 2 already-cancelled → intent satisfied; client đúng 1 call/service invocation (test).
AC-7: identifier empty → exception; rawurlencode path (test). AC-8: 0 CarrierRateOutcome /
address handoff trong CANCEL code (grep). AC-9: KCXKVR/6YG3HP/W8SH0N/BE5YD2 regressions pass.
AC-10: ShippingCore/VN diff 0; validator pass.
