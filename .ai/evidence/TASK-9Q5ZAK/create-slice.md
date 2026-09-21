# Evidence — TASK-9Q5ZAK GHN-D: Standalone CREATE Shipment (sanitized)

> Date: 2026-09-15 · Environment: local dev WSL, store `fashion_en`, GHN sandbox shop 200537 (token masked)
> Scope: `app/code/Secomm/Ghn/**` only (ShippingCore/VietNamAddress/AddressDropdown READ-ONLY)

## 1. Deliverables

| File | Type |
|---|---|
| `Observer/GhnShipmentCreateObserver.php` + `etc/events.xml` | new — `sales_order_shipment_save_commit_after` trigger (contained, idempotency-first) |
| `Model/Shipment/GhnShipmentCreationService.php` | new — CREATE domain flow (handoff CREATE → Stage-2 verbatim names → POST → outcome) |
| `Model/Shipment/GhnCreateRequestBuilder.php` | new — pure payload builder theo contract matrix (§5/§15) |
| `Model/Shipment/GhnCreateOutcome.php` + `GhnCreateValidationException.php` | new — normalized outcome + reason-token validation failure |
| `Model/Shipment/ShipmentParcelBuilder.php` | new — weight fallback Σ items + config dims fail-closed |
| `Model/Shipment/GhnShipmentRepository.php` | new — PENDING-first persistence, markSubmitted/markNotSubmitted |
| `Model/Shipment/ShipmentTrackAttacher.php` | new — native Track (dedupe theo track_number) |
| `Model/Capability/GhnCreateCapabilityAdapter.php` | new — legacy-shape bridge pin CREATE |
| `Model/Unit/StoreWeightConverter.php` | new — shared kgs/lbs→gram fail-closed (RATE mapper delegate) |
| `Console/Command/RetryShipmentCommand.php` | new — `secomm:ghn:shipment:retry` (§28 recovery) |
| `etc/db_schema.xml` + whitelist | `secomm_ghn_shipment` (client_order_code UNIQUE; fee decimal; provider_status enum) |
| `Model/Config.php` + config/system/i18n | `parcel_length/width/height` (cm, empty = fail-closed) |
| Tests | 5 suite mới (+mapper ctor adjust) — 203 tests Ghn scoped |

## 2. Sandbox runtime cases (sanitized)

| Case | Result | Evidence |
|---|---|---|
| A — create thường (merged ward) | **PASS** | Order ship `secomm_ghn`, address ward **"Kỳ Lừa"** (2025 canonical, merged ward: Kỳ Lừa MERGED_INTO Tân Thanh) → shipment #10 qua REAL `ShipmentRepository::save` → observer fired → row PENDING-first → POST create → **order_code `L8TKYG`, fee 122,100 VND**, `service_type_id=2`, `is_new_to_address=true`, `to_ward_name="Xã Tân Thanh"` (Stage-2 verbatim), `to_district_name=""`. = **DEC-FEATFQWEQ3-001 gate #4 evidence** (create chấp nhận + định tuyến đúng ward đã merge) |
| B — provider idempotency | **PASS** | Resend CÙNG `client_order_code=GHNS10` (cùng payload path) → GHN trả **cùng `L8TKYG`**, 0 duplicate ("IDEMPOTENT: same order returned"). Retry CLI: SUBMITTED short-circuit → không gọi API + track attach |
| C — dims unset | **PASS** | `parcel_length=""` → shipment #11 → row FAILED `INVALID_PARCEL`, **0 GHN HTTP** (log: chỉ dòng unavailable; 0 `GHN call`) |
| D — mapping missing | **PASS** | Flip mapping VNA25-4EA3ADA7E1 → REVIEW_REQUIRED (SQL, đã restore APPROVED) → shipment #12 → FAILED `PROVIDER_MAPPING_MISSING`, 0 HTTP. ≠ CANONICAL_UNRESOLVED (§23 boundary) |
| E — COD absent | **PASS** | Payload KHÔNG có `cod_amount`/`insurance_value`/`order_value` (builder unit test assertArrayNotHasKey toàn bộ + runtime payload) — 0 payment-method inspection (grep gate = 0) |
| Cleanup | ✓ | `L8TKYG` cancelled per-order (`result:true, message:OK`, reason GHN-CO003) |

## 3. Magento runtime chain (verified)

```text
Admin shipment save (order 15 retrofitted carrier secomm_ghn, address "Kỳ Lừa"/1211)
  → ShipmentRepository::save → commit
  → sales_order_shipment_save_commit_after → GhnShipmentCreateObserver
      [carrier prefix secomm_ghn_ (RAW string) · entity_id > 0 · SUBMITTED-row guard]
  → GhnShipmentCreationService::createForShipment(shipment#10)
      → insertPending GHNS10 (PENDING anchor trước API)
      → ShipmentParcelBuilder (1.5kg → 1500g; dims config 30/20/10)
      → handoffContextForOperation(CREATE) → VNA25-4EA3ADA7E1 (Secomm-only)
      → GhnMappingResolver → to_ward_name "Xã Tân Thanh" / to_province_name "Lạng Sơn"
      → POST v2/shipping-order/create → SUCCESS 122,100 VND → markSubmitted
      → ShipmentTrackAttacher → sales_shipment_track (secomm_ghn : L8TKYG)
```

Failure case (phone fixture sai → `PHONE_INVALID` HTTP 400): row FAILED + log — shipment Magento
NGUYÊN VẸN, không crash; sửa fixture → `secomm:ghn:shipment:retry 10` → SUCCESS (cùng code).

## 4. Regression + integrity

- Secomm_Ghn scoped: **203 tests / 0F / 0E / 0S** (+25 CREATE tests; RATE giữ nguyên green)
- Full Secomm suite: chỉ Tracking (8) + FulfillmentCore (2) baseline pre-existing; GHN/Ghtk/VietNamAddress/ShippingCore sạch
- `setup:di:compile` + `setup:upgrade` OK (bảng `secomm_ghn_shipment` 16 cột đúng manifest)
- Grep gates: Mageplaza/TableRate/VietMap/GiaoHangNhanh/Ghtk = 0 · `service_id` = 0 · `getPaymentMethod` = 0
- Log audit: 0 token/PII (chỉ client_order_code/order_code/status/reason/exception)
- Mapping/dataset: 0 đổi (flip QC đã restore APPROVED; audit production_ready không đổi)

## 5. Deviations REPORT TL

1. **Scope narrowing**: cancel/return + MQ async của mini-spec gốc → backlog GHN-E/F (TL brief §38; §52.4 MQ = ShippingCore slice riêng). CREATE chạy synchronous qua commit_after observer.
2. **COD amount**: không gửi `cod_amount` (policy upstream — user-approved §13).
3. **Type-5**: fail-closed `GHN_HEAVY_PARCEL_UNSUPPORTED` (per-item dims chưa có contract) — backlog.
4. **provider_reason_code** = service-level token (ShippingFailureReason / INVALID_*), không phải raw GHN code (client không expose).

---

# r2 — Physical Package Alignment (2026-09-15, sandbox-verified)

## Deliverables (delta)

| Layer | File | Content |
|---|---|---|
| ShippingCore | `Api/Physical/PhysicalPackageInterface` + `ShipmentPhysicalDataInterface` + `CarrierPhysicalLimitInterface`; `Model/Physical/PhysicalPackage` + `ShipmentPhysicalData` + `StoreWeightConverter` (moved) + `ShipmentPhysicalPersister` + `ConfiguredDefaultPackageDimensions` | carrier-neutral facts (grams/cm int) + `sales_shipment.packages` marker `secomm_physical` storage + prefill-only defaults config `secomm_shippingcore/physical/*` |
| GHN | `Model/Shipment/GhnPhysicalParcelInterpreter` + `GhnParcelPlan` + `GhnPhysicalLimit` | type-2 root / type-5 items[] interpretation + limits fail-closed |
| GHN admin | `Block/Adminhtml/Shipment/PackageInformation` + `ViewModel/PackageInformationViewModel` + layout `adminhtml_order_shipment_new` + phtml | "Package Information" editable rows (prefill Σ-items weight + ShippingCore dims) + "+ Add Package" + GHN limits hiển thị |
| GHN service/observer | physical-source resolution (POST → snapshot → fail-closed); in-flight guard; HttpRequest concrete | REQUIRES confirmed physical data — merchant defaults không bao giờ auto-inject |

## Sandbox r2 (sanitized — cả 3 order đã cancel)

| Case | Payload | Result |
|---|---|---|
| A type-2 | shipment#11, 1 pkg [4.5kg, 40/30/20] | **SUCCESS L8TWX8**, fee 122,100 VND, service_type_id=2, root = package |
| B type-5 heavy single | shipment#12, 1 pkg [45kg, 60/50/40] | **SUCCESS L8TWAF**, fee 550,000 VND, service_type_id=5, items[0] exact — **positive multi-item shape + items.price omitted ACCEPTED** |
| C multi-package | shipment#13, 2 pkgs [30kg 50/40/30] + [10kg 40/30/20] | **SUCCESS L8TWAP**, fee 550,000 VND, type 5, items ×2 exact, root weight Σ = 50,000g (biên — accepted) |
| D invalid | package side 201cm | **FAILED INVALID_PARCEL, 0 GHN HTTP** (per-side limit fail-closed) |

## Retained from r1 (không regress)

CREATE → VN_ADMIN_2025 TEXT_NAME handoff · Stage-2 verbatim names · `is_new_to_address=true` + district rỗng · payment_type/required_note config · KHÔNG cod/payment inspection · GHNS{id} idempotency (PENDING-first, SUBMITTED short-circuit, UNKNOWN uncertain) · commit_after observer (in-flight guard mới chống re-fire double-create) · Track attach · safe logging · retry CLI (snapshot path) · KHÔNG service_id/fallback/VietMap/Ghtk.

## RATE decision (§20/§21)

**Option A**: type-5 RATE giữ backlog — checkout chưa có confirmed physical package data; không invent dims tại checkout. CREATE dùng confirmed warehouse facts — hai stage khác nhau chấp nhận khác behavior (documented).

---

# r3 — Type-5 root payload correction + persistence audit (2026-09-15, sandbox-verified)

## Provider probes (shipment#14, sanitized — order cancelled)

| Probe | Payload | Result |
|---|---|---|
| P0 items-only (no root weight/dims) | items[2×30kg] | **REJECTED** HTTP 400 — `ShiipCreate.Weight failed on the 'required' tag` ⇒ root weight MANDATORY |
| P1 root Σ60,000g + dims + items | weight=60000, dims 50/40/30 | **ACCEPTED** L8TT4B, fee 550,000 — root Σ >50,000g OK with items[]; cancelled |
| P2 root weight=60000 only (NO dims) + items | weight only | **ACCEPTED** L8TT43, fee 550,000 — root dims NOT required for type 5; cancelled |
| Final r3 payload (service) | weight=60000 (Σ) + items[2], NO dims | **SUCCESS L8TTRG** 550,000 VND via `GhnShipmentCreationService` — submitted log; cancelled |

## r3 semantics (final)

- type 2: root weight/length/width/height = package; content required; items absent
- type 5: root `weight` = factual Σ (MANDATORY — verified); root dims OMITTED (verified optional);
  items[] = physical packages

## Persistence audit (§13/§14 — vendor code authority)

- `sales_shipment.packages`: text column, `_serializableFields = ['packages' => [[], []]]` → JSON
  encode/decode by sales resource (`module-sales/Model/ResourceModel/Order/Shipment.php:33`).
- Core writers: ONLY `Magento\Shipping\Model\Shipping\LabelGenerator::create()` line 79
  (`setPackages($request->getParam('packages'))`) — runs ONLY when `create_shipping_label` POSTed
  AND carrier `isShippingLabelsAvailable()` = true. GHN = FALSE ⇒ 0 collision today.
- Readers: label flow (`Labels.php:159`), package-split (`Shipping.php:324`), packaging popup
  render (`Block/Adminhtml/Order/Packaging.php:234`, `Pdf/Packaging.php:161`) — all gated the same.
- **Verdict: COMPATIBLE_WITH_CONVERSION (B)** — marker `secomm_physical` + popup numeric entries
  coexist (persister MERGE preserves non-marker entries — unit test
  `testPersistMergesPreservingNonMarkerEntries`); GHN-E boundary: khi bật label flow, giữ marker
  (LabelGenerator thay toàn bộ array → cần merge tại GHN-E) + persister reader ignores popup shape.

## Retry/idempotency (§16)

`testRetryReplaysThePersistedPhysicalSnapshot` green — retry đọc snapshot `secomm_physical`,
không recommerce từ product/default data.
