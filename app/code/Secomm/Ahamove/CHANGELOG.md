# Changelog - Secomm_Ahamove

All notable changes to the `Secomm_Ahamove` module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.2] - 2026-08-27

### Fixed
- **Order Push State Sync & Empty Shipment Bug**: Fixed `PushAhamove` controller passing empty items array to `ShipmentFactory`, preventing `We cannot create an empty shipment` error. Properly populated items array with `$item->getQtyToShip()` for all product types and ensured order state persistence (`BUG-EFWXPA` / `SLP-120`).
- **Invalid Method Call in Plugin**: Fixed fatal call to non-existent `$order->isHolded()` in `AddPushAhamoveOrderButtonPlugin`, replaced with `$order->getState() === Order::STATE_HOLDED`.
- **Payload Item Duplication**: Updated `CreateShipment::prepareShippingData` to iterate over `$order->getAllVisibleItems()` instead of `$order->getAllItems()` to eliminate duplicate configurable child item payloads.
- **Phone Number Normalization**: Added `Helper/Data::sanitizePhoneNumber()` to strip invalid characters and normalize `+84`/`84` to `0` for Ahamove API payload compliance.

---

## [1.0.1] - 2026-08-05

### Fixed
- **Session Wipe Bug**: Fixed `ErrorMessageManager::__destruct()` calling `parent::destroy()` which was wiping active customer sessions (cart items, checkout state, customer login) on script shutdown.
- **Webhook Fatal Error**: Fixed `NewShipmentOrder::afterExecute()` calling `getStatus()` on null due to `Webhooks\Index::execute()` returning void. Added return of `$ahamoveOrderStatus` model and safe null check.
- **PHP 8 Array Property Error**: Fixed `Api::processResponse()` trying to access object property `$response->status` when `$response` is an array.
- **Staging Token Hardcode**: Fixed `Observer/RefreshToken.php` hardcoding token saves to Staging paths regardless of active environment mode.
- **Validation Errors Overwrite**: Fixed `AfterValidatePlugin::afterValidate()` replacing the entire `$result` array instead of appending validation errors.

### Added
- **Multi-Store Scope Support**:
  - Added `$storeId` parameter support to `Helper/Data` (`getConfig`, `getAPIKey`, `getToken`, `isStagingMode`, `getMobilePhoneValue`).
  - Added `store_id` isolation to rate calculation cache keys in `AhamoveAbstractCarrier` and `AhamoveShippingMethod`.
  - Added dynamic Store/Website scope saving in `Observer/RefreshToken` and Admin `Controller/Adminhtml/System/RefreshToken`.

### Optimized
- **Targeted Cache Flushing**: Refactored `Helper/Data::flushCache()` to clear only `config` cache type instead of flushing system-wide Full Page Cache (FPC) and Block HTML caches.

---

## [1.0.0] - 2024-05-15

### Added
- Initial release of `Secomm_Ahamove` module.
- Real-time rate calculation for Ahamove Standard and Express shipping methods.
- Integration with Ahamove API `/v3/orders/fee` and `/v3/orders`.
- City management CLI command `secomm_ahamove:generate:city` and DB schema.
- Webhook endpoint for order status synchronization and failed delivery seller email notification.
