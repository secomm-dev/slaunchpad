# Changelog — Secomm_GiaoHangNhanh

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
