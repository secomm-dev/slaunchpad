# SPEC-TASK-VDA8V8 — TikTok Pixel Hyvä compat (Secomm_TiktokHyva, P1)

> MINI spec. Nguồn: audit `Tiktok_Tiktok` v1.0.3 2026-09-24 + phê duyệt TL in-chat
> (P1 only, AddToCart hybrid). Record: `.ai/records/tasks/TASK-VDA8V8.md`.

## 1. Goal

Pixel TikTok + 5 event P1 (`Pageview`, `ViewContent`, `AddToCart`, `InitiateCheckout`,
`CompletePayment`) hoạt động trên Hyvä 3.x + Mageplaza OSC, không double-count
pixel/S2S (same `event_id`), không sửa file vendor, FPC-safe.

## 2. Gaps đã xác nhận (audit 2026-09-24)

| Gap | Chi tiết |
|---|---|
| Hyvä | vendor inject qua `text/x-magento-init` + `pixel-loader.js` (jQuery/Underscore/RequireJS) — không execute trên Hyvä (không shim) |
| OSC | vendor handle `checkout_index_index.xml` không match route OSC `onestepcheckout/index/index` → InitiateCheckout không fire |
| FPC | payload event chứa session data → không được render thẳng vào HTML cacheable |
| AddToCart | theme dùng AJAX add-to-cart (Monsoon `ajaxSubmitCart`) — `ajax:addToCart` của Luma không tồn tại |

## 3. Approach

Module mới `Secomm_TiktokHyva` (không đụng vendor):

1. **Base pixel inline** trong `pixel.phtml` (vanilla JS, `HyvaCsp::registerInlineScript()`),
   chỉ chứa pixel code per-website → cacheable.
2. **Endpoint** `POST /tiktokhyva/events/index` (`Controller\Events\Index`, whitelist pool
   keys, POST-only, read-only) trả `{user, events}` — session-safe, FPC-safe; `fetch`
   vanilla track + `identify` sau khi base load.
3. **ViewModel `Pixel`** tái dùng `Tiktok\Tiktok\Model\Event\Pool` (mỗi type execute đúng
   1 lần/request → S2S publish như vendor), build payload client + gộp pending events,
   dedupe theo `event_id`.
4. **Layout handles**: `default` (Pageview), `catalog_product_view` (ViewContent),
   `onestepcheckout_index_index` (InitiateCheckout), `checkout_onepage_success`
   (CompletePayment); remove vendor block `tikTok.tiktok.pixel`.
5. **AddToCart hybrid**: plugin after `Tiktok\Tiktok\Observer\AddToCartObserver` stash
   payload (cùng `event_id` với S2S đã publish) vào checkout session TTL 600s
   (`PendingPixelEventStorage`) → endpoint trả về ở render kế tiếp → TikTok dedupe.
   AJAX flow: S2S là tín hiệu chính (đã ghi nhận limitation; P2 inject vào response JSON).

## 4. Constraints

- Không sửa `app/code/Tiktok/` — chỉ plugin (after) + layout remove block.
- Guard: skip admin area + pixel disabled; mọi path `Throwable`-safe — không break
  add-to-cart/page render/endpoint.
- Endpoint read-only, whitelist event types, không đổi state → không cần form_key/ACL
  (surface tương đương endpoint vendor `tiktok/tiktokevents`).
- Checkout OSC: chỉ render thêm block — KHÔNG đổi flow; QC e2e bắt buộc (§12).
- `strict_types`, PHP 8.2+, không ObjectManager, không storefront string.

## 5. Acceptance Criteria

1. View-source: 1 `ttq.load('<pixel_code>')`/page; không còn vendor
   `text/x-magento-init`; không event payload nào trong HTML cached.
2. Network: 1 `POST /tiktokhyva/events/index`/page → `{user, events}`; event_types ngoài
   whitelist bị lọc.
3. Events Manager (Test Events): mỗi event xuất hiện đúng 1 record sau dedupe
   pixel+S2S; CompletePayment với COD và VNPAY (return success).
4. AddToCart: S2S luôn có (log `var/log/tiktok*.log`, queue `tiktok.event.track` drain
   sau cron 5'); pixel-side bắn ở render kế tiếp cùng `event_id`.
5. Checkout OSC e2e không regression (ExtraFee/DeliveryTime/Secomm address dropdown).
6. `setup:upgrade` sạch (data patch vendor: `tiktok_brand`, `tiktok_condition`,
   `tiktok_product_group_id`).

## 6. Test matrix

| Page/flow | Event | Kiểm chứng |
|---|---|---|
| Home/PLP/CMS | Pageview | Pixel Helper + Test Events |
| PDP | Pageview + ViewContent | content_id = sku/group, content_type |
| Thêm vào giỏ (PDP AJAX + PLP card) | AddToCart S2S + pixel (render kế tiếp) | same event_id |
| OSC checkout | Pageview + InitiateCheckout | quote contents/value/currency |
| Success (COD) | Pageview + CompletePayment | order_id, value |
| Success (VNPAY return) | CompletePayment | đúng 1 lần |
| FPC hit | (PDP cached) | endpoint vẫn trả payload đúng user |
| Admin order create | (không stash pixel) | plugin skip admin area |
