# Task Spec: Secomm_Ghtk — alignment lên target architecture Secomm_ShippingCore (Phase E-C1/GHTK)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-7AJ3K8 (parent FEAT-YA2C0W) |
| Mode | A |
| Decision basis | DEC-FEATYA2C0W-004 (accepted 2026-09-03) + DEC-TASK7AJ3K8-001 (4 amendments) |
| Audit basis | Code audit 2026-09-10 trên branch `development` (file:line trong §2) |
| Risk | High — carrier runtime + shared contracts; Tier-2 review trước merge |

## 1. Objective

`Secomm_Ghtk` hiện tự giữ VN address resolution (raw-SQL directory + first-match), tự giữ transport
(curl/timeout/retry/JSON), tự giữ tracking-reconciliation orchestration. Refactor đưa các phần
generic về `Secomm_VietNamAddress` / `Secomm_ShippingCore`, giữ nguyên mọi phần carrier-specific.
Đây là consumer runtime ĐẦU TIÊN của pipeline E-A/E-B/E-C0 — các carrier sau (GiaoHangNhanh,
Ahamove, SPX, Grab) tái dùng cùng shape.

## 2. Verified implementation basis (audit 2026-09-10)

**Đã đúng kiến trúc (giữ nguyên):**

- `GhtkOriginProvider` legacy-override chain + strict pickup gate (`PickupAddressResolver`) — đúng
  carrier policy layer (DEC-021).
- Origin rate-path và shipment-submit dùng chung `OriginProviderInterface` chain qua
  `ShippingContextFactory` (`Model/Carrier/Ghtk.php:241-244`, `Model/OrderSubmit/OrderSubmitService.php:61-64`).
- Webhook + API reconciliation cùng feed `CarrierTrackingProcessorInterface` (SL-017) — pipeline
  chung đã đúng, chỉ orchestration loop còn nằm ở GHTK.
- `GhtkStatusMapper` nằm đúng carrier module.
- Native `AbstractCarrierOnline` label lifecycle (SL-016) — không đổi.
- `secomm_ghtk_address_map` schema: runtime-keyed (country, region, ward) + no-FK — hợp swap model
  hiện tại; KHÔNG trùng canonical mapping (`secomm_vietnam_address_mapping` giữ cross-scheme
  relations; bảng GHTK giữ GHTK-accepted TEXT names) → là carrier alias map, giữ lại (không xóa,
  không re-key trong task này — D6 migration là follow-up DB riêng).

**Vi phạm boundary (fix trong task):**

| # | Finding | Evidence |
|---|---------|----------|
| F1 | `WardIdBridge` raw-SQL `directory_region_city` + first-match khi ambiguous | `Model/Address/WardIdBridge.php:38-62` |
| F2 | `BestEffortViVnResolver` biết `directory_country_region_name` / `directory_region_city_name` / `vi_VN` | `Model/Address/BestEffortViVnResolver.php:38-81` |
| F3 | `DestinationAddressResolver` combine 4 trách nhiệm (recover identity → map → fallback → GhtkAddress) | `Model/Address/DestinationAddressResolver.php:46-133` |
| F4 | Name-match chỉ target `default_name` (= `name_en` sau import `VnAddressSchemeImporter.php:521,531`) trong khi storefront submit tên vi → match fail tiềm ẩn trên store vi | `WardIdBridge.php:44` + importer evidence |
| F5 | `GhtkApiClient` tự xử lý transport + retry + JSON; endpoint hard-code rời rạc | `Model/GhtkApiClient.php:26-27,42-62,178-226` |
| F6 | `TrackingRefreshService` tự làm orchestration carrier-agnostic (state query/terminal/stale/batch/continue) | `Model/Tracking/TrackingRefreshService.php:45-81` |
| F7 | `ShippingOriginProvider` comment/semantic "VN 2-level: ward = config city" nằm trong generic provider | `Model/OriginProvider/ShippingOriginProvider.php:25-30,68` |
| F8 | Pipeline E-C0 chưa dùng được runtime: quote/order address không mang `city_id`; `DestinationContextBuilder` chỉ resolve id-based → identity null → UNMAPPED sai | `Model/Address/DestinationContextBuilder.php:40-43` |
| F9 | Không carrier nào consume handoff (grep 2026-09-10: chỉ ShippingCore + tests) | grep `CarrierAddressHandoffService` |

## 3. Scope — Secomm_VietNamAddress

### 3.1 Name-based operational bridge (D5 entry tạm thời)

- `Api\VnOperationalNameResolverInterface` — SIBLING contract, KHÔNG sửa
  `VnOperationalAddressResolverInterface` (đã freeze ở TASK-Q4B98P):
  `resolveWardByName(int $regionId, string $wardName): VnOperationalNameResolutionInterface`.
- `Api\Data\VnOperationalNameResolutionInterface`: `getStatus()` (reuse
  `VnAddressResolutionInterface::STATUS_EXACT|AMBIGUOUS|UNMAPPED` — không параллel constants),
  `getIdentity(): ?VnOperationalIdentityInterface`, `getCandidateCodes(): string[]`, `getReason(): ?string`,
  `isResolved(): bool`.
- `Model\VnOperationalNameResolver`: region row (VN gate) → region code active scheme →
  `VnAddressUnitProviderInterface::getChildren(activeScheme, regionCode)` → match `name_vi` HOẶC
  `name_en` (exact sau trim — fix F4) → 0: UNMAPPED; >1: AMBIGUOUS (candidates, KHÔNG pick); 1:
  identity qua `VnOperationalAddressResolverInterface::resolveFromCanonical(activeScheme, unitCode)`
  (compose, không duplicate SQL). Active scheme theo cùng rule trust config+registry với resolver cũ.

### 3.2 Không đổi

Scheme swap guard, importer, admin/cross-scheme resolver, reference layer — đọc thôi.

## 4. Scope — Secomm_ShippingCore

### 4.1 Runtime address context builder (fix F8)

- `Api\Address\RuntimeAddressContextBuilderInterface`:
  `build(?string $countryId, int $regionId, ?int $cityId, ?string $cityName, ?string $streetText = null): ShippingAddressResolutionContextInterface`.
- `Model\Address\RuntimeAddressContextBuilder`: id-based trước (`resolveFromRuntime`), thiếu
  cityId → name-based (`VnOperationalNameResolverInterface`, `$cityName` non-empty); identity null
  + candidates → context mang `candidateCodes` (canonical active-scheme codes) + sourceScheme = active.
- `DestinationContextBuilder` (quote, E-C0) refactor delegate scalar builder (thêm cityName =
  `$destination->getCity()`); giữ nguyênPII hygiene (street only).

### 4.2 Manager candidates passthrough

`ShippingAddressResolutionManager::resolveLocally`: identity missing + candidates non-empty →
`AMBIGUOUS` (candidates pass-through, document: candidates đã là canonical target-scheme codes —
name bridge resolve trong active scheme; cross-scheme candidate translation là việc của disambiguation
phase sau). Identity missing + no candidates → UNMAPPED (như cũ).

### 4.3 Handoff service — entry thứ 2 cho non-quote sources

`CarrierAddressHandoffServiceInterface` += `handoffContext(context, capability): CarrierAddressHandoffInterface`
(additive; `handoff(Address, …)` giữ nguyên, delegate vào đường chung). Non-quote carriers
(RateRequest / Sales order address) không phải dựng `Quote\Address` giả.

### 4.4 Carrier API profile contract (P1)

- `Api\CarrierApiProfileInterface`: `getCode(): string; getAddressScheme(): ?string` (lean theo
  directive; endpoint getters là phần implementation riêng của từng carrier profile class).
- Preference → không default (chỉ carrier đăng ký profile class riêng; ShippingCore không giữ registry).

### 4.5 Shared HTTP primitive (P1 — DEC-TASK7AJ3K8-001 §1)

- `Api\Http\CarrierHttpErrorCategory` (final, constants): `TIMEOUT | NETWORK | RATE_LIMIT |
  SERVER_ERROR | CLIENT_ERROR | INVALID_RESPONSE`.
- `Api\Http\CarrierHttpException`: `getCategory(): string`.
- `Api\Http\CarrierHttpClientInterface`: `send(CarrierHttpRequest): CarrierHttpResponse` +
  `sendJson(CarrierHttpRequest): array` (decode strict → `INVALID_RESPONSE`).
- `Model\Http\CarrierHttpRequest` (DTO: method, uri, headers, body, timeoutConnect, timeoutTotal),
  `Model\Http\CarrierHttpResponse` (status, body).
- `Model\Http\CurlCarrierHttpClient`: Magento Curl; status 0 → `NETWORK` (timeout không tách được
  qua Magento client — documented; `TIMEOUT` dành cho adapter expose errno); 429 → `RATE_LIMIT`;
  other 4xx → `CLIENT_ERROR`; 5xx → `SERVER_ERROR`.
- `Model\Http\RetryPolicy` (maxAttempts + retryableCategories) + `Model\Http\RetryExecutor::
  execute(callable, RetryPolicy): mixed` (catch `CarrierHttpException`, retry khi category ∈ set).
- KHÔNG auth/endpoint/payload ở đây (D2). 429 KHÔNG retry (giữ behavior cũ: 4xx never retry).

### 4.6 Shared tracking reconciliation (P1)

- `Api\Tracking\CarrierTrackingFetcherInterface`: `fetch(string $trackingNumber): ?TrackingUpdateInterface`
  (null = không có status mới; throw = lỗi transport cho service tiếp tục item kế).
- `Model\Tracking\TrackingReconciliationService`: ctor (state collection factory, fetcher,
  `string $carrierCode`, logger, `int $batchSize = 50`); `refresh(int $staleThresholdSeconds): int`
  — logic y hệt F6 (terminal `nin`, stale filter PHP, page 1 size batch, per-item try/catch
  continue). Carrier config gate (enabled/threshold) ở carrier side.

### 4.7 Origin — VN semantics (P1, resolution lean)

`ShippingOriginProvider`: KHÔNG đổi behavior; comment/contract đổi:ObjectInterface `getWard()` được
define lại = generic locality slot mà AddressDropdown profile engine lưu ở native `city` field (platform
convention, không riêng VN); VN canonical origin enrichment (unit_code metadata) KHÔNG làm lúc này —
chưa có consumer (D10), decorator VietNamAddress là việc sau khi MSI/§23 xuất hiện. DEC-TASK7AJ3K8-001 §4.

## 5. Scope — Secomm_Ghtk

### 5.1 Thêm

- `Model\Address\GhtkAddressCapability implements CarrierAddressCapabilityInterface`:
  requiredScheme = `VnSchemes::VN_ADMIN_2025` (từ profile), `supportsTextualFallback() = true`.
- `Model\GhtkApiProfile implements CarrierApiProfileInterface`: code `GHTK_2025`; scheme
  `VN_ADMIN_2025`; + `getFeePath() / getOrderPath() / getOrderStatusPath(labelId)`.
- `Model\Address\GhtkDestinationResolver` (Stage-2 adapter, thay `DestinationAddressResolver`):
  `resolve(?string $countryId, int $regionId, ?int $cityId, ?string $wardName): ?GhtkAddress`
  — flow §6. Never-throw giữ nguyên (rate path); cache Magento giữ nguyên, key theo canonical
  unitCode (fallback key theo region+name hash).
- `Model\Tracking\GhtkTrackingFetcher implements CarrierTrackingFetcherInterface` (extract order
  block + `GhtkStatusMapper` + build `TrackingUpdate`, source `api`).
- `TrackingRefreshService` thành thin shell: config gate (enabled) + threshold → delegate shared
  service (fetcher/carrierCode/batch qua virtual type DI).

### 5.2 Sửa

- `GhtkApiClient`: transport qua shared client/profile; giữ `GhtkApiException` (public surface) —
  map category→isRetryable (TIMEOUT/NETWORK/SERVER_ERROR=true; RATE_LIMIT/CLIENT_ERROR/
  INVALID_RESPONSE=false). `getFee` = `RetryExecutor` policy (1 + `retry_max`, categories
  NETWORK/SERVER_ERROR/TIMEOUT). `submitOrder` = single attempt (KHÔNG đổi). Headers
  Token/X-Client-Source/Content-Type vẫn GHTK build.
- `PickupAddressResolver`, `Carrier\Ghtk`, `OrderSubmitService`: đổi call sang
  `GhtkDestinationResolver` (giữ nguyên gates/hide/throw semantics).

### 5.3 Xóa

`Model\Address\WardIdBridge`, `Model\Address\BestEffortViVnResolver`,
`Model\Address\DestinationAddressResolver` (+ tests tương ứng).

## 6. GHTK destination resolution matrix (Stage-2)

| Canonical handoff outcome | GHTK behavior |
|---|---|
| non-VN (not applicable) | null (caller hide/throw theo path) |
| EXACT/MAPPED unitCode | reverse bridge → runtime ids → alias `findActive` hit → GHTK names (exact=true); alias miss → `name_vi` (unit + region unit) (exact=false) |
| AMBIGUOUS | **null + log** — KHÔNG pick (thay first-match cũ — intentional, directive bắt buộc) |
| UNMAPPED + fallback eligible | province = name_vi region unit (bridge region-only), ward = submitted text, exact=false + log warning |
| UNMAPPED + no fallback | null |

## 7. DI

- ShippingCore: preference `CarrierHttpClientInterface → CurlCarrierHttpClient`; preference
  `RuntimeAddressContextBuilderInterface`, `CarrierApiProfileInterface` (không default type —
  chỉ khai báo contract; GHTK inject class cụ thể).
- VietNamAddress: preference `VnOperationalNameResolverInterface`.
- Ghtk: virtual type `GhtkTrackingReconciliation` (shared service + `GhtkTrackingFetcher`,
  carrierCode `ghtk`, batchSize 50) inject vào `TrackingRefreshService`.

## 8. Out of scope

GHN/Ahamove/SPX/Grab migration · alias re-key scheme-aware (D6 — DB migration follow-up, Tier-2) ·
external resolver providers · service-level aggregation/fallback (E-SL1/2) · webhook contract ·
system.xml mới · checkout OSC · Launchpad_* modules.

## 9. Acceptance Criteria

- AC-1: `grep -rn "directory_region_city\|directory_country_region_name\|directory_region_city_name\|vi_VN" app/code/Secomm/Ghtk --include='*.php'` → 0 hit production code.
- AC-2: destination resolution đi qua `CarrierAddressHandoffServiceInterface` duy nhất; 0 carrier class
  type-hint resolver/manager của ShippingCore ngoài handoff + runtime context builder.
- AC-3: AMBIGUOUS name match → resolver null, 0 candidate được pick (test chứng minh).
- AC-4: alias hit → exact GHTK names; alias miss → canonical `name_vi` (test cả 2).
- AC-5: legacy pickup config: all-empty → delegate; any-populated → legacy branch (test giữ nguyên pass).
- AC-6: fee GET retry NETWORK/SERVER_ERROR theo `retry_max`; 4xx/429/invalid-JSON không retry;
  submitOrder single attempt (test cả 4).
- AC-7: taxonomy đủ 6 category; `CurlCarrierHttpClient` classify đúng (test).
- AC-8: reconciliation orchestration ở shared service (terminal exclude, stale, batch, per-item
  continue — test); GhtkTrackingFetcher map + UNKNOWN (test).
- AC-9: webhook path không đổi (test cũ giữ nguyên pass).
- AC-10: name bridge: exact vi/en match, ambiguous (candidates), unmapped, non-VN region (test).
- AC-11: runtime context builder: id-based ưu tiên; name fallback; candidates passthrough qua
  manager → AMBIGUOUS (test).
- AC-12: phpunit secomm suite pass; `bin/project-ai-validate` 0 new finding; module compile ok.

## 10. Test plan (unit, AAA)

Xem plan `TASK-7AJ3K8-implementation-plan.md` §Test plan — ma trận theo §11 directive (Address /
Origin / HTTP / Tracking), PHPUnit 10.5, AAA, Magento object mocks theo pattern existing tests.

---

# r1 AMENDMENT (2026-09-10) — DEC-TASK7AJ3K8-002: address mode TEXT_NATIVE

Material correction theo directive r1 (cùng ngày). r0 đặt alias map lookup-first; r1 đổi premise.

## r1. Objective correction

`Secomm_VietNamAddress` cung cấp canonical VN address; `Secomm_Ghtk` gửi TRỰC TIẾP canonical
Vietnamese names (`name_vi`) sang GHTK — `TEXT_NATIVE`. `secomm_ghtk_address_map` KHÔNG còn là
mandatory runtime dependency; chỉ là exception/override table.

## r1. Verified basis (audit r1)

- r0 adapter (`GhtkDestinationResolver`) lookup alias FIRST, canonical `name_vi` chỉ là fallback
  best-effort → contradicts TEXT_NATIVE premise (5/sandbox directive).
- GHTK fee/order API nhận TEXT address fields; district nullable theo Q-EXT hiện có — không có
  carrier IDs. Lookup-first bảng map 3k+ rows gần-duplicate canonical names = unnecessary duplication.
- r0 nhánh "UNMAPPED → province name_vi + ward text submit" = guessed address → bị cấm bởi rule mới.
- DEC-001 hoãn re-key D6; r1 re-key bảng sang canonical identity vì override keyed runtime sẽ
  mất sau scheme swap — canonical key là shape đúng cho override data.
- Sandbox GHTK (dev.giaohangtietkiem.vn) không reachable từ dev env + DB local down (2026-09-10)
  → Option A (remove table) CHƯA có evidence → chọn Option B (override-only, dataset rỗng mặc định).

## r1. Scope changes (so với §5 của SPEC gốc)

| # | Thay đổi |
|---|----------|
| r1-1 | `GhtkDestinationResolver` → rename `GhtkAddressAdapter` (destination + pickup chung; §9 không tạo 2 hệ resolution) |
| r1-2 | Adapter algorithm §12: canonical names (unit provider) là default; override by canonical identity là optional; unresolved → null; KHÔNG textual fallback; validate province/ward non-empty |
| r1-3 | Schema `secomm_ghtk_address_map`: keys `scheme_code+province_code+ward_code` (UNIQUE), overrides nullable + `note`; DROP `country_id/region_id/ward_id` + old unique; DROP guard registration + class; **setup:upgrade required** |
| r1-4 | Entity/repository/import rename: `GhtkAddressOverrideInterface` / `GhtkAddressOverrideRepository` / `GhtkAddressOverrideImport\*` |
| r1-5 | CSV header mới `scheme_code,province_code,ward_code,ghtk_province,ghtk_district,ghtk_ward,is_active,note`; validator 6 checks qua `VnAddressUnitProviderInterface` (valid scheme/province/ward, ward∈province, ≥1 override, no dup) |
| r1-6 | `GhtkAddressCapability::supportsTextualFallback()` → **false**; `GhtkApiProfile::getAddressMode()` = `TEXT_NATIVE` |
| r1-7 | Observability: `CANONICAL_ADDRESS_UNRESOLVED` / `GHTK_ADDRESS_INVALID` / `GHTK_OVERRIDE_APPLIED` (debug) |
| r1-8 | Reverse bridge (resolveFromCanonical) RÚT KHỎI rate path — canonical names không cần runtime ids |
| r1-9 | `GhtkAddress::$isExact` semantic mới: true = override applied; false = canonical native text |

## r1. Behavior changes (documented, intentional)

1. AMBIGUOUS/UNMAPPED → hide rate + fail-fast submit (r0: UNMAPPED vẫn có best-effort rate).
2. Bảng mapping trống mặc định vẫn hoạt động full flow (canonical-first).
3. Deploy cần `setup:upgrade`; existing map rows phải re-import CSV format mới.

## r1. Out of scope (không đổi)

Carrier khác (GHN/Ahamove) · legacy pickup config chain · webhook · HTTP primitive · E-SL1/2.

## r1. Acceptance additions

AC-r1-1: canonical name_vi là default source cả destination + pickup name-path (test).
AC-r1-2: adapter KHÔNG gọi repository khi... (no — vẫn gọi để check override; nhưng override miss
        KHÔNG đổi kết quả). Override hit → text thay đúng trường; disabled row → bỏ qua (test).
AC-r1-3: AMBIGUOUS/UNMAPPED → null, 0 text guess (test).
AC-r1-4: validator 6 checks + CSV header mới (test).
AC-r1-5: fee + create-order + pickup dùng cùng `GhtkAddressAdapter` (constructor grep + test).
AC-r1-6: legacy pickup chain giữ nguyên (test cũ pass); pick_address_id ưu tiên (test cũ pass).
AC-r1-7: grep 0 "mapping miss → no rate" / "exact mapping required" semantics còn sót.
AC-r1-8: E2E sandbox PENDING — runbook trong evidence; không chặn code-complete.
