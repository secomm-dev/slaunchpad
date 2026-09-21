---
id: TASK-32ACTR
type: task
title: 'Phase E-SL1 — Service-level realtime rate aggregation trong Secomm_ShippingCore (aggregate outcomes per dynamic service level)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-32ACTR — aggregation shape theo approved E-SL1 directive; TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-32ACTR-shippingcore-service-level-rate-aggregation.md
risk: medium                  # additive aggregator + VO; chưa có runtime consumer; 0 carrier code
status: in_progress
priority: high
decision_assessment: none-material   # mixed-outcome matrix đã approved SPIKE-YH439T; carrier identity Option A theo directive — không DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [TASK-NAT3YV, TASK-XXBN5X, TASK-T78YH6]
---

# [SLP][FEAT-YA2C0W][TASK-32ACTR] Phase E-SL1 — Service-level realtime rate aggregation trong Secomm_ShippingCore (aggregate outcomes per dynamic service level)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-32ACTR-shippingcore-service-level-rate-aggregation.md, FULL)*

### Goal

Một bước aggregation duy nhất: 1 dynamic service-level code + tập realtime outcomes đã classify
(E-C1) → aggregate trả lời "có usable realtime rate không" (`hasSuccessfulRate()` + rates) và
"có technical failure không" (`hasTechnicalFailure()`) — input cho E-SL2 fallback decision.
KHÔNG routing/selection/fallback/checkout.

### Expected Behavior

1. `ServiceLevelRateAggregator::aggregate(code, outcomes)` — validate code qua
   `ShippingServiceLevelRegistry::assertKnown` (unknown → `LocalizedException`, dynamic — KHÔNG
   compile-time constants); DI registry.
2. Input: `array<string, CarrierRateOutcomeInterface>` — KEY = carrier code non-empty string
   (identity bảo toàn — Option A directive §12/§13); key sai/value sai type → `LogicException`.
3. Matrix §3.3 spec đúng 8 hàng: SUCCESS → rate giữ nguyên key + input order (không sort);
   TECHNICAL_FAILURE → hasTechnicalFailure true (kể cả khi có SUCCESS — tín hiệu không suppress,
   E-SL2 rule "any SUCCESS ⇒ no fallback" thuộc E-SL2); UNAVAILABLE → bỏ qua; zero outcomes valid.
4. Aggregate VO: `getServiceLevelCode()` · `getSuccessfulRates(): array<string, CarrierRateInterface>`
   · `hasSuccessfulRate()` · `hasTechnicalFailure()` · `getOutcomeCount()` (diagnostics). Immutable,
   không metadata/fallback fields.
5. Reason strings KHÔNG đọc bất kỳ đâu (§10/§11 — status authoritative); KHÔNG invoke
   `FallbackRateProviderInterface`; KHÔNG service-level code hardcoded (production + tests dùng
   LEVEL_A/LEVEL_B dynamic — §18).

### Constraints / Rules

- Scope = lean Launchpad aggregation, KHÔNG generic routing platform: không cheapest/preferred/
  fastest/SLA/cut-off/source-routing; không carrier membership config (caller đã biết bucket — §19);
  không đụng address DTOs (E-C0 xảy ra trước, pipeline conceptual chỉ document — §20).
- SUCCESS ⇒ rate non-null do E-C1 VO guarantee — reuse, không duplicate validation (§21).
- KHÔNG FallbackPolicy/decision engine (E-SL2 thêm nếu cần — §17).
- Namespace: flat `Api\` (E-SL0 precedent) + `Model\ServiceLevel\`; DI 1 preference.

### Out of Scope

Fallback trigger + `FallbackRateProvider` invocation · policy object · ranking/selection/priority ·
carrier membership config · checkout method/showmethod · carrier adoption · provider API · address
mapping/external resolver · OrderOperations/OMS · hardcoded taxonomy.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-32ACTR (tóm tắt): dynamic registry validation · matrix 8 hàng đúng +
keys/order preserved + mixed không suppress · reason 0 lần đọc · zero outcomes valid · input
validation fail-fast · không fallback dependency + không sort · DI + compile + validator 0 new
finding + phpunit pass + README/CHANGELOG + working memory sync + 0 carrier code.

## Plan

`../plans/TASK-32ACTR-implementation-plan.md`
