# Changelog — Secomm_FulfillmentCore

## 0.4.2 — 2026-09-10 (SLP-30 / FFC-002)

### Changed
- `ExportOrchestrator` logs exporter/retry failures through `FulfillmentLogger` (per `service_code`) instead of the generic system logger.

### Added
- `InboundUpdateApplier::applyToExport()` public entry point: poll adapters can apply an update against an already-loaded mapping row when the POS payload id differs from the stored external id.

## 0.4.1 — 2026-09-10 (SLP-30 / FFC-001)

### Changed
- Cron job `secomm_fulfillmentcore_retry_failed_export` moved from group `default` to dedicated group **`secomm_pos`**, declared in `etc/cron_groups.xml`. The OS crontab must run `bin/magento cron:run --group=secomm_pos`; jobs in this group never run under `--group=default`.

## 0.4.0 — 2026-09-10 (SLP-30)

### Added
- Admin-managed status mapping table `secomm_fulfillment_status_map` (UNIQUE per `service_code` + `external_status_code`, `is_active`) with `StatusMapResolverInterface` / `StatusMapRepositoryInterface`, `StatusMapConflictException`, and `NormalizedStatus` admin config source. When an inbound update matches an **active** map, `InboundUpdateApplier` now changes the Magento order status in addition to the storefront-locale comment; **no map keeps the previous comment-only behaviour**. The normalized timeline still comes from `FulfillmentStatusMapperInterface` (code), never from this table.
- Scope mới của epic **SLP-30** — canonical record: `.ai/records/tasks/TASK-BS91A3.md` (Mode A, chờ TL review).

## 0.3.5 — 2026-09-09

### Added
- `FulfillmentLogger` writes `var/log/fulfillment/{service_code}/fulfillment.log` only when that POS module registers a log gate and its Enable log config is Yes.

## 0.3.4 — 2026-09-09

### Added
- `secomm_fulfillment_export.current_status` stores the latest raw status from the POS/OMS (`service_code` identifies which system). Pancake poll writes it from the GET order `status` field.

## 0.3.3 — 2026-09-09

### Changed
- Inbound order comment is written in the order storefront locale (`vi_VN` / `en_US`), not the cron/admin locale. Visible on the customer order history.

## 0.3.2 — 2026-09-09

### Fixed
- Outbound export listens to `sales_model_service_quote_submit_success` so the mapping row is inserted after `sales_order.entity_id` exists (admin Create Order and storefront). `sales_order_place_after` ran too early and the insert failed silently.

## 0.3.1 — 2026-09-09

### Changed
- Admin config comments under Fulfillment Core explain the master switch and where max export attempts are applied (local retry cron, not Pancake).

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
