# Changelog — Launchpad_MageplazaDeliveryTime

All notable changes to this module are documented here (append-only).

## [Unreleased]

### Changed — 2026-09-08 (2)

- Renamed again `Launchpad_MageplazaRewrite` →
  **`Launchpad_MageplazaDeliveryTime`** (dir `app/code/Launchpad/MageplazaRewrite`
  → `app/code/Launchpad/MageplazaDeliveryTime`): module scope narrowed to
  `Mageplaza_DeliveryTime` fixes (user decision 2026-09-08).

### Changed — 2026-09-08

- Renamed `Secomm_AdminCalendarFix` → `Launchpad_AdminCalendarFix` →
  **`Launchpad_MageplazaRewrite`** (dir `app/code/Secomm/AdminCalendarFix` →
  `app/code/Launchpad/MageplazaRewrite`): module repositioned as the single home
  for Mageplaza fixes (user decision 2026-09-08). `Launchpad_MageplazaExtraFeeFix`
  stays a separate module.

### Added — 2026-09-04

- Admin datepicker position fix (BUG-SRF024 / ext ticket SLP-147 — record
  `.ai/records/bugs/BUG-SRF024.md`, evidence `.ai/evidence/BUG-SRF024/`):
  `view/adminhtml/requirejs-config.js` mixin `mage/calendar` +
  `view/adminhtml/web/js/calendar-position-mixin.js` re-registering
  `mage.calendar` / `mage.dateRange` with a document-coordinate
  `_overwriteFindPos` (core regression 2.4.8-p1+, upstream
  magento/magento2#40083). Originally shipped as `Secomm_AdminCalendarFix`.
