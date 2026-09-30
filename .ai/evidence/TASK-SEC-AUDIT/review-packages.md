# Review packages (r4, 2026-09-22) — KHÔNG commit; working-tree manifests

## package-1-FEAT-QA23PZ-execution-wiring

- Files:
  - `app/code/Secomm/Ghn/Model/Carrier/Ghn.php`
  - `app/code/Secomm/Ghn/Model/Rate/RealtimeRateContributor.php`
  - `app/code/Secomm/Ghn/Model/Rate/RealtimeRateContributorFactory.php`
  - `app/code/Secomm/Ghn/Model/Config.php`
  - `app/code/Secomm/Ghn/etc/adminhtml/system.xml`

- Diff stat (tracked portion):

```
app/code/Secomm/Ghn/Model/Carrier/Ghn.php    | 199 ++++++++++++++++++++-------
 app/code/Secomm/Ghn/Model/Config.php         |  25 +++-
 app/code/Secomm/Ghn/etc/adminhtml/system.xml |  20 +++
 3 files changed, 197 insertions(+), 47 deletions(-)
```
- Dependencies: ['ShippingCore execution-service contracts (committed)', 'FEAT-QA23PZ zone persistence (uncommitted, same stream)']
- Review order: REVIEW FIRST — eligibility transport applies on top
- Test command: vendor/bin/phpunit app/code/Secomm/Ghn/Test/Unit → expect 354+ tests OK
- Rollback boundary: revert stream files; ShippingCore contracts additive-only
- Blocked runtime verification: runtime quote smoke (origin_district_id unset, catalog trống)

## package-2-eligibility-transport

- Files:
  - `app/code/Secomm/ShippingCore/Api/Rate/CarrierRateDecisionRecordInterface.php`
  - `app/code/Secomm/ShippingCore/Model/Rate/CarrierRateDecisionRecord.php`
  - `app/code/Secomm/ShippingCore/Api/Rate/CarrierRateOutcomeCollectorInterface.php`
  - `app/code/Secomm/ShippingCore/Model/Rate/CarrierRateOutcomeCollector.php`
  - `app/code/Secomm/Ghn/Model/Carrier/Ghn.php (recordDecision sites)`
  - `app/code/Launchpad/MageplazaTableRate/Model/FallbackCoordinator.php`
  - `app/code/Launchpad/MageplazaTableRate/Test/Unit/Integration/*`

- Diff stat (tracked portion):

```
.../Model/FallbackCoordinator.php                  |  36 +++-
 .../Rate/CarrierRateOutcomeCollectorInterface.php  |  33 ++++
 .../Model/Rate/CarrierRateOutcomeCollector.php     | 190 +++++++++++++++++++++
 3 files changed, 255 insertions(+), 4 deletions(-)
```
- Dependencies: ['Package 1 (wiring — recordDecision có nghĩa chỉ khi carrier đã wire)']
- Review order: REVIEW SECOND
- Test command: ShippingCore + Ghn + Launchpad suites → 417+354+93 tests OK; D3 matrix 10+5 tests
- Rollback boundary: collector additive; coordinator guard branch; revert = legacy path tự tiếp quản
- Blocked runtime verification: D3 runtime E2E qua Magento HTTP channel

## package-3a-audit-batch-a

- Files:
  - `app/code/Secomm/AddressDropdown`

- Diff stat (tracked portion):

```
.../AddressDropdown/Block/Form/City/Delete.php     |   2 +-
 .../AddressDropdown/Block/Form/Region/Delete.php   |   2 +-
 .../Command/City/DeleteByIdCommand.php             |  14 +-
 .../AddressDropdown/Command/City/SaveCommand.php   |  15 +-
 .../Command/Region/DeleteByIdCommand.php           |  12 +-
 .../AddressDropdown/Command/Region/SaveCommand.php |  14 +-
 .../Controller/Adminhtml/City/Delete.php           |  10 +-
 .../Controller/Adminhtml/City/MassDelete.php       | 169 +++++-----
 .../Controller/Adminhtml/Region/Delete.php         |  10 +-
 .../Controller/Adminhtml/Region/MassDelete.php     | 190 ++++++-----
 .../AddressDropdown/CustomerData/CityData.php      |  13 +-
 app/code/Secomm/AddressDropdown/Helper/Address.php |   3 +-
 app/code/Secomm/AddressDropdown/Helper/Data.php    |  31 +-
 .../Model/Export/AddressDropdown.php               |  10 +-
 .../Model/Import/DeleteAddressImport.php           | 367 +++++++++++----------
 .../Model/Resolver/AddressLocationsGraphql.php     |  16 +-
 .../Model/Resolver/AddressSchemaGraphql.php        |  16 +-
 .../Model/Resolver/GetListCityGraphql.php          |  11 +-
 .../Plugin/Cart/LayoutProcessorPlugin.php          |   8 +-
 .../Model/Resolver/AddressLocationsGraphqlTest.php |   8 +-
 .../Model/Resolver/AddressSchemaGraphqlTest.php    |   7 +-
 .../Unit/Model/Resolver/GetListCityGraphqlTest.php |   8 +-
 app/code/Secomm/AddressDropdown/etc/acl.xml        |  13 +-
 app/code/Secomm/AddressDropdown/etc/di.xml         |   6 +
 .../Secomm/AddressDropdown/etc/frontend/di.xml     |   5 +
 .../view/adminhtml/ui_component/city_listing.xml   |   2 +-
 .../adminhtml/ui_component/country_listing.xml     | 152 ++++-----
 .../view/adminhtml/ui_component/region_listing.xml | 198 +++++------
 .../view/adminhtml/web/js/form/schema-cascade.js   |   3 +-
 .../web/js/action/billing-address-dropdown.js      |  19 +-
 .../web/js/action/shipping-address-dropdown.js     |  19 +-
 .../view/frontend/web/js/address-dropdown.js       |   8 +-
 .../web/js/model/checkout-data-resolver-mixin.js   |   8 +-
 .../model/shipping-save-processor/default-mixin.js |   8 +-
 .../frontend/web/js/view/billing-address-mixin.js  |   7 +
 .../web/js/view/cart/shipping-estimation-mixin.js  |   2 +-
 .../view/frontend/web/js/view/shipping-mixin.js    |   8 +-
 37 files changed, 807 insertions(+), 587 deletions(-)
```
- Dependencies: ['Secomm_VietNamAddress (data)']
- Review order: independent
- Test command: 96/223
- Rollback boundary: revert per-file
- Blocked runtime verification: browser POST runtime

## package-3b-audit-b1

- Files:
  - `app/code/Secomm/VietNamAddress`

- Diff stat (tracked portion):

```
app/code/Secomm/VietNamAddress/CHANGELOG.md        | 35 ++++++++++++++++
 .../Model/Import/VnDatasetReader.php               | 22 ++++++++++-
 .../VietNamAddress/Model/Scheme/VnSchemes.php      | 29 ++++++++++++--
 app/code/Secomm/VietNamAddress/README.md           | 12 ++++--
 .../ImportVnAdminPre2025To2025MappingPatch.php     |  9 +++--
 .../Test/Unit/Model/Import/VnDatasetReaderTest.php | 28 +++++++++++++
 .../Unit/Model/Import/VnDatasetValidatorTest.php   | 46 +++++++++++-----------
 .../Test/Unit/Model/Scheme/VnSchemesTest.php       |  4 +-
 .../ImportVnAdminPre2025To2025MappingPatchTest.php |  2 +-
 9 files changed, 148 insertions(+), 39 deletions(-)
```
- Dependencies: ['none']
- Review order: independent
- Test command: 192/776
- Rollback boundary: revert patch/service/manifest
- Blocked runtime verification: setup:upgrade old-DB thật

## package-3c-audit-batch-c

- Files:
  - `app/code/Launchpad/MageplazaTableRate (C1/C2/C3 files)`

- Diff stat (tracked portion):

```

```
- Dependencies: ['Mageplaza vendor (read-only)']
- Review order: independent
- Test command: 93/157
- Rollback boundary: revert plugin/persister/preference
- Blocked runtime verification: REST/GraphQL runtime E2E

## Overlap notes (exact hunks)

- `Secomm_Ghn/Model/Carrier/Ghn.php`: Package 1 (wiring skeleton) + Package 2 (recordDecision sites + helper) — cùng file, hai concern; reviewer xem theo hunk: buildExecutionRequest/collect flow = P1; recordDecision helper + 6 record sites = P2.
- `Secomm_ShippingCore/Model/Rate/CarrierRateOutcomeCollector.php`: Package 2 only (this audit) — stream changes to this file landed in 0.19.0 (committed).
- `FallbackCoordinator.php`: Package 2 (consume-first) + FEAT-QA23PZ (DESTINATION_NOT_IN_SCOPE guard, uncommitted hunk) — hai hunk riêng, review cùng package 1+2.
