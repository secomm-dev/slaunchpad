# Task Spec: Launchpad_MageplazaTableRate — ShippingCore fallback provider bridge (LT-BRIDGE-1)

Specification ID: SPEC-TASK-NQT782

> Filename: `SPEC-TASK-NQT782-launchpad-mptablerate-fallback-bridge.md`. Launchpad-specific
> composition quanh Mageplaza_TableRateShipping v4.0.8 — KHÔNG phải reusable Secomm shipping
> logic (thực thi SPIKE-WHHEZV §4/§5/§12/§13 đã approved; ShippingCore contracts FROZEN — gap
> nào phát hiện thì REPORT, không tự mở rộng core).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-NQT782 |
| Feature ID | FEAT-YA2C0W (parent — chưa có FEAT Launchpad-integration riêng; không tự tạo FEAT một-ticket; mint FEAT riêng khi cụm LT-* lớn lên) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved LT-BRIDGE-1 directive + SPIKE-WHHEZV audit |
| Status | **VALID** — bridge shape theo directive + audit Mageplaza source; TL review chạy cùng code pre-review |
| Date | 2026-09-08 |
| Related Ticket(s) | TASK-NQT782 · TASK-M3ME32 (E-SL2) · TASK-XXBN5X (E-SL0 fallback contracts) · SPIKE-WHHEZV |
| Workflow Mode | A (checkout/shipping integration = generic risk category) |

## 1. Objective

Module mới `Launchpad_MageplazaTableRate` (namespace `Launchpad\MageplazaTableRate` — khớp
convention hiện hữu `Launchpad_MageplazaDeliveryTime/ExtraFeeFix/Translate`):

1. implement `Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface`;
2. map dynamic service-level code → Mageplaza `method_id` (DI mapping — version-controlled);
3. tính fallback price qua pipeline nội bộ Mageplaza (KHÔNG qua `Carrier\TableRate::collectRates()`);
4. trả provider-neutral `FallbackRateInterface` (amount/label, estimate null);
5. FALLBACK_ONLY: Mageplaza ẩn khỏi checkout (config `active=0` + guard plugin);
6. STANDALONE: Mageplaza chạy native. Bridge KHÔNG quyết eligibility.

## 2. Verified Mageplaza pipeline (audit lại source thật trước code — SPIKE đã xác nhận, re-verify)

```text
Method::isActive($storeId)                       // status + store scope + customer-group (SESSION — xem §R2)
Rate\Collection::addFieldToFilter('method_id', …)->filterByRequest($rateRequest, $cartData)
  — $rateRequest cần: dest_country_id, dest_region_id, dest_postcode, all_items=[]
  — $cartData = ['weight','subtotal','qty']  ← TỪ FallbackRateRequest scalars (không phải items!)
Rate::calculatePrice($cartData)                  // order_fixed + %subtotal + ×qty + ×weight (formula Mageplaza)
Method::getCalculateRule() + Source\CalculateRule::{SUM,MIN,MAX}  // combine per-method
```

**Gap R1 (đã audit, không phải blocker)**: `Method::calculatePrice($rates, $request, $carrier)`
KHÔNG dùng được nguyên von vì nó gọi `$carrier->getCartData($request, $rate)` — tính weight/
subtotal/qty TỪ `$request->getAllItems()`, trong khi `FallbackRateRequestInterface` chỉ mang
scalars đã aggregate (ShippingCore FROZEN, hard stop — không thêm items). Bridge glue: dùng
`Rate::calculatePrice($cartData)` (formula Mageplaza — KHÔNG reimplement) + combine theo
`CalculateRule` của method (đọc constant từ Mageplaza `Source\CalculateRule`) — orchestration
của extension logic, không phải pricing logic. Gap này REPORT (report §R1).

**Group-less assumption giữ nguyên (§15)**: request `all_items=[]` → `filterByRequest` chỉ match
rate KHÔNG có shipping_group (match-all chiều đó) — đủ cho fallback profiles; KHÔNG mở core DTO.

## 3. Scope — module `Launchpad_MageplazaTableRate`

### 3.1 `Model\Config`

* `getMode($storeId): string` — ScopeConfig `launchpad_mptablerate/general/mode`, default
  `FALLBACK_ONLY` (`etc/config.xml`); chỉ 2 mode FALLBACK_ONLY/STANDALONE (§8 — không DISABLED).
* `getMethodMapping(): array<string,int>` — DI array `methodMapping` (service-level code →
  method_id int). **Khuyến nghị document (§29): mapping qua DI thay dynamic-row admin** — codes
  là composition-defined (registry), version-controlled cùng code định nghĩa chúng; admin
  dynamic rows defer đến khi codes ổn định + merchant tự quản.
* `getLabel($serviceLevelCode): string` — DI array `labels` (code → label), fallback = code
  (không dùng Mageplaza title làm customer-facing identity — §19; không hardcode label tiếng
  Việt trong ShippingCore; label bridge config là nơi duy nhất).

### 3.2 `Model\FallbackRateProvider` implements FallbackRateProviderInterface

```text
getRate(code, request):
  mode ≠ FALLBACK_ONLY → null                                  // STANDALONE: bridge không là pricing source (§10)
  methodId = mapping[code] ?? null → null (§5 — absence bình thường)
  method = methodFactory->create()->load(methodId); isEmpty
    → FallbackConfigurationException (§6 — fail loud)
  !method->isActive(request->getStoreId())
    → FallbackConfigurationException (§30 chosen semantic: configured-but-inactive là
      misconfiguration — bundle status+store+customer-group scope của Mageplaza; fail loud
      thay vì silently no-match. Documented; §R2 limitation.)
  rateRequest = new RateRequest (bridge-only) + dest_country_id/dest_region_id/dest_postcode/
    all_items=[]/store_id                                       // §13 boundary — không leak ngược
  cartData = weight/subtotal/qty từ request scalars
  rates = rateCollectionFactory->create()
    ->addFieldToFilter('method_id', methodId)->filterByRequest(rateRequest, cartData)->getItems()
  rates rỗng → null (§16 — no-match, không 0đ mặc định)
  price = combine(Rate::calculatePrice(cartData) per rate, method.getCalculateRule())   // Gap R1 glue
  → new FallbackRate(amount: price (kể cả 0 — §31), label: config label (§19), estimate: null (§20))
```

Exception riêng `Model\Exception\FallbackConfigurationException extends LocalizedException`
(convention `GhnLocationMappingException`). KHÔNG expose method_id/mptablerate/Mageplaza model
qua result (§18).

### 3.3 `Plugin\Carrier\TableRate` (defense-in-depth — §11)

`aroundCollectRates`: mode FALLBACK_ONLY → nếu `carriers/mptablerate/active=1` (misconfig) →
`logger->warning` + return false; active=0 (chính) → return false không log spam. STANDALONE →
`$proceed()`. Vendor không đụng. *(Cơ chế chính vẫn là `active=0` — Magento không gọi
collectRates; plugin chỉ chống bật nhầm.)*

### 3.4 DI + registration

* di.xml: provider vào pool ShippingCore (`FallbackRateProviderPool.fallbackRateProviders`
  item object) — sole provider Launchpad; plugin registration; mapping/labels arrays.
* module.xml sequence: `Mageplaza_TableRateShipping` + `Secomm_ShippingCore`; registration.php;
  `bin/magento module:enable`.
* system.xml: mode dropdown (admin-editable). Mapping/labels = DI (khuyến nghị đã ghi §3.1).

## 4. Out of scope

Eligibility/radius/cut-off/inventory (§25) · fallback trigger (E-SL2) · carrier adoption ·
selection · address canonicalization (§27 — nhận request sẵn) · ward/district granularity (§26) ·
new ShippingCore abstractions (hard stop — gap REPORT) · vendor modification (§34) · DB CRUD.

## 5. Acceptance Criteria

* **AC-1**: No mapping → null; mapping + no match → null; match → FallbackRate (label từ bridge
  config, estimate null); zero-price match → FallbackRate(0).
* **AC-2**: Method missing/inactive → `FallbackConfigurationException` (fail loud, documented
  semantic); STANDALONE → null.
* **AC-3**: Request translation: dest fields + cartData scalars đúng (mock capture); 0 Mageplaza
  object leak ngược ShippingCore.
* **AC-4**: Plugin: FALLBACK_ONLY + active=1 → warning + false; FALLBACK_ONLY + active=0 → false;
  STANDALONE → proceed. Không vendor edit (git status).
* **AC-5**: DI provider registration vào pool ShippingCore; module enable + compile pass.
* **AC-6**: phpunit pass; validator 0 new finding; README/CHANGELOG module mới (2 mode + bridge
  rule) + CURRENT_STATE sync; 0 carrier code đổi.

## 6. Test plan (unit, AAA — mock Mageplaza models, không DB)

`ConfigTest` (mode đọc + mapping/labels DI). `FallbackRateProviderTest`: no-mapping → null ·
no-match → null · match SUM/MIN/MAX · zero-price → FallbackRate(0) · method missing/inactive →
exception · STANDALONE → null · request translation captured · result không mang method identity.
`SuppressPluginTest`: 3 nhánh §AC-4.

## 7. Reported items (TL review)

* **§R1** `Method::calculatePrice` không dùng được với aggregate-input (items-based) → glue
  Rate::calculatePrice + CalculateRule (spec §2) — chấp nhận được hay cần thêm items vào
  FallbackRateRequest (thì mới mở hard stop)?
* **§R2** `Method::isActive` session-dependency (customer-group) — bridge gọi as-is; storefront
  path consistent (session group == request group); CLI/API chưa test — limitation documented.
* **§R3** Mapping qua DI (không admin dynamic rows) — khuyến nghị; đổi ý chỉ cần thêm block +
  backend model sau.
