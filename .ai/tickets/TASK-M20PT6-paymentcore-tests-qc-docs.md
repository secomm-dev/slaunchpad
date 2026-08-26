# TASK-M20PT6 — Unit tests + QC matrix + docs/context update

**Type:** Task (slice của FEAT-CSWYEJ)
**Mode:** B
**Placement:** `Secomm/PaymentCore/Test/Unit/**`, `.ai/evidence/FEAT-CSWYEJ/qc-matrix.md`, README/CHANGELOG, project-context diffs
**Risk tier:** Tier 1
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (phpunit run chờ user — env constraint không có php-cli)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §8, AC-016/AC-017 · Plan: plans/TASK-M20PT6-implementation-plan.md

## Mini Spec

### Goal

Unit test coverage lifecycle chính + race-condition quan trọng; QC e2e matrix cho VNPAY sandbox; docs/context cập nhật theo §14.

### Expected Behavior

- **Unit** (`vendor/bin/phpunit app/code/Secomm/PaymentCore/Test/Unit`): Config resolver (managed/override/snapshot semantics), AdapterPool (đủ/thiếu adapter), CancelExpiredOrder (paid-skip / unknown-skip / notpaid-cancel / cancel-throw / lock-busy / force-close), CanContinuePayment (mọi nhánh deny), AssignManagedPayment (assign/noop/disabled).
- **QC matrix** `.ai/evidence/FEAT-CSWYEJ/qc-matrix.md`: 8 kịch bản spec §8 (kèm race simulation chặn IPN + querydr PAID).
- **Context update**: `03` (integration querydr), `04` (module list), `06` (risk rows), estimation CSV.

### Constraints / Rules

- Tests theo Arrange-Act-Assert; happy + edge + error (không chỉ happy path).
- Mock theo constructor DI (pattern Secomm_Tracking Test/Unit).
- QC matrix evidence phải ghi rõ pass/fail từng S-row; không bỏ trống.

### Acceptance Criteria

- [ ] AC-1: phpunit toàn bộ pass (user chạy `! vendor/bin/phpunit ...`).
- [ ] AC-2: QC matrix điền evidence xong (hoặc đánh dấu pending rõ ràng).
- [ ] AC-3: Context diffs sinh xong chờ human review/commit.

### Out of Scope

Integration test framework (repo chưa có infra — đánh dấu coverage gap).

## Approach

Tests theo pattern Test/Unit của Secomm_Tracking (mock qua constructor DI); Arrange-Act-Assert; happy + edge + error.