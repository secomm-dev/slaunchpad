# Secomm_ShippingCore

Shared shipping contracts for all Secomm carriers (SL-015 / DEC-SL015-001). Small,
generic, carrier-agnostic — contains **no** GHTK/GHN/Ahamove business logic and
depends on no Secomm carrier.

## Purpose

Every Secomm shipping carrier consumes its **runtime shipping origin** (where a
shipment physically leaves from) through one stable extension contract, so a
future fulfillment module (`Secomm_ShippingFulfillment`: MSI-source-based origin,
multi-store routing) can change *where shipments originate from* without touching
any carrier.

## How it works

```
Carrier (e.g. Secomm_Ghtk)
  └─ ShippingContextInterface            # scalar snapshot: storeId/websiteId/carrierCode/quoteId/sourceCode
       └─ OriginProviderInterface        # THE extension point
            → resolve(context): OriginInterface
                 default preference: ShippingOriginProvider (Magento Shipping Origin,
                 store-scoped by context; ward = native city, regionId preserved)
```

- `Api\ShippingContextInterface` + `Model\ShippingContext` — immutable scalar DTO;
  `Model\ShippingContextFactory::fromRateRequest()` for the rate path (the future
  shipment path builds the same context so rate and submit share one origin).
- `Api\OriginInterface` + `Model\Origin` — immutable origin VO: sourceCode,
  countryId, regionId, province, **district (nullable — VN Launchpad model)**,
  ward, street, postcode, telephone, contactName + generic carrier metadata via
  dotted keys (`ghtk.pick_address_id`, `ghn.shop_id`) — no carrier fields are
  hard-coded in the shared DTO.
- `Api\OriginProviderInterface` + `Model\OriginProvider\ShippingOriginProvider` —
  default provider; returns a data snapshot, never decides usability
  (carrier-side policy, e.g. GHTK's strict pickup gate DEC-021).

## Extension points

- **Replace:** DI preference on `OriginProviderInterface` (see `etc/di.xml`).
- **Decorate:** plugin on the provider.
- No observers mutate request payloads; no Magento core rewrites/preferences.

## Tests

`Test/Unit` — provider normalization (country/province/ward/street/postcode,
nullable district/telephone, blank-string normalization, store scoping), factory
mapping, metadata behavior. Run:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --testsuite Magento_Unit_Tests_App_Code --filter 'Secomm\\ShippingCore'
```

## Dependencies

Magento Framework / Store / Directory / Shipping only. Loaded by
`Secomm_Ghtk` (module.xml sequence).

## Carrier tracking (SL-017 / DEC-SL017-001)

Shared tracking pipeline — webhook receivers, Tracking API fetchers and
reconciliation crons all build a `TrackingUpdateInterface` (carrier status +
mapper-normalized status + raw fields) and feed the ONE
`CarrierTrackingProcessorInterface` (`Model\Tracking\ShipmentTrackingProcessor`):

- find Magento track by carrier_code + track_number;
- duplicate no-op; sticky terminal (DELIVERED/RETURNED/CANCELLED never
  downgraded); timestamp-priority out-of-order guard; reattempts
  (DELIVERY_FAILED → IN_TRANSIT) allowed;
- persist `secomm_carrier_tracking_state` (normalized + raw side by side);
- native visibility: Track.description + shipment comments on key transitions;
- domain events `secomm_shipping_tracking_updated`,
  `secomm_shipment_carrier_{delivered,returned,delivery_failed}`.

Never mutates Magento order state (carrier shipment state ≠ order lifecycle).
Carrier modules only implement a `CarrierStatusMapperInterface` + webhook/API
parser — no carrier status codes live here.
