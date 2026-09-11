# Changelog — Launchpad_Osc

All notable changes to this project layer module are documented here.

## [Unreleased]

### Added

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
