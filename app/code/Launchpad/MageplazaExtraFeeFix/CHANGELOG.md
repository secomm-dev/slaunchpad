# Changelog — Launchpad_MageplazaExtraFeeFix

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_ExtraFee` vendor code untouched.

## [Unreleased]

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
