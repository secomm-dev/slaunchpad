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
updated: 2026-09-25
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
last_verified: 2026-09-25
supersedes: []
---

# [SLP][TASK-NHW715] TikTok Pixel P2 — 5 event bổ sung + guest advanced matching

## Summary

Mở rộng `Secomm_TiktokHyva` (v1.0.0 → v1.1.0) theo danh sách event TikTok Events Manager
đang trống: `Search`, `AddToWishlist`, `CompleteRegistration`, `PlaceAnOrder`,
`AddPaymentInfo` + P2-4 advanced matching cho guest checkout.

## Mini Spec

### Expected Behavior

1. **Search** (pixel-only): layout `catalogsearch_result_index` + pool entry mới
   (pixel-only, không publish S2S — vendor `TiktokEvent` không mang được `search_string`,
   ghi nhận limitation; S2S upgrade path để sau). `search_string` truyền qua endpoint
   body (sanitize, cap 128 ký tự).
2. **AddToWishlist**: observer `wishlist_add_product` → publish S2S + stash pending
   (tái dùng `PendingPixelEventStorage`) → pixel fire ở render kế tiếp, cùng `event_id`.
   Theme add-to-wishlist là fetch AJAX → pixel-side trễ 1 navigation (giống AddToCart).
3. **CompleteRegistration**: observer `customer_register_success` → cùng cơ chế.
4. **PlaceAnOrder**: observer `sales_model_service_quote_submit_success` (bắt mọi đường
   đặt hàng incl. Mageplaza OSC) → cùng cơ chế; cover luôn gap "đặt xong không quay về
   success" ở mức PlaceAnOrder (CompletePayment vẫn cần success page).
5. **AddPaymentInfo**: pool entry đọc quote (như InitiateCheckout) + delegated listener
   `change` trên `[name^="payment"]` ở trang checkout → fetch endpoint động
   `event_types:['AddPaymentInfo']`. Selector cần QC xác nhận trên OSC Hyvä.
6. **Guest advanced matching**: plugin 8 getter của vendor `EventContext` — fallback
   quote billing address khi customer-session data rỗng (email/phone/name/city/state/
   country/zip). Giá trị vẫn được vendor hash SHA-256 trước khi gửi.

### Constraints / Rules

- Không sửa vendor; pool mở rộng qua di.xml merge (cơ chế extensible sẵn có).
- Observers Throwable-safe + guard admin area + guard pixel disabled — không break
  wishlist/register/place-order flow.
- Endpoint whitelist + param parsing an toàn (string filter, numeric cast, cap length).
- `strict_types`, PHP 8.2+; không storefront string.

### Out of Scope

S2S `search_string` (cần preference/subclass vendor TiktokEvent); pixel-side AddToCart
tức thì cho AJAX; EMQ/Dedup verification (phụ thuộc cron drain P1 — TASK-VDA8V8).

### Acceptance Criteria

1. Search result page → `Search` event có `search_string` đúng query.
2. Thêm wishlist → log `Event published AddToWishlist`; render kế tiếp pixel fire cùng
   `event_id`.
3. Đăng ký tài khoản mới → `CompleteRegistration` 1 record.
4. Đặt đơn (OSC, guest) → `PlaceAnOrder` (S2S có MỌI khi kể cả không quay về success) +
   `CompletePayment` như cũ.
5. Chọn payment method ở OSC → `AddPaymentInfo` fire đúng khi change.
6. Guest checkout → event S2S có `user.email/phone` hash (trước đây trống với guest).
