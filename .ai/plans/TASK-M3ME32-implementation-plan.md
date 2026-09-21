# Implementation Plan: TASK-M3ME32 — Phase E-SL2 service-level fallback decision (FINAL foundation slice)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-M3ME32 (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-M3ME32-shippingcore-service-level-fallback-decision.md](../specs/SPEC-TASK-M3ME32-shippingcore-service-level-fallback-decision.md) — FULL, VALID (approved E-SL2 directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); E-SL2 không DEC mới — policy lean DI-array (§7), >1-provider ambiguity = fail-fast `LogicException` theo §15 "report, không invent routing" (REPORT TL) |
| Architecture basis | [SPIKE-YH439T](../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md) §21/§22 + [SPIKE-WHHEZV](../research/SPIKE-WHHEZV-mptablerate-service-level-fallback.md) §9/§10/§13 (fallback-enabled per level, eligibility ≠ rate match) |
| Contract basis | TASK-32ACTR (E-SL1 aggregate) · TASK-NAT3YV (E-C1 + r1 status/reason rule) · TASK-XXBN5X (E-SL0 fallback + r2 zero-rate) |
| Risk | Medium — orchestrator + policy + decision VO; chưa có runtime consumer; 0 carrier code. **Slice foundation CUỐI CÙNG (hard stop §29)** |

## Approach

3 additions: `FallbackPolicyInterface` + `ConfigurableFallbackPolicy` (DI array `fallbackEnabledByLevel`,
default DENY cho code không liệt kê — emergency pricing opt-in per level) ·
`ServiceLevelRateDecisionInterface` + VO (REALTIME/FALLBACK/UNAVAILABLE + invariants + factories)
· `ServiceLevelRateOrchestrator` (registry + policy + pool): flow §3.4 — consistency §22 →
enabled (§25: disabled ẩn realtime dù aggregate có SUCCESS) → SUCCESS ⇒ REALTIME (không provider
call) → no-technical ⇒ UNAVAILABLE → policy-disabled ⇒ UNAVAILABLE → providers 0 ⇒ UNAVAILABLE /
>1 ⇒ LogicException (ambiguity) → provider call ĐÚNG 1 LẦN → null ⇒ UNAVAILABLE / rate (kể cả
0đ) ⇒ FALLBACK. Không selection/routing/retry/address/Mageplaza/carrier concepts.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT `ticket_ref` += TASK-M3ME32 | spec-first TRƯỚC code |
| 2 | Policy | `Api/Fallback/FallbackPolicyInterface.php` + `Model/Fallback/ConfigurableFallbackPolicy.php` | DI array; default deny |
| 3 | Decision | `Api/ServiceLevelRateDecisionInterface.php` + `Model/ServiceLevel/ServiceLevelRateDecision.php` | 3 SOURCE_* + invariants + factories |
| 4 | Orchestrator | `Api/ServiceLevelRateOrchestratorInterface.php` + `Model/ServiceLevel/ServiceLevelRateOrchestrator.php` | flow §3.4 |
| 5 | DI | `etc/di.xml` | 2 preference (policy + orchestrator); policy array rỗng |
| 6 | Tests | `Test/Unit/Model/{Fallback/ConfigurableFallbackPolicyTest, ServiceLevel/ServiceLevelRateDecisionTest, ServiceLevel/ServiceLevelRateOrchestratorTest}.php` | §6 spec |
| 7 | Docs | `README.md` + `CHANGELOG.md` | final flow + fallback rule + **hard stop §29** |
| 8 | Validation | phpunit · validator · compile · grep (taxonomy/reason/RateRequest/Mageplaza) | AC-6 |
| 9 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-M3ME32/` | |

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Policy trôi thành rules engine | Contract 1 method; impl 1 array; spec §3.1 cấm mở rộng |
| >1 provider handling sai hướng | §15 cấm first-wins — LogicException fail-fast + REPORT TL |
| Disabled level lộ realtime rates | Test §25 (aggregate có SUCCESS nhưng decision UNAVAILABLE + rates rỗng) |

Rollback: revert — không DB, không config, không carrier code.
