# Implementation Plan: TASK-9Q5ZAK — GHN-D slice: Standalone CREATE Shipment trên ShippingCore v5

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-9Q5ZAK (parent FEAT-FQWEQ3) — GHN-D |
| Mode | A (shipment lifecycle + DB schema + money-related mutation → Tier-2; plan approval = signoff) |
| Specification | [specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md](../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md) — FULL, VALID (§16 Package, §17 Create, §18 Sender, §19 Idempotency, §20 Persistence, §21-§22 Trigger/Queue, §44 AC-SHIP) + Embedded Mini-Spec trong record (slice addendum) |
| Decision | [DEC-FEATFQWEQ3-001](../records/decisions/DEC-FEATFQWEQ3-001.md) (create = `is_new_to_address=true` + names 2025 + `to_district_name=""`; gate #4 merged-ward evidence) |
| Contract source | `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §5/§14/§15 (field matrix + error classification + recommended payload) + `sandbox-validation.md` (L8TL6B, idempotency, payment_type 1, type-5 items) |
| Scope (TL brief §38) | **CREATE ONLY** — synchronous observer `sales_order_shipment_save_commit_after`, label FALSE (§35 option A), `client_order_code = GHNS{shipment entity_id}`, cod_amount omitted. **Deviation REPORT TL**: cancel/return + MQ async của mini-spec gốc → backlog (GHN-E/F + ShippingCore shipment-contract slice) |
| Out of scope | cancel/return/webhook (GHN-E) · cutover (GHN-F) · label PDF · MQ · COD amount policy · type-5 items payload · multi-origin · ShippingCore edits (RE-FROZEN) |

## Approach

Observer post-commit → guards (carrier prefix `secomm_ghn_` trên RAW `getShippingMethod()`, VN,
chưa có row GHN) → persist `secomm_ghn_shipment` **PENDING trước API call** (recovery anchor §28) →
Stage-1 `handoffContextForOperation(context, capability, CREATE)` (target `VN_ADMIN_2025`;
source==target → EXACT passthrough) → Stage-2 `GhnMappingResolver::resolve(VN_ADMIN_2025, unit)`
verbatim names (`hasNewAddressNames()`) → payload build theo matrix (dims từ config merchant,
empty/>200 → fail-closed `INVALID_PARCEL`; type-5 → fail-closed `GHN_HEAVY_PARCEL_UNSUPPORTED` 0
HTTP; `payment_type_id`/`required_note` validate enum fail-closed `INVALID_CONFIGURATION`;
`content` = item names ≤2000; KHÔNG cod_amount/insurance/order_value/items/from_*/return_*/service_id)
→ POST `v2/shipping-order/create` (không auto-retry — POST never retried) → SUCCESS: row SUBMITTED +
native Track (dedupe theo track_number); FAILURE: row FAILED/UNKNOWN + log — observer không bao giờ
throw (shipment save không ảnh hưởng). Retry idempotent: CLI `secomm:ghn:shipment:retry` cùng
`client_order_code` → GHN dedupe (sandbox-proven). Weight: `getTotalWeight()` fallback Σ shipment
items weight×qty (vendor không populate total_weight — verified) qua shared
`StoreWeightConverter` (kgs/lbs fail-closed). Idempotency guard = câu lệnh đầu tiên của observer
(event re-fire bởi track save); duplicate-key trên insert PENDING → silent.

## Files affected

| File | Change | Lý do |
|------|--------|-------|
| `etc/db_schema.xml` + `etc/db_schema_whitelist.json` | modify | bảng `secomm_ghn_shipment` (SPEC §20 field list; client_order_code UNIQUE; ghn_order_code index non-unique; fee decimal(12,4); provider_status varchar comment enum) |
| `Model/Config.php` + `etc/config.xml` + `etc/adminhtml/system.xml` + `i18n/*` | modify | `getParcelLengthCm/WidthCm/HeightCm()` (0=unset, fail-closed phía service) + 3 field `<validate>integer</validate>` (empty hợp lệ) |
| `Model/Capability/GhnCreateCapabilityAdapter.php` | new | legacy-shape bridge pin `ShippingAddressOperation::CREATE` (mirror `GhnRateCapabilityAdapter`) |
| `Model/Unit/StoreWeightConverter.php` | new | shared kgs/lbs→gram fail-closed converter; `GhnRateRequestMapper` delegate (RATE tests giữ green) |
| `Model/Shipment/ShipmentParcelBuilder.php` | new | weight fallback Σ items + dims config validate → `GhnParcel` |
| `Model/Shipment/GhnCreateRequestBuilder.php` | new | pure payload builder theo matrix (pure/testable) |
| `Model/Shipment/GhnCreateOutcome.php` | new | VO SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE + reason + orderCode + fee + expectedDeliveryAt |
| `Model/Shipment/GhnShipmentCreationService.php` | new | domain flow (handoff → mapping → payload → POST → outcome); error classification mirror rate; PENDING-first persistence |
| `Model/Shipment/{GhnShipment,ResourceModel/GhnShipment,ResourceModel/GhnShipment/Collection}.php` | new | persistence + getByShipmentId/getByClientOrderCode |
| `Model/Shipment/ShipmentTrackAttacher.php` | new | native Track dedupe-by-number (mirror core AddTrack) |
| `Observer/GhnShipmentCreateObserver.php` + `etc/events.xml` | new | `sales_order_shipment_save_commit_after`; idempotency-first; catch-all; duplicate-key silent |
| `Console/Command/RetryShipmentCommand.php` + `etc/di.xml` | new/modify | `secomm:ghn:shipment:retry <shipment_id>` (§28 recovery) |
| `Model/Carrier/Ghn.php` | modify | sửa stale docblock flags (labels KHÔNG thuộc GHN-D); `_doShipmentRequest` vẫn throw; tracking/labels giữ false |
| `Test/Unit/**` | new/modify | 5 suite mới (builder/service/parcel-builder/observer/converter) + mapper ctor adjust |

## Verification

Scoped Ghn suite (RATE regression green) + VietNamAddress/ShippingCore suites · `setup:di:compile` +
`setup:upgrade` · `--check-specs` · grep gates (0 fallback/VietMap/Ghtk/`service_id`/payment-method
inspection) · **sandbox CREATE cases A-E** (A normal+merged ward → cancel sau; B idempotency same
code; C dims fail-closed 0 HTTP; D mapping flip → PROVIDER_MAPPING_MISSING; E cod_amount absent)
· Magento runtime end-to-end (order thật → admin shipment → Track ghn_order_code; retry CLI
idempotent; non-ghn shipment skip).

## AC (slice)

AC-D1 create thật + merged-ward evidence · AC-D2 retry không duplicate · AC-D4 `secomm_ghn_shipment`
persist đúng, 0 column mới trên sales_order · AC-D5 dims thật (không 1×1×1) · AC-D6 unit green +
QC L3. (AC-D3 cancel/return → backlog theo scope-narrowing.)

---

# r2 (2026-09-15) — Physical Package Alignment (TL-requested correction)

| Field | Value |
|-------|-------|
| Specification | SPEC-FEAT-FQWEQ3 (§16/§17) + [DEC-TASK9Q5ZAK-001](../records/decisions/DEC-TASK9Q5ZAK-001.md) — physical facts layer (proposed) |
| Root cause r1 | ShipmentParcelBuilder build parcel từ Magento data + GHN config dims → 1 parcel → type-2-only; type-5 auto-unsupported |

## r2 Approach

ShippingCore physical facts layer (facts only): `Api/Physical/{PhysicalPackageInterface,
ShipmentPhysicalDataInterface, CarrierPhysicalLimitInterface}` + VOs + `Model/Physical/
{StoreWeightConverter (moved), ShipmentPhysicalPersister (persist/read qua sales_shipment.packages
marker `secomm_physical`), ConfiguredDefaultPackageDimensions (secomm_shippingcore/physical/*,
prefill-only)}`. GHN: `GhnPhysicalParcelInterpreter` (type 2 root / type 5 items[]-per-package,
limits fail-closed) + `GhnParcelPlan` + `GhnPhysicalLimit` (50000g/200cm/multi=true) +
`GhnCreateRequestBuilder` switch GhnParcel→GhnParcelPlan + service physical-source resolution
(POST raw → snapshot → fail-closed) + observer in-flight guard + HttpRequestInterface + admin
"Package Information" block (extra_shipment_info, prefill + Add Package + limits). REMOVE
`carriers/secomm_ghn/parcel_*`. RATE giữ option A (type-5 backlog). Idempotency/address/COD
semantics KHÔNG đổi.

## r2 Verification

Sandbox A (type 2) / B (type 5 heavy single) / C (multi-package) / D (invalid pkg 0 HTTP) · 3 probe
flags (root Σ>50kg, items price omitted, positive multi-item create) · Ghn+ShippingCore suites 0F/0E
· RATE regression green · grep gates.

---

# r3 (2026-09-15) — Type-5 root payload correction + persistence compatibility audit

TL review r2: (1) type-5 KHÔNG gửi synthetic root physical fields (weight/length/width/height
omitted — items[] là đại diện physical duy nhất); (2) audit `sales_shipment.packages` forward-compat
với native label flow. Delta: GhnParcelPlan root fields nullable (type5 = null root); interpreter bỏ
largest-by-volume/Σ-root; builder omit root keys khi null; persister MERGE non-marker entries
(forward-compat GHN-E label flow); tests absence-assertion (§11). `totalWeightG` giữ nguyên trong
ShippingCore (fact-only, không imply root.weight). RATE/COD/address/idempotency không đổi.
