# TASK-7MHH19 — Continue Payment (My Account order view + controller)

**Type:** Task (slice của FEAT-CSWYEJ)
**Mode:** B (frontend customer-area; không đụng checkout flow)
**Placement:** `Secomm/PaymentCore/`: `Controller/Payment/Continue.php`, `Model/Lifecycle/CanContinuePayment.php`, `Block/ContinuePaymentButton.php`, `view/frontend/layout/sales_order_view.xml`, `view/frontend/templates/order/continue-payment.phtml`, `etc/frontend/routes.xml`, i18n
**Risk tier:** Tier 1 (guard bắt buộc đúng — ownership + CSRF + expiry)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (QC pending)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §4.1/§4.8, AC-004→AC-007, AC-014 · Plan: plans/TASK-7MHH19-implementation-plan.md

## Mini Spec

### Goal

Customer thấy nút **Continue Payment** trên order pending chưa hết hạn; click → redirect checkout URL mới từ provider; mọi nhánh từ chối được enforce **server-side** (không chỉ ẩn UI).

### Expected Behavior

- **Guard service** `CanContinuePayment::canContinue(order): bool` (+ `getDenyReason()`): core enabled + method có trong managed + không trong continue_disabled + record active + `expires_at > now` + state `new` + `canCancel()` + total_due > 0.
- **Button** (layout `sales_order_view`, content container): chỉ render khi guard pass; POST form `form_key` + `order_id` → `paymentcore/payment/retry`.
- **Controller**: form_key validate (CSRF) + ownership (order.customer_id == session customer id — sai → 404 redirect + log warn AC-007) + guard pass + adapter `getCheckoutUrl` → redirect URL; deny → redirect order view + message lý do (AC-006: expired/paid/canceled đều từ chối cả khi gọi trực tiếp).
- Template Hyvä-compatible (Tailwind classes tĩnh, không jQuery), strings vi_VN + en_US (BR-001).
- Disable core → nút biến mất + controller deny (AC-014).

### Constraints / Rules

- Action name `Retry` (`Continue` là PHP reserved word).
- Block render điều kiện qua guard service; controller re-validate toàn bộ (không tin UI).
- Ownership: order.customer_id phải = customer session; mismatch → redirect + log warning.
- `cacheable="false"` cho block (visibility phụ thuộc state + expiry).

### Acceptance Criteria

- [ ] AC-1: Order pending + chưa hết hạn → nút hiển thị; click sang VNPAY (URL mới).
- [ ] AC-2: Gọi trực tiếp URL continue cho order expired/paid/canceled/của người khác → bị từ chối + redirect (verify cả khi nút đã ẩn).
- [ ] AC-3: Không form_key / form_key sai → redirect (CSRF pass).

### Out of Scope

Guest continue link (email token — Phase 2) · reminder email.

## Approach

ViewModel-preferred: block mỏng delegate guard service; template render POST form; controller validate-delegate-respond (no logic trong controller — §7.2).