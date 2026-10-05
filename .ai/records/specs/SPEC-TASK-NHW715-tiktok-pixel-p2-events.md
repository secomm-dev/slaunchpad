# SPEC-TASK-NHW715 — TikTok Pixel P2 events (Secomm_TiktokHyva v1.1.0)

> MINI spec. Kế thừa TASK-VDA8V8 (P1). Nguồn: danh sách event trống trên TikTok
> Events Manager + phê duyệt TL in-chat ("ok giờ làm phase 2"). **Đã đồng bộ thiết kế
> final sau QC** — danh sách 10 fix pre-release xem task record
> `.ai/records/tasks/TASK-NHW715.md` (single source, không duplicate ở đây).

## 1. Goal

Bổ sung 5 event chuẩn TikTok còn thiếu (`Search`, `AddToWishlist`,
`CompleteRegistration`, `PlaceAnOrder`, `AddPaymentInfo`) + advanced matching cho guest,
tái dùng kiến trúc P1 (endpoint + pool vendor + pending stash + dedupe `event_id`).

## 2. Design (final, sau QC)

| Event | Trigger | S2S | Pixel |
|---|---|---|---|
| Search | page `catalogsearch_result_index` | ✅ pool entry, enrich top 10 kết quả (contents/value) | `search_string` từ endpoint body (chỉ pixel-side) |
| AddToWishlist | observer `wishlist_add_product` | ✅ observer | pending stash → render kế tiếp |
| CompleteRegistration | **plugin concrete `AccountManagement::createAccount*`** | ✅ tracker (dedupe per-request) | pending stash → render kế tiếp |
| PlaceAnOrder | observer `sales_model_service_quote_submit_success` (seed `lastRealOrderId` trước track) | ✅ observer | pending stash → render kế tiếp |
| AddPaymentInfo | delegated `change` listener `[name^="payment"]` (chỉ trang có InitiateCheckout) | ✅ pool entry (quote items) | dynamic fetch cùng `event_id` |

Guest advanced matching: plugin 8 getter `EventContext` (null-safe) → fallback quote
billing. Payload normalization: plugin after `getDataElement` — `contents[].price` /
`value` cast number; CompleteRegistration lead value configurable
(`tiktok/pixel_tracking/complete_registration_value`, default 1000 VND).

## 3. Constraints

- Vendor pool mở rộng bằng di.xml merge; virtualType factory inject qua di.xml argument
  override (không type-hint trực tiếp — DI không resolve virtualType).
- Observer/plugin: `Throwable`-safe + guard admin area; log lỗi dùng **INFO** (vendor
  handler exact-match level, ERROR bị drop — xem task record fix #4).
- After-plugin phải `return $result` (interceptor gán return vào $result vô điều kiện —
  fix #2).
- Endpoint: whitelist event types, `search_string` trim + cap 128.
- PII: plugin chỉ cung cấp raw value vào vendor pipeline; hash SHA-256 do vendor; không
  log PII.

## 4. Known limitations

- Composite products (configurable/bundle): `getFinalPrice()` = 0 → event thiếu `value`
  (contents/content_id vẫn đủ) — chờ TL quyết chấp nhận hay build fallback P3.
- `search_string` không đi S2S (vendor `TiktokEvent` không có property setter).
- SocialLogin popup gọi cả 2 `createAccount*` trong 1 request — tracker dedupe đã che
  phía TikTok, nhưng cần verify riêng không tạo trùng customer (bug SocialLogin tiềm ẩn,
  tách ticket nếu thấy).

## 5. Acceptance Criteria

Xem task record `.ai/records/tasks/TASK-NHW715.md` (đã verify trong QC — probe trực
tiép endpoint + log; các diagnostic rolling-window của TikTok tự clear theo volume mới).

## 6. Est

~14h spec gốc + ~6h fix QC (ngoài est ban đầu — đã ghi nhận tracking).
