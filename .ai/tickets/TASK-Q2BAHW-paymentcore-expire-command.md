# TASK-Q2BAHW — Console command `paymentcore:expire:run` (QC trigger cho expiry cron)

**Type:** Task (slice của FEAT-CSWYEJ — QC tooling)
**Mode:** C (console command + refactor nhẹ `ExpirePayments` thêm optional filter; không đụng race-guard logic)
**Placement:** `app/code/Secomm/PaymentCore/Console/Command/ExpirePaymentsCommand.php` + `Model/Lifecycle/ExpirePayments.php` (optional arg) + `etc/di.xml` (CommandList)
**Risk tier:** Tier 1 (tooling; per-order logic — CancelExpiredOrder — không đổi)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (static checks xanh; runtime verify chờ user)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §8 (QC), Mode C per DEC-TASKZ132WA-002 · Plan: plans/TASK-Q2BAHW-implementation-plan.md

## Mini Spec

### Goal

QC có thể chủ động chạy expiry processing trên server thay vì chờ cron schedule 5' — đặc biệt cho các kịch bản S-5/S-7/S-11 của QC matrix.

### Expected Behavior

- `bin/magento paymentcore:expire:run` — chạy **đúng logic** `ExpirePayments::execute()` như cron job (một code path, không drift). Output: số record đã xử lý + reminder đọc log.
- `--dry-run` — liệt kê candidate (order/method/expires_at/retry_count) **không xử lý gì** — QC xem trước dữ liệu.
- `[order-id]` (optional) — lọc theo order entity id; khi có filter, bỏ qua expiry-window check (QC nhắm đơn đã biết) nhưng vẫn enforce `status=active` + toàn bộ race guard trong CancelExpiredOrder (lock/state/querydr).
- Cron entry `etc/crontab.xml` gọi `execute()` không đối số — hành vi cron **không đổi**.

### Constraints / Rules

- Một code path duy nhất với cron (gọi `ExpirePayments::execute()`) — không nhân bản logic.
- Order-filter vẫn giữ status=active + toàn bộ guard trong CancelExpiredOrder.
- Dry-run chỉ đọc (getCandidates), không write DB.

### Acceptance Criteria

- [ ] AC-1: `bin/magento paymentcore:expire:run --dry-run` liệt kê candidate, không đổi DB.
- [ ] AC-2: `bin/magento paymentcore:expire:run` xử lý candidate (cancel đơn expired unpaid; verify bằng S-5 matrix).
- [ ] AC-3: `bin/magento paymentcore:expire:run <order_id>` chỉ xử lý order đó; đơn paid không bị cancel (S-7 vẫn an toàn qua querydr).

### Out of Scope

Cron schedule/format · CancelExpiredOrder logic (không đổi) · Continue Payment.

## Approach

Command mỏng delegate `ExpirePayments`; thêm `getCandidates(?int $orderId)` public cho dry-run + `execute(?int $orderId)` optional arg (cron gọi không arg = old behavior). DI: CommandList argument.
