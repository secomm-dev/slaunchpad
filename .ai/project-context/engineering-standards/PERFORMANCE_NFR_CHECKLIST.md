# PERFORMANCE_NFR_CHECKLIST

> Engineering standard — Performance NFR (Non-Functional Requirements) checklist for Component & Extension Review. English.
> Used as a CI/PR gate before any component or extension is approved into the Launchpad Reusable Package.

---

## 1. Asset Discipline

| # | Check | Status | Rule & Technical Notes |
| :---: | :--- | :---: | :--- |
| **1.1** | Does the component reuse existing Alpine.js components/stores rather than re-declaring them? | `[ ] Yes / [ ] No / [ ] N-A` | **Mandatory**: Verify existing `Alpine.data()` or `Alpine.store()` registrations in the theme/module before adding new ones. No duplicate JS logic or state store re-declarations. |
| **1.2** | Are the component's JS/CSS assets processed exclusively through the **approved asset build pipeline of the active Hyvä theme version**? | `[ ] Yes / [ ] No / [ ] N-A` | **Version-aware rule**: Hyvä 3.x / Default Theme 1.5+ (Tailwind CSS v4) — all styles must be scanned/compiled via the `@source` directive in `tailwind-source.css`. **Do NOT load a second parallel CSS/JS bundle** (no additional Vite/Webpack bundle from the extension). |
| **1.3** | Does the component comply with the rule of NOT creating a separate `tailwind.config.js`? | `[ ] Yes / [ ] No / [ ] N-A` | Tailwind v4 uses CSS-first configuration. All theme customisation must be placed in the `@theme` block inside `tailwind-source.css`. |
| **1.4** | Are all dynamic CSS class names within the scope of the `@source` scan? | `[ ] Yes / [ ] No / [ ] N-A` | Avoid fully dynamic concatenated class strings that the Tailwind v4 scanner cannot see, which causes classes to be purged unexpectedly. |
| **1.5** | Does the component eliminate all unnecessary inline `<script>` blocks? | `[ ] Yes / [ ] No / [ ] N-A` | Avoid complex inline JavaScript inside `.phtml` files. Extract logic into a componentised Alpine.js component or a ViewModel/Magewire component. |
| **1.6** | Are any additional JS libraries loaded as native ES Modules only? | `[ ] Yes / [ ] No / [ ] N-A` | **Strictly forbidden**: loading RequireJS, jQuery, Knockout.js, or any legacy library into the Hyvä frontend. |

---

## 2. Cacheability & Full Page Cache (FPC) Strategy

> **Principle**: For every Block/Component with cacheable output, all three factors must be reviewed: **Cache Lifetime**, **Cache Key (Identity Dimensions)**, and **Invalidation/Tag Strategy**.

| # | Check | Status | Rule & Technical Notes |
| :---: | :--- | :---: | :--- |
| **2.1** | **Cache Lifetime**: Does the component/Block define an appropriate cache lifetime (`cache_lifetime`)? | `[ ] Yes / [ ] No / [ ] N-A` | Declare `getCacheLifetime()` or the layout attribute `cache_lifetime`. If the block is fully static or changes on cron/events, assign an explicit lifetime rather than leaving the default at 0. |
| **2.2** | **Cache Key Identity**: Does the Cache Key include sufficient identity dimensions to prevent data leakage across contexts? | `[ ] Yes / [ ] No / [ ] N-A` | **Mandatory minimum**: `getCacheKeyInfo()` must include Store ID, Customer Group ID, Currency, and Tax Rate. **Device Category (mobile/desktop) must only be added when the block genuinely renders different HTML at the PHP server-side layer** — do not add it by default, as Hyvä uses responsive CSS with shared HTML; adding it unconditionally doubles cache entries needlessly. Missing dimensions cause cross-customer data leaks. |
| **2.3** | **Invalidation & Tag Strategy**: Does the block declare an accurate Cache Tag list (`getIdentities()`)? | `[ ] Yes / [ ] No / [ ] N-A` | Must return specific entity tags (e.g. `[Product::CACHE_TAG . '_' . $productId]`, `[Category::CACHE_TAG . '_' . $categoryId]`). When an entity changes, only the relevant block's cache is flushed — not the entire FPC. |
| **2.4** | **No Session Data in FPC**: Does the FPC-cached Block strictly avoid reading Session/Customer/Cart data directly? | `[ ] Yes / [ ] No / [ ] N-A` | **Mandatory**: Never read `CustomerSession`, `CheckoutSession`, or personalised Header/Cookie data inside the `_toHtml()` method of a cached Block. |
| **2.5** | **Private Content Separation**: Is customer-specific (private/session-specific) content separated from the FPC layer? | `[ ] Yes / [ ] No / [ ] N-A` | Personal data (cart item count, customer name, wishlist count, ExtraFee) must be loaded asynchronously via **Private Content / Section Data (`customerData`)** or **Magewire/Alpine.js async fetch**. |
| **2.6** | Does the component's Layout XML avoid declaring `cacheable="false"` except as an absolute last resort? | `[ ] Yes / [ ] No / [ ] N-A` | Adding `cacheable="false"` to any block in a layout **disables FPC for the entire page**. Lead/SA approval is mandatory before use. |

---

## 3. Image & Layout Stability (CLS Optimisation)

| # | Check | Status | Rule & Technical Notes |
| :---: | :--- | :---: | :--- |
| **3.1** | **Lazy-Loading**: Does every `<img>` outside the above-the-fold area carry `loading="lazy"`? | `[ ] Yes / [ ] No / [ ] N-A` | Hero banners and the main product image on PDP (above-the-fold) must **NOT** use `loading="lazy"` — use `fetchpriority="high"` instead. All remaining images (PLP grid, product cards, footer, related items) are required to use `loading="lazy"`. |
| **3.2** | **Explicit Dimensions (CLS Protection)**: Does every `<img>` or its container declare `width` & `height` or `aspect-ratio`? | `[ ] Yes / [ ] No / [ ] N-A` | **Mandatory**: Declare explicit `width="..." height="..."` on the `<img>` tag or use Tailwind classes such as `aspect-square` or `aspect-[4/3]` on the wrapper, so the browser reserves rendering space and prevents Layout Shift (CLS) when the image loads. |
| **3.3** | **Responsive Images (`srcset` / `<picture>`)**: Are optimised image sizes provided per screen breakpoint? | `[ ] Yes / [ ] No / [ ] N-A` | Use `srcset` and `sizes` attributes or the `<picture>` element so mobile devices do not download desktop-sized images. |
| **3.4** | **Image Format Optimisation**: Are images using next-generation formats (WebP or AVIF)? | `[ ] Yes / [ ] No / [ ] N-A` | Prefer WebP/AVIF over uncompressed JPG/PNG. |

---

## 4. Third-Party JS & Extension Integration Audit

| # | Check | Status | Rule & Technical Notes |
| :---: | :--- | :---: | :--- |
| **4.1** | **Source Code Audit**: Has the third-party extension/library been source-audited before merge? | `[ ] Yes / [ ] No / [ ] N-A` | Verify: no malicious code, no synchronous SQL queries inside loops, no blocking external API calls on the PHP critical path. |
| **4.2** | **Render-Blocking Prevention**: Is third-party JS (Analytics, Tracking Pixels, Livechat, GTM) loaded `async` or `defer`? | `[ ] Yes / [ ] No / [ ] N-A` | Never place third-party scripts as render-blocking in `<head>`. Load via `defer`, `async`, or trigger lazily after first user interaction (scroll/mousemove). |
| **4.3** | **Duplicate Library Check**: Does the third-party extension avoid re-loading libraries already present in the stack? | `[ ] Yes / [ ] No / [ ] N-A` | Verify the extension does not inject Alpine.js, a Tailwind CSS bundle, FontAwesome, or jQuery into the page. |
| **4.4** | **Hyvä Compatibility Layer Check**: Does the Magento 2 extension (originally Luma-based) include a compatible Hyvä Compatibility Module? | `[ ] Yes / [ ] No / [ ] N-A` | If the extension ships PHTML/layout templates targeting Luma, a Hyvä Compat module that overrides them with Alpine.js/Tailwind equivalents is mandatory. |
