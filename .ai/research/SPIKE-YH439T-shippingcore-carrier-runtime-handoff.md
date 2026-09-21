# SPIKE-YH439T — ShippingCore carrier runtime handoff + failure semantics (audit + kiến nghị, pre-implementation)

> **Analysis/design only — 0 production code.** Record: `.ai/records/spikes/SPIKE-YH439T.md`.
> Anchor: TASK-AQT7V3 (E-A contracts) + TASK-5XDG1P (E-B manager + r1 PII cleanup) + SPIKE-W273TB
> (Phase E design) + DEC-FEATYA2C0W-004 (D1–D10). Ngày: 2026-09-08. Prose: VI; identifiers: EN.
> Mọi contract shape dưới đây là ĐỀ XUẤT cho TL/SA review — KHÔNG phải quyết định đã ratified.

---

## 1. Current ShippingCore runtime state (audit code thật 2026-09-08)

### 1.1 Đã ship (E-A + E-B + r1)

| Capability | State | File |
|---|---|---|
| Canonical graph resolution | `VnAdminAddressResolverInterface::resolve(scheme, unit, target)` — 4-state cardinality-authoritative; unknown scheme → `LocalizedException` | `Secomm_VietNamAddress` (đã seeded 10.064 edges PRE→2025) |
| Operational bridge (runtime id ↔ canonical) | `VnOperationalAddressResolver` (D5) | `Secomm_VietNamAddress/Model/` |
| Capability | `CarrierAddressCapabilityInterface` — `getRequiredScheme()` + `supportsTextualFallback()` (chưa ai đọc flag này) | `ShippingCore/Api/Address/` |
| Local orchestration | `ShippingAddressResolutionManager` (TASK-5XDG1P): validate → cache `sourceScheme\|sourceUnitCode\|targetScheme` → delegate resolver → VO; non-VN → `UnsupportedDestinationException`; countryId null → resolve tiếp; missing identity → UNMAPPED; AMBIGUOUS không auto-pick | `ShippingCore/Model/Address/` |
| Result DTO | `ResolvedShippingAddress` — invariant center hóa (unresolved không thể lộ unitCode); **không** regionCode/district/tên/provider ID | `ShippingCore/Model/Address/` |
| Context DTO | `ShippingAddressResolutionContext` — scalar-only (countryId, source identity, targetScheme, streetText, candidateCodes); **receiverText đã XÓA** (r1 PII hygiene) | `ShippingCore/Model/Address/` |
| External resolver | `ExternalAddressResolverInterface` + pool (zero-provider valid) — **chưa có ai gọi** | `ShippingCore/Model/Address/` |
| ShippingContext | scalar (storeId, websiteId, carrierCode, quoteId, sourceCode) + factory `fromRateRequest()` / `fromShipment()` — **KHÔNG mang destination data** | `ShippingCore/Model/` |
| Origin | `OriginInterface` (text-based: province/district nullable/ward/street/postcode/phone…) + `ShippingOriginProvider` (Magento Shipping Origin config) | `ShippingCore/Model/` |
| Tracking | pipeline carrier-agnostic đầy đủ (DEC-SL017-001) — không liên quan handoff address | `ShippingCore/Model/Tracking/` |

### 1.2 Mắt xích THIẾU trong runtime flow (verified bằng grep)

1. **`VnOperationalAddressResolver` có 0 consumer** ngoài VietNamAddress — không ai chuyển
   destination runtime (region_id/city_id) thành canonical identity.
2. **Không tồn tại builder** `RateRequest/quote address → ShippingAddressResolutionContext` —
   manager E-B yêu cầu caller đưa sẵn `sourceScheme/sourceUnitCode` nhưng chưa có ai dựng nó.
3. **Không có canonical identity persisted** trên quote/order/address (`scheme_code/unit_code`
   grep = 0 hit ở carrier/ShippingCore) — OD-2 (persisted-address snapshot) chưa làm.
4. **Destination handoff ShippingCore→carrier chưa tồn tại trong production** — handoff thật
   duy nhất là origin side (`ShippingContextFactory` + `OriginProviderInterface`), được cả 3
   carrier dùng.

Kết luận: E-A/E-B là **engine hoàn chỉnh nhưng chưa có cánh tay nối** — hàng không chạm được
carrier, và carrier hiện vẫn tự lấy destination theo 3 kiểu khác nhau (§2).

---

## 2. Current carrier handoff patterns (audit code thật)

### 2.1 GHN (legacy `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper`)

| Khía cạnh | Thực tế (file:line) |
|---|---|
| Destination data | Raw runtime: `regionId` + `city` (text) + `city_id` custom field từ quote address/RateRequest (`ShippingDetailsDataBuilder.php:64-67`) — **không qua canonical layer** |
| Provider mapping (stage 2) | `GhnAddressMapper\LocationResolver::resolve(regionId, cityId)` / `resolveByName` — bảng `secomm_ghn_address_mapping_location` keyed **region_id+city_id** (pre-canonical, D6 migration pending); Magento `CacheInterface` wrapper; miss → `NoSuchEntityException` |
| Mapping failure | **Fail closed** (BUG-JBX3H9): `AbstractDataBuilder::resolveGhnLocation()` → `GhnLocationMappingException` + warning log (identifiers-only, không PII); KHÔNG còn fallback cứng 1456/21511 |
| Origin | **ShippingCore consumed** ✓: `ShippingContextFactory::fromRateRequest` + `OriginProviderInterface` (`ShippingDetailsDataBuilder.php:70-72`) |
| Unsupported country | **KHÔNG có check nào** — non-VN rơi vào mapping-miss (sai ngữ nghĩa: hiện như provider failure thay vì not-applicable) |
| Rate unavailable | `GHN::estimateShippingCost()` catch-all `Exception` → log **CHỈ khi isDebug()** → `null` → `collectRates()` trả `false` (`GHN.php:212-217,161`) — production failure **silent** |
| ⚠ Fake-rate path | `$shippingFee = 10;` default (`GHN.php:179`): khi `get_services` không trả service khớp, rate **10 vẫn được trả** thay vì unavailable — anti-pattern cần xóa ở GHN-C |
| Order sync path | Fail closed: sync fail → log + MQ retry (BUG-JBX3H9) |

### 2.2 GHTK (`Secomm_Ghtk`) — pattern TỐT NHẤT hiện có, làm chuẩn tham chiếu

| Khía cạnh | Thực tế (file:line) |
|---|---|
| Destination data | Ward NAME từ `destCity` (2-level) → `WardIdBridge` first-match city_id (warning khi ambiguous, `WardIdBridge.php:52-59`) → mapping-first `secomm_ghtk_address_map` (keyed country+region+ward) |
| Fallback | Miss → `BestEffortViVnResolver` (vi_VN names đọc readonly từ bảng AddressDropdown) — "GHTK may reject", **never throws**, null → caller degrade (`DestinationAddressResolver.php:20-27`) |
| Unsupported country | **VN gate AC-014**: `destCountryId !== 'VN'` → `hide()` (`Ghtk.php:228-230`) — precedent carrier-side non-VN filter |
| Rate unavailable | catch-all `\Throwable` → MaskingLogger **error LUÔN** (không debug-gated) → `hide()` (no rate, no error) (`Ghtk.php:206-216`); API fail → warning → `hide()`; fee unavailable/delivery denied → info → `hide()` |
| Origin | ShippingCore consumed ✓ + strict pickup gate (DEC-021) → `hide()` |
| Rate cache | Full-param key (`Ghtk.php:257-262`) |
| Shipment path | `AbstractCarrierOnline` native label; submit error **abort shipment save** (không false-success) (`Ghtk.php:56-63`) |

### 2.3 Ahamove (`Secomm_Ahamove`)

| Khía cạnh | Thực tế (file:line) |
|---|---|
| Destination data | **Text thô** từ RateRequest: region/city/street/postcode + country NAME + **dest name + telephone** (PII cần cho payload Ahamove) (`AhamoveAbstractCarrier.php:267-283`) — 0 mapping, 0 canonical |
| Unsupported country | `checkAvailableShipCountries()` hardcoded `[VN]`; khi `showmethod=1` → **Error object với message customer-facing** ("Sorry, but we can't deliver…", `:362-378,155-169`) — Magento native pattern, mặc định showmethod=0 |
| Rate unavailable | catch-all → error log → **fall-through trả null** (`:202-204`); `estimateShippingCost` catch-all → **`return 0` sentinel** → falsy → error-or-false (`:302-304,177`) |
| Origin | ShippingCore consumed ✓ với config fallback (`:286-300`) |

### 2.4 GhnAddressMapper

Service mapping thuần (stage 2), keyed runtime `region_id+city_id`, không sequence module nào,
không consume ShippingContext — migration key sang `scheme_code+unit_code` thuộc GHN-C (D6).

**Tổng hợp**: 3 carrier = 3 kiểu destination handoff (ID-based / name-first+best-effort / text thô),
3 kiểu failure semantics (silent-null / graceful-hide + log / 0-sentinel + error object). Đây là
sự trùng lặp + không nhất quán mà ShippingCore phải chuẩn hóa (§3).

---

## 3. Gap analysis — nơi sẽ xảy ra duplication/semantic lệch

| Logic | Nếu không chuẩn hóa sẽ xảy ra |
|---|---|
| Runtime → canonical identity | Mỗi carrier tự gọi bridge `VnOperationalAddressResolver` theo cách riêng → 3 chỗ dựng context, lệch nhau (GHN hiện thậm chí chưa qua bước này) |
| non-VN applicability | GHTK có gate riêng, GHN KHÔNG có, Ahamove dùng Magento native + error object → 3 semantics; `UnsupportedDestinationException` của manager sẽ bị mỗi carrier try/catch + dịch theo kiểu riêng |
| AMBIGUOUS/UNMAPPED nhánh unresolved | Mỗi carrier tự quyết "external? fallback? unavailable?" → policy trùng lặp, dễ xuất hiện first-candidate fallback (vi phạm D9) |
| External resolver | Không chuẩn hóa thì 1 ngày nào đó carrier sẽ tự gọi VietMap/Google trực tiếp — vi phạm D2/D7 |
| Canonical caching | E-B đã cache trong manager — nhưng nếu carrier tự dựng context trước manager thì cache có hiệu lực; nếu carrier tự resolve riêng thì mất |
| Rate failure → storefront | 3 pattern hiện hữu (silent-null / hide+log / 0-sentinel + error object) → UX không dự đoán được, log thiếu (GHN debug-only) |
| Stage-2 provider mapping failure | Dễ bị nuốt vào "không rate" chung chung hoặc tệ nhất bị map nhầm sang canonical UNMAPPED (sai domain) |

---

## 4. Recommended carrier handoff model — **Option B-minimal** (kiem chế)

### 4.1 So sánh

| Tiêu chí | A: raw `ResolvedShippingAddress` | B: handoff object | C: ShippingCore invoke carrier adapter |
|---|---|---|---|
| Carrier không reinterpret status | ✗ — carrier tự gọi manager, tự try/catch `UnsupportedDestinationException`, tự đọc capability → duplication ngay tại 3 collectRates | ✓ — applicability/fallback-eligibility đã được ShippingCore tính trước | ✓ nhưng over-centralize |
| Abstraction mới | 0 | **1 DTO + 1 service method** (mỏng) | 1 adapter interface + inversion của flow |
| Rủi ro over-modeling | Thấp (nhưng duplication cao) | Kiểm soát được nếu ≤ 5 members, không provider detail | Cao — ShippingCore biết quá nhiều về carrier lifecycle |
| Khớp D2/D10 | ✓ | ✓ | ✗ nghiêng |

### 4.2 Kiến nghị: giữ `ResolvedShippingAddress` nguyên vẹn (data), thêm ĐÚNG 1 lớp mỏng

**Không đổi gì E-A/E-B.** Bổ sung (đề xuất, chờ TL/SA):

```text
DestinationContextBuilder (ShippingCore, internal service)
  RateRequest|CustomerAddress|OrderAddress
    → VnOperationalAddressResolver bridge (runtime id/name → scheme_code + unit_code)
    → active-scheme config
    → ShippingAddressResolutionContext   (DTO E-A dùng nguyên xi)

CarrierAddressHandoffService (ShippingCore, 1 method — entry point duy nhất cho carrier)
  handoff(shippingContext, destination, capability): CarrierAddressHandoff

CarrierAddressHandoff (DTO mới, ≤ 5 members — ĐỀ XUẤT shape)
  isApplicable(): bool                      // false = non-VN — KHÔNG BAO GIỜ throw
  isResolved(): bool                        // EXACT|MAPPED (guard có sẵn của ResolvedShippingAddress)
  getResolvedAddress(): ?ResolvedShippingAddressInterface
  getUnresolved(): ?ResolvedShippingAddressInterface   // AMBIGUOUS/UNMAPPED (candidates đọc-only bên trong)
  isTextualFallbackEligible(): bool          // ShippingCore ĐỌC capability 1 LẦN, policy tập trung
  getFailureReason(): ?string               // mã chẩn đoán nhỏ (§9) — technical, không customer-facing
```

Vì sao B-minimal mà không A: với A, từng carrier phải (1) dựng context, (2) catch
`UnsupportedDestinationException`, (3) đọc `supportsTextualFallback()` + tự quyết policy, (4)
nhánh unresolved — đủ 4 điểm duplication × N carrier, và chính là chỗ "carrier tự reinterpret
unsupported country" mà directive cấm. DTO B chỉ **wrap + tính trước**, không thêm field
provider nào, không fifth status — `ResolvedShippingAddress` giữ nguyên tính canonical thuần.

Vì sao không C: adapter đảo ngược flow (ShippingCore gọi carrier) — trùng lifecycle mà Magento
đã có (`collectRates`/label path), phá pattern `AbstractCarrier` của project, over-centralize.

---

## 5. Carrier applicability semantics (non-VN)

* **Ownership: `CarrierAddressHandoffService`** (lớp 2 ở §4) — điểm dịch DUY NHẤT:
  `UnsupportedDestinationException` → `CarrierAddressHandoff::isApplicable() === false`.
  Exception **không bay ra khỏi ShippingCore service layer**; carrier boundary nhận non-exception
  result (sạch hơn — carrier không cần biết exception type; rule "technical exception không leak
  ra storefront" tự động đúng).
* Manager giữ nguyên hành vi throw (E-B, đã approved — contract nội bộ của ShippingCore).
* Carrier **được phép** giữ fast-path gate rẻ (GHTK AC-014-style `destCountryId !== 'VN'` →
  không gọi handoff) — nhưng gate này là optimization, KHÔNG phải nguồn sự thật; nguồn sự thật
  là handoff.
* Ahamove `showmethod=1` → Error object customer-facing: khuyến nghị Secomm standard mặc định
  **hide** (không Error object) — xem §10.
* Không thêm status thứ 5 — `isApplicable()` là guard của handoff, không phải canonical status.

---

## 6. AMBIGUOUS — flow sở hữu

Confirmed preferred intent (không challenge): **ShippingCore sở hữu external-resolution
orchestration.**

```text
handoff()
  → local resolve (manager, cached)
  → AMBIGUOUS?
      → external resolver configured & available?   [ShippingCore — pool + selection config]
            ├─ unit_code → RE-RESOLVED → handoff.isResolved() = true (§13)
            └─ null/unavailable → đi tiếp
      → capability fallback eligible?               [ShippingCore đã đọc sẵn → isTextualFallbackEligible()]
            ├─ true  → carrier tự build textual payload (provider-specific, §8)
            └─ false → carrier trả unavailable (§10)
```

Carrier KHÔNG được: gọi VietMap/Google, chọn `candidateCodes[0]`, tự re-resolve. Candidates chỉ
đi kèm handoff DTO để **chẩn đoán/hiển thị admin** (đọc-only).

**Evidence độ quan trọng (SPIKE-9Z231Q v2, đã TL/SA clarify 2026-09-08):** reverse-mapping
2025→PRE_2025 (chiều GHN carrier-required) là ONE_TO_ONE 186 (5.60%) / **ONE_TO_MANY 3.097
(93.26%)** / NO_MATCH 38 (1.14%) ⇒ AMBIGUOUS là case CHỦ ĐẠO của GHN-C, external disambiguation
+ AMBIGUOUS policy là **điều kiện GHN usable**, không phải phase tùy chọn — củng cố việc
ShippingCore-owned orchestration (§6) phải có trong handoff service trước khi GHN-C start.

---

## 7. UNMAPPED — phân biệt 3 loại, không collapse

| Loại | Bản chất | Ở đâu | Nhận diện |
|---|---|---|---|
| `unknown_source_unit` | Canonical identity hỏng/thiếu (missing sourceScheme/unit, unit lạ) | Stage 1 — trong `VnAddressResolutionInterface::getReason()` (đã có) | reason trên canonical result |
| `no_mapping` | Unit hợp lệ nhưng graph không có edge → target scheme | Stage 1 — canonical UNMAPPED ĐÚNG NGHĨA | reason trên canonical result |
| Provider mapping missing | Canonical resolved ✓ nhưng carrier không có provider ID | **Stage 2 — carrier side**, KHÔNG BAO GIỜ thành canonical UNMAPPED | `failureReason = PROVIDER_MAPPING_MISSING` (§9) |

Flow UNMAPPED giống AMBIGUOUS (§6): external → fallback-eligible → unavailable. External có giá
trị cho cả 2 reason (geocode/text có thể cứu cả `no_mapping` lẫn `unknown_source_unit`).

---

## 8. Textual fallback ownership — boundary chính xác

Audit asymmetry thực tế rồi **confirm** candidate boundary:

| Vai | Việc |
|---|---|
| **ShippingCore quyết** | `supportsTextualFallback()` được đọc đúng 1 lần (trong handoff service) → `isTextualFallbackEligible()`; log quyết định cho phép; đồng điều kiện: chỉ offer fallback khi canonical UNRESOLVED và external không cứu được |
| **Carrier thực thi** | Build provider-specific textual payload từ dữ liệu nó đã có (GHTK: `DestinationAddressResolver` hiện tại CHÍNH LÀ máy fallback này; Ahamove: text-native = normal path, không cần nhánh riêng) |
| **Carrier log** | Việc thực thi fallback + kết quả provider accept/reject (GHTK đã làm đúng pattern warning "may reject") |
| **Cấm** | ShippingCore sinh text payload chung (sẽ phải biết GHTK district-name/Ahamove address-string = vi phạm D2/D10); carrier tự quyết có được fallback hay không |

Đây không phải lý thuyết: capability flag E-A hiện **chưa có ai đọc** — handoff service sẽ là
reader duy nhất.

---

## 9. Canonical failure vs provider mapping failure — boundary + reason vocabulary nhỏ

Hai stage tách bạch tuyệt đối (ví dụ directive: PRE ward MAPPED ✓ nhưng GHN thiếu district_id
→ provider failure, KHÔNG phải canonical UNMAPPED):

```text
Stage 1 (ShippingCore): current/legacy canonical → target canonical identity  → ResolvedShippingAddress 4-state
Stage 2 (Carrier):      target canonical identity   → provider identity        → provider mapping/API outcome
```

Đề xuất vocabulary failure-reason NHỎ (string constants trên handoff contract — không enum class,
không DB, không taxonomy lớn):

```text
NOT_APPLICABLE_COUNTRY        // handoff: non-VN (exception đã dịch)
CANONICAL_AMBIGUOUS           // unresolved + candidates
CANONICAL_UNMAPPED            // unresolved, reason từ canonical result (no_mapping|unknown_source_unit)
PROVIDER_MAPPING_MISSING      // stage 2
PROVIDER_API_UNAVAILABLE      // stage 2
PROVIDER_VALIDATION_FAILED    // stage 2
```

Carriers map outcome của mình vào 6 mã này để log/ops nhất quán; mở rộng chỉ khi có bằng chứng.

---

## 10. Rate failure semantics (storefront) — một chuẩn cho carrier VN Secomm

Chuẩn hóa theo pattern GHTK (tốt nhất hiện có):

| Case | collectRates behavior | Log |
|---|---|---|
| Carrier inactive | `return false` (Magento native) | — |
| Non-VN / not applicable | `return false` (hide, KHÔNG Error object; `showmethod` là opt-in Magento — Secomm khuyến nghị default 0) | debug (không nhiễu) |
| Canonical AMBIGUOUS/UNMAPPED + external không cứu + fallback không eligible | `return false` | **warning có cấu trúc** (reason code + identifiers, không PII), 1 lần/request/destination |
| Canonical unresolved + fallback eligible | carrier tự fallback; fail → `return false` | warning |
| Provider mapping missing | `return false` | warning (`PROVIDER_MAPPING_MISSING`) |
| Provider API unavailable/validation fail | `return false` | warning/error |
| Bất kỳ exception nào | **không bao giờ throw ra ngoài collectRates** — hide + log error LUÔN (không debug-gated) | error |

Nguyên tắc: không show broken rate, không technical message, **không fake rate**.

**Anti-pattern phát hiện cần xóa (ghi nhận cho GHN-C, không sửa trong SPIKE):**
1. `GHN.php:179` — `$shippingFee = 10` default: không có service khớp vẫn trả rate 10 (fake rate).
2. `GHN.php:212-217` — error log CHỈ khi `isDebug()` → production silent failure.
3. Ahamove `showmethod=1` → error message customer-facing (mặc định nên 0 + policy hide).

---

## 11. Admin / operational failure semantics

Rate failure ≠ operational failure. Chuẩn theo pattern đã có:

| Path | Chuẩn Secomm | Precedent |
|---|---|---|
| Order-sync (async) | Fail closed + log có cấu trúc + MQ retry; KHÔNG fake success | GHN `SynchronizeOrderDataBuilder` (BUG-JBX3H9) |
| Label/shipment submit | Fail → abort shipment save (không false-success) | GHTK native label flow (`Ghtk.php:56-63`) |
| Diagnostic | `CarrierAddressHandoff::getFailureReason()` đủ cho log/ops hiện tại; carrier-local exception (GhnLocationMappingException-style) vẫn hợp lệ cho operational path | — |

`Secomm_OrderOperations` integration: chỉ reserve chỗ — reason codes của §9 là đủ nền tảng;
KHÔNG thiết kế thêm trong SPIKE này.

---

## 12. External resolver integration point (future flow — chưa implement)

* Vị trí: **bên trong `CarrierAddressHandoffService`**, sau local resolve, trước fallback
  evaluation (đúng flow §6). Carrier không chạm pool.
* Selection: **1 provider được config chọn** (`Secomm > Shipping > VN Address Resolution` —
  None | <provider name>) — Launchpad-minimal. KHÔNG provider chaining/priority matrix: pool
  giữ làm registration mechanism (D7), runtime chỉ dùng provider được chọn; selection không hợp
  lệ / `isAvailable()=false` → skip + 1 warning/request, đi tiếp fallback.
* Contract `ExternalAddressResolverInterface` (isAvailable + resolve→?string) GIỮ NGUYÊN —
  `getName()` chỉ thêm khi selection config được implement (đã ghi trong E-A report).
* Đồng bộ SPIKE-9Z231Q v2: VietMap bridge là task riêng đằng sau contract này; pool invocation
  khi AMBIGUOUS là cơ chế "E-B v2" mà 9Z231Q xác định là điều kiện GHN usable — handoff service
  (§4) chính là điểm neo duy nhất của cơ chế đó, không có call-site nào khác.

---

## 13. External resolution result semantics

* Không status mới (giữ 4-state DEC-004): external trả `unit_code` → ShippingCore dựng
  `ResolvedShippingAddress(MAPPED, targetScheme, unitCode)` — kết quả "MAPPED" đúng nghĩa
  (1 deterministic target), nguồn sinh ra là chi tiết.
* Observability: **metadata, không status** — đề xuất thêm `resolutionSource: LOCAL|EXTERNAL`
  (dotted metadata trên handoff/log; hoặc member nhỏ trên handoff DTO nếu audit trail cần ở
  API level — để TL quyết khi implement E-F). KHÔNG đưa vào `ResolvedShippingAddress`canonical.

---

## 14. Cache implications (document-only)

```text
Layer 1 (đã có, E-B):  key = sourceScheme|sourceUnitCode|targetScheme — request-scoped in-memory
Layer 2 (future, E-F): key = canonical tuple + hash(normalized streetText) + hash(sorted candidateCodes)
                       — request-scoped trước; persistent chỉ khi volume AMBIGUOUS/UNMAPPED thật
                       chứng minh cần (business input, kiểu OD-3)
```

* External cache KHÔNG được dùng chung key với local cache (input khác nhau).
* Persisted quote/order snapshot (`scheme_code/unit_code` — OD-2) vẫn là follow-up riêng, tách
  khỏi 2 cache layer này; nếu landing sau E-C thì GHN mapping keyed scheme_code+unit_code đọc
  thẳng identity đã persist, giảm phụ thuộc cache.
* Không Redis/Magento-frontend cho layer local (giữ nguyên E-B); layer 2 nếu cần TTL ngắn có thể
  xem lại `CacheInterface` pattern đã dùng ở GhnAddressMapper/Ghtk — quyết định khi implement.

---

## 15. Capability contract assessment

* Verdict: **ĐỦ cho hiện trạng + E-C/E-D/E-E** — `getRequiredScheme()` + `supportsTextualFallback()`
  phủ đủ nhu cầu handoff đã quan sát (GHN PRE_2025/false, GHTK PRE_2025/true, Ahamove 2025/true).
* Đã kiểm tra giả định "operation-specific requirement": cả 3 carrier đều dùng CÙNG scheme cho
  rate và shipment (GHN rate=label đều cần district/ward; Ahamove text cả hai) — chưa có bằng
  chứng thực tế cần tách theo operation → **không đổi contract** (D10: no speculative abstraction).
* Future-compatible adjustment NHỎ NHẤT nếu sau này chứng minh cần: optional method
  `getRequiredSchemeForOperation(string $operation): ?string` (operation `rate|shipment`; null =
  dùng carrier-level scheme). CHỈ đề xuất — không implement khi chưa có carrier thật đòi hỏi.

---

## 16. Proposed common flow (đã adapt theo audit)

```text
collectRates() [carrier]
  → inactive? → false (Magento native)
  → (fast-path, optional) destCountryId ≠ VN → false        [optimization, không phải nguồn sự thật]
  → CarrierAddressHandoffService.handoff(shippingContext, destination, capability)   [ShippingCore]
        ├─ DestinationContextBuilder: address → bridge → ShippingAddressResolutionContext
        ├─ manager.resolve (local, cached)                    [non-VN → catch → isApplicable=false]
        ├─ unresolved? → external provider (config chọn, 1 lần/request) → MAPPED nếu cứu được
        ├─ vẫn unresolved? → isTextualFallbackEligible = capability.supportsTextualFallback()
        └─ trả CarrierAddressHandoff
  → handoff.isApplicable() === false                  → return false (hide)
  → handoff.isResolved()                              → stage-2: provider mapping → API → rate
  → !resolved + fallbackEligible                      → carrier textual fallback → API → rate | false
  → !resolved + !fallbackEligible                     → return false + warning (reason code)
  → stage-2 provider mapping/API fail                 → return false + warning (reason code)
```

Shipment/order path dùng cùng handoff nhưng failure = fail-closed operational (§11) thay vì hide.

---

## 17. Avoid carrier duplication — ranh giới rành mạch

**MỌI carrier KHÔNG được tự implement (ShippingCore-owned):**

```text
runtime → canonical identity (bridge gọi DestinationContextBuilder)
canonical current↔historical translation (manager + VnAdminAddressResolver)
AMBIGUOUS candidate handling / external resolver selection / external call
canonical result caching
non-VN canonical bypass (exception dịch tại handoff)
fallback ALLOW policy (supportsTextualFallback đọc 1 lần)
```

**Phải là carrier-specific (giữ ở carrier):**

```text
provider IDs + provider location mapping table (stage 2, D6/D8)
provider API payload (kể cả text từ address data)
provider textual fallback representation + chấp nhận/từ chối của provider
API errors / service availability / pickup requirements
rate composition, COD, service catalog
```

---

## 18. Future Vietnam carrier engineering rule (đề xuất chốt)

> **Mọi Secomm-built Vietnam carrier** (rate, shipment/label, order-sync, tracking-địa-chỉ) **PHẢI**
> lấy destination qua `CarrierAddressHandoffService` của `Secomm_ShippingCore` và **KHÔNG được**:
> tự resolve Vietnam administrative scheme/unit, tự gọi external address-disambiguation provider,
> chọn candidate AMBIGUOUS, hard-code scheme/status/repository-key song song, hoặc fake rate khi
> không resolve được.
> Provider identity mapping (stage 2), textual payload, provider API và tính khả dụng service là
> trách nhiệm của carrier. Rate-unavailable = no-rate + structured log; exception canonical
> không bao giờ leak ra storefront.

Áp dụng cho GHN-C/GHTK-D/Ahamove-E và mọi carrier mới; viết vào README ShippingCore khi E-C
implement.

---

## 19. Recommended implementation phases (nhỏ, có thứ tự)

| # | Phase | Nội dung | Phụ thuộc |
|---|---|---|---|
| 1 | **E-C0 handoff contracts** | `DestinationContextBuilder` + `CarrierAddressHandoff` DTO + `CarrierAddressHandoffService` + failure-reason constants (ShippingCore only; unit tests; 0 carrier đổi; spec-first riêng) | SPIKE này được TL/SA approve §4/§5/§9 (OD-1..OD-3) |
| 2 | **E-F external resolution** | Selection config + pool invocation trong handoff service + `getName()` + external cache layer (§12/§13/§14). **Lên trước GHN-C**: SPIKE-9Z231Q v2 chứng minh AMBIGUOUS = 93.26% chiều GHN → external + AMBIGUOUS policy là điều kiện GHN usable | 1 + business chọn provider (VietMap — OD-3 cũ/SPIKE-9Z231Q) |
| 3 | **E-C GHN migration** | GHN consume handoff (đúng directive SPIKE-9Z231Q GHN-C); migrate mapping key `region_id+city_id` → `scheme_code+unit_code`; xóa `$shippingFee=10` + debug-only logging (§10 anti-patterns) | 1 + 2 + TASK-5XDG1P approved |
| 4 | **E-D GHTK consume handoff** | Giữ `DestinationAddressResolver` làm textual-fallback machinery; thay name-first entry bằng canonical identity | 1 |
| 5 | **E-E Ahamove consume handoff** | Chủ yếu applicability + EXACT-short-circuit; text payload giữ nguyên | 1 |
| 6 | *(optional)* diagnostic enrichment | Reason-code → OrderOperations bridge | khi OrderOperations thật sự cần |

*(Đổi so với đề xuất sơ bộ trong SPIKE này: E-F được đẩy lên trước E-C theo evidence
reverse-mapping của SPIKE-9Z231Q v2 — external disambiguation là precondition của GHN usable,
không phải "đợi volume chứng minh" — volume đã được chứng minh bằng audit 2025→PRE_2025.)*

---

## 20. Open decisions (chỉ cái TL/SA thật sự phải chốt)

| # | Decision | Vì sao chưa chốt |
|---|---|---|
| OD-1 | Approve **Option B-minimal** (handoff DTO + service — API surface mới trước E-C) | Là quyết định architecture contract; directive §4 chỉ định chọn nhưng việc thêm contract cần Tier-2 |
| OD-2 | Failure-reason vocabulary dạng string constants trên handoff contract (§9) | Shape API; nhẹ hơn enum nhưng là commitment dài hạn |
| OD-3 | non-VN: handoff non-exception result là kênh DUY NHẤT carrier-facing (exception chỉ nội bộ ShippingCore) | Sửa "carriers sẽ thấy gì"; directive §5 mở 2 khả năng |
| OD-4 | Secomm rate policy: mặc định hide (không Error object) kể cả khi showmethod=1 — hay tôn trọng showmethod của Magento | UX policy + tương thích Magento native |
| OD-5 | External: single-configured-provider (không chaining) + metadata LOCAL/EXTERNAL | Launchpad-minimal nhưng chặn trước nhu cầu multi-provider tương lai |
| OD-6 | GHN legacy: migrate tại chỗ (GHN-C) vs module mới `Secomm_Ghn` (hướng SPIKE-9Z231Q) | Thuộc phạm vi 2 SPIKE; cần TL/SA pick — handoff semantics của SPIKE này đúng cho cả 2 hướng |

## Phụ lục — files inspected (2026-09-08)

ShippingCore: `Api/Address/*`, `Model/Address/*` (manager/VOs/pool/exception), `Api/{ShippingContextInterface, OriginInterface, OriginProviderInterface}`, `Model/{ShippingContext, ShippingContextFactory, Origin, OriginProvider/ShippingOriginProvider, Tracking/ShipmentTrackingProcessor}`, `etc/{di.xml, module.xml}`.
VietNamAddress: `Api/{VnAdminAddressResolverInterface, VnOperationalAddressResolverInterface, Data/VnAddressResolutionInterface, VnAddressUnitProviderInterface}`, `Model/{VnAdminAddressResolver, MappingCandidateFinder, Scheme/VnSchemes}` (+ grep 0-consumer của operational resolver).
GiaoHangNhanh: `Model/Carrier/GHN.php`, `Model/Carrier/GHN/{Express,Standard}.php` (grep canDisplay), `Model/Service/Request/{AbstractDataBuilder, ShippingDetailsDataBuilder}.php`, `Model/Exception/GhnLocationMappingException.php`.
GhnAddressMapper: `Model/LocationResolver.php`, `Api/LocationResolverInterface` (qua consumer).
Ghtk: `Model/Carrier/Ghtk.php`, `Model/Address/{DestinationAddressResolver, BestEffortViVnResolver, WardIdBridge}.php`.
Ahamove: `Model/Carrier/AhamoveAbstractCarrier.php` (collectRates/estimateShippingCost/checkAvailableShipCountries/getCityTo/getFullStreet).
Research nền: SPIKE-W273TB, SPIKE-9Z231Q (historical — giữ nguyên, không rewrite).
