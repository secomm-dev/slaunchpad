# Secomm_FulfillmentCore

Offline OMS export core (SLP-30 / FFC-001). Vendor-agnostic: register exporters,
persist Magento↔external mapping, retry failed API pushes. Contains **no** Pancake
(or other OMS) HTTP, status maps, or payloads.

## Purpose

Checkout/offline shipment flows that must copy a Magento-origin order to an
external system go through this module so each OMS is a separate Magento module
that only implements `OrderExporterInterface`.

## How it works

```
sales_order_place_after
  └─ ExportOrderAfterPlaceObserver
       └─ ExportOrchestrator::exportOrder()
            ├─ skip if fulfillment_core/general/enabled = 0
            ├─ foreach ExporterPool exporters
            │    ├─ load or create secomm_fulfillment_export (origin=magento)
            │    ├─ skip if push_status=success or attempt_count >= max
            │    └─ OrderExporterInterface::export(Order)
            └─ persist pending|success|failed (last_error is a generic code, never PII)
```

Retry: cron `secomm_fulfillmentcore_retry_failed_export` (`*/10 * * * *`) reloads
pending/failed Magento-origin rows with `attempt_count < max_attempts`.

## Extension points

- **Add an OMS adapter:** implement `Api\OrderExporterInterface` and append to
  `Secomm\FulfillmentCore\Model\Export\ExporterPool` `$exporters` in the adapter
  module `etc/di.xml` (`item name` should match `getServiceCode()`).
- **Inbound mapper:** implement `FulfillmentStatusMapperInterface` and append to
  `MapperPool` `$mappers`. Unmapped → `UNKNOWN`.
- **Warehouse map (FFC-004):** `WarehouseMapResolverInterface::resolve($serviceCode, $sourceCode)`
  + `OrderFulfillmentSourceResolverInterface`. Table `secomm_fulfillment_warehouse_map`
  UNIQUE per service. Adapters own UI; core owns persistence.
- Core ships empty exporter/mapper lists. No ShippingCore / Ghtk / Ahamove sequence.

Inbound: adapters call `InboundUpdateApplier::apply(InboundUpdate)`. No Magento-origin
export mapping → no-op. Duplicate `event_id` → no second comment.

Storefront: customer order view timeline (`CONFIRMED` → `DELIVERED` points) from
`secomm_fulfillment_state` only.

## Configuration

Stores → Configuration → Secomm Extensions → Fulfillment Core:

- Enable outbound order export (default Yes)
- Max export attempts (default 5)

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --no-extensions app/code/Secomm/FulfillmentCore/Test/Unit
```

## Dependencies

Magento Sales, Store, Config, Cron, InventoryApi / InventoryCatalogApi / InventorySalesApi.
No Secomm shipping modules.

## Q1 / Q2 (ticket defaults)

- **Q1** trigger: `sales_order_place_after` (after `Order::place()`). Change via
  `etc/events.xml` if SA chooses quote-submit instead.
- **Q2** module name: `Secomm_FulfillmentCore`.
