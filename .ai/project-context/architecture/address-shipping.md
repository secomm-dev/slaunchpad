# Secomm Launchpad — Address & Shipping Architecture Context

> Mục đích tài liệu: dùng làm **architecture review map** cho cụm Address + Shipping của Secomm Launchpad, đồng thời có thể nạp trực tiếp vào Project Tool làm context khi audit, review hoặc triển khai các module liên quan.
>
> Nguyên tắc chính: **mỗi module chỉ own đúng responsibility của layer đó; không kéo logic của layer khác vào module hiện tại.**

> **Revision v3 (post-review):** v2 giới thiệu per-operation scheme; v3 đóng các gap từ review.
> **P0 fixes:** (1) resolution snapshot chỉ lưu **canonical PRE-2025 unit_code**, tuyệt đối không GHN provider IDs (§5.1); (2) `CanonicalResolutionSnapshot.failure_class` (NONE|AMBIGUOUS|UNMAPPED|TECHNICAL) để RATE phân biệt UNAVAILABLE vs TECHNICAL_FAILURE (§5.1/§23). **Scope fixes:** (3) external resolver P1 **chỉ AMBIGUOUS**, không UNMAPPED, là selector trên candidate đã biết (§23); (4) `GEOPOINT`/`GeocodeHandoff` (Type B) và address cho CANCEL/TRACK **deferred**, không vào contract P1 (§5/§8/§28). **Wording:** representation là category không phải provider value (§5/§6); Type A/B là planning-only không phải domain enum (§8); bỏ concrete `carrier→Secomm_Cod` coupling (§8). Thêm **§32 Verified External Facts** (provenance cho mọi claim thị trường).
> **Revision v4 (COD payment identification):** bổ sung MỘT contract gap nhỏ do consumer thật (GHN/GHTK create-order) chứng minh: **ShippingCore own COD payment identification** — configuration khai báo Magento payment method codes nào được xem là COD + resolver `isCod(paymentMethodCode)` cho carrier consume khi build provider request (§4.1). Reword các chỗ "collect amount precompute / approved COD contract / COD architecture document" (§8/§28/§29) về mức identification tối thiểu — **KHÔNG có `Secomm_Cod`, KHÔNG có COD framework**. Scope v3 khác không đổi.
> **Revision v5 (Legacy RATE strategy + LEGACY_ADDRESS_FALLBACK) — MATERIAL AMENDMENT, partial supersede:** rule trước đây *"chỉ TECHNICAL_FAILURE mới fallback"* được supersede MỘT PHẦN. Thêm **Legacy RATE Strategy** per carrier-operation (`FALLBACK_ONLY` | `MAP_THEN_FALLBACK`, §15.1): khi RATE cần legacy scheme mà merchant opt-in, AMBIGUOUS/UNMAPPED/skip-mapping **vẫn giữ `CarrierRateOutcome::UNAVAILABLE`** (KHÔNG reclassify thành TECHNICAL_FAILURE) nhưng tạo **LEGACY_ADDRESS_FALLBACK eligibility** explicit — fallback đi bắt buộc qua `FallbackRateProviderInterface → Launchpad_MageplazaTableRate → Mageplaza TableRate semantics` (§19.1: bridge = alternate entry path, không phải calculator riêng). Fallback eligibility = orchestration state từ ĐÚNG 2 nguồn (`TECHNICAL_FALLBACK` | `LEGACY_ADDRESS_FALLBACK`), không parse reason. Giữ nguyên: any-realtime-SUCCESS-suppresses-fallback, resolver AMBIGUOUS-only P1, bridge isolation, STANDALONE behavior, status vs reason.

> **Revision v5.1 (external resolver provider removed from backlog — 2026-09-16):** merchant/
> TL decision — **VietMap bị LOẠI khỏi backlog** (yêu cầu geocode, không phù hợp scope). Hệ quả:
> (1) không có external resolver provider nào được build ở bất kỳ phase nào cho tới khi xuất hiện
> một provider KHÔNG cần geocode; (2) seam `ExternalAddressResolverInterface` + pool GIỮ NGUYÊN
> làm extension point (contract đã đúng, chưa có implementation); (3) AMBIGUOUS residual trên
> legacy RATE path → KHÔNG disambiguate tự động → `UNAVAILABLE` → **LEGACY_ADDRESS_FALLBACK**
> theo strategy opt-in là con đường xử lý duy nhất (§15.1); (4) failure classification KHÔNG đổi.
> Đã ghi vào §23/§32.
> **Revision v6 (Shipment Physical Package Data):** bổ sung contract gap do consumer thật GHN-D CREATE chứng minh — **ShippingCore own carrier-neutral physical facts** của shipment đã confirm: `ShipmentPhysicalData` (totalWeightG + packages[]) / `PhysicalPackage` (weightG, lengthCm, widthCm, heightCm — đơn vị canonical: gram/centimeter), persistence reuse Magento-native `sales_shipment.packages` (marker key `secomm_physical`, fail-closed malformed), 1 Magento Shipment → 1..N Physical Packages, snapshot ổn định cho retry (không recalculate từ catalog), admin prefill (item weight + merchant default dimensions) ≠ confirmed truth, product dimensions ≠ parcel dimensions, KHÔNG cartonization/auto-packing. Ownership: ShippingCore = physical facts; carrier = provider interpretation (GHN root vs items[], service_type — thuộc `Secomm_Ghn`). Chi tiết **§33**; DEC: DEC-TASK9Q5ZAK-001; implementation: TASK-9Q5ZAK.
> **v6 — amendment hoàn tất (full physical-model amendment, GHN-D r3):** thêm **`CarrierPhysicalLimitInterface` capability boundary** (limits KHAI BÁO bởi carrier modules, ShippingCore không hardcode giá trị; final validation vẫn carrier-authoritative — §33.7); **terminology 4 concept tách bạch** (Product / Magento Shipment / Physical Package / Carrier Order — 1 Shipment có thể chứa N packages, KHÔNG assume 1 package = 1 shipment, nhiều shipments = các fulfillment event riêng — §33.1); PhysicalPackage semantics (không infer dims từ product: không sum/max/cube-root/cartonization/3D bin packing/auto carton selection — §33.3); Launchpad Core operational model (default 1 Shipment → 1 package; prefill ≠ confirmed; KHÔNG phải cartonization/warehouse-optimization — §33.4); **RATE vs CREATE độ chắc chắn physical khác nhau** (RATE trước packing — estimates OK; CREATE dùng confirmed snapshot; GHN type-5 RATE = backlog riêng, không phải defect — §33.8); **`sales_shipment.packages` compatibility rule** (COMPATIBLE_WITH_CONVERSION — MERGE marker với native entries, KHÔNG blind-overwrite; forward-compat bắt buộc cho future native label flow / GHN-E — §33.10); **idempotency** (stable physical snapshot + stable carrier request identity — replay, không recalculate; GHN `client_order_code = GHNS{shipment_id}` là carrier-owned, không nằm trong generic contract — §33.11); non-goals Launchpad Core (không cartonization/3D packing/package-profile optimization/warehouse slotting/auto multi-package splitting/carrier volumetric optimization/OMS packing engine — §33.12); GHN type-2/type-5 documented như **reference implementation only** (sandbox-proven 2026-09-15, KHÔNG phải ShippingCore rule; root weight type-5 = sandbox behavior, provenance ghi rõ — §33.16).
> **Revision v7 (Mageplaza fallback composition — Magento-orchestrated outcomes):** định hình lại
> mô hình Mageplaza fallback theo DEC-TASK5XQXZK-001: (1) KHÔNG có carrier fan-out registry —
> Magento Shipping Framework own carrier discovery/execution; (2) carriers REPORT normalized
> outcomes vào `CarrierRateOutcomeCollectorInterface` (ShippingCore, pair
> `carrier_code`+`method_code`, bracket per rate-collection execution); (3) ShippingCore own
> safe-degradation eligibility POLICY (TECHNICAL_FAILURE + CANONICAL_AMBIGUOUS eligible;
> UNMAPPED/unsupported/config errors NOT; AMBIGUOUS không bị convert thành TECHNICAL);
> (4) Mageplaza Method = fallback grouping identity với per-method `show_to_customer`,
> `use_as_fallback` và realtime membership — supersede `service_level_code → method_id` mapping
> và global FALLBACK_ONLY/STANDALONE mode (§15.1 ownership note, §17, §18); (5) fallback
> trigger = outer seam plugin `Magento\Shipping\Model\Shipping::collectRates()`; (6) membership
> KHÔNG thay đổi carrier visibility (không auto-dedup); (7) City/Area dimension với stable
> `city_code` + precedence CHỈ narrow location scope (không phá SUM/MIN/MAX).
> **§12 `CarrierServiceLevelInterface` DEPRECATED** (0 implementation, hướng ngược với
> method-membership) — xoá khi dọn dẹp kế tiếp, không giữ dead abstraction "just in case".
> **Revision v8 (external resolver — backlog cleanup / dormant seam normalization):**
> - External resolver providers **removed from active Launchpad roadmap** (VietMap bị loại — yêu
>   cầu geocode; Google Maps resolver cũng KHÔNG nằm trong active backlog).
> - `ExternalAddressResolverInterface` + `ExternalAddressResolverPool` giữ nguyên như
>   **dormant extension seam** — active provider implementations = none; active roadmap item = none.
> - Residual legacy RATE ambiguity: AMBIGUOUS (candidate > 1) và UNMAPPED (candidate = 0) đều
>   **KHÔNG gọi resolver** — sử dụng configured `LEGACY_ADDRESS_FALLBACK` (§15.1) qua normal
>   fallback orchestration; giữ resolution semantics UNAVAILABLE, không reclassify technical.
> - AMBIGUOUS detection (VietNamAddress: mapping-graph cardinality + name-bridge) và propagation
>   (ShippingCore: manager/handoff candidate codes) GIỮ NGUYÊN — đã complete.
> - Auto-select candidate vẫn FORBIDDEN (D9). Reopen resolver chỉ khi real consumer evidence mới
>   + architecture/governance decision mới.
> **Revision v9 (Carrier Eligibility / Destination Scope / Rate Source Mode / Address Resolution Policy) — MATERIAL AMENDMENT, partial supersede DEC-FEATYA2C0W-005:** merchant cần cấu hình carrier
> eligibility theo canonical destination (DestinationScope ALL | SELECTED_ZONES + Canonical Zones
> provider-neutral — zone codes là composition data, không domain enum), Rate Source Mode per
> carrier-RATE (`CARRIER_ONLY` | `CARRIER_WITH_FALLBACK` | `FALLBACK_ONLY` — FALLBACK_ONLY skip
> resolution/mapping/RATE API), Address Resolution Policy (`STRICT` | `FALLBACK`; PICK_PRIMARY
> DEFERRED — D9), và normalize Fallback Eligibility taxonomy (TECHNICAL_FALLBACK |
> INTEGRATION_LIMITATION_FALLBACK [mapping missing / capability unsupported / auth-config —
> configurable + mandatory high-severity warning] | LEGACY_ADDRESS_FALLBACK). Processing order:
> FALLBACK_ONLY skip resolution/mapping/RATE API. **Supersede:** `LegacyRateStrategy` config axes
> (DIRECT_FALLBACK ≡ FALLBACK_ONLY; MAP_THEN_FALLBACK ≡ CARRIER_WITH_FALLBACK +
> AddressResolutionPolicy::FALLBACK) — chi tiết §35, DEC-FEATYA2C0W-006. Giữ nguyên: outcome
> 3-state, status vs reason, realtime suppression, D9 no-auto-pick, bridge isolation, v7
> composition model.Core invariant (layered ownership, two-stage mapping, status vs failureReason, fallback-as-price-only, bridge isolation) không đổi.
> **v10 bridge adaptation (TASK-78PVR1 — Mageplaza fallback bridge trên frozen v10):**-delta
> confirmed: (1) `SafeDegradationEligibilityPolicy` default map cập nhật theo §35.5 —
> PROVIDER_MAPPING_MISSING = INTEGRATION_LIMITATION → eligible (status vẫn UNAVAILABLE); auth/
> config configurable (DI reasons), default OFF; (2) `Launchpad_MageplazaTableRate` áp
> RateSourceMode/AddressResolutionPolicy **per member carrier** qua shared config-path
> convention `carriers/<carrier_code>/{rate_source_mode,address_resolution_policy}` (thin read,
> fail-closed defaults §35.7): `CARRIER_ONLY` → member failures không bao giờ mở fallback;
> `FALLBACK_ONLY` → fallback eligibility TRỰC TIẾP (không synthetic TECHNICAL_FAILURE — carrier
> short-circuit trước resolution/mapping/API); `AMBIGUOUS + STRICT` → không ambiguity-fallback;
> PICK_PRIMARY selection vẫn ở shared handoff/selector — bridge không inspect candidates.
> Bridge ownership KHÔNG đổi: membership + per-method caps + Mageplaza calculation + city
> extension; ShippingCore ownership KHÔNG đổi: collector + eligibility semantics.
> **Revision v10 (PICK_PRIMARY promoted — P1, legacy-scheme RATE only):** supersede MỘT PHẦN
> v9/DEC-FEATYA2C0W-006 ("PICK_PRIMARY DEFERRED"). `AddressResolutionPolicy` P1 = `STRICT` |
> `FALLBACK` | `PICK_PRIMARY`; **PICK_PRIMARY chỉ applicable khi `requiredScheme(RATE)` là legacy
> scheme khác runtime scheme** (cross-scheme mapping có thể AMBIGUOUS). Chọn candidate =
> **deterministic curated primary designation** trong mapping dataset (is_primary/rank — KHÔNG
> alphabetical/db-row/code-order/fuzzy), cùng dataset version → cùng selected candidate; candidates
> thiếu curated designation → KHÔNG pick (xử lý như unresolved theo policy). FALLBACK_ONLY không
> evaluate AddressResolutionPolicy (short-circuit trước selector). Snapshot thêm optional
> provenance: selection_policy/selection_reason (minimal; selected candidate đã có qua
> resolved_pre2025). DATA_INTEGRITY_DEFECT không được auto-select. Launchpad default vẫn FALLBACK;
> PICK_PRIMARY = merchant explicit opt-in per applicable carrier RATE.Core invariant (layered ownership, two-stage mapping, status vs failureReason, fallback-as-price-only, bridge isolation) không đổi.
---

## 1. Architecture tổng thể

```text
Magento / Hyvä / Checkout
        │
        ▼
Secomm_AddressDropdown
        │
        ▼
Secomm_VietNamAddress
        │
        ▼
Secomm_ShippingCore
        │
        ├───────────────┬─────────────────┐
        ▼               ▼                 ▼
   Secomm_Ghn       Secomm_Ghtk    [Type A carriers…]     (Type B/geocode → P2)
        │               │                 │
        └───────────────┴─────────────────┘
                         │
                         ▼
              CarrierRateOutcome
                         │
                         ▼
             Secomm_ShippingCore
        Service-level aggregation/decision
                         │
                         ▼
            FallbackRateProviderInterface
                         ▲
                         │
          Launchpad_MageplazaTableRate
                         │
                         ▼
          Mageplaza_TableRateShipping
```

Phân lớp ownership:

```text
Secomm_*     = reusable product modules
Launchpad_*  = Launchpad-specific composition / third-party bridge
Mageplaza_*  = third-party extension
```

Hai rule bắt buộc:

```text
Carrier không biết Mageplaza tồn tại.
ShippingCore không biết Mageplaza tồn tại.
```

---

## 2. `Secomm_AddressDropdown`

### Vai trò

`Secomm_AddressDropdown` là **generic hierarchical address UI/data foundation**.

Module này không riêng Việt Nam và không được hardcode business terminology như District/Ward/Commune.

### Chức năng chính

Dựa trên Magento directory và hierarchy city-node:

```text
directory_region
directory_region_city
```

`directory_region_city` hỗ trợ:

```text
city_id
region_id
default_name
parent_city_id
code
```

Hierarchy:

```text
region
  ↓
city
  ↓
child city
  ↓
...
```

Ví dụ VN current:

```text
Province
  ↓
Ward
```

Ví dụ VN legacy:

```text
Province
  ↓
District
  ↓
Ward
```

Nhưng core module chỉ biết:

```text
region
city node
parent-child
address profile
```

### Module này own

- generic hierarchical address data model;
- dropdown behavior;
- admin CRUD;
- `parent_city_id`;
- address profile;
- frontend/admin address form foundation.

### Module này không own

- dữ liệu hành chính Việt Nam;
- mapping 2025 ↔ PRE-2025;
- GHN/GHTK/ViettelPost/Ahamove IDs;
- shipping logic;
- carrier API.

### Dependency

```text
Secomm_AddressDropdown
→ Magento Directory
```

Không được depend ngược vào `Secomm_VietNamAddress`.

---

## 3. `Secomm_VietNamAddress`

### Vai trò

`Secomm_VietNamAddress` là **Vietnam canonical administrative-address layer**.

Nó dùng `Secomm_AddressDropdown` làm generic hierarchy/runtime foundation, nhưng own dữ liệu và mapping hành chính Việt Nam.

### Dependency

```text
Secomm_VietNamAddress
→ Secomm_AddressDropdown
```

Không được reverse dependency.

### Address schemes

#### Current scheme

```text
VN_ADMIN_2025
```

Hierarchy:

```text
Province/City
  ↓
Ward/Commune
```

#### Historical scheme

```text
VN_ADMIN_PRE_2025
```

Hierarchy:

```text
Province/City
  ↓
District/Town
  ↓
Ward/Commune
```

Runtime storefront/admin dùng:

```text
VN_ADMIN_2025
```

`VN_ADMIN_PRE_2025` chỉ là historical/reference scheme phục vụ mapping/integration.

### Canonical identity

Không dùng name làm identity.

Canonical identity:

```text
scheme_code + unit_code
```

Ví dụ:

```text
VN_ADMIN_2025 + VNA25-xxxx
```

### Module ownership

Module own:

- scheme definitions;
- administrative units;
- mapping graph;
- import datasets;
- current/historical scheme metadata;
- canonical candidate resolution;
- runtime → canonical address bridge.

Core tables:

```text
secomm_vietnam_address_scheme
secomm_vietnam_address_unit
secomm_vietnam_address_mapping
```

### Mapping semantics

Graph:

```text
VN_ADMIN_PRE_2025
    ↔
VN_ADMIN_2025
```

Relation metadata:

```text
SAME_AS
RENAMED_TO
MERGED_INTO
SPLIT_INTO
```

Runtime resolution dựa trên **candidate cardinality**, không dựa vào `relation_type`.

```text
same scheme
→ EXACT

1 candidate
→ MAPPED

>1 candidates
→ AMBIGUOUS

0 candidate
→ UNMAPPED
```

Mandatory rule:

```text
AMBIGUOUS
→ không được tự chọn candidate đầu tiên
```

### Module này không own

- carrier API;
- carrier provider IDs;
- GHN district/ward IDs;
- GHTK provider mapping;
- VietMap IDs;
- shipping fallback;
- shipping rate.

---

## 4. `Secomm_ShippingCore`

### Vai trò

`Secomm_ShippingCore` là shared orchestration layer cho shipping.

Nó chịu trách nhiệm chuẩn hóa:

- shipping address resolution;
- carrier-facing address handoff;
- common realtime carrier rate outcome;
- dynamic shipping service level;
- service-level realtime aggregation;
- service-level fallback decision;
- provider-neutral fallback contract;
- COD payment identification (payment method codes nào được xem là COD);
- shipment physical facts (carrier-neutral package/weight/dimension data của shipment đã confirm — §33);
- carrier physical-limit capability boundary (shared UI/validation facts — giá trị do từng carrier khai báo, §33.7).

### Dependency

```text
Secomm_ShippingCore
→ Secomm_VietNamAddress
```

Không depend:

```text
Secomm_ShippingCore
-X-> Secomm_Ghn
-X-> Secomm_Ghtk
-X-> Secomm_Ahamove
-X-> Mageplaza_TableRateShipping
-X-> Launchpad_MageplazaTableRate
```

ShippingCore foundation hiện được xem là **COMPLETE / HARD STOP** cho scope Launchpad hiện tại.

### 4.1 ShippingCore — COD Payment Identification

Gap cụ thể: carrier (GHN/GHTK) khi build CREATE-order request cần biết order là COD hay
non-COD (GHN `cod_amount`; GHTK `pick_money` + `pick_option: cod`). Nếu mỗi carrier tự
hardcode `cashondelivery`… thì danh sách COD methods bị trùng lặp và lệch nhau giữa các carrier.

Ownership chốt:

```text
Secomm_ShippingCore own:
- configuration khai báo Magento payment method codes nào được xem là COD
  (ví dụ: cashondelivery, custom_cod — declare qua configuration, không hardcode trong code);
- shared, provider-neutral resolver:
      isCod(paymentMethodCode): bool
- expose một contract nhỏ cho downstream carrier modules consume.
```

Contract (shape scalar-in/bool-out — không kéo Magento order model vào ShippingCore):

```text
Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface

isCod(string $paymentMethodCode): bool
```

Carrier đã có order trong tay qua Magento contract (`Magento\Sales\Api\Data\OrderInterface`) —
carrier tự đọc `order.getPayment().getMethod()` rồi gọi resolver. ShippingCore đọc/chấp nhận
payment method code qua Magento contracts, KHÔNG coupling vào payment implementation cụ thể.

Runtime flow:

```text
Magento Order
    ↓
order.payment.method
    ↓
isCod(paymentMethodCode)     (ShippingCore, config-owned)
    ↓
true | false
    ↓
carrier build provider-specific request
```

Scope fence — KHÔNG build (chỉ reopen khi có business requirement/consumer thật):

```text
Secomm_Cod standalone module
COD eligibility engine
min/max COD order value
customer blacklist / risk scoring
COD surcharge
OTP COD
COD reconciliation / COD settlement
rule engine
payment-method visibility orchestration
shipping-service-level ↔ COD eligibility
partial-payment / deposit framework
generic COD policy framework
```

Đây là **identification thuần**: "payment method này có được coi là COD không?" — KHÔNG phải
COD framework. (`enabled`/visibility của payment method vẫn là việc của Magento + composition.)

---

## 5. ShippingCore — Canonical Address Resolution

Flow:

```text
Magento destination
      ↓
ShippingAddressResolutionContext
      ↓
ShippingAddressResolutionManager
      ↓
Secomm_VietNamAddress
```

Manager trả:

```text
EXACT
MAPPED
AMBIGUOUS
UNMAPPED
```

Carrier capability khai báo **theo từng operation**, trên hai trục tách biệt:

```text
requiredScheme(operation)      : VN_ADMIN_2025 | PRE_2025
supportedRepresentations(op)   : [UNIT_ID, TEXT_NAME]        # representation CATEGORY, không phải giá trị provider
supportsTextualFallback(op)    : bool
```

> **Scope P1:** chỉ **RATE** và **CREATE** cần address representation. CANCEL/TRACK operate bằng provider order-code / tracking-code, chưa có evidence cần address scheme → **KHÔNG** đưa vào capability P1. Thêm operation khác chỉ khi một carrier chứng minh nó cần address-scheme.
>
> **Representation là CATEGORY, không phải provider value:** `UNIT_ID`/`TEXT_NAME` là *kind* generic. ShippingCore biết operation cần category nào; ShippingCore KHÔNG biết `GHN district_id`, `GHN ward_code`, hay `is_new_to_address` — đó là provider value/payload, carrier-owned. `GEOPOINT` (Type B) **chưa** nằm trong contract P1 — deferred, xem §8/§23.

> Lý do per-operation (KHÔNG phải per-carrier): cùng một carrier có thể yêu cầu scheme khác nhau ở các operation khác nhau.
> Ví dụ GHN: RATE cần PRE_2025 + ID (district_id/ward_code cũ), còn CREATE nhận VN_ADMIN_2025 + TEXT (tên mới). Chi tiết §7.

Mục tiêu:

> ShippingCore quyết định canonical destination có resolve được về scheme mà **operation cụ thể** của carrier cần hay không.

Provider-specific mapping (render ID/text) chưa xảy ra ở stage này — đó là Stage 2, carrier-owned.

### 5.1 Shift-left resolution (bắt buộc cho AMBIGUOUS trên RATE path)

RATE chạy **fan-out đồng bộ qua N carrier tại checkout**. External disambiguation (gọi VietMap/GoogleMaps) **không được** execute bên trong rate collection đó. Nguyên tắc chính xác:

```text
External disambiguation resolve TRƯỚC rate fan-out (tại address-entry), với latency/timeout policy chặt,
rồi PERSIST snapshot. RATE đọc snapshot đã persist — KHÔNG gọi resolver trong carrier quote fan-out.
```

> Lưu ý wording: shift-left KHÔNG tự biến network call thành non-blocking. Address-entry có thể vẫn là một checkout step khách đang đợi. Cái shift-left bảo đảm là: resolver không chạy lặp lại trong từng `collectRates()` của từng carrier, và có một chỗ duy nhất để cache + gắn failure semantics.

**Resolution snapshot schema** (hợp nhất P0#1 + P0#2 + snapshot-semantics — đây là object trung tâm):

```text
CanonicalResolutionSnapshot
├── canonical_2025      { scheme=VN_ADMIN_2025, unit_code }          # cho CREATE
├── resolved_pre2025    { scheme=VN_ADMIN_PRE_2025,
│                          province_unit_code, district_unit_code, ward_unit_code }   # cho RATE
├── status              : RESOLVED | UNRESOLVED
├── failure_class       : NONE | AMBIGUOUS | UNMAPPED | TECHNICAL
├── source              : LOCAL_MAPPING | EXTERNAL_RESOLVER
└── provenance          : { resolver?, timestamp, mapping_version }
```

> **P0#1 — chỉ lưu CANONICAL identity, tuyệt đối không provider IDs.** `resolved_pre2025` là **Secomm canonical PRE-2025 unit_code**, KHÔNG phải `GHN district_id`/`ward_code`. Provider IDs vẫn carrier-owned (Stage 2), render tại `Secomm_Ghn` từ canonical PRE-2025. Nhờ vậy cùng một snapshot reuse được cho GHN / ViettelPost / J&T / NinjaVan, không bị GHN-specific.
>
> **Rule (ghi thẳng):** *Shift-left persistence stores canonical resolution snapshots only. Provider-specific IDs/codes remain carrier-owned and must not be persisted as ShippingCore canonical state.*

**Outcome mapping tại RATE** (P0#2 — phải phân biệt được, nếu không E-SL2 quyết fallback sai):

```text
status=RESOLVED              → carrier mapping/API
failure_class=AMBIGUOUS      → CarrierRateOutcome::UNAVAILABLE          (business; LEGACY_ADDRESS_FALLBACK eligible nếu RATE strategy opt-in — §15.1)
failure_class=UNMAPPED       → CarrierRateOutcome::UNAVAILABLE          (business; LEGACY_ADDRESS_FALLBACK eligible nếu RATE strategy opt-in — §15.1)
failure_class=TECHNICAL      → CarrierRateOutcome::TECHNICAL_FAILURE    (→ fallback eligible)
```

> Nếu chỉ persist "unresolved" trơn, RATE không phân biệt được *mapping thật sự ambiguous* (→ UNAVAILABLE) với *resolver timeout* (→ TECHNICAL_FAILURE → fallback). Đây là contract gap thật, đủ concrete để đưa snapshot shape này vào contract P1 (xem §28).

**Snapshot semantics** (P#9 — không tuyên bố "persist cả hai lên customer address" như invariant):

```text
customer address  → current canonical identity (reusable)
quote address     → current identity + CanonicalResolutionSnapshot (transactional, có provenance)
order address     → snapshot copy (đóng băng tại thời điểm đặt hàng)
```

> Có thể cache reusable resolution riêng để tối ưu, nhưng resolution PRE-2025 (đặc biệt khi từ external resolver) là transactional/provenance-bearing — không nên gắn như property vĩnh viễn của customer address mà thiếu metadata. Quyết định DB cụ thể để ở implementation, không chốt trong architecture doc.
>
> Kết quả geocode được cache/persist, KHÔNG promote thành canonical edge authoritative trong mapping table (normalization confirms edges, không create edges).
>
> **v5 — snapshot integrity với fallback:** fallback price được dùng (TECHNICAL_FALLBACK hoặc
> LEGACY_ADDRESS_FALLBACK — §15.1) KHÔNG được fake snapshot thành `RESOLVED`. `status`/`failure_class`
> phản ánh đúng resolution state (AMBIGUOUS/UNMAPPED vẫn UNRESOLVED như thật); nguồn fallback là
> thông tin của decision layer, không phải của resolution snapshot.

---

## 6. ShippingCore — Carrier Address Handoff

Carrier không nên tự interpret:

```text
EXACT
MAPPED
AMBIGUOUS
UNMAPPED
UnsupportedDestinationException
```

Flow:

```text
Magento destination
      ↓
DestinationContextBuilder
      ↓
ShippingAddressResolutionManager
      ↓
CarrierAddressHandoffService
      ↓
CarrierAddressHandoff
```

Handoff conceptually cung cấp (**theo operation**):

```text
is applicable?
operation                              (RATE | CREATE)
resolved canonical address             (ở scheme mà operation cần)
supported representations              ([UNIT_ID, TEXT_NAME])
textual fallback allowed?
failure reason?
```

Ví dụ:

```text
non-VN
→ not applicable

MAPPED
→ canonical address available

AMBIGUOUS
→ unresolved
→ maybe textual fallback eligible
```

Mandatory boundary:

```text
ShippingCore
→ resolve canonical về đúng scheme mà operation cần
→ quyết textual fallback CÓ ĐƯỢC PHÉP hay không

Carrier
→ chọn representation (ID hay text) theo operation
→ build provider-specific representation
→ (GHN.CREATE: set cờ định dạng địa chỉ mới `is_new_to_address` khi gửi text 2 cấp)
```

> ShippingCore biết **representation category** mà operation yêu cầu (`UNIT_ID` / `TEXT_NAME`), nhưng KHÔNG biết provider-specific values/payload (`GHN district_id`, `GHN ward_code`, `is_new_to_address`). Render giá trị cụ thể là carrier-owned.

---

## 7. Canonical Mapping và Provider Mapping là 2 stage khác nhau

### Stage 1 — Canonical mapping

```text
Magento/current address
→ target canonical VN identity
```

Owned by:

```text
Secomm_VietNamAddress
+
Secomm_ShippingCore
```

### Stage 2 — Provider mapping

Provider mapping là **per-operation**, vì carrier có thể cần scheme + representation khác nhau theo operation.

Ví dụ GHN (bất đối xứng đã verify từ API):

```text
RATE   : canonical PRE_2025 (Secomm unit_code) → GHN district_id + ward_code (ID cũ)   [bắt buộc ID]
CREATE : canonical VN_ADMIN_2025 (Secomm unit_code) → GHN ward/district/province NAME  [text mới + cờ is_new_to_address]
```

> Input của Stage 2 là **Secomm canonical unit_code** (từ resolution snapshot §5.1), KHÔNG phải provider ID. `Secomm_Ghn` tự render `district_id`/`ward_code` của GHN từ canonical PRE-2025. Provider IDs không rời khỏi carrier module.

> Calculate-fee API của GHN vẫn dùng địa chỉ cũ + ID cũ → backmapping 2025→PRE_2025 vẫn nằm trên critical path của GHN, nhưng ở bước **RATE** (đồng bộ, checkout), không phải CREATE. Đây là lý do shift-left §5.1 là bắt buộc, không optional.

Owned by:

```text
Secomm_Ghn
```

Ví dụ GHTK:

```text
canonical VN address
→ GHTK-compatible textual representation
```

Owned by:

```text
Secomm_Ghtk
```

Nếu Stage 2 fail:

```text
PROVIDER_MAPPING_MISSING
```

Không được convert ngược thành:

```text
CANONICAL_UNRESOLVED
```

---

## 8. Carrier Modules

Các reusable carrier modules, phân loại theo **carrier type** (quyết định hình dạng address handoff):

```text
# Type A — parcel / admin-unit carrier (handoff theo scheme: ID hoặc text)
Secomm_Ghn          [P1 Core]   self-serve API, in-house, dual-scheme per-operation
Secomm_Ghtk         [P1 Core]   public OpenApi, text-based (cần verify scheme per-op)
Secomm_ViettelPost  [Growth]    Open API, cần onboarding partner qua sales
Secomm_Jt           [Growth]    API qua cooperation agreement
Secomm_NinjaVan     [Growth]    partner onboarding

# Type B — on-demand / geocode carrier (handoff theo lat/lng) — P2
Secomm_Ahamove      [P2]        in-house, lat/lng, cần GeocodeHandoff
Secomm_Grab         [P2]
Secomm_Lalamove     [P2, optional]
```

> **SPX (Shopee Express) đã LOẠI:** không có public API (platform-locked, chỉ seller Shopee).
>
> **Type A/B là planning classification, KHÔNG phải ShippingCore domain enum.** Đừng sinh `CarrierType::TYPE_A/TYPE_B` trong domain model. Phân biệt code thật (khi đến P2) nằm ở **representation category** (`UNIT_ID`/`TEXT_NAME` vs `GEOPOINT`), không phải ở một carrier-type hierarchy.
>
> **Type B / GEOPOINT deferred P2 (hard stop):** contract representation P1 của ShippingCore chỉ cần `UNIT_ID`/`TEXT_NAME`. `GEOPOINT` và `GeocodeHandoff` **chưa** được thêm vào contract; reopen representation contract khi Ahamove/Grab là consumer thật chứng minh yêu cầu cụ thể. (Không build `GeocodeHandoff` sớm.)

### Carrier module own

- provider configuration;
- provider location mapping;
- provider API client;
- rate request;
- create order;
- cancel order;
- tracking;
- provider-specific textual representation;
- provider error classification.

Ví dụ:

```text
canonical address
↓
GHN provider IDs
↓
GHN rate API
```

### Carrier module không own

- VN 2025 ↔ PRE-2025 canonical mapping;
- service-level fallback orchestration;
- Mageplaza integration;
- common shipping policy;
- external address-disambiguation provider orchestration;
- COD identification/policy. Carrier KHÔNG hardcode Magento COD payment method codes, KHÔNG tự maintain danh sách COD methods, KHÔNG tự quyết method nào là COD, KHÔNG introduce COD policy riêng, KHÔNG depend vào một hypothetical `Secomm_Cod`. Carrier hỏi ShippingCore — `CodPaymentMethodResolverInterface::isCod(paymentMethodCode)` (§4.1) — rồi tự map kết quả sang provider-specific COD fields theo API contract của carrier (GHN `cod_amount`; GHTK `pick_money` + `pick_option: cod`). Collect amount của order được carrier đọc từ order tại thời điểm build request theo API contract của provider — không có COD amount abstraction riêng ở layer này.

### Dependency

```text
Secomm_Ghn
→ Secomm_ShippingCore

Secomm_Ghtk
→ Secomm_ShippingCore

Secomm_Ahamove
→ Secomm_ShippingCore
```

Không carrier-to-carrier dependency.

---

## 9. Common Carrier Rate Outcome

Carrier API result phải được translate thành common ShippingCore semantics:

```text
SUCCESS
UNAVAILABLE
TECHNICAL_FAILURE
```

### `SUCCESS`

Carrier trả valid realtime rate.

### `UNAVAILABLE`

Known/non-transient issue, ví dụ:

```text
destination unsupported
provider mapping missing
invalid merchant configuration
auth error
invalid parcel dimensions
outside coverage
service unavailable
canonical unresolved without valid textual fallback
```

`UNAVAILABLE` không được tự trigger emergency fallback.

### `TECHNICAL_FAILURE`

Temporary failure:

```text
timeout
connection failure
HTTP/provider 5xx
temporary provider outage
temporary malformed/unusable technical response
```

`TECHNICAL_FAILURE` có thể đóng góp vào fallback eligibility.

> **v5 — fallback eligibility ≠ outcome status:** `UNAVAILABLE` vẫn là business semantics và
> không bao giờ được reclassify thành `TECHNICAL_FAILURE`. Riêng carrier RATE với legacy
> strategy opt-in (§15.1), `UNAVAILABLE` do AMBIGUOUS/UNMAPPED/skip-mapping tạo ra
> **LEGACY_ADDRESS_FALLBACK eligibility** — một orchestration state riêng, không đổi outcome
> status và không infer từ `failureReason`.
>
> **v7 — safe-degradation policy (ShippingCore-owned):** carrier report FACT
> (status + structured `ShippingFailureReason`); ShippingCore quyết POLICY qua
> `FallbackEligibilityPolicyInterface` — default: TECHNICAL_FAILURE → eligible;
> UNAVAILABLE + `CANONICAL_AMBIGUOUS` → eligible; UNMAPPED / UNSUPPORTED_DESTINATION /
> PROVIDER_MAPPING_MISSING / INVALID_CONFIGURATION / SERVICE_UNAVAILABLE / auth & merchant
> config errors / invalid business input → NOT eligible. KHÔNG convert
> CANONICAL_AMBIGUOUS → TECHNICAL_FAILURE; không parse message; carrier không tự set
> `fallbackEligible`. Reasons mới: `CANONICAL_AMBIGUOUS`, `CANONICAL_UNMAPPED`,
> `INVALID_CONFIGURATION`.

---

## 10. `ShippingFailureReason`

Shared diagnostic reasons hiện tại:

```text
UNSUPPORTED_DESTINATION
CANONICAL_UNRESOLVED
PROVIDER_MAPPING_MISSING
SERVICE_UNAVAILABLE
TECHNICAL_ERROR
```

Rule:

```text
status
= orchestration semantics

failureReason
= diagnostics
```

E-SL1/E-SL2 không được parse reason string để quyết fallback.

---

## 11. Dynamic Shipping Service Level

ShippingCore **không hardcode**:

```text
EXPRESS
SAME_DAY
STANDARD
```

Service levels được đăng ký động qua:

```text
ShippingServiceLevelRegistry
```

Service-level definition:

```text
code
label
enabled
sortOrder
```

Ví dụ Launchpad composition có thể register:

```text
EXPRESS
SAME_DAY
STANDARD
```

Nhưng đây là Launchpad taxonomy, không phải ShippingCore invariant.

---

## 12. Carrier → Service Level

Contract concept:

```text
CarrierServiceLevelInterface
```

> **v7 DEPRECATED:** contract carrier→service-level này không có implementation nào và hướng
> khai báo ngược với mô hình method-membership (§17). Không dùng cho code mới; xoá khi dọn dẹp
> kế tiếp. Membership thật = Mageplaza method → realtime methods (bridge-owned, §17/§18).

Carrier/service membership có thể map như sau ở Launchpad:

```text
Grab            (Type B, P2)
→ EXPRESS

Ahamove         (Type B, P2)
→ EXPRESS / SAME_DAY

GHN             (Type A, P1)
→ STANDARD

GHTK            (Type A, P1)
→ STANDARD
```

> P1 Core chỉ có STANDARD (GHN/GHTK, Type A). EXPRESS/SAME_DAY gắn với Type B (Ahamove/Grab) → P2. Service-level là taxonomy của composition, không phải ShippingCore invariant.

Tuy nhiên ShippingCore hiện chưa bị over-engineer thành carrier routing framework.

Caller/composition chịu trách nhiệm tạo đúng service-level bucket.

---

## 13. E-SL1 — Service-Level Realtime Aggregation

Ví dụ:

```text
STANDARD
├── GHN  → SUCCESS 30k
└── GHTK → UNAVAILABLE (destination unsupported)
```

Aggregate:

```text
successfulRates:
  ghn  → 30k

hasSuccessfulRate = true
hasTechnicalFailure = false
```

Aggregator không chọn:

```text
cheapest
preferred
fastest
```

Ví dụ:

```text
STANDARD
├── GHN  → TECHNICAL_FAILURE
└── GHTK → UNAVAILABLE
```

Aggregate:

```text
successfulRates = []
hasTechnicalFailure = true
```

> **v5:** aggregate vẫn chỉ expose 2 flag trên. Nguồn fallback eligibility **LEGACY_ADDRESS_FALLBACK**
> (§15.1) KHÔNG nằm trong aggregate — nó là orchestration state do strategy config + mapping result
> quyết định ở bước decision (§14). Aggregate không parse reason (giữ nguyên).

---

## 14. E-SL2 — Service-Level Decision

Flow:

```text
service level disabled
→ UNAVAILABLE

realtime SUCCESS exists
→ REALTIME (fallback suppressed — bất kể eligibility từ nguồn nào)

no SUCCESS
+ fallback eligibility exists?
   ├── YES
   │   + fallback policy enabled
   │   + fallback provider exists
   │   → request fallback → FALLBACK
   └── NO
       → UNAVAILABLE
```

**Fallback eligibility — v5 nguồn, v9 SUPERSEDE MỘT PHẦN (xem §35):** taxonomy mở rộng bởi
Rev v9 (§35.5) — legacy RATE strategy là MỘT nguồn; thêm INTEGRATION_LIMITATION (mapping missing
/ capability unsupported / auth-config per policy). Danh sách dưới là historical v5:

```text
TECHNICAL_FALLBACK        — có TECHNICAL_FAILURE trong aggregate (carrier/resolver timeout, 5xx, outage)
LEGACY_ADDRESS_FALLBACK   — carrier RATE có legacy strategy opt-in (§15.1) và mapping
                            AMBIGUOUS/UNMAPPED hoặc strategy = FALLBACK_ONLY (skip mapping)
```

Final decision sources:

```text
REALTIME
FALLBACK
UNAVAILABLE
```

Fallback eligibility là **explicit orchestration state/policy** — KHÔNG infer bằng parsing
`ShippingFailureReason`; UNAVAILABLE mang reason `TECHNICAL_ERROR` vẫn là UNAVAILABLE.
Không carrier ranking.

Không OMS routing.

Không retry framework.

---

## 15. Fallback Policy

Contract:

```text
FallbackPolicyInterface
```

Concept:

```text
service-level-code
→ fallback enabled true/false
```

Default:

```text
DENY
```

Launchpad composition phải opt-in.

Ví dụ:

```text
EXPRESS   → false
SAME_DAY  → true
STANDARD  → true
```

Chỉ là example composition policy.

> **v5:** policy này trả lời *"fallback được phép cho service level này không"* ở mức per-level.
> Nguồn eligibility và legacy RATE strategy xem §15.1 — policy KHÔNG parse reason, KHÔNG chứa
> rules engine.

### 15.1 Legacy RATE Strategy (per carrier-operation — v5)

> **v9 SUPERSESSION:** config axes của §15.1 **đã superseded** bởi `RateSourceMode` +
> `AddressResolutionPolicy` (§35): DIRECT_FALLBACK ≡ FALLBACK_ONLY;
> MAP_THEN_FALLBACK ≡ CARRIER_WITH_FALLBACK + AddressResolutionPolicy::FALLBACK. Constants
> `LegacyRateStrategy` deprecated (migration alias — DEC-FEATYA2C0W-006). Nội dung dưới giữ để
> traceability:

Carrier cần legacy 3-level administrative scheme cho RATE (fee API) trong khi runtime dùng
VN_ADMIN_2025. Composition/carrier configuration khai báo strategy **PER RATE OPERATION**
(capability per-operation §5 — CREATE/CANCEL/TRACK không bị ảnh hưởng):

```text
LegacyRateStrategy (RATE only, P1)
├── FALLBACK_ONLY        — skip legacy mapping + skip carrier RATE API → fallback eligible
└── MAP_THEN_FALLBACK    — map 2025→PRE-2025 trước; AMBIGUOUS/UNMAPPED xử lý như §23
```

* Merchant/composition **explicit opt-in** (configuration) — không có strategy default ngầm.
* Không phải carrier-level disable: CREATE vẫn dùng capability riêng của operation
  (GHN: CREATE = VN_ADMIN_2025 + TEXT_NAME, không đổi).
* ⚠ **Trùng tên có chủ ý nhưng KHÁC concept:** `FALLBACK_ONLY` ở đây là **carrier RATE
  strategy**; `FALLBACK_ONLY` ở §18 là **Bridge Operational Mode** (Mageplaza checkout
  exposure). Hai configuration độc lập, không ràng buộc nhau.

**Semantics:**

```text
FALLBACK_ONLY
→ KHÔNG attempt 2025→PRE-2025 mapping
→ KHÔNG gọi external resolver
→ KHÔNG gọi carrier realtime RATE API
→ carrier contribution đánh dấu explicitly fallback-eligible (LEGACY_ADDRESS_FALLBACK)
→ normal fallback orchestration (§14)

MAP_THEN_FALLBACK
→ local canonical 2025 → PRE-2025 mapping
├── unique deterministic mapping → carrier RATE API (như thường)
├── AMBIGUOUS → external resolver configured/available?
│     ├── có  → selector chọn 1 candidate trong known set (§23)
│     │       ├── resolved  → carrier RATE API
│     │       └── vẫn AMBIGUOUS/unresolved → fallback eligible
│     └── không có resolver → fallback eligible
└── UNMAPPED → fallback eligible (resolver P1 KHÔNG xử lý UNMAPPED — §23)
```

**Fallback eligibility sources (enum conceptual — freeze semantics, tên implementation tự do):**
> **Implementation naming (TASK-5JQYMP):** identity constant được đặt `DIRECT_FALLBACK`
> (≈ wording `FALLBACK_ONLY` ở trên) để tránh collision với Bridge Operational Mode `FALLBACK_ONLY`
> (§18). Mapping documented trong `LegacyRateStrategy` docblock + DEC-FEATYA2C0W-005 addendum —
> semantics không đổi.

```text
TECHNICAL_FALLBACK        — carrier/resolver timeout, connection failure, 5xx, temporary outage
LEGACY_ADDRESS_FALLBACK   — merchant opt-in legacy RATE strategy:
                            FALLBACK_ONLY, hoặc MAP_THEN_FALLBACK + AMBIGUOUS unresolved,
                            hoặc MAP_THEN_FALLBACK + UNMAPPED
```

Configuration ownership (v5, amended v7): legacy RATE strategy + resolver selection
(NONE | VietMap bridge | Google Maps bridge | …) là **carrier/shipping composition configuration**
— ShippingCore chỉ own provider-neutral contracts/orchestration, KHÔNG hardcode strategy/provider;
external resolver bridge own integration VietMap/Google.
**v7 supersede:** mapping `service_level_code → Mageplaza method` KHÔNG CÒN —
`Launchpad_MageplazaTableRate` own **per-method membership + capabilities**
(`show_to_customer` / `use_as_fallback` / realtime members pair `(carrier_code, method_code)`;
Mageplaza Method = fallback grouping identity). Eligibility policy thuần thuộc ShippingCore
(`FallbackEligibilityPolicyInterface`).

---

## 16. `FallbackRateProviderInterface`

ShippingCore chỉ biết abstraction:

```text
getRate(
    serviceLevelCode,
    FallbackRateRequest
)
```

Provider-neutral request có các generic dimensions:

```text
country
region
postcode
weight
subtotal
qty
store
customer group
```

Result:

```text
amount
label
deliveryEstimate
```

Semantic:

```text
provider returns null
→ no fallback rate

provider returns rate amount = 0
→ valid explicit zero shipping rate
```

> **v5:** bridge là **alternate entry path** vào TableRate calculation/business semantics —
> không phải pricing calculator độc lập, không duplicate TableRate rules (§19.1). Contract này
> KHÔNG đổi; eligibility để request fallback quyết ở §14/§15.1.
>
> **v7:** production trigger KHÔNG đi qua pool contract này mà là outer seam composition
> (§18.1) — coordinator giữ Magento `RateRequest` thật (có `destCity`/`destRegionId`) nên
> City/Area dimension dùng runtime context, KHÔNG reopen `FallbackRateRequestInterface`
> (city field chỉ thêm khi một consumer provider-neutral thật yêu cầu). Pool contract vẫn
> hoạt động với bucket code `mptr_<method_id>` (không city — documented limitation).

---

## 17. `Launchpad_MageplazaTableRate`

### Vai trò

Đây là **Launchpad-specific third-party bridge** — **adapter/alternate entry path** vào
TableRate calculation/business semantics của Mageplaza.

Không phải generic Secomm shipping module. Không phải pricing calculator độc lập, không phải
bản duplicate của TableRate rules, không phải shortcut bỏ qua TableRate business semantics.

### Dependency

```text
Launchpad_MageplazaTableRate
├── Secomm_ShippingCore
└── Mageplaza_TableRateShipping
```

Không reverse dependency.

### Chức năng

Implement:

```text
CarrierRateOutcomeCollectorInterface (read) + FallbackEligibilityPolicyInterface (judge)
+ FallbackRateProviderInterface (pool path, bucket mptr_<method_id>)
```

**v7 — mapping superseded:** Mageplaza Method CHÍNH LÀ fallback grouping identity. Per method:

```text
show_to_customer  (bool) — standalone checkout exposure
use_as_fallback   (bool) — method là fallback group
realtime membership: N × (carrier_code, method_code) — pair identity, UNIQUE per method
```

Ví dụ:

```text
Method #10 "Giao tiết kiệm" — use_as_fallback=1
├── (secomm_ghn, secomm_ghn)
└── (ghtk, ghtk_standard)

Method #20 — use_as_fallback=0, show_to_customer=1 → standalone TableRate thuần
```

Admin tự quyết membership từ các Magento carriers ĐÃ CÀI (options động; `mptablerate` excluded);
không hardcode carrier nào, không infer từ label/leadtime. Storage = extension tables
Launchpad-owned với FK ON DELETE CASCADE (method/rate delete → membership/city constraint dọn
sạch). Thành viên không cài/disabled/KHÔNG tham gia collection hiện tại KHÔNG tự tạo outcome
failure — không tự trigger fallback.

---

## 18. Bridge Operational Modes

> **v5 disambiguation — hai concept trùng tên "FALLBACK_ONLY":**
> (1) **Bridge Operational Mode** (mục này) điều khiển **Mageplaza checkout exposure**;
> (2) **Carrier RATE Legacy Strategy** (§15.1) điều khiển **behavior của carrier RATE operation**.
> Hai configuration độc lập, không ràng buộc nhau — đặt tên giống nhau chỉ vì cả hai đều ẩn
> đường bảng giá trực tiếp.
>
> **Behavior requirement (chốt behavior, không hardcode implementation):** trong FALLBACK_ONLY,
> bridge phải reuse Mageplaza TableRate calculation/business semantics của supported subset mà
> không expose TableRate như normal checkout method. Implementation task sau phải audit Mageplaza
> extension để chọn seam phù hợp (internal service/API vs public `collectRates()`) — nếu public
> carrier method dùng `active`/visibility gate khiến FALLBACK_ONLY không chạy được thì KHÔNG
> architecture-hardcode việc gọi `collectRates()`; KHÔNG copy/reimplement TableRate calculation
> logic trong bridge hay ShippingCore.

### v7 — Per-method capabilities thay Bridge Operational Mode (supersede)

Global mode `launchpad_mptablerate/general/mode` (FALLBACK_ONLY/STANDALONE) và cơ chế chính
`carriers/mptablerate/active = 0` **KHÔNG CÒN** là runtime model — `carriers/mptablerate/active`
chỉ là carrier activation của Mageplaza, không dùng để mô phỏng fallback-only per method.

Per Mageplaza method:

```text
show_to_customer = true  + use_as_fallback = false  → standalone TableRate
show_to_customer = false + use_as_fallback = true   → fallback-only (ẩn normal checkout)
show_to_customer = true  + use_as_fallback = true   → standalone + fallback-capable
false / false                                        → không tham gia Launchpad runtime
```

### 18.1 Outer lifecycle seam (fallback trigger)

Plugin `around Magento\Shipping\Model\Shipping::collectRates()` — preference DUY NHẤT của
`RateCollectorInterface` (storefront + REST + GraphQL + admin cùng đi qua):

```text
begin collector bracket (per EXECUTION — Magento có thể collect nhiều lần/request)
→ proceed()  (normal carrier collection; carriers report outcomes)
→ filter show_to_customer=0 methods khỏi Result
→ per fallback group: participating members đọc từ collector
   (any SUCCESS → suppressed; ≥1 policy-eligible → internal TableRate price append)
→ end bracket
```

Không trigger fallback bên trong từng carrier; không dùng nhiều seam duplicate. Membership
KHÔNG thay đổi carrier visibility — không auto-dedup (việc để cả group và members cùng hiển thị
là cấu hình có chủ đích của admin).

---

## 19. Mageplaza Bridge chỉ là PRICE Source

Mandatory rule:

```text
Mageplaza fallback
≠ service eligibility
```

Bridge không được quyết:

```text
Express có thật sự phục vụ địa chỉ này không?
Same Day có nằm trong radius không?
Store/source có đang mở không?
Carrier có coverage không?
Carrier provider mapping có hợp lệ không?
```

Bridge chỉ trả lời:

> Nếu ShippingCore đã quyết định được phép request fallback price cho service level này, giá configured là bao nhiêu?

### 19.1 Visibility ≠ Calculation Eligibility (v5)

```text
normal checkout visibility
≠
fallback calculation eligibility
```

Trong FALLBACK_ONLY: TableRate method KHÔNG được expose như standalone shipping method cho
customer trong normal checkout collection — nhưng khi ShippingCore đã quyết fallback hợp lệ,
đường `ShippingCore → bridge → TableRate calculation path` vẫn lấy được configured fallback rate.

Bridge reuse đúng TableRate semantics nằm trong supported subset (§20): destination conditions,
weight, subtotal, qty, store scope, customer group nếu supported, configured rate/rule matching —
KHÔNG duplicate các rule này trong ShippingCore hay carrier, KHÔNG bypass TableRate business
semantics.

---

## 20. Mageplaza Supported Subset hiện tại

Bridge v1 chỉ guarantee fallback profiles dựa trên:

```text
destination
+
aggregate cart dimensions
```

Ví dụ:

```text
country
region
postcode
weight
subtotal
qty
```

Bridge v1 **không guarantee full Mageplaza parity** cho các capability item-level như:

```text
shipping group
per-item free shipping
ship_type
item volumetric behavior
```

Đây là constrained scope có chủ đích.

Không reopen ShippingCore chỉ để support toàn bộ khả năng Mageplaza khi Launchpad chưa có requirement thật.

---

## 21. Dependency Graph Chuẩn

### Main reusable chain

```text
Magento Directory
       ▲
       │
Secomm_AddressDropdown
       ▲
       │
Secomm_VietNamAddress
       ▲
       │
Secomm_ShippingCore
       ▲
       │
       ├──────────── Secomm_Ghn        (Type A, P1)
       ├──────────── Secomm_Ghtk       (Type A, P1)
       ├──────────── Secomm_Ahamove    (Type B, P2)
       └──────────── other Secomm carriers (Type A Growth / Type B P2)
```

Mũi tên thể hiện:

```text
consumer → dependency
```

Tức là:

```text
Secomm_Ghn → Secomm_ShippingCore
Secomm_ShippingCore → Secomm_VietNamAddress
Secomm_VietNamAddress → Secomm_AddressDropdown
```

### Fallback bridge

```text
Secomm_ShippingCore       Mageplaza_TableRateShipping
          ▲                         ▲
          │                         │
          └──── Launchpad_MageplazaTableRate
```

Bridge depend cả hai:

```text
Launchpad_MageplazaTableRate
→ Secomm_ShippingCore
→ Mageplaza_TableRateShipping
```

Không có:

```text
Secomm_ShippingCore → Launchpad_MageplazaTableRate
```

### COD identification — không tạo cạnh dependency mới

Resolver COD nằm trong `Secomm_ShippingCore` (§4.1); carrier đã depend ShippingCore sẵn nên
graph không thay đổi:

```text
Secomm_Ghn  → Secomm_ShippingCore  (isCod qua CodPaymentMethodResolverInterface)
Secomm_Ghtk → Secomm_ShippingCore  (isCod qua CodPaymentMethodResolverInterface)
```

ShippingCore đọc Magento payment method code qua Magento contracts (ví dụ
`Magento\Sales\Api\Data\OrderInterface` ở phía caller) — không coupling vào một payment
implementation cụ thể, không có cạnh `Secomm_ShippingCore → Secomm_Ghn`.

---

## 22. Forbidden Dependencies

Không được:

```text
Secomm_AddressDropdown
→ Secomm_VietNamAddress
```

Không được:

```text
Secomm_VietNamAddress
→ Secomm_ShippingCore
```

Không được:

```text
Secomm_ShippingCore
→ Secomm_Ghn
```

Không được:

```text
Secomm_ShippingCore
→ Mageplaza_TableRateShipping
```

Không được:

```text
Secomm_Ghn
→ Mageplaza_TableRateShipping
```

Không được:

```text
Secomm_Ghtk
→ Secomm_Ghn
```

Không được để carrier trực tiếp call VietMap/Google để resolve canonical administrative ambiguity:

```text
Carrier
-X-> VietMap / Google
```

Không được để carrier depend vào một COD module riêng — COD identification thuộc
`Secomm_ShippingCore` (§4.1), không có `Secomm_Cod` trong architecture hiện tại:

```text
Secomm_Ghn
-X-> Secomm_Cod

Secomm_Ghtk
-X-> Secomm_Cod
```

---

## 23. External Address Resolver — P1 cho AMBIGUOUS residual (KHÔNG UNMAPPED)

Architecture để seam:

```text
Secomm_ShippingCore
      ↓
ExternalAddressResolverPool
      ↓
Secomm_VietMap
Secomm_GoogleMaps
...
```

**Trạng thái (v8 — dormant extension seam):** consumer thật (GHN.RATE) đã chứng minh gap — một
phường 2025 map ra >1 huyện cũ → không có `district_id` xác định. **VietMap bị LOẠI khỏi backlog**
(yêu cầu geocode — merchant/TL decision 2026-09-16); Google Maps resolver cũng KHÔNG nằm trong
active backlog. KHÔNG có external resolver provider nào được build hay được planning. Seam
(`ExternalAddressResolverInterface` + pool) giữ nguyên như **dormant extension point** — không
phải unfinished P1/P2 work; reopen CHỈ khi real consumer evidence mới + architecture/governance
decision mới. Hiện tại: AMBIGUOUS residual → `UNAVAILABLE` → fallback theo Legacy RATE Strategy (§15.1).

**AMBIGUOUS ≠ UNMAPPED — resolver chỉ xử lý AMBIGUOUS (P#8):**

```text
AMBIGUOUS  (candidate set > 1)  → external resolver CHỌN 1 candidate trong set đã biết
UNMAPPED   (candidate set = 0)  → resolver KHÔNG có gì để "disambiguate"
                                → remain UNRESOLVED (failure_class=UNMAPPED) → UNAVAILABLE
                                → hoặc researched-flow riêng sau, KHÔNG để resolver xử lý
```

> Cho resolver xử lý UNMAPPED sẽ biến nó thành **canonical mapping discovery engine** — scope lớn hơn hẳn và mâu thuẫn với rule selector dưới đây. Chỉ mở UNMAPPED khi GHN real-data chứng minh volume đáng kể + có cách deterministic.

**Rule giới hạn output resolver (P#7 — ghi thẳng để Project Tool không diễn giải rộng):**

```text
External resolver chỉ được:
  - CHỌN giữa các canonical candidate mà VietNamAddress ĐÃ BIẾT (PRE-A | PRE-B | PRE-C), kèm provenance
  - hoặc trả UNRESOLVED
Resolver KHÔNG được:
  - tự mint canonical unit mới (Google/VietMap text → PRE-X)
  - ghi mapping edge authoritative vào mapping table
```

```text
2025 canonical unit
→ VietNamAddress candidate set { PRE-A, PRE-B, PRE-C }
→ external evidence chọn PRE-B (source=EXTERNAL_RESOLVER, provenance)   ✓
```

Ràng buộc giữ nguyên:

```text
- Carrier KHÔNG tự gọi external resolver.
- Resolver gọi ở address-entry (shift-left §5.1), KHÔNG trong rate fan-out.
- Kết quả cache/persist vào CanonicalResolutionSnapshot, KHÔNG promote thành canonical edge.
```

Flow khi cần external disambiguation:

```text
Address entry (shift-left)
→ ShippingCore
→ external resolver (VietMap/GoogleMaps, có cache) — chỉ chọn trong candidate set đã biết
→ CanonicalResolutionSnapshot (status, failure_class, source, provenance) — xem §5.1
→ carrier handoff đọc từ snapshot đã persist
```

Phân biệt failure (nối vào outcome mapping §5.1 — quyết đúng đồng tiền ship):

```text
failure_class=TECHNICAL  (resolver timeout/5xx/quota tại entry)
    → TECHNICAL_FAILURE → fallback TableRate hợp lệ (sự cố kỹ thuật — TECHNICAL_FALLBACK)
    → (tùy chọn) optional future retry outside checkout critical path

failure_class=AMBIGUOUS  (resolver chạy nhưng vẫn >1 candidate, hoặc không có resolver —
                         hiện KHÔNG có resolver provider nào trong backlog, v5.1)
    → UNAVAILABLE (KHÔNG reclassify thành TECHNICAL_FAILURE)
    → LEGACY_ADDRESS_FALLBACK eligible nếu RATE strategy opt-in (§15.1), nếu không → hỏi khách chọn lại phường tại entry

failure_class=UNMAPPED   (candidate set = 0)
    → UNAVAILABLE (KHÔNG reclassify thành TECHNICAL_FAILURE)
    → LEGACY_ADDRESS_FALLBACK eligible nếu RATE strategy opt-in (§15.1)
    → resolver P1 KHÔNG được gọi cho UNMAPPED
```

---

## 24. Runtime End-to-End Expected Flow

Ví dụ customer chọn service level `STANDARD`.

```text
Customer address
      ↓
Secomm_AddressDropdown
      ↓
Secomm_VietNamAddress canonical identity
      ↓
Secomm_ShippingCore address handoff
      ↓
GHN / GHTK
      ↓
provider-specific mapping
      ↓
provider realtime rate API
```

Ví dụ result:

```text
GHN  → TECHNICAL_FAILURE
GHTK → UNAVAILABLE
```

ShippingCore:

```text
aggregate STANDARD
→ no realtime SUCCESS
→ hasTechnicalFailure = true
```

Fallback policy:

```text
STANDARD fallback enabled
```

Sau đó:

```text
Secomm_ShippingCore
→ FallbackRateProviderInterface
→ Launchpad_MageplazaTableRate
→ mapped Mageplaza method
→ fallback amount 35,000
```

Final decision:

```text
service level = STANDARD
rate source   = FALLBACK
amount        = 35,000
```

Customer-facing identity nên là service promise, ví dụ:

```text
Giao tiêu chuẩn
35,000
```

Không fake thành:

```text
GHN 35,000
GHTK 35,000
Mageplaza 35,000
```

---

## 25. Realtime Success Example

```text
GHN  → TECHNICAL_FAILURE
GHTK → SUCCESS 32,000
```

Aggregate:

```text
hasSuccessfulRate = true
hasTechnicalFailure = true
```

Decision:

```text
REALTIME
```

Fallback provider không được gọi.

Rule:

```text
any realtime SUCCESS
→ fallback suppressed for that service level
```

---

## 26. Business Unavailable Example

```text
GHN  → destination unsupported
GHTK → service unavailable
```

Cả hai:

```text
UNAVAILABLE
```

Aggregate:

```text
no realtime success
no technical failure
```

Decision:

```text
UNAVAILABLE
```

Không được dùng Mageplaza TableRate chỉ vì một row pricing tình cờ match.

Mandatory rule:

> Fallback pricing không được biến một service level business-ineligible thành available.

### 26.1 Legacy RATE Strategy Examples (v5 — §15.1)

**Example A — FALLBACK_ONLY**

```text
GHN RATE requires PRE-2025
strategy = FALLBACK_ONLY
→ skip legacy mapping
→ skip GHN rate API
→ fallback eligible (LEGACY_ADDRESS_FALLBACK)
→ FallbackRateProviderInterface
→ Launchpad_MageplazaTableRate
→ TableRate price
```

**Example B — unique mapping (MAP_THEN_FALLBACK)**

```text
strategy = MAP_THEN_FALLBACK
2025 → PRE-2025 unique mapping
→ GHN RATE
→ SUCCESS
→ REALTIME
```

**Example C — AMBIGUOUS + resolver success**

```text
strategy = MAP_THEN_FALLBACK
2025 → PRE candidates A/B
→ VietMap/Google bridge selects B (selector trong known set — §23)
→ snapshot canonical PRE-B
→ GHN RATE
→ SUCCESS
```

**Example D — AMBIGUOUS + no resolver**

```text
strategy = MAP_THEN_FALLBACK
2025 → PRE candidates A/B
→ no external resolver configured/available
→ fallback eligible (LEGACY_ADDRESS_FALLBACK)
→ TableRate bridge
```

**Example E — UNMAPPED**

```text
strategy = MAP_THEN_FALLBACK
2025 → no PRE candidate
→ do NOT call external resolver P1
→ fallback eligible (LEGACY_ADDRESS_FALLBACK)
→ TableRate bridge
```

**Example F — carrier technical failure (TECHNICAL_FALLBACK — nguồn eligibility cũ, giữ nguyên)**

```text
strategy = MAP_THEN_FALLBACK
2025 → PRE-2025 unique mapping
→ GHN RATE
→ timeout
→ TECHNICAL_FAILURE
→ technical fallback eligible (TECHNICAL_FALLBACK)
→ TableRate bridge
```

**Example G — another realtime carrier succeeds (suppression still wins)**

```text
GHN → legacy mapping failed → fallback eligible (LEGACY_ADDRESS_FALLBACK)
GHTK → realtime SUCCESS 32,000
aggregate → hasSuccessfulRate = true
→ REALTIME
→ fallback suppressed — fallback provider KHÔNG được gọi
```

---

## 27. Module Responsibility Summary

| Module | Responsibility | Depends on |
|---|---|---|
| `Secomm_AddressDropdown` | Generic hierarchical address UI/data | Magento Directory |
| `Secomm_VietNamAddress` | VN canonical schemes, units, mapping graph | `Secomm_AddressDropdown` |
| `Secomm_ShippingCore` | Shared address/rate orchestration + COD payment identification + shipment physical facts + physical-limit capability boundary (§33) + carrier eligibility/zones/rate-source/address-policy (§35) | `Secomm_VietNamAddress` |
| `Secomm_Ghn` | GHN provider mapping/API + physical-data interpretation (type 2/5 — §33.16) | `Secomm_ShippingCore` |
| `Secomm_Ghtk` | GHTK provider mapping/API | `Secomm_ShippingCore` |
| `Secomm_ViettelPost` / `_Jt` / `_NinjaVan` | Type A carrier (Growth) provider mapping/API | `Secomm_ShippingCore` |
| `Secomm_Ahamove` | Type B on-demand carrier (P2), lat/lng | `Secomm_ShippingCore` |
| `Secomm_Grab` / `_Lalamove` | Type B on-demand carrier (P2) | `Secomm_ShippingCore` |
| `Launchpad_MageplazaTableRate` | Launchpad fallback pricing bridge | `Secomm_ShippingCore`, `Mageplaza_TableRateShipping` |
| `Mageplaza_TableRateShipping` | Third-party table-rate calculation engine | Mageplaza/Magento dependencies |

---

## 28. ShippingCore Scope Freeze

Current state:

```text
Secomm_AddressDropdown
→ foundation established

Secomm_VietNamAddress
→ canonical layer established

Secomm_ShippingCore
→ foundation COMPLETE / HARD STOP

Launchpad_MageplazaTableRate
→ first real fallback consumer implemented
```

Từ đây không mở thêm generic ShippingCore abstraction chỉ vì có thể sẽ cần trong tương lai.

Chỉ reopen ShippingCore khi:

```text
real carrier
or
real bridge
```

chứng minh một contract gap cụ thể.

> **Contract P1 đã chốt (address resolution)** — gồm đúng các gap consumer thật đã chứng minh:
> - `requiredScheme(op)` / `supportedRepresentations(op)` **per-operation**, scope RATE + CREATE (GHN chứng minh).
> - `CanonicalResolutionSnapshot` với `failure_class` (NONE|AMBIGUOUS|UNMAPPED|TECHNICAL) — để RATE map đúng UNAVAILABLE vs TECHNICAL_FAILURE.
> - External resolver **chỉ cho AMBIGUOUS**, là selector trên candidate set đã biết.
>
> **Contract amendment v4 (COD identification)** — gap nhỏ do consumer thật (GHN/GHTK create-order) yêu cầu, scope tối thiểu:
> - `Secomm_ShippingCore` own configuration khai báo COD payment method codes + resolver
>   `isCod(paymentMethodCode)` cho carrier consume (§4.1). Không mở rộng thêm gì khác của COD.
>
> **Contract P1 đã chốt (address resolution)** — gồm đúng các gap consumer thật đã chứng minh:
> - `requiredScheme(op)` / `supportedRepresentations(op)` **per-operation**, scope RATE + CREATE (GHN chứng minh).
> - `CanonicalResolutionSnapshot` với `failure_class` (NONE|AMBIGUOUS|UNMAPPED|TECHNICAL) — để RATE map đúng UNAVAILABLE vs TECHNICAL_FAILURE.
> - External resolver **chỉ cho AMBIGUOUS**, là selector trên candidate set đã biết.
>
> **Contract amendment v4 (COD identification)** — gap nhỏ do consumer thật (GHN/GHTK create-order) yêu cầu, scope tối thiểu:
> - `Secomm_ShippingCore` own configuration khai báo COD payment method codes + resolver
>   `isCod(paymentMethodCode)` cho carrier consume (§4.1). Không mở rộng thêm gì khác của COD.
>
> **v5 amendment (partial supersede — Legacy RATE strategy):** rule *"chỉ TECHNICAL_FAILURE mới
> fallback eligible"* được supersede MỘT PHẦN — thêm nguồn eligibility **LEGACY_ADDRESS_FALLBACK**
> qua Legacy RATE Strategy opt-in per carrier-operation (§15.1). KHÔNG đổi: outcome statuses
> (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE), failure semantics (AMBIGUOUS/UNMAPPED vẫn UNAVAILABLE,
> không reclassify), realtime-suppresses-fallback, bridge isolation, resolver AMBIGUOUS-only P1.
> ShippingCore reopen scope cho v5: eligibility orchestration (2 nguồn) + snapshot consumption
> cho legacy RATE path. Reopen tiếp chỉ khi carrier/bridge thật chứng minh.
>
> **v6 amendment (Shipment Physical Package Data):** reopen có kiểm soát do consumer thật GHN-D
> CREATE chứng minh — ShippingCore own carrier-neutral physical facts (`ShipmentPhysicalData` /
> `PhysicalPackage`, §33; DEC-TASK9Q5ZAK-001, TASK-9Q5ZAK). Persistence reuse Magento-native
> `sales_shipment.packages`; KHÔNG packaging subsystem. Re-freeze sau khi consumer thật xác nhận.
>
> **v9 amendment (Carrier Eligibility / Zones / Rate Source Mode / Address Resolution Policy —
> §35):** reopen do proposal vận hành yêu cầu merchant config eligibility/zones/mode/policy cho
> carrier RATE. Supersede MỘT PHẦN: LegacyRateStrategy config axes (→ RateSourceMode +
> AddressResolutionPolicy — DEC-FEATYA2C0W-006); eligibility taxonomy v5 "2 nguồn" → normalized
> (§35.5). Implementation = controlled task riêng sau khi v9 accepted.
>
> **v10 amendment (PICK_PRIMARY promoted — legacy-scheme RATE only):** supersede MỘT PHẦN v9/
> DEC-FEATYA2C0W-006 ("PICK_PRIMARY DEFERRED") — PICK_PRIMARY vào P1 AddressResolutionPolicy,
> chỉ applicable khi `requiredScheme(RATE)` là legacy scheme khác runtime scheme; deterministic
> curated-primary selection (KHÔNG $candidates[0]/alphabetical/db-order); DATA_INTEGRITY_DEFECT
> không auto-select; Launchpad default vẫn FALLBACK. Chi tiết §35.4.
>
> **Deferred — KHÔNG build ở P1 (giữ hard stop):**
> - `GEOPOINT` / `GeocodeHandoff` (Type B: Ahamove/Grab) → reopen khi consumer Type B chứng minh.
> - Address representation cho `CANCEL` / `TRACK` → thêm khi carrier chứng minh cần scheme.
> - Resolver cho `UNMAPPED` → chỉ mở khi có evidence volume + cách deterministic.
> - `Secomm_Cod` module / COD framework (surcharge, eligibility, reconciliation, settlement…) →
>   KHÔNG tồn tại trong architecture hiện tại; COD identification thuần thuộc ShippingCore (§4.1),
>   mọi phần mở rộng chỉ reopen khi có business requirement/consumer thật.

---

## 29. Architecture Review Checklist

Khi dùng Project Tool audit code, review từng module theo các câu hỏi sau.

### `Secomm_AddressDropdown`

- Có chứa VN-specific terminology/data/mapping không?
- Có dependency ngược vào `Secomm_VietNamAddress` không?
- Hierarchy có còn generic không?

### `Secomm_VietNamAddress`

- Có provider/carrier IDs không?
- Có shipping/business fallback logic không?
- Canonical resolution có dựa đúng candidate cardinality không?
- Có first-candidate fallback không?

### `Secomm_ShippingCore`

- Có dependency trực tiếp vào carrier hoặc Mageplaza không?
- Có hardcode Launchpad service levels không?
- Có parse `failureReason` để quyết fallback không?
- Có làm carrier ranking/routing không?
- Có mở thêm generic abstraction không có consumer thật không?
- Capability scheme/representation có resolve **per-operation** không (không fix cứng per-carrier)?
- Có gọi external resolver **đồng bộ trong RATE path** không (phải shift-left về address-entry, §5.1)?
- Resolution snapshot có lưu **canonical unit_code** thôi không (KHÔNG provider IDs như GHN district_id/ward_code)?
- Snapshot có `failure_class` phân biệt AMBIGUOUS/UNMAPPED (→UNAVAILABLE) vs TECHNICAL (→fallback) không?
- External resolver có bị giới hạn ở **AMBIGUOUS + selector trên candidate đã biết** không (không xử lý UNMAPPED, không mint canonical)?
- Có sinh `GEOPOINT`/`GeocodeHandoff`/`CarrierType` enum khi chưa có consumer Type B không?
- COD payment methods có được **config-owned** (không hardcode trong code) và expose qua
  provider-neutral `isCod(paymentMethodCode)` (§4.1) không — thay vì mỗi nơi một danh sách?
- Có lấn sang COD framework (surcharge/eligibility/reconciliation/settlement) không — ngoài
  identification thuần thì phải có business requirement/consumer thật?
- **v9:** Zone eligibility có dùng **canonical codes** (không localized name như "Quận 1") và
  evaluate TRƯỚC provider conversion không?
- **v9:** Fallback eligibility có derive từ **status/policy/strategy** (không parse
  `failureReason`) và giới hạn trong source taxonomy đã approved không?
- **v9:** FALLBACK_ONLY có skip resolution/mapping/RATE API (không lãng phí work) và vẫn respects
  CarrierEligibility không?
- **v6:** Shipment physical facts có giữ canonical units (gram/centimeter), không chứa provider
  values (service_type, items[] payload), và `totalWeightG` có nhất quán `sum(packages)` không?
- **v6:** Có cartonization/auto-packing/package-profile engine mọc lên khi Launchpad chỉ cần
  human-confirmed packing không?
- **v6:** Physical-limit values có do carrier modules declare qua `CarrierPhysicalLimitInterface`
  (ShippingCore không hardcode số GHN/GHTK) và final validation vẫn nằm trong carrier adapter
  không — §33.7?
- **v6:** Retry có REPLAY physical snapshot (không recalculate từ catalog), và writer bên ngoài
  (native label flow tương lai) có bị ràng buộc MERGE — không blind-overwrite
  `sales_shipment.packages` không — §33.10/§33.11?
- **v5:** Legacy RATE strategy có được config **per carrier-operation** (không hardcode, không
  default ngầm) không — §15.1?
- **v5:** Fallback eligibility có được quyết từ **STATUS + policy/strategy state** (2 nguồn:
  TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK) chứ không parse `failureReason` không?
- **v5:** Carrier/bridge có bị **bypass** (gọi thẳng Mageplaza calculator, bỏ qua
  `FallbackRateProviderInterface`) không?

### Carrier modules

- Có tự map VN canonical schemes thay vì dùng ShippingCore/VietNamAddress không?
- Có tự call external address resolver không?
- Có depend Mageplaza/TableRate không?
- Provider mapping và API logic có được giữ carrier-owned không?
- Raw provider errors có được classify đúng thành `SUCCESS` / `UNAVAILABLE` / `TECHNICAL_FAILURE` không?
- RATE và CREATE có dùng đúng scheme/representation **riêng theo operation** không (vd GHN: RATE=ID/PRE_2025, CREATE=text/2025 + `is_new_to_address`)?
- COD: carrier có hardcode/tự maintain danh sách COD payment methods, hoặc tự áp COD policy, hoặc depend `Secomm_Cod` không? (Phải hỏi `CodPaymentMethodResolverInterface::isCod(...)` từ ShippingCore — §4.1 — rồi tự map provider-specific COD fields; collect amount đọc từ order tại thời điểm build request theo provider API contract.)
- **v5:** Legacy RATE strategy (RATE cần legacy scheme) có được đọc từ configuration (không
  hardcode, §15.1) không? Khi fallback eligible, carrier có bị bypass bridge (gọi thẳng
  Mageplaza calculator) không — bắt buộc đi qua `FallbackRateProviderInterface`?
- **v6:** Carrier có tự đọc physical facts từ ShippingCore (`ShipmentPhysicalData`) và TỰ map
  sang provider payload không — thay vì ShippingCore encode GHN service_type/items[] hộ?
- **v9:** Carrier RATE có khai báo RateSourceMode + AddressResolutionPolicy qua configuration
  (không hardcode trong ShippingCore/carrier code) không? Provider pricing adjustment
  (buffer/rounding) có vẫn nằm ở carrier không?
- **v6:** Carrier limits có được carrier tự khai báo qua `CarrierPhysicalLimitInterface` và
  enforcement authoritative trong carrier adapter không (ShippingCore chỉ display/pre-check) — §33.7?

### `Launchpad_MageplazaTableRate`

- Có quyết service eligibility không? (eligibility chỉ qua ShippingCore policy — không parse
  message, không set flag tùy tiện)
- Có leak Mageplaza objects vào ShippingCore contracts không?
- Có gọi `collectRates()` cho fallback pricing không?
- **v7:** fallback có trigger ở outer seam SAU khi normal collection hoàn tất không (không
  trigger trong từng carrier, không duplicate seam)?
- **v7:** membership pair `(carrier_code, method_code)` có ổn định, có FK CASCADE, thành viên
  stale/uninstalled có fail-soft không (không tự tạo failure)?
- **v7:** membership có thay đổi carrier visibility không (không auto-dedup)?
- **v7:** City/Area precedence có CHỈ narrow location scope không — SUM/MIN/MAX trong tier
  thắng giữ nguyên; không có city rows = hành vi Mageplaza nguyên vẹn; destination không
  resolve được thì city rows không match?
- **v7:** CSV `city_code` có stable (unit code, không auto-increment id), unknown code có
  fail-loud không?
- Có sử dụng unsupported item-level Mageplaza rules ngoài documented subset không?

---

## 30. Core Architecture Rule

Khi review bất kỳ code nào trong cụm này, câu hỏi chuẩn là:

> **Module này đang làm đúng responsibility của nó, hay đang lấy responsibility của layer bên cạnh?**

Nếu một module cần phụ thuộc ngược hoặc biết implementation detail của layer downstream, đó là dấu hiệu architecture boundary đang bị phá.

---

## 31. Current Recommended Next Validation

Không xây thêm framework.

Validate architecture bằng consumer thật:

```text
real carrier
↓
address handoff
↓
provider mapping/API
↓
CarrierRateOutcome
↓
ServiceLevelRateAggregate
↓
ServiceLevelRateDecision
↓
Launchpad_MageplazaTableRate fallback
↓
checkout QA
```

GHN đã là consumer thật chứng minh gap **per-operation scheme** (RATE PRE_2025+ID vs CREATE 2025+text) — đã amend ở §5/§6/§7. Validation kế tiếp:

```text
1. Ping-test GHN sandbox: xác nhận semantics is_new_to_address (switch match tên 2 cấp mới)
   và xác nhận fee API có honor định dạng mới không (hiện đọc được: fee vẫn ID cũ).
2. Rà GHTK: RATE và CREATE cùng PRE_2025 hay khác → điền capability per-operation.
3. Chạy GHN + GHTK end-to-end qua flow trên.
4. QA shift-left resolve tại address-entry (persist canonical_2025 + resolved_pre2025).
5. **v5 — QA Legacy RATE Strategy (§15.1):** GHN RATE = FALLBACK_ONLY → xác nhận không gọi
   GHN fee API và fallback đi qua bridge; MAP_THEN_FALLBACK → unique mapping realtime /
   AMBIGUOUS+resolver / UNMAPPED; xác nhận any-SUCCESS suppresses fallback (Example F).
6. **v6 — QA Shipment Physical Data (§33):** admin confirm packages trên Create Shipment →
   snapshot ổn định qua retry (đổi product weight sau đó → snapshot không đổi); partial shipment
   prefill theo shipment items; GHN-D map đúng 1-package-nhẹ vs heavy/items[] từ physical facts.
7. **v9 — QA Carrier Eligibility / Rate Source Mode (§35):** SELECTED_ZONES match/no-match →
   eligible/ineligible (canonical codes, trước provider conversion); FALLBACK_ONLY skip
   resolution+RATE API; CARRIER_WITH_FALLBACK + AMBIGUOUS (policy FALLBACK) → fallback; STRICT +
   AMBIGUOUS → unavailable không fallback; auth-config fallback + mandatory warning.
8. **v10 — QA PICK_PRIMARY (legacy-scheme RATE):** AMBIGUOUS + PICK_PRIMARY → deterministic
   selected candidate (same input+dataset version → same result; shuffle candidate order →
   selected không đổi; KHÔNG phải $candidates[0]); FALLBACK_ONLY + PICK_PRIMARY configured →
   selector không được gọi; UNMAPPED + PICK_PRIMARY → không chọn gì; STRICT + AMBIGUOUS →
   unavailable không fallback.
```

Chỉ sau khi flow trên chứng minh **thêm** gap cụ thể mới xem xét amend ShippingCore.

---

## 32. Verified External Facts

> Các claim thị trường/API bên ngoài dùng trong doc này. Ngày verify + nguồn ghi rõ; cái nào chưa confirm để `NEEDS_VERIFICATION`, KHÔNG coi là architecture invariant cho đến khi confirm. Ngày tham chiếu: 2026-09-11.

| Claim | Status | Verified date | Source / cách confirm |
|---|---|---|---|
| GHN có public self-serve API, COD đầy đủ (`cod_amount`, `updateCOD`, callback) | Search | 2026-09-11 | api.ghn.vn/home/docs (create order, COD APIs) |
| GHN create-order nhận **địa chỉ theo tên** (`to_ward_name`/`to_district_name`/`to_province_name`) | Search | 2026-09-11 | api.ghn.vn/home/docs id=122, id=123 (field note "You can input to_ward_name / to_district_name") |
| GHN create có cờ **`is_new_to_address`** switch sang tên **2 cấp mới** | NEEDS_VERIFICATION | — | Field KHÔNG thấy trong docs public id=122/id=123 đã fetch; do team đọc từ live API. Ping-test sandbox để confirm semantics (§31.1) |
| GHN **fee/rate API vẫn dùng địa chỉ cũ + ID cũ** (`district_id`+`ward_code`) | NEEDS_VERIFICATION | — | Reported từ team + search; chưa confirm bằng fetch định danh. Ping-test sandbox (§31.1) |
| GHN create-order **physical limits**: `weight` ≤ 50,000 g; `length`/`width`/`height` ≤ 200 cm mỗi chiều | Verified (docs) | 2026-09-15 | developer.ghn.vn Create Order contract; evidence TASK-FMBBSD matrix §5 (`.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md`) |
| GHN type-5 create: root `weight` **provider-MANDATORY** (sandbox 400 'required' nếu thiếu), mang **factual Σ** các packages; Σ > 50,000 g được chấp nhận khi `items[]` có per-package weights | Sandbox-verified | 2026-09-15 | GHN sandbox probe (TASK-9Q5ZAK r3) |
| GHN type-5 create: root `length`/`width`/`height` **không bắt buộc** khi `items[]` mang dims → OMIT, không synthetic aggregate box | Sandbox-verified | 2026-09-15 | GHN sandbox probe (TASK-9Q5ZAK r3) |
| GHTK có public OpenApi, COD (`pick_option: cod`) + đối soát COD, address text-based | Search | 2026-09-11 | docs.giaohangtietkiem.vn (OpenApi, submit order, fee) |
| GHTK scheme per-operation (RATE vs CREATE cùng PRE_2025 hay khác) | NEEDS_VERIFICATION | — | Chưa rà; validation §31.2 |
| SPX (Shopee Express) **không có public API** (platform-locked) | Search | 2026-09-11 | Xác nhận qua search + xác nhận nội bộ team |
| ViettelPost có Open API, COD, nhưng **cần onboarding partner qua sales** | Search | 2026-09-11 | partner.viettelpost.vn / Open API docs |
| J&T Express VN: API qua **cooperation agreement** (không self-serve) | Search | 2026-09-11 | Search; onboarding/cooperation model |
| Ninja Van: NinjaAPI, **partner onboarding** | Search | 2026-09-11 | Search; partner integration model |
| Carrier VN phần lớn vẫn chạy **địa chỉ 3 cấp**, hỗ trợ song song 2 cấp, chưa hãng nào bắt buộc 2 cấp | DATED (2025-08) | 2025-08 | Nhanh.vn Open API note (tháng 8/2025) — có thể đã dịch chuyển; re-check khi go-live |
| COD ~35% đơn ecommerce VN; GHN/GHTK phí COD thấp nhất ~1.5% | DATED (2026-06) | 2026-06 | Nguồn aggregator ngành (tham khảo, không phải architecture-critical) |

> Nguyên tắc: khi một claim `NEEDS_VERIFICATION` được confirm, cập nhật Status + Verified date + Source tại đây, rồi mới treat nó như fact ổn định cho Project Tool.
>
> **2026-09-16 — Backlog decision (merchant/TL):** external address resolver (VietMap/Google
> bridge) **bị LOẠI khỏi backlog** — VietMap yêu cầu geocode, không phù hợp scope Launchpad.
> `ExternalAddressResolverInterface` + pool giữ nguyên làm extension point; reopen chỉ khi có
> provider không cần geocode. AMBIGUOUS residual trên legacy RATE → UNAVAILABLE → fallback theo
> Legacy RATE Strategy (§15.1).

---

## 33. ShippingCore — Shipment Physical Package Data (v6)

> Consumer thật chứng minh gap: GHN-D CREATE cần physical facts của shipment đã đóng hàng
> (mỗi thùng nặng bao nhiêu, kích thước nào) để chọn service type và build provider payload —
> không thể suy từ catalog. Amendment thêm **carrier-neutral physical model** vào ShippingCore
> mà không đổi address-resolution/fallback/tracking architecture. DEC: DEC-TASK9Q5ZAK-001;
> implementation: TASK-9Q5ZAK (GHN-D r2/r3).

### 33.1 Terminology

```text
Product           = Magento catalog/order item (weight/dimensions = thuộc tính sản phẩm).
Magento Shipment  = MỘT fulfillment event.
Physical Package  = MỘT thùng/bao/gói đã đóng THẬT, bàn giao cho carrier.
Carrier Order     = shipment/order phía provider, tạo qua carrier API.
```

Invariant cốt lõi:

```text
1 Magento Shipment  →  CÓ THỂ chứa N Physical Packages.

KHÔNG được assume:  1 Physical Package = 1 Magento Shipment.
```

Nhiều Magento Shipments nghĩa là **các fulfillment event riêng biệt** — không phải chỉ là
nhiều thùng hàng. Một shipment nhiều thùng vẫn là MỘT fulfillment event.

### 33.2 Ownership — ShippingCore owns physical facts, carrier owns interpretation

```text
ShippingCore owns PHYSICAL FACTS (carrier-neutral):
  ShipmentPhysicalData { totalWeightG (gram), packages[] }
  PhysicalPackage      { weightG (gram), lengthCm, widthCm, heightCm (cm) }
  CarrierPhysicalLimit capability boundary (§33.7)
  generic sanity validation: giá trị > 0, packages >= 1 khi confirmed

Carrier modules own PROVIDER INTERPRETATION:
  ShipmentPhysicalData → provider request
  provider limit VALUES + provider-specific validation
  provider payload shapes (root fields / items[] / products[] / pieces / separate requests)
```

Đơn vị canonical: **gram / centimeter, integer** — normalize một lần ở ShippingCore, không để
mỗi carrier tự quy đổi từ store weight unit.

Contracts (implemented — TASK-9Q5ZAK):

```text
Secomm\ShippingCore\Api\Physical\PhysicalPackageInterface          { getWeightG/getLengthCm/getWidthCm/getHeightCm — int }
Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface     { getTotalWeightG (Σ derived)/getPackages (>= 1) }
Secomm\ShippingCore\Api\Physical\CarrierPhysicalLimitInterface     { getMaxPackageWeightG/getMaxLengthCm/getMaxWidthCm/getMaxHeightCm/supportsMultiplePackages }
Secomm\ShippingCore\Model\Physical\{PhysicalPackage, ShipmentPhysicalData}  — immutable VO, invariant fail-fast
Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister       — persist/read trên sales_shipment.packages (§33.9)
Secomm\ShippingCore\Model\Physical\ConfiguredDefaultPackageDimensions — merchant default dims, PREFILL-only (§33.4)
Secomm\ShippingCore\Model\Physical\StoreWeightConverter            — store weight unit → gram, fail-closed với unit lạ
```

### 33.3 PhysicalPackage semantics

`PhysicalPackage` đại diện **thùng hàng thực tế SAU KHI warehouse đóng hàng** — operational
fact được confirm tại thời điểm shipment.

KHÔNG đại diện:

```text
Magento Product
Magento Order Item
GHN item
GHTK product
provider parcel DTO nào đó
```

Product dimensions có thể hỗ trợ warehouse operations, nhưng Launchpad Core **không được infer**
final package dimensions bằng:

```text
sum dimensions
max dimensions
cube-root volume
cartonization
3D bin packing
automatic carton selection
```

Giá trị physical là fact **được confirm tại thời điểm shipment** — không phải giá trị suy luận.

### 33.4 Launchpad Core operational model

```text
Default:  1 Shipment → 1 Physical Package
```

Admin/warehouse cung cấp weight + length/width/height trên shipment (editable, confirm trước
khi CREATE):

- **Weight** prefill từ shipment items (qua `StoreWeightConverter` — store unit → gram, fail-closed).
- **Dimensions** prefill từ merchant default package configuration
  (`secomm_shippingcore/physical/default_package_*` qua `ConfiguredDefaultPackageDimensions`;
  unset = không prefill — admin phải nhập giá trị thật).

```text
default dimensions  ≠  confirmed physical dimensions
prefill             ≠  confirmed truth
```

Giá trị dùng cho carrier CREATE là **physical data đã confirm** trên shipment — không phải config,
không phải giá trị suy từ catalog. Launchpad Core **không phải** hệ thống cartonization hay
warehouse-optimization.

Partial shipments: physical data thuộc **TỪNG shipment** (prefill theo shipment items/qty —
không dùng full order weight).

### 33.5 Multi-package

Data model là `PhysicalPackage[]`; admin/warehouse UX mặc định 1 package:

```text
Order
  ↓
Shipment #1
  ├── Physical Package #1
  ├── Physical Package #2
  └── Physical Package #3
```

…vẫn có thể tạo ra **1 carrier order** — tùy semantics của carrier.

ShippingCore **KHÔNG quyết** provider APIs represent các packages này như root fields, items,
products, pieces, hay separate requests — đó là provider interpretation (§33.2/§33.15).

### 33.6 Carrier-neutral total weight

```text
ShipmentPhysicalData.totalWeightG  = carrier-neutral fact
totalWeightG = sum(packages[].weightG)      (invariant-enforced — 1 source of truth, không duplicate lệch)
```

```text
totalWeightG KHÔNG ngụ ý mọi carrier phải gửi nó như một root API field.
```

Carrier adapter tự quyết map nó vào đâu trong provider request (GHN type-5: root `weight`
provider-mandatory mang Σ — xem §33.16; carrier khác có thể không gửi).

### 33.7 Carrier physical-limit capability boundary

ShippingCore consume một generic capability declaration — chỉ chứa **shared UI/validation facts**
(để admin hiển thị limits + pre-submit validation), không phải provider payload semantics:

```text
Secomm\ShippingCore\Api\Physical\CarrierPhysicalLimitInterface
├── getMaxPackageWeightG(): int
├── getMaxLengthCm(): int
├── getMaxWidthCm(): int
├── getMaxHeightCm(): int
└── supportsMultiplePackages(): bool
```

Ownership bắt buộc:

```text
Limits KHAI BÁO bởi carrier modules:
  Secomm_Ghn                  → GhnPhysicalLimit (GHN limits — §33.16)
  Secomm_Ghtk                 → GHTK limits
  Secomm_ViettelPost (tương lai) → VTP limits

ShippingCore KHÔNG hardcode giá trị limit cụ thể của carrier nào.
```

Carrier-specific validation **vẫn authoritative bên trong carrier module**: capability này chỉ
phục vụ display/pre-check; enforcement thật nằm ở carrier adapter (GHN: per-package limit check
fail-closed `INVALID_PARCEL` trong `GhnPhysicalParcelInterpreter`, trước mọi HTTP call).

### 33.8 RATE vs CREATE — độ chắc chắn physical khác nhau

```text
RATE   xảy ra TRƯỚC khi warehouse đóng hàng.
CREATE xảy ra SAU/TẠI thời điểm shipment packing.
```

Do đó:

```text
RATE   có thể chạy với thông tin physical thiếu/ước lượng (cart/shipment available info).
CREATE dùng confirmed ShipmentPhysicalData.
```

KHÔNG yêu cầu RATE dùng CREATE physical-package snapshots — checkout chưa có trusted package
information. Đây là hai level of certainty có chủ đích, không phải defect.

GHN rule hiện tại: **type-5 RATE vẫn là backlog riêng** cho đến khi checkout có thông tin
package đáng tin — không phải defect của ShippingCore physical model.

### 33.9 Persistence — immutable shipment snapshot

Carrier CREATE phải dùng **stable physical snapshot**:

```text
retry cùng một Magento Shipment → cùng physical package facts.
```

Thay đổi các nguồn sau attempt CREATE **không được** âm thầm đổi retry payload:

```text
product dimensions
merchant default dimensions
shipment-item source data
```

Current implementation (mô tả hiện trạng, KHÔNG phải eternal architectural requirement):

```text
sales_shipment.packages  — JSON column Magento-native (không bảng mới, không packaging subsystem)
marker key: secomm_physical → {'secomm_physical': [[weightG, lengthCm, widthCm, heightCm], ...]}
persist: SAU shipment save (snapshot gắn shipment đã có entity)
read:    malformed marker → null (fail closed upstream)
```

Physical facts là ShippingCore state; bảng provider-state của carrier (ví dụ
`secomm_ghn_shipment`) giữ provider state, KHÔNG mirror physical payload.

### 33.10 `sales_shipment.packages` — Magento package-field compatibility rule

Kết luận hiện tại:

```text
sales_shipment.packages = COMPATIBLE_WITH_CONVERSION
```

Trạng thái hôm nay: `Secomm_Ghn::isShippingLabelsAvailable() = false` (GHN-D) → Magento native
label package writer không chạy → chưa có conflict.

Persistence behavior bắt buộc:

```text
MERGE Secomm physical marker VỚI các package entries non-Secomm/native
— KHÔNG bao giờ blind-replace toàn bộ cấu trúc packages.
```

(`ShipmentPhysicalPersister` chỉ replace/remove marker key, giữ nguyên mọi entry khác.)

Forward-compatibility invariant:

```text
Nếu một carrier sau này bật Magento native shipping-label capability,
label integration PHẢI preserve hoặc convert snapshot `secomm_physical`
— KHÔNG được blind-overwrite `sales_shipment.packages`.
```

Áp dụng cho future GHN-E và mọi carrier bật native labels.

### 33.11 Idempotency — snapshot + carrier request identity

Generic requirement (không GHN-specific):

```text
ShipmentPhysicalData snapshot  +  stable shipment-level carrier identity
→ phải ổn định qua các retry.

Physical snapshot được REPLAY, không recalculate.
```

Ví dụ GHN hiện tại (carrier-owned — KHÔNG nằm trong generic ShippingCore contract):

```text
client_order_code = GHNS{shipment_id}
```

Anchor row ghi TRƯỚC POST; retry/reconciliation đi bằng CÙNG `client_order_code` — không bao
giờ blind re-create. Identifier `GHNS{id}` là của `Secomm_Ghn`; generic contract chỉ là
*"stable physical snapshot + stable carrier request identity"*.

### 33.12 Non-goals — Launchpad Core

KHÔNG nằm trong ShippingCore / Launchpad Core:

```text
cartonization engine
3D bin packing
automatic carton selection
package profile optimization
warehouse slotting
automatic multi-package splitting
carrier volumetric optimization
OMS packing engine
```

Có thể thành optional capabilities trong tương lai CHỈ khi merchant demand thật chứng minh.

Cũng cấm trong ShippingCore (provider semantics — không bao giờ): `service_type_id`, GHN
`items[]`, GHTK `products[]`, ViettelPost `PRODUCT_*`, provider volumetric formulas, provider
aggregation rules, box catalog. Launchpad workflow = human-confirmed physical packing.

### 33.13 Dependency boundary

```text
Magento Shipment / Admin
        ↓
Secomm_ShippingCore
        ↓
ShipmentPhysicalData
        └── PhysicalPackage[]
        ↓
Carrier module
        ↓
provider-specific interpreter
        ↓
provider API
```

```text
Secomm_Ghn                    → GHN type 2 / type 5 interpretation (§33.16)
Secomm_Ghtk                   → GHTK-specific interpretation
Secomm_ViettelPost (tương lai) → VTP-specific interpretation
```

Carrier modules **không được** đẩy provider semantics ngược lên ShippingCore.

### 33.14 Quan hệ với address architecture — orthogonal

Physical shipment data **orthogonal** với canonical address resolution — hai concerns riêng,
họp lại ở carrier CREATE:

```text
Magento Shipment
        ↓
ShippingCore CREATE operation

Address side:
Magento address
→ canonical resolution (§5)
→ CanonicalResolutionSnapshot

Physical side:
admin/warehouse
→ ShipmentPhysicalData (snapshot §33.9)

        ↓
Carrier module

Carrier:
canonical address → Stage-2 provider mapping (§7)
physical data     → provider parcel interpretation

        ↓
provider CREATE API
```

Mandatory:

```text
KHÔNG mix physical package information vào CanonicalResolutionSnapshot.
```

Fallback price/snapshot resolution cũng KHÔNG liên quan physical facts.

### 33.15 Cross-carrier rationale — facts generic, interpretation carrier-specific

```text
GHN:
  type 2 → root dimensions
  type 5 → items[] physical packages

GHTK:
  products[] — semantics KHÁC, không tương đương GHN packages

Viettel Post:
  dùng provider-specific root shipment dimension fields
```

→

```text
ShippingCore KHÔNG standardize provider payload semantics.
```

Abstraction đúng:

```text
Physical facts → carrier interpretation → provider payload
```

### 33.16 GHN reference implementation (carrier-specific example — KHÔNG phải ShippingCore rule)

Toàn bộ subsection này là **ví dụ interpretation của `Secomm_Ghn`** — không standardize thành
generic rules. Implementation: `GhnPhysicalLimit`, `GhnPhysicalParcelInterpreter`,
`GhnParcelPlan`, `GhnShipmentCreationService` (idempotency §33.11).

**Type 2 (Hàng nhẹ):**

```text
1 physical package  AND  weight < 20,000 g
→ service_type_id = 2
```

GHN request dùng root physical fields:

```text
weight
length
width
height
```

Không có GHN heavy `items[]`.

**Type 5 (Hàng nặng):**

```text
heavy package (>= 20,000 g)  OR  multiple physical packages
→ service_type_id = 5
```

GHN dùng `items[]` với:

```text
one GHN item = one Physical Package
quantity = 1
weight / length / width / height = exact per-package values
```

**Root weight cho type 5 — provenance ghi rõ:**

```text
root weight = ShipmentPhysicalData.totalWeightG   (factual Σ)
```

Đây là **verified current GHN sandbox behavior, KHÔNG phải ShippingCore rule**:

```text
- root `weight` provider-MANDATORY: sandbox từ chối create thiếu field ('required' validation)
- Σ > 50,000 g CHẤP NHẬN khi items[] mang per-package weights
- root length/width/height: OMITTED (provider không yêu cầu khi items[] có dims)
  — không bao giờ synthetic aggregate box
```

(Provenance: GHN sandbox probe TASK-9Q5ZAK r3, 2026-09-15 — behavior có thể đổi theo provider;
xem §32 Verified External Facts.)

**GHN physical limits (carrier-owned — chỉ bên trong ví dụ GHN này):**

```text
PER physical package:
  weight ≤ 50,000 g
  length ≤ 200 cm
  width  ≤ 200 cm
  height ≤ 200 cm
```

KHÔNG describe `ShipmentPhysicalData.totalWeightG ≤ 50kg` như một rule type-5 của GHN —
multi-package shipment CÓ THỂ có tổng physical weight > 50kg nếu từng package individually
valid và provider chấp nhận consignment (sandbox-proven). Limits expose cho admin UI qua
`GhnPhysicalLimit` (§33.7); enforcement fail-closed trong `GhnPhysicalParcelInterpreter`.

**Trạng thái GHN-D r3 (runtime/sandbox-proven):**

```text
Type 2 CREATE                                        ✓ proven
Type 5 single-heavy CREATE                           ✓ proven
Type 5 multi-package CREATE                          ✓ proven
total shipment > 50kg với từng package valid          ✓ proven
per-package limit validation (fail-closed trước HTTP) ✓ proven
stable retry snapshot                                 ✓ proven
```

(Không ghi sandbox order IDs / runtime credentials vào architecture SSOT — evidence runtime
thuộc work-item records, không phải architecture contract.)


---

## 35. Carrier Eligibility / Destination Scope / Rate Source Mode / Address Resolution Policy (v9)

### 35.1 CarrierEligibility + DestinationScope

```text
DestinationScope::ALL              → eligible cho mọi canonical destination hợp lệ
DestinationScope::SELECTED_ZONES   → eligible khi destination thuộc ≥1 configured canonical zone
```

Evaluate bằng **canonical address identity TRƯỚC provider conversion** (không provider IDs
trong zone logic). Merchant eligibility config per carrier — KHÔNG phải provider/API health
("eligibility" ≠ "availability").

### 35.2 Canonical Zones (ShippingCore-owned generic evaluation)

```text
ZoneDefinition: code, label, enabled,
                include province codes, include ward codes, exclude ward codes
```

Zone CODES là merchant/composition data (HCM_INNER/HCM_OUTER/INTERPROVINCE chỉ là example —
không domain enum, không hardcode tên "Quận 1"/"huyện" theo localized name). INTERPROVINCE
origin-relative → `CarrierEligibilityContext { canonical destination, canonical origin khi cần }`.
KHÔNG DSL/expression engine/GIS/polygon.

### 35.3 RateSourceMode (per carrier RATE operation)

```text
CARRIER_ONLY           → eligible → carrier RATE → no valid rate → KHÔNG fallback
CARRIER_WITH_FALLBACK  → carrier RATE trước → fallback khi failure/state fallback-eligible (default Launchpad)
FALLBACK_ONLY          → skip resolution/mapping/RATE API → normal fallback orchestration
```

FALLBACK_ONLY vẫn respects CarrierEligibility (destination ngoài scope → contribution unavailable,
KHÔNG expose fallback price giả eligibility).

### 35.4 AddressResolutionPolicy (v10 — PICK_PRIMARY promoted vào P1)

```text
STRICT        → AMBIGUOUS → unavailable, không fallback vì ambiguity (trừ technical failure độc lập)
FALLBACK      → AMBIGUOUS → không auto-select candidate → address-related fallback eligible
                (thực tế vẫn qua RateSourceMode + policy)
PICK_PRIMARY  → AMBIGUOUS → deterministic selection của curated primary candidate → carrier path
```

**PICK_PRIMARY applicability (v10):** chỉ áp dụng khi `requiredScheme(RATE)` là **legacy scheme
khác runtime scheme** (cross-scheme mapping có thể AMBIGUOUS). Carrier RATE dùng current
canonical identity hoặc không có cross-scheme ambiguity → PICK_PRIMARY **not applicable**
(config/UI ẩn/vô hiệu hoá, không lỗi). Không leak vào CREATE/CANCEL/TRACK. `FALLBACK_ONLY`
**không evaluate** AddressResolutionPolicy (RATE path skipped — không mapping/selector vô ích).

**Deterministic selection basis (v10):** candidate được chọn qua **curated primary designation
trong mapping dataset** (`is_primary`/`rank` trên mapping edge — curated/import-declared),
KHÔNG phải: alphabetical order, database row order, candidate code order, fuzzy text similarity.
`same canonical input + same mapping dataset version → same selected candidate`.

**DATA_INTEGRITY_DEFECT guard (§16):** selector KHÔNG được dùng để "giữ checkout chạy" qua data
hư hỏng — candidate set chứa defect chưa curated (uncurated split/missing district) → không
designate primary → không pick. Chỉ curated designation đủ tin cậy cho deterministic selection.

**Snapshot provenance (v10 — minimal additions):** `selection_policy` (PICK_PRIMARY),
`selection_reason` (curated-primary designation basis), `candidate_count` — selected candidate
KHÔNG lưu trùng (đã là `resolved_pre2025.ward_unit_code`). Không provider IDs.

**Launchpad default vẫn FALLBACK; PICK_PRIMARY = merchant explicit opt-in per applicable carrier
RATE (không global default).**

### 35.5 Fallback Eligibility normalization (supersede §14 "2 nguồn")

```text
technical timeout / connection / 5xx / outage            → fallback YES (TECHNICAL_FALLBACK)
provider mapping missing                                  → fallback YES (INTEGRATION_LIMITATION — outcome vẫn UNAVAILABLE, không reclassify)
capability unsupported                                    → fallback YES
address AMBIGUOUS + policy FALLBACK                       → fallback YES
auth/configuration failure                                → configurable YES + MANDATORY high-severity admin/operational warning
invalid customer address                                  → fallback NO
provider out-of-service-area / coverage rejection         → fallback NO
real business rejection                                   → fallback NO
```

Nguyên tắc: technical/integration/capability limitation → fallback có thể được phép; real
business rejection → fallback KHÔNG được mask. Outcome taxonomy `CarrierRateOutcome`
(SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE) KHÔNG đổi — separation outcome semantics vs fallback
eligibility; mapping-missing KHÔNG reclassify thành TECHNICAL_FAILURE. Auth/config fallback
phải kèm mandatory high-severity diagnostic/admin warning seam (không mask lỗi cấu hình).

> **Phase C contract amendment (TASK-8MQHJX, 2026-09-18, TL-approved — KHÔNG tạo v11):**
> `FallbackEligibilitySource.INTEGRATION_LIMITATION` được thêm vào contract, đóng một
> representational gap phát hiện khi implement (frozen §35.5 đã named nguồn này từ Rev v9 —
> "supersede §14: 2 nguồn" — nhưng contract 2 nguồn của TASK-5JQYMP không represent được
> và từ chối mislabel thành TECHNICAL_FALLBACK/LEGACY_ADDRESS_FALLBACK). Amendment là generic
> contract change, không carrier-specific.
>
> **v10 RUNTIME ORCHESTRATION = COMPLETE (TASK-8MQHJX Phase D, 2026-09-18):**
> - `CarrierEligibility` runtime ACTIVE (`CarrierEligibilityEvaluator` + matcher + registry;
>   eligibility chạy trước mode/policy/realtime — ineligible carrier = 0 realtime + 0 fallback).
> - `CanonicalZone` runtime ACTIVE (canonical static geography only — VN-xx/VNA codes; không
>   GIS/distance/provider-ID/localized-text matching).
> - `RateSourceMode` upstream gating ACTIVE (`CarrierRateExecutionService`; FALLBACK_ONLY skip
>   origin/policy/realtime; realtime mode + unresolved origin fail-closed, không re-mode).
> - `AddressResolutionPolicy` gated SAU eligibility/mode, chạy qua shared handoff service
>   (STRICT/FALLBACK/PICK_PRIMARY — carrier chỉ nhận 1 destination đã chọn, không leakage).
> - `CarrierRateExecution` ACTIVE (provider-neutral contributor seam; carrier modules implement,
>   không default). `ServiceLevelRateOrchestrator` = final aggregator + realtime-success
>   suppression + fallback dispatch owner (không parallel orchestration path).
> - `INTEGRATION_LIMITATION` source ACTIVE end-to-end (execution emit → orchestrator dispatch
>   cho UNAVAILABLE+PROVIDER_MAPPING_MISSING khi mode permits; outcome vẫn UNAVAILABLE).
> - DEFERRED contract follow-up: provider-auth/config warning seam (§35.5 configurable-YES) —
>   chưa có consumer runtime; cần constant provider-auth riêng + mandatory warning seam khi
>   làm. KHÔNG overload INVALID_CONFIGURATION/TECHNICAL_FAILURE/INTEGRATION_LIMITATION.
> - Runtime quote smoke: BLOCKED_BY_ENVIRONMENT (sandbox credentials có; origin_district_id
>   unset + catalog trống). Evidence: `.ai/evidence/TASK-8MQHJX/phase-d.md`. Ý nghĩa: realtime contribution không tạo được vì
> shared/provider integration data, mapping, adapter readiness thiếu — VÀ KHÔNG phải carrier
> business/service rejection confirmed. Frozen case duy nhất được map hiện tại:
> UNAVAILABLE + PROVIDER_MAPPING_MISSING (outcome vẫn UNAVAILABLE). `INVALID_CONFIGURATION`
> KHÔNG map — reason contract frozen định nghĩa merchant-side carrier configuration
> (fail-closed); seam auth/config configurable-YES của §35.5 cần constant provider-auth riêng
> + warning seam mandatory trước khi có thể degrade an toàn (ambiguity hẹp còn lại, ghi nhận
> cho Phase D; KHÔNG mở rộng eligibility chỉ để test pass).

### 35.6 Processing order

```text
Canonical Destination
→ Carrier Eligibility (DestinationScope + zones)
→ Rate Source Mode
   FALLBACK_ONLY      → skip resolution/mapping/RATE API → fallback eligibility trực tiếp
   CARRIER_*          → Address Resolution Policy → provider mapping → carrier RATE API
                        → failure classification → fallback eligibility → service-level decision
```

### 35.7 Launchpad defaults (proposal — chốt khi implement)

```text
DestinationScope = ALL; RateSourceMode = CARRIER_WITH_FALLBACK; AddressResolutionPolicy = FALLBACK
fallback: technical ON · mapping-missing ON · ambiguous ON · capability-unsupported ON ·
auth/config ON + mandatory warning · invalid-address OFF · out-of-service-area OFF ·
business-rejection OFF · PICK_PRIMARY = merchant explicit opt-in (P1 — legacy-scheme RATE only; v10)
```

### 35.8 LegacyRateStrategy migration (superseded — §15.1)

```text
DIRECT_FALLBACK     → RateSourceMode::FALLBACK_ONLY
MAP_THEN_FALLBACK   → RateSourceMode::CARRIER_WITH_FALLBACK + AddressResolutionPolicy::FALLBACK
```
Constants `LegacyRateStrategy` deprecated (alias transition — không xoá khỏi history); carriers
không còn consumer thật của constants này (0 external consumer — đã audit).

### 35.9 Ownership boundaries

ShippingCore owns: CarrierEligibility, Destination Scope, canonical Zones, Rate Source Mode,
Address Resolution Policy, Fallback Eligibility Policy, generic rate orchestration. Carrier owns:
provider address rendering, provider mapping, provider rate request, provider capabilities/service
types, provider physical limits, provider-specific pricing adjustment (buffer/rounding — KHÔNG
move vào ShippingCore), provider errors → common classification. ShippingCore KHÔNG được mọc
carrier-specific conditionals.
