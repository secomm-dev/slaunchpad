# Implementation Plan: Secomm_VietQr Payment Module

> Plan only — chưa viết code (Hard Gate 3: No Code Without Plan).

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | TASK-N35E28 / SPEC-FEAT-ZKD4VA |
| Specification | [.ai/specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md](../../specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md) |
| Author | AI (Victor Pham) |
| Reviewer (TL) | ___________ |
| Workflow Mode | A (Tier-2: payment + checkout) |
| Date | 2026-08-24 |

## 1. Approach

### Tại sao approach này

**Offline payment method + post-order QR generation + custom redirect page.** Order Lifecycle: Place Order → QR generated server-side → redirect custom page → customer view QR / submit confirm.

**Key architectural decisions (đã resolve qua research):**

| Decision | Lựa chọn | Lý do |
|----------|----------|-------|
| Payment rendering trong OSC | Không cần custom Knockout renderer | Offline method — `magento2-luma-checkout` compat layer render standard `AbstractMethod` tự động. Method title + instructions từ config đủ. |
| Post-order redirect | Plugin `around` trên `Onepage\Success::execute` (`SuccessRedirectPlugin`) | Event `checkout_onepage_controller_success_action` không carry `response` — observer là silent no-op (đã phát hiện khi test). Plugin chỉ redirect khi method VietQR + chưa confirmed; skip khi `skip_vietqr=1` (Cancel). Không conflict OSC/Mollie. |
| QR generation timing | Observer `checkout_submit_all_after` (sau order persist) | Same event Mollie dùng. Idempotent qua `additional_info` check. |
| Order status transition | `vietqr_pending` → `vietqr_awaiting_payment_confirm` (cùng state `new`) | 2 custom status do module sở hữu — merchant lọc đơn VietQR từ grid, customer thấy "Chờ thanh toán". State `new` native `visible_on_front=1` — My Orders hiện ngay, không cần visibility patch. Custom status qua data patch `Setup/Patch/Data/InstallVietQrStatuses.php` map state `new`, `visible_on_front=1`. Không modify trạng thái dùng chung — không side effect với Mollie in-flight (`STATE_PENDING_PAYMENT`). Không invoice, không capture — merchant xử lý thủ công. |
| QR rendering | Client-side JS từ EMVCo payload | Inline lightweight JS library (<10KB gzip). Không CDN, không server-side image. |
| OSC payment method description | Override `getInfo()` trong Payment model hoặc layout `beforeMethods` block | OSC `LayoutProcessor` có slot `beforeMethods`/`afterMethods` trong jsLayout. Tuy nhiên, standard offline method chỉ cần title — descriptions thêm qua `payment/info/default.phtml` (order view) hoặc `beforeMethods` phtml block. **Verify e2e: nếu title-only đủ thì không cần thêm description tại checkout.** |

**Q1 (ticket) resolved:** OSC render payment methods theo standard Magento `jsLayout` payment renderer pipeline. `magento2-luma-checkout` compat cho phép Knockout component chạy dưới Hyvä. Offline method không cần custom renderer.

**Q2 (ticket) resolved:** OSC không có interceptor trên success controller. Implement bằng plugin `around` trên `Magento\Checkout\Controller\Onepage\Success::execute` — event `checkout_onepage_controller_success_action` không carry `response` nên observer approach bị bỏ.

### Không làm gì

- Không modify core Magento, Mageplaza modules, VNPAY, Mollie.
- Không dùng RequireJS/Knockout cho custom VietQR page (dùng Alpine.phtml).
- Không tạo `tailwind.config.js`.
- Không add composer dependency mới (QR JS bundle inline).

## 2. Files affected

Tất cả file là **new** trong `app/code/Secomm/VietQr/` — không modify file existing nào.

| File | Change | AC mapping |
|------|--------|------------|
| `registration.php` | new | — |
| `composer.json` | new | — |
| `etc/module.xml` | new | — |
| `etc/payment.xml` | new | AC-005 |
| `etc/config.xml` | new | AC-001..AC-004 |
| `etc/adminhtml/system.xml` | new | AC-001..AC-004 |
| `etc/di.xml` | new | DI wiring & custom logger bindings |
| `etc/adminhtml/menu.xml` | new | Admin menu link under Secomm root menu |
| `etc/acl.xml` | new | ACL permissions for VietQR menu & config |
| `Logger/Handler.php` | new | Custom Log Handler (var/log/secomm_vietqr.log) |
| `Logger/Logger.php` | new | Custom Monolog Logger instance |
| `etc/events.xml` | new | AC-008 |
| `Setup/Patch/Data/InstallVietQrStatuses.php` | new | AC-015 |
| `etc/frontend/routes.xml` | new | AC-011 |
| `Api/QrGeneratorInterface.php` | new | AC-008 |
| `Model/Payment.php` | new | AC-005, AC-007 |
| `Model/Config.php` | new | AC-001..AC-004 |
| `Model/VietQr/ApiClient.php` | new | AC-008, AC-019, AC-020 |
| `Model/VietQr/QrGenerator.php` | new | AC-008 |
| `Model/VietQr/RequestBuilder.php` | new | AC-008 |
| `Model/VietQr/QrResult.php` | new | AC-008, AC-009 |
| `Observer/GenerateQrAfterOrder.php` | new | AC-006, AC-008, AC-010 |
| `Plugin/SuccessRedirectPlugin.php` | new | AC-011 |
| `Model/RateLimiter.php` | new | AC-020 |
| `Model/InstructionsConfigProvider.php` | new | AC-005 |
| `Controller/Payment/View.php` | new | AC-011, AC-017, AC-019, AC-023 |
| `Controller/Payment/Submit.php` | new | AC-012, AC-013, AC-014 |
| `Block/PaymentInfo.php` | new | AC-009, AC-018 |
| `Block/Order/VietQrButton.php` | new | AC-016 |
| `view/frontend/layout/secomm_vietqr_payment_view.xml` | new | AC-011 |
| `view/frontend/layout/sales_order_history.xml` | new | AC-016 |
| `view/frontend/layout/sales_email_order_items.xml` | new | AC-018 |
| `view/frontend/templates/payment/view.phtml` | new | AC-011, AC-012, AC-013, AC-014, AC-022 |
| `view/frontend/layout/checkout_index_index.xml` | new | AC-005 |
| `view/frontend/web/js/view/payment/vietqr-renderer.js` + `method-renderer/vietqr-method.js` + `template/payment/vietqr.html` | new | AC-005 |
| `view/frontend/layout/sales_order_view.xml` + `templates/order/vietqr-button-view.phtml` | new | AC-016 |
| `view/frontend/templates/order/vietqr-button.phtml` | new | AC-016 |
| `view/frontend/templates/email/payment-info.phtml` | new | AC-018 |
| `view/frontend/web/js/vietqr-qr-renderer.js` | new | AC-022 |
| `i18n/vi_VN.csv` | new | AC-021 |
| `i18n/en_US.csv` | new | AC-021 |
| `README.md` | new | WARN (AGENTS.md §7.2) |
| `CHANGELOG.md` | new | WARN (AGENTS.md §7.2) |

## 3. Steps (độc lập reviewable, theo thứ tự)

### Step 1 — Module scaffold + Payment method — risk: low — deps: none

Scaffold toàn bộ module structure, payment method declaration, admin config.

- `registration.php` — register `Secomm_VietQr`.
- `composer.json` — name `secomm/module-vietqr`, type `magento2-module`.
- `etc/module.xml` — `setup_version="1.0.0"`, sequence `Magento_Sales`, `Magento_Payment`, `Magento_Checkout`.
- `etc/payment.xml` — method `secomm_vietqr`, group `offline`, `allow_multiple_address=1`.
- `etc/config.xml` — default values: `active=0`, `model=Secomm\VietQr\Model\Payment`, `order_status=vietqr_pending`, `title=VietQR`, `group=offline`, API endpoint, timeout=10, transfer template `DH{{order_increment_id}}`.
- `etc/adminhtml/system.xml` — 4 groups (General, Merchant Bank, VietQR API, Payment) theo spec §4.8. Encrypted fields: `api_client_id`, `api_key` (type `obscure`). Source model cho `order_status`: `Magento\Sales\Model\Config\Source\Order\Status\Newprocessing`. **Field `awaiting_confirm_status`**: hardcoded value `vietqr_awaiting_payment_confirm`, type `select` với source model custom hoặc static options.
- `Model/Payment.php` — extends `AbstractMethod`, `$_code = 'secomm_vietqr'`, `$_isOffline = true`, all `$_can* = false`, `getConfigPaymentAction() → null`. **Không override `isAvailable()`** — standard behavior dựa vào config `active`.
- `Model/Config.php` — helper đọc admin config values. Methods: `isEnabled()`, `getBankCode()`, `getBankAccount()`, `getAccountName()`, `getApiEndpoint()`, `getApiClientId()`, `getApiKey()`, `getRequestTimeout()`, `getTransferContentTemplate()`, `getPaymentInstructions()`, `getNewOrderStatus()`, `getAwaitingConfirmStatus()`. All via `ScopeConfigInterface`.
- `etc/di.xml` — declare `Config` as non-virtual type (hoặc preference nếu cần).
- **verify**: `bin/magento module:status Secomm_VietQr` shows enabled. Admin → Stores → Config → Sales → Payment Methods → VietQR hiển thị đúng các field.

### Step 2 — Custom order status — risk: medium — deps: Step 1

- `Setup/Patch/Data/InstallVietQrStatuses.php` — insert 2 status: `vietqr_pending` (label `Chờ thanh toán`) + `vietqr_awaiting_payment_confirm` (label `Chờ xác nhận thanh toán`) + map to state `new` với `visible_on_front=1` (cùng state với `pending` — My Orders hiện native, không cần visibility patch, không ảnh hưởng Mollie).
- **verify**: Admin Order grid dropdown hiển thị `Awaiting Payment Confirm`.

### Step 3 — VietQR API layer — risk: medium — deps: Step 1

- `Api/QrGeneratorInterface.php` — `generate(\Magento\Sales\Model\Order $order): QrResult`.
- `Model/VietQr/QrResult.php` — value object: `qrCode` (string), `rawData` (array). Constructor + getters. Immutable.
- `Model/VietQr/RequestBuilder.php` — `build(Order $order, Config $config): array`. Render transfer content template (replace `{{order_increment_id}}`), map fields theo spec §4.6. Return array chuẩn API request.
- `Model/VietQr/ApiClient.php` — `generate(array $request): QrResult`. Dùng `Magento\Framework\HTTP\Client\Curl`. Set timeout từ config. Set headers (auth placeholder: `x-client-id` + `x-api-key` nếu configured). POST JSON, parse response. Validate `qrCode` field không empty. Throw `\Magento\Framework\Exception\LocalizedException` nếu 4xx/5xx hoặc `qrCode` missing. **Không log credentials.**
- `Model/VietQr/QrGenerator.php` — implements `QrGeneratorInterface`. DI `ApiClient`, `RequestBuilder`, `Config`. `generate()`: build request → call API → return `QrResult`.
- `etc/di.xml` — bind `QrGeneratorInterface` → `QrGenerator`.
- **verify**: Unit test `RequestBuilder::build()` với mock order/config. Unit test `ApiClient` response parsing.

### Step 4 — Observer: QR generation sau order — risk: high — deps: Step 3

- `etc/events.xml` — observer `vietqr_generate_qr` on `checkout_submit_all_after`, instance `Secomm\VietQr\Observer\GenerateQrAfterOrder`.
- `Observer/GenerateQrAfterOrder.php`:
  1. Get order from event data.
  2. Check `payment.getMethod() === 'secomm_vietqr'` — nếu không → return.
  3. **Idempotency**: check `payment.getAdditionalInformation('vietqr_qr_code')` — nếu đã có → return.
  4. Call `QrGeneratorInterface::generate($order)`.
  5. Lưu vào payment `additional_information`: `vietqr_qr_code`, `vietqr_bank_code`, `vietqr_bank_name` (hardcode label từ `Config::getBankCode()`), `vietqr_bank_account`, `vietqr_account_name`, `vietqr_amount` (order `grand_total`), `vietqr_content` (rendered template string), `vietqr_generated_at` (datetime string). Set via `$payment->setAdditionalInformation(...)`.
  6. `$payment->save()`.
  7. **Error handling**: catch `\Exception`, log warning (không log credentials), **KHÔNG throw** — order đã persist, không rollback.
- **verify**: Place order qua OSC với VietQR → check `sales_order_payment.additional_information` có `vietqr_qr_code`. Place order lần 2 → check không gọi API lại.

### Step 5 — Redirect sang custom page — risk: medium — deps: Step 1

- `etc/frontend/di.xml` — plugin `vietqr_success_redirect` trên `Magento\Checkout\Controller\Onepage\Success`.
- `Plugin/SuccessRedirectPlugin.php` (`aroundExecute`):
  1. Nếu request có `skip_vietqr` param → proceed (thank-you page sau Cancel).
  2. Load order từ `checkoutSession->getLastRealOrder()`.
  3. Check `payment.getMethod() === 'secomm_vietqr'` + chưa có `vietqr_customer_confirmed`.
  4. Nếu đúng → redirect `/vietqr/payment/view` (+ `key` cho guest).
  5. Ngược lại → `$proceed()` (Mollie, VNPAY, method khác không受 ảnh hưởng).
- Ghi chú: observer `checkout_onepage_controller_success_action` ban đầu được plan nhưng **silent no-op** (event không carry `response`) — thay bằng plugin này.
- **AC-006 guard**: Plugin chỉ redirect, KHÔNG tạo order. Single order guaranteed.
- **verify**: Place order VietQR → redirect đến `/vietqr/payment/view/order_id/{id}`. Place order Mollie → vẫn success page bình thường. Cancel → `skip_vietqr=1` → thank-you page không bị redirect lại.

### Step 6 — Custom VietQR page (Controller + Template) — risk: high — deps: Step 2, 4, 5

- `etc/frontend/routes.xml` — route `secomm_vietqr`, frontName `vietqr`.
- `view/frontend/layout/secomm_vietqr_payment_view.xml` — page layout, 1-column. Block `Secomm\VietQr\Block\PaymentInfo` as ViewModel.
- `Block/PaymentInfo.php` (ViewModel):
  - Constructor DI: `OrderRepositoryInterface`, `CustomerSession`, `UrlInterface`, `Config`.
  - `init($orderId, $key = null)`: load order, verify ownership (customer_id match hoặc guest key match). Trả về `self` hoặc throw.
  - `getOrder()`, `getPaymentInfo()` (return array từ `additional_information`), `getQrCode()`, `isQrAvailable()`, `isConfirmed()`, `getBankCode()`, `getBankAccount()`, `getAccountName()`, `getAmount()`, `getContent()`, `getInstructions()`, `getOrderIncrementId()`, `getCancelUrl()`, `getFormActionUrl()`.
  - Nếu `vietqr_qr_code` chưa có (API failed lúc Step 4) → attempt generate lại một lần qua `QrGeneratorInterface` + save.
  - `getFormattedAmount()`: format currency từ order.
- `Controller/Payment/View.php`:
  - `execute()`: get `order_id` param, get `key` param (optional, guest). Call `$this->paymentInfo->init($orderId, $key)`. Return page result.
  - Catch `\Magento\Framework\Exception\NoSuchEntityException` → redirect home.
  - Catch ownership violation → redirect home hoặc customer order list.
  - CSRF: GET request, không cần form key.
- `view/frontend/templates/payment/view.phtml`:
  - Hyvä/Alpine.js + Tailwind v4 styling.
  - Layout: card container, QR code area (render từ JS), info grid (order number, bank, account, amount, transfer content), instructions section.
  - QR: `<div x-data="vietQrRenderer()" x-init="render()"></div>`, pass `qrCode` payload qua `x-data` attribute.
  - Hai nút:
    - **Cancel**: link đến `$block->getCancelUrl()` (home hoặc order list). Không submit gì.
    - **Submit**: form POST đến `$block->getFormActionUrl()`, field `form_key` + optional `transaction_ref` + `customer_notes`. Ẩn bằng `x-show="!isConfirmed"`.
  - Nếu `isConfirmed()`: ẩn form Submit, hiện message "Bạn đã xác nhận thanh toán".
  - Tailwind classes: static, trong `@source` scope.
- **verify**: Access `/vietqr/payment/view/order_id/{id}` → hiển thị QR + info. Access với order của customer khác → redirect. QR render đúng từ EMVCo payload.

### Step 7 — Submit controller — risk: high — deps: Step 2, 6

- `Controller/Payment/Submit.php`:
  - `execute()`:
    1. POST only → else redirect.
    2. Get `order_id` + `key` params.
    3. Load order, verify ownership (same logic View.php).
    4. **CSRF**: `$this->formKeyValidator->validate($this->getRequest())` → fail → redirect back.
    5. Check payment method === `secomm_vietqr`.
    6. **Guard duplicate**: `payment.getAdditionalInformation('vietqr_customer_confirmed')` → true OR order status !== configured New Order Status → redirect to custom page (show confirmed message).
    7. Set additional info: `vietqr_customer_confirmed = true`, `vietqr_customer_confirmed_at = date('Y-m-d H:i:s')`, `vietqr_transaction_ref` (nếu POST param), `vietqr_customer_notes` (nếu POST param).
    8. `$payment->save()`.
    9. Set order status: `$order->setStatus('vietqr_awaiting_payment_confirm')` + add status history comment. `$order->save()`.
    10. Redirect về `/vietqr/payment/view/order_id/{id}`.
- **verify**: Submit → payment info có `vietqr_customer_confirmed = true`, order status = `vietqr_awaiting_payment_confirm`. Submit lần 2 → redirect về page, form ẩn. Merchant chuyển order processing → customer không submit được.

### Step 8 — QR rendering JS — risk: low — deps: Step 6

- `view/frontend/web/js/vietqr-qr-renderer.js`:
  - Alpine.js component `vietQrRenderer()`.
  - Sử dụng lightweight QR code generator (inline bundle, ~6KB gzip — ví dụ `qrcode-generator` npm package, copy file vào module, không CDN, không `require.js`).
  - `init()`: get `qrCode` payload from element attribute, render SVG QR into container.
  - Export as global function trên `window` hoặc IIFE.
- Load trong template via `<script>` tag (không RequireJS — Hyvä).
- **verify**: QR image render đúng, scan được bằng app ngân hàng.

### Step 9 — My Orders button — risk: low — deps: Step 6

- `view/frontend/layout/sales_order_history.xml` — add block `Secomm\VietQr\Block\Order\VietQrButton` vào `sales.order.history` container, after existing items.
- `Block/Order/VietQrButton.php` (ViewModel):
  - `__construct(OrderFactory $orderFactory, Config $config)`.
  - `canShowButton(Order $order): bool` — return true chỉ khi: `payment.method === 'secomm_vietqr'` AND `order.status === configured New Order Status` (default `vietqr_pending`).
  - `getPaymentViewUrl(Order $order): string` — `/vietqr/payment/view/order_id/{id}`.
- `view/frontend/templates/order/vietqr-button.phtml` — check `canShowButton($order)` → render button link. Tailwind styled.
- **verify**: My Orders → VietQR order ở `vietqr_pending` → button hiển thị. Order `vietqr_awaiting_payment_confirm` / canceled / processing → button ẩn. Non-VietQR order → button ẩn.

### Step 10 — Email payment instructions — risk: low — deps: Step 6

- `view/frontend/layout/sales_email_order_items.xml` — add block `Secomm\VietQr\Block\PaymentInfo` (hoặc separate email block) vào order confirmation email, before/after items.
- `view/frontend/templates/email/payment-info.phtml` — text instructions + QR image từ `img.vietqr.io` (fallback text khi image lỗi). Đọc từ `additional_information` snapshot (giống View page).
- **verify**: Trigger order confirmation email → payment instructions hiển thị đầy đủ.

### Step 11 — i18n — risk: low — deps: Step 6, 9, 10

- `i18n/vi_VN.csv` — tất cả storefront strings (Vietnamese primary).
- `i18n/en_US.csv` — tất cả storefront strings (English).
- Strings bao gồm: payment method title, custom page headings, button labels, instructions, status labels, messages (confirmed, error), email text.
- **verify**: Switch locale vi_VN/en_US → tất cả strings render đúng.

### Step 12 — Enable module + integration test — risk: high — deps: all

- `bin/magento setup:upgrade`.
- Enable module trong Admin Config.
- Configure bank info + API endpoint.
- **L3 high-risk validation (payment + checkout):**
  - OSC e2e: select VietQR → Place Order → redirect custom page (không success page mặc định).
  - Custom page: QR + bank info + amount + transfer content hiển thị đúng.
  - Cancel: redirect về trang phù hợp, order giữ `vietqr_pending`.
  - Submit: order chuyển `vietqr_awaiting_payment_confirm`, payment info có confirmed fields.
  - Duplicate submit: form ẩn sau confirm.
  - My Orders: button chỉ hiện `vietqr_pending`, ẩn khi đã confirmed.
  - My Orders reopen: QR hiển thị từ snapshot.
  - Idempotency: mở custom page 3× → check log, chỉ 1 API call.
  - Order ownership: truy cập order người khác → redirect.
  - Error case: sai API endpoint → manual instructions hiển thị, order vẫn tạo.
  - Email: payment instructions có đầy đủ thông tin.
  - i18n: vi_VN + en_US.
- **verify**: tất cả AC pass.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| OSC checkout flow bị ảnh hưởng | high | VietQR là offline method, không intercept OSC JS. Observer `checkout_submit_all_after` chạy sau order persist, không modify quote. Plugin `SuccessRedirectPlugin` chỉ redirect khi method === `secomm_vietqr` + chưa confirmed, không affect Mollie/VNPAY/other methods. Test e2e all payment methods. |
| Mollie/VNPAY redirect bị chặn | high | Plugin `SuccessRedirectPlugin` check `payment.getMethod() === 'secomm_vietqr'` trước khi redirect. Các method khác không bị ảnh hưởng. Test Mollie + VNPAY flow sau khi enable VietQR. |
| Order duplicate | high | Không tạo order trong observer — chỉ read/save payment additional_info. Order creation là Magento core responsibility. Test: 1 order per Place Order click. |
| VietQR API error gây order rollback | medium | Observer catch exception, log, không throw. Order vẫn persist ở `vietqr_pending`. Custom page fallback manual instructions. |
| QR JS bundle size | low | Chọn library <10KB gzip. Bundle inline. |
| Admin change bank config sau khi order đã tạo | low | QR snapshot lưu theo order `additional_information`, không đọc current config khi render. |

## 5. Test approach

- **Unit**: `RequestBuilder::build()` (template rendering, field mapping), `QrResult` (value object), `Config` (config reads).
- **Integration / QC**: Xem `testcase` skill cho full QC test case generation. Toàn bộ AC-001..AC-023.
- **High-risk validation (L3)**: OSC checkout e2e + payment redirect + order status transition + idempotency + order ownership. Payment + checkout = L3 mandatory.

## 6. Out of scope

- VietQR callback/webhook, Get Token endpoint, Bank API integration.
- Bank reconciliation / auto detect transfer / auto confirm payment / auto invoice.
- Auto transition `Pending → Processing`.
- Virtual Account / Settlement / Refund qua ngân hàng.
- Transaction matching / Custom reconciliation UI.
- Multi-bank QR (chỉ 1 bank account theo config).
- QR expiry / timeout.
- Modify core, Mageplaza, VNPAY, Mollie code.

## 7. Open questions / Escalation

- **RESOLVED Q1**: OSC render payment methods theo standard Magento `jsLayout` pipeline. `magento2-luma-checkout` compat layer cho phép Knockout chạy dưới Hyvä. Offline method không cần custom renderer — title từ config đủ. Checkout description (AC-005) có thể cần verify e2e.
- **RESOLVED Q2**: OSC không interceptor trên success controller. Dùng plugin `around` `Onepage\Success::execute` (event không carry `response` → observer no-op).
- **ESC-1 (Tier 2)**: Confirm choice of QR JS library với TL trước khi bundle. Đề xuất: `qrcode-generator` (kazuhikoarase, MIT, ~6KB gzip) — pure JS, không dependency, output SVG/CSS.
- **ESC-2 (Tier 2)**: Confirm `Awaiting Payment Confirm` status label và visibility trong Admin Order grid với TL.
