# Changelog — Secomm_FulfillmentCore

## 0.3.0 — 2026-08-20 (SLP-30 / FFC-004)

### Added
- Table `secomm_fulfillment_warehouse_map` with UNIQUE `(service_code, magento_source_code)`
  and `(service_code, external_warehouse_id)`.
- `WarehouseMapResolverInterface`, `WarehouseMapRepositoryInterface`,
  `OrderFulfillmentSourceResolverInterface` (+ MSI default/website stock fallback).
- Multi-source orders: primary/first + warning log. Unmapped resolve → null (adapter fails).

## 0.2.0 — 2026-08-19 (SLP-30 / FFC-002 / FFC-003)

### Added
- `NormalizedFulfillmentStatus`, `FulfillmentStatusMapperInterface`, `MapperPool`,
  `InboundUpdate` DTO, `InboundUpdateApplier` (Magento-origin only, idempotent
  `last_event_id`, no terminal downgrade, order comments).
- Table `secomm_fulfillment_state` (carrier/tracking on core; no ShippingCore).
- Customer Hyvä timeline ViewModel + `sales_order_view` template + vi_VN/en_US CSV.
- `OrderExporterInterface::isEnabled()` so disabled adapters skip mapping rows.

## 0.1.0 — 2026-08-19 (SLP-30 / FFC-001)

### Added
- Module scaffold: `registration.php`, `etc/module.xml` (Sales/Store/Config/Cron
  sequence only — no ShippingCore/Ghtk/Ahamove), README, CHANGELOG.
- `Api\OrderExporterInterface` + `Api\Data\ExportResultInterface` /
  `ExportResult`; empty `ExporterPool` DI map for adapter modules.
- Table `secomm_fulfillment_export` (unique magento_order_id + service_code,
  origin, push_status, attempt_count, last_error without PII).
- `ExportOrchestrator` + observer `sales_order_place_after`.
- Cron `RetryFailedExport` for pending/failed rows under max attempts.
- Admin config: enable flag + max attempts (Secomm tab).
- Unit tests for pool, orchestrator (create mapping + call exporter, retry).
