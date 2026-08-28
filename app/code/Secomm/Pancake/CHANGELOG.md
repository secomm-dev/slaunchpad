# Changelog — Secomm_Pancake

## 0.2.0 — 2026-08-20 (SLP-30 / PNC-004)

### Added
- Admin **Secomm → Pancake POS → Warehouse Mapping** (ACL `Secomm_Pancake::warehouse_map`);
  lists/saves only `service_code=pancake` via FulfillmentCore repository.
- `PosWarehouseCatalog` (`GET /shops/{SHOP_ID}/warehouses`).
- Create-order payload includes `warehouse_id`; unmapped MSI source → fail `warehouse_unmapped`
  (no POS POST).

## 0.1.0 — 2026-08-19 (SLP-30 / PNC-001 / PNC-002)

### Added
- POS client (`api_key` query, no key in logs), `PayloadBuilder`, `PancakeOrderExporter`
  registered on FulfillmentCore exporter pool (`service_code=pancake`).
- `PancakeStatusMapper` many-to-one (status 2 → SHIPPED; unknown → UNKNOWN).
- Poll cron (GET order for Magento-origin mapping only) + optional webhook controller.
- Admin config: shop_id, encrypted api_key, webhook secret, enable flags.
