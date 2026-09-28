# Implementation Plan — TASK-ZA10BT (Admin Shipping Zones CRUD)

| Specification | SPEC-FEAT-QA23PZ (../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md) — Full |
|---|---|
| **Work item** | TASK-ZA10BT — slice B của FEAT-QA23PZ |
| **Mode** | A |
| **Depends** | TASK-1EK2MW (repository + validator) |
| **Risk** | low — admin surface mới, pattern hiện có |

## Steps

1. **ACL** — `etc/acl.xml`: `Secomm_ShippingCore::zones` (View, sortOrder dưới config
   resource) → `Secomm_ShippingCore::zones_manage`.
2. **Menu** — `etc/adminhtml/menu.xml`: `Secomm_ShippingCore::zones` parent
   `MenuSecomm_Base::menu`, action `shippingcore/zone/index` (*điểm cần TL chốt: Secomm menu
   theo convention vs "Stores/Sales/Shipping" theo gợi ý directive — spec §8*).
3. **Routes + controllers** — `etc/adminhtml/routes.xml` frontName `secomm_shippingcore`;
   `Controller/Adminhtml/Zone/{Index,NewAction,Edit,Save,Delete,MassEnable,MassDisable,
   MassDelete}` + `Controller/Adminhtml/Zone/WardOptions` (AJAX: `?provinces=VN-01,...` →
   `[{code,label}]` theo selected provinces qua `VnAddressUnitProviderInterface::getChildren`,
   isAjax guard, ACL `::zones`). `public const ADMIN_RESOURCE` mọi controller; POST-only
   mutations.
4. **Grid** — `Ui/DataProvider/ZoneListingDataProvider` (CollectionFactory);
   `view/adminhtml/ui_component/secomm_shippingcore_zone_listing.xml` (dataSource aclResource;
   columns code/label/enabled/counts/updated_at — counts qua `JSON_LENGTH()` trong
   Collection `_initSelect`; actionsColumn; massactions enable/disable/delete).
   Grid collection virtual type đăng ký global `etc/di.xml` (pattern Secomm_Tracking —
   collections array không merge cross-scope).
5. **Form** — `Ui/DataProvider/ZoneFormDataProvider` (DataPersistor + registry);
   `view/adminhtml/ui_component/secomm_shippingcore_zone_form.xml`: code (text, disabled khi
   edit), label, enabled (select), include_province_codes (multiselect,
   `Model/Config/Source/ProvinceOptions` — level-1 active scheme), include_ward_codes +
   exclude_ward_codes (multiselect, options AJAX theo selected provinces — JS component
   `view/adminhtml/web/js/zone-ward-options.js` theo precedent `city-selector.js`, RequireJS
   wrapper vì admin Luma).
6. **Buttons + layout** — `Block/Adminhtml/Zone/Edit/{BackButton,DeleteButton,SaveButton}` +
   layout handles `secomm_shippingcore_zone_index.xml` / `_edit.xml` / `_new.xml`.
7. **i18n** — strings vào `i18n/en_US.csv` (+ `vi_VN.csv` nếu module có; BR-001 admin strings
   theo convention module).
8. **Tests** — `Test/Unit/Controller/Adminhtml/Zone/{SaveTest,DeleteTest,WardOptionsTest}`
   (pattern `Secomm_Ghn/Test/Unit/Controller/Adminhtml/Shipment/CancelTest`: POST-only,
   ACL const reflection, validation-error redirect, isAjax guard); validator acceptance cases
   nằm ở TASK-1EK2MW suite (reuse).

## Validation

`php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore"`;
`bin/magento setup:di:compile`; `bin/magento cache:flush`; manual admin smoke (create zone
HCM_INNER với province VN-79 + 2 wards, 1 exclude) — evidence screenshot/step list vào
`.ai/evidence/FEAT-QA23PZ/`.

## Risks

- Multiselect ward với 3.321 wards — AJAX constraint giữ DOM nhỏ; validation server-side là
  source of truth (JS chỉ UX).
- Menu id/ACL trùng lặp với resource config hiện có của module (`Magento_Backend::stores`
  cho section `secomm_shippingcore`) — giữ tách bạch: grid resource riêng, config resource
  không đổi.
