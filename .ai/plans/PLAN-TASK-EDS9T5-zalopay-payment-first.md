# PLAN-TASK-EDS9T5 — ZaloPay payment-first only

- **Work item**: TASK-EDS9T5
- **Spec**: [SPEC-TASK-EDS9T5-zalopay-payment-first-only.md](../specs/SPEC-TASK-EDS9T5-zalopay-payment-first-only.md) (`spec_status: VALID`)
- **Workspace**: `../slaunchpad-workspaces/zalopay-payment-first` (branch `task/zalopay-payment-first`, base `3b26189e`)

## Approach (tổng quan)

Giữ nguyên những gì clean base đã đúng (PaymentAttempt entity + state machine + repository locking +
schema unique key, QuoteContractFingerprint, PaymentAttemptManagement, ReturnProcessor, khung
OrderFinalizer); sửa 3 lỗ hổng: xoá legacy/flag, IPN tự finalize, thêm authorization + guard.

## Steps

1. **`Service/OrderPlacementAuthorization`** (mới): service injectable, property request-scoped.
   `grant(int $quoteId, int $attemptId, string $appTransId): void` /
   `consumeIfMatches(...): bool` (single-use) / `clear(): void`. Không static, không session, không registry.
2. **`Plugin/Quote/CartManagementPlaceOrderGuard`** (mới): `beforePlaceOrder` trên
   `Magento\Quote\Api\CartManagementInterface` trong `etc/di.xml` (global — frontend/webapi_rest/graphql/
   webapi_soap). Load quote bằng `QuoteRepository`; method ≠ `zalopay` ⇒ return (không đổi hành vi);
   method `zalopay` ⇒ `consumeIfMatches(quote_id, …)` thất bại ⇒ throw `LocalizedException`.
   Admin order create (`QuoteManagement::submit`) không bị đụng (verified vendor Create.php:2071).
3. **`Service/OrderFinalizer`**: inject `OrderPlacementAuthorization`; quanh
   `cartManagement->placeOrder()` mở `grant()` + `try/finally clear()`. Cập nhật comment chữ ký.
4. **`Service/IpnProcessor`**: sau khi mark PAID (amount hợp lệ) ⇒ gọi `OrderFinalizer::finalizeOrRecover()`
   trong try/catch: `ContractMismatchException` ⇒ giữ PAID + ack 200 "recorded for reconciliation";
   exception khác (transient) ⇒ trả retryable (http 500, errors=true). Duplicate IPN trên FINALIZED ⇒ ack
   idempotent (giữ nguyên). Inject `OrderFinalizer` qua DI (proxy để tránh circular? — IpnProcessor ← OrderFinalizer
   không circular: OrderFinalizer không phụ thuộc IpnProcessor).
5. **`Controller/Payment/Start`**: bỏ `executeLegacy`/`executePaymentFirst` split + flag; chỉ chạy
   payment-first; quote không initiable ⇒ `handleFailure()` (payment failure + message + redirect cart) —
   không fallback. Bỏ DI không dùng (commandPool/orderRepository/paymentDataObjectFactory/ContextHelper…).
6. **`Controller/Payment/ReturnAction`**: bỏ `executeLegacy`; luôn `ReturnProcessor`
   (`apptransid` bắt buộc).
7. **`Controller/Payment/Ipn`**: bỏ nhánh order-first (`readOrderId`, `commandPool('ipn')`, order
   loading); unknown `app_trans_id` ⇒ 404 + log, không order, không mutation.
8. **Frontend**: `zalopay-wallet.js` — bỏ flag `isPaymentFirst` + `placeOrder()` legacy; nút luôn:
   validate → `selectPaymentMethod` → `setPaymentMethodAction` (PUT set-payment-information, không tạo
   order) → `redirectOnSuccessAction.execute()` → Start. `ZaloPayConfigProvider`: bỏ `paymentFirst`.
   `config.xml` + `system.xml`: xoá node/field `payment_first` (giữ `attempt_ttl`).
9. **Xoá legacy gateway chain** (chỉ còn wire trong di.xml): `CompleteCommand`,
   `CompleteUpdateDetailsCommand`, `IpnCommand`, `IpnUpdateDetailsCommand`, `UpdateDetailsCommand`,
   `UpdateOrderCommand`, `Validator/ReturnValidator`, `Validator/CompleteValidator`,
   `Response/TransactionReturnHandler`, `Response/TransactionCompleteHandler`;
   `TransactionReader::readOrderId/isIpn/IS_IPN`; dọn `etc/di.xml` (command pool `complete`/`ipn` + type blocks).
   Giữ: `InitializeCommand` (chạy trong core placeOrder — set PENDING_PAYMENT cho finalizer capture),
   refund chain, `get_pay_url`, `query_transaction`.
10. **Tests**: cập nhật unit tests bị ảnh hưởng; thêm test authorization/guard/IPN-finalize/matrix theo
    spec AC (§4) — map 35 case ticket §18 vào unit suite (mock gateway/repository).
11. **Validate**: phpunit (docker recipe), PHPCS Magento2, `php -l`, `setup:di:compile`,
    compiled plugin-list proof.
12. **CodeGraph proof** + evidence `.ai/evidence/TASK-EDS9T5/` + receipt.

## Rủi ro & biện pháp

- **Guard chặn nhầm admin**: admin order create dùng `submit()` — verified vendor; compile plugin-list
  kiểm chứng sau build.
- **Circular DI**: `IpnProcessor → OrderFinalizer → (CartManagement) → Guard → Authorization` — không vòng.
- **OSC**: Place Order button click đúng nút renderer (`button.action.primary.checkout`) — renderer mới
  giữ binding `continueToZaloPay` nên OSC tự động chạy payment-first (verified `Mageplaza_Osc/js/view/review/placeOrder.js`).
- **IPN retry loop khi mismatch**: ack 200 cho mismatch deterministic (đã quyết định ở DEC).
