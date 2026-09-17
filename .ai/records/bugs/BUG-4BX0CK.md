---
id: BUG-4BX0CK
type: bug
title: "[VietQR] Currency not converted when on EN store view"
project_code: SLP
parent: FEAT-ZKD4VA
external_refs:
  ticket: SLP-234
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-16
updated: 2026-09-16
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_VietQr
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/VietQr
source_areas:
  - payment
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-16
supersedes: []
---

# [SLP][BUG-4BX0CK] [VietQR] Currency not converted when on EN store view

<!-- External ticket: SLP-234 -->

## Summary

The VietQR transfer amount is taken directly from `order.grand_total` (the order/display currency at purchase time) without conversion to VND. When an order is placed on a store view whose display currency is not VND (e.g. the EN store view), the QR code, the stored `vietqr_amount` snapshot, and every display surface show the foreign amount as if it were the transfer amount. Vietnamese bank transfers only accept VND, so the customer transfers a wrong amount and the payment cannot be matched.

Amounts were read from `grand_total` in 3 write paths and re-formatted with the order currency in 3 display paths:

| Path | Location |
|---|---|
| QR API payload | `Model/VietQr/RequestBuilder.php` |
| Payment snapshot | `Observer/GenerateQrAfterOrder.php` |
| QR retry on payment page | `Block/PaymentInfo.php::loadPaymentInfo()` |
| Payment page display | `Block/PaymentInfo.php::getFormattedAmount()` |
| Admin order view / email | `Block/Info/VietQr.php` |
| Order email QR link + table | `templates/email/payment-info.phtml` |

## Mini Spec

### Goal

Every VietQR amount — QR payload, stored snapshot, and display — is VND regardless of the order's display currency.

### Expected Behavior

- Order in VND (VI store view): QR amount, snapshot, and display identical to pre-fix behavior — no regression.
- Order in a foreign display currency with VND base (likely EN store view): QR amount = `base_grand_total` in VND.
- Foreign display currency + foreign base with a configured rate: QR amount = rate-converted, rounded to integer.
- Foreign display currency + foreign base and no VND rate configured: QR generation fails gracefully; customer sees manual bank transfer instructions; error logged. No QR with a wrong amount is ever produced.
- Payment page, admin order view, and order email all render `1.250.000 VND`-style amounts.

### Constraints / Rules

- Vietnamese bank transfers accept VND only — the VietQR API payload has no currency field.
- VND has no subunit — amounts are integers (`round`); display uses `1.250.000 VND` grouping (pre-existing project pattern).
- Single source of truth: `Secomm\VietQr\Model\VndAmount` — all 6 write/display paths consume it; no per-path conversion logic.
- No ObjectManager; constructor DI only (project [BLOCK] rule).
- Missing currency rate must fail closed (throw → caught by existing try/catch paths), never fail open to a foreign amount.

### Approach

- Single helper `Secomm\VietQr\Model\VndAmount`:
  - order currency = VND → `grand_total`;
  - base currency = VND → `base_grand_total` (no rate lookup needed — works without maintained currency rates);
  - otherwise convert `grand_total` via the directory currency rate (`CurrencyFactory`), throwing `LocalizedException` when no VND rate is configured → QR generation fails → customer falls back to manual bank transfer instructions instead of a QR with a wrong amount.
- VND has no subunit — amounts are rounded to integers (`round`).
- Display format: `1.250.000 VND` (matches the pre-existing fallback in `Block/Info/VietQr`), replacing order-currency formatting.
- All 3 write paths + 3 display paths consume the helper.

### Out of Scope

- Regenerating QR / `vietqr_amount` for orders placed before the fix (TL decision — data cleanup).
- Consolidating the duplicated additional-info block between observer and `PaymentInfo` retry path.

### Acceptance Criteria

- Order placed in VND: QR amount unchanged, display unchanged (regression check).
- Order placed in a foreign display currency with VND base: QR amount equals base grand total in VND.
- Order placed in foreign display currency with non-VND base and a configured rate: QR amount = converted, rounded to integer.
- No VND rate configured + foreign base: QR generation fails gracefully, manual transfer instructions shown, error logged.
- Unit tests cover all four branches (`Test/Unit/Model/VndAmountTest.php`).

### Escalation

- Tier 2 (payment logic — revenue): code requires SA/TL review before merge.
- Open questions for TL: currency rates maintenance source for the EN store view; round vs ceil for the rounded VND amount; whether legacy orders need QR regeneration.
