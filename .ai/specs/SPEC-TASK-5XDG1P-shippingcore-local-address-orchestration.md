# Task Spec: ShippingCore — Local canonical shipping-address orchestration (Phase E-B)

Specification ID: SPEC-TASK-5XDG1P

> Filename: `SPEC-TASK-5XDG1P-shippingcore-local-address-orchestration.md` — standalone work-item
> spec (slice Phase E-B của FEAT-YA2C0W; behavioral contract riêng, kế thừa contract shapes của
> SPEC-TASK-AQT7V3 mà không mở rộng chúng).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-5XDG1P |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-B) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved Phase E-B directive; architecture pre-approved qua DEC-FEATYA2C0W-004 + SPIKE-W273TB |
| Status | **VALID** — runtime flow + cache + non-VN bypass theo approved directive; TL review spec text chạy cùng code pre-review. **r1 (2026-09-08): TL-review cleanup** — `getReceiverText()` removed khỏi context contract (PII hygiene trước contract freeze, §6) |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D1 dependency · D2 ownership · D9 ambiguity · D10 no-abstraction — accepted 2026-09-03) |
| Related Ticket(s) | TASK-5XDG1P · TASK-AQT7V3 (E-A contracts) · SPIKE-W273TB (architecture evidence) |
| Workflow Mode | A (shipping shared-contract = generic risk category, AGENTS §9/§12) |

## 1. Objective

Implement concrete local canonical orchestration trong `Secomm_ShippingCore` — runtime flow:

```text
ShippingAddressResolutionContext
  → ShippingAddressResolutionManager (validate → cache lookup)
  → VnAdminAddressResolver (Secomm_VietNamAddress — canonical graph)
  → EXACT / MAPPED / AMBIGUOUS / UNMAPPED
  → ResolvedShippingAddress (cached, returned)
```

**Local resolution ONLY** — KHÔNG external resolver call, KHÔNG carrier integration, KHÔNG
persistence. `ExternalAddressResolverPool` vẫn là extension point đứng ngoài manager (E-C+).

## 2. Verified implementation basis (audit 2026-09-08, code thật)

* E-A contracts tồn tại đúng shape: `ShippingAddressResolutionManagerInterface` (1 method, chưa có
  impl + chưa có DI preference — `etc/di.xml:22-24`); `ResolvedShippingAddress` VO enforce toàn bộ
  invariant ở constructor (AMBIGUOUS không thể lộ unitCode; UNMAPPED luôn candidates rỗng;
  status lạ/EXACT-MAPPED thiếu unitCode/scheme rỗng → `LogicException`).
* `VnAdminAddressResolver::resolve(sourceScheme, sourceCode, targetScheme)` — semantics cần REUSE
  (KHÔNG duplicate): same-scheme + unit tồn tại → `EXACT` (resolvedCode = sourceCode, không cần
  mapping edge); same-scheme + unit lạ → `UNMAPPED/unknown_source_unit`; cross-scheme → union
  outgoing+incoming edges, cardinality authoritative (1 → MAPPED, >1 → AMBIGUOUS candidates đã
  sort+unique, 0 → UNMAPPED có reason); **unknown scheme code → `LocalizedException`** (config
  fault, `VnSchemes::assertKnown`).
* Cardinality authoritative đã nằm ở resolver (`relation_type` chỉ descriptive) — ShippingCore
  KHÔNG thêm logic MERGED_INTO/SPLIT_INTO.
* **Không có representation "not applicable" nào trong ShippingCore** (grep `applicable|unsupported`
  = 0 hit) — return contract 4-status canonical không represent được non-VN bypass ⇒ điều chỉnh
  nhỏ nhất, xem §4.2.
* Logging convention module: `Psr\Log\LoggerInterface` constructor-injection (`ShipmentTrackingProcessor`).
* Precedent domain exception fail-closed: `GiaoHangNhanh\Model\Exception\GhnLocationMappingException
  extends LocalizedException` (BUG-JBX3H9).
* Cache precedent: SPIKE-W273TB §7 khuyến nghị "Context-level cache (in-memory, request-scoped)"
  — không persistence, không Redis/Magento cache frontend.

## 3. Scope — `Secomm\ShippingCore\Model\Address`

### 3.1 ShippingAddressResolutionManager (final, implements interface)

Constructor DI: **duy nhất** `VnAdminAddressResolverInterface $adminAddressResolver`. Không logger
(xem §6), không pool, không capability matrix.

```text
resolve(context, capability):
  1. targetScheme = capability.getRequiredScheme()          // member DUY NHẤT của capability được dùng
  2. countryId ≠ null && countryId ≠ 'VN'                    // so sánh case-insensitive sau trim
     → throw UnsupportedDestinationException                 // bypass — resolver KHÔNG bao giờ được gọi
  3. cacheKey = sourceScheme | sourceUnitCode | targetScheme // raw values, byte-exact, không trim
  4. cache hit → return cached ResolvedShippingAddress
  5. miss:
     sourceScheme hoặc sourceUnitCode null/rỗng (trim)
       → ResolvedShippingAddress(UNMAPPED, targetScheme, null)   // không gọi resolver — §4.3
     ngược lại
       → adminAddressResolver.resolve(sourceScheme, sourceUnitCode, targetScheme)
       → convert §3.2
  6. cache[result] và return
```

`supportsTextualFallback()` **không được gọi** (textual fallback là orchestration phase sau —
directive E-B §12). Context `getStreetText()`/`getCandidateCodes()` **không được consume**;
`getReceiverText()` **đã bị XÓA khỏi contract** theo TL review — PII hygiene, xem §6.

### 3.2 Canonical conversion (match 1-1, không diễn giải lại)

| VnAddressResolution | ResolvedShippingAddress |
|---|---|
| EXACT (resolvedCode) | EXACT, unitCode = resolvedCode, candidates = [] |
| MAPPED (resolvedCode) | MAPPED, unitCode = resolvedCode, candidates = [] |
| AMBIGUOUS (candidates sorted) | AMBIGUOUS, unitCode = null, candidates giữ nguyên thứ tự |
| UNMAPPED | UNMAPPED, unitCode = null, candidates = [] |
| status khác (impossible) | `LogicException` — invariant failure (directive §18) |

`schemeCode` của result = targetScheme (capability). Canonical response EXACT/MAPPED thiếu
resolvedCode → `LogicException` từ VO constructor (impossible resolver response — §18).

### 3.3 Local resolution cache

* Scope: **in-memory array trên manager instance** — Magento DI chia sẻ instance theo area nên
  mảng sống đúng 1 request (mode developer/production như nhau); KHÔNG Redis, KHÔNG Magento cache
  frontend, KHÔNG DB, KHÔNG session (directive §7).
* Key: `sourceScheme|sourceUnitCode|targetScheme` (raw, không trim — canonical identity là
  byte-exact). `countryId` KHÔNG nằm trong key: non-VN không bao giờ chạm cache (bước 2 ném trước)
  và canonical identity là VN-invariant (directive §8 cho phép loại).
* Value: `ResolvedShippingAddress` VO final + immutable → an toàn chia sẻ lại.
* AMBIGUOUS / UNMAPPED (kể cả missing-identity) được cache như resolved — không recomputes trong
  1 request (directive §10).
* Không có invalidation: dataset mapping không đổi giữa chừng trong 1 request (import chạy CLI,
  ngoài shipping flow) — ghi rõ trong class docblock.

## 4. Behavior contracts (TL review points)

### 4.1 EXACT/MAPPED/AMBIGUOUS/UNMAPPED

Nguyên văn directive §3/§4: EXACT/MAPPED → unitCode populated + candidates rỗng + isResolved true;
AMBIGUOUS → unitCode null bắt buộc + toàn bộ candidates + isResolved false (**không bao giờ chọn
candidate đầu**); UNMAPPED → unitCode null + candidates rỗng. Không state nào được "nâng cấp"
AMBIGUOUS/UNMAPPED thành MAPPED.

### 4.2 Non-VN → bypass qua domain exception (contract adjustment BẮT BUỘC report)

Return contract (`ResolvedShippingAddressInterface`, 4 status canonical, never-null) **không**
represent được "not applicable" mà không thêm status thứ 5 (bị cấm) hoặc abuse UNMAPPED (bị cấm).
Điều chỉnh nhỏ nhất — **không đổi signature bất kỳ interface nào**:

* `Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException extends
  LocalizedException` — mirror precedent fail-closed `GhnLocationMappingException`.
* Manager ném exception này khi `countryId` non-null ≠ 'VN' (case-insensitive); resolver không
  được gọi; không có gì vào cache.
* Docblock `ShippingAddressResolutionManagerInterface` cập nhật MINIMAL để contract trung thực:
  ghi nhận bypass channel + sửa text forward-looking E-A ("E-B will provide external
  disambiguation + textual-fallback") thành thực tế E-B (local-only; external + textual fallback
  là phase sau). Docblock-only, không đổi signature/member.
* `countryId = null` (unknown): guard không kết luận non-VN → đi tiếp vào resolution; canonical
  identity (nếu có) là authoritative. Null + missing identity → rơi vào §4.3.

### 4.3 Invalid context (missing/invalid canonical identity)

`sourceScheme` hoặc `sourceUnitCode` null/rỗng trên destination VN → result `UNMAPPED`
(unitCode null, candidates rỗng) **không gọi resolver** — "explicit unresolved/failure according
to current contract" (directive §13): vocabulary unresolved duy nhất của contract là status
UNMAPPED; đây là runtime state bình thường (address legacy chưa có canonical identity), KHÔNG
throw, KHÔNG log (§17), và được cache như mọi outcome khác. Không fallback về region_id/city_id/
ward name/street parsing.

### 4.4 Unknown scheme code

`sourceScheme`/`targetScheme` không nằm trong VnSchemes catalog → `LocalizedException` từ
`VnAdminAddressResolver` **propagate nguyên văn** (config/programming fault — directive §18;
rule [BLOCK] không nuốt exception). Manager không wrap, không catch-all. Không cache (nothing
sensible to cache).

### 4.5 Capability usage

Chỉ `getRequiredScheme()`. `supportsTextualFallback()` không được execute (directive §12) —
không redesign capability contract speculatively.

## 5. DI

`etc/di.xml`: thêm
`<preference for="…ShippingAddressResolutionManagerInterface" type="…Model\Address\ShippingAddressResolutionManager"/>`
+ comment DEC/TASK; thay comment "no preference yet" của E-A. KHÔNG plugin/preference ngoài
ShippingCore; KHÔNG đụng carrier DI; pool giữ nguyên array rỗng.

## 6. receiverText / streetText / context candidates (audit directive §14 + TL-review cleanup)

Audit E-B: `ShippingAddressResolutionContextInterface::getReceiverText()` docblock E-A
*"Receiver/contact name — disambiguation hint only"* — **là PII người nhận (tên)**, KHÔNG phải
supplementary address text; local canonical resolution keyed bởi canonical identity nên không cần.
**TL review E-B chốt XÓA** khỏi contract + concrete DTO (property/param/getter) trước contract
freeze — KHÔNG thay thế bằng field recipient identity nào khác (customer name/phone/email cấm).
Không có production logic nào consume (audit grep: 0 consumer ngoài interface/impl/test; 0 carrier
reference). `getStreetText()` + context `getCandidateCodes()` GIỮ NGUYÊN cho external
disambiguation sau này. Regression guard: unit test assert `method_exists(...'getReceiverText')`
= false trên cả interface + concrete.

Semantic boundary của context `candidateCodes` (không đổi behavior E-B):

```text
initial local canonical resolution
→ KHÔNG trust/dùng caller-supplied candidateCodes (manager chỉ đọc identity + countryId)

external disambiguation context (phase sau)
→ có thể dùng candidateCodes do local resolution sản xuất (AMBIGUOUS targets)
```

> External address disambiguation may use address-related textual context such as street text
> and canonical candidates. Recipient identity is not part of the address-resolution contract.

## 7. Out of scope

External resolver invocation (pool đứng ngoài manager) · provider selection config · VietMap/
Google · textual fallback execution · carrier code (GHN/GHTK/Ahamove/GiaoHangNhanh/GhnAddressMapper)
· provider IDs · persistence (quote/order snapshot) · origin resolution (OD-1) · fuzzy/name
normalization · geocoding · Magento cache frontend/Redis/session · logger injection (không có gì
đáng log ngoài expected states — §6 spec) · i18n (không có UI string) · DB schema.

## 8. Acceptance Criteria

* **AC-1**: `ShippingAddressResolutionManager` implements interface; delegate canonical resolution
  100% qua `VnAdminAddressResolverInterface` — 0 logic mapping/cardinality tự chế trong ShippingCore
  (grep: không có constant scheme, không có status literal song song).
* **AC-2**: 4-state semantics đúng §4.1; AMBIGUOUS không bao giờ auto-select (unitCode null + full
  candidates); UNMAPPED candidates rỗng; isResolved matrix đúng.
* **AC-3**: non-VN → `UnsupportedDestinationException`, resolver không được gọi, không vào cache;
  countryId null → không bypass (đi tiếp §4.3).
* **AC-4**: cache: same (scheme,unit,target) → resolver gọi đúng 1 lần/request-scope; khác unit
  hoặc khác targetScheme → entry riêng; AMBIGUOUS/UNMAPPED/missing-identity lặp lại không re-call
  resolver; key không chứa street/candidates và KHÔNG có bất kỳ recipient identity nào
  (contract không còn field PII — §6).
* **AC-5**: missing sourceScheme/sourceUnitCode → UNMAPPED, không gọi resolver; unknown scheme →
  `LocalizedException` propagate; impossible canonical response → `LogicException`.
* **AC-6**: DI preference đúng §5; `setup:di:compile` pass; phpunit Secomm pass (unit tests §9 +
  1 test wire manager với `VnAdminAddressResolver` THẬT + mock unit provider/candidate finder —
  directive §21); validator `--check-specs --check-records --check-identity` pass.
* **AC-7**: README + CHANGELOG ShippingCore cập nhật; working memory (CURRENT_STATE/NEXT_TASK) sync;
  FEAT-YA2C0W `ticket_ref` += TASK-5XDG1P.

## 9. Test plan (unit, AAA)

Manager với mock resolver: EXACT same-scheme · MAPPED 1 candidate · AMBIGUOUS (unitCode null +
candidates full + KHÔNG phải candidates[0]) · UNMAPPED · cache hit (resolver 1 lần, cùng instance
trả lại) · cache separation (đổi unit / đổi targetScheme → gọi thêm) · cache unresolved (AMBIGUOUS
và UNMAPPED lặp không re-call) · non-VN (US) → exception + resolver 0 lần · missing identity →
UNMAPPED + resolver 0 lần · unknown scheme → LocalizedException propagate · capability chỉ đọc
getRequiredScheme. Integration-level: manager + `VnAdminAddressResolver` thật (mock
`VnAddressUnitProviderInterface` + `MappingCandidateFinder`) → EXACT/MAPPED/AMBIGUOUS/UNMAPPED
end-to-end qua resolver contract thật.
