# TASK-8FZ8YX — Purchase/refund observers + outbox insert + consent gating

**Type:** Task (slice của FEAT-31X6N2 — order lifecycle hooks)
**Priority:** High
**Estimate:** ~10h
**Mode:** A (order state transition + creditmemo lifecycle — Tier-2)
**Placement:** `app/code/Secomm/Tracking/Observer/`, `app/code/Secomm/Tracking/etc/events.xml`, `app/code/Secomm/Tracking/Model/Consent/`
**Risk tier:** Tier 2 (order lifecycle, VNPAY IPN / Mollie webhook interaction)
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete — chờ order e2e QC (D/E/G) + TL review (Tier-2 order lifecycle)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §8, §9, §11 · Plan: [TASK-8FZ8YX plan](../plans/TASK-8FZ8YX-implementation-plan.md)

## Approach

> Retro-canonical 2026-08-27 — distilled từ work đã dev-complete + runtime fixes session 25–27/08.

1. **Purchase hook = state transition trên `sales_order_save_after`** (D3): gate `isPaidTransition()` gồm 2 điều kiện — state mới ∈ {processing, complete} AND `getOrigData('state') !== state mới`. Xử đúng: webhook Mollie + IPN VNPAY + admin invoice đều quy về đúng 1 transition fire; save lặp (state không đổi) không re-fire. `payment_review` bị loại có chủ đích (chưa chắc chắn thanh toán).
2. **Observer contract:** chỉ normalize → hash → insert outbox qua `EnqueueService` (0 HTTP, toàn bộ trong try/catch — tracking fail không bao giờ break order save; log error qua channel riêng). EnqueueService: master-switch check + consent marketing gate + per-vendor `isConfigured()`/`mapEventName()` → status pending|skipped.
3. **Refund:** observer `sales_order_creditmemo_save_after`, gate config `refund/enabled` (default OFF); mỗi CM 1 event, value = CM grand_total (spec §4 assumption). Meta removed (DEC-002) — chỉ TikTok nhận refund.
4. **Consent (D5):** `CookieRestrictionEvaluator` implements `ConsentEvaluatorInterface` — đọc Cookie Restriction Mode + cookie `user_allowed_save_cookie`; OFF = cho phép (VN default). DI preference swap được cho CMP tương lai.
5. **Runtime fixes thực tế (đã xảy ra, giữ làm lesson):** `new TrackingEventQueue()` thủ công → fatal thiếu DI args → đổi sang `TrackingEventQueueFactory->create()`; thiếu `const CACHE_TAG` cho IdentityInterface; module từng không có trong config.php sau một lần reset — kiểm tra `grep Secomm_Tracking app/etc/config.php` khi observer im lặng.

**QC pending:** Mollie test order (webhook confirm → 1 row), VNPAY sandbox (IPN + return double-confirm → vẫn 1 row — transition guard), creditmemo partial/full với refund enabled/disabled, consent=false → row skipped.

## Description

- `Observer/PurchaseFire.php` — theo DEC D3: hook order state transition → processing (cụ thể: plugin/observer trên state change, KHÔNG `sales_order_save_after` mù). Guard: chỉ fire 1 lần per order (check flag — outbox UNIQUE (event_id, vendor) + state guard); skip khi tracking disabled hoặc consent marketing=false.
- `Observer/RefundFire.php` — `sales_order_creditmemo_save_after`, chỉ khi config `secomm_tracking/refund/enabled=1` (default OFF); partial refund = 1 event per creditmemo (spec §4 assumption).
- Cả 2 observer: normalize (TASK-NNKTRM) → hash → **insert outbox row only** (0 HTTP, 0 heavy work — observer rule §7.2) → cron TASK-VRKJKQ flush.
- `Model/Consent/CookieRestrictionEvaluator` impl thật (server-side đọc config + cookie `user_allowed_save_cookie` khi request context có) — D5.
- Verify state matrix với Mollie (webhook confirm) + VNPAY (IPN `Order/Ipn`): purchase fire khi payment confirmed bất kể path.

## Mini Spec

### Goal

Purchase + refund events vào outbox đúng 1 lần per order/creditmemo, đúng state, tôn trọng refund-enable + consent flags — không bao giờ chậm checkout.

### Expected Behavior

- Mollie: order place → redirect → webhook confirm → state processing → outbox row purchase (1 lần).
- VNPAY sandbox: IPN confirm → state processing → purchase fire 1 lần (kể cả khi customer return + IPN cùng lúc confirm).
- Order pending/hold/canceled: KHÔNG fire.
- Creditmemo tạo (partial/full) + refund enabled → outbox row refund; disabled → không row.
- Observer runtime <50ms (chỉ normalize + insert).

### Constraints / Rules

- State guard idempotent: fire điều kiện trên **transition** (from ≠ to), không phải current state — handle save thứ 2 không re-fire.
- KHÔNG modify `Vnpayment_VNPAY`, Mollie module, core Sales — observer trên event chuẩn + plugin nếu cần.
- Consent=false → row status `skipped` (audit được) hoặc không insert + log — chọn insert-skipped cho reconcile.

### Out of Scope

Adapters/flush (TASK-VRKJKQ) · browser events (TASK-E0NG8Z) · refund GA4 (DEC D4 defer).

### Acceptance Criteria

- [ ] **AC-1:** Mollie test order: purchase row xuất hiện đúng 1 lần sau webhook confirm; không row khi order pending payment.
- [ ] **AC-2:** VNPAY sandbox: IPN + return double-confirm → vẫn 1 purchase row (transition guard).
- [ ] **AC-3:** Creditmemo partial + full (refund enabled): mỗi CM 1 refund row value=CM grand_total; disabled: 0 row.
- [ ] **AC-4:** Consent=false → row skipped_consent (không vendor call sau flush).
- [ ] **AC-5:** Integration test: observer trên save thứ 2 (state không đổi) → không row mới; transition pending→processing → đúng 1 row.
- [ ] **AC-6:** Checkout e2e (Mollie test) response time không tăng >50ms (observer chỉ insert).

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** SPEC-FEAT-31X6N2 §8/§9/§11 · **DEC:** DEC-FEAT31X6N2-001 (D3, D4, D5)