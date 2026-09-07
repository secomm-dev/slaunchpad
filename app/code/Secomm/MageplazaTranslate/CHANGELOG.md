# Changelog — Secomm_MageplazaTranslate

All notable changes to this module are documented here (append-only).

## [Unreleased]

### Added — 2026-09-07

- `i18n/vi_VN.csv` — Vietnamese translations for `Mageplaza_DeliveryTime` (46/46 keys
  mirrored from the module's `en_US.csv`); storefront phrases mirror the
  `Secomm/launchpad` theme CSV values. BUG-GJT6C1 / ext ticket SLP-146.
- Module scaffolding (`registration.php`, `etc/module.xml`, README, CHANGELOG).
- `view/frontend/requirejs-config.js` + `view/frontend/web/js/delivery-date-locale-mixin.js`
  — mixin `Mageplaza_DeliveryTime/js/view/delivery-information`: registers the
  Vietnamese jQuery UI datepicker regional (month/weekday names, firstDay Monday)
  per-page-store (`<html lang>` gate) so the Delivery Date calendar renders
  Vietnamese on vi_VN while en_US keeps the English default. BUG-GJT6C1 Phase B.
- `i18n/vi_VN.csv` +3 phrase: `Leave a message for the extra fee.` (ExtraFee admin
  `message_title` default), `Comments`, `Enter your comment here` (OSC order-comment
  Knockout block). Mirror rows added to the `Secomm/launchpad` theme CSV pair.
- `i18n/vi_VN.csv` +2 phrase: `Your coupon was successfully applied.` /
  `Your coupon was successfully removed.` (runtime `$t()` literals in
  `Mageplaza_OscPro/js/action/{set,cancel}-coupon-code.js` remapped by the OscPro
  requirejs-config on checkout). Values mirror the `Secomm/launchpad` theme CSV pair.
  BUG-5NR0PD / ext ticket SLP-150.
