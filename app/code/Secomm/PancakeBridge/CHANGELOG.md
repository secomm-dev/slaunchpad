# Changelog — Secomm_PancakeBridge

## 0.1.0 — 2026-09-10 (SLP-30 / BRG-001)

### Added
- Magento wiring split from `Secomm_Pancake` (monolith — deprecated 2026-09-10, xem `Secomm_Pancake` stub): admin UI (Warehouse/Status Mapping), cron `secomm_pancake_poll_orders` + CLI `secomm:pancake:poll`, webhook `POST /pancake/webhook/index`, `PancakeOrderExporter`, `PancakeConfig` (+ preference `PosApiConfigInterface`), data patches seed/backfill status maps, FulfillmentCore pool registration (exporter/mapper/log gate).
- Depends on `Secomm_PancakeFunction` (POS client, payload, mapper) + `Secomm_FulfillmentCore`. Behavior giữ nguyên (relocate-only); config path `pancake/*`, `service_code=pancake`, tên CLI/bridge route BC.

---

# History (inherited from monolith Secomm_Pancake 0.1.0–0.3.1)

## 0.3.1 — 2026-09-10 (SLP-30 / PNC-002)

### Changed
- Inbound hardening: `Cron/PollUpdatedOrders` re-written — checks Enable/Poll per **order store**, unwraps multiple POS response shapes, writes `secomm_fulfillment_export.current_status`, keeps the POS numeric id when the create response only echoed `increment_id` (`rememberPosOrderId`), applies updates through `InboundUpdateApplier::applyToExport()`, and logs a per-run summary (throws when every row was skipped by config). Cron job moved to group `secomm_pos` (needs its own OS crontab line).
- Webhook `POST /pancake/webhook/index` validates `secret` with `hash_equals` (empty secret skips the check).
- `OrderPayloadParser` falls back to `order_status`, unwraps nested `order` nodes, reads tracking from `partner.extend_update[].tracking_id` / `tracking_link` / `partner_name` / `delivery_name`.
- `PosClient` classifies auth (401/403), timeout and 4xx/5xx errors with a redacted hint, logs every call through `FulfillmentLogger`, and adds `listOrders()` / `listWarehouses()`.

### Fixed
- `PancakeOrderExporter::extractOrderId()` prefers the POS numeric id and ignores values that only echo back Magento `custom_id` / `increment_id`.

## 0.3.0 — 2026-09-10 (SLP-30)

### Added
- Admin **Status Mapping** dashboard (Secomm → Pancake POS → Status Mapping): grid + form on core table `secomm_fulfillment_status_map`, hard-scoped to `service_code=pancake`; duplicate `(Pancake status code)` rows are rejected. `PancakeStatusCatalog` documents all Pancake status codes with suggested Magento statuses. Data patches `SeedPancakeStatusMaps` + `BackfillPancakeStatusMapMagentoStatus` seed default maps idempotently — **after `setup:upgrade`, inbound updates change the Magento order status by default** (delete/deactivate rows to fall back to comment-only).
- Scope mới của epic **SLP-30** — canonical record: `.ai/records/tasks/TASK-BS91A3.md` (Mode A, chờ TL review).

## 0.2.4 — 2026-09-10

### Added
- CLI `bin/magento secomm:pancake:poll` runs inbound poll without cron and prints debug to stdout (`--increment-id`, `--limit`, `--force`).

## 0.2.3 — 2026-09-09

### Added
- Admin **Enable log** (`pancake/general/enable_log`, default No). When Yes, Pancake calls `FulfillmentLogger` and files land in `var/log/fulfillment/pancake/`.

## 0.2.2 — 2026-09-09

### Fixed
- Create-order payload omits `shipping_address.country_code` and `post_code`. Magento ISO `VN` is not a Pancake geo id and POS returned `422 [country_code]: is invalid`. Country stays in `full_address` text.

## 0.2.1 — 2026-09-09

### Changed
- Admin config comments state where to copy Shop ID, API Key, Base URL, webhook secret, and poll behaviour.
- Warehouse Mapping form notices state MSI source vs Pancake warehouse data sources.

## 0.2.0 — 2026-08-20 (SLP-30 / PNC-004)

### Added
- Admin **Secomm → Pancake POS → Warehouse Mapping** (ACL `Secomm_PancakeBridge::warehouse_map`);
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
