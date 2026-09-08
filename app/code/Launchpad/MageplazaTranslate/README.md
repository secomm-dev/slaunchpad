# Launchpad_MageplazaTranslate

## Purpose

Central Launchpad-owned home for **Vietnamese translations of Mageplaza modules**, keeping
`app/code/Mageplaza/*` untouched so third-party modules can be upgraded/overwritten freely.

Magento resolves translation values per scope from the aggregate of **all enabled modules'**
`i18n/*.csv` — a standalone translate module feeds every theme scope exactly like a
module-level CSV would.

## Why a separate module (not theme CSV)

The Mageplaza OSC checkout page (`/checkout`) runs entirely in the **Magento/luma scope**;
the `Secomm/launchpad` theme (and its `i18n/*.csv`) is NOT in the luma fallback chain, so
theme CSVs never reach the checkout page's `js-translation.json`. Only module-level i18n
CSVs do — this module is that source, without touching the vendor directory.

Details / evidence: `.ai/evidence/BUG-GJT6C1/RESULTS.md`, lesson LL-0011.

## Features

- `i18n/vi_VN.csv` — `Mageplaza_DeliveryTime` storefront + admin phrases (BUG-GJT6C1 /
  ext ticket SLP-146). Storefront values mirror the theme CSV (`Secomm/launchpad/i18n`)
  so both scopes render identical strings.
- `i18n/vi_VN.csv` — coupon add/remove success messages on OSC checkout, sourced from
  `Mageplaza_OscPro` JS `$t()` literals (BUG-5NR0PD / ext ticket SLP-150).

## Maintenance

- When adding translations for another Mageplaza module, append rows prefixed/sourced
  from that module's `en_US.csv` and keep key parity per file.
- After changing any CSV: `rm pub/static/frontend/<scope>/<locale>/js-translation.json`
  for the affected scopes, then `bin/magento setup:static-content:deploy -f <locale>`
  (a normal deploy silently skips an existing dictionary — LL-0008/LL-0011), then
  `bin/magento cache:flush`.
