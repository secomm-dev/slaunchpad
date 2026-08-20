# Changelog — Secomm_GiaoHangNhanh

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
