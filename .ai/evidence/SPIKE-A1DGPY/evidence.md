# Evidence — SPIKE-A1DGPY: GHTK API contract audit (post ShippingCore v5)

Date: 2026-09-14 · Mode: analysis-only (0 production code changed) · Probe: read-only

## Evidence classification legend

- `VERIFIED_FROM_OFFICIAL_DOCS` — fetch trực tiếp official docs (api.ghtk.vn, EN + VI), 2026-09-14.
- `VERIFIED_BY_STAGING` — probe staging thật (không khả dụng trong audit này — xem §Probe).
- `NEEDS_RUNTIME_VERIFICATION` — docs không đủ để chốt; phải verify trên staging trước khi freeze.

## 0. Environment / base URLs — VERIFIED_FROM_OFFICIAL_DOCS

| Env | OPEN_API base | Nguồn |
|---|---|---|
| STAGING | `https://services-staging.ghtklab.com` | api.ghtk.vn "Môi trường, tham số và xác thực" |
| PRODUCTION | `https://services.giaohangtietkiem.vn` |同上 |

- Auth: `Token` + `X-Client-Source` trên MỌI request; token **scoped permissions + expiry date**,
  tự phát hành từ portal (không có static test token). Token fail → **HTTP 403 với body rỗng**.
- Connectivity check: `GET/POST /services/authenticated`.
- Envelope: success = `{success:true, message, log_id, data}`; failure = `{success:false,
  message, error_code, log_id}`. **Business error có thể đi kèm HTTP 200** — không được classify
  theo HTTP code trơn.
- **`dev.giaohangtietkiem.vn` KHÔNG phải official endpoint** (không xuất hiện trong docs hiện
  hành). Grep codebase: 0 occurrence trong code/config ✓ (config `carriers/ghtk/api_base_url` =
  production ✓). Runbook cũ trong `.ai/evidence/TASK-7AJ3K8/evidence.md` (2026-09-10) ghi dev
  endpoint → **SỬA runbook khi thực hiện probe (dùng staging URL trên)**.
- `ver=1.5`: xuất hiện trong CREATE URL sample nhưng **không được định nghĩa** ở bất kỳ trang
  docs nào fetch được → NEEDS_RUNTIME_VERIFICATION (semantics: có đổi request/response/address
  behavior gì không).

## 1. RATE — `GET /services/shipment/fee` — VERIFIED_FROM_OFFICIAL_DOCS

| Field | Req | Ghi chú |
|---|---|---|
| pick_address_id | No | "will take priority if not empty"; khi valid → pick_province/pick_district **không bắt buộc** |
| pick_province / pick_district | **Yes*** | *trừ khi pick_address_id |
| pick_ward / pick_address / pick_street | No | |
| province | **Yes** | text name |
| **district** | **Yes** | text name — **KHÔNG có ghi chú 2-level/fallback trên trang docs** |
| ward | **No** | optional |
| address / street | No | |
| weight | **Yes** | **integer, GRAM** |
| value | No | VND — insurance basis |
| transport | No | `road` \| `fly`; invalid → default |
| tags | No | |

Response: `fee{name: area1|area2|area3, fee, insurance_fee, delivery: bool, extFees[]}`.
KHÔNG có `ver` trên fee docs. Không có error enumeration trang này (trang "Xử lý mã lỗi" riêng).

**Đối chiếu implementation:**
- `FeeRequestMapper`: province/ward/weight(gram)/transport/value/pick_* ✓ MATCH; **district chỉ
  gửi khi override có** — docs nói REQUIRED → **delta** (xem §7 matrix — NEEDS_RUNTIME_VERIFICATION
  về behavior thật với post-2025 2-level: GHTK vẫn map internal 3-level hay chấp nhận thiếu district).
- `FeeResponseMapper`: fee/insurance_fee/extFees/delivery/name ✓ MATCH docs response.
- `FeeResponseMapper` KHÔNG đọc `success`/`error_code` — business error HTTP 200 → feeBlock null →
  hide (implicit fail-closed ✓) nhưng KHÔNG phân biệt business vs technical cho
  `CarrierRateOutcome` (E-C1 runtime sẽ cần) → P1.

## 2. CREATE — `POST /services/shipment/order?ver=1.5` — VERIFIED_FROM_OFFICIAL_DOCS

Request shape chính thức: **`{"order": {...}, "products": [...]}`** — order là OBJECT, products
là ARRAY RIÊNG. Các fields trong `order`:

| Field | Req | Semantics chính thức |
|---|---|---|
| **`id`** | **Yes** | partner order code — **duplicate-detection key** |
| pick_name / pick_tel / pick_address / pick_province / pick_district | Yes | |
| pick_ward / pick_street / pick_ext_tel / pick_email | No | |
| **pick_address_id** | No | **"will take priority if not empty"** (override name-based pickup) |
| **pick_money** | **Yes** | int VND — COD amount; **0 = không thu tiền** |
| **pick_option** | No | **`cod` \| `post`, default `cod`** — **logistics pickup mode** (shop handoff semantics), KHÔNG phải payment-method selector. `cod` = GHTK đến lấy hàng tại shop (thu ngân sách nếu pick_money>0). |
| **is_freeship** | No (default **0**) | **1 → người nhận CHỈ trả pick_money** (shop đã thu phí ship trước); **0 → người nhận trả pick_money + phí ship** |
| name / tel / address | Yes | recipient |
| **province / district / ward** | **Yes** | text names |
| **street / hamlet** | Conditional | street required nếu không có hamlet; ngược lại hamlet required; không áp dụng → dùng **"Khác"** |
| value | **Yes** | int VND — insurance/compensation basis |
| weight_option | No | `gram` \| `kilogram`, **default kilogram** |
| transport | No | `road` \| `fly` |
| use_return_address + return_* | conditional | |
| tags / sub_tags / not_delivered_fee / opm / label_id / pick_date… | No | tags: 2 high-value, 17 partial-delivery, 19 failed-delivery-fee… |

`products[]`: **name (yes), weight (yes — KILOGRAM per docs), quantity, price, product_code, h/w/l**.

Response success: `order{partner_id, label (vd "S1.A1.2001297581"), area, fee, insurance_fee,
tracking_id, estimated_pick_time, estimated_deliver_time, products, status_id}`.

### ORDER_ID_EXIST (idempotency) — VERIFIED_FROM_OFFICIAL_DOCS

GHTK "không cho phép push lại mã đơn đã tạo thành công". Duplicate `order.id` → error
**`ORDER_ID_EXIST`** kèm:
- `partner_id` (mã partner đã submit),
- `ghtk_label` (label GHTK của đơn gốc),
- `created` (timestamp tạo gốc),
- `status` (status code hiện tại — cross-ref bảng webhook).

**Kết luận (recommendation):** `ORDER_ID_EXIST` = **RECOVERABLE_EXISTING_ORDER**, KHÔNG phải
hard failure, KHÔNG blind-retry. Flow target:

```text
deterministic partner order id (đã có: ghtk-{increment}-{seq})
        ↓
create → ORDER_ID_EXIST
        ↓
validate returned partner_id == submitted id (belongs to same order)
        ↓
recover: ghtk_label + status → hoàn tất shipment (tracking/label) hoặc
         surface "đã tồn tại với trạng thái X" cho merchant (khi status cho thấy
         đơn gốc đã tiến xa — KHÔNG tự claim success nếu status đã returned/cancelled)
```

Auto-retry POST vẫn KHÔNG cho phép; manual merchant retry giữ nguyên. Chi tiết implement trong
SPEC riêng (TASK backlog #5).

**Đối chiếu implementation (`OrderRequestMapper` / `OrderSubmitService`):**
- **P0 — request shape sai docs**: current gửi payload PHẲNG + `'partner_order_id' => ...` +
  `'order' => $products` (dùng key `order` cho products array!). Official: bọc `order` OBJECT +
  `products` array + field tên `id`. (Q-EXT trong docblock cũ đã cảnh báo drift — giờ có docs
  evidence cụ thể. Create-order chưa từng verify runtime — token rỗng.)
- **P0 candidate — products weight unit**: docs = KILOGRAM; current gửi gram trong products
  (`gramsPerUnit`) + `weight_option=gram`. Interaction `weight_option` với `products[].weight`
  chưa được docs mô tả rõ → NEEDS_RUNTIME_VERIFICATION; nếu weight_option chỉ áp top-level
  weight thì products weights lệch 1000×.
- `hamlet: 'Khác'` ✓ MATCH docs; `is_freeship=1` ✓ semantics khớp implementation (shop đã thu
  phí ship ở checkout); `pick_money` = resolved COD ✓; `transport` ✓; `value` ✓.
- **district omitted khi null** — docs district REQUIRED → cùng tension với RATE (NEEDS_RUNTIME_VERIFICATION).
- `OrderResponseMapper`: đọc `order.label` (+fallback label_id) ✓; tracking candidates
  `tracking_code|tracking|label` — docs trả **`tracking_id`** → thêm candidate (P1 nhỏ).
- Success check `success === true` ✓ đúng envelope.

## 3. TRACK — `GET /services/shipment/v2/{TRACKING_ORDER}` — VERIFIED_FROM_OFFICIAL_DOCS

- Identifier: **label GHTK HOẶC partner `order.id`** (path param).
- Response `order`: `label_id`, `partner_id`, **`status`** (string code), **`status_text`**,
  created/modified, pick_date/deliver_date, message (note), pick_money, ship_money, insurance,
  value, weight (grams), is_freeship, ext_fees[], storage_day.
- **KHÔNG có address input** → **TRACK address capability = NOT REQUIRED** ✓ (chốt được).
- Đối chiếu: `GhtkTrackingFetcher` GET đúng path ✓; đọc `order.status` (+fallback status_id) ✓;
  message → hiện đọc `order['message']` (order NOTE, không phải status text) → nên đọc
  `status_text` (P1 nhỏ). Reconciliation theo partner_id khả dụng (fetcher hiện dùng label — OK).

## 4. CANCEL — `POST /services/shipment/cancel/{TRACKING_ORDER}` — VERIFIED_FROM_OFFICIAL_DOCS

- Identifier: label HOẶC `partner_id:{PARTNER_CODE}`.
- ⚠️ Docs discrepancy: Endpoint section ghi **POST**, code samples dùng **GET** →
  NEEDS_RUNTIME_VERIFICATION (method thật).
- **Chỉ cancel được khi status ∈ {1 (Chưa tiếp nhận), 2 (Đã tiếp nhận), 12 (Đang lấy hàng)}** —
  "Đơn đã lấy hàng, không thể hủy đơn."
- Response: `{success, message, log_id}`; already-cancelled → `success:false` +
  "Đơn hàng đã đã ở trạng thái hủy" (benign duplicate, KHÔNG phải technical error).
- **KHÔNG có address** → CANCEL address capability = NOT REQUIRED ✓.
- **Đối chiếu: module chưa có cancel implementation** (grep: 0 cancel service) → GAP P1 — Magento
  cancel order/shipment hiện không tác động đơn GHTK thật. Classification đề xuất:
  already-cancelled → SUCCESS-equivalent (idempotent benign); not-allowed-by-state → UNAVAILABLE
  (business, message cụ thể); unknown order → UNAVAILABLE; timeout/5xx → TECHNICAL_FAILURE.

## 5. WEBHOOK — callback `POST {partner_url}?hash=XXX` — VERIFIED_FROM_OFFICIAL_DOCS

- Auth = **URL secret** (`hash` query) — KHÔNG có HMAC. Khớp thiết kế `webhook_secret` hiện tại ✓.
- Payload: `label_id`, `partner_id`, `status_id` (int), `action_time` (**ISO 8601**),
  `reason_code`, `reason`, `weight` (**kg**), `fee`, `pick_money`, `return_part_package`
  (1 = giao một phần).
- Sample **form-urlencoded** + JSON sample cùng tồn tại trong docs → NEEDS_RUNTIME_VERIFICATION
  content-type thật. Current `WebhookPayloadParser` chỉ `json_decode` → nếu form thật thì parse
  fail → **P1 delta: accept cả form-urlencoded và JSON**.
- `action_time` — current parser đọc `updated_at|timestamp|time`, **KHÔNG đọc `action_time`** →
  occurredAt luôn null, mất out-of-order guard → **P1 delta**.
- Response: **HTTP 200** = delivered; non-200/no-response → **GHTK retry ĐÚNG 1 lần**. Current
  controller luôn 200 ✓ MATCH.

## 6. STATUS TABLE chính thức (VI + EN docs CONSISTENT — không có EN/VN discrepancy ở bảng này)

Nguồn: api.ghtk.vn `/docs/submit-order/webhook` (VI) + `/en/docs/submit-order/webhook` (EN) —
hai bản cùng semantics. Đối chiếu `GhtkStatusMapper` hiện tại:

| Mã | Chính thức (VI) | EN | Normalized đúng | Mapper hiện tại | Verdict |
|---|---|---|---|---|---|
| -1 | Hủy đơn hàng | Order Canceled | CANCELLED | CANCELLED | ✓ |
| 1 | Chưa tiếp nhận | Not Yet Received | CREATED | CREATED | ✓ |
| 2 | Đã tiếp nhận | Received | PICKING | PICKING | ✓ |
| 3 | Đã lấy hàng/Đã nhập kho | Picked Up / Warehoused | PICKED_UP | PICKED_UP | ✓ |
| 4 | Đã điều phối giao hàng/**Đang giao hàng** | Out for Delivery / In Delivery | **OUT_FOR_DELIVERY** | IN_TRANSIT | ⚠️ P1 — "Đang giao" = out-for-delivery; current IN_TRANSIT conservative nhưng sai semantic (mất granularity) |
| 5 | Đã giao hàng/Chưa đối soát | Delivered / Not Yet Reconciled | DELIVERED | DELIVERED | ✓ (chưa đối soát = financial detail) |
| 6 | **Đã đối soát** | Reconciled | DELIVERED (terminal financial) | **DELIVERY_FAILED** | **P0 — sai hoàn toàn**: đối soát = TRẠNG THÁI SAU KHI GIAO THÀNH CÔNG; mapper hiểu "gặp lỗi khi giao hàng" → shipment bị đánh dấu failed SAI |
| 7 | **Không lấy được hàng** | Pickup Failed | DELIVERY_FAILED (+message) * | PICKING | **P0** — pickup failure bị coi như đang lấy hàng; (*normalized enum không có PICKUP_FAILED — DELIVERY_FAILED là closest representable; giữ carrier message chi tiết) |
| 8 | Hoãn lấy hàng | Pickup Delayed | PICKING | PICKING | ⚠️ chấp nhận được (delay thuộc pickup loop, reattempt); comment "Đang lấy hàng" sai — sửa comment |
| 9 | **Không giao được hàng** | Delivery Failed | **DELIVERY_FAILED** | PICKING | **P0 — sai nghiêm trọng**: giao-thất-bại (reattempt possible) bị coi là đang-lấy-hàng → customer tracking hiển thị sai pha |
| 10 | Delay giao hàng | Delivery Delayed | OUT_FOR_DELIVERY | OUT_FOR_DELIVERY | ⚠️ chấp nhận (vẫn trong vòng giao, trễ); comment "Đang giao hàng" ổn |
| 11 | **Đã đối soát công nợ trả hàng** | Return Reconciliation Completed | **RETURNED** | DELIVERY_FAILED | **P0** — return-reconciliation-completed bị coi delivery-failed |
| 12 | **Đã điều phối lấy hàng/Đang lấy hàng** | Pickup Assigned / In Pickup | **PICKING** | **RETURNING** | **P0 — NGƯỢC HOÀN TOÀN**: đầu luồng lấy hàng bị coi là đang trả hàng (CANCEL doc cũng xác nhận 12 = "Picking up in progress" — còn là trạng thái được phép hủy) |
| 13 | **Đơn hàng bồi hoàn** | Compensation Order | RETURNED + message * | RETURNED | ⚠️ P1 — "bồi hoàn" = bồi thường (thường là mất hàng — KHÔNG có hàng trả về); RETURNED sai nghĩa nhưng cùng terminal-family, representable + message. Không mở normalized status mới (D10 — 1 carrier 1 code) |
| 20 | Đang trả hàng (COD cầm hàng đi trả) | In Return Process | RETURNING | RETURNING | ✓ |
| 21 | **Đã trả hàng (COD đã trả xong hàng)** | Returned | **RETURNED** | **(thiếu → UNKNOWN)** | **P0 — thiếu code**: return flow không bao giờ kết thúc được trên Magento |
| 123/127/128/45/49/410 | Shipper báo — "chỉ mang tính thông báo, **không phải trạng thái đơn hàng**" | informational | **UNKNOWN (không map)** | UNKNOWN | ✓ ĐÚNG — docs cảnh báo 123 có thể bị sửa thành 127; không được map |

Nguyên nhân gốc: mapper được viết từ một bảng legacy không chính thức (comments "Gặp lỗi khi
giao hàng"(6), "Chưa lấy được hàng"(9), "Chuyển hoàn"(12), "Đã giao hoàn"(13) không khớp docs
hiện hành nào). Terminal semantics: -1, 6, 11, 13, 21 (+5 = delivered chuẩn); 7 kết thúc luồng
lấy (đơn thường bị hủy — reason 110–115); 9/10 reattempt possible; DELIVERY_FAILED giữ non-terminal
✓ đúng NormalizedTrackingStatus docblock. Không cần normalized status mới.

## 7. Address capability per-operation (§14/§26)

| Operation | requiredScheme | Representation | Address required | Provider notes |
|---|---|---|---|---|
| RATE | **NEEDS_RUNTIME_VERIFICATION** — candidate VN_ADMIN_2025 + TEXT_NAME (r1 hypothesis); tension: docs **district REQUIRED** trong khi 2-level post-2025 không có district → nếu GHTK cần district thật thì RATE phải PRE_2025-name-district hoặc strategy riêng | TEXT_NAME (chắc chắn — fee API thuần text) | Yes | weight GRAM; pick_address_id priority override pickup fields |
| CREATE | **NEEDS_RUNTIME_VERIFICATION** — candidate VN_ADMIN_2025 + TEXT_NAME; tension district REQUIRED + `hamlet`/level-4 bắt buộc "ở một số khu vực" (`getAddressLevel4`) | TEXT_NAME | Yes | request bọc `{order, products}`; `id` = partner key; ver=1.5 semantics unknown |
| TRACK | n/a | n/a | **No** — VERIFIED (identifier-only) | identifier = label HOẶC partner id |
| CANCEL | n/a | n/a | **No** — VERIFIED | chỉ được ở status 1/2/12 |

**supportsTextualFallback: false cho cả RATE + CREATE** (docs không có khái niệm fallback;
"không gửi guessed address" giữ nguyên). KHÔNG freeze scheme cho tới staging probe §9.

**TEXT_NATIVE (r1 `GhtkAddressAdapter`) assessment:** concept hướng đúng (text-based API đã
verify; không có carrier IDs anywhere trong docs) nhưng **chưa chứng minh được canonical
post-2025 names accepted** — đặc biệt district question. Override table giữ nguyên vai trò
exception-only ✓ đúng hướng. Không reintroduce full mapping table trừ khi staging evidence bắt buộc.

## 8. COD semantics (§8) — VERIFIED_FROM_OFFICIAL_DOCS

| Field | Official semantics | Current implementation | Verdict |
|---|---|---|---|
| `pick_money` | COD amount (VND) GHTK thu từ recipient; 0 = không thu | `DefaultCodAmountResolver` → `base_total_due` (outstanding), non-COD → 0 | ✓ semantics đúng; amount đọc từ order ✓ đúng principle "carrier chỉ map collect amount đã quyết upstream" |
| `pick_option` | **Logistics pickup mode** (`cod` = GHTK đến lấy tại shop / `post` = shop đem ra bưu cục), default `cod`. KHÔNG phải payment-method COD selector | Không gửi (default cod) | ✓ KHÔNG mislabel — không cần sửa; ghi nhận semantics |
| `is_freeship` | 1 → recipient CHỈ trả pick_money (shop đã thu ship trước); 0 → recipient trả pick_money + ship | Luôn `is_freeship=1` (Magento đã thu ship ở checkout) | ✓ khớp business model storefront; lưu ý nếu tương lai có "ship at door" |

**COD identification ownership — delta P1:** `DefaultCodAmountResolver` tự đọc
`GhtkConfig::getCodMethodCodes()` (carrier-owned config, default `cashondelivery`). Architecture
v5 §4.1/§8 chốt: ShippingCore own COD method configuration +
`CodPaymentMethodResolverInterface::isCod()` (contract ĐÃ TỒN TẠI trong ShippingCore —
TASK-STC3NB). Carrier vẫn tự đọc amount từ order ✓. → delta: consume shared resolver; composition
feed ShippingCore COD config; bỏ carrier-level `cod_method_codes` (hoặc deprecate).

## 9. Sandbox probe — PENDING (không đủ điều kiện)

- Staging `https://services-staging.ghtklab.com` **reachable** (401 trong 0.3s — auth-gated, live).
- Token: `carriers/ghtk/api_token` trong config local **RỖNG**; không có staging token từ nguồn
  khác → probe RATE không thực hiện được (đúng rule §16: chỉ probe khi token khả dụng).
- Runbook (sửa so với bản 09-10 — **staging URL chính thức, KHÔNG dùng dev.giaohangtietkiem.vn**):
  ```bash
  export GHTK_BASE="https://services-staging.ghtklab.com"
  export GHTK_TOKEN="<staging token — tự phát hành từ khachhang-staging.ghtklab.com>"
  export GHTK_SRC="<shop/partner code>"
  # Connectivity:
  curl -s -H "Token: $GHTK_TOKEN" -H "X-Client-Source: $GHTK_SRC" "$GHTK_BASE/services/authenticated"
  # RATE probes (read-only) — 7 representative cases: Hà Nội / TP.HCM / Đà Nẵng /
  # ward post-2025 / xã / ward name trùng giữa 2 tỉnh / address KHÔNG có district:
  curl -s -H "Token: $GHTK_TOKEN" -H "X-Client-Source: $GHTK_SRC" \
    "$GHTK_BASE/services/shipment/fee?pick_province=H%C3%A0+N%E1%BB%99i&pick_district=<t%C3%AAn>&province=<name_vi>&district=<name_vi?>&ward=<name_vi>&weight=1000&transport=road"
  # Ghi: request shape, HTTP status, success/error_code, accepted/rejected fields.
  # CREATE chỉ sau khi RATE ổn + có cleanup (cancel API) — KHÔNG chạy trên production.
  ```
- Câu hỏi probe PHẢI trả lời trước khi freeze capability: (1) district omitted → accept/reject?
  (2) products weight unit khi `weight_option=gram`; (3) webhook content-type thật; (4) CANCEL
  method GET/POST; (5) `ver=1.5` có đổi behavior; (6) ORDER_ID_EXIST response shape thật.

## 10. Error classification (§17/§18) + retry matrix (§19)

Classification (per response, không theo HTTP code trơn):

| GHTK response | Class | Ghi chú |
|---|---|---|
| `success:true` (+fee/order/data) | SUCCESS | |
| HTTP 200 + `success:false` + error_code (khác ORDER_ID_EXIST) | UNAVAILABLE (business) | message + log_id vào diagnostics |
| `ORDER_ID_EXIST` | RECOVERABLE_EXISTING_ORDER | reconcile theo §2 — không failure, không retry |
| HTTP 403 (empty body — token invalid/expired/scoped-off) | **UNAVAILABLE** (merchant config/auth — non-transient) | docs: token có expiry + scoped permissions → vẫn là vấn đề merchant phải sửa; KHÔNG transient |
| TIMEOUT / connection / HTTP 5xx | TECHNICAL_FAILURE | retryable trên read ops |
| HTTP 4xx khác (429…) | UNAVAILABLE (không retry) | docs không mô tả rate-limit semantics → conservative |

Retry matrix:

| Operation | Retry? | Condition | Max | Reason |
|---|---|---|---|---|
| RATE | Yes | NETWORK/SERVER_ERROR/TIMEOUT | 1 + `retry_max` config | read-like (hiện tại ✓ đúng) |
| CREATE | **No auto-retry** | — | 1 | duplicate risk; recovery path = ORDER_ID_EXIST reconcile + manual merchant retry (hiện tại ✓ giữ) |
| TRACK | Yes | NETWORK/SERVER_ERROR/TIMEOUT | 1 + `retry_max` | read (✓ hiện tại) |
| CANCEL | Single-attempt (đề xuất) | — | 1 | state mutation; dù already-cancelled benign, 5xx sau write ambiguous → merchant retry an toàn nhờ idempotent answer. KHÔNG retry tự động. |
| PICKUP list | Yes | NETWORK/SERVER_ERROR | 1 + retry_max | read |

## 11. ShippingCore v5 compatibility (§24)

| Area | Verdict | Ghi chú |
|---|---|---|
| Per-operation capability (RATE/CREATE) | **CARRIER CODE CHANGE** | `CarrierAddressCapabilityInterface` (per-carrier) ĐÃ @deprecated — GHTK phải migrate sang `CarrierOperationAddressCapabilityInterface` + `handoffForOperation()/handoffContextForOperation()` (TASK-Y3X6H5). GHTK r1 dùng contract cũ. |
| `CanonicalResolutionSnapshot` | MATCH (chưa consume) | GHTK rate path chưa đọc snapshot (shift-left chưa wiring cho GHTK) — việc của carrier-adaptation task; v5 contract đủ. |
| COD identification | **CARRIER CODE CHANGE** | Consume `CodPaymentMethodResolverInterface` (đã tồn tại); bỏ carrier-owned `cod_method_codes` config. |
| LegacyRateStrategy (§15.1) | MATCH (không opt-in) | GHTK TEXT_NATIVE candidate không cần legacy 3-level RATE — trừ khi staging chứng minh district bắt buộc → lúc đó cân nhắc MAP_THEN_FALLBACK. Không quyết trong SPIKE. |
| CarrierRateOutcome / ShippingFailureReason | MATCH | 5 reasons đủ; ORDER_ID_EXIST là carrier-internal recovery state, không cần shared reason. |
| Tracking pipeline | MATCH | Fetcher → shared processor ✓; chỉ sửa mapping table (carrier-owned). |
| HTTP primitive | MATCH | Client đã dùng shared `CarrierHttpClientInterface` + RetryExecutor. |
| **POSSIBLE SHIPPINGCORE GAP** | **NONE declared** | Không operation nào của GHTK yêu cầu semantics v5 không express được. Business-error-in-HTTP-200: express được (carrier classify success:false → UNAVAILABLE) — không cần shared change. |

## 12. Delta đến class/file (§27/§28 input — P0/P1/P2)

### P0 — production correctness (trước go-live carrier)
1. `Model/Tracking/GhtkStatusMapper.php` — sai semantics 6/7/9/11/12/13 + thiếu 21 (bảng §6).
2. `Model/OrderSubmit/OrderRequestMapper.php` — request shape `{order{...}, products[]}` + field
   `id` (bỏ flat + `partner_order_id` + `'order' => $products`).
3. `Model/OrderSubmit/OrderRequestMapper.php` (weight) — products weight unit kg vs gram
   (NEEDS_RUNTIME_VERIFICATION trước khi sửa, nhưng xác suất cao P0).

### P1 — align + hardening
4. `Model/OrderSubmit/OrderSubmitService.php` + mapper — ORDER_ID_EXIST recovery
   (validate partner_id → recover label/tracking/status; không auto-retry).
5. `Model/OrderSubmit/DefaultCodAmountResolver.php` + `etc/di.xml` + `system.xml` — consume
   ShippingCore `CodPaymentMethodResolverInterface`; deprecate `GhtkConfig::getCodMethodCodes`.
6. `Model/Tracking/WebhookPayloadParser.php` — accept form-urlencoded + JSON; đọc `action_time`.
7. `Model/Address/GhtkAddressCapability.php` — migrate per-operation capability (+ adapter gọi
   `handoffContextForOperation`); freeze scheme sau staging probe.
8. RATE/CREATE district optionality — xử lý theo probe result (nếu bắt buộc: strategy riêng,
   cân nhắc MAP_THEN_FALLBACK §15.1).
9. NEW cancel integration (`CancelOrderService` + GhtkApiClient::cancelOrder) — identifier
   label/partner_id, states 1/2/12, already-cancelled benign.
10. `Model/Fee/FeeResponseMapper.php` + rate outcome — business-error classification
    (success/error_code → UNAVAILABLE) phục vụ E-C1 outcome; log `log_id`.
11. `Model/GhtkApiClient.php` + `OrderResponseMapper` — read `tracking_id`; TRACK message từ
    `status_text`; CANCEL method chốt theo probe.
12. Config/docs — staging base URL vào runbook/docs (config key `api_base_url` đã đủ — giá trị
    là config, không cần key riêng).

### P2 — future
13. PICKUP list API: `TestConnection` thật (`/services/authenticated` / `list_pick_add`) +
    validate `pick_address_id` + admin pickup selector UI.
14. Special-address level-4 (`getAddressLevel4`) — hamlet bắt buộc ở một số khu vực.
15. Print-label API (hiện native PDF gen đủ dùng).
16. Profile code `GHTK_2025` — không phải official generation name (docs chỉ có `ver` param);
    đổi tên internal khi implement (CARRIER CODE CHANGE nhỏ, không phải API behavior).

## 13. Sources (official, fetched 2026-09-14)

- https://api.ghtk.vn/en/docs/submit-order/submit-order-express/ (CREATE + ORDER_ID_EXIST)
- https://api.ghtk.vn/en/docs/submit-order/calculate-shipping-fee/ (RATE)
- https://api.ghtk.vn/en/docs/submit-order/tracking-status/ (TRACK)
- https://api.ghtk.vn/en/docs/submit-order/api-cancel-order/ (CANCEL)
- https://api.ghtk.vn/docs/submit-order/webhook (WEBHOOK — VI, bảng status chính thức) + EN variant
- https://api.ghtk.vn/en/docs/submit-order/api-get-pick-addresses/ (PICKUP list)
- https://api.ghtk.vn/en/docs/submit-order/api-get-specific-addresses/ (level-4)
- https://api.ghtk.vn/en/docs/submit-order/logistic-overview/ (env/auth/errors)
