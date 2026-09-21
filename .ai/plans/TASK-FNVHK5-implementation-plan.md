# Implementation Plan: TASK-FNVHK5 — GHTK CANCEL lifecycle

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-FNVHK5 (parent FEAT-YA2C0W) |
| Mode | B (carrier capability mới theo official docs — không architecture mới) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-FNVHK5.md](../records/tasks/TASK-FNVHK5.md) — MINI, VALID (SPIKE-A1DGPY §12 official CANCEL contract) |
| Risk | Medium — mutation op; chưa auto-wired (carrier-owned service only); single-attempt fail-closed |

## Approach

Client method (+1) trên transport abstraction sẵn có; typed parser/service mirror pattern
TASK-BE5YD2 (VO kinds + service mapping transport category). ALREADY_CANCELLED là benign
first-class (message-contract match — GHTK cancel response không có error_code field).
No-auto-wire: Magento cancel flow integration là follow-up OrderOperations/bridge (§19).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + plan | spec-first |
| 2 | Client | `GhtkApiClient.php` += `cancelShipment(string $identifier): array` | POST single-attempt; rawurlencode |
| 3 | VO | NEW `Model/Cancel/GhtkCancelResponse.php` | 4 kinds + message/identifier diagnostics |
| 4 | Service | NEW `Model/Cancel/CancelShipmentService.php` | parser + transport mapping + masked log; no Magento mutation |
| 5 | Tests | NEW `Test/Unit/Model/Cancel/CancelShipmentServiceTest.php` | §31–§37 matrix + retry scenario + identifier encoding |
| 6 | Docs | README + CHANGELOG | caller expectation (§18) |
| 7 | Gates | grep §39; scoped + full suite; validator | |

## Test plan

Service: success → CANCELLED; already-cancelled message → ALREADY_CANCELLED; state-rejection
message → BUSINESS_REJECTION; other success=false → BUSINESS_REJECTION; timeout/network/5xx
exception → TECHNICAL_FAILURE; CLIENT_ERROR/RATE_LIMIT → BUSINESS_REJECTION; invalid JSON →
TECHNICAL_FAILURE; empty identifier → InvalidArgumentException; URI path encoding assert;
single-call-per-invocation assert; manual-retry scenario (technical → already-cancelled).
Regression: scoped Ghtk suites (KCXKVR/6YG3HP/W8SH0N/BE5YD2).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| POST/GET discrepancy | chọn POST (official Endpoint section) + NEEDS_RUNTIME_VERIFICATION; đổi = 1 dòng client |
| already-cancelled message variant | exact documented message match + NEEDS_RUNTIME_VERIFICATION; unmatched → BUSINESS_REJECTION (fail-closed, không fake benign) |
| Chưa auto-wire Magento cancel | intentional (§19) — caller expectation documented trên service |

## Validation gates

Scoped suite · full suite (pre-existing tách riêng) · grep §39 · ShippingCore/VN diff 0 · validator.
