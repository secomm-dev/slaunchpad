# Implementation Plan — TASK-1EK2MW (Zone persistence + repository + config reader)

| Specification | SPEC-FEAT-QA23PZ (../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md) — Full |
|---|---|
| **Work item** | TASK-1EK2MW — slice A của FEAT-QA23PZ |
| **Mode** | A (DB schema Tier-2 — chờ TL approve trước khi implement) |
| **Depends** | None (slice đầu) |
| **Risk** | medium — schema mới + DI preference thay registry |

## Steps

1. **Schema** — `etc/db_schema.xml` += `secomm_shipping_zone` (zone_id PK identity, code
   varchar(64) unique NN, label varchar(255) NN, enabled smallint NN default 1,
   include_province_codes / include_ward_codes / exclude_ward_codes json NN,
   created_at/updated_at timestamp); `db_schema_whitelist.json` += entry.
2. **Model layer** — `Model/Zone` (AbstractModel + typed getters/setters, JSON
   encode/decode qua ResourceModel `_beforeSave`/`_afterLoad`) +
   `Model/ResourceModel/Zone` + `Model/ResourceModel/Zone/Collection`.
3. **Repository** — `Api/Address/CanonicalZoneRepositoryInterface` +
   `Model/Zone/ZoneRepository` (getByCode → domain VO `CanonicalZone`; getEnabledByCodes
   thứ tự input; save/delete + cache clean `secomm_shippingcore_zones`; save chạy Validator).
   Domain VO tái dùng `Model/Address/CanonicalZone` (không model leak ra domain).
4. **Validator** — `Model/Zone/Validator` (§4 SPEC): code normalize + unique; province/ward
   tồn tại active scheme qua `VnAddressUnitProviderInterface`; include-ward membership khi có
   province constraint; exclude chỉ cần tồn tại. Fail = `LocalizedException` (message chi tiết
   từng code sai).
5. **Persistent registry** — `Model/Address/PersistentCanonicalZoneRegistry` implements
   `CanonicalZoneRegistryInterface`; constructor nhận DI `canonicalZones` array (như cũ) +
   repository + cache; lazy-load; precedence persisted-wins; duplicate nội bộ từng nguồn
   fail-fast `LogicException`. `etc/di.xml`: preference chuyển sang registry mới.
6. **Cache type** — `etc/cache.xml` += `secomm_shippingcore_zones`.
7. **Config reader** — `Api/Config/CarrierDestinationScopeConfigInterface` +
   `Model/Config/CarrierDestinationScopeConfig` (ScopeConfig `carriers/<code>/*`, enum
   fail-closed ALL + warning, allowed codes trim/dedupe + diagnostics unknown/disabled qua
   registry + Psr LoggerInterface). DI preference.
8. **Shared reason** — `Api/Failure/ShippingFailureReason::DESTINATION_NOT_IN_SCOPE`
   (additive; KHÔNG đụng policy defaults).
9. **Tests** — `Test/Unit/Model/Address/PersistentCanonicalZoneRegistryTest`,
   `Test/Unit/Model/Zone/{ZoneRepositoryTest,ValidatorTest}`,
   `Test/Unit/Model/Config/CarrierDestinationScopeConfigTest` (case: parse multiselect/array,
   missing → ALL/[], bad enum → ALL+warning, unknown/disabled diagnostics, SELECTED_ZONES
   empty codes passthrough).

## Validation

`php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore"`;
`bin/magento setup:upgrade` (local DB — Tier-2 approved); `bin/magento setup:di:compile`;
`bin/project-ai-validate --check-records --check-specs`.

## Risks

- DI preference thay `CanonicalZoneRegistry` — mọi consumer cũ (evaluator) nhận registry mới
  cùng interface ⇒ zero API change; regression bằng full ShippingCore suite.
- JSON column: Magento declarative `xsi:type="json"` — verify MySQL 8 + whitelist format.
