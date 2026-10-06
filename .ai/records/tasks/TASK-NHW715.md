---
id: TASK-NHW715
type: task
title: 'TikTok Pixel P2 — Search / AddToWishlist / CompleteRegistration / PlaceAnOrder / AddPaymentInfo + guest advanced matching'
project_code: SLP
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: '.ai/records/specs/SPEC-TASK-NHW715-tiktok-pixel-p2-events.md'
risk: medium
status: in_review
created: 2026-09-25
updated: 2026-10-05
decisions: []
components:
  - CMP-TIKTOK
source_areas:
  - app/code/Secomm/TiktokHyva
  - app/code/Tiktok/Tiktok
changes_project_state: true
changes_architecture: false
changes_integration: true
changes_known_limitations: true
last_verified: 2026-10-05
supersedes: []
---

# [SLP][TASK-NHW715] TikTok Pixel P2 — 5 event bổ sung + guest advanced matching

## Summary

Mở rộng `Secomm_TiktokHyva` (v1.0.0 → v1.1.0) theo danh sách event TikTok Events Manager
đang trống: `Search`, `AddToWishlist`, `CompleteRegistration`, `PlaceAnOrder`,
`AddPaymentInfo` + advanced matching cho guest checkout. Thiết kế đã điều chỉnh 2 điểm
so với bản spec đầu (CompleteRegistration qua service plugin, Search có S2S — xem
Expected Behavior) + 9 fix phát hiện trong QC — chi tiết ở section cuối.

## Mini Spec

### Expected Behavior (đồng bộ thiết kế final)

1. **Search**: layout `catalogsearch_result_index` + pool entry riêng, **có S2S**;
   enrich top 10 kết quả (fulltext collection + `addAttributeToSelect` name/price) để
   `contents`/`value` đầy đủ; `search_string` chỉ đi pixel-side (vendor `TiktokEvent`
   không mang được), TikTok merge 2 kênh theo `event_id`.
2. **AddToWishlist**: observer `wishlist_add_product` → publish S2S + stash pending
   (`PendingPixelEventStorage`) → pixel fire ở render kế tiếp, cùng `event_id`.
3. **CompleteRegistration**: **plugin trên concrete class
   `Magento\Customer\Model\AccountManagement`** (`createAccount` +
   `createAccountWithPasswordHash`) — SocialLogin popup tạo account mà không dispatch
   `customer_register_success`; plugin trên interface không inherit được trên stack này.
   Lead value configurable `tiktok/pixel_tracking/complete_registration_value`
   (default 1000 VND) — TikTok từ chối value thiếu lẫn value=0.
4. **PlaceAnOrder**: observer `sales_model_service_quote_submit_success` → trước khi
   track phải seed `checkoutSession.setLastRealOrderId()` (vendor `addOrderToEvent()`
   đọc order từ session, chưa set tại thời điểm dispatch).
5. **AddPaymentInfo**: pool entry (quote items) + delegated `change` listener
   `[name^="payment"]` ở trang có InitiateCheckout → dynamic fetch.
6. **Guest advanced matching**: plugin 8 getter `EventContext` (null-safe `?->`) —
   fallback quote billing; hash do vendor.
7. **Payload normalization**: plugin after `getDataElement` (điểm cuối chung mọi kênh) —
   `contents[].price`/`value` cast về number (EAV DECIMAL string → JSON string là sai
   spec TikTok); tracker dedupe mỗi event name 1 lần/request (SocialLogin popup gọi
   cả 2 service method trong 1 request).

### Constraints / Rules

- Không sửa vendor; pool mở rộng qua di.xml merge; factory virtualType inject qua
  di.xml argument override (không type-hint trực tiếp).
- Observer/plugin Throwable-safe + guard admin area — không break flow.
- Log lỗi dùng INFO (vendor `TiktokHandler::isHandling` exact-match → ERROR bị drop).
- `strict_types`, PHP 8.2+; không storefront string; không log PII.

### Out of Scope

S2S `search_string`; pixel-side AddToCart tức thì cho AJAX; value fallback cho composite
products (configurable/bundle — `getFinalPrice()` = 0, chờ TL quyết chấp nhận hay build
P3); GA-style analytics (ngoài scope TikTok EM).

### Acceptance Criteria (đã verify từng mục trong QC)

1. Search result page → `Search` + `search_string` + `contents` + `value` (probe
   trực tiếp: value 47,090,000 cho "luna").
2. Wishlist → S2S ngay + pixel cùng `event_id` ở render kế tiếp.
3. Register (popup SocialLogin) → `CompleteRegistration` ×1 (dedupe) với `value` 1000.
4. Đặt đơn → `PlaceAnOrder` S2S có `contents`/`value`/`order_id` kể cả không về
   success; success page có cả `CompletePayment`.
5. Chọn payment method ở OSC → `AddPaymentInfo` fire.
6. Guest checkout → `user.email/phone` hash không rỗng.
7. `contents[].price`/`value` là number (không string `.000000`).

## Pre-release fixes phát hiện trong QC (2026-09-25 → 10-05)

| # | Bug | Fix |
|---|---|---|
| 1 | Plugin on interface không inherit → CompleteRegistration không fire | Hook concrete `AccountManagement` |
| 2 | After-plugin `: void` → interceptor gán null vỡ flow đăng ký (account tạo xong UI báo lỗi) | Return `$customer` |
| 3 | `getBilling()->getEmail()` null-unsafe → fatal `Error` nuốt lặng (vendor Publisher chỉ catch Exception) | `?->` null-safe |
| 4 | Vendor `TiktokHandler::isHandling` exact-match level → mọi `logger->error()` vô hình | Chuyển log lỗi sang INFO + comment |
| 5 | Search inject nhầm factory Catalog (không có `addSearchFilter`) → event rỗng | Fulltext CollectionFactory qua di.xml override |
| 6 | Type-hint trực tiếp virtualType → "Impossible to process constructor argument" (Pool vỡ mọi page) | Hint factory gốc + override argument |
| 7 | Fulltext collection không select price/name → `value` 0, `content_name` null | `addAttributeToSelect` |
| 8 | `price`/`value` dạng DECIMAL string trong payload | `TiktokEventPayloadNormalizer` (after `getDataElement`) |
| 9 | PlaceAnOrder rỗng (session `last_real_order` chưa set lúc dispatch) | Observer seed `setLastRealOrderId` trước track |
| 10 | SocialLogin popup gọi 2 service method/1 request → 2 CompleteRegistration | Tracker dedupe per-request |

## Est

~14h spec gốc + ~6h fix QC (không vào est ban đầu — đã ghi nhận cho tracking).
