# Secomm VietQr Payment Module

Offline bank transfer payment method with dynamic QR code generation via VietQR API.

## Features

- Payment method: VietQR (offline, no authorize/capture/invoice)
- Dynamic QR code generation per order via VietQR API
- Checkout renderer with payment instructions (Mageplaza OSC / Luma checkout)
- Custom payment page with QR + bank info + customer confirmation
- Custom order statuses: `vietqr_pending` ("Chờ thanh toán") + `vietqr_awaiting_payment_confirm` ("Chờ xác nhận thanh toán") — merchant filters VietQR orders in Admin grid
- My Orders button to reopen payment page
- Order confirmation email with payment instructions
- IP-based rate limiting on the public payment pages
- Bilingual: vi_VN + en_US

## How it works

1. Customer selects VietQR at Mageplaza OSC checkout
2. After Place Order, QR is generated via VietQR API and stored as payment snapshot
3. Customer is redirected to custom VietQR payment page
4. Customer scans QR, transfers, then clicks "I have completed the transfer"
5. Order moves to `vietqr_awaiting_payment_confirm` (Chờ xác nhận thanh toán)
6. Merchant verifies payment manually and processes the order

## Configuration

**Stores > Configuration > Sales > Payment Methods > VietQR**

- Enable/disable, title, sort order
- Bank Code, Bank Account, Account Name
- API Endpoint, API Client ID (encrypted), API Key (encrypted), Request Timeout
- Transfer Content Template, Payment Instructions
- New Order Status, Awaiting Confirm Status

## Compatibility

- Magento 2.4.8-p5, PHP 8.2+
- Hyva 3.x (Alpine.js + Tailwind v4)
- Mageplaza One Step Checkout

## Flow

```
Place Order -> `vietqr_pending` (Chờ thanh toán, state new) -> Customer confirms -> `vietqr_awaiting_payment_confirm` (state new) -> Merchant invoices -> `processing` -> `complete`
```
