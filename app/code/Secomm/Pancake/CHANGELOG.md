# Changelog — Secomm_Pancake

## 0.4.0 — 2026-09-11 (SLP-30 / MIG-002)

### Changed
- Merged all Pancake POS vendor logic and unit tests from deprecated `Secomm_PancakeFunction`.
- Restored `Secomm_Pancake` as the vendor module used by `Secomm_PancakeBridge`.
- Preserved `pancake/*` configuration paths, `service_code=pancake`, and existing POS behavior.

## 0.1.0 — 2026-09-10 (SLP-30 / FNC-001)

### Added
- Vendor logic layer originally split from `Secomm_Pancake`, without a dependency on Magento wiring.
- `Model/Client/PosClient` and `PosClientException`: POS HTTP calls with redacted error hints, order listing, and warehouse listing.
- `Model/Order/PayloadBuilder`: creates Pancake order payloads from Magento orders.
- `Model/Inbound/OrderPayloadParser`: parses GET order and webhook payloads into inbound updates.
- `Model/Mapping/PancakeStatusMapper`, `PancakeStatusCatalog`, and `Model/Warehouse/PosWarehouseCatalog`.
- `Api/PosApiConfigInterface`, `Model/ServiceCode`, and unit tests for payload, parser, and status mapping.
