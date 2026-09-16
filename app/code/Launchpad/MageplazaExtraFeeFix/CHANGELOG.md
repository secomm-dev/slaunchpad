# Changelog — Launchpad_MageplazaExtraFeeFix

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_ExtraFee` vendor code untouched.

## [Unreleased]

### Fixed (2026-09-15) — Non-Refundable Extra Fee Order Lifecycle State & Credit Memo Fix

- `Plugin/Model/Order/StateResolverPlugin.php`: add `afterGetStateForOrder` plugin on
  `Magento\Sales\Model\Order\OrderStateResolverInterface` to resolve order state to `closed`
  (instead of `complete`) when all items in the order have been fully refunded/canceled, even
  if a non-refundable extra fee keeps `total_paid > total_refunded`.
- `Plugin/Model/Order/CanCreditmemoPlugin.php`: add `afterCanCreditmemo` plugin on
  `Magento\Sales\Model\Order` to disable further creditmemo creation once all items and shipping
  have been fully refunded.
- `Test/Unit/Plugin/Model/Order/StateResolverPluginTest.php`: unit tests for `StateResolverPlugin`.
- `Test/Unit/Plugin/Model/Order/CanCreditmemoPluginTest.php`: unit tests for `CanCreditmemoPlugin`.

### Fixed (2026-09-15) — Creditmemo Extra Fee Non-Numeric Formatting Fix

- `Plugin/Model/Total/Creditmemo/ExtraFeePlugin.php`: add `aroundCollect` plugin on
  `Mageplaza\ExtraFee\Model\Total\Creditmemo\ExtraFee` to sanitize thousand-separator commas
  (e.g. `"10,000"`) from raw creditmemo fee inputs into clean floats before calculations.
  Fixes PHP 8 `Warning: A non-numeric value encountered` and prevents creditmemo save failure.
- `Test/Unit/Plugin/Model/Total/Creditmemo/ExtraFeePluginTest.php`: unit tests for sanitized
  amount addition, max fee validation, and explicit zero refund.

### Added (2026-09-09) — TASK-EPJVGG (SLP-198)

- `view/frontend/web/css/extra-fee-checkout.css`: extend the two SLP-139 rules
  with the `#mp-extra-fee` selector so the Extra Fee block in the OSC checkout
  summary/place-order area (KO template `cart/extra-fee.html`, area=3 Cart)
  collapses its empty `.mp-description` line too — title → first-option gap
  45px → 5px, matching the compact shopping-cart look. The block's external
  `margin-bottom: 20px` is kept. Evidence: `.ai/evidence/TASK-EPJVGG/`.

### Added (2026-09-09) — BUG-NY0M3S (SLP-139)

- `view/frontend/web/css/extra-fee-checkout.css`: collapse the empty
  `.mp-description` line in the OSC payment Extra Fee block (gap 49px → ~2px)
  and cap the description gap at 4px when present.
- `view/frontend/layout/onestepcheckout_index_index.xml`: load the stylesheet
  on the Mageplaza OSC checkout page only.
- Translation of the required-fee validation message lives in
  `Launchpad_MageplazaTranslate/i18n/` (module-layer dictionary — luma scope),
  not in this module; recorded here for discoverability.

### 2026-08 (initial)

- `before` plugin `Plugin/Controller/Product/ExtraFeePlugin.php` on
  `Mageplaza\ExtraFee\Controller\Product\ExtraFee::execute()` — normalize
  missing/null `super_attribute` to `[]` (PHP 8 `reset(null)` TypeError).
  See `REPORT.md` for root cause + unit tests.
