# Implementation Plan: TASK-32ACTR — Phase E-SL1 service-level rate aggregation

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-32ACTR (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-32ACTR-shippingcore-service-level-rate-aggregation.md](../specs/SPEC-TASK-32ACTR-shippingcore-service-level-rate-aggregation.md) — FULL, VALID (approved E-SL1 directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); E-SL1 không có architecture decision mới (carrier identity = Option A keyed-array theo directive §12; service-level validation qua registry E-SL0) |
| Architecture basis | [SPIKE-YH439T](../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md) §21/§22 (mixed-outcome matrix) |
| Contract basis | TASK-NAT3YV (E-C1 outcomes) · TASK-XXBN5X (E-SL0 registry — assertKnown) · TASK-T78YH6 (E-C0 — không dependency) |
| Risk | Medium — additive aggregator + aggregate VO; chưa có runtime consumer; 0 carrier code |

## Approach

1 service + 1 VO: `ServiceLevelRateAggregator` (DI `ShippingServiceLevelRegistry`;
`aggregate(code, outcomes)` — assertKnown dynamic → iterate outcomes keyed theo carrier code →
`ServiceLevelRateAggregate`). Aggregate immutable: successfulRates keyed carrier-code (Option A —
identity preserved, không wrapper DTO), hasSuccessfulRate/hasTechnicalFailure/getOutcomeCount.
Reason strings KHÔNG đọc; zero outcomes valid; không sort; không fallback dependency. Tests dùng
LEVEL_A/LEVEL_B (không Launchpad taxonomy — §18).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT `ticket_ref` += TASK-32ACTR | spec-first TRƯỚC code |
| 2 | Contracts | `Api/{ServiceLevelRateAggregatorInterface, ServiceLevelRateAggregateInterface}.php` | flat Api (E-SL0 precedent) |
| 3 | Concrete | `Model/ServiceLevel/{ServiceLevelRateAggregator, ServiceLevelRateAggregate}.php` | matrix §3.3; input validation |
| 4 | DI | `etc/di.xml` | 1 preference |
| 5 | Tests | `Test/Unit/Model/ServiceLevel/ServiceLevelRateAggregatorTest.php` | §6 spec |
| 6 | Docs | `README.md` + `CHANGELOG.md` | aggregation rule + E-SL1→E-SL2 |
| 7 | Validation | phpunit · validator · compile · grep (reason/fallback/hardcoded taxonomy) | AC-1..AC-6 |
| 8 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-32ACTR/` | |

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Keyed-array identity bị mất khi caller truyền list | Validator bắt buộc string key non-empty → LogicException fail-fast |
| Aggregator trôi thành routing engine | Spec §1 scope chốt; không sort/không selection; review grep |
| E-SL2 cần value object thay keyed array | Option B wrapper reserved — introdu khi có evidence, không speculative |

Rollback: revert — không DB, không config, không carrier code.
