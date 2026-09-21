# TASK-8MQHJX — ShippingCore v10 Runtime Completion (Eligibility / Zones / RateSourceMode / AddressPolicy execution)

**Type:** Shared-module runtime implementation (ShippingCore, carrier-agnostic)
**Priority:** High (chuỗi FEAT-YA2C0W — unpicks 2 blockers "not frozen" của v10)
**Mode:** A (shipping shared-contract)
**Risk tier:** Tier 2 (shared shipping contracts → TL review từng phase)
**Author:** AI draft từ implementation directive · **Date:** 2026-09-16
**Status:** **CLOSED — COMPLETE** (Phase A/B/C/D 2026-09-18; ShippingCore v10 runtime orchestration = COMPLETE)
**Specification:** SPEC-TASK-8MQHJX-shippingcore-v10-runtime-completion.md (FULL)
**Architecture:** address-shipping.md Revision v10 (§35) — không tạo v11
**Evidence:** `.ai/evidence/TASK-8MQHJX/phase-a.md` · `phase-d.md`
**Related:** TASK-MD2BD3 (PICK_PRIMARY v10) · TASK-Y3X6H5 (per-op capability) · TASK-M3ME32 (E-SL2 orchestrator) · TASK-5JQYMP (fallback eligibility)

## Deliverables (all complete)

| Phase | Nội dung | Trạng thái |
|---|---|---|
| A | `DestinationScope` + `CanonicalZone` VO + `CanonicalZoneRegistry` | COMPLETE |
| B | `CanonicalZoneMatcher` + `CarrierEligibilityResult` + `CarrierEligibilityEvaluator` + DI | COMPLETE |
| C | `CarrierRateExecutionService/Request/Decision` + `RealtimeCarrierRateContributorInterface` + DI + TL-approved `FallbackEligibilitySource.INTEGRATION_LIMITATION` amendment | COMPLETE |
| D | E2E integration proof (15 tests) + orchestrator INTEGRATION_LIMITATION wiring + `__set_state` runtime fix + cross-module regression + invariant grep 0 + SSOT/governance closure | COMPLETE |

## Key invariants preserved

- Eligibility chạy TRƯỚC mode/policy/realtime; ineligible = 0 realtime + 0 fallback.
- FALLBACK_ONLY skip origin/policy/handoff/realtime; LEGACY_ADDRESS_FALLBACK.
- Realtime mode + unresolved origin → fail closed, KHÔNG re-mode.
- AddressResolutionPolicy chỉ chạy cho realtime paths; qua shared handoff service.
- PICK_PRIMARY: carrier chỉ nhận 1 destination đã chọn — không candidates/order/rank.
- Outcome ≠ eligibility; UNAVAILABLE không bao giờ reclassify.
- INVALID_CONFIGURATION merchant-side = fail closed (kể cả policy opt-in).
- ServiceLevelRateOrchestrator = final aggregator + suppression + fallback dispatch.
- GHN/GHTK không own mode semantics; không zone matching; không fallback dispatch.

## Follow-up backlog (không block closure)

- Provider-auth/config warning seam (§35.5 configurable-YES) — DEFERRED, cần constant riêng + warning seam.
- Runtime quote smoke trên store thật — BLOCKED_BY_ENVIRONMENT (origin_district_id unset + catalog trống).
- `Secomm_FulfillmentCore` baseline failures (1 error + 2 failures) — external stream resolve.
- GHN/GHTK adoption của `CarrierRateExecutionService` khi lên composition integration (hiện carrier vẫn Magento-native entry; service là shared gating cho composition layer).
