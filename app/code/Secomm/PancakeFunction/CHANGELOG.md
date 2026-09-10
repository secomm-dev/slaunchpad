# Changelog — Secomm_PancakeFunction

## 0.1.0 — 2026-09-10 (SLP-30 / FNC-001)

### Added
- Initial split from monolith `Secomm_Pancake` (deprecated 2026-09-10) — vendor logic layer, không phụ thuộc Magento wiring.
- `Model/Client/PosClient` + `PosClientException`: POS HTTP (api_key query, error hints redact, list orders/warehouses).
- `Model/Order/PayloadBuilder`: create-order payload từ Magento order.
- `Model/Inbound/OrderPayloadParser`: parse GET order/webhook → InboundUpdate (status + tracking).
- `Model/Mapping/PancakeStatusMapper` (timeline normalized) + `PancakeStatusCatalog` (catalog status Pancake + Magento status gợi ý).
- `Model/Warehouse/PosWarehouseCatalog` (`GET /shops/{SHOP_ID}/warehouses`).
- `Api/PosApiConfigInterface` (Bridge implement qua preference) + `Model/ServiceCode` (`CODE=pancake`, BC).
- Unit tests namespaces mới: PayloadBuilderTest, OrderPayloadParserTest, PancakeStatusMapperTest.
