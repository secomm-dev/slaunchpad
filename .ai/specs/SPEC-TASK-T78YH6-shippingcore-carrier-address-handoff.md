# Task Spec: ShippingCore — Carrier-facing address handoff (Phase E-C0)

Specification ID: SPEC-TASK-T78YH6

> Filename: `SPEC-TASK-T78YH6-shippingcore-carrier-address-handoff.md` — standalone work-item
> spec (slice Phase E-C0 của FEAT-YA2C0W; thực thi SPIKE-YH439T §4 Option B-minimal đã approved).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-T78YH6 |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-C0) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved E-C0 directive; architecture pre-approved qua SPIKE-YH439T |
| Status | **VALID** — handoff shape theo directive + audit code thật 2026-09-08; TL review spec text chạy cùng code pre-review |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D2 ownership · D9 ambiguity · D5 bridge reuse) |
| Related Ticket(s) | TASK-T78YH6 · TASK-5XDG1P (E-B manager) · SPIKE-YH439T (handoff design) · TASK-XXBN5X (E-SL0) |
| Workflow Mode | A (shipping shared-contract = generic risk category) |

## 1. Objective

Điền đúng 1 gap: **carrier-facing handoff** — carrier không còn phải tự dựng canonical context,
tự catch `UnsupportedDestinationException`, tự diễn giải 4-state, hay tự quyết "canonical result
có dùng được không":

```text
Magento destination (Quote\Address)
  → DestinationContextBuilder (runtime → canonical source identity + target scheme)
  → ShippingAddressResolutionManager (E-B, cached)
  → CarrierAddressHandoffService (translate + catch + fallback-eligibility)
  → CarrierAddressHandoff → carrier module
```

KHÔNG: rate orchestration, fallback pricing, service-level aggregation, external resolver,
provider mapping, checkout rate behavior.

## 2. Verified implementation basis (audit 2026-09-08)

* **§5 bridge verdict: `VnOperationalAddressResolverInterface::resolveFromRuntime(regionId, cityId)`
  là bridge ĐÚNG — REUSE, không tạo path thứ hai**: pure code-lookup runtime ids → canonical
  identity của ACTIVE scheme (`VnOperationalIdentityInterface::getSchemeCode()/getUnitCode()`),
  business-miss trả unresolved + reason (KHÔNG exception) — chính xác hợp đồng builder cần
  (`Api/Data/VnOperationalAddressResolverInterface.php:37`, `VnOperationalResolutionInterface.php`).
* E-B manager: `resolve(context, capability)` cached; non-VN → `UnsupportedDestinationException`;
  missing identity → UNMAPPED; AMBIGUOUS không auto-select (`ShippingAddressResolutionManager.php`).
* Context DTO bắt buộc `targetScheme` non-empty ở constructor → target scheme áp 1 điểm duy nhất:
  **builder** lấy từ `capability.getRequiredScheme()` (§7 — chọn "destination + capability →
  builder/context" vì constructor hiện tại không cho phép context thiếu target scheme).
* Input Magento: carriers hiện đọc destination từ `RateRequest->getShippingAddress()` =
  `Quote\Address` (GHN `ShippingDetailsDataBuilder.php:64-67` — kể cả `getData('city_id')`);
  builder nhận `Quote\Address` typed (không array) — order-address cho shipment path là follow-up
  khi flow đó cần (E-C0 scope rate path).
* Test pattern: Magento models dùng trực tiếp `new RateRequest([...])` / mock
  (`ShippingContextFactoryTest.php:19-21`) — `Quote\Address` constructible + setData không cần DB.

## 3. Scope — `Api\Address` + `Model\Address`

### 3.1 DestinationContextBuilderInterface (+ DestinationContextBuilder)

```php
public function build(
    \Magento\Quote\Model\Quote\Address $destination,
    CarrierAddressCapabilityInterface $capability
): ShippingAddressResolutionContextInterface;
```

* Map: `countryId = destination->getCountryId()` · `sourceScheme/sourceUnitCode` =
  `resolveFromRuntime((int)regionId, (int)getData('city_id'))` → resolved ? identity :
  **null/null** (unresolved bridge = missing canonical identity → manager trả UNMAPPED — không
  exception, không fallback riêng) · `streetText` = join các dòng street non-empty (", ") ·
  `targetScheme = capability.getRequiredScheme()` · `candidateCodes = []` (§17 — KHÔNG trust
  caller candidates).
* KHÔNG: resolve graph (manager làm), candidate selection, provider mapping, carrier IDs,
  duplicate VN_ADMIN mapping (§4).

### 3.2 CarrierAddressHandoffInterface (+ CarrierAddressHandoff VO)

```php
interface CarrierAddressHandoffInterface
{
    public const REASON_UNSUPPORTED_DESTINATION = 'UNSUPPORTED_DESTINATION';
    public const REASON_CANONICAL_UNRESOLVED   = 'CANONICAL_UNRESOLVED';

    public function isApplicable(): bool;
    public function getResolvedAddress(): ?ResolvedShippingAddressInterface;
    public function isTextualFallbackEligible(): bool;
    public function getFailureReason(): ?string;
}
```

* KHÔNG `isResolved()` (derive từ `getResolvedAddress() !== null` — directive §8), KHÔNG
  `getUnresolved()` (chưa có carrier behavior cần nó), KHÔNG PII (§18), KHÔNG metadata
  resolution-method (§11).

### 3.3 CarrierAddressHandoffServiceInterface (+ CarrierAddressHandoffService)

```php
public function handoff(
    \Magento\Quote\Model\Quote\Address $destination,
    CarrierAddressCapabilityInterface $capability
): CarrierAddressHandoffInterface;
```

Service flow (duy nhất 1 path — §16): `context = builder.build(...)` → `manager.resolve(context,
capability)` → catch `UnsupportedDestinationException` → translate §4 matrix. KHÔNG gọi trực tiếp
`VnAdminAddressResolver`/`MappingCandidateFinder`; KHÔNG invoke external pool (§14); KHÔNG log
expected states (§22 — state đã tự diễn giải); KHÔNG đụng service-level/fallback-price (§20).

### 3.4 Handoff matrix (directive §10–§12)

| Manager outcome | applicable | resolvedAddress | fallbackEligible | failureReason |
|---|---|---|---|---|
| `UnsupportedDestinationException` (non-VN) | **false** | null | false | `UNSUPPORTED_DESTINATION` |
| EXACT / MAPPED | true | result | **false** | null |
| AMBIGUOUS / UNMAPPED + capability không hỗ trợ text | true | **null** (không auto-select) | false | `CANONICAL_UNRESOLVED` |
| AMBIGUOUS / UNMAPPED + capability hỗ trợ text | true | **null** | **true** (chỉ ALLOW — carrier tự thực thi payload riêng) | `CANONICAL_UNRESOLVED` |

VO invariants (constructor): `!applicable` → resolvedAddress null + reason bắt buộc;
applicable + resolved → fallbackEligible false + reason null; applicable + unresolved → reason
= `CANONICAL_UNRESOLVED` bắt buộc.

## 4. Stage 1 vs Stage 2 (engineering rule — ghi README)

```text
Stage 1 (ShippingCore, task này): runtime/current address → target canonical identity (handoff)
Stage 2 (carrier):              target canonical identity → provider IDs/text
```

Stage-2 failure (provider mapping missing/API fail) **KHÔNG BAO GIỜ** được convert ngược thành
`CANONICAL_UNRESOLVED` — đó là failure carrier-owned xảy ra SAU handoff.

## 5. DI

2 preference trong `ShippingCore/etc/di.xml`: `CarrierAddressHandoffServiceInterface` → service,
`DestinationContextBuilderInterface` → builder. Pool external resolver + registry service-level
giữ nguyên (không đụng).

## 6. Out of scope

Rate outcome / E-C1 semantics · service-level aggregation + fallback pricing (E-SL1) · external
resolver invocation · provider mapping · checkout methods · origin canonicalization ·
OrderOperations · carrier modules (Ghn/GiaoHangNhanh/GhnAddressMapper/Ghtk/Ahamove) ·
Mageplaza/Launchpad · logging policy (boundary carrier/rate ops quyết sau) · order-address
shipment-path builder overload (follow-up khi shipment flow cần).

## 7. Acceptance Criteria

* **AC-1**: Builder map đúng Quote\Address → context (country/identity/street/targetScheme),
  bridge unresolved → null identity; candidateCodes luôn `[]`; KHÔNG resolve graph trong builder.
* **AC-2**: Handoff service delegate 100% qua `ShippingAddressResolutionManagerInterface`
  (mock assert), 0 gọi trực tiếp VietNamAddress resolver; catch `UnsupportedDestinationException`
  → applicable=false + `UNSUPPORTED_DESTINATION` — carrier không cần catch.
* **AC-3**: Matrix §3.4 đúng 4 hàng; AMBIGUOUS/UNMAPPED không auto-select (resolvedAddress null);
  fallback chỉ ALLOW (`supportsTextualFallback` đọc duy nhất ở nhánh unresolved).
* **AC-4**: VO invariants §3.4; 0 PII; 0 `isResolved()`/`getUnresolved()` trên contract.
* **AC-5**: `VnOperationalAddressResolverInterface` là bridge duy nhất runtime→canonical trong
  ShippingCore (không path thứ hai); external pool không được invoke.
* **AC-6**: DI §5; compile + validator 0 new finding; phpunit Secomm pass; 0 carrier/Mageplaza/
  Launchpad code đổi.
* **AC-7**: README (engineering rule §24 + Stage 1/2) + CHANGELOG + working memory sync.

## 8. Test plan (unit, AAA)

`DestinationContextBuilderTest` (real `Quote\Address` + mock bridge + capability stub): map đầy
đủ khi bridge resolved · bridge unresolved → null identity · street join · targetScheme từ
capability · candidateCodes rỗng · city_id đọc qua getData · region/city 0 → bridge gọi với 0.
`CarrierAddressHandoffTest`: VO invariants 4 nhánh. `CarrierAddressHandoffServiceTest` (mock
builder + mock MANAGER — delegate assert; capability stub ném nếu fallback bị đọc sai nhánh):
6 hàng matrix §3.4 + delegate-once + non-VN translate.
