# TASK-JSQN6P — Assign managed payment + expiry snapshot (observer)

**Type:** Task (slice của FEAT-CSWYEJ)
**Mode:** B (observer + config resolver logic, order read-only — không state transition)
**Placement:** `app/code/Secomm/PaymentCore/Model/Lifecycle/AssignManagedPayment.php` + `etc/events.xml`
**Risk tier:** Tier 1 (ghi record mới khi place order; không mutate order)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (unit tests trong TASK-M20PT6; e2e QC pending)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §4.1/AC-001/AC-002/AC-003/AC-014

## Mini Spec

### Goal

Order place bằng managed method → payment record tạo với `expires_at` snapshot tại thời điểm place (D2/D4/D5); order không manage → không record.

### Expected Behavior

- Observer trên `sales_order_payment_place_end` (fallback xác minh runtime: nếu event không mang order id, đổi sang `checkout_submit_all_after` — ghi chú trong ticket).
- Guard: core enabled + method trong managed methods (scope website của order) → insert `{order_id, store_id, method_code, expires_at = now + resolved_minutes, status=active}`.
- Expiry resolution: per-method override (textarea `method_code:minutes`, validate + ignore line sai format có log warn) > default_expiry_minutes.
- Core disabled / method unmanaged → no-op hoàn toàn (AC-003/AC-014).
- Exception trong observer **không được** chặn place order — catch + log error (record thiếu = cron không cancel, an toàn hướng conservative).

### Acceptance Criteria

- [ ] AC-1: Order VNPAY (assigned) → 1 record active, expires_at đúng config; đổi config sau đó không đổi expires_at của order cũ (snapshot, AC-002 spec).
- [ ] AC-2: Order COD/VNPAY-unassigned → không record (AC-003 spec).
- [ ] AC-3: Observer exception (vd DB) → order vẫn place thành công + log error.

### Out of Scope

Cron expiry (TASK-PMKWS6) · Continue Payment (TASK-7MHH19).

## Approach

Observer class lightweight: validate → delegate sang service `AssignManagedPayment` (Config resolver + repository save + log `assign`). Unit test: managed/unmanaged/disabled/override/snapshot.