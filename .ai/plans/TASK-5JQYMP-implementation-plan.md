# Implementation Plan: TASK-5JQYMP — v5 fallback eligibility orchestration + legacy RATE strategy

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-5JQYMP (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract) |
| Specification | [specs/SPEC-TASK-5JQYMP-shippingcore-v5-fallback-eligibility.md](../specs/SPEC-TASK-5JQYMP-shippingcore-v5-fallback-eligibility.md) — FULL, VALID (DEC-FEATYA2C0W-005 + architecture v5 §14/§15.1) |
| Decision | [DEC-FEATYA2C0W-005](../records/decisions/DEC-FEATYA2C0W-005.md) — amend addendum: implementation naming `DIRECT_FALLBACK` ≡ architecture wording "FALLBACK_ONLY" (legacy RATE); semantics không đổi |
| Contract basis | TASK-32ACTR (E-SL1) · TASK-M3ME32 (E-SL2) · TASK-XXBN5X (E-SL0) |
| Risk | Low–medium — BC-safe optional params; orchestrator logic thêm 1 nguồn eligibility; chưa có consumer thật của legacy path |

## Approach

4 additions + 3 BC-safe extensions: `FallbackEligibilitySource`/`LegacyRateStrategy` constant
classes, `FallbackEligibilityInterface` + VO (2 flags + factories + getSources), aggregator +
aggregate + orchestrator nhận optional eligibility (BC — callers cũ không đổi). Orchestrator:
eligible = aggregate.hasTechnicalFailure() OR input legacy/input technical; các nhánh khác giữ
nguyên. Naming: `DIRECT_FALLBACK` (implementation) ≡ architecture "FALLBACK_ONLY" (legacy RATE)
— documented, không silent rename.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT ticket_ref + DEC-005 addendum + architecture §15.1 naming note | |
| 2 | Contracts | `Api/Fallback/{FallbackEligibilitySource, LegacyRateStrategy, FallbackEligibilityInterface}.php` | constants + VO contract |
| 3 | VO | `Model/Fallback/FallbackEligibility.php` | factories + getSources |
| 4 | Extend | `ServiceLevelRate{AggregatorInterface, AggregateInterface, Aggregator, Aggregate}` + `ServiceLevelRateOrchestrator{Interface, .php}` | optional params BC-safe |
| 5 | DI | `etc/di.xml` | preference FallbackEligibilityInterface |
| 6 | Tests | `Test/Unit/Model/Fallback/{FallbackEligibilityTest, LegacyRateStrategyTest}.php` + ServiceLevel additions | §6 spec |
| 7 | Docs | `README.md` + `CHANGELOG.md` (0.15.0) | eligibility 2 nguồn + naming mapping |
| 8 | Validation | phpunit + compile + validator + greps | AC-1..AC-5 |
| 9 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-5JQYMP/` | |

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Optional param thêm vào interface → BC | Param cuối, default null — callers cũ không đổi; regression test |
| Legacy eligibility supply sai phía caller | VO chỉ 2 flags; validation là việc orchestrator (đã có matrix tests) |
| Naming DIRECT_FALLBACK vs doc FALLBACK_ONLY | Mapping documented DEC-005 addendum + README + §15.1 note |

Rollback: revert — không DB, không carrier code.
