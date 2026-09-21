# Implementation Plan: TASK-W8SH0N — GHTK RATE error classification → CarrierRateOutcome

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-W8SH0N (parent FEAT-YA2C0W) |
| Mode | B (correctness trên v5 outcome contracts hiện hữu — TASK-NAT3YV) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-W8SH0N.md](../records/tasks/TASK-W8SH0N.md) — MINI, VALID (SPIKE-A1DGPY API semantics) |
| Contract basis | `CarrierRateOutcomeInterface` + `CarrierRate` + `ShippingFailureReason` + `ServiceLevelRateAggregator` (actual code review 2026-09-14) |
| Risk | Medium — rate runtime; customer-facing binary giữ nguyên; classification orchestration-internal |

## Approach

Parser/classifier tách theo §25 boundary: transport (client, giữ) → `FeeResponseMapper::parse()`
(KIND_SUCCESS/BUSINESS_REJECTION/MALFORMED — carrier VO `GhtkFeeResponse`) →
`GhtkRateOutcomeFactory` (map sang shared `CarrierRateOutcome` + `ShippingFailureReason`).
`Carrier\Ghtk::collect` consume: log classification + giữ native hide()/rate (§27). Transport
exception mang shared category (GhtkApiException += optional category) — classifier map category:
NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → TECHNICAL_FAILURE; CLIENT_ERROR/RATE_LIMIT →
UNAVAILABLE (403 auth + 429 conservative, documented).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + plan | spec-first |
| 2 | Parser VO | NEW `Model/Rate/GhtkFeeResponse.php`; `FeeResponseMapper.php` += `parse()` | map() chuyển nội bộ |
| 3 | Classifier | NEW `Model/Rate/GhtkRateOutcomeFactory.php` | từ response + từ transport exception |
| 4 | Exception | `GhtkApiException.php` += optional category; `GhtkApiClient.php` wrap() truyền | backward compat |
| 5 | Carrier | `Carrier/Ghtk.php` collect() flow | log + outcome + native behavior |
| 6 | Tests | factory (mới, gồm real-aggregator integration), FeeResponseMapperTest, GhtkApiClientTest (category), GhtkTest | §33–§35 |
| 7 | Gates | grep anti-patterns §37; ShippingCore diff 0; scoped + full suite; validator | |
| 8 | Evidence + report | `.ai/evidence/TASK-W8SH0N/` | delta matrix §39 |

## Test plan

Factory: success fee → SUCCESS + amount; success=false (+/- error_code) → UNAVAILABLE;
missing fee block / wrong types → TECHNICAL_FAILURE; !delivery → UNAVAILABLE; exception
NETWORK/SERVER_ERROR/TIMEOUT → TECHNICAL; CLIENT_ERROR/RATE_LIMIT → UNAVAILABLE.
Client: category propagated trên exception (403→CLIENT_ERROR, timeout→NETWORK…); retry giữ.
Integration: classifier outcomes → real `ServiceLevelRateAggregator` — business →
hasTechnicalFailure=false; technical → true; GHTK SUCCESS + carrier khác TECHNICAL →
hasSuccessfulRate + suppress (aggregate-level).
Regression: KCXKVR/6YG3HP suites; GhtkTest collect flow với factory real.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Outcome factory sai bucket → fallback sai | real-aggregator integration tests + §33 matrix đủ case |
| GhtkApiException signature đổi vỡ callers | optional param cuối, ctor cũ nguyên vị |
| Log noise | một dòng classification/collect, masked fields |

## Validation gates

Scoped suite · full suite (pre-existing ngoài scope tách riêng) · grep §37 · ShippingCore/VN diff
0 (task này) · `project-ai-validate`.
