# SPIKE-W273TB — Audit `Secomm_ShippingCore` + đề xuất orchestration canonical VN address resolution

> **Analysis/design only — 0 production code.** Record: `.ai/records/spikes/SPIKE-W273TB.md`.
> Anchor: DEC-FEATYA2C0W-003 (canonical layer) + DEC-FEATYA2C0W-004 (D1–D10). Ngày: 2026-09-04.
> Prose: VI. Contracts: PHP shapes (chưa implement).

---

## 1. Current state (theo code thật, có file:line)

### 1.1 `Secomm_ShippingCore` — 25 files, 1.554 dòng

| Capability | Thực tế có | Ghi chú |
|---|---|---|
| ShippingContext | ✅ `Api/ShippingContextInterface` (storeId, websiteId, carrierCode, quoteId, sourceCode — scalar-only immutable) + `Model/ShippingContext` + `Model/ShippingContextFactory::fromRateRequest()` | Rate path dùng; shipment path là "future" (docblock `ShippingContextInterface.php:17-19`) |
| Origin | ✅ `Api/OriginInterface` (sourceCode, countryId, **regionId**, province, **district nullable**, **ward (text)**, street, postcode, telephone, contactName, metadata dotted keys `ghtk.pick_address_id`) + `Model/Origin` | VO thuần, không có unit_code/scheme (`OriginInterface.php:25-55`) |
| OriginProvider | ✅ `Api/OriginProviderInterface` + default `Model/OriginProvider/ShippingOriginProvider` — đọc Magento Shipping Origin config; **ward = config `city` (TEXT), district = null, không có city_id/unit_code** (`ShippingOriginProvider.php:62-73`) | Extension point duy nhất của address hiện tại; "Returns a data snapshot only" (`:32-33`) |
| Tracking | ✅ pipeline đầy đủ (webhook/API/cron → `ShipmentTrackingProcessor`, `secomm_carrier_tracking_state`) | Carrier-neutral hoàn toàn, không liên quan address |
| Carrier/provider abstraction | ❌ **KHÔNG có** — không có collectRates abstraction, không có capability, không có rate request DTO chung. Carrier tự implement `collectRates` (`GiaoHangNhanh/Model/Carrier/GHN.php:138`) | Prompt hỏi "rate request abstractions?" — câu trả lời: **chưa tồn tại** |
| Address resolution | ❌ KHÔNG có gì | — |
| Config | ❌ Không có namespace riêng (origin dùng config core `shipping/origin/*`) | Chưa có `Stores > Secomm > Shipping` |
| DB | 1 bảng: `secomm_carrier_tracking_state` (`etc/db_schema.xml`) | — |
| Dependencies | Magento Framework/Store/Directory/Shipping **ONLY**; **KHÔNG depend `Secomm_VietNamAddress`** | DEC-004 D1 target "ShippingCore consumes VietNamAddress contracts" — **chưa vào code** |
| Consumers (module.xml sequence) | `GiaoHangNhanh`, `Ghtk`, `Ahamove` sequence ShippingCore; cả 3 đều dùng ShippingContext/OriginProvider thật (grep: `GiaoHangNhanh/Model/Service/Request/ShippingDetailsDataBuilder.php`, `SynchronizeOrderDataBuilder.php`, `Ghtk/Model/Origin/GhtkOriginProvider.php`, `Ahamove/Model/Carrier/AhamoveAbstractCarrier.php`, `Ahamove/Command/CreateShipment.php`) | `GhnAddressMapper` **KHÔNG** sequence ai (vấn đề D8) |

### 1.2 `Secomm_VietNamAddress` — canonical layer (đã ship + đã seed)

- Schemes/catalog: `Model/Scheme/VnSchemes` (+registry `VnSchemeRegistry`, status CURRENT/HISTORICAL).
- Units: `VnAddressUnitProvider` (getUnit/getChildren/countByScheme) — data seeded (2025: 3.355; PRE_2025: 11.357).
- Mapping graph + resolver: `VnAdminAddressResolver` + `MappingCandidateFinder` (union outgoing+incoming, parameterized) — statuses `EXACT|MAPPED|AMBIGUOUS|UNMAPPED`, cardinality-authoritative, candidates sorted, không auto-pick. Mapping seeded: **10.064 edges** PRE→2025 (TASK-NDSZ7V).
- Operational bridge: `VnOperationalAddressResolver` (runtime `region_id/city_id` ↔ `scheme_code/unit_code`, id-based chính + name-based region-scoped tạm thời) — DEC-004 D5.
- Guard extension: `Api/DirectoryReferenceGuardInterface` (DI pool).
- **Không depend** carrier/VietNam/Google (DEC-004 verification giữ nguyên).

### 1.3 Carriers — cách lấy address THẬT SỰ

**GHN (`Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper`)** — cần **GHN numeric IDs** (`toDistrictId: int`, `toWardCode: string`):
- `AbstractDataBuilder::resolveGhnLocation()` (`AbstractDataBuilder.php:139-176`): mapping-first qua `GhnAddressMapper\LocationResolver::resolve(regionId, cityId)` hoặc `resolveByName(regionId, cityName)`; miss → `NoSuchEntityException` → nếu `is_develop_mode` → **fallback cứng `toDistrictId=1456, toWardCode='21511'`** (`:161-167`); thiếu data hẳn → cùng fallback (`:166-167`).
- **`is_develop_mode` default = 1** trong `etc/config.xml:26` (chưa có override trong core_config_data trên dev) → fallback cứng đang **ACTIVE mặc định**. Trùng audit P0-3 của DEC-004 (migration note: default 0).
- `ServicesDataBuilder.php:21` ($toDistrict = 1456) + `SynchronizeOrderDataBuilder.php:90` ('Phường 17' cho from-ward) — fallbacks vẫn live.
- Bảng mapping `secomm_ghn_address_mapping_location` key = **`region_id` + `city_id`** (runtime PKs — DEC-004 D6 nói phải migrate sang scheme-aware) + `ghn_district_id`/`ghn_ward_code` (`GhnAddressMapper/etc/db_schema.xml:10-26`); `city_name`/`region_name` chỉ là display cache.
- `GhnAddressMapper` là module riêng, **không sequence GiaoHangNhanh** (circular dependency chưa khai báo — DEC-004 D8).

**GHTK (`Secomm_Ghtk`)** — **text-based API** (province/district/ward NAMES) + pickup_address_id riêng:
- `DestinationAddressResolver` (`Ghtk/Model/Address/DestinationAddressResolver.php:20-55`): mapping-first trên `secomm_ghtk_address_map` (key `region_id`+`ward_id`) → GHTK names; miss → `BestEffortViVnResolver` (best-effort vi_VN names, "does NOT guarantee GHTK recognition"); **never throws — null = caller degrade**.
- `WardIdBridge` (`WardIdBridge.php:24-65`): khôi phục ward_id = `directory_region_city.city_id` từ ward NAME trong region — **first-match + warning khi ambiguous** (log "using the first match") — chính pattern DEC-004 D5/D9 thay thế.
- Pickup: `pick_address_id` (GHTK pickup address ID riêng, metadata `ghtk.pick_address_id` qua OriginInterface).
- → GHTK **không cần provider location IDs cho destination** (text), NHƯNG cần **district name** mà runtime 2-level không có → structurally cần PRE_2025 (3 cấp).

**Ahamove (`Secomm_Ahamove`)** — **text/geocode thuần**:
- `AhamoveAbstractCarrier.php:467-552`: đọc trực tiếp field text từ Magento shipping address (`region`, `city`, `street`), load quote address; path/order dùng `address` string. **0 provider location IDs.**

### 1.4 Gaps so với flow mục tiêu

| Gap | Chi tiết |
|---|---|
| Không có carrier capability contract | Không nơi nào khai báo "carrier cần scheme nào / fallback text được không" |
| Không có orchestration | Mỗi carrier tự resolve address theo cách riêng (GHN: region_id+city_id runtime; GHTK: name-first-match; Ahamove: text thô) |
| Canonical identity không được consume | Không carrier nào biết `scheme_code/unit_code` hay gọi `VnAdminAddressResolver` (DEC-004 context — vẫn đúng đến hôm nay) |
| Destination chưa được normalize | Destination resolve lặp lại per-carrier, per-request, keyed runtime IDs/name — không có điểm dùng chung |
| Origin side text-only | `ShippingOriginProvider` trả ward TEXT (không city_id) — bridge name-based tạm (D5) là entry khả dụng duy nhất cho origin |

---

## 2. Recommended responsibility boundary (CONFIRM proposed boundary)

Boundary đề xuất của request **được CONFIRM** — và nó chính là DEC-004 D1/D4 đã ratified. Audit chỉ bổ sung bằng chứng code + 1 lưu ý coupling:

| Trách nhiệm | Owner | Lý do |
|---|---|---|
| Scheme registry, canonical units, PRE↔2025 graph, candidate lookup, cardinality, EXACT/MAPPED/AMBIGUOUS/UNMAPPED | **`Secomm_VietNamAddress`** | Domain knowledge VN; đã ship + đã test; carrier-agnostic |
| Operational ↔ canonical bridge (runtime id/name → unit_code) | **`Secomm_VietNamAddress`** (`VnOperationalAddressResolver`) | Đã có (D5); ShippingCore chỉ gọi |
| Shipping resolution orchestration (khi resolve, gọi resolver nào, khi nào gọi external, fail thế nào) | **`Secomm_ShippingCore`** | Là business flow của shipping; không biết VN semantics, chỉ biết statuses |
| External disambiguation (VietMap/Google) | **Optional provider modules** qua contract của ShippingCore | ShippingCore chỉ biết interface + config chọn provider |
| Provider mapping (GHN IDs, GHTK names, VietMap IDs) | **Carrier/provider modules** | D6/D8 — "centralize contracts, decentralize storage" |
| Fallback khi unresolved | **Carrier** (qua capability flag) | Chỉ carrier biết mình chịu được text hay bắt buộc ID |

**Coupling check** (câu hỏi 1): orchestration trong ShippingCore KHÔNG tạo unwanted dependency vì (a) ShippingCore đã được cả 3 carriers sequence sẵn — thêm `Secomm_VietNamAddress` vào sequence ShippingCore là thêm đúng 1 edge theo chiều D1 đã ratified; (b) VietNamAddress không biết ShippingCore tồn tại (đã grep — 0 reference); (c) carrier không cần biết resolver — chỉ thấy DTO. Rủi ro duy nhất: ShippingCore phải tránh hard-code scheme codes → lấy qua `VnSchemes` catalog của VietNamAddress (identity thuộc domain VN), không tự định nghĩa constant scheme.

---

## 3. Proposed contracts (PHP shapes — KHÔNG implement)

Nguyên tắc: mỗi contract ≤ 4 members; mọi field đều có carrier thật cần nó (GHN/GHTK/Ahamove).

### 3.1 Carrier capability (carrier khai báo, ShippingCore chỉ đọc)

```php
namespace Secomm\ShippingCore\Api\Address;

interface CarrierAddressCapabilityInterface
{
    public function getCarrierCode(): string;
    /** Scheme code canonical mà carrier yêu cầu — lấy từ VnSchemes catalog, không phải hằng số mới. */
    public function getRequiredScheme(): string;
    /** true = carrier còn ship được bằng địa chỉ text hiện có khi canonical resolution thất bại. */
    public function supportsTextualFallback(): bool;
}
```

Đủ tối thiểu từ thực tế: GHN → `VN_ADMIN_PRE_2025` + `false` (bắt buộc DistrictID/WardCode); GHTK → `VN_ADMIN_PRE_2025` + `true` (text + district name từ PRE, best-effort fallback là DNA sẵn có); Ahamove → `VN_ADMIN_2025` + `true` (text thuần). KHÔNG thêm các field dạng "requires external IDs?" — điều đó lộ chi tiết provider vào contract chung; provider mapping là việc của carrier (D6). KHÔNG capability matrix.

### 3.2 Resolution result DTO ShippingCore đưa xuống carrier

```php
namespace Secomm\ShippingCore\Api\Address;

interface ResolvedShippingAddressInterface
{
    /** RESOLVED_LOCAL | RESOLVED_EXTERNAL | AMBIGUOUS | UNMAPPED | EXTERNAL_RESOLUTION_FAILED */
    public function getStatus(): string;
    /** Scheme của unitCode bên dưới (requiredScheme khi resolved). */
    public function getSchemeCode(): string;
    public function getRegionCode(): ?string;   // VN-XX canonical
    public function getUnitCode(): ?string;     // ward-level unit code trong schemeCode
    /** AMBIGUOUS: candidates đã sort; trạng thái khác: []. */
    public function getCandidateCodes(): array;
    /** 'local' | 'external:<provider>' — audit trail, không dùng cho logic. */
    public function getResolutionMethod(): ?string;
    /** Shortcut an toàn: chỉ true khi RESOLVED_*. Làm carrier khó nhầm unresolved thành resolved. */
    public function isResolved(): bool;
}
```

District **không** nằm trong DTO: carrier đọc qua `VnAddressUnitProviderInterface::getUnit(scheme, unitCode)` → `parent_code` → district (đúng rule "không duplicate district mapping"). Region name/ward name cũng vậy. DTO chỉ mang identity + outcome.

### 3.3 External resolver contract + pool

```php
namespace Secomm\ShippingCore\Api\Address;

interface ExternalAddressResolverInterface
{
    public function getName(): string;            // 'vietmap' | 'google' — khớp giá trị config
    /** false = chưa config (thiếu API key) hoặc service ngừng khả dụng → pool bỏ qua, không throw. */
    public function isAvailable(): bool;
    /**
     * @return string|null unit_code trong $targetScheme, hoặc null khi không disambiguate được.
     * Implementation tự chịu timeout/log; KHÔNG throw ra orchestration.
     */
    public function resolveUnitCode(string $targetScheme, string $regionCode, ?string $unitCode, ?string $streetText, ?string $receiverText): ?string;
}
```

Pool = DI array (pattern `DirectoryReferenceGuards` D7), selection = config `carrier/vn_address_resolution/provider` (None | vietmap | google — tên khớp `getName()`). Invalid/unavailable selection → bỏ qua + log + trả `EXTERNAL_RESOLUTION_FAILED` cho flow (không fatal). API key của VietMap nằm trong `Secomm_VietMap` config — ShippingCore chỉ giữ lựa chọn provider.

### 3.4 Orchestration entry (1 method)

```php
namespace Secomm\ShippingCore\Api\Address;

interface ShippingAddressResolutionManagerInterface
{
    /**
     * @param array{country_id: string, region_id: ?int, city_id: ?int, ward_name: ?string, street: ?string, receiver: ?string} $destination
     *        — payload chuẩn hóa từ rate/submit path (destination). Origin tương tự khi cần.
     */
    public function resolve(array $destination, CarrierAddressCapabilityInterface $capability): ResolvedShippingAddressInterface;
}
```

Input là array scalar (giống triết lý ShippingContext — "free of mutable Magento models"). Không nhận RateRequest để giữ theme/headless-independent.

---

## 4. Proposed runtime flow (EXACT / MAPPED / AMBIGUOUS / UNMAPPED)

```text
Magento address (rate/submit)
  → destination array (region_id, city_id, ward_name, street…)
  → [bridge] VnOperationalAddressResolver → (VN_ADMIN_2025, unitCode | name-based AMBIGUOUSpolicy)
  → capability.getRequiredScheme()
      ├─ == VN_ADMIN_2025 → EXACT (unit tồn tại)          → RESOLVED_LOCAL → carrier mapping riêng → API
      └─ == VN_ADMIN_PRE_2025
             → VnAdminAddressResolver.resolve(2025, unit, PRE)
             ├─ EXACT/MAPPED (cardinality 1)               → RESOLVED_LOCAL → carrier
             ├─ AMBIGUOUS (>1) → KHÔNG pick
             │     → external configured & available? yes → try (1 lần, cache context)
             │            ├─ unit_code → RESOLVED_EXTERNAL → carrier
             │            └─ null/exception → EXTERNAL_RESOLUTION_FAILED
             │     → EXTERNAL_RESOLUTION_FAILED | AMBIGUOUS
             │           → capability.supportsTextualFallback()?
             │                  ├─ true  → carrier fallback text (route qua mapping-table/names của carrier)
             │                  └─ false → CARRIER UNAVAILABLE (collectRates: không trả method; submit: block + log)
             └─ UNMAPPED (0 candidates — hợp lệ, ~924 PRE wards)
                   → như nhánh AMBIGUOUS (external → fallback → unavailable)
```

Validation vs current code: GHN map `resolveGhnLocation` vào nhánh RESOLVED→carrier (bỏ runtime-key mapping dần, bỏ fallback develop); GHTK `DestinationAddressResolver` giữ nguyên vị trí "carrier fallback text" (nhánh supportsTextualFallback=true); Ahamove gần như không đổi (EXACT local).

---

## 5. External resolver strategy

- **Pool/registry**: DI array `externalAddressResolvers` vào orchestration (mirror D7 guards). Invalid entry → skip + `logger->warning` (pattern đã có ở `VnAddressSchemeImporter::collectExternalReferenceViolations`).
- **Config selection**: `Secomm > Shipping > Vietnam Address Resolution`: `enabled` (Yes/No), `provider` (None|vietmap|google|…từ pool). Giá trị provider khớp `ExternalAddressResolverInterface::getName()`.
- **Provider unavailable**: selection không có trong pool HOẶC `isAvailable() === false` → không call, status `EXTERNAL_RESOLUTION_FAILED`, log một lần/request.
- **API failure**: provider implementation tự catch (contract: trả null, không throw); orchestration log method=`external:<provider>` chỉ khi thành công. Một request chỉ gọi external tối đa 1 lần (xem §7), dùng chung cho mọi carrier.
- **Không installed**: pool rỗng → orchestration chạy chế độ local-only, không lỗi (yêu cầu "ShippingCore can operate when no external resolver module is installed").

## 6. Carrier capability model (asymmetry được tôn trọng)

| Carrier | requiredScheme | supportsTextualFallback | Provider mapping (carrier-owned) | Đổi gì so với hiện tại |
|---|---|---|---|---|
| GHN | `VN_ADMIN_PRE_2025` (cần DistrictID/WardCode 3 cấp) | `false` | `secomm_ghn_address_mapping_location` — **phải migrate key** `region_id+city_id` → `scheme_code+unit_code` (D6) | `resolveGhnLocation` nhận `ResolvedShippingAddress` thay vì tự lookup; **xoá fallback 1456/21511/'Phường 17'**; `is_develop_mode` default 0 |
| GHTK | `VN_ADMIN_PRE_2025` (cần district NAME) | `true` | `secomm_ghtk_address_map` (region_id+ward_id → GHTK names; migrate tương tự) | `DestinationAddressResolver` giữ làm textual-fallback; `WardIdBridge` (first-match) bị **thay** bởi bridge AMBIGUOUS-policy của VietNamAddress (D5/D9) |
| Ahamove | `VN_ADMIN_2025` (text thuần) | `true` | Không cần (text + geocode riêng) | Gần như không đổi — capability chỉ khai báo để orchestration short-circuit EXACT |

Không force GHTK vào bảng mapping kiểu GHN; không force GHN dùng text. Provider mapping **không** gộp universal table (không có bằng chứng cần thiết — D10).

## 7. Performance / caching

- **Điểm orchestration**: 1 lần cho destination + scheme, trong collectRates cycle — TRƯỚC vòng lặp carrier. Không phải per-keystroke (frontend cascade không đụng orchestration này), không per-carrier.
- **Context-level cache (in-memory, request-scoped)**: map key `(hash(destination scalar), requiredScheme)` → `ResolvedShippingAddress` — dùng chung cho N carriers trong cùng request. Đây là mức KHUYẾN NGHỊ duy nhất cho Launchpad Core.
- **External API**: chỉ call khi AMBIGUOUS/UNMAPPED + enabled + available; 1 lần/destination/request nhờ context cache; key thêm `street/receiver` vì disambiguation phụ thuộc text.
- **Không làm** giai đoạn này: application cache bền, Redis queue, persistence bảng disambiguation — đợi dữ liệu thật (volume AMBIGUOUS/UNMAPPED) rồi quyết; tránh premature persistence (đúng nguyên tắc request).

## 8. Failure behavior

- **CONFIRMED: không có first-candidate fallback** — `VnAdminAddressResolver` đã tuyệt đối (AMBIGUOUS → `resolvedCode=null`, candidates sorted); orchestration đề xuất không hề đọc `candidateCodes[0]`; external resolver là đường duy nhất biến AMBIGUOUS thành resolved.
- **Carrier unavailable**: capability `supportsTextualFallback()=false` + unresolved → collectRates **không trả method nào của carrier đó** (loại khỏi available methods) + log cảnh báo có cấu trúc; order-submit path phải block với lỗi rõ ràng cho khách (không guess). Pattern tương tự `DestinationAddressResolver` hiện tại (null → caller degrade) nhưng decision tập trung về ShippingCore thay vì mỗi carrier tự chế.
- Status set **đủ dùng**: dùng 5 status ở §3.2 (RESOLVED_LOCAL/RESOLVED_EXTERNAL/AMBIGUOUS/UNMAPPED/EXTERNAL_RESOLUTION_FAILED) — không thêm status nào khác; `isResolved()` làm hard guard chống nhầm.

## 9. Follow-up implementation plan (đề xuất thứ tự)

| # | Task | Nội dung | Phụ thuộc |
|---|---|---|---|
| A | ShippingCore contracts | `CarrierAddressCapabilityInterface` + `ResolvedShippingAddressInterface` (DTO) + status constants + `ShippingAddressResolutionManagerInterface` skeleton + config namespace `Vietnam Address Resolution` | VietNamAddress đã ship — không chặn |
| B | Orchestration local | `ShippingAddressResolutionManager`: bridge → local resolver → context cache; pool DI rỗng sẵn sàng; unit tests 5 status | A |
| C | **GHN migration** (ưu tiên cao nhất trong carriers — hiện đang risk lớn nhất: runtime keys + fallback cứng ACTIVE default) | capability khai báo PRE_2025/false; migrate `secomm_ghn_address_mapping_location` key → `scheme_code+unit_code`; xoá fallback 1456/21511/'Phường 17'; `is_develop_mode` default 0 | B + mapping seed (đã có, TASK-NDSZ7V) |
| D | GHTK integration | capability PRE_2025/true; `WardIdBridge` → bridge VietNamAddress (AMBIGUOUS policy); mapping table migrate key | B |
| E | Ahamove integration | capability 2025/true — gần như chỉ khai báo | A |
| F | External resolver contract + pool + selection config | `ExternalAddressResolverInterface` + pool + config provider | B |
| G | `Secomm_VietMap` (provider đầu tiên) | chỉ khi OD-3 chốt làm | F |

Thứ tự A → B → C → D → E → (F → G khi business cần). F/G tách riêng vì optional; C ưu tiên vì GHN hiện fallback sai ngầm (P0-3).

## 10. Risks / open decisions (chỉ cái THẬT SỰ chưa chốt sau khi đọc code)

| # | Decision | Vì sao chưa chốt |
|---|---|---|
| OD-1 | **Origin resolution**: `ShippingOriginProvider` trả ward TEXT không city_id (`ShippingOriginProvider.php:68`) → origin đi bridge name-based (region-scoped AMBIGUOUS policy, D5 tạm) hay bổ sung city_id/unit_code vào origin config? Ảnh hưởng GHN from-address (hiện fallback 'Phường 17') | Cần SA chốt: đổi origin config (thêm field) hay chấp nhận name-based tạm |
| OD-2 | **§23 persisted-address snapshot** (`vn_scheme_code`/`vn_unit_code` trên quote/sales/customer) nên đi TRƯỚC hay song song Phase E — vì resolution id-based cần unit_code có ở địa chỉ đã lưu | DEC-003 để follow-up riêng; ảnh hưởng sequencing C/D |
| OD-3 | **Có cần external resolver ngay không** (F/G): business cần số lượng AMBIGUOUS/UNMAPPED thực tế (924 wards UNMAPPED + 3.120 reverse-ambiguous targets là tiềm năng) để quyết đầu tư VietMap | Business input |
| OD-4 | **GHN mapping migration strategy**: migrate bảng cũ (region_id,city_id → scheme keys) bằng tool nào — CLI mapping chuẩn `secomm:vietnam-address:import-mapping` hay tool riêng GhnAddressMapper; dừng/bám data cũ thế nào trong transition | Thiết kế chi tiết task C |
| OD-5 | **Contract `resolve()` input shape** (array scalar ở §3.4) — chốt field list cuối khi implement B (city_id có sẵn ở destination rate path nhưng origin thì không) | Chi tiết implement,TL review task A/B |

## Phụ lục — files inspected

ShippingCore: `Api/{ShippingContextInterface, OriginInterface, OriginProviderInterface}.php`, `Api/Tracking/*`, `Model/{ShippingContext(+Factory), Origin, CarrierTrackingState, OriginProvider/ShippingOriginProvider, Tracking/*}`, `etc/{di.xml, db_schema.xml, module.xml}`, `README.md`.
VietNamAddress: `Model/Scheme/*`, `Model/{VnAddressUnitProvider, VnAdminAddressResolver, VnOperationalAddressResolver, MappingCandidateFinder}`, `Model/Import/{VnMapping*, VnReferenceSchemeImporter, VnAddressSchemeImporter}`, `Api/*`.
Carriers: `GiaoHangNhanh/Model/{Carrier/GHN, Service/Request/{AbstractDataBuilder, ServicesDataBuilder, SynchronizeOrderDataBuilder, ShippingDetailsDataBuilder}}`, `GhnAddressMapper/{Model/{LocationResolver, LocationMappingRepository}, etc/db_schema.xml}`, `Ghtk/Model/Address/{DestinationAddressResolver, WardIdBridge, BestEffortViVnResolver, PickupAddress*}`, `Ghtk/Model/Origin/GhtkOriginProvider`, `Ahamove/Model/Carrier/AhamoveAbstractCarrier`.
Configs: module.xml sequences của 4 module + `GiaoHangNhanh/etc/config.xml:26` (is_develop_mode default 1).
