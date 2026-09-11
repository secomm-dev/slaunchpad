---
id: DEC-TASKEDS9T5-001
title: 'ZaloPay PAYMENT-FIRST ONLY: IPN tự finalize (không chờ browser), OrderFinalizer là biên quote→order duy nhất với internal authorization + placeOrder guard; xoá hoàn toàn order-first mode (flag/legacy/toggle)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit: 3b26189e5d19e60ea3b778baa56bcc9650d69355
supersedes: []
superseded_by:
work_items: [TASK-EDS9T5]
---

# Decision: ZaloPay payment-first only (TASK-EDS9T5)

## Bối cảnh

Clean base `3b26189e` đã có bộ khung payment-first (PaymentAttempt, fingerprint, OrderFinalizer) nhưng
vẫn chạy sau flag `payment/payment_first` (default 0), giữ legacy order-first ở Start/Return/Ipn +
renderer, IPN không tự tạo order, và không có cơ chế nào phân biệt `placeOrder` của OrderFinalizer với
caller generic (REST/GraphQL/SOAP/OSC/renderer). Ticket lỗi: đơn pending_payment tồn tại dù chưa thanh toán.

## Quyết định

1. **Một luồng duy nhất — payment-first.** Xoá flag `payment_first` (config.xml, system.xml, JS,
   ConfigProvider) và toàn bộ nhánh legacy order-first trong `Start`, `ReturnAction`, `Ipn`,
   `zalopay-wallet.js`. Không có toggle quay về order-first.
2. **IPN tự finalize.** IPN hợp lệ (MAC key2 + amount khớp snapshot) ⇒ attempt PAID ⇒ gọi
   `OrderFinalizer::finalizeOrRecover()` ngay trong ngữ cảnh server-to-server. Browser Return chỉ là
   UX + authoritative fallback + idempotent recovery. Lý do: không được phép tồn tại trạng thái
   "đã trả tiền nhưng không có order vì khách đóng browser".
3. **Authorization tách khỏi payment verification.** Service `OrderPlacementAuthorization` injectable,
   request-scoped (shared object per request), single-use, grant gắn đúng `(quote_id, attempt entity_id,
   app_trans_id)`. `OrderFinalizer` grant → `CartManagementInterface::placeOrder()` → `finally` clear.
   Không dùng registry/session/param/stacktrace/status-alone.
4. **Guard server-side.** Plugin `beforePlaceOrder` trên `Magento\Quote\Api\CartManagementInterface`
   (interface — plugin áp cho implementation `QuoteManagement` qua type hierarchy): quote ZaloPay ⇒
   chặn mọi caller generic; chỉ grant exact của
   OrderFinalizer đi qua. Đăng ký global di.xml (áp cho frontend/webapi_rest/graphql/webapi_soap);
   admin order create không dùng interface này (`AdminOrder\Create:2071` gọi `QuoteManagement::submit`)
   nên không bị ảnh hưởng — đã verify vendor tại base SHA. Quote non-ZaloPay không đổi hành vi.
5. **PAID không terminal.** Finalize lỗi transient ⇒ attempt giữ PAID (transaction rollback), IPN trả
   retryable (http 500) ⇒ ZaloPay retry. Mismatch deterministic (amount/fingerprint/method) ⇒ giữ PAID +
   `last_error` + ack (200) để tránh retry vô ích ⇒ reconcile thủ công. Không bao giờ mark FAILED một
   transaction đã được trả tiền thật.
6. **Xoá dead legacy gateway chain** (`complete`/`ipn` command pool + 6 command + 2 validator +
   2 handler chỉ còn được wire bởi di.xml sau khi bỏ controller legacy). `InitializeCommand` giữ — nó chạy
   trong core `placeOrder` và set PENDING_PAYMENT mà `captureOrder()` của finalizer phụ thuộc.

## Hệ quả

- Trong biên ZaloPay: `Sales Order` ⇒ "đã có payment verification authoritative". Invariant được enforce
  ở 3 tầng: renderer (không gọi place-order), guard (chặn REST/GraphQL/SOAP/generic), finalizer (chủ sở hữu).
- MoMo/VNPAY/ phương thức khác: guard chỉ áp cho quote có method `zalopay` — không đổi hành vi.
- Giới hạn: caller nội bộ có type-hint trực tiếp `QuoteManagement::placeOrder` (bỏ qua interface) sẽ không
  qua guard — không tồn tại caller như vậy trong codebase (verified bằng grep + CodeGraph tại base).
