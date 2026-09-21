# SPIKE-WHHEZV — Mageplaza_TableRateShipping làm fallback engine theo service level cho Secomm_ShippingCore (audit + kiến nghị, pre-implementation)

> **Analysis/design only — 0 production code.** Record: `.ai/records/spikes/SPIKE-WHHEZV.md`.
> Anchor: SPIKE-YH439T (carrier runtime handoff + failure semantics) + TASK-5XDG1P (E-B manager)
> + TASK-AQT7V3 (E-A contracts) + DEC-FEATYA2C0W-004. Ngày: 2026-09-08. Prose VI, identifiers EN.
> Addendum §15 cập nhật forward architecture của SPIKE-YH439T (không rewrite nội dung cũ).
> Mọi contract/config shape là ĐỀ XUẤT chờ TL/SA.

---

## 1. Installed Mageplaza architecture (audit source `app/code/Mageplaza/TableRateShipping`, v4.0.8)

| Thành phần | Thực tế (file:line) |
|---|---|
| Carrier | `Model\Carrier\TableRate` — `AbstractCarrier`, `_code = 'mptablerate'`, `_isFixed = true` (`TableRate.php:47-57`) |
| Default config | `carriers/mptablerate/active = 0`, `showmethod = 0`, `sallowspecific = 0`, `volume_weight = weight`, `shipping_factor = 5000` (`etc/config.xml` — khớp BR-005: dimensional factor 5000, default inactive) |
| Method entity | bảng `mageplaza_tablerate_method`: `method_id` (int identity PK), `name`, `status` (ENABLE/DISABLE), `calculate_rule` (SUM/MIN/MAX), `store_id` (CSV), `customer_group` (CSV), `labels` (JSON per-store), `comments` (JSON per-store), `image` (`db_schema.xml:24-41`) |
| Rate entity | bảng `mageplaza_tablerate_rate`: `rate_id`, `method_id` (FK), 4 thành phần giá (`order_fixed_rate`, `product_percentage_rate` (% subtotal), `product_fixed_rate` (× qty), `weight_fixed_rate` (× weight)), `delivery` (số ngày estimate), destination (`country_id`, `region` = destRegionId, postcode from/to/pattern + alpha/num split), ranges (`weight/subtotal/qty_from/to`), `shipping_group` CSV (`db_schema.xml:44-82`) |
| Price formula | `Rate::calculatePrice(cartData)` = `orderFixed + productPercentage/100×subtotal + productFixed×qty + weightFixed×weight` (`Rate.php:64-72`); aggregate per method theo `calculate_rule` SUM/MIN/MAX (`Method.php:188-219`) |
| Cart dimensions | `TableRate::getCartData($request, $rate)` (PUBLIC): weight (4 chế độ: `weight` attribute / volumetric `LxWxH/factor` / user attributes / plain — `TableRate.php:321-375`), subtotal, qty; bỏ virtual theo config; free-shipping item + lọc theo shipping group khi per-rate |
| collectRates | `TableRate.php:179-234`: gate `getConfigFlag('active')` → loop TẤT CẢ methods → `isActive(storeId)` per method → rate collection `filterByRequest` → `validateShippingGroup` → `calculatePrice` → Method result: `setMethod($item->getId())` — **method code = method_id (string)**, title = store label fallback `name`, placeholder `{{delivery_days}}` từ `rate.delivery` (`:212-216`) |
| Method active | `Method::isActive($storeId)` (`Method.php:124-136`): `status === ENABLE` **AND** store scope (`store_id` CSV chứa storeId hoặc 0) **AND** customer group (CSV chứa `customerSession->getCustomerGroupId()`; admin → backend session quote) |
| Rate matching | `Rate\Collection::filterByRequest()` (`Rate/Collection.php:101-139`): country + destRegionId + postcode (split alpha/num + `LIKE` pattern) + shipping group `FIND_IN_SET` + weight/subtotal/qty ranges — **mọi điều kiện đều OR-null** (rule trống = match-all cho chiều đó) |
| No-match | methods không có rate khớp bị skip; `rateCount === 0` → `showmethod=1` ? append Error object : result rỗng (`TableRate.php:225-231`) — **không 0-fee, không default, không first-match** |
| Delivery estimate | CÓ: cột `delivery` per rate + `{{delivery_days}}` trong title (`TableRate.php:404-430`) |
| CSV import | `Model/Import.php` + admin controllers (`Method/ImportProcess`, `Import/Process`) — rate rows import theo method |
| Plugins/DI | Root di.xml: chỉ UI grid collections; adminhtml/di.xml: 0 plugin; events.xml: **0 observer**. 2 plugin presentation-only: `Plugin\Model\Quote\Address::afterGetGroupedAllShippingRates` + `Plugin\Model\Cart\ShippingMethodConverter::afterModelToDataObject` — enrich image/comment qua extension attributes, skip nếu carrier ≠ `mptablerate` |
| Dependencies | module.xml sequence chỉ `Mageplaza_Core` |
| ⚠ Caveats phát hiện | (1) `getAllowedMethods()` trả TẤT CẢ methods, không filter active (`TableRate.php:120-130`). (2) `Method::isActive()` phụ thuộc **customer session** — guest OK (group 0) nhưng CLI/cron context sẽ không có session quote (chỉ ảnh hưởng admin backend branch; storefront rate path an toàn). (3) `customer_group` rỗng → `explode(',', '')` = `['']` → `array_intersect` rỗng → method **không bao giờ active** — cấu hình method bắt buộc set customer group cụ thể. (4) Destination granularity = **country/region/postcode, KHÔNG có city/ward** — fallback VN chỉ granularity tỉnh (chấp nhận được cho emergency pricing; ghi nhận limitation) |

---

## 2. Direct checkout exposure mechanism

Mageplaza methods thành rates qua đúng 1 con đường: Magento shipping rating gọi `collectRates()` —
và nó chỉ chạy khi `carriers/mptablerate/active = 1` (`\Magento\Shipping\Model\Shipping::collectCarrierRates`
check `AbstractCarrier::isActive()` trước khi gọi). Bên trong, mỗi method `status=ENABLE` khớp
store/customer-group được append vào `Rate\Result` với carrier `mptablerate` + method code =
`method_id`. Admin order-create path dùng cùng carrier qua `Block\Adminhtml\Order\Create\...`.
GraphQL/REST chỉ hiển thị những gì quote address đã collect (plugins chỉ enrich presentation).

⇒ **Exposure = hàm của (carrier `active` config) × (method `status`/scope)**. Không cơ chế nào khác.

---

## 3. Internal calculation capability (Case A / B / C)

### Case A — method tính được khi checkout exposure bị suppress? **YES**

`carriers/mptablerate/active` chỉ là gate ĐẦU của `collectRates()` (`TableRate.php:181-183`).
Toàn bộ calculation internals KHÔNG đọc flag này: method loop + `filterByRequest` +
`calculatePrice` hoạt động độc lập. Method `status` (DB) cũng độc lập với carrier `active` (config).
Vậy: `active = 0` (không exposure) + method `status = ENABLE` (calculable) là trạng thái song song
hợp lệ — Case A được chứng minh bằng cấu trúc code, không cần state mutation.

### Case B — bridge gọi calculator nội bộ, không qua `collectRates()`? **YES**

Pipeline tính 1 method hoàn toàn compose được từ public members:

```text
ResourceModel\Method\Collection (lọc method_id cần)
  → Method::isActive($storeId)                      // Method.php:124 (public)
  → Rate\Collection::addFieldToFilter('method_id', …)->filterByRequest($request, $cartData)  // Collection.php:101 (public)
      với $cartData = TableRate::getCartData($request, $rate)   // TableRate.php:242 (public)
  → TableRate::validateShippingGroup($rates, $request)          // TableRate.php:138 (public)
  → Method::calculatePrice($rates, $request, $carrier)          // Method.php:188 (public; cần carrier instance cho getCartData)
  → Rate::calculatePrice($cartData)                              // Rate.php:64
```

Chỉ `getApplicableRates()` là private (`TableRate.php:303`) nhưng nó chỉ là 5 dòng
`isActive + filterByRequest` — bridge không cần nó. Không plugin/observer nào can thiệp đường này
(2 plugin hiện có chỉ enrich presentation sau khi rate đã vào quote). Caveat duy nhất: cần một
instance `TableRate` (DI-injectable, carrier virtual có thể tạo qua factory) cho
`getCartData/calculatePrice`.

### Case C — method inactive tắt cả calculation? **YES (ở mức logic, không phá data)**

Carrier `active = 0` → `collectRates()` return false ngay (`TableRate.php:181-183`) — calculation
nguồn tắt ở tầng collectRates exposure. Method `status = DISABLE` → method bị skip trong loop
và bridge phải coi như không có profile. Dữ liệu DB không bị xóa — bật lại là chạy lại.
⇒ KHÔNG dùng state-mutation runtime (Option C bị loại): chỉ đổi config/DB qua admin.

---

## 4. Recommended integration option — **Option A (internal calculator)**

| Tiêu chí | A: internal calculator | B: collectRates + filter + plugin suppress | C: state mutation |
|---|---|---|---|
| Tính đúng 1 service level | ✓ — query đúng method_id | ✗ — collectRates tính TẤT CẢ methods rồi mới chọn (waste + đọc kết quả gián tiếp) | ✓ |
| Checkout suppression tách rời | ✓ — suppression chỉ là config `active=0` (§5) | cần plugin aroundCollectRates + parse Result | ✗ |
| Độ bền upgrade Mageplaza | ✓ — dùng đúng public pipeline của module, không override | ✓ | ✗ |
| Nguy cơ side-effect | Thấp — không đi qua `Rate\Result`/error path | Phải mô phỏng request hoàn chỉnh + bỏ kết quả thừa | Cao |

Bridge compose pipeline §3-Case-B; `collectRates()` của Mageplaza không được gọi trong FALLBACK_ONLY.

---

## 5. FALLBACK_ONLY suppression strategy

**Chính: cấu hình thuần — `carriers/mptablerate/active = 0`** (Case A/C đã chứng minh: Magento
không gọi `collectRates` ⇒ 0 exposure; internal path không đọc flag ⇒ vẫn tính được). Zero code,
zero plugin, đảo được qua admin/CI config, đồng thời là default của module (`etc/config.xml`
`<active>0</active>`).

**Phụ (guardrail, đề xuất):** `Launchpad_MageplazaTableRate` thêm 1 plugin mỏng
`aroundCollectRates` trên `Mageplaza\TableRateShipping\Model\Carrier\TableRate`: khi bridge mode =
FALLBACK_ONLY mà ai đó bật `active=1` → log warning + `return false` (không gọi `$proceed()`).
Mục đích: chống admin bật nhầm làm methods lộ ra checkout; KHÔNG phải cơ chế tính. Trong
STANDALONE: plugin không tồn tại về mặt hành vi (`$proceed()` luôn). KHÔNG sửa vendor/module source.

---

## 6. STANDALONE behavior

Mode thuộc ownership `Launchpad_MageplazaTableRate` (config bridge), KHÔNG phải ShippingCore:

```text
mode = STANDALONE   → KHÔNG cài/enable realtime Secomm carriers
                    → carriers/mptablerate/active = 1 (Magento native collectRates chạy như designed)
                    → methods hiện checkout trực tiếp (labels/comments/image của Mageplaza)
                    → guard plugin vô hiệu ($proceed())
mode = FALLBACK_ONLY → active = 0 + guard plugin bật; chỉ bridge gọi internal calculator
mode = DISABLED      → module config inactive hoàn toàn (mặc định hiện tại của project — BR-005)
```

ShippingCore orchestration KHÔNG bao quanh Mageplaza trong STANDALONE —Mageplaza chạy thuần
Magento native; bridge chỉ sở hữu mapping/mode config. Đây là option "Mageplaza-only shipping"
cho project lean.

---

## 7. Service-level model (EXPRESS / SAME_DAY / STANDARD)

* **Ownership identities: `Secomm_ShippingCore`** — đề xuất `Api\ShippingServiceLevel` final class
  constants: `EXPRESS`, `SAME_DAY`, `STANDARD` (string codes, machine identity, không i18n,
  không đến từ label Mageplaza). Mageplaza chỉ sở hữu display labels per-store ("Giao nhanh 2
  giờ"…) — labels KHÔNG phải business identity.
* **Carrier khai báo service level, KHÔNG method**: đề xuất interface mới nhỏ (không đụng
  `CarrierAddressCapabilityInterface` — capability là address concern, service level là shipping
  concern):

```php
interface CarrierServiceLevelInterface   // ĐỀ XUẤT — ShippingCore\Api
{
    /** @return string[] ShippingServiceLevel::* mà carrier phục vụ (vd Ahamove: EXPRESS + SAME_DAY) */
    public function getServiceLevels(): array;
}
```

  GHN → `[STANDARD]`; GHTK → `[STANDARD]`; Ahamove → `[EXPRESS, SAME_DAY]`. KHÔNG có
  carrier→Mageplaza dependency; KHÔNG method ID nào trong carrier modules (directive §5).

---

## 8. Mageplaza mapping model

Stable identifier được Mageplaza support tốt nhất chính là **`mageplaza_tablerate_method.method_id`
(int identity PK)** — nó là giá trị Mageplaza dùng làm method code trong rate result
(`TableRate.php:211`) và chính plugin converter load theo nó (`ShippingMethodConverter`:
`load($result->getMethodCode())`). Auto-increment, không recycle, không đổi khi sửa tên/label.

```text
Bridge config (store-scoped), conceptual:
  launchpad_mptablerate/fallback/map_express    → method_id X
  launchpad_mptablerate/fallback/map_same_day   → method_id Y
  launchpad_mptablerate/fallback/map_standard   → method_id Z
```

CẤM dùng: method `name`/`labels` (display), rate row ID (quá chi tiết), position. Admin UI mapping
form list methods theo `method_id + name + status` để operator chọn.

---

## 9. Fallback provider contract proposal (provider-neutral, ShippingCore)

Đề xuất tối thiểu — KHÔNG freeze trước TL review; khớp cấu trúc hiện có (scalar DTO + optional
provider theo D7 pattern):

```php
namespace Secomm\ShippingCore\Api\Fallback;

interface FallbackRateProviderInterface        // ShippingCore không biết Mageplaza
{
    /**
     * @return FallbackRateInterface|null  null = không có fallback price cho level này
     */
    public function getRate(
        string $serviceLevel,                  // ShippingServiceLevel::*
        ShippingContextInterface $shippingContext,
        RateRequest $request                   // Magento rate-flow primitive (chuẩn boundary như collectRates)
    ): ?FallbackRateInterface;
}

interface FallbackRateInterface                 // VO nhỏ — chỉ những gì checkout cần
{
    public function getAmount(): float;
    public function getLabel(): string;         // service-level display label (vd "Standard Delivery")
    public function getDeliveryEstimate(): ?string;
    /** @return array metadata backend: service_level, rate_source=FALLBACK, provider, method_id… */
    public function getMetadata(): array;
}
```

Provider pool: **optional + tối đa 1 provider thực tế** (Mageplaza bridge) — DI array
`fallbackRateProviders` (D7 pattern giống `ExternalAddressResolverPool`); zero provider = hợp lệ
(fallback disabled de facto). KHÔNG thiết kế multi-provider competition.

---

## 10. Failure/fallback matrix — per service level (tích hợp SPIKE-YH439T §10)

Fallback trigger classification mở rộng từ failure model YH439T:

| Failure (từ realtime providers của level) | Fallback? | Ghi chú |
|---|---|---|
| Timeout / connection / HTTP 5xx / outage / malformed-tTechnical response | **CÓ** (nếu `fallback_enabled` của level = true) | temporary technical — chữ ký YH439T `PROVIDER_API_UNAVAILABLE` |
| non-VN / not applicable | KHÔNG | service không áp dụng từ đầu |
| canonical AMBIGUOUS/UNMAPPED | KHÔNG | unresolved địa chỉ là business/data state, không phải technical outage |
| provider location mapping missing | KHÔNG | data ops phải sửa mapping |
| carrier "destination not serviceable" | KHÔNG | business rejection của provider |
| invalid weight/dimensions, cut-off, outside radius | KHÔNG | business validation |
| auth/config error | KHÔNG | ops error — fallback sẽ che lỗi cấu hình |

**Per-service-level độc lập** (directive §11 — confirmed, không xung đột gì với YH439T): mỗi level
aggregate outcome các provider của level đó; còn ≥1 realtime rate usable → KHÔNG fallback level đó;
tất cả fail technical + `fallback_enabled[level]` → gọi provider pool. Express fail không bị
Standard suppress và ngược lại. Config per level (§13): `fallback_enabled` EXPRESS/SAME_DAY/
STANDARD — merchant quyết SLA risk (vd EXPRESS=false, SAME_DAY=true, STANDARD=true).

---

## 11. No-match behavior (installed evidence)

`filterByRequest` không khớp rule nào → collection rỗng → `calculatePrice` không chạy → method
skip → nếu không method nào match: `$rateCount === 0` → `showmethod=1` ? **Error object** :
result rỗng (`TableRate.php:225-231`). **KHÔNG có** 0-fee, default fee, hay first-match-unrelated.
⇒ Bridge semantics đúng chuẩn: no match = `null` (no fallback rate) — khớp nguyên tắc "never 0
shipping fee" của directive §14 chỉ với 1 lưu ý: đảm bảo bridge config không trỏ method có rate
"catch-all" ngoài ý muốn (rule trống = match-all do OR-null pattern).

---

## 12. Recollection behavior

Khớp Magento rate lifecycle native: quote address rates được recollect trên mỗi
`collectRates()` (thay đổi address/cart/totals/OSC re-render); fallback rate chỉ được ghi vào
quote khi lần collect đó realtime thất bại; khi provider hồi phục, lần collect sau trả realtime
rate và fallback rate không còn (rates cũ bị thay thế toàn bộ). Sau order placement, shipping
amount là snapshot của order — không recollect. Compatible ✓. Lưu ý: Mageplaza OSC có thể cache
rate ở tầng checkout — verify lúc QC (không phải code-level concern của bridge).

---

## 13. Dependency graph

```text
Secomm_ShippingCore           → Magento_* + Secomm_VietNamAddress  (KHÔNG Mageplaza, KHÔNG Launchpad)
Launchpad_MageplazaTableRate  → Secomm_ShippingCore (fallback contract + service-level identity)
                              → Mageplaza_TableRateShipping (internal calculator + method entities)
                              → Mageplaza_Core (transitive)
Secomm_Ghn / Secomm_Ghtk / Secomm_Ahamove → Secomm_ShippingCore ONLY (KHÔNG bridge, KHÔNG Mageplaza)
Mageplaza_TableRateShipping   → Mageplaza_Core (không biết gì Secomm/Launchpad)
```

Không có reverse edge nào. Tên module: **`Launchpad_MageplazaTableRate`** — khớp convention
Launchpad_* = project-specific composition/third-party bridge (đúng boundary DEC-8-style
Launchpad vs Secomm core); tên nói rõ Launchpad-specific + Mageplaza bridge, không giả danh
module reusable.

---

## 14. Proposed implementation phases

| # | Phase | Nội dung | Phụ thuộc |
|---|---|---|---|
| 1 | **E-SL0 contracts** (ShippingCore, contracts-only) | `ShippingServiceLevel` constants + `CarrierServiceLevelInterface` + `FallbackRateProviderInterface` + `FallbackRate` VO + DI array pool (zero-provider valid); unit tests; 0 carrier đổi | TL/SA approve §7/§9 |
| 2 | **LT-BRIDGE-1** (Launchpad_MageplazaTableRate scaffold) | Module mới: mode config (FALLBACK_ONLY/STANDALONE/DISABLED) + service-level→method_id mapping config + internal calculator service (Option A pipeline) + guard plugin + unit tests; KHÔNG expose gì checkout khi FALLBACK_ONLY | 1 |
| 3 | **E-SL1 orchestration** (ShippingCore) | Service-level rate aggregation: thu realtime outcome per level (sau handoff E-C0), fallback trigger theo matrix §10 + `fallback_enabled` per level, inject `FallbackRateProviderInterface` pool; rate result identity service-level (§12 phong cách) | 1–2 + E-C0 handoff (SPIKE-YH439T phase 1); ý nghĩa thật chỉ có sau khi ≥1 realtime carrier consume handoff (E-C/E-D/E-E) |
| 4 | QC | FALLBACK_ONLY: suppress + fallback path; STANDALONE: native exposure; recollection lifecycle; OSC (Mageplaza) hiển thị đúng identity service-level | 3 |

---

## 15. Addendum — cập nhật forward architecture (SPIKE-YH439T)

**Không rewrite nội dung SPIKE-YH439T** — addendum này cập nhật hướng đi phía trước:

1. Lớp **Shipping Service Level** (EXPRESS/SAME_DAY/STANDARD) trở thành trục fallback của
   ShippingCore: failure model YH439T (§10 matrix) được aggregate **theo service level**, không
   theo carrier. Concept "single emergency table rate" (nếu từng ngầm định) bị **superseded**
   bởi per-service-level fallback với switch `fallback_enabled` riêng từng level.
2. `Launchpad_MageplazaTableRate` là **Launchpad bridge đầu tiên implement
   `FallbackRateProviderInterface`** — bổ sung cho handoff architecture YH439T (§4 Option
   B-minimal): handoff giải quyết "address có resolve được không", fallback layer giải quyết
   "giá tạm khi realtime outage" — hai trục độc lập, gặp nhau ở service-level orchestration
   (phase E-SL1).
3. Ranh giới duy trì: carrier KHÔNG biết fallback; Mageplaza KHÔNG biết realtime; ShippingCore
   không biết Mageplaza; mode/ownership thuộc bridge.
4. Phases YH439T (E-C0 → E-F) giữ nguyên; E-SL0/E-SL1 cài vào sau E-C0, trước/kèm E-F tùy
   business ưu tiên (fallback không phụ thuộc external resolver).

---

## Phụ lục — Mageplaza files inspected (2026-09-08)

`Model/Carrier/TableRate.php` · `Model/Method.php` · `Model/Rate.php` ·
`Model/ResourceModel/{Method,Rate}.php` + Collections (+Grid) · `Model/Import.php` (skim) ·
`Model/Source/{Status,CalculateRule,VolumeWeight}.php` · `Plugin/Model/Quote/Address.php` ·
`Plugin/Model/Cart/ShippingMethodConverter.php` · `Helper/Data.php` (getPostcodeData/getScopeId/
SHIP_TYPE_ATTR) · `etc/{config.xml, di.xml, adminhtml/di.xml, module.xml, db_schema.xml,
adminhtml/system.xml, events.xml (0 observer)}` · `composer.json` (v4.0.8) · admin Block/Controller
(skim: Order/Create shipping form dùng carrier thường).
