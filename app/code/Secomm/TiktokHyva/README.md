# Secomm_TiktokHyva

Hyvä theme compatibility layer for the official `Tiktok_Tiktok` connector (v1.0.3) on
Secomm Launchpad (Hyvä 3.x + Mageplaza One Step Checkout).

## Why

The vendor pixel only works on Luma:

- `Tiktok_Tiktok/view/frontend/templates/pixel.phtml` injects via `text/x-magento-init`
  (RequireJS — not present on Hyvä).
- `pixel-loader.js` depends on jQuery/Underscore and the Luma `ajax:addToCart` event.
- The vendor `InitiateCheckout` handle (`checkout_index_index.xml`) never matches because
  Mageplaza OSC uses the `onestepcheckout/index/index` route.

## Architecture

- `pixel.phtml` renders the **base pixel inline** (vanilla JS, CSP-safe via
  `HyvaCsp::registerInlineScript()`). Only the per-website pixel code is embedded — safe
  for full-page cache (Varnish).
- Event payloads contain session data (quote items, customer hashes, pending events), so
  they are fetched per-request via vanilla `fetch` from the module endpoint
  `POST /tiktokhyva/events/index` (JSON; whitelist of pool keys; POST is never FPC-cached).
  This replaces the vendor jQuery loader while keeping its FPC-correct design.
- The endpoint and `ViewModel\Pixel` reuse the **vendor event pool**
  (`Tiktok\Tiktok\Model\Event\Pool`), so every event is still published server-side
  (S2S Events API via the `tiktok.event.track` queue + `tiktok_event_queue_processor`
  cron) with the same `event_id` — TikTok dedupes pixel + S2S.
- Removes the inert vendor pixel block `tikTok.tiktok.pixel`.
- Adds the missing OSC layout handle (`onestepcheckout_index_index.xml`) for
  `InitiateCheckout`.

Events (P1): `Pageview` (all pages), `ViewContent` (PDP), `AddToCart` (hybrid),
`InitiateCheckout` (OSC), `CompletePayment` (success page).

Events (P2, v1.1.0): `Search` (search results; enriched with top 10 result products for
`contents`/`value` — `search_string` rides the pixel-side payload only: vendor
`TiktokEvent` cannot carry search_string), `AddToWishlist`
(`wishlist_add_product` observer), `CompleteRegistration` (plugin on
`AccountManagementInterface` — SocialLogin popup / CreatePost / GraphQL; the core
`customer_register_success` event only fires on the unused CreatePost form POST),
`PlaceAnOrder` (`sales_model_service_quote_submit_success` observer — the S2S
event exists even when the customer never returns to the success page), `AddPaymentInfo`
(pool entry + delegated payment-change listener on the checkout page via dynamic
endpoint fetch). Guest advanced matching: `EventContextGuestData` plugin falls back to
quote billing address when customer-session data is empty (values are SHA-256 hashed by
the vendor before sending).

### AddToCart strategy

The vendor observer (`checkout_cart_product_add_after`) publishes AddToCart S2S. Plugin
`AddToCartPendingEvent` stashes the same payload (`event_id` included) in the checkout
session (TTL 600s); the next page render returns it from the endpoint and tracks it
client-side. Same `event_id` on both channels → TikTok dedupe → never double-counted.

Note: this theme uses AJAX add-to-cart (Monsoon `ajaxSubmitCart`) so on AJAX flows the
S2S event is the primary signal; the pixel-side event fires on the next navigation.
(P2 candidate: inject the payload into the add-to-cart JSON response and track
immediately.)

## Requirements

- `Tiktok_Tiktok` enabled + connected (Business Center OAuth: `app_id`, `app_secret`,
  access token, `pixel_code` per website scope).
- Cron running: `tiktok_event_queue_processor` (every 5 min) drains the S2S queue.
- CSP: the vendor `Tiktok_Tiktok/etc/csp_whitelist.xml` whitelists `*.tiktok.com` etc.

## Testing

1. View-source: exactly one `ttq.load('<pixel_code>')` per page; no vendor
   `text/x-magento-init` block.
2. Network tab: one `POST /tiktokhyva/events/index` per page returning
   `{user, events:[...]}`.
3. TikTok Events Manager → Test Events: verify each event once (post-dedupe).
4. Order flows: COD and VNPAY (return to success page) → `CompletePayment`.
5. S2S: `var/log/tiktok*.log`, queue rows for `tiktok.event.track` drain after the cron run.
