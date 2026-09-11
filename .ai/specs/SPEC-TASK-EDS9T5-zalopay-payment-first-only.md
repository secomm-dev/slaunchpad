# SPEC-TASK-EDS9T5 — ZaloPay Payment-First Only (không Sales Order trước khi thanh toán được xác thực)

- **Work item**: TASK-EDS9T5
- **Mode**: A (payment/checkout/order-lifecycle → Tier-2)
- **Specification level**: FULL
- **spec_status**: VALID
- **Base SHA đã audit**: `3b26189e5d19e60ea3b778baa56bcc9650d69355` (`dev/development/thanhle`, workspace sạch)
- **Nguồn yêu cầu**: Ticket `[ZaloPay][Create order] Lỗi tạo order khi redirect qua ZaloPay gateway dù chưa thanh toán`

## 1. Bối cảnh & Root cause (audit từ CLEAN BASE)

Hành vi sai của ticket: Checkout → chọn ZaloPay → redirect provider → Sales Order **đã tồn tại** với
trạng thái `pending_payment` → khách huỷ/không thanh toán → đơn không thanh toán vẫn nằm trong hệ thống.

Audit module `Secomm_ZaloPay` tại clean base cho thấy payment-first **đã được partial-implement nhưng
không phải luồng canonical duy nhất**, với 3 lỗ hổng:

1. **Feature flag + legacy fallback**: `Start` có nhánh `payment/zalopay/payment_first` (mặc định `0`
   trong `etc/config.xml` — tức default là **order-first**), có `executeLegacy()` và admin toggle trong
   `system.xml`. `ReturnAction`/`Ipn` giữ nguyên luồng order-first khi payload không mang attempt.
   Renderer JS có nhánh `placeOrder()` legacy và gate `window.checkoutConfig.payment.zalopay.paymentFirst`.
2. **IPN không tự finalize**: `IpnProcessor` chỉ đánh dấu attempt `PAID` rồi chờ browser Return
   ("order placement deferred to Return/cron") — cron reconcile **không tồn tại**. Khách thanh toán
   thành công rồi đóng trình duyệt ⇒ tiền thật nhưng **không có order** (lỗi đối xứng với ticket).
3. **Không có authorization/guard cho `placeOrder`**: `OrderFinalizer` gọi `CartManagementInterface::placeOrder`
   trực tiếp; mọi đường generic (REST `POST /carts/mine/payment-information`, `POST /V1/carts/{id}/order`,
   GraphQL `placeOrder`, SOAP, renderer legacy, OSC submit generic) đều tạo được Sales Order cho quote
   ZaloPay **chưa thanh toán**. `PAID`-thành-viên-không-ai-guard ⇒ mọi attempt `PAID` cũ đều "cho phép" đặt hàng.

## 2. Yêu cầu nghiệp vụ

- **KHÔNG thanh toán được xác thực ⇒ KHÔNG Sales Order.** Order chỉ tồn tại sau khi provider xác nhận
  thanh toán một cách authoritative (IPN MAC + amount, hoặc v2/query ở Return).
- **PAYMENT-FIRST ONLY** — một luồng production duy nhất. CẤM: feature flag, legacy mode, order-first
  fallback, compatibility switch, admin toggle quay về order-first.

## 3. Kiến trúc mục tiêu

```
ACTIVE QUOTE → save payment info → PaymentAttempt (INITIATED, snapshot contract+fingerprint)
→ ZaloPay create-order (get_pay_url) → attempt ACTIVE → redirect ZaloPay     [KHÔNG có Order]

IPN (MAC + amount hợp lệ)  ─┐
Return (v2/query hợp lệ)   ─┴→ attempt PAID → OrderFinalizer (lock row, validate contract,
→ OrderPlacementAuthorization grant → đúng 1 lần CartManagement::placeOrder → bind attempt↔order
→ capture/final → FINALIZED, commit) → Return: success page
```

- **OrderFinalizer là biên duy nhất** quote → Sales Order cho ZaloPay.
- `OrderPlacementAuthorization`: service injectable, request-scoped, **single-use**, grant gắn chính xác
  `(quote_id, attempt entity_id, app_trans_id)`; guard tiêu thụ grant ở lần `placeOrder` khớp;
  `OrderFinalizer` clear bằng `try/finally`. CẤM: registry, session token, query param, status-alone,
  stacktrace, config secret.
- Guard plugin trên `Magento\Quote\Api\CartManagementInterface::placeOrder` chặn mọi caller generic
  cho quote ZaloPay (frontend/webapi_rest/graphql/webapi_soap — đăng ký global di.xml; admin order create
  dùng `AdminOrder\Create::createOrder → QuoteManagement::submit()` nên KHÔNG bị ảnh hưởng — đã verify
  `vendor/magento/module-sales/Model/AdminOrder/Create.php:2071`). Quote không phải ZaloPay: hành vi
  Magento không đổi.
- **IPN-first**: IPN hợp lệ ⇒ PAID ⇒ `OrderFinalizer` ngay trong ngữ cảnh server-to-server (không phụ thuộc
  browser session). Lỗi transient khi finalize ⇒ attempt giữ PAID, IPN trả lỗi để ZaloPay retry (retryable
  theo callback contract). `ContractMismatchException` (amount/fingerprint/method) ⇒ **không tự tạo order**,
  attempt giữ trạng thái money-real (PAID/FINALIZED), `last_error` ghi evidence, IPN **ack** để tránh
  retry vô ích — reconcile thủ công.
- Return = UX + authoritative fallback (v2/query) + idempotent recovery (FINALIZED ⇒ trả đúng order đã
  bind, không trùng). IPN/Return race ⇒ khoá row + duy nhất một order (unique `app_trans_id`,
  unique `order_id` trong `secomm_zalopay_payment_attempt`).
- Abandon/cancel ở provider ⇒ không order; quote còn dùng được; attempt về FAILED/STALE/EXPIRED.

## 4. Acceptance criteria

1. Chọn ZaloPay và redirect sang provider KHÔNG tạo Sales Order (kiểm chứng cả luồng Luma lẫn Mageplaza OSC).
2. IPN thành công không cần Return vẫn tạo **đúng 1** order; Return sau đó recover đúng order đó.
3. Return trước/IPN sau và ngược lại: hội tụ về 1 attempt + 1 order + FINALIZED.
4. `PAID` đơn thuần / attempt `PAID` lịch sử KHÔNG cho phép generic `placeOrder`; chỉ grant exact của
   OrderFinalizer cho phép; grant bị tiêu thụ sau 1 lần và được clear cả khi `placeOrder` throw.
5. REST / payment-information / GraphQL / SOAP đặt order cho quote ZaloPay bị chặn; method khác không đổi.
6. Amount/fingerprint/method đổi sau thanh toán ⇒ không order, attempt giữ PAID + `last_error`.
7. MAC IPN sai, `app_trans_id` lạ ⇒ không mutation, không order.
8. Không còn dấu vết order-first: không flag, không toggle, không nhánh legacy trong Start/Return/Ipn/renderer.
9. Toàn bộ test matrix (§18 ticket) được tự động hoá ở mức unit; unit suite `Secomm_ZaloPay` pass; PHPCS
   Magento2 sạch; `setup:di:compile` pass; compiled plugin-list có guard.

## 5. Ngoài phạm vi

- Không sửa Mageplaza source, vendor, module Secomm khác (MoMo/VNPAY…), theme.
- Refund flow giữ nguyên (chỉ đọc audit).
- Migration dữ liệu order-first cũ: dự án new-build chưa launch ⇒ không có order legacy trong production;
  ghi nhận ở KNOWN_LIMITATIONS.
