# Changelog — Secomm_GiaoHangNhanh

## 1.2.0 — 2026-09-08 (BUG-JBX3H9)

### Fixed — remove unsafe hardcoded destination fallback (fail closed)
- **`AbstractDataBuilder::resolveGhnLocation()`**: mapping miss (no row / missing
  region/city identifiers / incomplete row) now throws the new
  `Model\Exception\GhnLocationMappingException` + logs a warning with the
  administrative identifiers involved (side, region_id, city_id, city) — never
  customer PII. The hardcoded fallback `to_district_id=1456, to_ward_code='21511'`
  (previously gated by `is_develop_mode`, default ON) is REMOVED — an unmappable
  destination must never silently become an unrelated GHN address.
- **`ServicesDataBuilder`**: develop-mode branch (fake `from_district=1457` /
  `to_district=1456`) removed — districts come from config + the location mapping.
- **`ShippingDetailsDataBuilder`**: develop-mode branch overriding the resolved
  origin with hardcoded `1457`/`'21715'` removed (it produced real quotes for a
  fake route); origin now always comes from `OriginProvider` + mapping.
- **`SynchronizeOrderDataBuilder`**: develop-mode branch faking the from-address
  (`'Phường 17'`/`'Quận Phú Nhuận'`/`'Hồ Chí Minh'`) removed.
- **`is_develop_mode` config removed entirely** (`etc/config.xml` default +
  `system.xml` field): after the branch removals it had zero readers, and its
  only historical behavior was faking administrative location data. Stale
  `core_config_data` rows for the path are harmless orphans.
- Failure semantics: rate path → `GHN::estimateShippingCost()` catch →
  **GHN method unavailable** for the address (no fake quote, no stack trace to
  customers); order/shipment sync → explicit failure (MQ consumer logs
  `[GHN OrderSync]` + re-throws for retry; admin direct sync shows the error).
- Unit tests (+9): mapping exists (id + name), missing mapping fails closed even
  with a stale develop-mode config value, missing identifiers, partial mapping
  (district/ward), ServicesDataBuilder mapping + fail-closed.

## 1.1.1 — 2026-09-03 (BUG-YQT1FW / SLP-138)

### Fixed
- **Shipping Fee Currency Conversion**: `Helper\Rate::convertPriceToDefaultCurrency()` now normalizes the GHN VND shipping fee to the **store base currency** (carrier-price contract) instead of the current store view's display currency, which caused a double conversion and a ~0 USD shipping fee on the EN store view. Adds a `rate > 0` guard and safe fallback to the raw fee when the rate lookup fails; rate-lookup errors are now logged instead of silently swallowed.

### Added
- **Debug logging in `Model\Carrier\GHN`**: `isDebug()` flag (`giaohangnhanh_setting/general/debug`) gates debug logs for the calculate-rate API response/fee and estimate-shipping errors.

## 1.1.0 — 2026-08-18 (FEAT-008 / AC-001)

### Fixed
- Webhook `Controller\Webhook\ShippingUpdate` no longer hardcodes the base
  carrier code (`giaohangnhanh`) when feeding the Secomm_ShippingCore tracking
  pipeline: Magento shipment tracks are stored with the service-level code
  (`giaohangnhanh_standard` / `giaohangnhanh_express` — see
  `Model\Service\OrderSyncService::GHN_SERVICE`), so updates never matched and
  GHN tracking through the shared pipeline silently no-opped. The webhook now
  tries every candidate (`CARRIER_CODE_CANDIDATES`) until the pipeline resolves
  a track, mirroring the GHTK webhook pattern.
