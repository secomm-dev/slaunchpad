# SL-015 Implementation Plan — Secomm ShippingCore origin contract + GHTK origin refactor

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-015-shippingcore-origin-contract.md |

> **Mode A** · Tier 2 · Status: **Approved 2026-08-17 (user acting as SA/TL; DEC-SL015-001 accepted) — Phase C implement cleared**
> Scope: tạo `Secomm_ShippingCore` (origin contract + default provider) + refactor `Secomm_Ghtk` consume contract. Không implement fulfillment/MSI routing.
> Audit performed 2026-08-17 trên working tree (branch `development`).

---

## PART 1 — ANALYSIS (Phase A)

### 1.1 Current architecture (đã audit)

**`Secomm_Ghtk`** (SL-008 + SL-009, vừa build, **rate-only** — order sync SL-010 parked):

```
collectRates(RateRequest)                         Model/Carrier/Ghtk.php
 ├─ VN gate (destCountry != VN → hide)
 ├─ DestinationAddressResolver.resolve(country, regionId, wardName) → GhtkAddress
 │   └─ secomm_ghtk_address_map (SL-008) → miss → WardIdBridge → best-effort vi_VN (DEC-020)
 ├─ PickupAddressResolver.resolve(storeId) → ?PickupAddress          ← ORIGIN LIVES HERE
 │   └─ đọc carriers/ghtk/pick_address_id | pick_province+pick_ward(+pick_district) qua GhtkConfig
 │   └─ strict gate DEC-021: thiếu → null → carrier hide
 ├─ ShipmentWeightCalculator → grams (DEC-022)
 ├─ RateCache (key = pickup identity + dest + weight + value + transport)
 ├─ GhtkApiClient.getFee(dest, pickup, weight, value, transport)
 │   └─ buildFeeUrl() — map PickupAddress → pick_address_id | pick_province/pick_district/pick_ward  ← MAPPING Ở TRONG CLIENT
 ├─ FeeResponseMapper → FeeResult (fee/insurance/extFees/delivery)
 └─ RateComposer (base fee + multi-select include) → Result
```

- GHTK **không đọc Magento Shipping Origin** (`shipping/origin/*`) — origin hiện đến 100% từ `carriers/ghtk/pick_*`.
- Không có shipment/order flow, tracking, cancel, label (SL-010 parked, DEC-023/024 pending).
- Không có di.xml (toàn constructor DI concrete class — sạch).
- Consumers của `PickupAddressResolver`/`PickupAddress`: chỉ `Carrier\Ghtk`, `Controller\Adminhtml\Ghtk\TestConnection`, và unit tests (đã grep toàn `app/code` — không consumer ngoài module).

**Các module shipping-related khác:**
- `Secomm_Ahamove` (legacy, import từ project cũ): `Helper\Data` đọc `shipping/origin/country_id|region_id|city|postcode|street_line1` trực tiếp trong carrier (`AhamoveAbstractCarrier` line ~313-321 set `*From` từ helper). Style cũ (Helper, RequireJS frontend) — không chung pattern gì với Ghtk.
- `Secomm_Ghtk` mapping table `secomm_ghtk_address_map` key `(country_id, region_id, ward_id)` (DEC-020); ward_id = `directory_region_city.city_id` (VN 2-level, ward = native city).
- SL-013/SL-014 đã đưa VN ward dropdown vào admin **Shipping Origin** + **Store Information** + **MSI Source** form (ward persist vào native `city`, region_id vào `region_id`; `ValidateVietNamWard` plugin validate khi save). → Dữ liệu Shipping Origin / MSI Source **đã ở dạng VN 2-level** — điều kiện sẵn cho default provider + future fulfillment provider.
- `Secomm_Base\Plugin\Model\Shipping` (legacy `beforeCollectRates` — mutate RateRequest từ checkout session cho cart estimate; không liên quan origin, không đụng).

### 1.2 Problems found

| # | Vấn đề | Bằng chứng |
|---|---|---|
| P1 | **Origin resolution là carrier-owned + config-bound** — GHTK tự quyết định origin đến từ đâu (config riêng), không có seam để module ngoài thay đổi | `PickupAddressResolver` đọc `GhtkConfig.getPick*` |
| P2 | **Merchant phải duplicate origin** vào `carriers/ghtk/pick_*` thay vì dùng Magento Shipping Origin chuẩn | system.xml pick_* fields |
| P3 | **Origin → payload mapping nằm trong API client** — client build business payload (`buildFeeUrl` map pick_*) | `GhtkApiClient::buildFeeUrl()` |
| P4 | **Carrier-specific metadata (pick_address_id) hard-code** vào `PickupAddress` VO + cache key + client | `PickupAddress`, `Ghtk::buildCacheKey()` |
| P5 | **Scope inconsistency**: client đọc token/base URL/retry default-scope-only, còn pickup/transport store-aware | `GhtkApiClient` gọi `config->getApiToken()` không storeId |
| P6 | **Không có shared contract** — Ahamove tự đọc `shipping/origin/*` kiểu riêng trong Helper; GHN tương lai sẽ lại tự làm | Ahamove `Helper\Data` |
| P7 | **SL-010 (order sync) plan reuse `PickupAddressResolver`** (AC-6) — nếu không có abstraction chung, rate và order sync có nguy cơ lệch origin source | SL-010 AC-6 |

### 1.3 Coupling / technical debt (đối với MSI fulfillment, runtime origin, Pancake/OMS)

1. Thêm MSI-source origin hôm nay = phải sửa `Secomm_Ghtk` trực tiếp (vì P1) — vi phạm extension point requirement.
2. `pick_address_id` (GHTK) / `shop_id` (GHN) là **carrier metadata của origin**, hiện không có chỗ chứa generic — thêm carrier mới = thêm field riêng.
3. Checkout rate path nhận `RateRequest` (storeId-only context) — không có context object truyền quote/source xuống provider.
4. Ahamove legacy không tuân theo pattern nào chung — mỗi carrier một cách đọc origin.

### 1.4 Shared vs carrier-specific (đánh giá)

**Shared (→ ShippingCore):** ShippingContext (scalar DTO), Origin VO + carrier metadata, OriginProvider interface + default Magento Shipping Origin provider, context factory.
**Carrier-specific (giữ trong Ghtk):** GHTK name normalization (mapping table + WardIdBridge + best-effort), `PickupAddress` VO, legacy `pick_*` BC chain, fee payload mapper, weight calc, fee response parse, rate composition, cache, API client.
**Đặt deliberately carrier-local (lean, không tạo core abstraction):** request mapper interface (chỉ 1 carrier tồn tại; payload shape khác nhau từng carrier), capability interfaces (GHTK rate-only — tạo khi SL-010 resume), common result DTO (Magento `Rate\Result`/`Method` đã đủ — reuse), response contracts (`FeeResult` carrier-local).

### 1.5 Proposed architecture

```
                      ┌──────────────────────────────────────────────┐
                      │  Secomm_ShippingCore (NEW, generic)          │
                      │  Api: ShippingContextInterface, OriginInterface,
                      │       OriginProviderInterface                │
                      │  Model: ShippingContext, Origin,             │
                      │       ShippingContextFactory,                │
                      │       OriginProvider\ShippingOriginProvider  │
                      │       (preference → default)                 │
                      └─────────────▲────────────────────────────────┘
                                    │ resolve(context): Origin
 ┌──────────────────────────────────┼─────────────────────────┐
 │ Secomm_Ghtk                      │                         │
 │  GhtkOriginProvider ─────────────┘  (decorator, BC chain)  │
 │   1. legacy pick_* set → legacy Origin + metadata          │
 │      ghtk.pick_address_id                                  │
 │   2. all empty → delegate inner OriginProviderInterface    │
 │      (default: Magento Shipping Origin;                    │
 │       future: FulfillmentOriginProvider swap qua DI)       │
 │                                                            │
 │  Carrier\Ghtk: context → origin → PickupAddressResolver    │
 │    (Origin → ?PickupAddress; metadata priority; WardId-    │
 │    Bridge + mapping + best-effort normalize; null=hide)    │
 │  → FeeRequestMapper (payload params) → GhtkApiClient       │
 │    (HTTP/auth/retry only)                                  │
 └────────────────────────────────────────────────────────────┘

 Future: Secomm_ShippingFulfillment → preference trên OriginProviderInterface
 (hoặc plugin trên ShippingOriginProvider) → MSI-source origin + carrier
 metadata (ghtk.pick_address_id per source) — KHÔNG sửa carrier.
```

**Contracts (chi tiết):**

```php
// Secomm\ShippingCore\Api\ShippingContextInterface — immutable scalar DTO
getStoreId(): ?int;  getWebsiteId(): ?int;  getCarrierCode(): ?string;
getQuoteId(): ?int;  getSourceCode(): ?string;

// Secomm\ShippingCore\Api\OriginInterface — immutable runtime shipping origin
getSourceCode(): ?string;   getCountryId(): ?string;  getRegionId(): ?int;
getProvince(): ?string;     getDistrict(): ?string;   // NULLABLE — Launchpad VN model
getWard(): ?string;         getStreet(): ?string;     getPostcode(): ?string;
getTelephone(): ?string;    getContactName(): ?string;
getMetadata(string $key, mixed $default = null): mixed;   // 'ghtk.pick_address_id'
hasMetadata(string $key): bool;

// Secomm\ShippingCore\Api\OriginProviderInterface
public function resolve(ShippingContextInterface $context): OriginInterface;
// không trả null, không quyết validity — carrier tự đánh giá usable (ISP/DEC-021)
```

- Provider default đọc `shipping/origin/*` (country_id, region_id, city→ward, street_line1/2→street, postcode; `telephone/contactName/district = null` — shipping origin không có các field này; decorator/enrichment là việc của module khác). Province name = region default name (Magento_Directory); **regionId giữ nguyên** để carrier normalize sang tên GHTK bằng id (DEC-020 canonical key).
- Metadata dotted-key `{carrierCode}.{key}` — flat, không nested array, không hard-code GHTK/GHN field trong core.
- Ward **id** không đưa vào Origin (YAGNI — không surface nào persist ward id trên address/source hiện tại; carrier bridge bằng (regionId, wardName) như DEC-020 path B).

**Key decisions (→ DEC-SL015-001):**
1. Origin contract như trên; provider không quyết validity (carrier-side gate giữ DEC-021).
2. BC chain: legacy `pick_*` (bất kỳ field set) thắng; chỉ khi **tất cả trống** mới delegate Shipping Origin. Half-filled legacy KHÔNG fallback (tránh ship từ kho sai — giữ strict DEC-021).
3. Mapper/client tách: `FeeRequestMapper` (GHTK-local concrete — không core interface), `GhtkApiClient` chỉ transport + store-scoped config.
4. Không tạo: core mapper interfaces, capability interfaces, common result DTO (lean — tạo khi có carrier thứ 2 / SL-010 resume).
5. `PickupAddressResolver` giữ tên + vai trò strict gate nhưng đổi input sang `OriginInterface`.

### 1.6 Classes to add

| Class | Module | Vai trò |
|---|---|---|
| `Api\ShippingContextInterface` + `Model\ShippingContext` | ShippingCore | context DTO |
| `Api\OriginInterface` + `Model\Origin` | ShippingCore | origin VO + metadata |
| `Api\OriginProviderInterface` | ShippingCore | extension contract |
| `Model\ShippingContextFactory` | ShippingCore | `fromRateRequest(RateRequest, carrierCode)` |
| `Model\OriginProvider\ShippingOriginProvider` | ShippingCore | default: Magento Shipping Origin |
| `Model\Origin\GhtkOriginProvider` | Ghtk | BC chain (legacy config → delegate inner) |
| `Model\Fee\FeeRequestMapper` | Ghtk | (dest, pickup, weight, value, transport) → GHTK params |
| `registration.php`, `etc/module.xml`, `etc/di.xml` (preference) | ShippingCore | module skeleton |
| `README.md`, `CHANGELOG.md` | ShippingCore (+ Ghtk — đóng gap [WARN]) | docs |

### 1.7 Classes to refactor

| Class | Thay đổi |
|---|---|
| `Model\Carrier\Ghtk` | inject `GhtkOriginProvider` + `ShippingContextFactory` + `FeeRequestMapper`; origin flow mới; cache key từ mapped pickup identity |
| `Model\Address\PickupAddressResolver` | input `?int $storeId` → `OriginInterface`; thêm nhánh normalize bằng regionId (reuse `DestinationAddressResolver`) |
| `Model\GhtkApiClient` | `getFee(array $params, ?int $storeId)`; bỏ `buildFeeUrl` business mapping; store-scoped config reads |
| `Controller\Adminhtml\Ghtk\TestConnection` | dùng provider chain + mapper (giữ nguyên UX/message) |
| `etc/adminhtml/system.xml` (+ i18n CSV) | label/comment pick_* → "legacy override — empty falls back to Magento Shipping Origin" |
| `etc/module.xml` (Ghtk) | thêm sequence `Secomm_ShippingCore` |
| `Test/Unit/...` | PickupAddressResolverTest refactor; thêm tests mới (xem 1.10) |

**Xóa:** không xóa class nào (`PickupAddress` VO giữ — là GHTK pickup payload VO).

### 1.8 Config changes

- **Không thêm/bỏ config path nào.** `carriers/ghtk/pick_*` giữ nguyên (BC — có thể đang dùng thật).
- Semantic mới: `pick_*` toàn trống → origin = Magento Shipping Origin (trước đây: carrier inactive). Đây là behavior change có chủ đích (AC-11 SL-015).
- Migration path (đề xuất, không làm trong ticket): merchant muốn dùng Shipping Origin thì **xóa sạch** 4 field pick_*; muốn override theo carrier thì điền (legacy wins). Không cần data patch.
- Long-term (out of scope): `pick_address_id` per-source → origin metadata từ `Secomm_ShippingFulfillment`; global config field dần deprecate.

### 1.9 Backward compatibility concerns

| Điểm | Đánh giá |
|---|---|
| Config paths | Giữ nguyên 100% — không breaking |
| Public classes | `PickupAddressResolver::resolve()` đổi signature — chỉ consumer nội bộ Ghtk (đã grep: carrier + TestConnection + tests) → breaking được kiểm soát, document trong CHANGELOG |
| Carrier behavior | Merchant đã set pick_*: **không đổi** (legacy wins, DEC-021 strict giữ). Merchant chưa set: trước đây inactive → giờ dùng Shipping Origin (muốn vậy) |
| Rate cache | Key format đổi → tối đa 1 miss/TTL (≤10 min) — negligible |
| API request shape | Không đổi — cùng param set gửi tới GHTK như cũ |
| Scope config | Token/base URL/retry giờ store-scoped (superset của default-only) — document |
| Module dependency | Ghtk giờ cần ShippingCore enable (setup:upgrade) — deployment note |
| DB schema | Không đụng |

### 1.10 Test plan (tối thiểu, per yêu cầu §19)

```
ShippingOriginProviderTest (core)
  - country/province(region default name)/ward(city)/street/postcode từ shipping/origin/*
  - district null, telephone null, contactName null, sourceCode null, metadata empty
  - regionId forwarded; store scope được dùng (storeId truyền xuống ScopeConfig)
ShippingContextFactoryTest (core)
  - fromRateRequest maps storeId/quoteId/carrierCode
GhtkOriginProviderTest
  - legacy pick_address_id → metadata ghtk.pick_address_id (inner KHÔNG được gọi)
  - legacy province+ward → origin fields; district optional
  - legacy half-filled (province only) → legacy origin (KHÔNG delegate — strict)
  - legacy trống → delegate inner provider (mock)
PickupAddressResolverTest (refactor)
  - metadata pick_address_id → PickupAddress(id) không cần mapping
  - regionId+ward → normalize qua DestinationAddressResolver (mock) → GHTK names
  - names-only (legacy) → as-is; district optional forwarded
  - thiếu → null (hide)
FeeRequestMapperTest
  - pickup có id → pick_address_id only; names → pick_province/pick_ward(+district)
  - dest params + value>0 + transport; district optional
Extension point test (GhtkCarrierTest)
  - stub OriginProviderInterface trả origin custom (metadata pick_address_id + names)
    → carrier (mock GhtkApiClient) nhận params từ custom origin — chứng minh replace
    default provider không sửa Secomm_Ghtk
Existing tests giữ pass: DestinationAddressResolver/WardIdBridge/GhtkConfig/
  FeeResponseMapper/RateComposer/ShipmentWeightCalculator/import tests
Run: vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --filter 'Secomm\\(ShippingCore|Ghtk)'
```

QC manual (sau build): rate checkout VN với (a) legacy pick_* set — behavior như cũ; (b) pick_* trống + Shipping Origin VN hợp lệ — rate xuất hiện dùng origin; (c) pick_* trống + origin thiếu ward — carrier hide + warning log; (d) TestConnection admin vẫn báo đúng.

---

## PART 2 — IMPLEMENTATION TASKS (Phase B)

> Thực hiện tuần tự; sau mỗi task: run unit tests liên quan, report files/behavior changed. Không mở rộng scope.

### Task 1 — Create `Secomm_ShippingCore` module skeleton

- **Goal:** module rỗng đăng ký được, dependency đúng (Magento_Store, Magento_Directory, Magento_Shipping, Magento_Framework).
- **Files:** `registration.php`, `etc/module.xml`, `composer.json` (nếu convention — Ghtk không có; skip theo project style), `README.md`, `CHANGELOG.md` (0.1.0), `etc/di.xml` (preference `OriginProviderInterface → ShippingOriginProvider` — add ở Task 3).
- **Implementation:** module name `Secomm_ShippingCore`; KHÔNG dependency Secomm carrier.
- **Acceptance:** module xuất hiện trong `bin/magento module:status` (hoặc config.php sau upgrade — không tự chạy setup:upgrade nếu env không cho phép; tối thiểu `bin/magento module:status` check); no code.
- **Risk:** thấp.

### Task 2 — Origin + ShippingContext contracts

- **Goal:** immutable DTO + interface đúng signature (1.5).
- **Files:** `Api/OriginInterface.php`, `Api/ShippingContextInterface.php`, `Model/Origin.php`, `Model/ShippingContext.php`.
- **Implementation:** final readonly-style (PHP 8.2, constructor promotion, `declare(strict_types=1)`, Secomm header); metadata flat dotted-key; constructor validates không cần (VO thuần).
- **Acceptance:** unit test khởi tạo + getter + metadata default; `php -l` pass.
- **Risk:** thấp.

### Task 3 — `ShippingContextFactory` + `ShippingOriginProvider` (default)

- **Goal:** default provider đọc Magento Shipping Origin → Origin; factory từ RateRequest.
- **Files:** `Model/ShippingContextFactory.php`, `Model/OriginProvider/ShippingOriginProvider.php`, `etc/di.xml` (preference).
- **Implementation:** scopeConfig `shipping/origin/*` SCOPE_STORE theo context storeId; province name qua `Magento\Directory\Model\RegionFactory` (default name); street = line1 (+ line2 join nếu có); telephone/contactName/district = null (documented); KHÔNG quyết validity.
- **Acceptance:** ShippingOriginProviderTest + ShippingContextFactoryTest pass (1.10).
- **Risk:** thấp — chỉ đọc config chuẩn.

### Task 4 — `GhtkOriginProvider` (BC chain) + module.xml sequence

- **Goal:** legacy pick_* chain + delegate inner; Ghtk depend ShippingCore.
- **Files:** `Ghtk/Model/Origin/GhtkOriginProvider.php`, `Ghtk/etc/module.xml` (+sequence), config reads reuse `GhtkConfig`.
- **Implementation:** theo key decision #2 (1.5). Legacy origin: countryId='VN' (GHTK VN-only), province/district/ward từ config, metadata `ghtk.pick_address_id` khi có, sourceCode null.
- **Acceptance:** GhtkOriginProviderTest pass (4 nhánh).
- **Risk:** medium — nhánh half-filled phải KHÔNG delegate (test bắt).

### Task 5 — Refactor `PickupAddressResolver` (Origin → ?PickupAddress)

- **Goal:** strict gate DEC-021 trên Origin; normalize regionId-based origin sang GHTK names.
- **Files:** `Ghtk/Model/Address/PickupAddressResolver.php` (+ test refactor).
- **Implementation:** theo 1.5 flow (metadata priority → regionId+ward normalize qua `DestinationAddressResolver` → names-only as-is → null).
- **Acceptance:** PickupAddressResolverTest (refactor) pass; existing carrier tests liên quan adjust pass.
- **Risk:** medium — normalize path phải graceful (null → hide, không throw).

### Task 6 — `FeeRequestMapper` + refactor `GhtkApiClient` + `Carrier\Ghtk` + `TestConnection`

- **Goal:** tách payload mapping khỏi client; carrier dùng provider chain; cùng origin abstraction cho rate (và SL-010 sau này).
- **Files:** `Ghtk/Model/Fee/FeeRequestMapper.php`, `Model/GhtkApiClient.php`, `Model/Carrier/Ghtk.php`, `Controller/Adminhtml/Ghtk/TestConnection.php`.
- **Implementation:** carrier: context → origin (GhtkOriginProvider) → pickup (resolver, null→hide) → dest/weight/transport như cũ → cache key (mapped pickup identity) → mapper → `apiClient->getFee($params, $storeId)` → mapper fee → composer. Client: URL từ params array, bỏ `buildFeeUrl` mapping, store-scoped config.
- **Acceptance:** FeeRequestMapperTest + GhtkCarrierTest (extension point) pass; `php -l`/DI compile nếu workflow yêu cầu; manual smoke TestConnection (nếu env cho phép).
- **Risk:** cao nhất trong plan — chạm rate path; mitigations: giữ try/catch resilient (AC-011 SL-009), không đổi param gửi GHTK.

### Task 7 — Config labels BC + i18n

- **Goal:** system.xml/comment/CSV phản ánh fallback semantics; không đổi paths.
- **Files:** `Ghtk/etc/adminhtml/system.xml`, `i18n/vi_VN.csv`, `i18n/en_US.csv`.
- **Acceptance:** label mới song ngữ; không path mới/mất.
- **Risk:** thấp.

### Task 8 — Tests đầy đủ + run

- **Goal:** toàn bộ 1.10 pass.
- **Files:** các file test mới/refactor (ShippingCore `Test/Unit/...`, Ghtk tests).
- **Acceptance:** `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --filter 'Secomm\\(ShippingCore|Ghtk)'` green; evidence ghi `.ai/runtime/evidence/SL-015/`.
- **Risk:** thấp.

### Task 9 — Documentation + records

- **Goal:** đóng record theo AGENTS §14.
- **Files:** ShippingCore/Ghtk `README.md` + `CHANGELOG.md`; DEC-SL015-001 proposed→(TL approve); SL-015 status update; FEAT-006 note (origin resolution chuyển sang ShippingCore — không đổi fee scope); project-context 04/09/10 diff (surface cho human commit).
- **Acceptance:** `bin/project-ai-validate --check-records` không lỗi mới; evidence artifact tồn tại.
- **Risk:** thấp.

### Sequence & gating

```
[TL/SA approve DEC-SL015-001 + plan này]
  → Task 1 → 2 → 3 (ShippingCore hoàn chỉnh, test)
  → Task 4 → 5 → 6 → 7 (Ghtk refactor, test từng task)
  → Task 8 (full test) → QC manual (a–d) → Task 9 (docs)
  → AI pre-review → TL code review (Tier 2) → release
```

Không implement bất kỳ task nào trước khi DEC-SL015-001 + plan được approve (AGENTS §7.1, §8.2, §10).
