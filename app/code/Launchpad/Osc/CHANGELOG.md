# Changelog — Launchpad_Osc

All notable changes to this project layer module are documented here.

## [Unreleased]

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
