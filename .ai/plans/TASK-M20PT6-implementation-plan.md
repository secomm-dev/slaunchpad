# TASK-M20PT6 Implementation Plan — Unit tests + QC matrix + docs

| Field | Value |
|---|---|
| Specification | tickets/TASK-M20PT6-paymentcore-tests-qc-docs.md (`## Mini Spec`) · canonical parent SPEC-FEAT-CSWYEJ §8, AC-016/AC-017 |

> **Mode B** · Tier 1 · Status: **Retro-canonical** — test code complete (đã rewrite theo DEC-004 matrix); **phpunit chưa chạy** (env AI không có php-cli); QC matrix PENDING evidence; context docs appended chờ commit.

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Test framework pattern? | `Test/Unit` + PHPUnit TestCase thuần (pattern Secomm_Tracking) — không integration test infra trong repo | audit Tracking |
| Matrix test nào theo DEC-004? | Bỏ 3 case provider-verify (paid-skip/unknown-skip adapter); giữ 6 case: cancel/paid-state/canceled/throw/lock-busy/canCancel-false | DEC-FEATCSWYEJ-004 |
| DateTime mock? | `gmtDate`/`gmtTimestamp` (đổi theo runtime fix API thật) | QC session 2026-08-25 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | ConfigTest (managed/override/default fallback/invalid lines/continue-disabled/cron defaults) | Test/Unit/Model/ConfigTest.php | ✅ code |
| 2 | AdapterPoolTest (empty/indexed/reject non-adapter) | Test/Unit/Model/Adapter/AdapterPoolTest.php | ✅ code |
| 3 | CancelExpiredOrderTest (matrix DEC-004) | Test/Unit/Model/Lifecycle/CancelExpiredOrderTest.php | ✅ code (rewritten) |
| 4 | CanContinuePaymentTest (mọi deny branch) | Test/Unit/Model/Lifecycle/CanContinuePaymentTest.php | ✅ code |
| 5 | AssignManagedPaymentTest (snapshot semantics) | Test/Unit/Model/Lifecycle/AssignManagedPaymentTest.php | ✅ code |
| 6 | QC e2e matrix (12 scenarios + command checks) | .ai/evidence/FEAT-CSWYEJ/qc-matrix.md | ⏳ PENDING evidence |
| 7 | Context docs 03/04/06 + estimation CSV | .ai/project-context/* · .ai/estimation-tracking.csv | ✅ chờ commit |

## Remaining steps (human)

1. `! vendor/bin/phpunit app/code/Secomm/PaymentCore/Test/Unit` — dán output, fix nếu fail.
2. QC matrix điền evidence theo từng S-row (VNPAY sandbox).
3. Dev-evidence update sau khi phpunit + QC pass.

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Unit test lifecycle + race chính | 5 files ~27 cases (matrix khớp DEC-004) |
| QC matrix đầy đủ | qc-matrix.md S-1→S-12 + C-1/C-2 |
| Context cập nhật | 03 (integration), 04 (module), 06 (risks) appends |
