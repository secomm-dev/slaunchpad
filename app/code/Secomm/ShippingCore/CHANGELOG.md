# Changelog — Secomm_ShippingCore

## 0.3.0 — 2026-08-17 (SL-017 / DEC-SL017-001)

### Added — carrier tracking pipeline (shared, carrier-agnostic)
- `Api\Tracking\NormalizedTrackingStatus` (11 statuses; terminal = DELIVERED/RETURNED/CANCELLED),
`TrackingUpdateInterface`, `CarrierTrackingProcessorInterface`, `CarrierStatusMapperInterface`.
- `Model\Tracking\TrackingUpdate` immutable DTO (carrier + normalized + raw status + occurredAt + source).
- `Model\Tracking\ShipmentTrackingProcessor` — THE single pipeline: find track → duplicate no-op →
sticky-terminal + timestamp out-of-order guards (DELIVERY_FAILED→IN_TRANSIT reattempts allowed) →
persist state → native Track.description update → shipment comment on key transitions →
domain events (`secomm_shipping_tracking_updated` + delivered/returned/delivery_failed).
Never mutates Magento order state; never throws for ordinary conditions.
- `secomm_carrier_tracking_state` table (db_schema + whitelist; UNIQUE carrier_code+tracking_number).
- Unit tests (+15: processor ordering/idempotency matrix).


## 0.2.0 — 2026-08-17 (SL-016 / DEC-SL016-001)

### Added
- `ShippingContextFactory::fromShipment()` — the label-submit flow builds the
  same scalar context as the rate path, so both flows resolve the origin
  through one `OriginProviderInterface` (completes SL-015 AC-10).
- `etc/module.xml` sequences `Magento_Sales`.
- Factory unit tests for the shipment path.


## 0.1.0 — 2026-08-17 (SL-015 / DEC-SL015-001)

### Added
- `Api\ShippingContextInterface` + immutable `Model\ShippingContext` (scalar DTO:
  storeId, websiteId, carrierCode, quoteId, sourceCode).
- `Api\OriginInterface` + immutable `Model\Origin` (sourceCode, countryId, regionId,
  province, nullable district, ward, street, postcode, telephone, contactName,
  dotted-key carrier metadata).
- `Api\OriginProviderInterface` — the stable origin extension contract
  (`resolve(ShippingContextInterface): OriginInterface`).
- `Model\ShippingContextFactory` — `fromRateRequest()` + generic `create()`.
- `Model\OriginProvider\ShippingOriginProvider` — default provider normalizing the
  Magento Shipping Origin (`shipping/origin/*`, SCOPE_STORE); district/telephone/
  contactName null by design; never decides usability.
- `etc/di.xml` preferences (provider + DTOs).
- Unit tests: provider (6 cases), factory (3), origin metadata (1).
