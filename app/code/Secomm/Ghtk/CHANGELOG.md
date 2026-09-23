# Changelog — Secomm_Ghtk

## 2.2.0 — 2026-09-15 (TASK-3HPB76 — Pickup/TestConnection operational tooling)

### Added
- `GhtkApiClient::getPickupAddresses(?int $storeId)` — GET `/services/shipment/list_pick_add`
  qua shared transport + auth headers; read-only → `RetryPolicy::safeRead` (NETWORK/SERVER_ERROR/
  TIMEOUT retry; 403/400/429 no). KHÔNG bao giờ được gọi từ rate/create/checkout paths.
- `Model/Pickup/GhtkPickupAddress` (lean VO) + `GhtkPickupList` (parser — malformed rows skip +
  count; exact-ID lookup; merchant pickup list KHÔNG phải admin master data).
- `Model/Pickup/TestConnectionResult` (CONNECTED / AUTH_FAILED / TECHNICAL_FAILURE /
  PICKUP_ID_INVALID + message/configuredPickupId/configuredPickupName/pickupCount).
- `Model/Pickup/TestConnectionService` — flow: list_pick_add → auth/technical classification →
  configured `pick_address_id` exact validation; no-id → CONNECTED với note; invalid id →
  PICKUP_ID_INVALID KHÔNG first-pickup fallback; legacy text-only config vẫn pass connection.
- `Controller/Adminhtml/Ghtk/TestConnection` refactor: fee-probe cũ thay bằng service; ACL
  `Secomm_Ghtk::config` giữ; messageManager phân biệt 6 trạng thái; không expose token/raw payload.

### Không đổi
`GhtkOriginProvider`/origin resolution · RATE/CREATE/CANCEL/tracking behavior · COD ·
ShippingCore/VietNamAddress (0 diff) · không pickup sync/persistence/selector/MSI.

### Tests
+`TestConnectionServiceTest` (15); +2 client pickup cases (safeRead retry/no-retry).
Scoped 645 tests — 0 failure trong scope; full suite 1555 — 10 failing pre-existing ngoài scope.
Live staging E2E: BLOCKED_BY_CREDENTIAL (TASK-44F7V7).

## 2.1.0 — 2026-09-14 (TASK-FNVHK5 — CANCEL lifecycle integration)

### Added
- `GhtkApiClient::cancelShipment(identifier)` — POST `/services/shipment/cancel/{id}` (rawurlencode;
  identifier = GHTK label hoặc `partner_id:{code}`); SINGLE attempt (mutation — không dùng
  safeRead retry). Method POST vs sample GET discrepancy documented — NEEDS_RUNTIME_VERIFICATION.
- `Model/Cancel/GhtkCancelResponse` (typed, 4 kinds: CANCELLED / ALREADY_CANCELLED benign /
  BUSINESS_REJECTION / TECHNICAL_FAILURE + `isSatisfied()`) + `Model/Cancel/CancelShipmentService`
  (application service — classification theo category + documented already-cancelled message
  contract; KHÔNG mutate Magento order/shipment/payment/refund; KHÔNG auto-wire Magento cancel —
  caller expectation documented trên class).
- `GhtkApiProfile::getCancelPath()`.
- Logging masked: identifier/classification/message(truncated)/log_id.

### Không đổi
KCXKVR/6YG3HP/W8SH0N/BE5YD2 behavior · TEXT_NATIVE/capability (CANCEL không cần address) · COD ·
ShippingCore/VietNamAddress (0 diff).

### Tests
+`CancelShipmentServiceTest` (14): success/already-cancelled benign/state rejection/400/403/
unknown/RATE_LIMIT/timeout/network/5xx/invalid JSON/manual-retry benign resolution/empty identifier/
partner_id variant/single-call. Scoped 620 tests — 0 failure trong scope; full suite 10 failing
pre-existing ngoài scope.

## 2.0.0 — 2026-09-14 (TASK-BE5YD2 — CREATE lifecycle reliability + ORDER_ID_EXIST recovery)

### Added — typed CREATE lifecycle (DEC-TASKBE5YD2-001)
- `Model/OrderSubmit/GhtkCreateResponse` (typed VO) + `OrderResponseMapper::parse()` thay các
  method rời: 4 kinds — CREATED / DUPLICATE_EXISTING / BUSINESS_REJECTION / MALFORMED. Identity
  normalization chung cho success (partner_id/label/tracking_id) và duplicate
  (partner_id/ghtk_label/status); parser defensive cả top-level lẫn order block (runtime shape
  NEEDS_RUNTIME_VERIFICATION — TASK-44F7V7).
- **ORDER_ID_EXIST = RECOVERED_EXISTING** khi provider partner_id khớp deterministic `order.id`
  và có ghtk_label: reuse provider identity, `OrderSubmitResult.recovered=true`, shipment comment
  "existing order recovered" — KHÔNG submit lại, KHÔNG tạo shipment thứ hai. Mismatch/missing
  partner_id/missing label → hard conflict fail-closed.
- Transport failures phân loại theo shared category: 403/400/429 → business/config failure
  non-retry; timeout/network/5xx → technical failure (message hướng dẫn retry an toàn cùng
  reference — recovery path). Single submission attempt GIỮ NGUYÊN (không blind POST retry).
- `OrderSubmitResult` += `recovered` + `providerStatus` (provider raw status — diagnostics only).

### Fixed
- success=true nhưng thiếu usable shipment identity → technical abort (trước đây có thể rơi nhánh
  label-null chung); message technical hiện hướng dẫn merchant retry an toàn.

### Không đổi
KCXKVR (payload shape/weight kg/status mapper/webhook) · 6YG3HP (per-op handoff/shared COD) ·
W8SH0N (RATE classifier) · TEXT_NATIVE/override-only · COD behavior · CANCEL/pickup (out of scope) ·
ShippingCore/VietNamAddress (0 diff).

### Tests
`OrderResponseMapperTest` rewrite (9); `OrderSubmitServiceTest` +6 scenarios (recovered/conflict/
missing-partner/missing-label/403-business/5xx-technical/malformed) + manual-retry recovery
scenario (2 attempts, same deterministic id — §33). Scoped 606 tests — 0 failure trong scope;
full suite 1516 — 10 failing pre-existing ngoài scope (Tracking/FulfillmentCore WIP).

## 1.9.0 — 2026-09-14 (TASK-W8SH0N — RATE error classification → CarrierRateOutcome v5)

### Added
- `Model/Rate/GhtkFeeResponse` + `Model/Rate/GhtkRateOutcomeFactory` — RATE response phân loại
  thành shared `CarrierRateOutcome` (v5): SUCCESS (fee hợp lệ + delivery) / UNAVAILABLE
  (success=false business rejection, delivery=false, transport CLIENT_ERROR 400/403/404,
  RATE_LIMIT 429 conservative) / TECHNICAL_FAILURE (NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE,
  malformed payload). Classification point DUY NHẤT; GHTK error_code/message không rò vào
  ShippingCore.
- `FeeResponseMapper::parse()` thay `map()`: phân biệt BUSINESS_REJECTION vs MALFORMED (trước đây
  null-for-every-error); success thiếu/non-numeric fee → MALFORMED (không nhận 0-fee sentinel),
  delivery=false không cần amount (business answer).
- `GhtkApiException` mang shared transport `category` (optional ctor param — backward compat);
  client `wrap()` truyền qua.
- `Carrier\Ghtk::collect()` log classification masked một dòng/outcome (status/reason/error_code/
  admin names); customer-facing giữ binary rate/rate-unavailable.

### Fixed
- HTTP 200 + success=false trước đây chảy vào nhánh malformed chung — giờ là UNAVAILABLE business
  (không trigger fallback); malformed technical → TECHNICAL_FAILURE (fallback contributor đúng).
- Success payload thiếu fee amount không còn tạo 0-fee rate.

### Retry
KHÔNG đổi: `RetryPolicy::safeRead` (NETWORK/SERVER_ERROR/TIMEOUT) — business/403/429/invalid-JSON
no retry (category-driven). 429 = NEEDS_PROVIDER_VERIFICATION.

### Tests
+`GhtkRateOutcomeFactoryTest` (15 — §33 matrix + real-aggregator integration: business không
contribute fallback, technical contribute, success suppress); `FeeResponseMapperTest` tái viết (8);
client/carrier tests cập nhật. Scoped 596 tests — 0 failure trong scope; full suite 1506 — 10
failing pre-existing ngoài scope (Tracking/FulfillmentCore WIP).

## 1.8.0 — 2026-09-14 (TASK-6YG3HP — structural alignment lên ShippingCore v5)

### Changed — per-operation address capability (v5)
- `Model/Address/GhtkOperationAddressCapability` (MỚI) implements
  `CarrierOperationAddressCapabilityInterface`: per-op scheme (CANDIDATE `VN_ADMIN_2025` —
  PENDING TASK-44F7V7 staging probe, single freeze seam), per-op representations =
  `[AddressRepresentation::TEXT_NAME]`, per-op textualFallback = false. `GhtkAddressCapability`
  (per-carrier, deprecated contract) ĐÃ XÓA.
- `GhtkAddressAdapter::resolve(…, string $operation)` — RATE/CREATE resolve qua
  `handoffContextForOperation` riêng; operation là explicit argument (carrier truyền RATE,
  OrderSubmitService truyền CREATE, PickupAddressResolver passthrough). Structurally sẵn sàng
  cho RATE/CREATE scheme khác nhau sau probe.
- `GhtkLegacyCapabilityShim` (MỚI, documented): DUY NHẤT reference còn lại tới deprecated
  per-carrier contract — adapt per-op capability cho 1 legacy call (ShippingCore scalar builder
  vẫn type-hint contract cũ; hard stop). Removal condition ghi trên class.

### Changed — COD ownership (architecture v4 §4.1)
- `DefaultCodAmountResolver` consume shared `CodPaymentMethodResolverInterface::isCod()`
  (config `secomm_shippingcore/cod/payment_methods`); carrier KHÔNG còn maintain danh sách COD
  methods. Provider conversion giữ nguyên (base_total_due → pick_money; partial-COD fail-fast).
- **REMOVED** `carriers/ghtk/cod_method_codes` (system.xml + config.xml + GhtkConfig getter) —
  clean migration (pre-stable). ⚠️ Merchant: set `secomm_shippingcore/cod/payment_methods`
  (vd `cashondelivery`) khi enable GHTK COD; empty = nothing is COD (ShippingCore contract).

### Không đổi
KCXKVR correctness (status mapper/webhook/CREATE shape/weight kg) · TEXT_NATIVE + override-only
table · ORDER_ID_EXIST behavior (P1) · CANCEL/pickup UI (out of scope) · LegacyRateStrategy
(không consume). ShippingCore/VietNamAddress: 0 change.

### Tests
+`GhtkOperationAddressCapabilityTest` (7); adapter test +operation-forward assert; COD resolver
test tái viết trên shared resolver (7); pickup/carrier/order-submit tests cập nhật operation.
Scoped 578 tests — 0 failure trong scope; full suite 12 failing = pre-existing ngoài scope.

## 1.7.0 — 2026-09-14 (TASK-KCXKVR — P0 API correctness theo SPIKE-A1DGPY)

### Fixed — tracking/webhook status semantics (official table api.ghtk.vn)
- `GhtkStatusMapper` viết lại theo bảng chính thức: 6 (Đã đối soát) → DELIVERED (trước đây
  DELIVERY_FAILED — sai hoàn toàn); 7 (Không lấy được hàng) → DELIVERY_FAILED (trước đây
  PICKING); 9 (Không giao được hàng) → DELIVERY_FAILED (trước đây PICKING); 11 (Đã đối soát
  công nợ trả hàng) → RETURNED (trước đây DELIVERY_FAILED); 12 (Điều phối lấy hàng) → PICKING
  (trước đây RETURNING — ngược nghĩa); 13 (Bồi hoàn) → RETURNED (raw message giữ semantics);
  **21 (Đã trả hàng) → RETURNED (trước đây thiếu → UNKNOWN)**; 4 → OUT_FOR_DELIVERY (trước đây
  IN_TRANSIT). Terminal states không bao giờ bị downgrade (6 sau 5 vẫn DELIVERED — processor sticky).
- Shipper-informational codes 123/127/128/45/49/410 → UNKNOWN có chủ ý (docs: "không phải trạng
  thái đơn hàng").

### Fixed — webhook payload
- Parser nhận CẢ `application/x-www-form-urlencoded` (official sample) và JSON; `action_time`
  (ISO 8601) → `occurredAt` (invalid/missing → null, update vẫn xử lý); identifier candidates
  += `partner_id`. Controller dùng `decode()` (payload form không còn bị bỏ sót); HTTP luôn-200
  giữ nguyên (documented: GHTK retry đúng 1 lần — reconciliation cron là safety net).

### Fixed — CREATE order request shape
- Payload theo official contract: `{"order": {...}, "products": [...]}` (trước đây flat +
  `partner_order_id` + `order` chứa products array); partner key = **`order.id`**.
- **`products[].weight` = KILOGRAM** (official docs verbatim "Product weight in kilograms") —
  conversion gram→kg tại mapper boundary; `order.total_weight` (Double kg) gửi tổng shipment
  weight; `weight_option` OMIT (default kilogram — không mixed-unit). Interaction weight_option
  ↔ products: NEEDS_RUNTIME_VERIFICATION (staging) — revert point duy nhất =
  `OrderRequestMapper::gramsToKilograms`.
- `OrderResponseMapper::trackingNumber` đọc `tracking_id` (official field) trước các fallback cũ.
- Log context key `partner_order_id` → `order_id` (giá trị = Secomm partner id an toàn).

### Không đổi (theo directive)
ShippingCore v5 alignment (per-operation capability) · COD ownership · ORDER_ID_EXIST recovery
(vẫn rejection — P1) · business-error taxonomy · CANCEL · pickup UI · TEXT_NATIVE/district.

### Tests
+`GhtkStatusMapperTest` bảng chính thức (17 cases + terminal-preservation + informational);
`WebhookPayloadParserTest` +form-urlencoded/action_time/duplicate/partner_id (15);
`OrderRequestMapperTest` viết lại theo shape mới (9 — {order,products}, order.id, kg conversion,
min 1g, pickup nested, district nullable); `OrderResponseMapperTest` +tracking_id;
`OrderSubmitServiceTest` assertion shape mới. Scoped suite 571 tests — 0 failure trong
Ghtk/ShippingCore/VietNamAddress (1 failure = Secomm_Ghn WIP song song, ngoài scope).

## 1.6.0 — 2026-09-10 (TASK-7AJ3K8 r1 / DEC-TASK7AJ3K8-002 — address mode TEXT_NATIVE)

### Changed — canonical-first (material correction của 1.5.0)
- **Address mode `TEXT_NATIVE`** (`GhtkApiProfile::getAddressMode()`): canonical Vietnamese
  names (`name_vi`, qua `VnAddressUnitProviderInterface`) là representation MẶC ĐỊNH gửi GHTK;
  bảng `secomm_ghtk_address_map` KHÔNG còn là mandatory runtime dependency (r0 lookup-first bị
  sửa — premise GHTK nhận TEXT, không có carrier ids).
- `GhtkDestinationResolver` → rename **`GhtkAddressAdapter`** — MỘT adapter cho destination
  (fee + create order) + pickup name-path; reverse bridge RÚT KHỎI rate path (canonical names
  không cần runtime ids).
- `GhtkAddressCapability::supportsTextualFallback()` → **false**: AMBIGUOUS/UNMAPPED → null
  (rate hide / submit fail-fast). KHÔNG còn best-effort guessed address (r0 behavior bỏ).
- `GhtkAddress::$isExact` semantic mới: true = override applied; false = canonical native text.

### Changed — override table re-key (schema)
- `secomm_ghtk_address_map` hạ cấp thành **exception/override table**: key canonical
  `(scheme_code, province_code, ward_code)` UNIQUE (thay `country_id/region_id/ward_id` runtime
  — khắc phục luôn D6 instability); overrides `ghtk_province/district/ward` nullable + cột
  `note`; drop `source_*` audit columns. **⚠️ `setup:upgrade` bắt buộc; rows cũ phải re-import
  CSV format mới.** Bảng rỗng = carrier hoạt động full flow (canonical-first).
- Bảng không còn runtime directory references → `Import\DirectoryReferenceGuard` + DI
  registration XOÁ (guard chỉ cần cho runtime-keyed references).

### Changed — import lifecycle thu hẹp
- Namespace `GhtkAddressMapImport` → `GhtkAddressOverrideImport`; entity/repository rename
  `GhtkAddressOverrideInterface` / `GhtkAddressOverrideRepository::findActive(scheme, province, ward)`.
- CSV header mới: `scheme_code,province_code,ward_code,ghtk_province,ghtk_district,ghtk_ward,
  is_active,note`; Validator kiểm tra QUA VietNamAddress contracts (unit provider, không raw
  SQL): scheme ∈ catalog, canonical province (region unit), canonical ward (sub-level), ward
  thuộc province, ≥1 override non-empty (cấm full-dataset duplication), no duplicate key.

### Observability
- `CANONICAL_ADDRESS_UNRESOLVED` / `GHTK_ADDRESS_INVALID` (rate-hidden reasons);
  `GHTK_OVERRIDE_APPLIED` (debug). Log chỉ chứa region_id/unit codes.

### Tests
- `GhtkAddressAdapterTest` viết lại (15): canonical name_vi default (kể cả ward name trùng
  giữa các tỉnh — region-scoped), override full/partial/district-only/disabled/empty-string,
  AMBIGUOUS/UNMAPPED → null (no guess), unit missing/name incomplete → GHTK_ADDRESS_INVALID,
  cache. ValidatorTest (9) + CsvReaderTest (6) + ImporterTest (4) theo format override.
- Backward compat giữ nguyên: legacy pickup chain + `pick_address_id` (test cũ pass), fee
  retry/create-order single-attempt (test cũ pass).

## 1.5.0 — 2026-09-10 (TASK-7AJ3K8 / DEC-TASK7AJ3K8-001 — alignment lên target architecture ShippingCore)

### Removed — private VN address resolution (DEC-FEATYA2C0W-004 migration)
- `Model\Address\WardIdBridge` — raw-SQL `directory_region_city` name→id + first-match khi
  ambiguous (vi phạm D4/D9) — THAY BỞI name-based bridge của `Secomm_VietNamAddress`
  (`VnOperationalNameResolverInterface`, AMBIGUOUS trả candidates, không pick).
- `Model\Address\BestEffortViVnResolver` — raw-SQL `directory_country_region_name` /
  `directory_region_city_name` / `vi_VN` — thay bằng canonical `name_vi` qua
  `VnAddressUnitProviderInterface`.
- `Model\Address\DestinationAddressResolver` — combine 4 trách nhiệm — tách thành pipeline:
  runtime context builder → ShippingCore handoff → Stage-2 adapter (dưới đây).

### Added
- `Model\Address\GhtkDestinationResolver` — GHTK Stage-2 ADDRESS ADAPTER: canonical handoff →
  GHTK text names. Alias `secomm_ghtk_address_map` (qua reverse bridge runtime ids) → hit =
  exact; miss = canonical `name_vi` (best-effort); AMBIGUOUS = KHÔNG gửi request (thay first-match
  cũ — intentional, fail-closed); UNMAPPED + textual fallback = province name_vi + ward text
  submit. Cache theo canonical unitCode (tag `secomm_ghtk_address_map` giữ nguyên — importer
  invalidation không đổi).
- `Model\Address\GhtkAddressCapability` + `Model\GhtkApiProfile` — capability (required scheme
  `VN_ADMIN_2025` + textual fallback true) và carrier API profile hoàn chỉnh (D3: identity
  `GHTK_2025`, scheme, fee/order/status endpoint paths).
- `Model\Tracking\GhtkTrackingFetcher` — `CarrierTrackingFetcherInterface`: tracking number →
  GHTK status API → `TrackingUpdate` (GhtkStatusMapper vẫn carrier-owned).

### Changed
- `GhtkApiClient` — transport chuyển lên shared `CarrierHttpClientInterface` +
  `RetryExecutor`/`RetryPolicy` của ShippingCore (DEC-TASK7AJ3K8-001 §1); GHTK giữ auth headers
  (Token/X-Client-Source), payload, endpoint (qua profile). Retry semantics giữ nguyên: fee/status
  GET retry NETWORK/SERVER_ERROR theo `retry_max`; create-order SINGLE attempt; 4xx/429/invalid
  JSON không retry. Public surface `GhtkApiException` (kèm `isRetryable()`) không đổi.
- `TrackingRefreshService` — thin shell (config gate + threshold); orchestration chuyển vào shared
  `TrackingReconciliationService` (DI virtual type `GhtkTrackingReconciliation`: fetcher + carrier
  code + batch). Cron + webhook path không đổi.
- `PickupAddressResolver` / `Carrier\Ghtk` / `OrderSubmitService` — đổi call sang resolver mới;
  gates/hide/throw semantics giữ nguyên. Legacy pickup chain (`carriers/ghtk/pick_*`) GIỮ NGUYÊN
  100% (all-empty → delegate; any-populated → legacy branch).

### Tests
- +Mới: `GhtkDestinationResolverTest` (10), `GhtkApiClientTest` (8), `GhtkTrackingFetcherTest` (6),
  `GhtkApiProfileTest` (4); cập nhật `TrackingRefreshServiceTest` (thin shell),
  `PickupAddressResolverTest`/`GhtkTest`/`OrderSubmitServiceTest` (resolver mới).
- Xoá: `WardIdBridgeTest`, `DestinationAddressResolverTest`.

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
