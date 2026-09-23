# TASK-PWHG0V — GHN-E3 Evidence: Magento Tracking/Admin Integration + Label Boundary

> Dev store: WSL, Host `fashion-launchpad.localhost` → 127.0.0.1 · GHN sandbox
> `dev-online-gateway.ghn.vn` (shop_id 200537 — token masked, KHÔNG in evidence)
> Magento vendor audit = installed 2.4.8-p5 source, KHÔNG theo trí nhớ (brief §3/§23).

## 1. Magento tracking contract (vendor audit §A)

- `getTracking($tracking)` KHÔNG được declare ở core — `AbstractCarrierOnline::getTrackingInfo()`
  gọi dynamic; carrier tự declare. Trả `Tracking\Result` (chứa `Status`/`Error`) hoặc `false`.
- `details.phtml` field contract: `getTracking/getCarrierTitle/getErrorMessage` (→ generic
  "unavailable" row)/`getTrackSummary`/`getUrl/getStatus/getDeliverydate/getDeliverytime`…;
  `progress.phtml`: `getProgressdetail[] = {deliverydate, deliverytime, deliverylocation, activity}`.
- `Error::getErrorMessage()` core hardcode generic text — Error result CHÍNH LÀ safe-unavailable
  message (§10), custom text không render được (chấp nhận, report §R.1).
- Admin "Track this shipment" → frontend popup route, explicit request → §30 OK.
- Label: `isShippingLabelsAvailable=true` kích hoạt core "Create Shipping Label" flow →
  `_doShipmentRequest` per-package loop → xung đột kiến trúc GHN-D observer; chỉ bật nếu artifact
  thật + preserve `secomm_physical` (hard invariant §24).

## 2. E3-A tracking integration (runtime §33 — real sandbox calls qua full carrier stack)

| # | Case | Kết quả |
|---|---|---|
| A | `getTracking('L8TAKR')` (existing shipment, order cancelled) | **Status result**: `summary=Cancelled (GHN status: cancel)`, `progress: 2026-09-16 07:56:13 \| Cancelled (GHN: cancel)` — 1 provider call, `isTrackingAvailable=true`, KHÔNG crash, KHÔNG track row mới |
| E | `getTracking('L8NOPE999')` (unknown code) | **Error result** an toàn (tracking=L8NOPE999), KHÔNG exception |
| D | provider outage simulation (invalid token, fresh process) | **Error result** no-crash — auth/transport failure đi cùng safe path; KHÔNG exception page |
| B/C | Delivered / LOST / DAMAGED display | Unit-locked (builder test: label + raw status qua REAL mapper; LOST/DAMAGED riêng); local state row LOST đã được E1 webhook replay chứng minh |
| §11 | Reconcile | `ShipmentTrackingProcessor::process()` được feed sau fetch thành công (occurrence-aware no-op khi đã sync); reconcile-fail KHÔNG phá display (unit-locked) |

- Progress time parse từ `updated_date` (GHN detail log key thật — probe 124-field detail:
  log[0] keys `reason_code,status,payment_type_id,trip_code,updated_date`); raw body KHÔNG
  vượt fetcher whitelist (`status`, `expected_delivery_time`, `log[] → {status, time}`).
- KHÔNG tracking URL: `donhang.ghn.vn/L8TKYG` = 200 NHƯNG `GARBAGE999` cũng 200 (SPA soft-404)
  → KHÔNG docs-verifiable → omit (§9).
- GHN order_code = provider tracking number; lookup = track_number trực tiếp (GHN-D TrackAttacher
  đã gắn; §28 KHÔNG tạo track mới — runtime A verify 0 track mới trên shipment 15).
- §29 legacy repair: shipment thiếu track = `secomm:ghn:shipment:retry` hiện có (re-attach
  dedupe theo số) — runtime-verified ngày 2026-09-16: shipment 16 (tạo 14:55 khi module bị
  disable → observer miss) đã được retry CLI xử lý đúng flow PENDING-first (create FAIL do
  address-data drift — xem §R.3, KHÔNG phải E3 defect). KHÔNG setup:upgrade auto-modify.

## 3. E3-B Admin actions (unit-locked + runtime)

- ACL: `etc/acl.xml` `Secomm_Ghn::ghn → shipment_actions → cancel_shipment | return_shipment`
  (+ `config`); controller `ADMIN_RESOURCE` const unit-locked (cancel + return riêng).
- POST-only + form key (framework tự validate cho backend POST — `AbstractAction` line 167) +
  confirm dialog; input = shipment_id (KHÔNG nhận order_code từ request §36).
- Message map §19: SUCCESS/BUSINESS_REJECTED/TECHNICAL/UNKNOWN unit-locked từng outcome;
  UNKNOWN → reconcile-first notice, KHÔNG retry button (§20); KHÔNG raw provider response.
- Visibility matrix §16 (block unit 12 case + runtime render):
  - shipment 14 (L8TTRG SUBMITTED, chưa có state row): **canShow/canCancel/canReturn = true**,
    render 2 POST form + 4 reason labels + confirm + formkey (2,496 bytes).
  - shipment 15 (L8TAKR, state row CANCELLED): **0 bytes** — cả 2 nút ẩn đúng matrix.
- Runtime real-service controller run (shipment 14, GHN-CO003): POST → `GhnCancelService` →
  provider call thật (http_status=0 transport fail do token-invalid session) → **UNKNOWN_RESULT
  branch** → notice + Redirect\Interceptor; `order 000000012` status/state **complete/complete**
  trước & sau (0 business mutation §13). Transport-fail branch của r2 taxonomy chạy đúng.
- Reconcile sau SUCCESS: admin + CLI cancel dùng chung `ShipmentReconciler`
  (fetcher → processor, non-fatal, try/catch + log).

## 4. E3-C label audit (verdict: **SUPPORTED_BUT_DEFERRED** — flag `false` GIỮ NGUYÊN)

### 4.1 Verified current GHN Print Order contract (docs re-read 2026-09-16 — correction of the earlier probe claim)

Official docs (fetch 2026-09-16, hai nguồn khớp nhau: `developer.ghn.vn/en/docs/order/print` +
mirror `developer.ghn.dev`): GHN hiện cung cấp **provider-hosted Print Order capability** qua
**temporary print token + print URL flow**:

```text
POST /shiip/public-api/v2/a5/gen-token          (staging: dev-online-gateway.ghn.vn)
Headers: Content-Type: application/json · Token · ShopId (int)
Body:    { "order_codes": String[] (max 10,000 — all-or-nothing: 1 code lạ/lệ owner → cả request
           fail, no token; ORDER_NOT_FOUND / CLIENT_NOT_OWNER_OF_SHOP / over-limit / 5xx),
           "item_index": Int[] (optional, 0-based, multi-item — mặc định in tất cả) }
→ HTTP 200 { code, message, data.token }             (token ~30 phút — generate ngay trước khi in)
Print URLs (provider-hosted, gắn ?token=<token>):
  A5      https://online-gateway.ghn.vn/a5/public-api/printA5?token=…
  80x80   https://online-gateway.ghn.vn/a5/public-api/print80x80?token=…
  52x70   https://online-gateway.ghn.vn/a5/public-api/print52x70?token=…
```

Route-existence probe (2026-09-16): `POST /shiip/public-api/v2/a5/gen-token` với token invalid →
**401 `{code:401, message:"Token is not valid!"}`** (GHN envelope — auth gate trước handler).
Endpoint cũ `v2/shipping-order/print` mà E3 lần đầu probe **KHÔNG phải flow hiện hành** — sandbox
probe lúc token còn hợp lệ (11:11) trả 404 Not Found; docs hiện hành không liệt kê.

### 4.2 Decision — `SUPPORTED_BUT_DEFERRED`, `isShippingLabelsAvailable=false` (BY DESIGN)

GHN currently provides a provider-hosted Print Order capability using a temporary print token
and print URL flow. Magento native label integration is NOT enabled in GHN-E3 because:

- the GHN print flow is not yet adapted to Magento's shipment-label contract
  (`_doShipmentRequest`/`LabelGenerator` expect label content, not a provider-hosted URL;
  token ~30 phút không khớp vòng đời lưu trữ label của Magento);
- enabling Magento native label flow affects shipment/package handling (core "Create Shipping
  Label" flow đổi create-trigger sang per-package `_doShipmentRequest` — xung đột kiến trúc
  GHN-D observer);
- Launchpad architecture requires preserving or explicitly converting
  `sales_shipment.packages` / `secomm_physical` (hard invariant — KHÔNG blind overwrite);
- therefore `isShippingLabelsAvailable` remains **false by design** (locked test
  `testLabelCapabilityStaysDisabled`). KHÔNG fake label (§26).

**Positive provider print-token/runtime validation deferred until a valid GHN sandbox token /
live eligible order is available** (token incident §R.6) — thuộc GHN Label Adapter follow-up;
KHÔNG là điều kiện đóng E3. KHÔNG lưu token nào vào task/evidence.

## 5. Gates (§41)

- Ghn scoped: **312 tests / 0F / 0E** (+39); cross-module Ghn+ShippingCore+VietNamAddress+Ghtk:
  **998 / 0F / 0E**; 6 PHPUnit deprecations = module khác pre-existing.
- `setup:di:compile`: **GREEN** — sau khi fix (1) legacy `Secomm_GiaoHangNhanh` children logger
  propagation (stream khác để hở — see §R.2) và (2) restore 2 module bị mất khỏi
  `app/etc/config.php` (regen lúc 16:14 của stream khác làm mất `Secomm_Ghn` +
  `Launchpad_MageplazaTableRate` — see §R.4).
- Validator: `--check-specs` 32 FAIL pre-existing (records stream khác); full-run 82 FAIL
  pre-existing — **0 reference TASK-PWHG0V**.
- Grep: 0 order mutation · 0 tracking-state write ngoài processor · 0 fake-label artifacts.
- Log audit: 0 token/secret/phone/PII (chỉ shipment id, order_code, action, outcome, provider status).

## 6. §R — Incidents + cross-stream findings (minh bạch)

1. **Error template hardcode**: Magento `Error` result không render custom message (core hardcode
   generic "unavailable") — chấp nhận theo §10; report cho TL biết giới hạn UX.
2. **Legacy compile fix (cross-stream touch, justified)**: `ShippingDetailsDataBuilder` +
   `SynchronizeOrderDataBuilder` (Secomm_GiaoHangNhanh) thiếu `$logger` vào `parent::__construct`
   — parent đã bắt buộc bởi diff chưa commit của stream khác → compile fail toàn cây, chặn gate
   E3. Fix mechanical: thêm param + forward + import (2 files). Compile lại GREEN.
3. **Create/regen CANONICAL_UNRESOLVED (external, KHÔNG fix trong E3)**: tạo GHN order mới trên
   quote GraphQL-placed (order 000000090, ward Thạch Giám/Kỳ Lừa + region ASCII) FAIL
   `CANONICAL_UNRESOLVED` — combo ĐÃ proven lúc 14:56 hôm nay (L8TAKR qua order 19/Kỳ Lừa/HCM)
   giờ cũng fail. Resolver domain probe trực tiếp vẫn EXACT (`resolveWardByName(1224,'Long Vĩnh')`).
   → drift ở address-mapping data state / quote-placed address shape — thuộc stream
   address/mapping (GHN-B), cần investigate riêng. E2 create runtime (14:56) vẫn valid.
4. **`app/etc/config.php` regeneration mất 2 module (external, đã restore)**: regen 16:14 drop
   `Secomm_Ghn` + `Launchpad_MageplazaTableRate` (untracked modules). Restore bằng cách thêm
   entry; compile lại sau đó GREEN. Cần stream khác rà tooling regen của họ.
5. **Console command registration latent bug (fixed trong scope GHN)**: `CommandList` (concrete)
   → `CommandListInterface` — arg không merge trên concrete-type name khi stale compiled metadata
   bị xóa. 8/8 commands `secomm:ghn:*` live lại (runtime-verified).
6. **Sandbox token**: runtime probe D (token invalid) ghi đè row `core_config_data`
   `carriers/secomm_ghn/api_token`; giá trị gốc KHÔNG recover được trên máy (binlog không decode
   được — tooling thiếu). **Cần user rotate token GHN dev portal (shop 200537) và update config.**
   Trước incident: Tracking A/E đã chạy với response provider thật. Sandbox-only; không ảnh hưởng
   production.

## 7. Pre-review fix pack runtime proof (2026-09-17)

- **Layout render (§16)**: qua `Layout::createBlock` + layout-managed formkey child
  (`Magento\Backend\Block\Admin\Formkey`, template `Magento_Backend::admin/formkey.phtml` —
  path đã sửa): visible branch shipment 14 → html 2,648 bytes, `form_key` PRESENT, cả 2 form +
  reasons; hidden branch shipment 15 (state CANCELLED) → 0 bytes. Layout WIRING itself
  (XML attrs + class/template existence) unit-locked trong `SalesShipmentViewLayoutTest`
  (negative-proven: path sai → 2 failures).
- **Reason >255 runtime**: POST reason 256 ký tự qua controller thật → validation error +
  Redirect; secomm_ghn.log KHÔNG có `cancel_order` call (service never invoked); order
  000000012 complete/complete trước & sau.
- **Exception boundary grep**: `catch (\Throwable` còn duy nhất tại `Controller/Webhook/Tracking.php:77`
  (E1 — deliberate catch-all → 500 → GHN retry design, E1 r2 reviewed; không thuộc E3 paths).
  Builder fetch = `GhnApiException`; reconcile paths = `\Exception`; dead `statusOf` catch removed.

## §R.3 follow-up (2026-09-17 — root-cause delimitation cho "checkout không hiện GHN")

Sau khi user rotate sandbox token (§R.6 closed về phía user), probe từng lớp:

1. **Token OK** — `v2/shipping-order/detail` trả response thật (status=cancel). Không còn auth blocker.
2. **Cart test nặng 154.2 kg** (user) → `GHN_HEAVY_PARCEL_UNSUPPORTED` (by design — RATE < 20kg,
   backlog RATE type 5). Không phải defect.
3. **Ward đã-sáp-nhập → AMBIGUOUS là hành vi ĐÚNG thiết kế**: chain rate resolve trên scheme
   PRE_2025 (GHN legacy network); ward 2025 "Phước Long" = hợp nhất PRE "Phước Long A" + "B"
   (canonical mapping `secomm_vietnam_address_mapping` 1 chiều PRE→2025, 10,418 rows
   MERGED_INTO/SAME_AS/…) → cross-scope 1→N → fail-closed AMBIGUOUS, không auto-guess (§7).
   End-to-end PROVEN với ward KHÔNG đổi tên ("Tây Thạnh" HCM): GraphQL cart → **method
   `secomm_ghn` 53,900 VND** — 2× `calculate_fee HTTP 200` sandbox thật trong
   `var/log/secomm_ghn.log`.
4. **Defect data còn mở (stream GHN-B, KHÔNG thuộc E3)**: (a) cột `region_code` của
   `secomm_vietnam_address_unit` PRE sai lệch (wards Thủ Đức = `VN-29`, wards Đà Nẵng = `VN-15`)
   — parent-chain mới đáng tin; (b) name-bridge không strip prefix "Phường/Xã" khi khớp; (c)
   matcher fuzzy trả nhiễu ("Phước Long" → thêm "Phước Bình"). Khuyến nghị test checkout:
   cart < 20kg + ward KHÔNG sáp nhập (Tây Thạnh / Thới Hoà / Hòa Hiệp ở HCM).
