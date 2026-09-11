# Secomm_PancakeBridge

Magento wiring layer for Pancake POS integration.

## Role

`Secomm_PancakeBridge` connects Magento admin, cron, CLI, webhook, and FulfillmentCore pools to the vendor-neutral logic in `Secomm_Pancake`.

- **Pancake** — POS API client, payload builder, inbound parser, status/warehouse catalogs, status mapper.
- **PancakeBridge** (this module) — Magento config UI, ACL, routes, warehouse/status admin grids, export/inbound registration, cron poll, webhook endpoint.

## Dependencies

- `secomm/module-pancake`
- `secomm/module-fulfillment-core`

## DI wiring

- `PosApiConfigInterface` → `PancakeConfig`
- `ExporterPool` → `PancakeOrderExporter`
- `MapperPool` → `Pancake\Model\Mapping\PancakeStatusMapper`
- `FulfillmentLogger` gates → `PancakeLogConfig`
- CLI `secomm:pancake:poll` → `PollOrdersCommand`

## Note

Enable both `Secomm_Pancake` and `Secomm_PancakeBridge`. Keep the deprecated compatibility module disabled.
