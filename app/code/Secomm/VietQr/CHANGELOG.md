# Changelog

## 1.0.3 (2026-09-03)

- Added `Block/Info/VietQr` to render payment additional information (Bank, Account Number, Account Holder, Amount, Transfer Content, Customer Confirmed, Confirmed At, Transaction Reference, Customer Notes) in Admin Order View and invoices/PDFs (BUG-63CVS3 / SLP-149)
- Updated `Model/Payment` to register `$_infoBlockType` pointing to `Block/Info/VietQr`
- Updated `Controller/Payment/Submit` to append Transaction Reference and Customer Notes into order status history comment
- Added system configuration `customer_confirm_comment` allowing merchants to customize the default confirmation comment in Admin
- Added bilingual translations for all new payment info labels and configuration in `vi_VN.csv` and `en_US.csv`

## 1.0.2 (2026-08-26)

- Added `vietqr_pending` ("Chờ thanh toán") as the default order status — merchants filter VietQR orders in the Admin grid; customers see "unpaid" in My Orders
- Renamed `awaiting_payment_confirm` to `vietqr_awaiting_payment_confirm` ("Chờ xác nhận thanh toán")
- Both statuses map state `new` with `visible_on_front=1` (single data patch `InstallVietQrStatuses`)
- Fixed guest checkout: submit form URL now carries the `key` (protect code) — guests previously could never confirm
- Added missing i18n entries (Amount (VND), Note:, QR scan hint, thank-you message) to both locales
- Replaced the success-redirect observer with `Plugin/SuccessRedirectPlugin` (the `checkout_onepage_controller_success_action` event carries no `response` — observer was a silent no-op)
- Added VietQR button on the customer Order View page
- Email now includes a QR image from `img.vietqr.io` (text fallback remains)
- Removed dead code: unused observer, `Files/banks.json`, `note.md`, unused `getQrImageUrl()`
- Added standard payment availability config: Minimum/Maximum Order Total, Payment from Applicable/Specific Countries (validated by core `Checks\TotalMinMax` + `canUseForCountry` — no custom code)

## 1.0.1 (2026-08-25)

- Added checkout payment method renderer (title + instructions + place order) for Mageplaza OSC / Luma checkout
- Added IP-based rate limiting (10 req/min) on VietQR view/submit endpoints
- Added checkout instructions config provider (`payment_instructions`)
- Changed default order status from `pending_payment` to `pending` (offline bank-transfer convention; native My Orders visibility)
- Changed `awaiting_payment_confirm` to map state `new` (same state as `pending`; status-only change on confirm, no `setState`)
- Removed `UpdatePendingPaymentVisibility` patch — it flipped shared `pending_payment` visibility and would expose Mollie in-flight orders in My Orders
- Removed forced `STATE_PENDING_PAYMENT` transition in `GenerateQrAfterOrder` observer

## 1.0.0 (2026-08-25)

- Initial release
- VietQR offline payment method (secomm_vietqr)
- Dynamic QR generation via VietQR API
- Custom payment page with QR, bank info, confirm/cancel
- Custom order status: awaiting_payment_confirm
- My Orders VietQR button
- Order confirmation email payment instructions
- Bilingual vi_VN + en_US
