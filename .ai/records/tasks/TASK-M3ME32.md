---
id: TASK-M3ME32
type: task
title: 'Phase E-SL2 — Service-level fallback decision orchestration trong Secomm_ShippingCore (FINAL foundation slice)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-M3ME32 — decision matrix theo approved E-SL2 directive; TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-M3ME32-shippingcore-service-level-fallback-decision.md
risk: medium                  # orchestrator + policy + decision VO; chưa có runtime consumer; 0 carrier code
status: in_progress
priority: high
decision_assessment: none-material   # decision matrix đã approved trong directive §23; >1-provider ambiguity = fail-fast per §15 ("report, không invent routing") — REPORT TL, không DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [TASK-32ACTR, TASK-NAT3YV, TASK-XXBN5X]
---

# [SLP][FEAT-YA2C0W][TASK-M3ME32] Phase E-SL2 — Service-level fallback decision orchestration trong Secomm_ShippingCore (FINAL foundation slice)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-M3ME32-shippingcore-service-level-fallback-decision.md, FULL; **E-SL2 = slice foundation ShippingCore CUỐI CÙNG cho scope Launchpad hiện tại — hard stop §29**)*

### Goal

`ServiceLevelRateOrchestrator::decide(code, aggregate, fallbackRequest)` → decision
**REALTIME / FALLBACK / UNAVAILABLE** cho 1 service level: enforce `enabled` (lần đầu ở
ShippingCore runtime), tôn trọng "any SUCCESS ⇒ no fallback", chỉ TECHNICAL_FAILURE + policy
enabled + provider available mới gọi fallback provider (đúng 1 lần). KHÔNG routing/selection.

### Expected Behavior

1. `FallbackPolicyInterface::isEnabled(code): bool` + `ConfigurableFallbackPolicy` (DI array
   `fallbackEnabledByLevel`, default DENY — emergency pricing opt-in per level; §7 không DB;
   upgrade path admin config = thay impl qua preference).
2. `ServiceLevelRateDecision` — SOURCE_REALTIME/SOURCE_FALLBACK/SOURCE_UNAVAILABLE + invariants
   (REALTIME: rates non-empty + fallback null; FALLBACK: rates empty + fallback non-null;
   UNAVAILABLE: cả hai rỗng + !available) + factories. KHÔNG reason field (§26).
3. Orchestrator flow §3.4 spec: unknown code → `LocalizedException`; code/aggregate mismatch →
   `LogicException` (§22); **disabled → UNAVAILABLE + realtime KHÔNG expose dù aggregate có
   SUCCESS** (§25); SUCCESS → REALTIME (rates as-is E-SL1, không chọn — §11; provider KHÔNG
   call); no technical → UNAVAILABLE; policy disabled → UNAVAILABLE; providers 0 → UNAVAILABLE
   (§14, không exception); **>1 → LogicException** (§15 ambiguity — REPORT TL); provider gọi
   ĐÚNG 1 LẦN → null → UNAVAILABLE; rate **kể cả 0đ** → FALLBACK (r2 semantics).
4. Reason strings 0 lần đọc (status authoritative — r1 rule); không Magento RateRequest leak
   (caller cấp FallbackRateRequest — §20); không retry/circuit breaker (§18); không address
   DTOs (§19); không Mageplaza/carrier concepts (§16/§17).

### Constraints / Rules

- KHÔNG: Mageplaza bridge, carrier adoption, selection/cheapest/preferred, checkout method,
  OMS/routing, inventory routing, external resolver, retry/circuit breaker, admin CRUD, DB,
  order metadata, generic policy engine (priority/conditions/time windows/price caps — §6).
- Policy storage = DI (§7); admin config là upgrade path document-only.
- **Hard stop §29**: foundation ShippingCore HOÀN THÀNH tại E-SL2 — phase kế tiếp = real-consumer
  validation (carrier adoption + LT-BRIDGE-1), KHÔNG thêm generic architecture khi chưa có evidence.

### Out of Scope

Như Constraints + mọi thứ ngoài 3 additions ở plan.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-M3ME32 (tóm tắt): matrix 9 hàng + critical rule "SUCCESS ⇒ provider
không call" (mock expectations) · provider exactly-once trên fallback path · disabled ẩn realtime
· zero-provider UNAVAILABLE / >1 LogicException · zero-rate FALLBACK · mismatch/unknown fail-fast
· 0 hardcoded taxonomy/reason inspection/RateRequest leak · DI + compile + validator 0 new
finding + phpunit pass · README final flow + hard stop + CHANGELOG + working memory sync + 0
carrier code.

## Plan

`../plans/TASK-M3ME32-implementation-plan.md`
