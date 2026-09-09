# Launchpad_MageplazaExtraFeeFix

Project-local compatibility/UX fixes for `Mageplaza_ExtraFee` (4.5.4). The vendor
module is never modified in place — every fix lives here (plugin, layout, CSS).

## Fix 1 — `reset(null)` TypeError on configurable product (REPORT.md)

`Mageplaza_ExtraFee` 4.5.4 controller `Controller/Product/ExtraFee.php` calls
`reset($params['super_attribute'])` without the argument being an array when a
configurable product's extra-fee AJAX request carries no `super_attribute`
(first page load) — PHP 8 throws `TypeError: reset(): Argument #1 ($array) must
be of type array, null given`.

Fix: `before` plugin on `execute()` (`Plugin/Controller/Product/ExtraFeePlugin.php`)
normalizes a missing/null `super_attribute` to `[]`; any other value is untouched.
Details + unit tests: `REPORT.md`.

## Fix 2 — Checkout spacing + validation message (BUG-NY0M3S / SLP-139)

Mageplaza OSC payment step, Extra Fee block:

- **Spacing**: the empty `.mp-description` line box (template renders it even when
  the rule has no description) pushed the options 49px below the rule label.
  `view/frontend/web/css/extra-fee-checkout.css` collapses the empty line
  (`:has()`-based, graceful degradation) and caps the gap at 4px when a
  description exists.
- **Translation**: the validation message `Please choose at least one option for
  each require extra fee` (`extra-fee-billing.js:194`, `$t()`) is translated via
  the module-layer dictionary `Launchpad_MageplazaTranslate/i18n/vi_VN.csv` — the
  OSC checkout runs in the Magento/luma scope (LL-0011), so theme CSVs never
  apply there (same mechanism as BUG-5NR0PD / SLP-150).

Attachment: `view/frontend/layout/onestepcheckout_index_index.xml` loads the
stylesheet on the OSC checkout page only.

## Fix 3 — Summary Extra Fee spacing (TASK-EPJVGG / SLP-198)

The same Extra Fee block in the OSC checkout **summary/place-order area**
(`#mp-extra-fee`, KO template `cart/extra-fee.html`, rule area=3 Cart) had the
identical empty-`.mp-description` gap (45px title → first option; the shopping
cart page's Hyvä template collapses it and shows 13.5px).

Fix: the two SLP-139 CSS rules now also carry the `#mp-extra-fee` selector
(same `:has()` collapse + 4px cap, same graceful degradation). The block's
external `margin-bottom: 20px` is kept — only the internal gap is tightened.
No layout/CSV/template change; the stylesheet is already attached to the OSC
handle only.

## Test

```bash
vendor/bin/phpunit --filter ExtraFeePlugin # unit tests (Fix 1)
```

Fix 2/3 are display-only; verify manually on `/onestepcheckout/` (vi_VN + en_US):
message locale + label→option gap ≤ ~10px in both the payment block and the
summary-area block; `/checkout/cart/` gap unchanged.
