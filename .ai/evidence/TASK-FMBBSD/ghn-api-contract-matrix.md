# GHN API Contract Matrix — TASK-FMBBSD PHASE A (2026-09-11)

> **UPDATE 2026-09-11 (sau sandbox run):** credentials đã được owner cấu hình trong ngày ⇒ đã chạy
> sandbox validation đầy đủ (xem `sandbox-validation.md` cùng thư mục). `is_new_to_address`,
> fee service_type_id-only, leadtime, idempotency client_order_code, cancel per-order — **VERIFIED
> live**. Available-Services: **DOCS vs SANDBOX CONFLICT** (docs `from_district`/`to_district` bị
> gateway từ chối ở mọi biến thể naming) ⇒ AS để EXTERNAL/OPERATIONAL, không đưa vào runtime trên
> contract chưa verify. Type-5 fee không items ⇒ bị từ chối (items de-facto bắt buộc cho heavy).

**Sources (priority per task §2):**
1. **Current official docs** — `developer.ghn.vn/en/docs/` (301 từ `api.ghn.vn/en/docs/`; staging mirror `developer.ghn.dev`). Fetched 2026-09-11: `token/get-token`, `master-data/get-service`, `order/calculate-fee`, `order/leadtime`, `order/create`, `order/info`, `order/update`, `order/update-cod`, `order/cancel`, `order/return`, `webhook/callback-order-status`, `master-data/order-status`.
2. **Verified sandbox behavior** — sandbox run 2026-09-11 (shop 200537, token owner-configured): create/fee/leadtime/info/cancel — xem `sandbox-validation.md`.
3. Architecture v3 (`.ai/project-context/architecture/address-shipping.md`).
4. Current `Secomm_Ghn` (GHN-A/B/B.2 code).
5. Legacy `Secomm_GiaoHangNhanh` — **reference only, không authoritative**.

**Verified by** cột: `DOCS` = current official docs (fetch 2026-09-11) · `SANDBOX` = live sandbox run 2026-09-11 · `UNVERIFIED_SANDBOX` = chưa thử live · `NOT_USED` = GHN contract có, Secomm chưa dùng.

---

## 1. Authentication / headers (tất cả endpoints)

| Header | Required | Ghi chú | Verified by |
|---|---|---|---|
| `Content-Type: application/json` | Yes | mọi endpoint | DOCS |
| `Token` | Yes | token issued per ENVIRONMENT (staging token ≠ production token; portal tự lấy, có IP allowlist) | DOCS |
| `ShopId` | Yes | header cho mọi order/master-data endpoint; **KHÔNG thay thế được body `shop_id`** của available-services | DOCS |

Base URL: production `https://online-gateway.ghn.vn/shiip/public-api/` · staging `https://dev-online-gateway.ghn.vn/shiip/public-api/`.
**Secomm current:** `GhnApiClient::prepareTransport()` set đủ 3 header + timeout + envelope parse + token-scrubbed log — **conform**. `Config::getBaseUrl()` theo environment config ✓.

⚠️ Available-services: body `shop_id` BẮT BUỘC và **khác** `ShopId` header (docs ghi rõ "distinct from the ShopId header") — legacy không gửi body shop_id → **LEGACY ONLY defect, không carry sang Secomm_Ghn**.

## 2. Available Services — POST `v2/shipping-order/available-services`

| Field | In | Type | Required | Notes | Secomm source | Transformation | Runtime owner | Verified by |
|---|---|---|---|---|---|---|---|---|
| `shop_id` | body | Int | **Yes** (error USER_ERR_COMMON nếu thiếu) | distinct từ header | — (chưa dùng) | config shop_id | GhnRateCalculator (khi dùng) | DOCS |
| `from_district` | body | Int | **Yes** | tên field **KHÔNG có `_id`** (legacy dùng `from_district_id` → discrepancy) | — | — | — | DOCS (naming) + UNVERIFIED_SANDBOX |
| `to_district` | body | Int | **Yes** | như trên | — | — | — | DOCS + UNVERIFIED_SANDBOX |
| `service_id` | response | Int | — | **"for reference only; not used when calling Calculate Fee or Create Order"** | — | KHÔNG persist, KHÔNG dùng làm identity | — | DOCS |
| `short_name` | response | String | — | vd "Hàng nhẹ"/"Hàng nặng" | — | diagnostic | — | DOCS |
| `service_type_id` | response | Int | — | **`2` = <20kg; `5` = ≥20kg hoặc multi-parcel** | — | weight-class probe | — | DOCS |

Quirk: **HTTP 200 + body rỗng = shop not found** (không phải lỗi JSON) → client đang ném `ProviderRemoteException` (R11) — khi dùng AS phải classify về UNAVAILABLE (config), không technical.
⚠️ **SANDBOX CONFLICT:** docs example (`from_district`/`to_district`) bị sandbox gateway từ chối ở MỌI biến thể naming (xem `sandbox-validation.md` §9) ⇒ AS KHÔNG đưa vào runtime tới khi GHN xác nhận binding.
**Vai trò trong RATE:** current fee contract **không** cần `service_id` (**SANDBOX-VERIFIED**) → Available Services **KHÔNG bắt buộc** cho fee — chỉ là availability probe tùy chọn. Quyết định slice: KHÔNG gọi AS trên checkout critical path (bớt 1 RTT; no-route sẽ lộ qua fee error `ROUTE_NOT_FOUND_SERVICE`).

## 3. Calculate Fee — POST `v2/shipping-order/fee`

| Field | Type | Required | Default/Limit | Notes | Secomm source | Transformation | Runtime owner | Verified by |
|---|---|---|---|---|---|---|---|---|
| `service_type_id` | Int | Yes (de-facto) | **enum {2, 5}** | 2 = <20kg; 5 = ≥20kg/multi-parcel. **KHÔNG có 1/3/4** | derived từ weight | `weight < 20000 → 2 else 5` | GhnRateCalculator | DOCS |
| `weight` | Int | **Yes** ("must be non-zero", USER_ERR_COMMON) | gram | | GhnParcel | quote weight → gram | GhnRateCalculator | DOCS |
| `to_district_id` | Int | Yes (de-facto) | | GHN legacy district ID | **Stage-2 mapping** (`GhnMappingResolver` ← canonical PRE_2025 unit_code) | canonical unit_code → provider ID | Ghn (adapter) | DOCS+ARCH |
| `to_ward_code` | String | Yes (de-facto) | | GHN WardCode | Stage-2 mapping | như trên | Ghn | DOCS+ARCH |
| `from_district_id` | Int | Optional | **default = shop registered address** | → GHN-owned origin; Secomm cấu hình riêng khi muốn override | config `origin_district_id` | int | Config/GhnRateCalculator | DOCS |
| `from_ward_code` | String | Optional | default shop address | không cấu hình trong P1 (shop default đủ) | — | — | — | DOCS |
| `length`/`width`/`height` | Int | Optional (RATE) | cm | omit khi không có dữ liệu — **cấm 1×1×1 hardcoded** | GhnParcel nullable | omit khi null | GhnRateCalculator | DOCS |
| `insurance_value` | Int | Optional | VND | declared value để bồi thường | upstream (P1: omit=0) | — | — | DOCS |
| `cod_value` | Int | Optional | VND | **tên field phí/COD ở FEE là `cod_value`** (≠ create `cod_amount`) | upstream collection amount | `collection_amount → cod_value` | GhnRateCalculator | DOCS |
| `coupon` | String | Optional | | | — (P1 omit) | — | — | DOCS |
| `items` | Object[] | Conditional | shape = Create | "used for heavy goods" (type 5) | — (P1 omit — xem Create §5) | — | — | DOCS |
| ~~`service_id`~~ | — | — | — | **KHÔNG xuất hiện** trong request/response của fee (current docs) | legacy dùng → **LEGACY ONLY** | — | — | DOCS |

Response: `total` (+ `service_fee`, `insurance_fee`, `cod_fee`, …) — VND. **KHÔNG có `service_id`/`service_type_id` trong response.**
Errors: `USER_ERR_COMMON` 400 (weight thiếu/zero), `CLIENT_NOT_OWNER_OF_SHOP` 400, `SERVER_ERROR_COMMON` 500.

## 4. Leadtime — POST `v2/shipping-order/leadtime`

| Field | Type | Required | Notes | Verified by |
|---|---|---|---|---|
| `to_district_id` / `to_ward_code` | Int/String | **Yes** | cùng nguồn Stage-2 với fee | DOCS |
| `from_district_id` / `from_ward_code` | Int/String | Optional | default shop address | DOCS |
| `service_type_id` | Int | Optional | `{2,5}` — **ảnh hưởng estimate** | DOCS |
| `weight` | Int | Optional | "affects the estimate for heavy goods" | DOCS |
| `length/width/height` | Int | Optional | cm | DOCS |
| ~~`service_id`~~ | — | — | **"Use service_type_id, not service_id" — service_id has NO effect** (docs warn explicitly; legacy+old page dùng service_id → LEGACY ONLY) | DOCS |

Response: `data.leadtime` (unix seconds) + `data.leadtime_order.from_estimate_date`/`to_estimate_date` (ISO 8601) — tên đúng là `leadtime_order`, **không phải** `order_leadtime`.
**Secomm:** KHÔNG dùng trong slice này — ShippingCore `CarrierRateInterface` chưa có field leadtime và chưa có consumer thật (§28 hard stop) → `DEFERRED`.

## 5. Create Order — POST `v2/shipping-order/create`

| Field | Type | Required | Default/Limit | Notes | Verified by |
|---|---|---|---|---|---|
| `client_order_code` | String | Optional (khuyến nghị) | ≤50 chars, **unique per shop**; resend với code đã dùng → trả lại `order_code` cũ (idempotent) | identity = shipment/fulfillment reference, KHÔNG phải order increment_id | DOCS |
| `to_name` / `to_phone` / `to_address` | String | Yes | ≤1024 | | DOCS |
| `to_ward_name` | String | Yes (new mode) | tên theo master-data (mới hoặc cũ) | GHN_ADMIN_2025 names verbatim | DOCS |
| `to_province_name` | String | Yes | | như trên | DOCS |
| `to_district_name` | String | **Conditional** | **required khi `is_new_to_address=false`; để RỖNG khi true** | KHÔNG fabricate district PRE_2025 cho create | DOCS |
| `is_new_to_address` | Bool | Optional | **default false** | true = scheme mới 2 cấp (ward+province) | DOCS (semantics) + UNVERIFIED_SANDBOX (live) |
| `from_name/phone/address/ward_name/district_name/province_name`, `from_hotline` | String | **All optional** | default = shop profile theo `ShopId` | **Launchpad Core: omit toàn bộ from_* — 1 Magento origin → 1 ShopId** | DOCS |
| `return_*` (`return_name/phone/address/ward_name/district_name/province_name`, `is_new_return_address`) | | Optional | default = sender / địa chỉ kho mặc định trên dashboard | current docs **name-based** (`return_district_name`); `return_district_id`/`return_ward_code` là LEGACY ONLY | DOCS |
| `weight` | Int | Yes | gram, **max 50,000** | | DOCS |
| `length`/`width`/`height` | Int | Yes (create) | cm, **max 200 mỗi chiều** | create yêu cầu đủ dims (≠ RATE optional) | DOCS |
| `service_type_id` | Int | Yes | enum {2,5} | 2/<20kg, 5/≥20kg-multiparcel | DOCS |
| `payment_type_id` | Int | Yes | **1 = shop trả phí; 2 = buyer trả phí** | **KHÔNG liên hệ COD**; Launchpad default 1 (Magento đã thu phí tại checkout — 2 ⇒ rủi ro double-charge); merchant config, không infer từ payment method | DOCS |
| `required_note` | String | Yes | enum `KHONGCHOXEMHANG` \| `CHOXEMHANGKHONGTHU` \| `CHOTHUHANG` | config default, không hardcode, không derive từ order comment | DOCS |
| `content` | String | **Conditional** | ≤2000 | bắt buộc **khi `items` vắng mặt**; gửi cả hai rỗng bị từ chối | DOCS |
| `items[]` | Object[] | Conditional | `name` ≤512, `code`, `quantity` ≥1, `price` (VND), `weight`/`length`/`width`/`height` — **dims+weight per-item BẮT BUỘC khi type 5**; optional (khuyến nghị) cho type 2 | KHÔNG include field Magento lạ | DOCS |
| `cod_amount` | Int | Optional | VND, **max 50,000,000**, default 0 | **tên create là `cod_amount`** (≠ fee `cod_value`); upstream-owned | DOCS |
| `cod_failed_amount` | Int | Optional | VND, default 0 | | DOCS |
| `insurance_value` | Int | Optional | VND, **max 5,000,000**, default 0 | declared/compensation value — **KHÔNG đồng nghĩa `order_value`**; vượt limit → validate/upstream policy, không truncate | DOCS |
| `order_value` | Int | Optional | VND, default 0 | giá trị đơn (provider metadata) | DOCS |
| `coupon`, `note` (≤5000), `pick_station_id` (0 = pickup tại shop), `pick_shift[]` (default ca sớm nhất) | | Optional | | | DOCS |
| ~~`delivery_date`, `client_id`, `items[].category`~~ | | — | **không có trong current docs** (legacy/old) → LEGACY ONLY | | DOCS (missing) |

Response: `data.order_code` (tracking number), `data.fee{...}`, `data.total_fee`, `data.expected_delivery_time` (+ `message_display`).
Errors: `USER_ERR_COMMON`, `PHONE_INVALID`, `CLIENT_NOT_OWNER_OF_SHOP`, `ROUTE_NOT_FOUND_SERVICE`, `SERVICE_NOT_FOUND_CONFIG_FEE`, `SERVER_ERROR_COMMON`.

## 6. Order Info — GET `v2/shipping-order/detail?order_code=…`

Request: `order_code` (client_order_code KHÔNG phải request param — chỉ là response echo). Response ~120 fields; bản ghi cần: `status` (23 giá trị, 7 terminal: delivered/returned/cancel/exception/lost/damage/scrap), `payment_type_id` (response: **1/4/5 = shop trả, 2 = buyer trả** — request chỉ 1/2, response rộng hơn — ghi nhận), `cod_amount`, `cod_collect_date`, `is_cod_collected`, `service_type_id` + `service_id` (echo), `calculate_weight`, `leadtime`, `items[]`. **Không có `total_fee` trong response table hiện tại** (fee qua webhook `fee` event / create response). Headers Token+ShopId. Verified by: DOCS.

## 7. Update Order — POST `v2/shipping-order/update`

Partial update — chỉ field gửi lên mới đổi. `order_code` **required**. `cod_amount` đổi **cần `otp`** (lỗi `OTP_NOT_VALID`, `PERMISSION_DENIED_EDIT_COD`). Ma trận field-cập nhật-theo-status đầy đủ trong docs (ready_to_pick = all; delivered/cancel/… = none). Verified by: DOCS. **NOT_USED** (chưa có consumer trong P1).

## 8. Update COD — POST `v2/shipping-order/updateCOD`

Chính xác 2 field: `order_code` + `cod_amount` (**max 5,000,000** — khác max 50M của create; "0 clears COD"). **Không có `otp`** trong trang này (discrepancy với update page nơi cod_amount cần otp — ghi nhận, sandbox confirm khi dùng). Response `data: null`. Extra field bị forward "unchecked" → builder phải gửi đúng 2 field. Verified by: DOCS. **NOT_USED**.

## 9. Cancel — POST `v2/switch-status/cancel`

| Field | Type | Required | Notes | Verified by |
|---|---|---|---|---|
| `order_codes` | String[] | Yes | **batch all-or-nothing**: 1 code lạ ⇒ cả request bị reject | DOCS |
| `reason_code` | String | Yes | enum `GHN-CO001` (pickup lâu) \| `GHN-CO002` (hết hàng) \| `GHN-CO003` (khách bỏ đơn) \| `GHN-CANCEL-OTHER` | DOCS |
| `reason` | String | No | free text | DOCS |

Idempotency: hủy lại / đơn đã giao ⇒ **HTTP 200 với `data[].result=false`** (không phải HTTP error) — classifier phải đọc per-order `result`. Không cần address. Verified by: DOCS. **NOT_USED** (GHN-D).

## 10. Return (force R2S) — POST `v2/switch-status/return`

`order_codes[]` duy nhất; **per-order best-effort** (khác cancel all-or-nothing!). Trạng thái được return: `delivery_fail`, `storing`, `waiting_to_return`, `return` — status khác ⇒ `result:false` + message. Verified by: DOCS. **NOT_USED** (GHN-D).

## 11. Webhook callback-order-status

- Payload **PascalCase**, mọi field luôn hiện diện (zero value khi unset): `ShopID, Time, OrderCode, ClientOrderCode, Type, Description, Status, Reason, ReasonCode, CODAmount, CODTransferDate, Weight, ConvertedWeight, Length/Width/Height, PaymentType, IsPartialReturn, PartialReturnCode, Fee, TotalFee, Warehouse, ShipperName, ShipperPhone, PodURL`.
- `Type` ∈ `create` (bắn một mình) | `switch_status` | `update_weight` | `update_cod` | `update_fee` | `update_payment_type` | `cod` | `update_partial_return`.
- **Auth: KHÔNG có signature** — merchant tự cấu hình custom headers (token riêng do mình định nghĩa) → Secomm phải validate header riêng và KHÔNG xử lý khi thiếu. **KHÔNG lặp lại defect legacy (webhook không auth).**
- Dedup key (docs hướng dẫn): `OrderCode + Type + Time`. FIFO per order; retry backoff 30s→12h; 4xx (trừ 408/429) bị drop; 2xx = success.
- `Status` = 23 giá trị của Order Status Codes. `Reason/ReasonCode` chỉ với ready_to_pick/delivery_fail/return_fail/damage/lost/cancel.
Verified by: DOCS. **NOT_USED** (GHN-E).

## 12. Print/Label — POST `v2/shipping-order/print` — **NOT_USED**, chưa fetch chi tiết (không consumer; trang `/en/docs/order/print` tồn tại, fetch khi GHN-D label slice).

---

## 13. Discrepancy register (current docs vs legacy/old pages vs Secomm code)

| # | Vấn đề | Current docs | Legacy / old | Secomm_Ghn hiện tại | Kết luận |
|---|---|---|---|---|---|
| D1 | Rate identity | `service_type_id` {2,5} — fee/create/leadtime; leadtime warn rõ service_id **no effect** | `service_id` từ available-services (persist vào quote_address) | endpoints có sẵn nhưng 0 runtime usage | **P0 contract**: kiến trúc theo service_type_id; service_id chỉ provider metadata (nếu lộ qua AS/info) |
| D2 | AS body naming | `shop_id` + `from_district` + `to_district` (header ShopId vẫn bắt buộc) | `from_district_id`/`to_district_id`, không có body shop_id | chưa dùng | naming theo docs; **UNVERIFIED_SANDBOX** cho naming thật |
| D3 | COD field name | fee=`cod_value`; create/update=`cod_amount` (updateCOD max 5M; create max 50M) | cả 2 constant đều có, dùng lẫn | chưa dùng | adapter transform: `collection_amount → cod_value` (RATE) / `→ cod_amount` (CREATE) |
| D4 | payment_type_id | 1=shop, 2=buyer (request); response có thể 4/5 (=shop-trả variants) | config payment_type (1/2) | Config `PaymentType` source đã đúng 1/2 | tách bạch COD; default 1; KHÔNG infer từ payment method |
| D5 | return address | name-based (`return_district_name`, `is_new_return_address`) | `return_district_id`/`return_ward_code` | chưa dùng | theo docs hiện tại |
| D6 | required_note | 3 enum | enum tương tự | `RequiredNote` source có sẵn | config-driven, không hardcode |
| D7 | service_type enum | {2,5} theo weight | old docs có 1/3/5 kiểu service class | chưa dùng | **không** map 1→EXPRESS như phỏng đoán cũ |
| D8 | webhook auth | merchant custom headers, không signature | legacy webhook **không có auth** | chưa có webhook | GHN-E phải validate merchant header (anti-defect legacy) |
| D9 | cancel vs return | cancel all-or-nothing; return per-order best-effort; cả hai trả per-order `result` | legacy xử khác | chưa dùng | classifier phải đọc `data[].result` |
| D10 | is_new_to_address | bool default false; true ⇒ to_district_name rỗng | n/a | chưa dùng | **SANDBOX-VERIFIED 2026-09-11**: create mới-địa-chỉ thành công (L8TL6B), idempotency cùng client_order_code → cùng order_code |

## 14. Error classification matrix (GHN → Secomm outcome)

| GHN condition | HTTP | Secomm outcome | FailureReason | Retryable | Note |
|---|---|---|---|---|---|
| token/shop_id chưa cấu hình | — (pre-flight) | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | auth/config thất bại KHÔNG bao giờ TECHNICAL (E-C1) |
| origin district chưa cấu hình / invalid parcel (weight ≤0) | — (pre-flight) | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | invalid parcel/config là business |
| canonical unresolved (handoff) | — | UNAVAILABLE | handoff reason (`CANONICAL_UNRESOLVED`/`UNSUPPORTED_DESTINATION`) | — | Stage-1 domain |
| mapping APPROVED không tồn tại (Stage 2) | — | UNAVAILABLE | `PROVIDER_MAPPING_MISSING` | — | **KHÔNG** convert thành CANONICAL_UNRESOLVED (§28 task) |
| `ROUTE_NOT_FOUND_SERVICE` / `SERVICE_NOT_FOUND_CONFIG_FEE` / rate-not-offered fragments | 400 | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | business no-service |
| `USER_ERR_COMMON` (weight zero / invalid input) | 400 | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | |
| `PHONE_INVALID` | 400 | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | |
| `CLIENT_NOT_OWNER_OF_SHOP` / 401/403 | 400/403 | UNAVAILABLE | `SERVICE_UNAVAILABLE` | — | auth/config |
| `SERVER_ERROR_COMMON` / HTTP 5xx / 429 | 5xx/429 | **TECHNICAL_FAILURE** | `TECHNICAL_ERROR` | yes (client GET-retry đã có; fee không retry) | fallback-eligible tại ShippingCore |
| timeout / connection failure / HTTP 0 | — | **TECHNICAL_FAILURE** | `TECHNICAL_ERROR` | — | |
| malformed JSON / empty 200 body (quirk) | 200 | **TECHNICAL_FAILURE** | `TECHNICAL_ERROR` | — | khi dùng AS: empty 200 = shop not found → về UNAVAILABLE (config) — ghi chú riêng |
| cancel/return `data[].result=false` | 200 | business result per-order | n/a (GHN-D slice) | — | KHÔNG coi là technical |

Status điều khiển orchestration; reason chỉ là diagnostics — không ai parse reason để quyết fallback.

## 15. Secomm recommended payloads (chưa final tới khi sandbox verify)

**RATE (fee):**
```json
{
  "service_type_id": 2,                     // weight < 20000g ? 2 : 5
  "weight": <grams>,                        // required, non-zero
  "to_district_id": <Stage-2>, "to_ward_code": "<Stage-2>",
  "from_district_id": <config origin>,      // optional theo docs; omit nếu chưa cấu hình
  "length|width|height": <cm>,              // chỉ khi có dữ liệu thật; omit — cấm 1×1×1
  "cod_value": <collection_amount>          // chỉ khi > 0; upstream-owned
}
```
**CREATE (current-address, P1 khuyến nghị — sandbox gate trước khi ship):**
```json
{
  "client_order_code": "<stable shipment reference>",
  "to_name|to_phone|to_address": "...",
  "to_province_name": "<GHN_ADMIN_2025 verbatim>", "to_ward_name": "<verbatim>",
  "to_district_name": "", "is_new_to_address": true,
  "weight": ..., "length|width|height": ...,   // required, ≤200cm
  "service_type_id": 2, "payment_type_id": 1, "required_note": "<config>",
  "cod_amount": <collection_amount>, "insurance_value": ..., "order_value": ...,
  "items": [...]                               // type 5: per-item dims+weight bắt buộc
}
```
from_*/return_*: **omit** (ShopId profile fallback — docs confirm) · coupon/note/pick_station_id/pick_shift: optional.
