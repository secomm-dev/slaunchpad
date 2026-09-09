# Changelog — Launchpad_MageplazaExtraFeeFix

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_ExtraFee` vendor code untouched.

## [Unreleased]

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
