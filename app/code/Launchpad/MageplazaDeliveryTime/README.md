# Launchpad_MageplazaDeliveryTime

## Purpose

Launchpad-owned home for **fixes to `Mageplaza_DeliveryTime`** (and core bugs that
surface on its admin/storefront surfaces). Future DeliveryTime fixes land here instead
of new per-bug modules.

Fixes are applied via requirejs mixins / plugins — **never by editing
`app/code/Mageplaza/*` in place** (vendor must stay upgradable — same isolation rule
as `Launchpad_MageplazaTranslate`).

## Contents

- `view/adminhtml/web/js/calendar-position-mixin.js` + `view/adminhtml/requirejs-config.js`
  — admin datepicker position fix (BUG-SRF024 / SLP-147): `mage/calendar` and
  `mage.dateRange` are re-registered with a document-coordinate `_findPos` (core
  regression 2.4.8-p1+, upstream magento/magento2#40083) that floated the jQuery UI
  datepicker to the top of the page on the Mageplaza → Delivery Time → Date Off
  config rows.

## Adding a new fix

- **Requirejs mixin** (frontend/adminhtml): add the mixin file, declare it in the
  area's `requirejs-config.js`. A mixin must NOT declare its target module as a
  dependency — circular reference, requirejs hangs silently (BUG-SRF024 gotcha).
- **PHP plugin/override**: `etc/<area>/di.xml` + `Plugin/...`; prefer `before`/`after`
  over `around`.
- Each fix keeps its own `.ai/records/bugs/BUG-*.md` record + gets a CHANGELOG entry
  here.
