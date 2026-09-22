# Task Spec: ShippingCore — Shipping service-level + fallback contracts (Phase E-SL0)

Specification ID: SPEC-TASK-XXBN5X

> Filename: `SPEC-TASK-XXBN5X-shippingcore-service-level-fallback-contracts.md` — standalone
> work-item spec (slice Phase E-SL0 của FEAT-YA2C0W; contracts-only, thực thi SPIKE-WHHEZV §7/§9/§13/§17).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-XXBN5X |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-SL0) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved E-SL0 directive; architecture pre-approved qua SPIKE-WHHEZV + SPIKE-YH439T |
| Status | **VALID** — contract shapes theo directive + SPIKE-WHHEZV audit; TL review spec text chạy cùng code pre-review. **r1 (2026-09-08): TL/SA review REJECT hardcoded constants** — ShippingCore không được hardcode Launchpad taxonomy (`EXPRESS/SAME_DAY/STANDARD`); thay bằng dynamic service-level model (interface + VO + registry, §3.1-r1) |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D7 DI-pool pattern · D10 no-abstraction) |
| Related Ticket(s) | TASK-XXBN5X · SPIKE-WHHEZV (fallback design basis) · SPIKE-YH439T (handoff/failure model) · TASK-5XDG1P (E-B) |
| Workflow Mode | A (shipping shared-contract = generic risk category, AGENTS §9/§12) |

## 1. Objective

Đưa vào `Secomm_ShippingCore` **minimum reusable API** cho mô hình
`Carrier ≠ Service Level ≠ Rate Source`:

```text
ShippingServiceLevel            — machine identities EXPRESS/SAME_DAY/STANDARD
CarrierServiceLevelInterface    — carrier KHAI BÁO level (không biết fallback)
FallbackRateRequestInterface    — provider-neutral fallback request (KHÔNG RateRequest)
FallbackRateInterface           — provider-neutral result (amount/label/estimate — KHÔNG metadata dump)
FallbackRateProviderInterface   — provider trả ?FallbackRate, null = no rate
FallbackRateProviderPool        — zero/one provider qua DI (D7 pattern)
```

**Contracts ONLY** — KHÔNG orchestration/trigger policy, KHÔNG admin config, KHÔNG checkout
method, KHÔNG rate-outcome taxonomy, KHÔNG Mageplaza/Launchpad concept nào.

## 2. Verified implementation basis

* SPIKE-WHHEZV (audit Mageplaza v4.0.8 thật): filterByRequest match theo
  `country_id + region(destRegionId) + postcode(alpha/num) + weight/subtotal/qty ranges`;
  `Method::isActive(storeId)` cần storeId + customer group; no-match = không rate (never 0-fee);
  method code = `method_id`. Destination granularity region/postcode là ĐỦ cho fallback pricing
  (directive §8: fallback pricing không phải service-eligibility engine).
* Pool precedent: `ExternalAddressResolverPool` (D7, zero-provider valid, DI array).
* Status/constant precedent: `VnSchemes::assertKnown` (static validate, không registry/DB).
* Monetary convention project: rate `setPrice(float)` (GHN/GHTK/Ahamove) → float.

## 3. Scope — contracts

### 3.1-r1 Service level model — DYNAMIC (thay thế §3.1 constants sau khi TL/SA reject)

Nguyên tắc được duyệt lại: **ShippingCore sở hữu CONCEPT + contracts của service level;
project/composition (Launchpad_*) sở hữu định nghĩa taxonomy cụ thể** (`EXPRESS/SAME_DAY/
STANDARD` là Launchpad business defaults, không phải ShippingCore invariant — project khác có
thể dùng `INSTANT/NEXT_DAY/ECONOMY` hoặc chỉ `STANDARD` không cần đổi ShippingCore).

```php
interface ShippingServiceLevelInterface        // Api\ShippingServiceLevelInterface
{
    public function getCode(): string;         // machine identity — stable, KHÔNG phải label
    public function getLabel(): string;        // configurable presentation (non-empty)
    public function isEnabled(): bool;
    public function getSortOrder(): int;
}

final class ShippingServiceLevel               // Model\ServiceLevel\ShippingServiceLevel — VO self-guarding
{ /* constructor(code, label, enabled = true, sortOrder = 0); empty code/label → LogicException */ }

final class ShippingServiceLevelRegistry       // Model\ServiceLevel\ShippingServiceLevelRegistry
{
    public function __construct(array $serviceLevels = []);  // DI array (D7 pattern); zero = valid
    public function getAll(): array;           // registration order
    public function getEnabled(): array;       // isEnabled filter, sorted theo sortOrder asc (stable)
    public function has(string $code): bool;
    public function getByCode(string $code): ?ShippingServiceLevelInterface;  // null = unknown
    /** @throws LocalizedException unknown code — explicit configuration error (§9) */
    public function assertKnown(string $code): void;
    // duplicate code hoặc code rỗng trong DI → \LogicException (fail-fast misconfiguration)
}
```

* KHÔNG constants, KHÔNG compile-time list, KHÔNG `all()/exists()/assertKnown()` tĩnh dựa
  compile-time universe — validation chuyển sang registry (dynamic).
* KHÔNG `fallback_enabled` trong definition (§12: generic identity tách fallback policy — policy
  là config/orchestration phase sau; recommendation ghi ở report).
* KHÔNG Mageplaza/fallback mapping fields (§11 — `mageplaza_method_id`… thuộc Launchpad bridge).
* Storage: **Option A — DI registration** (định nghĩa là DI objects qua
  `<item xsi:type="object">` của registry, module project đăng ký; version-controlled, không DB).
  Option B (system config dynamic rows) là upgrade path sau — lúc đó mới cân nhắc seam provider
  đọc config; Option C (DB entity) rejected vì không có evidence nhu cầu CRUD rộng.

### 3.2 `Api\CarrierServiceLevelInterface`

```php
interface CarrierServiceLevelInterface
{
    /** @return string[] ShippingServiceLevel::* mà carrier phục vụ */
    public function getServiceLevels(): array;
}
```

* Carrier khai báo, ShippingCore chỉ đọc (như capability). KHÔNG sửa carrier modules trong E-SL0.
* Known limitation (document ở docblock): carrier-level declaration đủ cho Launchpad hiện tại;
  method/operation-specific chỉ thêm khi carrier thật chứng minh cần (directive §5).

### 3.3 `Api\Fallback\FallbackRateRequestInterface` (+ VO `Model\Fallback\FallbackRateRequest`)

Provider-neutral request — trường mapped 1-1 từ audit Mageplaza (§2), KHÔNG qua `RateRequest`:

```php
interface FallbackRateRequestInterface
{
    public function getCountryId(): ?string;      // dest country
    public function getRegionId(): ?int;          // dest region (granularity region/postcode — §8 đủ)
    public function getPostcode(): ?string;       // raw postcode (bridge tự split alpha/num)
    public function getWeight(): float;
    public function getSubtotal(): float;
    public function getQty(): float;
    public function getStoreId(): int;            // Method::isActive(storeId)
    public function getCustomerGroupId(): ?int;   // null = unknown (bridge resolve cụ thể)
}
```

* KHÔNG: carrier code, Mageplaza method_id, Magento models, PII (name/phone/email), canonical
  candidates, provider IDs (directive §7).
* KHÔNG shipping-group/product data trong E-SL0 — Mageplaza chỉ cần nếu fallback profile dùng
  shipping group; profiles fallback cấu hình KHÔNG dùng group là đủ cho use case Launchpad
  (audit §2: group-less rate = match-all chiều đó). Nếu sau này bridge chứng minh cần → extension
  follow-up có evidence, không speculative.
* VO invariant: weight/subtotal/qty **negative → `LogicException`** (invalid DTO state); 0 hợp lệ.

### 3.4 `Api\Fallback\FallbackRateInterface` (+ VO `Model\Fallback\FallbackRate`)

```php
interface FallbackRateInterface
{
    public function getAmount(): float;           // >= 0 (r2: zero = valid explicit rate; chỉ negative reject; no-match ⇒ null)
    public function getLabel(): string;           // service-level display label (config concern)
    public function getDeliveryEstimate(): ?string;
}
```

* KHÔNG carrierCode/providerCarrierCode (fallback = service-level price, directive §10); KHÔNG
  `getMetadata(): array` trong E-SL0 (directive §9 — tránh metadata dump; metadata backend là
  việc của orchestration phase sau).
* VO invariant (r2): `amount < 0 → `LogicException`` — zero là valid explicit rate; `null` từ
  provider vẫn là cách duy nhất diễn đạt "no fallback rate" (0 không phải no-match sentinel);
  label non-empty. Policy cấm zero-fallback (nếu cần) thuộc project/Launchpad fallback policy,
  KHÔNG phải core VO invariant. Float theo monetary convention project (§2).

### 3.5 `Api\Fallback\FallbackRateProviderInterface`

```php
interface FallbackRateProviderInterface
{
    /**
     * @return FallbackRateInterface|null null = KHÔNG có fallback rate (no profile/no match/
     *                  provider unavailable). KHÔNG BAO GIỜ trả zero-fee thay cho null —
     *                  0đ là explicit configured rate hợp lệ (r2).
     */
    public function getRate(
        string $serviceLevel,
        FallbackRateRequestInterface $request
    ): ?FallbackRateInterface;
}
```

* Provider CHỈ trả lời "có giá fallback configured/matched không" — KHÔNG quyết eligibility
  (directive §12; eligibility/policy là orchestration phase sau).

### 3.6 `Model\Fallback\FallbackRateProviderPool`

* Mirror chính xác `ExternalAddressResolverPool` (D7): final, constructor DI
  `array $fallbackRateProviders` (instanceof guard → `LogicException`), `getProviders()` giữ
  registration order, **zero provider = valid state**.
* KHÔNG competition/chaining — hiện tại cần đúng 1 provider (Launchpad_MageplazaTableRate sau này).

## 4. DI

`etc/di.xml`: thêm `<type …FallbackRateProviderPool><arguments><argument name="fallbackRateProviders" xsi:type="array"/></arguments></type>` (mirror pool E-A). KHÔNG preference nào mới (không
interface cần preference — provider được bridge đăng ký sau này).

## 5. Namespace layout

`Api\ShippingServiceLevel` + `Api\CarrierServiceLevelInterface` (flat — cùng cấp
`ShippingContextInterface`/`OriginProviderInterface`); `Api\Fallback\*` (sub-namespace mirror
precedent `Api\Address`, `Api\Tracking`); `Model\Fallback\*`.

## 6. Out of scope

Fallback trigger policy / per-level `fallback_enabled` config · realtime aggregation · rate
outcome taxonomy (SUCCESS/UNAVAILABLE/…) · checkout methods (`secomm_standard`…) · Mageplaza
bridge · carrier module changes · external resolver · admin config · DB · order metadata ·
metadata field trên result · shipping-group data trên request · carrier address handoff (E-C0
riêng theo SPIKE-YH439T).

## 7. Acceptance Criteria

* **AC-1 (r1)**: Dynamic service-level model — `ShippingServiceLevelInterface` + VO + registry;
  **0 constants/taxonomy hardcoded trong ShippingCore** (grep: không còn `Api\ShippingServiceLevel`
  constants class); zero-level valid; duplicate/empty code → `LogicException`; unknown code qua
  `assertKnown` → `LocalizedException`; `getEnabled` filter + sort theo sortOrder.
* **AC-2**: `CarrierServiceLevelInterface` đúng shape ≤1 member; 0 carrier module đổi.
* **AC-3**: `FallbackRateRequest` đủ 8 trường neutral (§3.3), không có field carrier/Mageplaza/PII;
  negative dims → `LogicException`; KHÔNG raw `RateRequest` qua provider API (grep).
* **AC-4 (r2)**: `FallbackRate` amount>=0 (zero valid, negative → `LogicException`) + label
  non-empty + estimate nullable
  (enforce §11); KHÔNG metadata/carrier fields.
* **AC-5**: Provider contract trả `?FallbackRate`; pool: zero valid + 1 retrievable + order preserved
  + invalid entry → `LogicException`.
* **AC-6**: Grep ShippingCore = 0 reference Mageplaza/TableRate/mptablerate/method_id; 0 reference
  Launchpad_*.
* **AC-7**: Unit tests pass (service levels + validation, request transport + invariants, rate
  invariants, provider stub match/no-match, pool zero/one); compile + validator 0 new finding;
  phpunit Secomm pass.
* **AC-8**: README + CHANGELOG có 2 engineering rules (directive §20); working memory sync.

## 8. Test plan (unit, AAA)

`ShippingServiceLevelTest` (Model/ServiceLevel — r1: VO transports 4 fields, empty code/label
reject) + `ShippingServiceLevelRegistryTest` (r1: zero valid · one/multiple discoverable ·
getByCode known/unknown · has · getEnabled filter + sortOrder sort · assertKnown throw ·
duplicate code throw · label độc lập code). `FallbackRateRequestTest`:
transport 8 trường · defaults nullable · negative dims reject. `FallbackRateTest`: happy path ·
zero/negative amount reject · empty label reject. `FallbackRateProviderPoolTest`: zero valid ·
one retrievable · order preserved · invalid entry reject (mirror ExternalAddressResolverPoolTest).
