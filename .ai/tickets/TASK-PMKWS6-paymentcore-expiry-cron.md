# TASK-PMKWS6 — Expiry cron: verify + cancel expired pending payments

**Type:** Task (slice của FEAT-CSWYEJ)
**Mode:** A (order state transitions — cancel qua cron; Tier 2)
**Placement:** `Secomm/PaymentCore/Model/Lifecycle/{ExpirePayments,CancelExpiredOrder}.php` + `etc/crontab.xml` + `etc/cron_groups.xml`
**Risk tier:** Tier 2 (order cancel + inventory release)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (unit tests trong TASK-M20PT6; e2e QC + TL review pending)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §4.1/§4.7, AC-008→AC-012, DEC D3/D3b/D5 · Plan: plans/TASK-PMKWS6-implementation-plan.md

## Mini Spec

### Goal

Cron group `secomm_paymentcore` (mỗi 5'): scan records `active` + `expires_at < now` → **chỉ cancel khi chắc chắn chưa thanh toán**; mọi tình huống không chắc chắn → skip + retry.

### Expected Behavior

Per record (batch 50, order by expires_at):
1. **Lock** `paymentcore_cancel_{order_id}` (LockManager, timeout 0) — busy → skip.
2. **Reload + state check**: state `new` + `canCancel()` mới được tiếp tục; state khác → resolve record (`completed` nếu processing/complete — đã pay; `canceled` nếu canceled).
3. **Adapter verify** (`isPaymentCompleted`): `PAID` → skip + warn (chờ IPN); `UNKNOWN` → skip + retry_count++; `NOT_PAID` → `OrderManagementInterface::cancel()` (Magento std flow → MSI reservation compensate).
- Adapter thiếu (method không có adapter đăng ký) → log error + giữ active.
- Cancel throw → catch, log error, giữ active retry (AC-012 spec).
- **Force-close (D3b):** record active > force_close_days (7) → status `error` + log error (ops pickup) — **không cancel order** (never-cancel-when-uncertain, spec §7).
- Cron **không check enabled flag** (D5 snapshot semantics — disable chỉ dừng tạo record mới; tắt hẳn = disable module).
- Log mọi nhánh (`expire_candidate/provider_verify/cancel_success/cancel_skipped{reason}/cancel_failed`) vào `secomm_paymentcore.log` — masked (AC-015 spec).

### Constraints / Rules

- Cancel qua `OrderManagementInterface::cancel()` duy nhất — không SQL/set state tay.
- Cron KHÔNG đọc enabled flag (DEC D5 snapshot semantics).
- Force-close (7 ngày) chỉ set record error — không bao giờ cancel order.
- crontab.xml: `schedule` đứng trước `config_path` theo XSD (đã bỏ config_path).

### Acceptance Criteria

- [ ] AC-1: Đơn expired NOT_PAID → canceled qua std flow; salable qty trả về (QC verify MSI).
- [ ] AC-2: Đơn paid (state processing) trong scan → KHÔNG cancel, record completed (AC-009 spec).
- [ ] AC-3: Verify UNKNOWN → không cancel, retry_count tăng, run sau xử lý lại.
- [ ] AC-4: Cancel throw → record giữ active, cron chạy tiếp batch không chết.

### Out of Scope

querydr API call (thuộc adapter TASK-KKPDNZ — cron chỉ consume VerifyResult).

## Approach

`ExpirePayments` (cron entry: batch fetch + force-close + loop) → `CancelExpiredOrder.process(record)` (lock + reload + verify + cancel + unlock, try/finally). Unit test với mock full matrix.