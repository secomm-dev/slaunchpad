# SL-016 Implementation Plan — GHTK order submit qua Magento native shipping-label flow

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-016-ghtk-native-label-flow.md |

> **Mode A** · Tier 2 · Status: **Approved 2026-08-17 (user acting as SA/TL; DEC-SL016-001 accepted) — Phase C cleared**
> Audit 2026-08-17 trên Magento 2.4.8-p5 vendor code + working tree.

---

## PART 1 — ANALYSIS (Phase A)

### 1.1 Magento native shipping-label lifecycle (đã audit vendor 2.4.8-p5)

```
Admin: Sales → Order → Ship → check "Create Shipping Label" → Package popup → Submit
  ↓
Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save::execute
  ├─ shipmentLoader->load() → Shipment (CHƯA persist) → register()
  ├─ if create_shipping_label:
  │    LabelGenerator::create($shipment, $request)
  │      ├─ carrier->isShippingLabelsAvailable()? (checkbox hiện khi true)
  │      ├─ $shipment->setPackages($request->getParam('packages'))  ← admin package popup
  │      ├─ Shipping\Labels::requestToShipment($shipment)
  │      │    ├─ PRECONDITIONS (native, throw rõ ràng): admin user first/last name,
  │      │    │  general/store_information name+phone, shipping/origin street1/city/zip/country
  │      │    ├─ build Shipment\Request: shipper = admin user + store_info + origin;
  │      │    │  recipient = order shipping address; packages; storeId; baseCurrency
  │      │    └─ carrier->requestToShipment($request)
  │      │         AbstractCarrierOnline::requestToShipment — loop packages → _doShipmentRequest()
  │      │         → per-package ['tracking_number', 'label_content']
  │      ├─ response hasErrors → throw LocalizedException  ⇒ SHIPMENT KHÔNG SAVE (no false success)
  │      ├─ info yêu cầu CẢ tracking_number + label_content (PDF bytes) mới persist
  │      ├─ combineLabelsPdf (\Zend_Pdf — có sẵn vendor/magento/zend-pdf)
  │      ├─ $shipment->setShippingLabel(pdf)  ← native persist label blob
  │      └─ addTrack(Track: number/carrier/title)  ← native persist sales_shipment_track
  └─ _saveShipment($shipment)  ← CHỈ chạy khi label thành công (hoặc không có label)
```

Điểm mấu chốt đã verify:
- **API call xảy ra TRƯỚC khi shipment save** → fail = không có shipment + không có false success — native, miễn phí.
- **`label_content` PDF bytes là bắt buộc** để track + label được persist (`if (!empty(tracking_number) && !empty(label_content))`).
- **Shipment entity_id = NULL tại thời điểm submit** → partner_order_id KHÔNG thể dùng shipment_id.
- Native shipper preconditions (store info + origin đầy đủ) trùng khớp yêu cầu VN: pick_name/pick_tel có nguồn (admin user + store_information phone) — đã được native validate.
- `AbstractCarrierOnline` constructor: 16 deps (xmlElFactory, track factories, directory, stockRegistry…); abstract `_doShipmentRequest()`; `requestToShipment()` public không final (carrier có thể override — DHL precedent).

### 1.2 Current GHTK integration flow

- `Ghtk extends AbstractCarrier` (KHÔNG phải Online) — chỉ `collectRates()`; `requestToShipment()` = inherited stub trả empty DataObject (nếu ép label → LabelGenerator throw "Response info is not exist" → shipment abort — không có push ngầm).
- `isShippingLabelsAvailable()` = AbstractCarrier default **false** → checkbox Create Shipping Label **không hiện** cho GHTK hôm nay.
- GHTK Submit Order API (`POST /services/shipment/order`): **không tồn tại trong codebase** — client chỉ có `/services/shipment/fee`.
- **Không observer/plugin nào** trên shipment save (đã grep; Ahamove có `Observer/Sales/OrderShipmentSaveAfter` + `Plugin/NewShipmentOrder` — pattern bị cấm theo yêu cầu, không đụng).
- Tracking/label: chưa lưu ở đâu; COD: chưa có logic nào (SL-010 parked; COD payment = BA backlog).
- Rate ↔ shipment coupling: **không** (rate-only) — giữ nguyên khi thêm label flow.

### 1.3 Gaps against native architecture

| Gap | Giải pháp |
|---|---|
| Carrier không phải AbstractCarrierOnline | Chuyển base class + thoả 16 parent deps + `_doShipmentRequest` (qua override `requestToShipment` gọn hơn) |
| Không có label PDF | `\Zend_Pdf` generate minimal label (tracking + addresses) — `%PDF-` bytes |
| Multi-package loop ≠ GHTK single-parcel | Override `requestToShipment()`: aggregate → 1 submit → 1 info entry |
| partner_order_id không có shipment_id | `ghtk-{order_increment}-{seq}` (seq = số shipment GHTK đã có trên order + 1) |
| COD/origin chưa có | `CodAmountResolverInterface` + origin chain SL-015 (`fromShipment()`) |
| Package popup cần container type | `getContainerTypes()` lean `PACKAGE` |

### 1.4 Current COD behavior + risks

- COD: không tồn tại (đúng — chưa build). Prepaid (Mollie) là path sống hôm nay → pick_money=0.
- Risks: Q-EXT order API contract chưa verify; container popup UI cần QC thật; sync call trong admin request (timeout 5s); DEC-023 supersession cần approval.

---

## PART 2 — IMPLEMENTATION TASKS (Phase B)

### Task 1 — ShippingCore: `ShippingContextFactory::fromShipment()`
- **Goal:** context cho shipment path (AC-6; hoàn tất AC-10 SL-015).
- **Files:** `ShippingCore/Model/ShippingContextFactory.php` (+test).
- **Implementation:** storeId = shipment store, quoteId = order quote_id, sourceCode null.
- **AC:** unit test mapping; no BC break. **Risk:** thấp.

### Task 2 — GHTK COD resolver
- **Goal:** COD tách khỏi mapper (AC-7/9).
- **Files:** NEW `Ghtk/Model/OrderSubmit/CodAmountResolverInterface.php` + `DefaultCodAmountResolver.php`; `GhtkConfig::getCodMethodCodes()`; system.xml `cod_method_codes`; i18n ×2; tests.
- **Implementation:** non-COD → 0.0; COD → `max(0, base_total_due)`; partial shipment (qty shipped < ordered, hoặc order còn ship) + COD > 0 → `LocalizedException` fail-fast với message rõ.
- **AC:** prepaid→0; COD→total_due; partial+COD→throw; deposit (total_paid>0) → đúng phần còn lại. **Risk:** medium — phải không âm, không double.

### Task 3 — GHTK order payload + response mappers + weight
- **Goal:** payload build (AC-6/8/10) tách client; response defensive.
- **Files:** NEW `Model/OrderSubmit/OrderRequestMapper.php`, `OrderResponseMapper.php`, `OrderSubmitResult.php` (VO); `ShipmentWeightCalculator` +`calculateForShipment()`; tests.
- **Implementation:** input = (Shipment\Request, PickupAddress, GhtkAddress dest, weightGram, codAmount, partnerId) → payload: pick_* từ pickup (metadata priority — reuse FeeRequestMapper semantics), address/name/tel recipient từ native request + DestinationAddressResolver (order shipping address region_id+city=ward), products từ shipment items (name+qty), pick_money=codAmount, is_freeship=1, value=subtotal shipped, partner ORDER_ID, transport theo config. Response mapper: label/tracking/fee defensive (null-safe).
- **AC:** mapper tests: metadata priority, pick_money mapping, is_freeship=1, partial-safe. **Risk:** Q-EXT — mapper là nơi cô lập drift.

### Task 4 — `GhtkApiClient::submitOrder()` + LabelPdfGenerator
- **Goal:** transport POST + PDF label (AC-3/5).
- **Files:** `GhtkApiClient.php` (+method, NO auto-retry); NEW `Model/OrderSubmit/LabelPdfGenerator.php` (\Zend_Pdf A6: tracking lớn, pick/deliver addr, partner id); tests (client URL/headers/method; PDF `%PDF-` prefix + chứa tracking).
- **AC:** POST JSON + Token/X-Client-Source headers; GhtkApiException non-retryable semantics; PDF parse-able. **Risk:** medium (Zend_Pdf API usage).

### Task 5 — `OrderSubmitService` + Carrier `AbstractCarrierOnline` conversion
- **Goal:** orchestration + native wiring (AC-1/3/4/5).
- **Files:** NEW `Model/OrderSubmit/OrderSubmitService.php`; `Model/Carrier/Ghtk.php` (base class + constructor parent deps + `requestToShipment()` override + `isShippingLabelsAvailable()` + `getContainerTypes()`); tests.
- **Implementation:** service: context → origin chain → pickup (null → error string) → dest (order address qua DestinationAddressResolver; null → error) → weight → COD → partnerId → payload → client submit → response mapper → result(tracking, labelId) + snapshot comment string. Carrier `requestToShipment()`: validate packages; service submit; success → info[0] = tracking + LabelPdfGenerator; shipment comment thêm qua `$request->getOrderShipment()->addComment(...)`; fail → response errors (LabelGenerator throw → no save). Throw LocalizedException với message GHTK masked.
- **AC:** §10 tests 1-4 (no-api normal path = service chỉ được gọi từ requestToShipment; structural: không events.xml); carrier constructor DI compile pass. **Risk:** cao nhất — chạm rate constructor; mitigations: collectRates body giữ nguyên, DI compile + full test suite.

### Task 6 — Tests đầy đủ + docs + records
- **Goal:** §10 test 1-8 green; records closure-ready.
- **Files:** tests ở Tasks 2-5 + tổng run; CHANGELOG/README Ghtk (1.2.0); SL-010 ticket note (redirect → SL-016); SL-016 ticket AC check; evidence `.ai/runtime/evidence/SL-016/`.
- **AC:** phpunit suite green (84+ mới); DI compile OK; validator records OK. **Risk:** thấp.

### Sequence & gating

```
[SA/TL approve DEC-SL016-001 (supersedes DEC-023)]
→ Task 1 → 2 → 3 → 4 → 5 (test từng task) → 6
→ QC manual (sandbox): normal shipment no API; label flow submit + tracking + label PDF;
  fail API → shipment không save; partial+COD error; store info thiếu → native error
→ AI pre-review → TL code review (Tier 2)
```

QC environment cần: GHTK sandbox token, store_information đầy đủ, mapping rows, order VN test. **Không implement trước approval.**
