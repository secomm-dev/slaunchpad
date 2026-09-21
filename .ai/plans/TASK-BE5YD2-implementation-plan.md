# Implementation Plan: TASK-BE5YD2 — GHTK CREATE lifecycle reliability

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-BE5YD2 (parent FEAT-YA2C0W) |
| Mode | B (material behavior change ORDER_ID_EXIST → DEC-TASKBE5YD2-001 theo §38) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-BE5YD2.md](../records/tasks/TASK-BE5YD2.md) — MINI, VALID (SPIKE-A1DGPY §2 + official CREATE docs) |
| Risk | Medium — create runtime; recovery fail-closed qua identity validation; native lifecycle bảo vệ |

## Approach

Typed parser trước (`GhtkCreateResponse` VO + `OrderResponseMapper::parse` — normalization step
chung cho success + duplicate §19), rồi service lifecycle: transport category → business/technical
failure message; DUPLICATE_EXISTING → identity validation (expected order.id vs provider partner_id)
→ RECOVERED_EXISTING hoặc hard conflict. Deterministic id giữ nguyên — retry dùng lại id cũ → hit
recovery path (§12). Single attempt giữ (`RetryPolicy::singleAttempt`).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + plan + DEC-TASKBE5YD2-001 | §38 material change |
| 2 | Parser VO | NEW `Model/OrderSubmit/GhtkCreateResponse.php`; rewrite `OrderResponseMapper::parse()` | normalization §19; ghtk_label primary cho duplicate (§20) |
| 3 | Result VO | `OrderSubmitResult.php` += recovered/providerStatus | optional params |
| 4 | Service | `OrderSubmitService.php` | lifecycle match + validation + logging + messages |
| 5 | Tests | `OrderResponseMapperTest` rewrite; `OrderSubmitServiceTest` update + recovery/retry scenarios | §31–§35 |
| 6 | Gates | grep §37 (no retry loop; CarrierRateOutcome trong OrderSubmit* = 0); scoped + full suite; validator | |
| 7 | Evidence + report | `.ai/evidence/TASK-BE5YD2/` | delta matrix §39 |

## Test plan

Parser: CREATED tracking_id precedence/label fallback; DUPLICATE identity extraction (top-level +
order block); BUSINESS; MALFORMED (missing success key/garbage/success-thiếu-identity).
Service: recovered flow (result recovered=true + comment); partner mismatch → hard; missing
partner → hard; missing label → hard; business error_code → business failure; 403/400 → business
non-retry; timeout/network/5xx → technical non-retry; retry scenario attempt1 technical →
attempt2 duplicate-match → recovered, cùng partner id. Regression: KCXKVR/6YG3HP/W8SH0N suites.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Exact ORDER_ID_EXIST response shape khác staging | parser defensive (top-level + order block); mismatch/missing → hard failure fail-closed; NEEDS_RUNTIME_VERIFICATION documented |
| Recovery nhầm đơn khác | bắt buộc partner_id === order.id; thiếu → hard failure |
| Message change làm admin bối rối | message hướng dẫn retry an toàn cùng reference; customer-facing vẫn binary |

## Validation gates

Scoped suite · full suite (pre-existing tách riêng) · grep §37 · ShippingCore/VN diff 0 · validator.
