# TASK-N35E28 — Implement Secomm_VietQr Payment Module

- **ID**: `TASK-N35E28`
- **Parent**: `FEAT-ZKD4VA`
- **External Ref**: `SLP-90 / LC-11`
- **Priority**: P1 (High)
- **Estimate**: ~24–32h
- **Mode**: A (Tier-2: payment + checkout)
- **Spec**: [.ai/specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md](../specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md)
- **Risk tier**: Tier 2 (AGENTS.md §12 — payment + checkout)

## Description

Implement toàn bộ module `Secomm_VietQr` — Magento payment method cho VietQR (offline bank transfer với QR động theo order). Module độc lập, không modify core, tuân thủ Hyvä 3.x (Alpine.js + Tailwind v4) + Mageplaza OSC.

## Scope

- Tạo module `Secomm_VietQr` mới.
- Payment method: enable/disable, không authorize/capture/invoice, order `vietqr_pending` (custom, label "Chờ thanh toán").
- Admin config: bank info, API endpoint, timeout, transfer content template, payment instructions.
- VietQR API integration: `ApiClient`, `RequestBuilder`, `QrGenerator`, `QrResult`.
- Observer `checkout_submit_all_after` để generate QR sau khi order tạo.
- Lưu QR snapshot vào payment `additional_information`.
- **Custom VietQR page** (`/vietqr/payment/view/order_id/{id}`) thay thế success page mặc định: QR + bank info + form Cancel/Submit.
- Plugin `SuccessRedirectPlugin` (around `Onepage\Success::execute`) redirect sang custom page thay success page.
- Submit controller: guard duplicate, lưu confirmed + form fields (transaction ref, notes), chuyển order sang `Awaiting Payment Confirm`.
- **Custom order status** `vietqr_awaiting_payment_confirm` (state `new` — cùng state với `pending`) qua data patch `Setup/Patch/Data/InstallVietQrStatuses.php`.
- **My Orders button**: hiển thị "Thanh toán VietQR" chỉ khi order ở configured New Order Status (default `vietqr_pending`); ẩn khi đã confirmed/canceled/processed.
- Order confirmation email: payment instructions + QR image (`img.vietqr.io`).
- **Custom Monolog Logger**: `Logger/Handler.php` + `Logger/Logger.php` ghi log riêng vào `var/log/secomm_vietqr.log`.
- **Admin Menu & ACL**: link VietQR Configuration trực tiếp dưới menu `Secomm` (`etc/adminhtml/menu.xml` & `etc/acl.xml`).
- **Admin Config mở rộng**: `Debug Mode`, `min_order_total`, `max_order_total`, `allowspecific`, `specificcountry`.
- **Tối ưu QR Code**: Server-side SVG render qua `BaconQrCode` hiển thị tức thì (0ms).
- i18n: vi_VN + en_US.

## Acceptance Criteria

Tham khảo spec SPEC-FEAT-ZKD4VA §3 (AC-001..AC-023).

## Technical Approach

1. Scaffold module (`module.xml`, `registration.php`, `composer.json`, `payment.xml`, `di.xml`, `routes.xml`).
2. `Model/Payment.php` — payment method (offline, no authorize/capture).
3. `Model/Config.php` — admin config reader.
4. `Model/VietQr/` — `ApiClient` + `RequestBuilder` + `QrGenerator` + `QrResult`.
5. `Observer/GenerateQrAfterOrder.php` — checkout_submit_all_after, idempotent, error-safe.
6. `Plugin/SuccessRedirectPlugin.php` — redirect sang custom VietQR page thay success page (event observer là silent no-op).
7. `Controller/Payment/View.php` — custom page (ownership check, QR + info, ẩn Submit nếu đã confirmed).
8. `Controller/Payment/Submit.php` — POST submit (guard duplicate, lưu confirmed + form fields, chuyển status `vietqr_awaiting_payment_confirm`).
9. `Setup/Patch/Data/InstallVietQrStatuses.php` — custom order status `vietqr_awaiting_payment_confirm` (state `new`).
10. `Block/PaymentInfo.php` + `Block/Order/VietQrButton.php` — ViewModels.
11. Admin config (`system.xml`, `config.xml`).
12. Frontend templates (checkout info, custom payment page, My Orders button, email) — Hyvä/Alpine/Tailwind.
13. QR rendering JS (lightweight, inline bundle).
14. i18n files.

## Files/Areas Affected

- `app/code/Secomm/VietQr/` — **NEW**, toàn bộ module.
- `app/code/Secomm/VietQr/Controller/Payment/` — custom page + submit controller.
- `app/code/Secomm/VietQr/view/frontend/layout/` — layout handles cho custom page, My Orders button, email.
- `app/code/Secomm/VietQr/view/frontend/templates/` — custom page, checkout info, My Orders button, email.
- `app/code/Secomm/VietQr/i18n/` — vi_VN.csv + en_US.csv.
- **KHÔNG affect**: core Magento, Mageplaza modules, Secomm_VNPAY, Mollie.

## Risks

- Tier 2 (§12): payment + checkout → TL/SA review bắt buộc.
- OSC payment render compatibility — cần e2e test.
- VietQR API downtime — fallback manual instructions.

## Open Questions (Level 2)

- **Q1**: Mageplaza OSC render payment method description theo Magento standard hay cần custom template override? (Verify trong implementation.)
- **Q2**: Custom VietQR page — Mageplaza OSC có interceptor riêng trên `checkout_onepage_controller_success_action` không? Nếu có, cần kiểm tra tương tác. (Verify trong implementation.)

## Dependencies

- VietQR API (external, available).
- Mageplaza OSC (installed).
- Hyvä 3.x (installed).

## Definition of Done

- [x] Code complete + matches spec
- [x] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [x] Hyvä build OK (`npm run build`)
- [x] QC verified: OSC checkout e2e + custom VietQR page + My Orders button + Submit/Cancel + email (vi/en)
- [x] Evidence `.ai/evidence/TASK-N35E28/`
- [x] project-context updated (`CURRENT_STATE.md`, `NEXT_TASK.md`)
