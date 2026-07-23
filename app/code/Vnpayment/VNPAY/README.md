# Vnpayment_VNPAY

VNPAY payment gateway for Magento 2 (Vietnam market). Implements the VNPAY redirect payment flow with IPN callback and signature validation.

## What it does
- **`Controller/Order/Pay`** — builds the signed redirect to the VNPAY hosted payment page (request signature construction; amount/currency integrity).
- **`Controller/Order/Info`** — customer return URL after payment.
- **`Controller/Order/Ipn`** — VNPAY server-to-server callback; validates signature + amount + currency, updates order state (idempotent — unique transaction id).
- `payment.xml` / `config.xml` — payment method definition (**default `active=0`**).

## ⚠️ High-risk (Tier 2)
Any change to this module (IPN validation, signature, order state mutation) **requires SA review** (AGENTS.md §11/§12). Requirements:
- Payment logging must **never** include PAN/CVV/full cardholder+expiry (PCI DSS 3.4); mask PII.
- IPN must be **idempotent** (reject duplicate processing on retry) + re-validate signature/amount/currency server-side before any order state change.
- **Default inactive** — enable + security review before go-live.

## Requirements
- `TmnCode` + hash secret configured via env/secret manager (never committed).
- VNPAY sandbox for pre-production testing.

## Related
- DEC-6 (enable + secure VNPAY before go-live).
- `project-context/05_API_CONTRACTS.md`, `10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md` (VNPAY flow).
