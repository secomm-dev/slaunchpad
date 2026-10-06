# Secomm_CurrencyPrecision

Configurable display decimal precision for prices (ticket **Format Price Product** /
[SLP] — TL-approved solution: plugin on `formatTxt` + `getPriceFormat` + Hyvä wrapper).

## What it does

Adds **Admin → Stores → Configuration → General → Currency Setup → Display Precision (Secomm)**
(store-view scope):

| Field | Meaning |
|---|---|
| Default Display Precision | `Auto` (keep locale/currency default) or fixed `0–4` for currencies without an override |
| Currency Overrides | `CODE=PRECISION` pairs, e.g. `VND=0, USD=2` — resolution is per currency code, so `VND=0` never turns USD into 0 decimals |

Display **only**: numeric price values, tax/discount math, `convertAndRound()`,
order totals and payment payloads (VNPAY/MoMo/Mollie) are never touched.

## How it works — 3 synchronized layers

1. **Server PHP** — `Plugin\CurrencyFormatting` (before, `Magento\Directory\Model\Currency::formatTxt`).
   `formatTxt` is the single choke point (`format() → formatPrecision() → formatTxt()`); it covers
   PLP/PDP/cart/checkout/email/PDF, both the Intl NumberFormatter path and the legacy fallback.
2. **Magento JS** — `Plugin\JsPriceFormat` (after, `Magento\Framework\Locale\Format::getPriceFormat`)
   sets `precision` + `requiredPrecision` together → `checkoutConfig.priceFormat` / `priceUtils`
   (Luma fallback + Mageplaza OSC) matches the server output.
3. **Hyvä** — `view/frontend/templates/currency_precision.phtml` loads right **after**
   `head.hyva-scripts` (vendor `hyva.phtml`) and wraps `hyva.formatPrice`, injecting
   `minimumFractionDigits = maximumFractionDigits = N`. Auto renders nothing — native behavior untouched.

## Scope rules

- `formatTxt` override is skipped in `adminhtml` area (decision D-4 default, TL to confirm) —
  order emails/PDF still follow the config because they render under frontend area emulation.
- Per D-3, caller-supplied `$options['precision']` is overridden — display parity wins.
- CSV export (`Magento\Directory\Model\Currency\Filter`) uses the framework Currency directly →
  stays native by design.

## Verification quick list (QC)

Golden gate first: **Auto → output identical to pre-module** (VND + USD). Then per surface
(PLP, PDP initial/configurable/custom-option/special price, minicart, cart, checkout OSC, totals,
customer account, order-confirmation email, invoice PDF): fixed 0/2/4 × VND/USD with
server ↔ JS parity. Payment smoke after place order: charged amounts unchanged.

Unit tests: `dev/tests/unit/phpunit-secomm.xml` → `Secomm_CurrencyPrecision`.
