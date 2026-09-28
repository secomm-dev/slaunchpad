# Changelog — Secomm_TiktokHyva

All notable changes to this module are documented in this file (Secomm-owned module rule,
AGENTS §7.2).

## [1.1.0] — 2026-09-25

### Added (TASK-NHW715 — P2 events)

- `Search` (search results page; enriched with the top 10 result products so
  `contents`/`content_id`/`value` ride along — TikTok flags Search events missing
  value; `search_string` rides the pixel-side payload only, TikTok merges both
  channels by event_id), `AddToWishlist` (`wishlist_add_product`),
  `CompleteRegistration` (plugin on `AccountManagementInterface::createAccount*` — this
  storefront registers through the Mageplaza SocialLogin popup, which never dispatches
  `customer_register_success`; the service covers SocialLogin popup, CreatePost form and
  GraphQL; admin-created customers excluded via the admin-area guard), `PlaceAnOrder`

### Fixed (pre-release, P2)

- `AccountManagementPlugin` after-methods now return the `CustomerInterface` result —
  a void after plugin nulls the interceptor's `$result` and broke the registration flow
  downstream (account created but the SocialLogin popup never reported success).
- Plugin moved from `AccountManagementInterface` to the concrete
  `Magento\Customer\Model\AccountManagement` (interface-type plugin inheritance did not
  fire on this stack); entry logging added for observability.
- `EventContextGuestData` getter fallbacks are null-safe (`?->`) — a bare call on a null
  billing address threw a fatal `Error` which the vendor `Publisher` (catches Exception
  only) let escape, silently killing the event.
- Error-path logging switched to INFO — the vendor `TiktokHandler::isHandling` uses
  exact-level matching, so ERROR records never reach `var/log/tiktok.log`.
- Search result lookup: inject the CatalogSearch fulltext collection factory via di.xml
  argument override (the virtualType cannot be constructor type-hinted) — the wrong
  base factory made `addSearchFilter` fatal and the event shipped without contents.
- Search result collection now selects name/price attributes — `getFinalPrice()` was 0
  (no `value`) and `content_name` was null on unselected attributes.
- `PendingEventTracker` dedupes per request — the SocialLogin popup invokes both
  `createAccount*` service methods for one registration, producing two
  CompleteRegistration events.
- `TiktokEventValueDefaults`: CompleteRegistration carries a configurable lead value
  (`tiktok/pixel_tracking/complete_registration_value`, website scope, default 1000 VND)
  — TikTok rejects both missing and zero values ("must be a number > 0"), so a positive
  lead value is required for clean diagnostics.
  (`sales_model_service_quote_submit_success` — covers checkouts that never return to
  the success page), `AddPaymentInfo` (pool entry + delegated payment-change listener
  on the checkout page, dynamic endpoint fetch).
- `Observer\AbstractPendingObserver` + 3 observers — S2S publish + pending stash
  (shared `event_id`, TikTok dedupe), Throwable-safe with admin-area guard.
- `Plugin\EventContextGuestData` — guest advanced matching: 8 `EventContext` getters
  fall back to quote billing address (email/phone/name/city/state/country/zip); vendor
  hashing unchanged. No PII in logs.

## [1.0.0] — 2026-09-24

### Added (TASK-VDA8V8)

- Inline vanilla-JS TikTok pixel base for Hyvä (`pixel.phtml` + `ViewModel\Pixel`),
  replacing the vendor Luma loader; CSP-safe via `HyvaCsp`; FPC-safe (only the per-website
  pixel code is inline).
- Endpoint `POST /tiktokhyva/events/index` (`Controller\Events\Index` + whitelist of pool
  keys) returning the per-request payload `{user, events}` — session data never hits the
  FPC cache.
- Layout handles: `default` (Pageview), `catalog_product_view` (ViewContent),
  `onestepcheckout_index_index` (InitiateCheckout — fixes missing event on Mageplaza OSC),
  `checkout_onepage_success` (CompletePayment). Vendor block `tikTok.tiktok.pixel` removed.
- `Plugin\AddToCartPendingEvent` + `Model\PendingPixelEventStorage` — hybrid AddToCart:
  S2S (vendor observer) + pixel-side on next render, shared `event_id` (TikTok dedupe).

### Fixed (pre-release)

- `POST /tiktokhyva/events/index` sent `X-Requested-With: XMLHttpRequest` — without it the
  M2.4.8 `CsrfValidator` 302-redirected the fetch (Invalid Form Key), so no endpoint event
  ever reached the pixel or the S2S queue.
- `ViewContent` now resolves the PDP product through the endpoint request (`product` param
  → `ProductRepository`); the catalog registry is per-request, so the AJAX-built event had
  no `contents`/`content_id` (TikTok Events Manager flagged >10% ViewContent missing
  content_id). Content-less `ViewContent` events are now skipped entirely.
