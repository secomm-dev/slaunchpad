# Feature Spec: FEAT-ZKD4VA — VietQR Payment

- **ID**: `FEAT-ZKD4VA` (External Ref: `SLP-90 / LC-11`)
- **Priority**: P1 (High)
- **Workflow Mode**: A (Tier-2: payment + checkout generic risk)

---

## 1. Feature Overview

**Feature name**: VietQR Payment — offline bank transfer với QR động theo order

**Ticket reference**: TASK-N35E28

**Feature type**: New

**Priority**: P1 (High)

**Module**: `Secomm_VietQr`

---

## 2. User Stories

- **US-001**: As a customer, I want to select VietQR as a payment method at checkout so that I can pay via bank transfer with a QR code containing the correct amount and order reference.
- **US-002**: As a customer, I want to be redirected to a dedicated VietQR payment page after placing my order so that I can see the QR code, bank details, and confirm when I have completed the transfer.
- **US-003**: As a customer, I want to access the VietQR payment page again from My Orders so that I can view the QR code or confirm payment later.
- **US-006**: As a customer, I want a Cancel button on the VietQR payment page so that I can leave without confirming payment.
- **US-007**: As a customer, I want a Submit button on the VietQR payment page so that I can confirm I have completed the bank transfer.
- **US-004**: As a merchant admin, I want to enable/disable VietQR and configure bank information from Admin so that I can update payment details without code changes.
- **US-005**: As a merchant admin, I want to receive an order confirmation email with payment instructions so that I can confirm payment was received against the correct order.

---

## 3. Acceptance Criteria

- [ ] **AC-001** (Admin enable/disable): Admin có thể enable/disable VietQR tại `Stores → Configuration → Sales → Payment Methods → VietQR`. Khi disabled, method không hiển thị ở checkout. (BR-003)
- [ ] **AC-002** (Admin config — bank): Admin có thể config Bank Code, Bank Account, Account Name. Các field lưu đúng trong `system.xml` / `config.xml`. (BR-003)
- [ ] **AC-003** (Admin config — API): Admin có thể config API Endpoint, Request Timeout. Architecture cho phép bổ sung API key/token sau này. (BR-003)
- [ ] **AC-004** (Admin config — payment): Admin có thể config Transfer Content Template (vd `DH{{order_increment_id}}`), Payment Instructions, Pending Order Status. (BR-003)
- [ ] **AC-005** (Checkout display): VietQR hiển thị ở Mageplaza OSC checkout với payment title + mô tả/hướng dẫn ngắn. Không generate QR trước khi order được tạo. (BR-004)
- [ ] **AC-006** (Single order creation): Customer Place Order chỉ tạo **một order**. Không duplicate order khi Place Order. (BR-004)
- [ ] **AC-007** (Order status): Order VietQR được tạo ở trạng thái `vietqr_pending` (custom, label "Chờ thanh toán" — merchant lọc được đơn VietQR trong Admin grid; customer hiểu đơn chưa thanh toán từ My Orders). Không authorize, không capture, không auto-invoice. (BR-003)
- [ ] **AC-008** (QR generation): Sau khi order tạo thành công, gọi VietQR API (`POST /api/vietqr/generate`) với `bankAccount`, `userBankName`, `bankCode` từ Admin config; `amount` từ `order.grand_total`; `content` từ template + `order.increment_id`. QR payload (`qrCode` field) lưu vào `payment.additional_information.vietqr_qr_code`. (US-001, US-002)
- [ ] **AC-009** (Payment snapshot): Lưu tối thiểu `vietqr_qr_code`, `vietqr_bank_code`, `vietqr_bank_name`, `vietqr_bank_account`, `vietqr_account_name`, `vietqr_amount`, `vietqr_content`, `vietqr_generated_at` vào payment additional_information. Không lưu unused fields. (US-002)
- [ ] **AC-010** (Idempotency): Nếu payment đã có `vietqr_qr_code`, không gọi VietQR API lại. Mở lại custom VietQR page không tạo request mới. (US-002, US-003)
- [ ] **AC-011** (Custom payment page — redirect): Sau khi Place Order thành công, customer được redirect đến trang custom VietQR của module (`/vietqr/payment/view/order_id/{id}`). Không sử dụng success page mặc định. Trang hiển thị QR code (render từ `qrCode` payload), Order number, Bank name, Bank account, Account holder, Amount, Transfer content, Payment instructions. (US-002)
- [ ] **AC-012** (Custom payment page — Cancel): Trang custom VietQR có nút **Cancel**. Bấm Cancel → hủy thao tác thanh toán, chuyển customer đến thank-you page (`checkout/onepage/success` với `skip_vietqr=1` — plugin không redirect lại), KHÔNG submit xác nhận thanh toán. Order giữ `vietqr_pending`. (US-006)
- [ ] **AC-013** (Custom payment page — Submit): Trang custom VietQR có form **Submit** (có thể gồm trường Transaction Reference, Customer Notes — tùy scope). Bấm Submit → (1) lưu `vietqr_customer_confirmed = true`, `vietqr_customer_confirmed_at`, và các trường form (nếu có) vào payment additional_information; (2) chuyển order sang trạng thái **`Awaiting Payment Confirm`** (custom status, map state `new`). KHÔNG tự động mark paid, KHÔNG invoice, KHÔNG chuyển sang processing/complete. (US-007)
- [ ] **AC-014** (No duplicate submit): Không cho phép customer submit lặp. Nếu payment đã có `vietqr_customer_confirmed = true` hoặc order đã được merchant xử lý → custom page ẩn form Submit, chỉ hiển thị QR + info + thông báo "đã xác nhận". (US-007)
- [ ] **AC-015** (Custom order statuses): Module khai báo 2 order status riêng qua data patch `Setup/Patch/Data/InstallVietQrStatuses.php` (insert `sales_order_status` + `sales_order_status_state`): `vietqr_pending` (label "Chờ thanh toán" — initial) và `vietqr_awaiting_payment_confirm` (label "Chờ xác nhận thanh toán"). Cả 2 map state `new` + `visible_on_front=1` — merchant lọc đơn VietQR trong Admin grid, customer thấy "Chờ thanh toán" trong My Orders. Confirm là status-only change (không setState); không modify status dùng chung `pending`/`pending_payment` (Mollie/PayPal in-flight). Trạng thái hiển thị đúng trong Admin Order grid và Order View. (BR-003)
- [ ] **AC-016** (My Orders — VietQR button): Customer Dashboard → My Orders và Order View page hiển thị button/action "Thanh toán VietQR" cho các order sử dụng payment method VietQR và ở trạng thái `vietqr_pending` (configured New Order Status). Button **không** hiển thị với order không dùng VietQR, order đã bị hủy, hoặc order đã ở trạng thái `Awaiting Payment Confirm`/đã được merchant xử lý. (US-003)
- [ ] **AC-017** (My Orders — reopen page): Bấm button "Thanh toán VietQR" từ Order list → mở lại trang custom VietQR của order đó. QR và payment instructions hiển thị lại từ payment snapshot (idempotent, không gọi API lại). (US-003)
- [ ] **AC-018** (Email): Order confirmation email có payment instructions bao gồm Bank name, Account number, Account name, Amount, Transfer content, Payment instructions + QR image từ `img.vietqr.io` (external service; text vẫn đầy đủ khi image không tải được). (US-005)
- [ ] **AC-019** (Error handling): VietQR API timeout/4xx/5xx → không rollback order, không tạo duplicate order, log lỗi (không log credentials), custom page vẫn hiển thị manual bank transfer instructions (bank account, account name, amount, transfer content). Không expose exception kỹ thuật ra frontend. (US-002)
- [ ] **AC-020** (Security): Không gọi VietQR API trực tiếp từ browser. Flow: Frontend → Magento Backend → VietQR API. Không expose API key/token. Custom page controller phải verify order ownership (customer_id match hoặc guest hash). Escape/sanitize dữ liệu render. Validate response. HTTP timeout. Không log credentials. (Security baseline)
- [ ] **AC-021** (i18n — BR-001): Chuỗi storefront mới có entry `vi_VN.csv` + `en_US.csv`; cả 2 locale render đúng. (BR-001)
- [ ] **AC-022** (Hyvä/Alpine): Frontend dùng Alpine.js + Tailwind v4 (CSS-first `@theme`/`@source`), không Knockout/RequireJS. QR render client-side từ payload (QR code library inline hoặc SVG). (Hyvä conventions)
- [ ] **AC-023** (Order ownership): Custom VietQR page (`/vietqr/payment/view/order_id/{id}`) chỉ cho phép owner của order truy cập. Nếu customer khác hoặc chưa login truy cập → redirect về trang phù hợp. Guest order hỗ trợ qua param `key` (Magento standard). (Security)

---

## 4. Technical Notes

### 4.1 Module Structure

```
Secomm/VietQr
├── Api/
│   └── QrGeneratorInterface.php
├── Controller/
│   └── Payment/
│       ├── View.php                   # GET /vietqr/payment/view/order_id/{id}
│       └── Submit.php                 # POST /vietqr/payment/submit — customer confirm
├── Model/
│   ├── Payment.php                  # Magento payment method model
│   ├── Config.php                   # Admin config reader
│   ├── RateLimiter.php              # IP rate limiting view/submit (10 req/min)
│   ├── InstructionsConfigProvider.php # checkout instructions config provider
│   └── VietQr/
│       ├── ApiClient.php            # HTTP client, auth readiness
│       ├── QrGenerator.php           # implements QrGeneratorInterface
│       ├── RequestBuilder.php        # order → API request mapping
│       └── QrResult.php              # response/value object
├── Observer/
│   └── GenerateQrAfterOrder.php      # checkout_submit_all_after — QR generation
├── Plugin/
│   └── SuccessRedirectPlugin.php     # around Onepage\Success::execute — redirect sang VietQR page
├── Block/
│   ├── PaymentInfo.php               # ViewModel cho QR + bank info
│   └── Order/
│       └── VietQrButton.php          # ViewModel: hiển thị/ẩn button ở My Orders
├── Logger/
│   ├── Handler.php                  # Custom Log Handler (var/log/secomm_vietqr.log)
│   └── Logger.php                   # Custom Monolog Logger instance
├── Setup/
│   └── Patch/Data/
│       └── InstallVietQrStatuses.php  # custom order statuses: vietqr_pending + vietqr_awaiting_payment_confirm
├── etc/
│   ├── module.xml                   # module declaration with sequence Secomm_Base
│   ├── config.xml                   # default config values (active=0, debug=0)
│   ├── payment.xml                  # payment method declaration
│   ├── di.xml                       # DI configuration & custom logger bindings
│   ├── events.xml                   # checkout_submit_all_after
│   ├── acl.xml                      # ACL resource declarations
│   ├── frontend/
│   │   └── routes.xml               # route secomm_vietqr
│   └── adminhtml/
│       ├── system.xml               # Admin config fields (Debug mode, Min/Max totals, Allowed countries)
│       └── menu.xml                 # Admin menu link under Secomm root menu
├── view/frontend/
│   ├── layout/
│   │   ├── secomm_vietqr_payment_view.xml     # custom payment page layout
│   │   ├── checkout_index_index.xml           # OSC payment method (optional)
│   │   ├── sales_order_history.xml            # My Orders — VietQR button
│   │   └── sales_email_order_items.xml        # email payment instructions block
│   ├── templates/
│   │   ├── payment/info/default.phtml         # checkout payment description
│   │   ├── payment/view.phtml                 # custom VietQR page (QR + info + buttons)
│   │   ├── order/vietqr-button.phtml          # My Orders action button
│   │   └── email/payment-info.phtml           # email payment instructions
│   ├── web/
│   │   └── js/vietqr-qr-renderer.js          # client-side QR render (Alpine)
├── i18n/
│   ├── vi_VN.csv
│   └── en_US.csv
└── registration.php
```

### 4.2 Payment Method

- Class `Secomm\VietQr\Model\Payment` extends `\Magento\Payment\Model\Method\AbstractMethod`.
- `$_code = 'secomm_vietqr'`.
- `$_isGateway = false`, `$_canAuthorize = false`, `$_canCapture = false`, `$_canCapturePartial = false`, `$_canRefund = false`, `$_canVoid = false`.
- `$_isOffline = true` — không cần online authorization.
- `getConfigPaymentAction()` trả về `null` (không action).
- Order status mới: `getConfigData('order_status')` mặc định `vietqr_pending`.

### 4.3 Post-Order Flow (QR Generation + Redirect)

1. Observer `checkout_submit_all_after` — sau khi Magento tạo order.
2. Kiểm tra `payment.getMethod() === 'secomm_vietqr'`.
3. Kiểm tra idempotency: `$payment->getAdditionalInformation('vietqr_qr_code')`.
4. Nếu chưa có → gọi `QrGeneratorInterface::generate($order)`.
5. `QrGenerator` → `RequestBuilder::build($order)` → `ApiClient::generate($request)` → `QrResult`.
6. Lưu `QrResult` vào `$payment->setAdditionalInformation(...)` + `$payment->save()`.
7. Nếu API error → log + KHÔNG throw (order đã tạo, không rollback).
8. Plugin `aroundExecute` trên `Magento\Checkout\Controller\Onepage\Success::execute` (`Plugin/SuccessRedirectPlugin.php`, đăng ký trong `etc/frontend/di.xml`) — redirect customer đến `/vietqr/payment/view/order_id/{id}` (+ `key` cho guest) thay vì success page mặc định. Skip redirect khi có param `skip_vietqr=1` (Cancel) hoặc payment đã có `vietqr_customer_confirmed`. (Observer `checkout_onepage_controller_success_action` bị loại — event không carry `response`, observer là silent no-op.)

### 4.4 Custom VietQR Payment Page

**Route**: `GET /vietqr/payment/view/order_id/{id}` (`Controller/payment/View.php`).

- Verify order ownership: `order.getCustomerId() == current customer_id` (logged-in) hoặc match guest `protected_key` param.
- Nếu không phải VietQR order → redirect về order list.
- Load QR + bank info từ payment `additional_information` (snapshot, không đọc current config).
- Nếu `vietqr_qr_code` chưa có (API failed lúc đầu) → attempt generate lại một lần.
- Render template `payment/view.phtml` với QR + info + hai nút.

**Nút Cancel**:
- Link đến thank-you page (`checkout/onepage/success` kèm `skip_vietqr=1` — `SuccessRedirectPlugin` skip redirect).
- KHÔNG thay đổi order state, KHÔNG lưu gì.

**Rate limiting**: `Model/RateLimiter.php` — 10 req/phút/IP trên view + submit endpoints (cache-based), chống brute-force order_id/guest key.

**Nút Submit** (`POST /vietqr/payment/submit` → `Controller/Payment/Submit.php`):

- Verify order ownership + CSRF.
- **Guard duplicate submit**: nếu `vietqr_customer_confirmed` đã true hoặc order status không còn là configured New Order Status (`vietqr_pending`) → redirect về custom page (hiện thông báo đã xác nhận).
- Lưu vào payment additional_information:
  - `vietqr_customer_confirmed = true`
  - `vietqr_customer_confirmed_at = now()`
  - `vietqr_transaction_ref` (nếu form có trường Transaction Reference)
  - `vietqr_customer_notes` (nếu form có trường Customer Notes)
- Chuyển order status sang **`vietqr_awaiting_payment_confirm`** (custom status, state `new`).
- KHÔNG tự động mark paid, KHÔNG invoice, KHÔNG chuyển sang processing/complete.
- Redirect về custom VietQR page (form ẩn, hiển thị trạng thái đã xác nhận).

**Custom Order Status `vietqr_awaiting_payment_confirm`:**

- Khai báo qua data patch `Setup/Patch/Data/InstallVietQrStatuses.php`: 2 status — `vietqr_pending` (label "Chờ thanh toán") + `vietqr_awaiting_payment_confirm` (label "Chờ xác nhận thanh toán"), cả 2 state `new` + `visible_on_front=1` (native My Orders visibility — không cần patch). Patch insert trực tiếp vào `sales_order_status` + `sales_order_status_state` (declarative `etc/sales.xml` không áp dụng state mapping cho module mới trên install sẵn có).
- Trạng thái visible trong Admin Order grid, Order View, và Order Status dropdown.
- Merchant sử dụng trạng thái này để biết customer đã xác nhận chuyển khoản và cần kiểm tra thủ công.

### 4.5 My Orders — VietQR Button

- Thêm ViewModel `Block/Order/VietQrButton.php` vào `sales_order_history.xml` layout.
- ViewModel kiểm tra: order payment method === `secomm_vietqr` VÀ order status === configured New Order Status (default `pending`).
- Nếu thỏa mãn → render button "Thanh toán VietQR" link đến `/vietqr/payment/view/order_id/{id}`.
- Nếu không (không VietQR, đã canceled, đã `vietqr_awaiting_payment_confirm`, đã processing/complete) → không render.
- ponytail: so sánh status string đơn giản, đủ cho 1 payment method. Nếu cần check nhiều status sau này, move sang config-based allowlist.

### 4.6 VietQR API Integration

- **Endpoint**: `POST https://vietqr.vn/api/vietqr/generate` (configurable).
- **Auth**: hiện tại không cần, nhưng `ApiClient` phải có placeholder cho headers (API key/client ID/token) — chỉ cần sửa `ApiClient` khi cần, không sửa business logic.
- **Request**:
  ```json
  {
    "bankAccount": "{{bank_account}}",
    "userBankName": "{{account_name}}",
    "bankCode": "{{bank_code}}",
    "amount": "{{grand_total}}",
    "content": "{{transfer_content_template_rendered}}"
  }
  ```
- **Response mapping**: `qrCode` field là QR payload chính. Không phụ thuộc `qrLink`.
- **HTTP client**: `Magento\Framework\HTTP\Client\Curl` hoặc `Magento\Framework\HTTP\ZendClient` — có timeout từ config.
- **Response validation**: kiểm tra `qrCode` không empty; log warning nếu missing.

### 4.7 QR Rendering

- `qrCode` payload là chuỗi EMVCo QR string — cần render thành QR image client-side.
- Ưu tiên lightweight JS QR library (vd `qrcode-generator` hoặc pure-JS encoder) — bundle inline trong module, không CDN.
- Reuse ở: custom VietQR page. Email: text instructions đủ.

### 4.8 Admin Configuration

**Path**: `Stores → Configuration → Sales → Payment Methods → VietQR`

| Group | Field | Type | Default | Encrypted |
|-------|-------|------|---------|-----------|
| General | Enabled | enable | 0 | No |
| General | Title | text | VietQR | No |
| General | Sort Order | text | 0 | No |
| General | Minimum Order Total | text | (empty) | No |
| General | Maximum Order Total | text | (empty) | No |
| General | Payment from Applicable Countries | allowspecific | 0 (all) | No |
| General | Payment from Specific Countries | multiselect | (empty) | No |
| Merchant Bank | Bank Code | text | (empty) | No |
| Merchant Bank | Bank Account | text | (empty) | No |
| Merchant Bank | Account Name | text | (empty) | No |
| VietQR API | API Endpoint | text | `https://vietqr.vn/api/vietqr/generate` | No |
| VietQR API | API Client ID | text | (empty) | Yes |
| VietQR API | API Key | text | (empty) | Yes |
| VietQR API | Request Timeout (seconds) | text | 10 | No |
| Payment | Transfer Content Template | text | `DH{{order_increment_id}}` | No |
| Payment | Payment Instructions | textarea | (i18n default) | No |
| Payment | New Order Status | select | `vietqr_pending` | No |
| Payment | Awaiting Confirm Status | select | `vietqr_awaiting_payment_confirm` | No |

### 4.9 Mageplaza OSC Compatibility

- Payment method phải hiển thị đúng trong OSC single-page flow.
- OSC render payment methods theo Magento standard `payment.xml` + `MethodInterface`.
- Nếu OSC cần custom template override, thêm layout handle trong `checkout_index_index.xml`.
- [ASSUMPTION: OSC render payment method description từ `payment/info/default.phtml` theo Magento convention — verify trong implementation.]

### 4.10 Email Integration

- Thêm payment instructions block vào order confirmation email qua layout XML `sales_email_order_items.xml`.
- Block đọc từ `PaymentInfo` ViewModel — cùng logic với custom VietQR page.
- Payment instructions dạng text + QR image từ `img.vietqr.io` (fallback text khi image lỗi).

---

## 5. Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| VietQR API | External API | Available | `POST https://vietqr.vn/api/vietqr/generate` — public API, hiện không cần auth |
| Mageplaza OSC | Checkout | Installed | Payment method phải render đúng trong OSC flow |
| Hyvä 3.x | Frontend | Installed | Alpine.js + Tailwind v4, không Knockout/RequireJS |
| Magento_Sales | Core | Installed | Order, Payment models |
| Magento_Payment | Core | Installed | Payment method framework |

---

## 6. Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| VietQR API downtime | M | M | Fallback manual bank instructions; QR không phải prerequisite để thanh toán |
| Mageplaza OSC payment render incompatibility | L | H | Test e2e OSC checkout; OSC thường render payment methods theo Magento standard |
| QR JS library bundle size | L | L | Chọn lightweight library (<10KB gzip); bundle inline, không CDN |
| Admin thay bank config sau khi order đã tạo | M | L | QR snapshot lưu theo order, không phụ thuộc current config (AC-009) |
| VietQR API response format thay đổi | L | M | Validate response trước khi lưu; log warning nếu unexpected format |
| Customer chuyển sai số tiền/nội dung | M | L | Đây là offline payment — merchant reconcile ngoài hệ thống (out of scope) |

---

## 7. Out of Scope

- VietQR callback/webhook.
- Get Token endpoint cho VietQR gọi Magento.
- Bank API integration.
- Bank reconciliation / auto detect transfer.
- Auto-confirm payment / auto invoice.
- Auto transition `Pending → Processing`.
- Virtual Account / Settlement / Refund qua ngân hàng.
- Transaction matching / Custom reconciliation UI.
- Multi-bank QR (chỉ support 1 bank account theo config).
- QR expiry / timeout.

---

## 8. Test Notes

- **OSC e2e**: Place order qua Mageplaza OSC với VietQR → verify redirect đến custom VietQR page (không success page mặc định).
- **Custom page content**: Verify QR, bank info, amount, transfer content, instructions hiển thị đúng.
- **Cancel**: Bấm Cancel → verify redirect về trang phù hợp, order giữ `vietqr_pending`.
- **Submit**: Bấm Submit → verify `vietqr_customer_confirmed` + `vietqr_customer_confirmed_at` + các trường form lưu vào payment, order chuyển sang `Awaiting Payment Confirm`.
- **Duplicate submit**: Mở lại custom page sau submit → verify form ẩn, chỉ hiển thị thông tin + thông báo đã xác nhận.
- **Submit lại sau merchant xử lý**: Merchant chuyển order sang Processing → mở lại custom page → verify không cho submit.
- **My Orders button**: Verify button chỉ hiện với VietQR order ở `vietqr_pending`; ẩn với order `vietqr_awaiting_payment_confirm`, canceled, processing, complete.
- **My Orders reopen**: Bấm button → mở lại custom VietQR page, QR hiển thị từ snapshot.
- **Admin order status**: Verify `Awaiting Payment Confirm` hiển thị đúng trong Admin Order grid và Order View.
- **Idempotency**: Mở custom page 3× → verify chỉ 1 API call (check var/log/system.log).
- **Order ownership**: Truy cập `/vietqr/payment/view/order_id/{id}` với order của customer khác → verify redirect.
- **Guest submit**: Guest checkout → mở link có `key` → bấm Submit (form URL có `key`) → xác nhận thành công, order chuyển `vietqr_awaiting_payment_confirm`.
- **Rate limit**: 11+ request/phút cùng IP vào view/submit → bị chặn với thông báo.
- **Availability config**: set Min/Max Order Total hoặc Specific Countries → method ẩn khỏi checkout khi quote ngoài điều kiện (core `TotalMinMax` + `canUseForCountry`).
- **Error case**: Config sai API endpoint → place order → verify manual instructions hiển thị trên custom page, order vẫn tạo.
- **i18n**: Switch vi_VN/en_US → verify strings render đúng.
- **Email**: Trigger order confirmation email → verify payment instructions có đầy đủ thông tin.

---
<!-- Reference: project-context/02_BUSINESS_RULES.md (BR-001, BR-003, BR-004), project-context/10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md -->
