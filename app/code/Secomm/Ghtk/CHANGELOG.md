# Changelog — Secomm_Ghtk

## 1.4.0 — 2026-09-03 (TASK-Q4B98P / DEC-FEATYA2C0W-004)

### Added — VN scheme-swap reference guard
- `Model/Import/DirectoryReferenceGuard` — đăng ký bảng `secomm_ghtk_address_map` (keys `region_id` + `ward_id`) với guard extension point của `Secomm_VietNamAddress`; mỗi destructive VN scheme operation (swap/rebuild/stray purge) sẽ chặn khi bảng này vẫn tham chiếu runtime directory rows. Trước đây logic này hardcode trong `VietNamAddress/Model/Import/VnAddressSchemeImporter` — giờ bảng được khai báo bởi module sở hữu (DI argument `directoryReferenceGuards`). Behavior parity với guard cũ (cùng COUNT + cùng message, message có i18n vi_VN/en_US).
- `etc/di.xml` registration + `etc/module.xml` sequence += `Secomm_VietNamAddress` (đúng chuỗi dependency DEC-004 D1; chưa có composer.json — kế thừa trạng thái cũ).

## 1.3.0 — 2026-08-17 (SL-017 / DEC-SL017-001)

### Added — carrier tracking
- Webhook receiver `POST /ghtk/webhook/index` (CSRF-exempt, Ahamove precedent; ALWAYS HTTP 200 —
GHTK never retries indefinitely; optional `webhook_secret` URL guard — encrypted config, GHTK has
no documented HMAC). Thin controller → `WebhookPayloadParser` (defensive field alternatives,
sensitive keys stripped) → shared ShippingCore processor.
- `Model\Tracking\GhtkStatusMapper` — THE GHTK status mapping table (numeric codes → normalized;
unknown → UNKNOWN, never a failure).
- `GhtkApiClient::getOrderStatus()` (GET /services/shipment/v2/{label_id} — Q-EXT) +
`TrackingRefreshService` (stale non-terminal reconciliation through the SAME pipeline) +
`Cron\RefreshTracking` (every 30 min, no-ops unless tracking_refresh_enabled — webhook stays primary).
- Config: `webhook_secret` (obscure), `tracking_refresh_enabled` (default 0),
`tracking_refresh_threshold_hours` (default 6); i18n vi+en.
- Unit tests (+19: mapper, parser, refresh service same-path/disabled/failure-continue).


## 1.2.0 — 2026-08-17 (SL-016 / DEC-SL016-001)

### Added — Magento-native shipping-label flow
- Carrier now extends `AbstractCarrierOnline`; `isShippingLabelsAvailable()` = true,
  lean `getContainerTypes()`. The NATIVE label lifecycle is reused end-to-end
  ("Create Shipping Label" checkbox → package popup → `requestToShipment()` before
  shipment save → failure aborts the shipment — no false success). Normal
  shipments make NO GHTK API call; no observers, no outbox (supersedes DEC-023
  outbound — see DEC-SL016-001).
- `Model/OrderSubmit/OrderSubmitService` — label-triggered submission: origin via
  the SL-015 provider chain (same as rate), destination via SL-008 machinery,
  weight from admin package weights (fallback `ShipmentWeightCalculator::calculateForShipment()`),
  deterministic partner id `ghtk-{order_increment}-{seq}` (shipment-id-free), single-attempt POST
  `GhtkApiClient::submitOrder()` (no auto-retry — no duplicate-order risk).
- `Model/OrderSubmit/CodAmountResolverInterface` + `DefaultCodAmountResolver` —
  prepaid → pick_money 0; COD (`cod_method_codes` config, default
  `cashondelivery`) → `base_total_due` (deposit/partial-payment safe);
  partial shipment + outstanding COD → fail-fast LocalizedException.
  `is_freeship = 1` always in this release (Magento charged shipping at checkout
  — GHTK never collects it again at the door).
- `Model/OrderSubmit/OrderRequestMapper` / `OrderResponseMapper` / `OrderSubmitResult` —
  payload assembly isolated from the client; response mapping defensive (Q-EXT).
- `Model/OrderSubmit/LabelPdfGenerator` — internal fallback label PDF
  (\Zend_Pdf; ASCII-transliterated) so native LabelGenerator persists tracking
  + `shipping_label`; submitted snapshot (partner id, label, pick_money, weight)
  stored on the shipment comment.
- Config `cod_method_codes`; i18n vi_VN + en_US; unit tests (+26: service,
  COD resolver, mappers, label flow, structural no-observer guard, rate-path
  never submits).

### Changed
- `ShipmentWeightCalculator` gained the shipment-side `calculateForShipment()`
  (order-item `getProduct()`, not shipment-item); rate path unchanged.
- Rate path fully independent from shipment creation — using a GHTK rate never
  implies a GHTK order (pinned by test).


## 1.1.0 — 2026-08-17 (SL-015 / DEC-SL015-001)

### Changed
- **Origin resolution moved to the `Secomm_ShippingCore` contract.** Carrier flow:
  `ShippingContext` → `GhtkOriginProvider` → `PickupAddressResolver`. New default
  behaviour: when all legacy `pick_*` fields are empty, the origin falls back to
  the Magento Shipping Origin (previously: carrier inactive). Legacy `pick_*`
  values keep winning exactly as before; a half-filled legacy override still
  invalidates the pickup (strict DEC-021 — no silent warehouse switch).
- `PickupAddressResolver::resolve()` signature changed: `?int $storeId` →
  `OriginInterface` (internal consumers only: carrier, TestConnection, tests).
  Region-id-based origins are normalized to GHTK names via the same machinery as
  destinations (mapping table → WardIdBridge → best-effort vi_VN).
- `GhtkApiClient::getFee()` now takes mapped params + store scope; business
  payload assembly moved to `Model\Fee\FeeRequestMapper`. API config reads
  (token/base URL/retry) are now store-scoped per context (previously
  default-scope-only — a superset, no breaking change).
- Admin `pick_*` labels clarify the legacy-override semantics; i18n updated
  (vi_VN + en_US).
- Rate cache key derivation moved to `FeeRequestMapper::pickupIdentity()` (same
  identity content — one cold cycle of ≤10 min TTL on deploy).

### Added
- `Model\Origin\GhtkOriginProvider` (BC chain + `ghtk.pick_address_id` metadata).
- `Model\Fee\FeeRequestMapper`.
- Unit tests: carrier extension-point proof, origin provider chain, refactored
  pickup resolver, fee request mapper.
- Module README (closing the CODING_RULES documentation gap).

## 1.0.0 — 2026-07-30 (SL-008 + SL-009)

- Initial release: rate-only carrier — address mapping table + CSV import,
  destination/pickup resolvers, weight calculator, fee API client with
  timeout/retry + masked logging, rate composition, short-TTL cache, admin
  connectivity check.
