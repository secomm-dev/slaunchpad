# Changelog — Launchpad_Osc

All notable changes to this project layer module are documented here.

## [Unreleased]

### Fixed

- SLP-232: `etc/db_schema.xml` — re-declare `quote_address.osc_gift_wrap_amount`
  as `decimal`. Mageplaza_Osc 2.1.9 (product One Step Checkout 4.3.14, current
  latest) ships it as `boolean` → TINYINT(1), signed max **127**: every totals
  collect copies the amount onto the address (vendor
  `TotalsCollector::collectAddressTotals` → `$address->addData($total->getData())`),
  quote save clamps any amount ≥ 128 (every real VND case) to 127, and the Order
  Summary segment then displays "127" while grand total math stays correct.
  Declaration merges after the vendor's (module sequence), so
  `setup:db-schema:upgrade` emits the tinyint→decimal ALTER once. Requires
  Tier-2 review (OSC + DB schema) before apply + one-time data repair per
  environment (SQL in the file header comment). Soft-coupling intact: with
  Mageplaza_Osc absent the declaration only keeps the orphaned column alive.
- BUG-F8R4E8 (SLP-214): `view/frontend/requirejs-config.js` +
  `view/frontend/web/js/model/shipping-rate-service-mixin.js` — collapse duplicate
  init-time shipping-rate estimations. Mageplaza OSC fires two estimations on
  checkout load (`shipping.js afterResolveDocument` + `shipping-rates-validator`
  200 ms field timer) and the rate processor only caches into `rateRegistry` after
  the first response, so the duplicate re-POSTed the same payload to
  `POST /V1/carts/mine/estimate-shipping-methods` (double loading-spinner flicker).
  Mixin on `Mageplaza_Osc/js/model/shipping-rate-service` skips same-address
  (`getCacheKey()`) requests while one is in flight; address changes still refetch.

### Added

- TASK-8TXS2P (SLP-203): `view/frontend/web/css/osc-checkout-ui.css` + head entry in
  `onestepcheckout_index_index.xml` — checkout section UI alignment fixes (display-only,
  measured root causes): payment radio baseline (`vertical-align: -1px`), Order Summary
  qty stepper equal 24x24 frames (neutralize vendor absolute-positioned input), mobile
  estimated-total bar no longer clipped by Luma's `-21px` top margin, mobile ≤480px
  form fields full-width/no-float (vendor 98% + alternating floats misaligned left
  edges once the grid stacks), mobile payment list restored into section padding
  (Luma pulls it out by -15px). Same module-level CSS mechanism as BUG-2MK37V.
- BUG-2MK37V (SLP-199): `view/frontend/web/css/osc-discount-code.css` + head entry in
  `onestepcheckout_index_index.xml` — align the apply-discount-code section on the OSC
  checkout (button landed +12px below the input; input collapsed to ~30px on narrow
  columns). CSS-only, scoped under `.opc-payment-additional.discount-code`; checkout
  runs the Magento/luma scope (LL-0011) so the fix is attached at module level, same
  mechanism as BUG-NY0M3S.
- TASK-FMAN1B / DEC-TASKFMAN1B-001: module created. OSC-tuned copies of the address
  cascade components (`shipping-address-dropdown`, `billing-address-dropdown`) moved
  out of `Secomm_AddressDropdown` (generic module keeps its default-checkout copies),
  injected via `onestepcheckout_index_index.xml` (soft-coupled: runtime handle scope,
  no hard Mageplaza dependency).
- Round-3 quick fix on the copies: for VN the native City field is hidden regardless
  of cascade state (required attribute stripped while hidden, restored for non-VN),
  and the ward dropdown shows immediately — visible order Country → Province/City →
  Ward even before a region is selected; fixes the "Please fill out this field."
  bubble on the empty native City field.

### Fixed

- BUG-ER121M (SLP-199 follow-up): the discount section's Apply button dropped ~12px
  below the input whenever the required-entry validation message showed — mage/validation
  inserts `div.mage-error` inside `.control` (36px -> 60px) and the row's
  `align-items: center` centered the button against the taller control. Switched to
  `flex-start` (identical rendering in every non-error state, where control/input/button
  are all 36px). CSS-only, scoped under `.opc-payment-additional.discount-code`.

### Changed

- Round-4 quick fix on the copies (user direction 2026-09-03): VN row layout is now
  Country | Region on one row, then Ward and Postcode each on their own row
  (Company | Phone unchanged). The region field moves beside the country field
  before the ward anchor; `mp-clear` (OSC new-row marker) is added to the postcode
  wrapper for VN and removed for non-VN so the native City | Postcode pair keeps
  sharing a row once Region sits beside Country.

### Removed

- TASK-6MKF0V / DEC-TASK6MKF0V-001: the legacy sub-city tier is gone from both
  copies (user direction 2026-09-03 — project is greenfield, no sub_city data to
  protect). No sub-city select, no `GetListSubCity` GraphQL call, no hidden
  `sub_city` input, no `custom_attributes[sub_city]` /
  `extension_attributes.sub_city` sync. The cascade is Country → Region → Ward
  (city levels per the profile schema); validation and the non-VN UI state no
  longer reference the removed select, and `setupCitySubCity` /
  `initializeCitySubCityElements` are renamed to `setupCityCascade` /
  `initializeCityCascadeElements`.
