---
id: TASK-VDA8V8
type: task
title: 'TikTok Pixel — Hyvä compat module Secomm_TiktokHyva (P1: Pageview/ViewContent/InitiateCheckout/CompletePayment + AddToCart hybrid)'
project_code: SLP
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: '.ai/records/specs/SPEC-TASK-VDA8V8-tiktok-hyva-pixel-compat.md'
risk: medium
status: in_review
created: 2026-09-24
updated: 2026-09-24
decisions: []
components:
  - CMP-TIKTOK
source_areas:
  - app/code/Tiktok/Tiktok
  - app/code/Mageplaza/Osc
  - app/design/frontend/Secomm/launchpad/Monsoon_HyvaAjaxAddToCart
changes_project_state: true
changes_architecture: false
changes_integration: true
changes_known_limitations: false
last_verified: 2026-09-24
supersedes: []
---

# [SLP][TASK-VDA8V8] TikTok Pixel — Hyvä compat module Secomm_TiktokHyva (P1)

## Summary

Extension official `Tiktok_Tiktok` v1.0.3 không chạy được trên Hyvä (pixel inject qua
`text/x-magento-init` + `pixel-loader.js` dùng jQuery/Underscore/RequireJS) và miss
`InitiateCheckout` trên Mageplaza OSC (route `onestepcheckout/index/index` ≠
`checkout/index/index`). P1 build module compat `Secomm_TiktokHyva` thay tầng frontend,
tái dùng toàn bộ backend của vendor (event pool, S2S queue, catalog sync, OAuth).

## Mini Spec

### Goal

Pixel TikTok + 5 event P1 (Pageview, ViewContent, AddToCart, InitiateCheckout,
CompletePayment) hoạt động trên Hyvä + Mageplaza OSC, không double-count pixel/S2S
(same `event_id`), không sửa file vendor.

### Expected Behavior

1. Base pixel inline (vanilla JS, CSP-safe qua `HyvaCsp::registerInlineScript()`); event
   payload (chứa session data) fetch per-request từ endpoint `POST /tiktokhyva/events/index`
   của module — FPC-safe (payload không bao giờ vào page cache), thay jQuery loader của
   vendor, không form_key.
2. Layout handles: `default` (Pageview), `catalog_product_view` (ViewContent),
   `onestepcheckout_index_index` (InitiateCheckout — fix gap OSC),
   `checkout_onepage_success` (CompletePayment). Vendor block `tikTok.tiktok.pixel` removed.
3. AddToCart hybrid: plugin trên `Tiktok\Tiktok\Observer\AddToCartObserver` stash payload
   (cùng `event_id` với S2S đã publish) vào checkout session (TTL 600s) → lần render kế
   tiếp pixel track cùng `event_id` → TikTok dedupe. Theme dùng AJAX add-to-cart
   (Monsoon `ajaxSubmitCart`) nên trên AJAX flow S2S giữ vai trò chính; pixel-side bắn ở
   render kế tiếp.

### Constraints / Rules

- Không sửa vendor `Tiktok_Tiktok`; chỉ layout remove block + plugin (before/after).
- Guards: skip khi admin area / pixel disabled; `Throwable` catch — không bao giờ break
  add-to-cart hay page render.
- `strict_types`, PHP 8.2+, không ObjectManager, không storefront string (không cần i18n).
- Checkout OSC: chỉ thêm layout handle render block — KHÔNG đổi flow checkout. QC e2e
  bắt buộc (AGENTS §12).

### Out of Scope

P2: Search / AddToWishlist / CompleteRegistration; S2S order event (offline-safe);
advanced matching guest; pixel-side AddToCart cho AJAX flow (inject vào response JSON +
override `ajaxSubmitCart`).

### Acceptance Criteria

1. View-source các page: script `ttq.load('<pixel_code>')` render đúng 1 lần, vendor
   block không còn.
2. TikTok Events Manager (Test Events): Pageview mọi page; ViewContent PDP; InitiateCheckout
   trên OSC; CompletePayment trên success (COD + VNPAY quay về) — mỗi event duy nhất 1
   record sau dedupe pixel+S2S.
3. AddToCart: S2S luôn có (log `var/log/tiktok*.log` / queue `tiktok.event.track` trống dần
   sau cron 5'); pixel-side bắn ở render kế tiếp, cùng `event_id`.
4. Checkout OSC end-to-end không regression (ExtraFee/DeliveryTime/address dropdown hoạt động).
5. `setup:upgrade` chạy sạch (data patch vendor tạo 3 attributes).

## Pre-release fixes (QC 2026-09-24/25)

1. Endpoint fetch thiếu header `X-Requested-With: XMLHttpRequest` → M2.4.8
   `CsrfValidator` 302 "Invalid Form Key" toàn bộ request (không event nào tới được
   pixel/S2S qua endpoint). Fix: thêm header (fetch vanilla không tự gắn như jQuery).
2. `ViewContent` thiếu `content_id`: catalog registry per-request — endpoint phải nhận
   `product` param (pixel.phtml embed id) rồi load qua `ProductRepository`; event rỗng
   bị skip hẳn (TikTok flag >10% thiếu content_id).

## Est

~10h (build 6h, AddToCart hybrid 2h, CSP/config 1h, verify 1h) — đã TL chốt in-chat
2026-09-24 (P1 only, P2 defer).