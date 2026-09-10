# Changelog — Secomm_Pancake (DEPRECATED)

## 0.9.0-deprecated — 2026-09-10 (SLP-30 / MIG-001)

### Deprecated
- Module **deprecated và disabled** — chỉ còn registration stub rỗng (composer/README/registration/module.xml). Toàn bộ code đã relocate:
  - Vendor logic → **`Secomm_PancakeFunction`** (PosClient, PayloadBuilder, OrderPayloadParser, StatusMapper/Catalog, PosWarehouseCatalog).
  - Magento wiring → **`Secomm_PancakeBridge`** (config, exporter, cron/CLI/webhook, admin Warehouse/Status Mapping, data patches).
- **CHANGELOG history 0.1.0–0.3.1 kế thừa tại `Secomm_PancakeBridge/CHANGELOG.md`** (file này không lặp lại).
- **Deploy**: env đã bật module này chạy `bin/magento module:disable Secomm_Pancake && bin/magento module:enable Secomm_PancakeBridge Secomm_PancakeFunction && bin/magento setup:upgrade && bin/magento cache:flush`. Config path `pancake/*` + `service_code=pancake` + DB mapping rows giữ nguyên (BC).
