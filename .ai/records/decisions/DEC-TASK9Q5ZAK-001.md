---
id: DEC-TASK9Q5ZAK-001
type: decision
title: 'Physical shipment facts layer — ShippingCore owns carrier-neutral package facts; GHN interprets type 2/5'
project_code: SLP
status: proposed            # TL accept tại GHN-D r2 review
decision_type: architecture
impact: material
work_items: [TASK-9Q5ZAK]
created: 2026-09-15
updated: 2026-09-15
owner: [dev]
---

# DEC-TASK9Q5ZAK-001 — Physical shipment facts layer

## Context

GHN-D r1 build parcel trực tiếp từ Magento data + GHN config defaults (Σ item weight + dims config →
1 parcel → type-2-only, type-5 auto-unsupported). TL review r2 yêu cầu alignment: **ShippingCore
own physical facts (carrier-neutral), Secomm_Ghn own GHN API semantics** — cùng physical package
tương lai phải dùng được cho GHTK/ViettelPost/SPX mà không duplicate merchant packaging defaults
per carrier.

## Decision

1. **ShippingCore introduced a minimal carrier-neutral physical contract** (`Api/Physical/`):
   `PhysicalPackageInterface` (weightG int, lengthCm/widthCm/heightCm int — normalize to grams/cm
   first, không float ambiguity), `ShipmentPhysicalDataInterface` (totalWeightG + packages[]),
   `CarrierPhysicalLimitInterface` (maxPackageWeightG/max*Cm/supportsMultiplePackages — CHỈ để
   render/validation display; final provider validation vẫn carrier-owned).
2. **Facts storage = `sales_shipment.packages`** (Magento-native, JSON-serialized cột có sẵn) dưới
   marker key `secomm_physical` (self-identifying — không collide popup-shape label flow, vốn
   mutually exclusive vì labels FALSE). KHÔNG thêm bảng mới, KHÔNG serialize provider payload.
3. **Không packaging engine**: không cartonization/bin-packing/auto-split/volumetric. Warehouse/
   admin cung cấp physical facts qua form; merchant default dims (`secomm_shippingcore/physical/
   default_package_*`, di chuyển khỏi `carriers/secomm_ghn/parcel_*`) chỉ là PREFILL — không bao
   giờ được GHN adapter tự inject khi thiếu final physical data (fail-closed INVALID_PARCEL).
4. **GHN interpretation**: 1 package && <20,000g → service_type_id 2 (root = package); else type 5
   → items[] = mỗi physical package (name "Package N", quantity 1, exact weight/dims — mỗi
   GHN heavy item = 1 physical package, KHÔNG phải catalog product).
   **r3 amendment (2026-09-15, TL directive): type-5 root physical fields (weight/length/width/
   height) = OMIT** — `items[]` là đại diện physical duy nhất cho type 5; không synthesize
   aggregate (Σ weight / largest-by-volume). `ShipmentPhysicalData.totalWeightG` vẫn là fact
   ShippingCore (invariant Σ packages) nhưng KHÔNG imply GHN root.weight. Type 2 giữ root = package.
   Root-field absence khóa bằng payload tests (type5: weight/length/width/height absent).
5. **RATE giữ option A**: type-5 RATE stays backlog — checkout chưa có confirmed physical facts;
   không invent dims tại checkout (§20/§21).
6. **Persistence forward-compat (r3 audit)**: `sales_shipment.packages` là cột free-form JSON
   (marker `secomm_physical` + có thể chứa popup-shape entries song song). Persister MERGE
   (giữ non-marker entries, update marker) — khi GHN-E bật label flow, marker phải được preserve
   (LabelGenerator setPackages thay toàn bộ array → GHN-E boundary phải merge lại).

## Consequences

- `Secomm_ShippingCore` thêm `Model/Physical/*` + config group `physical` (additive, không đụng
  contract cũ); `StoreWeightConverter` move từ Ghn sang ShippingCore (dependency direction).
- `Secomm_Ghn`: `ShipmentParcelBuilder` + parcel-dims config XÓA; thay bằng
  `GhnPhysicalParcelInterpreter` + `GhnPhysicalLimit`; observer capture admin POST.
- Cross-carrier: contract không encode GHN semantics — GHTK/VTP/SPX interpret riêng khi đến lượt.
- Multi-package: 1 Magento Shipment → N PhysicalPackage → 1 GHN order; nhiều Shipment = nhiều
  fulfillment event (không phải nhiều hộp).
