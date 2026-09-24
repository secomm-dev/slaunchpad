---
Specification ID: SPEC-TASK-7HP4R0
Feature ID: NONE
Specification Level: FULL
last_verified: 2026-09-18
verified_against_commit: 87f4db4459c67fe92ce6ba99a61c4d833ebde7bd
status: current
work_items: [TASK-7HP4R0]
external_refs: [github:thanhle74/slaunchpad#3]
---

# Spec: [MoMo][MOMO-01] Payment-first order finalization cho Secomm_MoMo

> **Project**: Secomm Launchpad · Magento 2.4.8-p5 + PHP 8.3
> **Ticket**: GitHub issue #3 (TASK_ID = MOMO-01) · record `TASK-7HP4R0` · Mode A (payment — Tier 2 risk)
> **Tham chiếu kiến trúc được chấp nhận**: `Secomm_ZaloPay` (FEAT-ZLP1PF, DEC-FEATZLP1PF-003) — mô hình
> payment-first đã được TL review; task này mirror policy, thu gọn theo NON_SCOPE của issue (không recovery
> cron, không refund redesign, không shared abstraction cross-module).

## 1. Feature Overview

**Feature name**: MoMo payment-first order finalization (MOMO-01)

**Feature type**: Enhancement (lifecycle conversion — order-first → payment-first)

**Priority**: P1 (do issue lane đặt — deadline-oriented)

**Goal**: `NO verified MoMo payment → NO Magento Sales Order`. Bắt đầu payment từ **Active Quote**, chốt
hợp đồng thanh toán bất biến (amount + contract fingerprint), tạo giao dịch MoMo, và chỉ tạo Magento
Sales Order đúng một lần sau khi MoMo xác nhận có thẩm quyền (Notify/IPN hoặc server-side query) —
qua MỘT finalizer canonical, dùng native Magento order/invoice/payment accounting.

## 2. User Stories

- **US-001**: Là khách hàng, tôi muốn thanh toán qua MoMo wallet mà không bị tạo đơn hàng trước khi tôi
  hoàn tất thanh toán, để giỏ hàng của tôi không bị "đóng băng" thành một đơn pending không cần thiết.
- **US-002**: Là chủ shop, tôi muốn chỉ những giao dịch MoMo đã được xác thực (signature + identity +
  amount) mới sinh ra Sales Order + invoice, để không có đơn rác/hàng tồn kho bị giữ oan.
- **US-003**: Là chủ shop, tôi muốn callback trùng lặp (Notify/Return) không bao giờ tạo đơn/invoice/email
  thứ hai, để sổ kế toán luôn sạch.

## 3. Acceptance Criteria (từ issue #3 — canonical)

- **AC1**: Bắt đầu MoMo payment KHÔNG tạo Sales Order.
- **AC2**: Trước khi success có thẩm quyền, KHÔNG tồn tại Sales Order cho attempt.
- **AC3**: Notify (IPN) hợp lệ (signature + amount + identity khớp) tạo đúng MỘT order.
- **AC4**: Signature sai, amount lệch hoặc provider failure KHÔNG tạo order.
- **AC5**: Callback success trùng lặp KHÔNG tạo order/invoice thứ hai.
- **AC6**: Quote/contract bị thay đổi sau khi initiate không thể âm thầm tạo order với contract khác.
- **AC7**: Browser Return không thể bypass verification để tạo order.
- **AC8**: Return trên attempt FINALIZED rebuild được 5 success-session keys từ order đã bind.
- **AC9**: Verified money-real + finalizer fail được biểu diễn bền vững, retry/reconciliation-safe,
  KHÔNG bị hạ xuống FAILED.
- **AC10**: Refund integration hiện tại vẫn compile + unit coverage không regress.

## 4. Technical Notes

### 4.1 Kiến trúc đích (mirror ZaloPay, thu gọn)

```
ACTIVE QUOTE
→ JS set-payment-information (KHÔNG placeOrder)
→ POST momo/payment/redirect (quote-first start)
→ PaymentAttemptManagement::initiate(): validate → collectTotals → amount VND snapshot
  → quote-row lock → double-payment guard → mint order_ref/request_id
  → persist INITIATED attempt (contract_hash + expires_at)
  → get_pay_url (MoMo /v2/gateway/api/create, HTTP ngoài DB TX) → markActive(payUrl)
→ redirect browser tới payUrl
→ KHÔNG CÓ SALES ORDER

MoMo IPN (Notify) hoặc Return (server-side v2/query):
→ resolve attempt theo order_ref (merchant reference MoMo orderId)
→ verify signature + merchant identity + amount + transaction identity
→ PaymentAttemptLifecycle (locked, short TX, fresh-row CAS)
→ OrderFinalizer::finalizeOrRecover() — MỘT flattened DB TX:
  lock attempt row (FOR UPDATE) → refuse quarantined
  → FINALIZED ⇒ recover bound order (idempotent)
  → PAID ⇒ assertQuoteMatchesContract → grant → placeOrder → markFinalized
  → captureOrder (native invoice CAPTURE_ONLINE; `capture` command = NullCommand — không HTTP trong TX)
  → commit → email post-commit với dispatch claim (grace 900s)
→ order đi lifecycle Magento chuẩn
```

### 4.2 Components mới/sửa (MUTATION_SCOPE = app/code/Secomm/MoMo + schema/tests MoMo-owned)

**Schema** (`etc/db_schema.xml` mới + whitelist): bảng `secomm_momo_payment_attempt` — mirror
`secomm_zalopay_payment_attempt`, bỏ cột recovery_* (recovery cron ngoài scope; TTL lazy-expiry):
entity_id PK; quote_id (FK quote CASCADE); reserved_order_id; order_ref (unique);
request_id (unique); provider_transaction_id; pay_url; payment_status; provider_status;
amount int unsigned (VND); currency; contract_hash; order_id (unique, FK sales_order SET NULL);
last_error; requires_reconciliation; reconciliation_code; retry_count; store_id; email_dispatch;
created_at/updated_at/expires_at.

**Api + Model**: `Api/Data/PaymentAttemptInterface`, `Model/PaymentAttempt` (state machine
initiated→active→paid→finalized; terminal failed/stale/expired; mark*() throw khi transition bất hợp
pháp), `Model/PaymentAttemptFactory`, `Api/PaymentAttemptRepositoryInterface` +
`Model/PaymentAttemptRepository` + `Model/ResourceModel/PaymentAttemptResource` + collection.
Repository API: save/getById/getByOrderRef/lockByOrderRef (SELECT … FOR UPDATE, không tự mở TX)/
getActiveByQuoteId/getBlockingAttemptByQuoteId/getListByQuoteId/claimEmailDispatch/releaseEmailDispatch.

**Initiation**: `Model/PaymentAttemptManagement` (isInitiable: active + items + method `momo_payment`
+ currency VND; initiate: collectTotals → amount int VND → createOrReuseAttempt (quote-row lock,
double-payment guard, reuse ACTIVE khi amount+fingerprint khớp, STALE attempt cũ, mint
order_ref/request_id) → get_pay_url → markActive) + `Model/OrderRefBuilder`
(`MOMO{ymdHis}{reservedOrderId}{4hex}`; requestId = order_ref + `-R{4hex}`) +
`Model/QuoteContractFingerprint` (sha256: quote_id, reserved_order_id, store, currency, amount VND,
items sorted (sku|product_id|qty, children), shipping, payment method, coupon, rule ids, hashed addresses).

**Guard + authorization**: `Service/OrderPlacementAuthorization` (per-request singleton, grant triple
(quote_id, attempt_id, order_ref), single-use) + `Plugin/Quote/CartManagementPlaceOrderGuard`
(trước `QuoteManagement::placeOrder`: block mọi momo quote không có grant được backed bởi attempt row
persisted — chặn REST/GraphQL/SOAP/OSC/stale-JS).

**Lifecycle + finalizer + processors**: `Service/PaymentAttemptLifecycle` (canonical state mutation:
locked short TX, fresh-row decisions, RECON_* quarantine, không bao giờ hạ money-real),
`Service/OrderFinalizer` (MỘT flattened TX; email post-commit + claim grace 900s; the ONLY quote→order
boundary), `Service/IpnProcessor` (Notify delegate: outcome mapping, strict identity checks),
`Service/SuccessSessionPreparer` (Return-only writer của 5 keys, không clearHelperData — mirror ZaloPay),
`Service/ReturnProcessor` (rewrite: attempt-based; ALWAYS server-side v2/query verify; quyết định state
thuộc về lifecycle; finalizer + session preparer).

**Gateway**: `Gateway/Command/InitializeCommand` (order-based; đặt pending_payment + suppress
confirmation email lúc placeOrder — finalizer gửi email post-commit); `Gateway/Request/CreateOrderBuilder`
(rewrite: attempt-based — orderId = order_ref, requestId = attempt request_id, extraData =
base64(order_ref), amount int VND từ attempt snapshot); `Gateway/Validator/NotifyValidator` (rewrite:
attempt-based — signature (13 signed fields giữ nguyên) + partnerCode + amount==attempt.amount +
orderId==attempt.order_ref + requestId==attempt.request_id + extraData↔order_ref); mới:
`Gateway/Request/QueryDataBuilder` (partnerCode, orderId, requestId + signature
accessKey&orderId&partnerCode&requestId) + `Gateway/Validator/QueryValidator` (signature response
accessKey&amount&message&orderId&partnerCode&responseTime + echo match attempt). Xoá:
`Gateway/Command/NotifyCommand` + `Gateway/Response/TransactionHandler` (invoice/capture chuyển vào
OrderFinalizer); pool `notify` removed, thêm `query_transaction`.

**Controllers** (giữ nguyên route/class name — URL admin return/notify cấu hình sẵn không đổi):
- `Controller/Payment/Redirect` — rewrite: quote-first start (mirror ZaloPay Start: isInitiable →
  initiate → redirect payUrl; fail-safe → PaymentFailuresInterface + cart).
- `Controller/Payment/Notify` — rewrite: thin → IpnProcessor; mapping outcome → HTTP/JSON:
  SUCCESS/ACK_RECON → 200 resultCode 0; INVALID → 200 resultCode 1; unknown order_ref → 404 resultCode 1;
  RETRYABLE (finalizer/local transient throw) → 500 resultCode 1 (MoMo retry = recovery driver cho AC9).
  POST + GET giữ (compat), CsrfAware như cũ.
- `Controller/Payment/ReturnAction` — giữ shape (thin, delegates ReturnProcessor).

**Frontend**: `view/frontend/web/js/view/payment/method-renderer/momo-method.js` — rewrite theo
`zalopay-wallet.js` (PayPal Express pattern): `placeOrder()` override → `continueToMoMo()`:
validate → selectPaymentMethod → set-payment-information (Magento_Paypal set-payment-method action) →
redirect `momo/payment/redirect`. KHÔNG placeOrder. Template `momo.html` giữ nguyên (button vẫn bind
`placeOrder` — giờ đi path payment-first). Layout `checkout_index_index.xml` + `onestepcheckout_index_index.xml`
giữ nguyên.

**DI/config**: `etc/di.xml` — thêm guard plugin lên `Magento\Quote\Model\QuoteManagement`;
wire OrderFinalizer/IpnProcessor/ReturnProcessor/SuccessSessionPreparer/PaymentAttemptManagement
(command pool `MoMoCommandPool`), OrderPlacementAuthorization (non-shared);
pool: initialize → MoMoInitializeCommand, get_pay_url (giữ), query_transaction (mới),
capture/cancel_order = NullCommand, refund (giữ), **bỏ `notify`**;
`etc/config.xml` + `attempt_ttl` (15 phút, lazy expiry).

### 4.3 Delta có chủ ý so với ZaloPay (ghi nhận)

1. **order_ref/request_id thay app_trans_id**: MoMo v2 dùng `orderId` + `requestId` — merchant reference
   mint tại initiate, persist trên attempt; Notify/Return resolve theo order_ref.
2. **VND-only, không Rate helper**: MoMo chỉ settle VND; initiate yêu cầu quote currency VND
   (`isInitiable` fail-safe khi không phải VND). amount = `(int)round(quote.grand_total)`.
3. **Return luôn chạy v2/query server-side** (MoMo có query API — `Config::PATH_QUERY` có sẵn) — như
   ZaloPay ReturnProcessor; IPN vẫn là success path có thẩm quyền (issue SCOPE 5) — Return chỉ được
   hoàn tất finalization khi query trả về bằng chứng có thẩm quyền; non-zero query resultCode = chưa
   paid → KHÔNG mutation (attempt chờ TTL/IPN).
4. **Không recovery cron** (NON_SCOPE — follow-up): money-real + finalizer fail → attempt giữ PAID/
   quarantine + Notify trả 500 → MoMo tự retry IPN = recovery driver (AC9). TTL chỉ lazy-expiry.
5. **Refund backward-compat (AC10)**: finalizer set `momo_order_ref` (+ `momo_trans_id`) lên payment
   additionalInformation; `RefundBuilder` đọc `momo_order_ref` (fallback increment id cho legacy orders
   đã tồn tại dưới flow cũ).

### 4.4 Ràng buộc

- MUTATION_SCOPE: chỉ `app/code/Secomm/MoMo` (+ declarative schema/tests MoMo-owned).
- Không push/merge — branch task local; TL review gate ở cuối.
- PHP 8.2+ `strict_types`, composition controllers, không ObjectManager trực tiếp, không secrets/log PII.
- MoMo query response signature fields: accessKey&amount&message&orderId&partnerCode&responseTime;
  create/IPN signed fields giữ nguyên theo code hiện tại (13 fields IPN; create request 10 fields).

## 5. Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| Issue #2 (MOMO-H01 — MoMo v2 gateway) | task | CLOSED | Flow v2 create/notify/refund + Signature helper có sẵn |
| Issue #6 (repo hygiene) | task | CLOSED | BASE_SHA 87f4db44 đã verify tại start gate |
| ZaloPay payment-first policy (FEAT-ZLP1PF) | reference | accepted | Mirror policy, không dùng code cross-module |

## 6. Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| MoMo query response signature field list lệch docs | M | M | QueryValidator testable; sandbox smoke khi có creds; field list đặt const, dễ sửa |
| OSC (Mageplaza) nút Place Order gọi placeOrder JS renderer | M | H | momo-method.js override `placeOrder()` (chính là chỗ OSC click) — mọi entry JS đi continueToMoMo; guard server-side chặn phần còn lại |
| Legacy orders (đã paid dưới flow cũ) refund | M | M | RefundBuilder fallback increment id khi thiếu `momo_order_ref` |
| Duplicate schema whitelist drift | L | L | Whitelist khớp db_schema; validate bằng setup:upgrade |

## 7. Out of Scope

- Recovery cron lost-callback (follow-up riêng của issue).
- Refund hardening ngoài refund identity compat (AC10).
- Shared payment abstraction VNPAY/ZaloPay/MoMo; refactor rộng module.
- Production deployment; tự merge/push branch.

## 8. Test Notes

- Unit: attempt management (initiate/reuse/stale/blocking), lifecycle (CAS/quarantine), finalizer
  (exactly-once/recovery/contract-mismatch), IpnProcessor (success/fail/dup/identity), NotifyValidator,
  QueryValidator, ReturnProcessor (query-verify, không browser-trust), guard plugin,
  OrderPlacementAuthorization, fingerprint, OrderRefBuilder, InitializeCommand.
- php -l + PHPCS (Magento2) changed scope; setup:upgrade (schema mới) + setup:di:compile.
- Sandbox smoke khi có MoMo sandbox credentials (tùy chọn).
