# Changelog — Secomm_Ghn

## 0.9.0-draft (2026-09-18, UNCOMMITTED — TASK-WAWNDS / GHN RATE Type-5 — PAUSED chờ v10-aligned TL prompt)

> **Trạng thái: DRAFT/PAUSED — chưa đóng; các mốc contract + estimator GHN-specific được giữ
> nguyên; phần orchestration chờ ShippingCore v10 frozen contracts.**

* **Contract re-verified (docs 2026-09-18 + sandbox probes A–I, sanitized)**: type-5 fee
  BẮT BUỘC `items[]` (root-only → 400 "Cân nặng không hợp lệ"); `quantity=N` ≠ N rows
  (616,000 vs 605,000) → serialize per-unit rows (`quantity:1`); aggregate >50kg KHÔNG bị
  chặn ở fee; **fee KHÔNG enforce 50kg/200cm** (60kg single + 210cm = 200); per-item dims
  optional ở fee; root dims type-2 THAY ĐỔI GIÁ (70,400 → 185,900) → giữ OMIT dims.
* **SANDBOX-OBSERVED hard limit MỚI: 150cm/dimension** (create probe: "Kích thước (dài) vượt
  quá mức cho phép: 150") — CONFLICT với docs 200cm; sandbox value thắng cho RATE eligibility
  (`GhnPackageLimits::MAX_DIMENSION_CM`). Weight per-parcel: UNSETTLED (CREATE probes bị
  upstream tenant-api timeout) → KHÔNG pre-reject weight; aggregate KHÔNG enforce (§8).
* **Estimator GHN-specific (PRODUCT_UNIT_AS_PACKAGE)**: `QuoteParcelEstimate`/`EstimatedPackage`
  (transient — KHÔNG persist; RATE ≠ CREATE truth), per-unit `items[]`, trusted-dims-only
  hard-limit check (missing dims ≠ carrier rejection — brief §13), Magento parent-parity item
  expansion (virtual skip, configurable parent-weight, ship-separately children, double-count
  guard), decimal qty → estimation-unavailable (không fractional parcels), weight qua
  `StoreWeightConverter` fail-closed, dimensions KHÔNG đọc từ Magento (không unit contract).
* **Reason taxonomy**: `GHN_HEAVY_PARCEL_UNSUPPORTED` retired khỏi happy path; mới:
  `GHN_RATE_ESTIMATION_UNAVAILABLE`, `GHN_RATE_INVALID_PARCEL_DATA`,
  `GHN_PACKAGE_{LENGTH,WIDTH,HEIGHT}_LIMIT_EXCEEDED` (150cm verified),
  `GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE` reserved.
* **Buffer draft (FLAG cho v10 review)**: `GhnRateAdjuster` + system.xml `rate_adjustment`
  (fixed/percent, rounding none|1000|5000, apply_to carrier_rate_only|carrier_and_fallback —
  carrier_and_fallback CHỜ v10 composition contract; GHN không tự gọi fallback) + carrier
  post-success hook; providerRate/finalRate logged riêng.
* **v10 realignment**: 0 GHN-local RateSourceMode/eligibility/zones/fallback code (grep 0);
  legacy terms (LegacyRateStrategy/DIRECT_FALLBACK/MAP_THEN_FALLBACK) = ShippingCore-only
  (NOT PRESENT trong GHN); FALLBACK_ONLY → orchestration misuse tại GHN boundary (documented);
  migration adapter point = handoff call trong `GhnRateCalculator::resolveAndQuote` (chờ v10
  frozen contract để wire "selected canonical PRE destination" input boundary).
* **v10 adaptation (RE-FROZEN contracts có code)**: system.xml blocker fix (inline option
  format — value là text content, schema VALID) + fields `rate_source_mode` /
  `address_resolution_policy` (defaults CARRIER_WITH_FALLBACK / FALLBACK, whitelist fail-closed
  theo frozen constants); carrier entry guard FALLBACK_ONLY → `GHN_RATE_SKIPPED_FALLBACK_ONLY`
  (0 mapping/0 API); calculator pass AddressResolutionPolicy verbatim vào
  `handoffContextForOperation` (4th param) — selection 100% shared selector, GHN chỉ nhận
  selected unit_code. Tests +8 v10 boundary (mode short-circuit, pipeline, pass-through,
  candidates-leak). Ghn **346/0F/0E**; cross-module **1039/0F/0E**; compile GREEN.
* **Final orchestration delta (v10 wiring completion)**: ShippingCore
  `handoffContextForOperation` += optional AddressResolutionPolicy (frozen wiring gap —
  policy param bị drop ở context entry; selector inject chưa dùng). STRICT → unresolved +
  candidates[] + no textual fallback; PICK_PRIMARY → shared selector → resolved MAPPED
  selected-unit (candidates[] — carriers không thấy lists); NO_DESIGNATED/MULTIPLE →
  fail-closed unresolved (VO guard CANONICAL_UNRESOLVED). FALLBACK/default = pre-v10 shape
  (BC-lock). GHN: FALLBACK_ONLY entry guard giữ vai trò defensive misuse-protection;
  **V10 CONTRACT GAPS mở cho TL**: CarrierEligibility/DestinationScope contracts + upstream
  orchestrator (execution-before-carrier) chưa tồn tại trong code → freeze chờ.
* **FREEZE (TASK-8MQHJX runtime orchestration complete — final delta closed)**: implement
  `RealtimeRateContributor` + `RealtimeRateContributorFactory` (GHN của seam
  `RealtimeCarrierRateContributorInterface`): nhận FINAL gated handoff từ
  `CarrierRateExecutionService`, đóng RateRequest hiện hành, output CarrierRateOutcome;
  **không lặp Stage-1** (contributor deps không chứa handoff-service — structural proof);
  calculator split `calculate()` (compat) vs `quoteWithHandoff()` (contributor path) với
  hard-limit pre-gate cả hai đường; FALLBACK_ONLY carrier guard = DEFENSIVE_ONLY (runtime owner
  = execution service step 2). Smoke: GraphQL ALL → secomm_ghn 53,900 VND thật (calculate_fee
  200) sau split. Ghn **353/0F/0E**; ShippingCore 278/0F/0E; cross-module **1131/0F/0E**;
  compile GREEN; validator 0 WAWNDS. **Secomm_Ghn v10 = FROZEN** (commit pending TL).
* Tests: +29 net (estimator/mapper 12, calculator 24 total, adjuster 5, carrier updated) —
  Ghn **342/0F/0E**. Cross-module có 1 failure EXTERNAL (VietNamAddress VnMappingReaderTest —
  stream khác vừa sửa 11:35 working-tree, không thuộc stream này).

## 0.8.0 (2026-09-16) — TASK-PWHG0V / GHN-E3 — Magento Tracking/Admin Integration + Label Boundary

* **Tracking integration (E3-A)**: `isTrackingAvailable()` → `true` (locked: chỉ sau khi
  `getTracking()` implement + runtime-proven). `Ghn::getTracking()` delegate
  `GhnTrackingResultBuilder` — 1 provider query qua E1 fetcher + CÙNG `GhnStatusMapper` (KHÔNG
  Magento-only mapping), thành công feed `ShipmentTrackingProcessor` (reconcile §11 — KHÔNG
  persistence path song song). Display: normalized label i18n + raw status ("Delivered (GHN
  status: delivered)"), expected delivery date, curated progressdetail[] (status label + time —
  KHÔNG raw payload/PII; log time từ `updated_date`). Provider not-found + transport/5xx → safe
  `Error` result (KHÔNG crash popup). KHÔNG tracking URL (§9: donhang.ghn.vn = SPA soft-404,
  không verify được). Tracking number = GHN order_code (§6).
* **Admin actions (E3-B)**: `POST /admin/secomm_ghn/shipment/cancel|returnShipment` — input chỉ
  shipment_id (provider identity resolve nội bộ §36); delegate thẳng `GhnCancelService`/
  `GhnReturnService`; message map §19 (KHÔNG raw provider response; UNKNOWN → reconcile-first
  notice, KHÔNG nút retry §20); ACL `Secomm_Ghn::ghn → shipment_actions → cancel_shipment|
  return_shipment` (`etc/acl.xml` mới); block+template trên `sales_shipment_view` với visibility
  matrix §16 từ local state (SUBMITTED + order_code + latest normalized state; hide Cancel khi
  DELIVERED/RETURNED/CANCELLED/LOST/DAMAGED; hide Return khi CANCELLED/RETURNED; provider vẫn là
  authority). Cancel reason UI = enum GHN-CO001/CO002/CO003/GHN-CANCEL-OTHER + merchant labels
  i18n (vi_VN/en_US). `ShipmentReconciler` (fetcher → processor) sau SUCCESS — non-fatal.
  KHÔNG order/shipment mutation, KHÔNG refund/restock/RMA.
* **Label audit (E3-C)** — verdict **SUPPORTED_BUT_DEFERRED**, `isShippingLabelsAvailable=false`
  BY DESIGN (locked test): GHN hiện có provider-hosted Print Order capability qua temporary
  print token + print URL flow — current docs (developer.ghn.vn, fetch 2026-09-16):
  `POST /shiip/public-api/v2/a5/gen-token` (`order_codes` max 10k all-or-nothing,
  `item_index?`) → `data.token` (~30 phút) → print URLs `/a5/public-api/printA5|print80x80|
  print52x70?token=`. KHÔNG bật native label vì: print flow chưa adapt Magento shipment-label
  contract; bật native label đổi create-trigger sang per-package `_doShipmentRequest` (xung đột
  kiến trúc GHN-D); invariant `sales_shipment.packages`/`secomm_physical` phải preserve/convert
  tường minh. `GhnEndpoints` const doc-corrected: `PRINT_ORDER` (`v2/shipping-order/print` —
  dead path, 404 khi probe với token hợp lệ; không phải flow hiện hành) → `GEN_PRINT_TOKEN`
  (`v2/a5/gen-token`, 0 consumer — future GHN Label Adapter follow-up). KHÔNG fake label (§26);
  positive print-token runtime validation DEFERRED đến khi có sandbox token hợp lệ + live order.
* **di.xml**: command registration `Magento\Framework\Console\CommandList` →
  `CommandListInterface` (console 2.4.8 instantiate interface; arg trên concrete-class name
  không merge — bug tiềm ẩn lộ khi stale `var/di`/metadata bị compile-fail xóa). CLI cancel
  SUCCESS giờ chạy E1 reconcile (parity với admin).
* **Pre-review fix pack (same-day)**: (1) `sales_shipment_view.xml` formkey template path đúng
  `Magento_Backend::admin/formkey.phtml` (trước đây `widget/formkey.phtml` — file không tồn tại →
  template-not-found crash Shipment View cho GHN shipments); layout-wiring unit test
  (XML + class/template existence, negative-proven); (2) exception boundary: builder fetch chỉ
  catch `GhnApiException` (provider failure → safe Error; programming Error/TypeError propagate),
  reconcile paths catch `\Exception` only (best-effort; webhook owns eventual truth), dead
  `statusOf` catch bỏ (`map()` non-throwing); (3) server-side cancel reason cap 255 ký tự
  (`mb_strlen`, reject không truncate); (4) `GhnActionOutcomeNotifier` (shared admin message map
  §19 — presentation only); controllers compact.
* Tests (E3 total vs pre-E3): builder 12, reconciler 6, cancel/return controllers 11, block
  visibility 12, notifier 5, layout wiring 3, carrier tracking/label 3, … — Ghn final
  **326**/0F/0E; cross-module **1012**/0F/0E. Runtime: Tracking A/E/D + layout-factory render
  (form_key PRESENT, hidden branch 0 bytes) + controller real-service run (transport-fail
  branch) + 256-char reason rejected 0 provider call — evidence `.ai/evidence/TASK-PWHG0V/`.

## 0.7.0 (2026-09-16) — TASK-4ATBC4 / GHN-E2 — Cancel + Return APIs

* **`GhnCancelService`**: POST `v2/switch-status/cancel` `{order_codes:[code], reason_code, reason?}`
  — reason enum (GHN-CO001/CO002/CO003/GHN-CANCEL-OTHER) fail-closed; per-order `result` đọc từ
  HTTP 200 (result:false ⇒ BUSINESS_REJECTED — KHÔNG bao giờ success từ HTTP 200); timeout/Remote →
  UNKNOWN_RESULT (mutation uncertain — KHÔNG blind retry, reconcile qua Order Info); 5xx →
  UNKNOWN_RESULT (applied-vs-not không kết luận được — taxonomy TASK-GKHXY1 r2); 429 →
  TECHNICAL_FAILURE (throttle = rejected TRƯỚC xử lý — definitively not-applied; tách khỏi
  ServiceUnavailable qua `ProviderRateLimitException`); auth/invalid → BUSINESS_REJECTED.
  Transport-level ambiguity (timeout/connect): client không chứng minh được before-send →
  conservative fallback UNKNOWN_RESULT, không fabricate certainty.
* **`GhnReturnService`**: POST `v2/switch-status/return` (force R2S) `order_codes[]` — per-order
  best-effort; eligible states thuộc provider (delivery_fail/storing/waiting_to_return/return);
  result:false ⇒ BUSINESS_REJECTED + provider message. Magento RMA/refund/stock KHÔNG liên quan.
* **Outcome VO**: `GhnActionOutcome` — SUCCESS/BUSINESS_REJECTED/TECHNICAL_FAILURE/UNKNOWN_RESULT
  + action + providerOrderCode + reasonCode + message (GHN-local, mirror GhnCreateOutcome).
* **CLI**: `secomm:ghn:shipment:cancel <id> <reason_code> [--reason=]` + `secomm:ghn:shipment:return
  <id>` — cùng services; cancel SUCCESS → E1 reconcile (fetcher → processor) sync CANCELLED.
  Admin buttons defer E3 (report decision).
* **Boundaries**: KHÔNG order mutation (0 setState/setStatus/cancel grep); KHÔNG tracking-state
  direct write; KHÔNG schema change (audit = log; lifecycle = E1 pipeline); provider cancel
  idempotent-positive runtime-proven (2× result:true same order — no inconsistent state).
* Tests r2 same-day delta: +4 (cancel +2: connect-failure conservative UNKNOWN, 429 → TECHNICAL;
  return +2: 5xx → UNKNOWN, 429 → TECHNICAL; translator 429 case đổi expectation sang
  `ProviderRateLimitException`). Final: Ghn **273**/0F/0E; cross-module **959**/0F/0E.

## 0.6.0 (2026-09-15) — TASK-GKHXY1 / GHN-E1 — Tracking + Webhook + Status Normalization

* **Webhook endpoint**: `POST /secomm_ghn/webhook/tracking` (frontName `secomm_ghn` — legacy `ghn`
  đã bị Secomm_GiaoHangNhanh chiếm). POST-only, CSRF-exempt in-controller (validated bằng secret
  thay thế — precedent Ghtk/Ahamove). Security model: GHN webhooks KHÔNG có signature (docs §11/D8)
  — merchant cấu hình shared secret (`carriers/secomm_ghn/webhook_secret`) làm custom header
  `X-Secomm-Ghn-Secret` trên GHN dashboard; `hash_equals` fail-closed — chưa configured hoặc sai →
  401 (GHN drop). Response JSON: 200 `{ok,matched[,error]}`; 500 internal (GHN retry backoff
  30s→12h). Không expose exception.
* **Type-aware parsing**: `WebhookPayloadParser` — chỉ `Type=switch_status` + OrderCode + Status →
  `TrackingUpdate` (webhook); `create`/`update_weight`/`update_cod`/`update_fee`/
  `update_payment_type`/`cod`/`update_partial_return` → ack 200 không xử lifecycle. Raw payload
  sanitize: chỉ giữ OrderCode/ClientOrderCode/Status/Type/Time/Reason/ReasonCode (bỏ
  ShipperName/ShipperPhone/PodURL/ShopID).
* **Status mapper**: `GhnStatusMapper implements CarrierStatusMapperInterface` — 23 documented GHN
  statuses → 13 normalized (explicit table; unknown → UNKNOWN; exception → UNKNOWN; lost→LOST,
  damage→DAMAGED — terminal theo r2; scrap→DAMAGED compat theo docs terminal list;
  delivery_fail giữ DELIVERY_FAILED riêng). MỘT mapper phục vụ cả webhook + fetcher (§30).
* **Reconciliation**: `GhnTrackingFetcher` (Order Info `v2/shipping-order/detail?order_code=` →
  cùng mapper, source api) + `TrackingRefreshService` + `Cron/RefreshTracking` + crontab 30'
  (opt-in `tracking_refresh_enabled`, staleness `tracking_refresh_threshold_hours` mặc định 6h).
* **Dedupe/ordering**: ShippingCore processor guards (duplicate same-status+code; terminal sticky
  DELIVERED/RETURNED/CANCELLED; strictly-older timestamp skip) — GHN retry idempotent, không regress.
* KHÔNG mutate Magento order business state (grep gate 0 setState/setStatus). KHÔNG flip
  `isTrackingAvailable`/`getTracking()` (E3). KHÔNG đổi RATE/CREATE/physical layer.
* **r2 (same-day, TL review)**: (1) **security wording chính xác hóa** — GHN KHÔNG sign callbacks;
  portal hỗ trợ merchant-configured custom headers; `X-Secomm-Ghn-Secret` = shared-secret auth
  (constant-time compare), KHÔNG phải provider signature verification; (2) **dedupe identity =
  OrderCode + Type + Time** — processor chấp nhận same-status-different-Time là DISTINCT occurrence
  (re-applied: row timestamp/message refresh + event re-emission); exact re-send (same Time) → no-op
  (ShippingCore `shouldApply` occurrence-aware — shared delta, Ghtk null-occurredAt behavior giữ
  nguyên); (3) **taxonomy += LOST, DAMAGED (terminal, commentable)** — lost→LOST, damage→DAMAGED,
  scrap→DAMAGED (docs terminal list), delivery_fail giữ riêng (non-terminal re-attempt);
  (4) **unsupported_event_type** — Type không phải switch_status → ack 200 tách bạch khỏi
  invalid_payload; (5) security wording chuẩn hóa trong system.xml/controller docblock.
* Tests: Ghn **273** tests 0F/0E (+45 tracking + r2 delta); cross-module **959** tests 0F/0E.
  Runtime verified trên dev store: matched/duplicate/unknown-order/invalid-secret/create-type/
  delivered + r2 matrix (distinct-occurrence re-applied, exact-resend no-op, LOST terminal sticky,
  stale-after-terminal skip, unsupported_event_type) — đầy đủ, log 0 PII/token.

## 0.5.1 (2026-09-15) — TASK-9Q5ZAK r2 — Physical Package Alignment (architecture correction)

* **ShippingCore physical facts layer (DEC-TASK9Q5ZAK-001, proposed)**: `Api/Physical/{PhysicalPackageInterface, ShipmentPhysicalDataInterface, CarrierPhysicalLimitInterface}` + VOs (grams/cm int — facts only, KHÔNG carrier semantics) + `Model/Physical/{StoreWeightConverter (moved từ Ghn), ShipmentPhysicalPersister (persist/read trên sales_shipment.packages marker `secomm_physical`), ConfiguredDefaultPackageDimensions (secomm_shippingcore/physical/* — PREFILL-only)}`. Không packaging engine (cartonization/bin-packing/auto-split OUT).
* **GHN interpretation**: `GhnPhysicalParcelInterpreter` + `GhnParcelPlan` — 1 pkg <20,000g → type 2 root (weight/dims = package); ≥20,000g HOẶC multi-package → type 5, items[] = MỖI physical package 1 item ("Package N", qty 1, exact values — GHN heavy item = physical package, không phải catalog product); root weight type-5 = Σ factual, dims = largest-by-volume. Limits per-package (50,000g / 200cm) fail-closed `INVALID_PARCEL` 0 HTTP. **Type-5 CREATE giờ SUPPORTED** (r1: auto-unsupported). Multi-package = 1 Shipment → N pkg → 1 GHN order.
* **Admin**: section "Package Information" trên new-shipment page (`extra_shipment_info`) — weight prefill Σ items + dims prefill ShippingCore defaults, editable, "+ Add Package" row clone, hiển thị limits GHN. POST `shipment[physical_packages]` = nguồn authoritative duy nhất khi fresh save; retry CLI đọc snapshot; KHÔNG có nguồn → fail-closed INVALID_PARCEL (merchant default không bao giờ tự inject).
* **Observer**: in-flight guard (persister save re-fire event — không còn double-POST); request concrete `Request\Http`. REMOVED `carriers/secomm_ghn/parcel_length|width|height` (chuyển ShippingCore prefill; 0.4.0/0.5.0 chưa release nên không migration). REMOVED `ShipmentParcelBuilder` + `Ghn Model\Unit`.
* **Sandbox r2**: type-2 (L8TWX8 122,100 VND) · **type-5 heavy single POSITIVE (L8TWAF 550,000 VND, items[0], items.price omitted accepted)** · multi-package 2 pkgs POSITIVE (L8TWAP 550,000 VND, root Σ=50,000g biên accepted) · invalid pkg 201cm → INVALID_PARCEL 0 HTTP · cả 3 order cancelled.
* Tests: cross-module **859 tests 0F/0E** (ShippingCore +physical suite; Ghn interpreter/builder/service/observer rewritten). DEC-TASK9Q5ZAK-001 (proposed) + evidence r2.

## 0.5.0 (2026-09-15) — TASK-9Q5ZAK / GHN-D — Standalone CREATE Shipment trên ShippingCore v5

* **Magento shipment integration**: observer `sales_order_shipment_save_commit_after`
  (`etc/events.xml` — post-commit, không giữ sales-transaction lock) → khi shipment dùng carrier
  `secomm_ghn` → `GhnShipmentCreationService::createForShipment()` → GHN Create Order sandbox →
  `ghn_order_code` gắn làm **native Track** (dedupe theo track_number). Observer không bao giờ
  throw — create fail chỉ để row FAILED/UNKNOWN + log; shipment save không bị ảnh hưởng.
  `isShippingLabelsAvailable()`/`isTrackingAvailable()` giữ FALSE (GHN create response không có
  PDF; label = slice sau; flip tracking = GHN-E vì `getTracking()` chưa tồn tại).
* **CREATE per-operation**: `handoffContextForOperation(context, capability, CREATE)` qua bridge
  `GhnCreateCapabilityAdapter` (pin CREATE) → destination `VN_ADMIN_2025` (source==target → EXACT)
  → Stage-2 `GhnMappingResolver` names verbatim (`hasNewAddressNames()`) → payload
  `is_new_to_address=true`, `to_district_name=""` (DEC-FEATFQWEQ3-001; sandbox + merged-ward
  "Xã Tân Thanh" verified lần 2). KHÔNG district_id/ward_code/service_id/items/from_*/return_*.
* **Idempotency (SPEC §19/§28)**: `client_order_code = GHNS{shipment entity_id}` — deterministic,
  stable retry, unique per shipment; row `secomm_ghn_shipment` ghi **PENDING TRƯỚC API call**;
  timeout/5xx/malformed → row UNKNOWN (không bao giờ auto-retry hay blind re-create); recovery =
  CLI `secomm:ghn:shipment:retry <shipment_id>` (cùng code → provider dedupe — sandbox-proven).
* **Parcel (SPEC §16)**: weight = shipment total (fallback Σ items weight×qty — vendor không
  populate total_weight) qua shared `StoreWeightConverter` (kgs/lbs fail-closed — RATE mapper
  refactor delegate, behavior giữ nguyên); dimensions = **config merchant default package**
  `carriers/secomm_ghn/parcel_length|width|height` (cm; empty/>200 → fail-closed
  `INVALID_PARCEL` — không 1×1×1, không guess); type-5 (≥20kg) → fail-closed
  `GHN_HEAVY_PARCEL_UNSUPPORTED` 0 HTTP (per-item dims chưa có contract).
* **COD/payment tách bạch (§13/§14)**: `cod_amount` KHÔNG bao giờ gửi (collection amount là
  ownership upstream — policy = slice sau); **0 payment-method inspection** trong Secomm_Ghn
  (grep gate); `payment_type_id`/`required_note` = config, validate enum fail-closed
  `INVALID_CONFIGURATION`.
* **Persistence (SPEC §20)**: bảng `secomm_ghn_shipment` (Tier-2): client_order_code UNIQUE,
  ghn_order_code, service_type_id, provider_status (PENDING|SUBMITTED|FAILED|UNKNOWN),
  provider_reason_code (service-level token), quoted/actual fee, cod_amount, expected_delivery_at.
  0 column mới trên sales_order. Không persist raw payload/PII.
* Tests: Secomm_Ghn **203 tests 0F/0E** (+25 CREATE suites); regression Ghn+Ghtk+VietNamAddress+
  ShippingCore 802+ green. Sandbox QC: create thật L8TKYG (merged ward, fee 122,100 VND, đã cancel),
  idempotency same-code → same-order (provider-level probe), INVALID_PARCEL / PROVIDER_MAPPING_
  MISSING 0 HTTP, log 0 token/PII.
* Deviation REPORT TL: TASK-9Q5ZAK mini-spec gốc yêu cầu MQ async + cancel/return — slice này
  synchronous (TL brief §24/§35 smallest valid integration); cancel/return/MQ → GHN-E/F backlog.

## 0.4.0 (2026-09-14) — TASK-FMBBSD slice 2 / GHN-C — Magento Carrier RATE wiring trên ShippingCore v5

* **Magento carrier `Model\Carrier\Ghn`** (extends `AbstractCarrierOnline`, `$_code = 'secomm_ghn'`):
  `collectRates()` = active gate → VN guard → VND-only base-currency guard (≠ VND → ẩn + warning;
  fee GHN trả VND, không convert slice này — VN-scope release; lâu dài cần conversion boundary nếu
  muốn reuse store base ≠ VND) → `GhnRateRequestMapper` → `GhnRateCalculator` → `CarrierRateOutcome`
  → `RateResult\Method` | no method. Catch-all `\Throwable` → error log → no method (estimation
  không bao giờ crash). `isTrackingAvailable()`/`isShippingLabelsAvailable()` = false (rate-only),
  `_doShipmentRequest()` throw (CREATE = slice GHN-D).
* **`processAdditionalValidation()` — parent parity trừ weight trap** (TL review r2): per-item
  `max_package_weight` chỉ chạy khi merchant thực sự cấu hình (parent cast unset → 0.0 → ẩn carrier
  với mọi giỏ hàng có item weight>0); các validation khác của parent GIỮ NGUYÊN: decimal-weight
  expansion qua stock item, zip-code gate (`isZipCodeRequired`), error rendering theo `showmethod`.
  Tests khóa behavior: unconfigured max không còn chặn; configured max vẫn enforce; zip gate giữ.
* **Per-operation capability (ShippingCore v5)**: `GhnAddressCapability` migrate sang
  `CarrierOperationAddressCapabilityInterface` — RATE → `VN_ADMIN_PRE_2025` + `[UNIT_ID]`,
  CREATE → `VN_ADMIN_2025` + `[TEXT_NAME]` (chỉ khai báo; CREATE runtime out of scope); textual
  fallback false cả hai (fail closed). `GhnRateCalculator` chuyển sang
  `handoffContextForOperation(..., RATE)`; context build qua bridge module-private
  `GhnRateCapabilityAdapter` (ShippingCore chưa có public per-op scalar builder — gap REPORT TL).
* **Heavy-parcel guard**: `service_type_id = 5` (≥ 20 kg) → UNAVAILABLE
  `GHN_HEAVY_PARCEL_UNSUPPORTED` TRƯỚC khi gọi API — type-5 de-facto cần item payload mà RATE
  không có (sandbox-verified); cấm gửi invalid request, cấm downgrade 5→2. **Backlog: GHN type-5
  RATE support (item payload) — là limitation slice, KHÔNG phải limitation vĩnh viễn.**
* **`GhnRateRequestMapper`**: RateRequest → `GhnRateQuery` — weight gram theo store
  `general/locale/weight_unit` (`kgs` ×1000, `lbs` ×453.59237); missing/lạ → **FAIL-CLOSED**
  (`LocalizedException` → UNAVAILABLE/`INVALID_CONFIGURATION`, không đoán unit — cùng số 10 có thể
  là kg hoặc lb); dimensions KHÔNG gửi ở RATE (Magento không có unit contract — omit là hợp lệ theo
  API; chỉ gửi khi có parcel contract unit-aware upstream); không COD inference; locality
  name-based (RateRequest không có dest city id).
* **Config**: field `enabled` thay bằng `active` chuẩn Magento + thêm `model/title/name/sort_order/
  showmethod/sallowspecific/specificcountry/specificerrmsg` (config.xml + system.xml). Method identity
  ổn định: carrier = method = `secomm_ghn` (không dùng service_id/service_type/district làm identity).
  ⚠ Merchant đã bật `enabled` trước 0.4.0 cần bật lại field `active` — chấp nhận được vì 0.3.x chưa
  có production adoption (module chưa từng commit; legacy `Secomm_GiaoHangNhanh` vẫn serve prod);
  nếu sau này có project nào adopt 0.3.x thì thêm data-patch migration nhẹ enabled→active.
* **`GhnLogger`** += `warning()`/`error()` (sanitize giữ nguyên). Service-level declaration
  **REMOVED theo TL review r2** — không hardcode STANDARD làm intrinsic identity; service-level
  mapping sẽ là config-driven/upstream mapping tại composition task (E-SL), không thuộc carrier.
* Tests: 174 tests green (carrier ×12 incl. validation-parity + INVALID_CONFIGURATION, mapper ×7,
  capability matrix ×7, adapter ×1; calculator per-op + heavy guard; ConfigTest bỏ `enabled`).
* Governance (TL r2, non-blocking): VND-only accepted cho release VN-scope (documented); lâu dài =
  conversion boundary thay vì hide nếu muốn reusable cross-currency.

## 0.3.1 (2026-09-10) — config placement: chuẩn Delivery Methods

* Chuyển cấu hình carrier từ tab riêng `secomm_ghn/general/*` sang section chuẩn
  **Sales → Delivery Methods** (`carriers/secomm_ghn/*`) — TL directive (GHN là shipping method).
  `Model\Config` constants, `config.xml` defaults, `system.xml` (group `secomm_ghn` dưới section
  `carriers`) đồng bộ; `acl.xml` xóa (section owned by Magento_Sales); README + ConfigTest guard
  cập nhật. Field ids giữ nguyên (`enabled/environment/api_token/...`) — chỉ vị trí + path đổi.

## 0.3.0 (2026-09-10) — TASK-TBM30R / Phase GHN-B2 — address dataset lifecycle

* **DEC-FEATFQWEQ3-002**: mapping lifecycle đổi sang versioned export → offline reviewed mapping →
  import/bootstrap. KHÔNG còn cơ chế tự tạo APPROVED mapping (exact/normalized name, extension_names,
  fuzzy, curated alias chỉ còn là candidate-generation/audit tooling).
* Bundled dataset `data/` (master/mapping CSV + manifest.json với dataset_version/counts/sha256) —
  bootstrap first-install không cần GHN API; placeholder v0.0.0-empty, refresh bằng export thật +
  offline review rồi commit lại.
* `secomm:ghn:address:export` — deterministic CSV (schema `scheme_code,level,provider_key,
  provider_id,provider_code,parent_provider_key,name,extension_names,status`; ordering ổn định;
  tên GHN verbatim; không entity_id) + manifest.
* `secomm:ghn:address:import` — master (UPsert + DISABLED-not-delete, manifest checksum) + mapping
  (CHỈ activate APPROVED; REVIEW_REQUIRED/UNRESOLVED/AMBIGUOUS skip + đếm; validate scheme pair /
  duplicate / unknown canonical / unknown provider / conflict / dangling — fail-loud toàn bộ,
  UPSERT không truncate). Mặc định import bundled `data/` = bootstrap.
* `secomm:ghn:address:suggest` — workfile đề xuất từ matcher (REVIEW_REQUIRED/AMBIGUOUS/UNRESOLVED,
  import-compatible, không bao giờ APPROVE). `MappingGenerator` (auto-write) đã XÓA; đường ghi
  APPROVED duy nhất = `MappingImporter`.
* `secomm:ghn:address:audit` — + duplicate, dangling (canonical identity hết tồn tại),
  `production_ready` (unmapped=ambiguous=invalid=duplicate=dangling=0).
* `UnitPersister` — persistence dùng chung API-sync và file-import (cùng rule parent-first,
  DISABLED-not-delete); provider sync vẫn KHÔNG đụng bảng mapping.
* Admin: read-only "Address Dataset (bundled)" info trong system config (§13 "see"; trigger
  operations CLI-only ở phase này).

## 0.2.0 (2026-09-10) — TASK-MZ2TCB / Phase GHN-B

* Schema (Tier-2): `secomm_ghn_address_unit` (dual-scheme GHN master data;
  `UNIQUE(scheme_code, provider_key)` qua `provider_key` = app-filled COALESCE — tránh NULL-unique
  MySQL 8; self-FK RESTRICT; status ACTIVE/DISABLED) + `secomm_ghn_address_mapping`
  (`UNIQUE(secomm_scheme_code, secomm_unit_code)`, FK unit CASCADE; chỉ chứa row APPROVED).
* Master-data sync (SPEC §8, CLI-only): `secomm:ghn:address:sync --scheme --dry-run`.
  Fetcher legacy (`master-data/{province,district,ward}` — fields
  ProvinceID/DistrictID/WardCode verified docs + legacy CLI) cho `GHN_ADMIN_PRE_2025`;
  fetcher v3 (`v3/master-data/province/all`, `v3/master-data/ward/all-by-province-id` — fields
  `_id/name/extension_names/parent_id/status`, paging ≤200) cho `GHN_ADMIN_2025`.
  Upsert theo `(scheme, provider_key)`; row mất khỏi snapshot → DISABLED (không DELETE);
  parent-first write; duplicate provider_key → fail loud.
* Mapping pipeline (SPEC §6/§7, offline): `MappingMatcher` — exact normalized-name match trong cùng
  parent scope (name_vi ↔ GHN `name`; extension_names KHÔNG dùng auto-approve) + curated alias CSV
  (`Files/ghn_mapping_aliases_{scheme}.csv`, versioned, parse fail-loud). AMBIGUOUS/UNMAPPED không
  bao giờ auto-approve; alias ngoài parent scope → alias_invalid. `MappingGenerator` rebuild 1
  scheme trong transaction + flush cache `secomm_ghn_mapping`.
* Audit (AC-ADDR-006): `secomm:ghn:address:audit --scheme --format=table|json` — mapped/unmapped/
  ambiguous/invalid/stale/disabled_provider_unit + coverage % per level (computed live).
* Runtime resolver: `GhnMappingResolver` — canonical code → approved mapping → `GhnLocation`
  (PRE_2025 ward walk parent chain trả đủ triple province_id+district_id+ward_code; 2025 ward trả
  verbatim names cho `is_new_to_address` — DEC-FEATFQWEQ3-001). Fail-closed
  (`GhnMappingNotFoundException`); cache chỉ cache HIT.
* `GhnApiClient::get()` mới (v3 master-data GET) + endpoints path sửa số ít theo docs
  (`master-data/province|district|ward`); refactored `request()` chung cho post/get.

## 0.1.0 (2026-09-10) — TASK-RJFTPZ / Phase GHN-A

* Skeleton module `Secomm_Ghn` theo [SPEC-FEAT-FQWEQ3](../../../.ai/specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md):
  * Config `secomm_ghn/general/*` — token encrypted backend, environment sandbox/production,
    payment_type + required_note preserve business behavior legacy, connection/request timeout
    (nợ legacy: không timeout nào được set).
  * `GhnApiClient` — điểm HTTP duy nhất: headers Token/ShopId, envelope `{code,message,data}`,
    error translation qua `GhnErrorTranslator`, **empty-body HTTP 200 → `ProviderRemoteException`**
    (quirk shop-not-found, R11), timeout → `ProviderTimeoutException`.
  * Exception taxonomy `Api/Exception/` (7 typed `Provider*` + base `GhnApiException`) — SPEC §11;
    không leak raw GHN errors; ShippingCore chỉ nhận outcome (translate ở boundary GHN-C).
  * `GhnAddressCapability` — required scheme `VN_ADMIN_PRE_2025` (rating-driven), textual fallback
    `false` (fail closed). Create path 2025-names không qua capability (DEC-FEATFQWEQ3-001).
  * `GhnSchemes` constants dual-scheme; `GhnLogger` + Handler `var/log/secomm_ghn.log`, token scrub
    unit-tested.
* Dependency: `Secomm_Ghn → Secomm_ShippingCore → Secomm_VietNamAddress`; không depend legacy
  `Secomm_GiaoHangNhanh`/`Secomm_GhnAddressMapper` (chạy song song đến GHN-F).
* Chưa có: rate, master data, mapping, shipment, webhook, MQ (các phase GHN-B..F).
