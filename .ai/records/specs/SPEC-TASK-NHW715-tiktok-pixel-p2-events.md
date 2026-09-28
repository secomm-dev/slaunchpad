# SPEC-TASK-NHW715 — TikTok Pixel P2 events (Secomm_TiktokHyva v1.1.0)

> MINI spec. Kế thừa TASK-VDA8V8 (P1). Nguồn: danh sách event trống trên TikTok
> Events Manager 2026-09-25 + phê duyệt TL in-chat ("ok giờ làm phase 2").

## 1. Goal

Bổ sung 5 event chuẩn TikTok còn thiếu (`Search`, `AddToWishlist`,
`CompleteRegistration`, `PlaceAnOrder`, `AddPaymentInfo`) + advanced matching cho guest,
tái dùng kiến trúc P1 (endpoint + pool vendor + pending stash + dedupe event_id).

## 2. Design

| Event | Trigger | S2S | Pixel |
|---|---|---|---|
| Search | page `catalogsearch_result_index` | ❌ (limitation — vendor TiktokEvent không có search_string) | pool entry (pixel-only) + `search_string` từ endpoint body |
| AddToWishlist | observer `wishlist_add_product` | ✅ observer | pending stash → render kế tiếp |
| CompleteRegistration | observer `customer_register_success` | ✅ observer | pending stash → render kế tiếp |
| PlaceAnOrder | observer `sales_model_service_quote_submit_success` | ✅ observer | pending stash → render kế tiếp (success page) |
| AddPaymentInfo | delegated `change` listener `[name^="payment"]` (chỉ trang có InitiateCheckout) | ✅ pool entry (quote items) | dynamic fetch cùng `event_id` |

Guest advanced matching: plugin 8 getter `EventContext` → fallback quote billing address
(khi customer session rỗng). Hash vẫn do vendor `TiktokEvent` thực hiện.

## 3. Constraints

- Vendor pool mở rộng bằng di.xml merge (item name trùng → merge vào argument `pool`).
- Observer pattern: `AbstractPendingObserver` — guard admin area + pixel enabled +
  Throwable catch (không break wishlist/register/order flow).
- Endpoint: whitelist += `Search`, `AddPaymentInfo`; `search_string` trim + cap 128.
- Search pixel-only: KHÔNG publish S2S — tránh plugin hack lên `TiktokEvent::getDataElement`
  (fragile); nếu sau này cần S2S Search → subclass/preference vendor event (đưa P3).
- PII: plugin chỉ cung cấp raw value vào vendor pipeline; hash SHA-256 trước khi gửi
  (giữ nguyên hành vi vendor). Không log PII.

## 4. Test matrix

| Flow | Event | Kiểm chứng |
|---|---|---|
| Search "abc" | Search | `search_string: "abc"` trong endpoint response + pixel track |
| Thêm wishlist (PDP/PLP) | AddToWishlist | S2S log + pixel cùng event_id ở render kế tiếp |
| Register account mới | CompleteRegistration | 1 record (kể cả qua SocialLogin) |
| Guest đặt đơn OSC | PlaceAnOrder + CompletePayment + AddPaymentInfo | PlaceAnOrder S2S có kể cả không về success; payment change → AddPaymentInfo |
| Guest event user data | (mọi event ở checkout) | `user.email`/`user.phone` hash ≠ null |
| Admin order create | — | không fire (guard area) |

## 5. Est

~14h dev (observers 4h, pool+endpoint+VM 3h, template JS 2h, guest plugin 3h, docs 1h,
QC tự chạy bởi TL).
