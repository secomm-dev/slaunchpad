# Task Spec: ShippingCore — Lean Shipping Address Resolution Contracts (Phase E-A)

Specification ID: SPEC-TASK-AQT7V3

> Filename: `SPEC-TASK-AQT7V3-shippingcore-address-resolution-contracts.md` — standalone work-item spec
> per `rules/spec-first.md` §Spec Naming (contracts-only slice; spec riêng thay vì mở rộng spec FEAT
> vì Phase E-B orchestration sẽ có behavioral contract riêng).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-AQT7V3 |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-A) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ SPIKE-W273TB + Phase E-A directive (architecture pre-approved) |
| Status | **VALID** — scope + contract shapes theo approved Phase E-A directive; TL review spec text chạy cùng pre-review |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D1 dependency direction · D2 ownership · D9 ambiguity — accepted 2026-09-03) |
| Related Ticket(s) | TASK-AQT7V3 · SPIKE-W273TB (architecture basis) |
| Workflow Mode | A (shipping shared-contract = generic risk category, AGENTS §9/§12) |

## 1. Objective

Đưa vào `Secomm_ShippingCore` **minimum contracts** để Phase E-B (local canonical shipping address
orchestration) bắt đầu implement mà không phải sửa lại API surface:

```text
carrier required scheme          → CarrierAddressCapabilityInterface
local canonical resolution result → ResolvedShippingAddressInterface (+ VO)
AMBIGUOUS candidate list          → ResolvedShippingAddressInterface::getCandidateCodes()
UNMAPPED                          → status UNMAPPED, unitCode = null
future external resolver          → ExternalAddressResolverInterface + pool (zero-provider valid)
carrier textual fallback          → CarrierAddressCapabilityInterface::supportsTextualFallback()
```

**Contracts/foundation only** — KHÔNG orchestration logic, KHÔNG gọi resolver nào, KHÔNG config,
KHÔNG persistence (OD-2: request/ShippingContext scope khi E-B; OD-3: không API credentials).

## 2. Verified implementation basis

* SPIKE-W273TB (2026-09-04, `.ai/research/SPIKE-W273TB-shippingcore-address-orchestration.md`):
  ShippingCore 25 files, KHÔNG depend `Secomm_VietNamAddress` (module.xml chỉ Magento_*); carriers
  đã sequence ShippingCore; GHN fallback cứng 1456/21511 + `is_develop_mode` default 1 còn live
  (re-verified 2026-09-08 — xem TASK-AQT7V3 report §GHN follow-up).
* `Secomm_VietNamAddress` đã expose đủ semantics cần REUSE:
  * Status constants `VnAddressResolutionInterface::STATUS_EXACT|MAPPED|AMBIGUOUS|UNMAPPED`
    (DEC-FEATYA2C0W-003) — ShippingCore **không định nghĩa hằng status song song**.
  * `VnAddressUnitInterface::getRegionCode(): string` — region_code có sẵn trên unit → result DTO
    **không cần regionCode** (derive được qua `VnAddressUnitProviderInterface::getUnit()`).
  * Parent chain `parent_code` PRE ward → district → derive district ở carrier side qua
    VietNamAddress (DEC-004 D2) → DTO **không thêm district field**.

## 3. Scope — contracts (namespace `Secomm\ShippingCore\Api\Address`)

> Đặt dưới sub-namespace `Api\Address` — mirror precedent `Api\Tracking` của cùng module;
> tránh làm nhiễu `Api\` phẳng hiện có.

### 3.1 CarrierAddressCapabilityInterface

```php
interface CarrierAddressCapabilityInterface
{
    public function getRequiredScheme(): string;      // scheme code canonical, vd VN_ADMIN_PRE_2025
    public function supportsTextualFallback(): bool;  // E-A: CHỈ khai báo, không trigger gì
}
```

* Carrier khai báo, ShippingCore chỉ đọc. Value `getRequiredScheme()` lấy từ scheme catalog
  (`Secomm\VietNamAddress\Model\Scheme\VnSchemes`) — ShippingCore không tự định nghĩa scheme constant.
* KHÔNG thêm `getCarrierCode()` (ShippingContext đã mang carrierCode ở flow), `requiresProviderIds()`,
  `priority`, `supportsGeocode()`, `supportsLegacy()` — capability matrix bị cấm (D10).

### 3.2 ResolvedShippingAddressInterface (+ VO)

```php
interface ResolvedShippingAddressInterface
{
    /** EXACT | MAPPED | AMBIGUOUS | UNMAPPED — REUSE Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface::STATUS_* */
    public function getStatus(): string;
    public function getSchemeCode(): string;          // scheme của unitCode/candidates bên dưới
    public function getUnitCode(): ?string;           // chỉ EXACT/MAPPED; AMBIGUOUS/UNMAPPED = null
    /** @return string[] AMBIGUOUS: toàn bộ candidates, giữ thứ tự deterministic của resolver; khác: [] */
    public function getCandidateCodes(): array;
    public function isResolved(): bool;               // true ⇔ EXACT|MAPPED (centralized guard)
}
```

* **Status semantics REUSE từ VietNamAddress** (không phát minh status song song):
  `EXACT` = same-scheme/direct canonical identity · `MAPPED` = 1 deterministic target candidate ·
  `AMBIGUOUS` = nhiều target candidates (không bao giờ auto-pick, D9) · `UNMAPPED` = không có candidate.
* `isResolved()` có trong scope: nó là hard guard chống việc carrier nhầm candidate thành resolved
  (test §6 bắt điều này).
* VO concrete (`Model\Address\ResolvedShippingAddress`) enforce invariant ngay ở constructor:
  * status phải thuộc 4 STATUS_* của `VnAddressResolutionInterface` (giá trị lạ → `LogicException`);
  * `schemeCode` non-empty;
  * EXACT/MAPPED → `unitCode` non-empty bắt buộc, candidates = [];
  * AMBIGUOUS → `unitCode` = null (candidate KHÔNG được lộ qua unitCode), candidates non-empty
    (rỗng → `LogicException`), giữ nguyên giá trị + thứ tự;
  * UNMAPPED → `unitCode` = null, candidates = [].
* KHÔNG thêm `regionCode` (derive qua unit provider — §2), district, tên địa danh, provider ID,
  `getResolutionMethod()` (chưa có external resolution thực — thêm khi E-B chứng minh cần audit trail).

### 3.3 ShippingAddressResolutionContextInterface (+ VO)

Transport/domain-oriented, scalar-only (cùng triết lý `ShippingContextInterface` — free of mutable
Magento models; KHÔNG expose quote/address object):

```php
interface ShippingAddressResolutionContextInterface
{
    public function getCountryId(): ?string;        // guard VN vs non-VN ở E-B (non-VN → bỏ qua resolution)
    public function getSourceScheme(): ?string;     // canonical identity hiện có (bridge output); null khi chưa resolve được
    public function getSourceUnitCode(): ?string;
    public function getTargetScheme(): string;      = capability.getRequiredScheme() ghi lại cho resolver đọc
    public function getStreetText(): ?string;       // full shipping street/address cho disambiguation sau này
    /** @return string[] AMBIGUOUS candidates ở target scheme (rỗng khi chưa có) */
    public function getCandidateCodes(): array;
}
```

* Đây là dữ liệu tối thiểu mà external resolver sẽ cần (prompt §6): street/address text · current
  canonical unit identity · target scheme · candidate target codes. `countryId` bổ sung vì orchestration
  E-B phải quyết "có phải địa chỉ VN không" trước khi gọi bridge — 1 scalar, không over-model.
* KHÔNG geocoding, KHÔNG normalize/fingerprint address, KHÔNG cache key ở lần này.
* **r1 (2026-09-08, TL review TASK-5XDG1P): `getReceiverText()` ĐÃ XÓA** khỏi contract + DTO
  trước contract freeze — recipient identity (tên/SĐT/email) không thuộc address-resolution
  contract; external disambiguation chỉ dùng address-related text (street/candidates).

### 3.4 ExternalAddressResolverInterface

```php
interface ExternalAddressResolverInterface
{
    public function isAvailable(): bool;   // false = chưa config/ngừng khả dụng → pool bỏ qua, không throw
    public function resolve(ShippingAddressResolutionContextInterface $context): ?string;
    // @return string|null canonical target unit_code (trong targetScheme của context), hoặc null
    // khi không disambiguate được. KHÔNG BAO GIỜ trả VietMap ID / Google place ID / carrier ID.
}
```

* KHÔNG `getName()` ở lần này: pool E-A là DI array có thứ tự (D7 pattern), provider-selection
  config là out-of-scope (prompt §7) — khi E-B đưa selection config vào, `getName()` sẽ được thêm
  kèm pool design đó (quyết định này được report rõ ở final report).
* Implementation tự chịu timeout/log; KHÔNG throw ra orchestration (null = không disambiguate được).

### 3.5 ShippingAddressResolutionManagerInterface (contract stabilizer cho E-B)

```php
interface ShippingAddressResolutionManagerInterface
{
    public function resolve(
        ShippingAddressResolutionContextInterface $context,
        CarrierAddressCapabilityInterface $capability
    ): ResolvedShippingAddressInterface;
}
```

* **KHÔNG có implementation** trong E-A (không dead concrete class). Preference DI cũng KHÔNG khai
  báo (preference trỏ class chưa tồn tại sẽ gãy compile/PCI).

### 3.6 ExternalAddressResolverPool (extension point)

* `Model\Address\ExternalAddressResolverPool` — final, constructor DI `array $externalAddressResolvers`
  (mặc định rỗng qua `etc/di.xml` array argument — mirror D7 guards pattern của VietNamAddress).
* **Zero registered providers là trạng thái hợp lệ** (local-only shipping vẫn chạy).
* Modules tùy chọn (`Secomm_VietMap`…) đăng ký provider bằng `<item xsi:type="object">` vào argument —
  ShippingCore KHÔNG biết và KHÔNG depend bất kỳ provider concrete nào.
* KHÔNG gọi gì; KHÔNG provider selection config. Pool chỉ aggregate + expose `getResolvers()`.

## 4. Module dependency

`Secomm_ShippingCore/etc/module.xml` sequence += `Secomm_VietNamAddress` — thực thi chiều D1 đã
ratified (`VietNamAddress → ShippingCore`). KHÔNG reverse dependency; VietNamAddress không đổi.
GhnAddressMapper/GiaoHangNhanh/Ghtk/Ahamove KHÔNG đụng (prompt §12).

## 5. Out of scope

E-B orchestration · local resolver call · context cache · external resolver call · provider selection
config · VietMap/Google · GHN/GHTK/Ahamove migration · origin migration (OD-1) · quote/order/customer
persistence (OD-2) · geocoding · name-based fallback · first-candidate fallback · textual fallback
execution · DB table/schema mới · API credentials (OD-3) · i18n strings (không có UI string mới).

## 6. Acceptance Criteria

* **AC-1**: `Secomm_ShippingCore` sequence `Secomm_VietNamAddress`; không module nào thêm dependency
  ngược; 0 file carrier thay đổi.
* **AC-2**: 4 contracts + 1 manager interface nằm `Api/Address/`, mỗi contract ≤ 4 members, đúng
  shape §3; status REUSE `VnAddressResolutionInterface::STATUS_*` (grep: ShippingCore không chứa
  string literal status riêng).
* **AC-3**: VO `ResolvedShippingAddress` enforce toàn bộ invariant §3.2; AMBIGUOUS không bao giờ lộ
  unitCode; UNMAPPED luôn unitCode = null + candidates rỗng.
* **AC-4**: Pool rỗng hợp lệ; DI registration shape sẵn sàng cho provider optional; ShippingCore
  không reference Secomm_VietMap/Google/GHN/GHTK/Ahamove.
* **AC-5**: Unit tests pass: 4 status · candidates preserved · unitCode guard · isResolved matrix
  (EXACT/MAPPED → true; AMBIGUOUS/UNMAPPED → false) · empty pool valid.
* **AC-6**: `bin/project-ai-validate --check-specs --check-records --check-identity` pass;
  `setup:di:compile` pass; phpunit Secomm suite pass.
* **AC-7**: README + CHANGELOG ShippingCore cập nhật; working memory (CURRENT_STATE/NEXT_TASK) sync.

## 7. GHN P0 follow-up (report-only, ngoài scope code)

Fallback cứng GHN **vẫn live** (re-verified 2026-09-08): `AbstractDataBuilder.php:161-167`
(1456/21511 khi miss hoặc thiếu data, gated `is_develop_mode`), `etc/config.xml:26`
(`<is_develop_mode>1</is_develop_mode>` — mặc định BẬT), `ServicesDataBuilder.php:21` (1456),
`SynchronizeOrderDataBuilder.php:90` ('Phường 17' from-ward). Khuyến nghị: BUG task độc lập
"Remove/disable GHN hardcoded destination fallback, fail closed khi mapping unavailable" — đúng
hướng DEC-004 Consequences (migration work: default 0 + bỏ fallback). KHÔNG sửa trong E-A.
