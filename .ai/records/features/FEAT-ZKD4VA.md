---
id: FEAT-ZKD4VA
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'VietQR Payment — offline bank transfer với QR động theo order'
mode: A                      # payment + checkout → Tier-2 generic risk
specification_level: FULL
spec_status: DRAFT
specification_ref: ../../specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md
risk: high
status: done                     # implemented 2026-08-26; pending TL Tier-2 code review + QC e2e
created: 2026-08-24
updated: 2026-08-26
ticket_ref:
  - TASK-N35E28                   # implement Secomm_VietQr module (Mode A)
decisions: []
decision_assessment: none
decision_refs: []
decision_approval_summary:
  total: 0
  pending_approval: []
  approved: []
  rejected: []
  superseded: []
  last_synced: 2026-08-24
verified_against_commit: 8d9d6deb
components:
  - CMP-VIETQR                 # Secomm_VietQr — payment method + VietQR API integration
source_areas:
  - app/code/Secomm/VietQr/                             # NEW — toàn bộ module
changes_project_state: true
changes_architecture: true    # thêm payment method mới
changes_integration: true     # VietQR API integration
changes_known_limitations: true   # thêm offline QR bank transfer payment
last_verified: 2026-08-26
supersedes: []
---

# [SLP][FEAT-ZKD4VA] VietQR Payment — offline bank transfer với QR động theo order

<!-- CANONICAL RECORD — Full spec: SPEC-FEAT-ZKD4VA. Offline/manual bank transfer
     có QR động theo order thông qua VietQR API. Không phải full payment gateway. -->

## Context

Merchant cần payment method **VietQR** — offline bank transfer có QR code động chứa thông tin order (bank account, amount, transfer content). Flow: customer chọn VietQR → Place Order → Magento tạo order `vietqr_pending` → gọi VietQR API generate QR → hiển thị QR + hướng dẫn chuyển khoản. Merchant reconcile ngoài hệ thống.

**Risk:** Tier-2 (payment + checkout generic risk categories) → Mode A, TL/SA review bắt buộc.

## Architecture (tóm tắt — canonical trong spec)

```
Mageplaza OSC checkout — chọn payment "VietQR"
        ↓ Place Order
Magento tạo order (vietqr_pending, state new, increment_id assigned)
        ↓ observer/after-place-order
Secomm_VietQr\Model\VietQr\QrGenerator::generate($order)
        ↓
VietQR API (POST /api/vietqr/generate)
        ↓
Lưu QR payload vào payment additional_information
        ↓ redirect
custom VietQR page (/vietqr/payment/{order_id}) — QR + bank info + Cancel/Submit form
        ↓ Submit (guard duplicate)
Lưu confirmed flag + form fields vào payment additional_information
Order → vietqr_awaiting_payment_confirm (state new, status-only)
        ↓
Customer Dashboard → My Orders: button "Thanh toán VietQR" (chỉ khi vietqr_pending)
Email: payment instructions + QR image (img.vietqr.io)
```

- **Payment method:** `Secomm_VietQr` — không authorize, không capture, không auto-invoice.
- **Custom payment page:** route riêng `/vietqr/payment/{order_id}` thay vì success page mặc định; hai nút Cancel (→ thank-you page với skip_vietqr=1) và Submit (lưu flag `customer_confirmed`, order giữ `vietqr_pending`).
- **API integration:** `ApiClient` (HTTP client + auth readiness) → `RequestBuilder` (map order→API request) → `QrGenerator` (orchestrate + save snapshot).
- **Idempotency:** kiểm tra `vietqr_qr_code` trong payment additional_information trước khi gọi API.
- **Error handling:** API failure không rollback order; log + fallback hiển thị manual instructions.

## Module boundary

- `Secomm_VietQr` — module độc lập; dependency: `Magento_Sales`, `Magento_Payment`, `Magento_Checkout`, `Magento_Store`.

## Requirements (feature-level; AC chi tiết AC-001..013 trong spec §3)

- Admin enable/disable + config merchant bank info + VietQR API endpoint + transfer content template.
- Checkout hiển thị VietQR với title + mô tả.
- Order tạo **một lần**, trạng thái `vietqr_pending` (label "Chờ thanh toán").
- Sau Place Order → redirect đến custom VietQR page (không dùng success page mặc định).
- Custom page: QR + bank info + form Cancel/Submit. Submit lưu confirmed + form fields, chuyển order sang `vietqr_awaiting_payment_confirm`. Không cho submit lặp.
- Module khai báo 2 custom order status: `vietqr_pending` + `vietqr_awaiting_payment_confirm` (state `new`) qua data patch.
- QR generate sau khi order có `increment_id`; lưu vào payment additional_information.
- Customer Dashboard → My Orders: button mở lại custom page chỉ khi order `vietqr_pending` (My Orders + Order View).
- Email có payment instructions + QR image.
- API failure → không duplicate order; fallback hiển thị manual instructions.
- Không webhook, reconciliation, auto-confirm, auto-invoice.

## Sub-ticket breakdown

- **[TASK-N35E28](../../tickets/TASK-N35E28-vietqr-payment.md)** — implement toàn bộ `Secomm_VietQr` module. → `done` 2026-08-26 (Mode A, **Tier-2 payment + checkout**; chờ TL review).

## Risks

- Tier-2 payment + checkout: TL/SA review spec trước khi VALID; QC e2e OSC.
- VietQR API availability: fallback manual instructions khi API down.
- Mageplaza OSC compat: payment method phải render đúng trong OSC single-page flow.
- Hyvä frontend: payment instructions phải dùng Alpine.js/Tailwind, không Knockout/RequireJS.
- RateLimiter dùng `REMOTE_ADDR` — sau Varnish (prod) phải config `X-Forwarded-For` nếu không toàn site chung 10 req/phút trên view/submit.
- Mageplaza ThankYouPage: nếu route success riêng thay vì core `checkout/onepage/success`, `SuccessRedirectPlugin` không chạy — cần QC e2e.

## Implementation notes (2026-08-26)

- Redirect dùng **plugin `around` trên `Onepage\Success::execute`** (`Plugin/SuccessRedirectPlugin.php`), KHÔNG phải observer `checkout_onepage_controller_success_action` — event đó không carry `response` (observer là silent no-op, đã phát hiện khi test).
- Order statuses: `vietqr_pending` ("Chờ thanh toán") → `vietqr_awaiting_payment_confirm` ("Chờ xác nhận thanh toán"), cả 2 state `new` + `visible_on_front=1` qua `Setup/Patch/Data/InstallVietQrStatuses.php`. KHÔNG dùng `pending`/`pending_payment` dùng chung (Mollie in-flight dùng `STATE_PENDING_PAYMENT`).
- Rate limiting 10 req/phút/IP trên view/submit (`Model/RateLimiter.php`).
- Guest submit: form URL kèm `key` (protect code) — guest ownership check pass.
- Checkout renderer: Knockout component cho OSC/luma-checkout compat (`view/frontend/web/js/view/payment/`); custom page dùng Alpine.js.

## References

- Spec: [SPEC-FEAT-ZKD4VA](../../specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md)
- Risk tier: AGENTS.md §9/§12
- Checkout flow: project-context/10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md
