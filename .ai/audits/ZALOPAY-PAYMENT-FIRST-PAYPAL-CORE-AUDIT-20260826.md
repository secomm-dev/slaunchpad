# ZaloPay Payment-First Audit — đối chiếu Magento core PayPal Express (từ source)

- **Ngày:** 2026-08-26
- **Branch:** `dev/development/thanhle` @ `cd3689b0` (worktree sạch)
- **Phạm vi:** `app/code/Secomm/ZaloPay` (toàn module) + `vendor/magento/module-paypal` (Express Checkout), `vendor/magento/module-quote`, `vendor/magento/module-inventory-sales`, `vendor/magento/module-catalog-inventory` — **đọc source trực tiếp, không suy từ documentation**.
- **Mode:** AUDIT ONLY — không thay đổi production code. Không architecture change trong audit này.
- **Công cụ bắt buộc:** CodeGraph (đã dùng: 3 lần `codegraph_explore` cho cluster ZaloPay) + magento-spec MCP (`business/order-lifecycle.md`, `security/payment-gateway.md` — §17 review rules cho redirect payment).
- **Quyết định đã có từ trước:** architecture mới là **payment-first, tham khảo Magento core PayPal**. Audit này trả lời: core PayPal làm payment-first chính xác thế nào (điểm tạo order nằm ở đâu), và `Secomm_ZaloPay` phải đổi gì.

---

## 1. Kết luận điều hành (Executive conclusion)

| Câu hỏi | Trả lời (chứng minh bằng source ở §2–§4) |
|---|---|
| Core PayPal (2.4.8-p5) có phải payment-first? | **CÓ.** Provider transaction (token) được tạo trên **quote đang active** (`reserveOrderId()`, chưa có `sales_order`); order chỉ được tạo ở `QuoteManagement::submit()` **sau khi** khách đã approve ở PayPal. |
| Điểm tạo order chính xác | `vendor/magento/module-paypal/Model/Express/Checkout.php:790` `place()` → `$this->quoteManagement->submit($this->_quote)` (được gọi từ controller `PlaceOrder::execute()` sau approval). |
| Provider transaction tồn tại trước order? | **CÓ.** `Checkout::start()` (`Checkout.php:482`) → `callSetExpressCheckout()` với `setInvNum($quote->getReservedOrderId())` — token gắn với **reserved increment id của quote**, không cần order. |
| `Secomm_ZaloPay` hiện tại | **ORDER-FIRST toàn phần.** Order + state `pending_payment` + MSI reservation được tạo ở bước "Place Order" của checkout; redirect sang ZaloPay chỉ là bước lấy pay URL từ order đã tồn tại (`Start.php:73-85`). |
| Migration impact | **ARCHITECTURAL** trên 3 controller (`Start`/`ReturnAction`/`Ipn`) và chuỗi command return/IPN; **LOW–MEDIUM** cho phần lớn phần còn lại (refund subsystem hầu như giữ nguyên); bắt buộc **ADD** cơ chế reconciliation (order-missing / callback race) mà **cả core PayPal cũng không giải quyết đầy đủ** (xem §4). |
| Verdict | **PAYMENT-FIRST FEASIBLE, ĐÃ CÓ BLUEPRINT TỪ CORE** — với 5 rủi ro mở ở §12 phải được TL quyết trước khi implement. |

---

## 2. TASK 1A — PayPal trước approval: quote, provider transaction, session

Trích `vendor/magento/module-paypal/Model/Express/Checkout.php`:

### 2.1 `start()` — Checkout.php:482 trở đi

```php
$this->_quote->collectTotals();
if (!$this->_quote->getGrandTotal()) { throw new LocalizedException(...); }
$this->_quote->reserveOrderId();
$this->quoteRepository->save($this->_quote);
// ... build API: amount = baseGrandTotal (rounded), currency,
//     setInvNum($this->_quote->getReservedOrderId()),
//     RETURN_URL / CANCEL_URL
$this->_getApi()->callSetExpressCheckout();
$token = $this->_getApi()->getToken();
// ... quote payment additional_information flags
return $token;
```

| Trạng thái tại thời điểm khách rời site | Giá trị | Nguồn |
|---|---|---|
| `sales_flat_quote.is_active` | **1 (quote vẫn active)** — không deactivate, không ẩn giỏ | `start()` không đụng `is_active` |
| `sales_order` | **KHÔNG tồn tại** | không có lệnh submit nào trong `start()` |
| Provider transaction | **token SetExpressCheckout** tạo bằng API call thật; gắn với `reserved_order_id` của quote qua `InvNum` | `callSetExpressCheckout()`, `setInvNum(...)` |
| `reserved_order_id` | Được set + **save vào quote** — đây chính là "increment id tương lai" mà provider sẽ tham chiếu | `reserveOrderId(); quoteRepository->save()` |
| Session | `checkoutSession` giữ `quote_id` (quote active); paypal session giữ `paypal_transaction_data` khi cần retry | `ReturnAction`/`PlaceOrder` đọc lại |
| Email khách | Chưa gửi (order chưa có) | — |

### 2.2 Frontend payment-first (chứng minh từ JS core, không phải doc)

`vendor/magento/module-paypal/view/frontend/web/js/view/payment/method-renderer/paypal-express-abstract.js:79-83`:

```js
setPaymentMethodAction(this.messageContainer).done(
    function () {
        $.mage.redirect(
            window.checkoutConfig.payment.paypalExpress.redirectUrl[quote.paymentMethod().method]
        );
```

→ Checkout JS **không gọi `placeOrder`**. Nó chỉ save payment-information (set payment method) rồi redirect tới `/paypal/express/start`. `vendor/magento/module-paypal/view/frontend/web/js/action/set-payment-method.js` gọi `setPaymentInformation(...)` — endpoint payment-information **không có place-order**. Đây là điều kiện bắt buộc cho payment-first ở frontend.

### 2.3 Đối chiếu `Secomm_ZaloPay` hiện tại tại cùng thời điểm

`app/code/Secomm/ZaloPay/Controller/Payment/Start.php:73-85`: `checkoutSession->getLastOrderId()` → `orderRepository->get()` → `commandPool->get('get_pay_url')` với `'amount' => $order->getTotalDue()`. Tức là **bước này chỉ chạy được khi order đã tồn tại** (do `zalopay-wallet.js` đã `placeOrder` trước đó — `redirectAfterPlaceOrder: true`). Chuỗi order-first hoàn chỉnh:

```
zalopay-wallet.js placeOrder
→ POST payment-information (savePaymentInformationAndPlaceOrder)
→ CartManagement::placeOrder → QuoteManagement::placeOrderRun (module-quote:420)
→ submit() → orderManagement->place($order)
→ Order\Payment::place() (module-sales:352) → isInitializeNeeded() (can_initialize=1)
→ Adapter::initialize() → command 'initialize' → InitializeCommand
   (app/code/.../Gateway/Command/InitializeCommand.php:39-41:
    state/status = PENDING_PAYMENT, is_notified=false, canSendNewEmailFlag=false)
→ order save, quote is_active=0, session LastOrderId set
→ JS redirect /zalopay/payment/start → đọc LastOrderId → get_pay_url → redirect ZaloPay
```

Hệ quả (đối chiếu từng điểm với bảng 2.1): quote **bị deactivate**, `sales_order` **đã tồn tại** ở state `pending_payment`, MSI reservation **đã trừ** (xem §5), trước khi khách hề thấy trang ZaloPay.

---

## 3. TASK 1B — Return flow: validation → quote restore → placeOrder → order

### 3.1 Chuỗi core (tất cả từ source)

1. **Redirect callback** — `vendor/magento/module-paypal/Controller/Express/AbstractExpress/ReturnAction.php`: nếu `retry_authorization == 'true'` + session có `PaypalTransactionData` → `_forward('placeOrder')`; ngược lại `returnFromPaypal($this->_initToken())`; nếu `canSkipOrderReviewStep()` → `_forward('placeOrder')`, không thì redirect `*/*/review`. Exception → `checkout/cart`.
2. **`returnFromPaypal()`** — `Checkout.php:611`: `callGetExpressCheckoutDetails()` (server-to-server xác thực token + lấy payer info), import shipping/billing vào quote, `ignoreAddressValidation()`. **Vẫn chưa có order** — chỉ cập nhật quote.
3. **`place($token)`** — `Checkout.php:790`: `updateShippingMethod` → `prepareGuestQuote` → `collectTotals` → **`$order = $this->quoteManagement->submit($this->_quote);`** ← **ORDER ĐƯỢC TẠI ĐÂY, sau approval** → xử lý redirect URL, gửi email, set session, chuyển state.
4. **Bên trong `submit()`** — `vendor/magento/module-quote/Model/QuoteManagement.php:511` và `submitQuote()` :561:
   - `submitQuoteValidator->validateQuote($quote)` (:564) = `QuoteValidator::validateBeforeSubmit` (`module-quote/Model/QuoteValidator.php:91`): throw nếu `$quote->getHasError()` hoặc `quoteValidationRule` fail (items count, address bắt buộc, email) — **không check tồn kho** (xem §5.4);
   - `reserveOrderId()` (:579 — reserve lần nữa, idempotent);
   - build order từ quote addresses/items/payment; `$order->setIncrementId($quote->getReservedOrderId())` (:617) — **order increment id = reserved_order_id đã dùng ở `start()`** ⇒ provider ref khớp order;
   - `orderManagement->place($order)` (:633) — đây là nơi MSI reservation được append (§5.3);
   - **thành công:** `$quote->setIsActive(false)` (:636) + `quoteRepository->save()`;
   - **thất bại:** `rollbackAddresses()` + **rethrow** (:640-644) — **quote vẫn active**, không có order.
5. **Controller sau submit** — `PlaceOrder.php:100-148`: set `LastQuoteId/LastOrderId/LastRealOrderId/LastOrderStatus`, dispatch `checkout_submit_all_after` + `paypal_express_place_order_success`, redirect success.

### 3.2 Session/quote restore khi thất bại (TASK 3 lien quan)

`PlaceOrder.php:158` `processException()` = message + redirect `*/*/review` — vì `submit()` throw nên **chưa có order, quote vẫn active** ⇒ trang review cho khách retry ngay. Điểm mấu chốt của payment-first: **failure path của order-creation không phải cancel order (order chưa tồn tại), mà là giữ quote sống để retry**.

---

## 4. TASK 1C — Provider payment success NHƯNG Magento order creation fail

### 4.1 Core PayPal giải quyết được gì (từ source)

| Tình huống | Cơ chế core | Nguồn |
|---|---|---|
| API PayPal từ chối khi đặt order (sau approval) | Ma trận lỗi processable: `API_MAX_PAYMENT_ATTEMPTS_EXCEEDED`/`API_TRANSACTION_EXPIRED` → redirect sang express start URL **restart**; `API_DO_EXPRESS_CHECKOUT_FAIL` → `_redirectSameToken()` **dùng lại token cũ** (không gọi SetExpressCheckout lại); `API_ADDRESS_MATCH_FAIL`/`API_TRANSACTION_HAS_BEEN_COMPLETED` → review page + error; `API_UNABLE_TRANSACTION_COMPLETE` (payment_action=order) → express order URL theo `paypalTransactionData` | `PlaceOrder.php:170` `_processPaypalApiError` |
| Retry authorization | Param `retry_authorization=true` + session `PaypalTransactionData` → forward `placeOrder` lại | `ReturnAction.php` |
| Order creation fail (quote invalid, exception) | `submitQuote` rethrow ⇒ quote active, chưa có order, **chưa có tiền capture** (vì capture/authorize của Express chạy TRONG `orderManagement->place()` — cùng transaction DB với việc tạo order) | `QuoteManagement.php:633-644` |
| Thanh toán thành công nhưng IPN đến khi… | IPN load order theo `invoice` (increment id) — `Model/Ipn.php:151 _getOrder()`; **không tìm thấy order → throw → HTTP 500** (`Controller/Ipn/Index.php` catch-all 500) ⇒ PayPal tự retry theo policy của provider; `RemoteServiceUnavailableException` → 503 | `Ipn.php`, `Ipn/Index.php` |

### 4.2 Core KHÔNG giải quyết (phải ghi rõ, không được giả định core đã cover)

1. **Provider đã capture tiền + order không thể tạo lại (fail vĩnh viễn):** Express Checkout của core thiết kế để **capture diễn ra đồng thời với việc tạo order trong cùng DB transaction** (`Order\Payment::place()` chạy trong `OrderManagement::place()`), nên trạng thái "đã thu tiền + không có order" gần như không xảy ra trong mô hình core. **NHƯNG** với ZaloPay thì ngược lại: **tiền bị trừ ở provider TRƯỚC** (khách nhập PIN trên app ZaloPay), còn order Magento tạo SAU. Core PayPal **không có pattern nào** cho "tiền đã thu, order chưa tạo, cần tạo lại order + đối soát" — module `paypal` không có reconciliation cron cho orphan transaction; IPN fail → 500 → provider retry hết hạn thì thôi, còn lại cho merchant xử lý thủ công.
2. **IPN/return đến khi order chưa kịp tạo (race):** không thể xảy ra với core (order luôn tạo xong trước khi capture), nhưng **chắc chắn xảy ra với ZaloPay payment-first** (IPN POST có thể đến trước khi ReturnAction xong `placeOrder`). Core không có code xử lý case này.
3. **Order-missing recovery tự động:** không có cron/query-status/fallback nào trong `module-paypal` cho giao dịch đã capture mà không tìm thấy order.

⇒ **Kết luận TASK 1C:** core cho ta (a) token-trên-quote + reserved increment id làm provider ref, (b) submit-sau-approval với quote-active-retry, (c) ma trận lỗi restart/same-token. Core **không** cho ta: đối soát khi provider success + order fail/race — phần này **bắt buộc thiết kế riêng cho ZaloPay** (xem §10.4 Reconciliation + §11), và là lý do bảng phân loại TASK 6 có nhóm **ADD**.

---

## 5. TASK 2 — Inventory lifecycle (MSI): validate/reserve ở đâu, kịch bản T0–T4

### 5.1 Salability validation (trace code, không lý thuyết)

- Event `sales_quote_item_qty_set_after` → `Magento\CatalogInventory\Model\Quote\Item\QuantityValidator` (`vendor/magento/module-catalog-inventory/etc/events.xml:15`) — chạy **mỗi khi qty của quote item được set** (add-to-cart, update qty).
- MSI override: `vendor/magento/module-inventory-sales/etc/di.xml:24-26` — plugin `CheckQuoteItemQtyPlugin` trên `StockStateInterface::checkQuoteItemQty` → `IsProductSalableForRequestedQty` → preference `IsProductSalableForRequestedQtyConditionChainOnAddToCart` (di.xml:89-120) với 5 condition: `IsCorrectQtyCondition`, `IsAnySourceItemInStockCondition`, `BackOrderCondition`, `ManageStockCondition`, `IsSalableWithReservationsCondition`.

### 5.2 Reservation được tạo ở ĐÂU (trả lời đúng câu hỏi "có reserve trước Order không?")

**Chỉ ở thời điểm đặt order — không sớm hơn.** `vendor/magento/module-inventory-sales/etc/di.xml:156`: plugin `AppendReservationsAfterOrderPlacementPlugin` trên `OrderManagementInterface::place` — tức là **bên trong `QuoteManagement::submitQuote()` → `orderManagement->place($order)`** (QuoteManagement.php:633), sau approval trong mô hình payment-first. Chi tiết từ `vendor/magento/module-inventory-sales/Plugin/Sales/OrderManagement/AppendReservationsAfterOrderPlacementPlugin.php`:

- `aroundPlace()` (:110) — khi `reservationExecution->isDeferred()`: build `itemsToSell` (qty âm theo SKU, bỏ qua product type không quản source), reserve, rồi mới `$proceed($order)` (persist order);
- `createOrder()` (:160) — **nếu `$proceed` throw: compensation** — append reservation ngược dấu với `SalesEventInterface::EVENT_ORDER_PLACE_FAILED` (:170-180). Đảm bảo không leak reservation khi order placement fail.

Trích dẫn phụ: `module-inventory-sales/etc/events.xml` disable observer `checkout_submit_all_after` của catalog-inventory với comment *"in multi source inventory only reservations are created after order placement"* — xác nhận cùng kết luận.

### 5.3 T0–T4: stock cạn trong lúc khách ở provider, rồi payment success

| Thời điểm | Trạng thái | Core PayPal (source-proven) | ZaloPay hiện tại (order-first) |
|---|---|---|---|
| **T0** add to cart | quote item qty set | salable chain chạy (§5.1) fail → item error | như core |
| **T1** redirect sang provider | — | **KHÔNG có order, KHÔNG reservation** — stock vẫn tự do bán cho người khác | order + `pending_payment` + **reservation `-qty` đã giữ stock** |
| **T2** người khác mua hết unit cuối trong lúc khách ở ZaloPay | salable qty về 0 | quote của khách vẫn active, **không tự re-validate** | stock đã bị khách đầu giữ reservation nên case này gần như không xảy ra (đánh đổi bằng leak ở T5') |
| **T3** khách thanh toán thành công ở provider | tiền đã trừ | — | — |
| **T4** quay về, tạo order | `submit()` | `validateBeforeSubmit` (§3.1 mục 4) **không check tồn kho**; reservation append **không check available qty** (chỉ ghi reservation âm) ⇒ salable qty có thể **âm** (oversell race) — core chấp nhận rủi ro này, phát hiện muộn ở source-deduction lúc shipment | order đã có từ T1 nên không có T4 |

**Kết luận TASK 2 (đúng nhiệm vụ "trace code, không lý thuyết"):**
1. **Không được nói "inventory reserve trước Order"** — source chứng minh reservation gắn với `OrderManagementInterface::place`, tức **tại thời điểm tạo order**, có compensation khi place fail.
2. Payment-first dịch reservation từ T1 về T4 — **giống hệt core PayPal**. Đổi rủi ro: từ "abandoned payment giữ stock vĩnh viễn" (hiện tại, vì không có cron hủy `pending_payment` — xem §7.4) sang "hiếm gặp: oversell race ở T4" mà core cũng có.
3. **Core không re-validate salability ở submit** — nếu dự án muốn chặn oversell race ở T4 thì phải chủ động gọi `IsProductSalableForRequestedQty` ngay trước `placeOrder` trong Return flow (đề xuất ở §10.5 — đây là **cải tiến có chủ đích vượt core**, phải được TL duyệt như một deviation).

---

## 6. TASK 3 — Quote lifecycle: 10 case — core có bảo vệ gì, ở đâu

| # | Case | Bảo vệ của core PayPal (vị trí code) | ZaloPay hiện tại | Gap cho payment-first ZaloPay |
|---|---|---|---|---|
| 1 | Quote expired (qua `quote_lifetime`) | `QuoteRepository::getActive` trong placeOrder chain chỉ lấy quote active; quote hết hạn → behaves như quote mới/giỏ trống → customer checkout lại | order-first không phụ thuộc quote active sau T1 ⇒ không sai — nhưng cart trống khi quay về thất bại | Sau return phải load lại quote active; hết hạn → flow thất bại sạch (redirect cart + message) như core |
| 2 | Quote bị deactivate (checkout khác hoàn tất) | Như case 1 — `placeOrder` không tìm thấy active quote → fail sạch, không tạo order trùng | RestoreQuoteObserver chỉ `restoreQuote()` cho `pending_payment` (Observer/RestoreQuoteObserver.php:45) | Đảm bảo return flow fail sạch, không đặt order từ quote đã is_active=0 |
| 3 | Cart đổi ở tab khác trong lúc ở provider | Quote là 1 theo session; `place()` `collectTotals()` lại trước submit ⇒ total mới; **amount mismatch với token cũ** → PayPal báo lỗi khi DoExpressCheckout → vào ma trận lỗi §4.1 | Order-first: giỏ đã đóng băng từ T1 — đổi tab không phản ánh | Trước `placeOrder` phải **so amount provider (app_trans_id query) với quote grand total** — sai → hủy + trả về cart (core dựa vào provider bắt mismatch) |
| 4 | Địa chỉ đổi (tại provider hoặc tab khác) | `returnFromPaypal()` import địa chỉ từ provider vào quote, `ignoreAddressValidation()` (Checkout.php:611) | Order đã lưu địa chỉ từ T1 | Trước submit giữ nguyên validation địa chỉ chuẩn của quote (không cần mimic ignore — ZaloPay không thu địa chỉ ở provider) |
| 5 | Thiếu shipping method khi quay về | Review step bắt chọn; `place()` `updateShippingMethod` + validate trước submit | OSC bắt buộc từ T1 | Validate shipping method trước submit; thiếu → fail sạch về checkout |
| 6 | Promo/total đổi giữa start và return | `collectTotals()` ở `place()`; mismatch amount → provider reject (case 3) | N/A (order-first đóng băng) | Như case 3 — amount check bắt buộc ở return |
| 7 | Product option/qty item trở nên invalid | Validator set `hasError` trên quote item (§5.1) → `validateBeforeSubmit` throw `ValidatorException` (QuoteValidator.php:93-96) → `processException` → review + message, quote active, retry được | Order-first không gặp (đã submit) — nhưng nếu xảy ra trước T1 thì checkout fail bình thường | Truyền thống core giữ nguyên: quote-error → fail sạch + message; KHÔNG đặt order khi quote có error |
| 8 | Callback đến nhiều lần (IPN retry, return reload) | Core: IPN đăng ký transaction; captured-payment path idempotent theo transaction — và capture chạy đồng thời order creation nên không có "capture 2 lần cho 2 order" | `UpdateOrderCommand` gate bằng `state === STATE_PENDING_PAYMENT` (UpdateOrderCommand.php:53) — IPN thứ 2 no-op (log warning) — **idempotent theo state** | Giữ state-gate; **thêm lock** (transaction DB / payment transaction unique) vì payment-first tạo 2 entry point return + IPN + reconciliation cùng lúc |
| 9 | Nhiều tab / nhiều attempt cho 1 quote | Mỗi attempt 1 token mới (SetExpressCheckout lại); attempt fail → quote active → retry; PayPal giới hạn attempt phía provider (ma trận lỗi) | Mỗi retry tạo **order mới** (`pending_payment` chồng chất) — rác order | Payment-first: mỗi attempt 1 `app_trans_id` mới trên cùng quote + reserved_order_id giữ nguyên — không sinh rác order |
| 10 | Session mất (cookie, đổi thiết bị) khi quay về | `_initToken()` đọc token từ **request param** — return vẫn xử lý được phần xác thực provider; phần quote thì theo session, mất session → fail sạch về cart | `Start`/`ReturnAction` đều phụ thuộc `checkoutSession->getLastOrderId()` — mất session là **gãy hoàn toàn** | Payment-first: return/IPN dò **order theo increment id parsed từ `app_trans_id`** (không phụ thuộc session) — tốt hơn hiện tại; trường hợp order chưa tạo + session mất → đường dành cho reconciliation cron (§10.4) |

Điểm tổng hợp TASK 3: hai bảo vệ quan trọng nhất để vay của core là **(a) token/provider-ref gắn với `reserved_order_id` của quote — không phụ thuộc session** (case 10) và **(b) submit-sau-approval với quote giữ active để retry** (case 2/7/9).

---

## 7. TASK 4 — Kiến trúc `Secomm_ZaloPay` hiện tại (audit toàn module)

### 7.1 Cấu trúc thực (đã trace qua CodeGraph + đọc trực tiếp)

```
Frontend:
  js/view/payment/method-renderer/zalopay-wallet.js   (placeOrder → redirectAfterPlaceOrder)
  js/action/redirect-on-success.js                    (window.location.replace('/zalopay/payment/start'))
  layout: checkout_index_index.xml + onestepcheckout_index_index.xml (OSC)

Controllers (frontName zalopay):
  Controller/Payment/Start.php        — getLastOrderId → order → 'get_pay_url' → redirect pay URL
  Controller/Payment/ReturnAction.php — getLastOrderId → order → nếu PENDING_PAYMENT → 'complete' → redirect success
  Controller/Payment/Ipn.php          — parse POST, app_trans_id[2]=incrementId → order → nếu zalopay+PENDING_PAYMENT → 'ipn'

Gateway (di.xml ZaloPayCommandPool):
  initialize → InitializeCommand (state PENDING_PAYMENT tại Order\Payment::place)
  get_pay_url → GetPayUrlCommand (Builder: ZaloAppInfo + ItemDetails + OrderAdditionalInformation;
               plugin PayUrlGenerateMac sort 10; client Zend; GetPayUrlValidator)
  complete   → CompleteCommand = CompleteUpdateDetailsCommand(ReturnValidator + TransactionReturnHandler)
               + UpdateOrderCommand(isIpn=false → không capture)
  ipn        → IpnCommand = IpnUpdateDetailsCommand(CompleteValidator + TransactionCompleteHandler)
               + UpdateOrderCommand(isIpn=true → payment->capture() khi authorize_capture)
  capture    → NullCommand ; cancel_order → NullCommand
  refund     → RefundCommand (+ RefundQueryCommand) + zalo_pay_refund table + 2 crons

Model/Plugin/Observer:
  ZaloPayConfigProvider (redirectUrl + logo)
  RestoreQuoteObserver (event restore_quote_after_payment_failed)
  SuccessValidatorPlugin (grace 15 phút cho success page)
  TotalMinMaxPlugin (min/max theo VND qua Rate)
  CreditmemoPlugin (STATE_PROCESSING) + CreditmemoServicePlugin (no-op wrapper)
```

### 7.2 Chuỗi tiếng Việt chuẩn hiện tại (đối chiếu phần 2/3)

order được tạo tại checkout place-order (T1) → redirect `Start` lấy pay URL từ order → khách trả tiền ở ZaloPay → **IPN (authoritative)**: `CompleteValidator` (MAC key2 + amount VND + `zp_trans_id`) → `TransactionCompleteHandler` (set transaction id) → `UpdateOrderCommand` `payment->capture()` → invoice; **Return (non-authoritative)**: `ReturnValidator` (status==1 + apptransid, amount best-effort, không MAC — comment trong file nói rõ) → handler chỉ lưu `apptransid`, KHÔNG capture. Đây là thiết kế hợp lý **trong phạm vi order-first**; payment-first sẽ thay "IPN là nguồn xác nhận duy nhất" bằng **server query chủ động** (§10.3).

### 7.3 `app_trans_id` — mấu chốt cho payment-first

`ZaloAppInfoDataBuilder::getAppTransId()` = `ymd_{timestamp_ms}_{orderIncrementId}`; `TransactionReader::readOrderId()` tách `[2]` = increment id. Trong payment-first, segment này sẽ là **`quote->getReservedOrderId()`** — chính xác pattern `InvNum` của PayPal (§2.1). Đổi 1 điểm này là Return/IPN vẫn tìm được "đơn tương lai" mà không cần order tồn tại trước.

### 7.4 Các phát hiện trong module (không phải BUG mới — ghi nhận cho refactor)

1. **Dead observer:** `restore_quote_after_payment_failed` được đăng ký (`etc/frontend/events.xml`) nhưng **không có bất kỳ dispatcher nào** trong `app/code` lẫn `vendor` (grep toàn bộ — chỉ thấy events.xml). Observer không bao giờ chạy. Quote restore sau thất bại thực tế đang không có cơ chế nào của module đảm nhiệm.
2. **Không có cron dọn `pending_payment`:** order-first sinh order `pending_payment` cho mọi khách bỏ thanh toán; không cron nào hủy + hoàn reservation. Với MSI, reservation `-qty` bị giữ vô thời hạn (leak tồn kho) — trùng cảnh báo magento-spec `security/payment-gateway.md` §17.5.
3. **`Start` không có xác thực trạng thái:** không check `state === pending_payment` trước khi gọi `get_pay_url` — gọi lại `/zalopay/payment/start` (refresh) sẽ tạo giao dịch ZaloPay mới cho order đã capture (mức độ: thấp, vì session thường đã đổi).
4. **`Ipn` trả 404 khi không tìm thấy order** (Ipn.php:103-108) — đúng cho order-first, nhưng với payment-first sẽ thành race bình thường ⇒ cần đối xử khác (queue/202) thay vì 404.
5. **`CreditmemoServicePlugin`** là wrapper không đổi hành vi (comment trong file tự nhận) — ứng viên REMOVE khi dọn.
6. **`SuccessValidatorPlugin` dùng Reflection** đọc protected `checkoutSession` — hoạt động nhưng fragile; tồn tại chỉ vì capture phụ thuộc IPN async. Capture chủ động (§10.3) sẽ làm plugin này không còn cần thiết.
7. **Refund subsystem** (RefundCommand/RefundQueryCommand/RefundCronjob/RefundCleanupCronjob/zalo_pay_refund/CreditmemoPlugin/RefundProcessor) gắn hoàn toàn với creditmemo của order đã có — **không chịu ảnh hưởng bởi payment-first**, giữ nguyên.
8. **Test tích hợp** có sẵn cho Start/ReturnAction/Ipn/cron (`Test/Integration/*`) — sẽ phải viết lại theo flow mới.

---

## 8. TASK 5 — Gap analysis table (Current ZaloPay vs Core PayPal vs Required Change vs Risk)

| # | Khu vực | ZaloPay hiện tại | Core PayPal (source) | Required Change | Risk nếu không đổi |
|---|---|---|---|---|---|
| 1 | Thời điểm tạo order | Tại checkout place-order (trước provider) | `QuoteManagement::submit` sau approval (Checkout.php:790) | **REWRITE Start/Return flow** — order chỉ tạo sau khi provider confirm | Rác order + lock stock + phụ thuộc session |
| 2 | Frontend action | `placeOrder` rồi redirect | `setPaymentMethodAction` + redirect (paypal-express-abstract.js:79) | Đổi renderer sang set-payment-information + redirect | Không thể payment-first (order bị tạo ngay) |
| 3 | Provider ref trước order | `app_trans_id` chứa order increment id (order phải tồn tại) | `InvNum = reserved_order_id` trên quote (Checkout.php:482 start) | `app_trans_id` segment [2] = `quote->getReservedOrderId()` | Không liên kết được provider ↔ order tương lai |
| 4 | `reserveOrderId` timing | Có (ngầm trong submit) nhưng sau đó mới build app_trans_id | `start()`: reserve + **save quote** trước khi gọi provider | Reserve + save quote ở Start flow mới | IPN/return không tìm thấy increment id |
| 5 | Quote state khi khách ở provider | `is_active=0` (đã submit) | `is_active=1`, giỏ còn nguyên | Bỏ mọi submit khỏi bước redirect | Khách mất giỏ khi fail; không retry được |
| 6 | MSI reservation timing | T1 (tạo order) — giữ stock từ trước thanh toán | T4 (`OrderManagement::place` plugin, có compensation fail) | Để core tự xử bằng cách không tạo order sớm | Leak tồn kho cho giao dịch bỏ dở (hiện hữu) |
| 7 | Xác nhận thanh toán | IPN là nguồn duy nhất (return non-authoritative) | Server-to-server tại `place()` (DoExpressCheckout đồng thời tạo order) | **ADD server query `v2/query` theo app_trans_id tại return** rồi mới capture | Trượt trạng thái: khách trả tiền nhưng order chờ IPN (race success page hiện phải hack bằng grace 15') |
| 8 | Return handler | Session LastOrderId → order PENDING_PAYMENT → 'complete' | `returnFromPaypal` → `placeOrder` (tạo order) | Return = **place order + verify + capture** | Không có order để gắn transaction |
| 9 | IPN handler | Bắt order tồn tại, nếu không → 404 | `_getOrder()` throw → 500 → provider retry | IPN order-missing → **queue 202** + reconciliation (§10.4) | Tiền đã thu, không còn đường lấy lại self-serve |
| 10 | Order-creation failure sau provider success | N/A (order có trước) | Quote active + retry review + ma trận lỗi restart/same-token (PlaceOrder.php:170) | Fail sạch: giữ quote, message, cho retry; không order | Khách trả tiền mà không có đơn — khiếu nại |
| 11 | Idempotency callback | State-gate `PENDING_PAYMENT` | Transaction-register + capture-in-placeOrder | Giữ state-gate + **ADD lock/unique transaction** | Double capture khi return + IPN + cron đè nhau |
| 12 | Session dependency | `getLastOrderId()` ở Start + Return (gãy khi mất session) | Token trong URL param; quote qua session, fail sạch | Dò order theo increment id từ app_trans_id (IPN) + session quote cho return | Khách đổi trình duyệt/lỗi cookie → mất đơn đã trả |
| 13 | Quote restore sau fail | **Dead event** — không có gì chạy (7.4.1) | Quote chưa từng deactivate ⇒ không cần restore ở flow này | Bỏ dead observer; fail path tự nhiên giữ giỏ | UX tệ: giỏ trống sau thất bại |
| 14 | Cron dọn pending | Không có | Không cần (không có pending order trước approval) | **ADD** cron hủy `pending_payment` zalopay cũ (check invoice/transaction trước khi hủy — magento-spec §17.7) | Tồn kho + báo cáo nhiễu (hiện hữu) |
| 15 | Success page race | SuccessValidatorPlugin grace 15' (Reflection hack) | Không cần — capture xong mới vào success | REMOVE sau khi capture chủ động | Complexity giữ lại vô ích |
| 16 | Amount validation | CompleteValidator so amount VND (IPN) | Provider reject amount mismatch tại capture | **ADD** so `v2/query` amount ↔ quote total VND trước placeOrder | Đặt đơn sai số tiền khi giỏ đổi (TASK 3 case 3/6) |
| 17 | Stock re-validation tại T4 | N/A | **Không có** (QuoteValidator không check stock — §5.3) | Optional deviation: gọi `IsProductSalableForRequestedQty` trước place (TL quyết) | Oversell race (cũng có ở core) |
| 18 | Initialize command | `can_initialize=1` + PENDING_PAYMENT tại placement | `can_initialize=0` cho express (không initialize trước) | config.xml: `can_initialize=0`, bỏ InitializeCommand khỏi flow placement | Còn order-first ở tầng facade |
| 19 | Config state machine | `order_status=pending_payment`, `payment_action=authorize_capture` | Express: state theo payment action tại place | Giữ pending_payment cho cửa sổ tạo-order; rà lại status mapping | State không nhất quán sau migrate |
| 20 | Cancellation/void khi khách hủy ở provider | Không có đường hủy chủ động (cancel_order = NullCommand) | CancelAction → hủy quote-side flow (chưa có order ⇒ không cần hủy gì) | Payment-first: hủy provider không chạm Magento; chỉ cần redirect fail sạch | Hủy nhầm order đã thanh toán |
| 21 | Logging/troubleshooting | Logger riêng (info/error khá đầy đủ ở Ipn) | Debug data IPN | Thêm log milestone: reserve, create, query, place, capture, reconcile | Khó đối soát khi tranh chấp |
| 22 | Refund | Đầy đủ (command + query + cron + bảng riêng) | N/A scope | **KEEP** — không đổi | N/A |

---

## 9. TASK 6 — Phân loại refactor theo file (KEEP / MODIFY / REWRITE / REMOVE / ADD)

| File / class | Hành động | Impact | Ghi chú |
|---|---|---|---|
| `Controller/Payment/Start.php` | **REWRITE** | ARCHITECTURAL | Load active quote (như `AbstractExpress::_getQuote()`), grand-total check, `reserveOrderId` + save, build app_trans_id từ reserved id, gọi `get_pay_url`, redirect. Không order. |
| `Controller/Payment/ReturnAction.php` | **REWRITE** | ARCHITECTURAL | Parse apptransid → tìm order (đã có do IPN race?) hoặc place order từ quote active → verify `v2/query` → capture → success. Fail sạch về cart kèm message. |
| `Controller/Payment/Ipn.php` | **REWRITE** | HIGH | Order-missing → lưu callback pending (bảng queue) + HTTP 202; order có → giữ state-gate capture idempotent như nay. |
| `Gateway/Command/InitializeCommand.php` | **REMOVE** | LOW | `can_initialize=0`; không còn initialize tại placement. |
| `Gateway/Command/GetPayUrlCommand.php` | **KEEP** | LOW | Cơ chế command/validator/transfer giữ nguyên. |
| `Gateway/Request/ZaloAppInfoDataBuilder.php` | **MODIFY** | MEDIUM | `getAppTransId()` nhận reserved id (quote) thay increment id (order); builder phải làm việc với quote payment (PaymentDataObject của quote). |
| `Gateway/Request/OrderAdditionalInformationDataBuilder.php` | **REWRITE** | MEDIUM | Amount/desc/embed từ **quote** (grand total, reserved id); redirect/callback URL giữ nguyên; bỏ dependency `$order->getIncrementId()`. |
| `Gateway/Request/ItemDetailsDataBuilder.php` | **MODIFY** | MEDIUM | Đọc quote items thay order items. |
| `Gateway/Command/CompleteCommand.php` + `IpnCommand.php` + 3 biến thể UpdateDetails | **REWRITE** (gộp) | HIGH | Thay bằng `VerifyAndCapture` (server query + capture idempotent + lock) và thống nhất return/IPN vào một đường xác nhận. |
| `Gateway/Command/UpdateOrderCommand.php` | **MODIFY** | MEDIUM | Giữ state-gate; thêm guard order-missing + lock; tách trách nhiệm capture rõ hơn. |
| `Gateway/Validator/CompleteValidator.php` | **KEEP** | LOW | MAC key2 + amount VND + zp_trans_id vẫn là xác thực callback chuẩn. |
| `Gateway/Validator/ReturnValidator.php` | **MODIFY** | MEDIUM | Suy giảm còn sanity check; authority chuyển cho server query. |
| `Gateway/Response/TransactionCompleteHandler.php` | **KEEP** | LOW | Đăng ký transaction id như nay. |
| `Gateway/Response/TransactionReturnHandler.php` | **REMOVE** | LOW | Return không còn tự persist gì (capture thuộc VerifyAndCapture). |
| `Observer/RestoreQuoteObserver.php` | **REMOVE** (cùng dead event) | LOW | Flow mới không deactivate quote nên không cần restore; xóa cả dòng events.xml chết. |
| `Plugin/.../SuccessValidatorPlugin.php` | **REMOVE** | LOW | Sau khi capture chủ động tại return — không còn race success page. |
| `Plugin/Checks/TotalMinMaxPlugin.php` | **KEEP** | LOW | Vẫn đúng với quote. |
| `Plugin/Order/CreditmemoPlugin.php`, `Plugin/Service/CreditmemoServicePlugin.php` | KEEP / **REMOVE** (CreditmemoServicePlugin no-op) | LOW | Dọn wrapper rỗng. |
| `Cron/RefundCronjob.php`, `Cron/RefundCleanupCronjob.php`, refund models + `db_schema` refund table | **KEEP** | LOW | Trọn bộ refund không đổi. |
| `Model/ZaloPayConfigProvider.php` | **MODIFY** | LOW | redirectUrl giữ; có thể thêm config query endpoint. |
| `view/frontend/web/js/view/payment/method-renderer/zalopay-wallet.js` | **MODIFY** | MEDIUM | Đổi sang pattern `set-payment-method` + redirect của PayPal (paypal-express-abstract.js). |
| `view/frontend/web/js/action/redirect-on-success.js` | **MODIFY** | LOW | Redirect URL giữ nguyên `/zalopay/payment/start`. |
| `etc/config.xml` | **MODIFY** | MEDIUM | `can_initialize=0`; rà `order_status`, giữ `authorize_capture`. |
| `etc/di.xml` | **MODIFY** | MEDIUM | Command pool mới, DI controller, bỏ wiring cho class REMOVE. |
| `etc/frontend/events.xml` | **MODIFY** | LOW | Bỏ dead observer. |
| `Test/Integration/*` (Start/ReturnAction/Ipn/crons) | **REWRITE** | HIGH | Theo flow mới + case race/idempotency. |
| **ADD** `Service/QueryTransaction` (wrapper `v2/query` + verify amount) | **ADD** | MEDIUM | "DoExpressCheckout tương đương" — xác nhận server-to-server. |
| **ADD** `Model/PendingCallback` + bảng `zalo_pay_pending_callback` + `Cron/ReconcileCronjob` | **ADD** | ARCHITECTURAL | Xử lý provider-success/order-missing + race IPN-trước-order (§4.2) — phần core không có. |
| **ADD** `Cron/CancelStalePendingOrder` (chỉ khi giữ cửa sổ pending) + query quote theo reserved id | **ADD** | MEDIUM | Dọn `pending_payment` an toàn (không hủy đơn có invoice/transaction). |
| **ADD** (optional, TL quyết) T4 stock pre-check `IsProductSalableForRequestedQty` trước placeOrder | **ADD** | MEDIUM | Deviation có chủ đích so với core (§5.3). |

---

## 10. TASK 7 — Target architecture payment-first (bám core PayPal, không copy mù)

### 10.1 Nguyên tắc

Lấy core PayPal làm khung xương — từng bước đều map về một vị trí source ở §2–§3 — và chỉ **tự thiết kế** ở những chỗ chứng minh được core không cover (§4.2), đúng ràng buộc *"không tự design lifecycle khi core có pattern dùng lại được"*.

### 10.2 Flow mục tiêu

```
[Checkout] zalopay-wallet.js → setPaymentInformation (KHÔNG placeOrder)
        → redirect /zalopay/payment/start                      ← paypal-express-abstract.js:79
[Start]  load ACTIVE quote (checkout session, _getQuote-style)
        quote->collectTotals(); guard grandTotal>0             ← Checkout::start() Checkout.php:482
        quote->reserveOrderId(); quoteRepository->save(quote)
        build app_trans_id = ymd_ms_{reserved_order_id}        ← InvNum pattern
        'get_pay_url' (quote-based builders) → redirect payUrl
        [quote active; KHÔNG order; KHÔNG reservation]
[ZaloPay] khách thanh toán (provider capture tiền tại đây)
[ReturnAction] parse apptransid → incrementId
        nếu order(incrementId) tồn tại → goto capture
        ngược lại: load quote theo reserved_order_id + session guard
        collectTotals; validate (error/address/shipping/amount)
        quoteManagement->submit($quote)  ← ORDER TẠI ĐÂY (Checkout.php:790 pattern)
        submit fail → fail sạch về cart (quote còn active, retry được)  ← §3.2
        capture: Service/QueryTransaction 'v2/query' theo app_trans_id
        return_code=1 + amount khớp (VND) → payment->capture() (invoice)
        → checkout/onepage/success
[Ipn]   MAC key2 + CompleteValidator (giữ nguyên)
        order tồn tại → state-gate capture (idempotent như nay)
        order CHƯA có → insert zalo_pay_pending_callback → HTTP 202
        (ZaloPay sẽ retry; đồng thời ReconcileCron xử lý)
[ReconcileCron] (mới)
        với mỗi pending callback: 'v2/query'
        order chưa có + quote còn dùng được → place + capture (lock + idempotent)
        quote không dùng được (hết hạn/đổi) → cảnh báo ops + hướng dẫn refund qua provider
        (không bao giờ "revive" order đã hủy — magento-spec §17.6/17.7)
```

### 10.3 Điểm khác PayPal và lý do (không phải copy mù)

| Khác biệt | Lý do nguồn |
|---|---|
| Capture xác nhận bằng **server query `v2/query`** thay vì "API call trong place()" | PayPal lấy confirmation ngay trong DoExpressCheckoutPayment (đồng thời tạo order). ZaloPay thu tiền **trước** khi ta biết — nên bước tương đương là query chủ động theo `app_trans_id` ngay tại return. IPN vẫn giữ làm fallback idempotent. Đây là thay thế đúng tinh thần, không phải cơ chế mới lạ. |
| **Queue + cron reconciliation** | §4.2: core không có gì cho provider-success/order-missing; bắt buộc tự có. |
| Payment action giữ `authorize_capture` (offline register) | Giữ hành vi invoice hiện tại của `UpdateOrderCommand` — không đổi tầng sales. |

### 10.4 Thành phần mới bắt buộc

1. `Service/QueryTransaction` — server-truth theo app_trans_id (+amount VND).
2. `zalo_pay_pending_callback` (declarative schema, whitelist) — hàng đợi callback khi order chưa có; unique theo app_trans_id; TTL.
3. `Cron/ReconcileCronjob` — xử lý queue như §10.2; log đầy đủ mọi nhánh (đặt/cảnh báo/refund-hướng-dẫn).
4. `Cron/CancelStalePendingOrder` — nếu giữ cửa sổ `pending_payment` hẹp: hủy theo TTL, **chỉ khi không có invoice/transaction**.

### 10.5 Deviation đề xuất (cần TL duyệt — vượt core)

- T4 stock pre-check trước `submit` (§5.3 mục 3) — chống oversell race mà core chấp nhận.
- Amount guard tại return (so `v2/query` amount ↔ quote total VND) — thay cho việc dựa provider bắt mismatch như PayPal.

---

## 11. TASK 8 — Failure-state matrix (8 trạng thái, ai phụ trách hồi phục)

| # | Trạng thái | Phát hiện bởi | Hành động | Recovery owner |
|---|---|---|---|---|
| 1 | Provider SUCCESS + order đã tạo (happy) | ReturnAction / Ipn | Capture + invoice + success page (idempotent state-gate) | Flow tự động |
| 2 | Provider SUCCESS + order creation FAIL tại return (quote invalid/stock/error) | ReturnAction catch | Fail sạch về cart, message, **quote active** để retry; KHÔNG capture ở Magento; callback ghi queue | Flow tự động + khách retry; nếu khách không retry → ReconcileCron → ops refund qua provider |
| 3 | Provider SUCCESS + IPN đến TRƯỚC khi return đặt xong order (race) | Ipn | Order chưa có → queue + 202; ReconcileCron đặt order từ quote (lock + idempotent); return đến sau thấy order tồn tại → capture 1 lần | ReconcileCron (tự động) |
| 4 | Provider FAIL / khách hủy ở trang ZaloPay | Redirect return (status≠1) | Redirect cart + message; quote còn active (không có gì để hủy bên Magento) | Flow tự động + khách |
| 5 | Khách bỏ trang, không return, không IPN (timeout provider ~15') | Không ai gọi về | Payment-first: **không có order, không reservation — không cần dọn gì**. Chỉ log/kiểm kê nếu cần | Provider TTL (không việc gì với Magento) |
| 6 | Callback trùng/lồng nhau (IPN retry + return + cron đồng thời) | State-gate + lock | Capture đúng 1 lần (state `pending_payment` → processing); transaction id unique; các lần sau no-op log | Flow tự động |
| 7 | Callback sai MAC / sai amount | CompleteValidator / amount guard | Reject + log + không đổi state; nếu tiền đã thu mà amount không khớp → queue để ops đối soát + refund qua provider | Ops admin (cảnh báo của ReconcileCron) |
| 8 | Đơn `pending_payment` già (TTL) — có/không có tiền | CancelStalePendingOrder | Kiểm tra invoice/transaction + `v2/query` TRƯỚC khi hủy: chưa thu → hủy qua Magento flow (giải phóng reservation qua cancellation chuẩn); đã thu → KHÔNG hủy, đẩy sang capture/đối soát (magento-spec §17.6–17.7) | Cron + Ops admin |

Ghi chú: mọi chuyển state đều qua service Magento (`OrderManagement`/payment command), không `UPDATE` thẳng — magento-spec `security/payment-gateway.md` §17.4.

---

## 12. Rủi ro mở (phải có quyết định TL trước khi implement)

1. **Cửa sổ race return ↔ IPN ↔ cron** — thiết kế lock/idempotency phải được test tải song song (đây là phần KHÔNG có sẵn ở core, rủi ro cao nhất của migration).
2. **Fallback khi mất checkout session nhưng tiền đã thu** (TASK 3 case 10) — ReconcileCron có đủ thông tin đặt đơn hộ khách không (email/địa chỉ nằm trong quote còn active? quote hết hạn thì sao?) → cần quyết định chính sách: auto-place trong TTL, sau TTL thì refund qua provider.
3. **Sai khác amount VND** giữa quote total lúc create và lúc query (tỷ giá đổi trong cửa sổ vài phút) — cần rule: lock amount vào lúc Start, so theo amount đã lock thay vì re-convert.
4. **Tương tác với Mageplaza OSC** — flow `set-payment-information + redirect` phải được verify trên OSC (task CLAUDE.md: checkout thuộc nhóm cần TL review).
5. **Bảo toàn dữ liệu lịch sử** — các order `pending_payment` cũ của order-first sau khi deploy sẽ không bao giờ có IPN match flow mới → chạy batch dọn một lần trước go-live.

---

## 13. Implementation phases (đề xuất — KHÔNG implement trong audit này)

| Phase | Nội dung | Cổng quality |
|---|---|---|
| 0 | TL review audit + chốt 5 rủi ro mở §12 + duyệt deviation §10.5 | Quyết định ghi vào `.ai/records/decisions` |
| 1 | Nền tảng quote-side: builders (§9 MODIFY/REWRITE nhóm request), `Service/QueryTransaction`, config `can_initialize=0` | Unit + integration test builders theo quote |
| 2 | REWRITE `Start` + frontend `set-payment-information + redirect` (OSC incl.) | QA runtime sandbox: chưa thấy order nào tạo ra trước redirect |
| 3 | REWRITE `ReturnAction` (place + verify + capture + fail-sach) + xóa dead observer/SuccessValidatorPlugin | Matrix TASK 8 #1/#2/#4 |
| 4 | REWRITE `Ipn` + queue + `ReconcileCron` + `CancelStalePendingOrder` | Matrix TASK 8 #3/#5/#6/#7/#8 + concurrency test |
| 5 | Cleanup (REMOVE list §9), rewrite Test/Integration, batch dọn pending cũ | Full regression L3 (payment — mức validation cao nhất theo `.ai/AGENTS.md` §"Change-aware validation") |

---

## Phụ lục: nguồn chính đã đọc (tất cả là source trên đĩa tại cd3689b0)

- `vendor/magento/module-paypal/Model/Express/Checkout.php` (start:482, returnFromPaypal:611, place:790)
- `vendor/magento/module-paypal/Controller/Express/AbstractExpress/{ReturnAction,PlaceOrder,AbstractExpress}.php` (processException:158, _processPaypalApiError:170, _getQuote:246)
- `vendor/magento/module-paypal/Controller/Ipn/Index.php`, `Model/Ipn.php` (_getOrder:151), `Model/AbstractIpn.php`
- `vendor/magento/module-paypal/view/frontend/web/js/view/payment/method-renderer/paypal-express-abstract.js` (:79-83), `js/action/set-payment-method.js`
- `vendor/magento/module-quote/Model/QuoteManagement.php` (placeOrderRun:420, submit:511, submitQuote:561-644), `Model/QuoteValidator.php` (:91), `Model/SubmitQuoteValidator.php`
- `vendor/magento/module-sales/Model/Order/Payment.php` (place:352, initialize:377)
- `vendor/magento/module-inventory-sales/etc/di.xml` (:24-26, :89-120, :156), `etc/events.xml`, `Plugin/Sales/OrderManagement/AppendReservationsAfterOrderPlacementPlugin.php`
- `vendor/magento/module-catalog-inventory/etc/events.xml`, `Model/Quote/Item/QuantityValidator.php`
- Toàn bộ `app/code/Secomm/ZaloPay` nêu trong §7/§9 (qua CodeGraph explore ×3 + đọc trực tiếp controllers/commands/validators/handlers/builders/di/config/events/crontab/db_schema/frontend JS)
- magento-spec: `business/order-lifecycle.md` (§6 MSI + §8 lookup-by-increment-id), `security/payment-gateway.md` (§10 flags, §12 areas, **§17 review rules redirect-payment**)
