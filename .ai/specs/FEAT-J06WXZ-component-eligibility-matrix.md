# FEAT-J06WXZ — Hyvä UI Component Eligibility Matrix

## Metadata

| Field | Value |
|---|---|
| Feature | FEAT-J06WXZ — Secomm UI Widgets |
| Source | `hyva-themes/hyva-ui` 2.8.0 |
| Hyvä baseline | Default Theme 1.5.2, Tailwind v4 |
| Date | 2026-08-24 |
| Status | Approved with implementation plan — 2026-08-24 |

## Status legend

- **B1**: Batch 1 — content/manual collection; implement sau Banner A proof.
- **B2**: Batch 2 — catalog/context-backed; cần provider/cache/context adapter.
- **DEFER**: có thể trở thành feature/widget sau, nhưng không thuộc delivery hiện tại.
- **EXCLUDE**: layout/system replacement; không được expose trong `Secomm UI`.

`CMS JIT` trong upstream không tạo runtime dependency: markup sẽ nằm trong code-owned `.phtml` và được Tailwind build scan.

## Import fidelity contract

- Component nội bộ phải giữ layout và visual behaviour của Hyvä UI 2.8.0 source đã ghi ở từng row.
- Cho phép thay data source, validation/escaping, instance-safe semantics và loại bỏ demo fallback; không cho phép tự đổi spacing, positioning, responsive order, typography, overlay, animation hoặc interaction mặc định.
- Option an toàn trong Admin phải map về đúng upstream behaviour. Extension ngoài upstream chỉ được opt-in và mặc định không ảnh hưởng base appearance.
- Gate trước component mới: visual parity tại 390px, 768px và 1440px, cùng Admin save/reopen, Tailwind production build và accessibility state QA.

## Included — Batch 1

| Internal ID proposed | Hyvä UI source | Dynamic data/schema | Extra requirement | Status |
|---|---|---|---|---|
| `accordion_a` | `accordion/A-basic` | panels[]: title, rich text; open mode | details CSS; repeated items | B1 — implemented 2026-08-25 |
| `banner_a` | `banner/A-default` | title, subtitle, mobile/desktop image, CTA, alignment, colors, card/gradient | vertical slice đầu tiên | B1 — implemented 2026-08-24 |
| `banner_b` | `banner/B-split` | title, content, image, CTA, image side, colors | static enum class map | B1 — implemented 2026-08-25 |
| `banner_c` | `banner/C-text` | title, content, CTA, alignment/colors | no media required | B1 — implemented 2026-08-25 |
| `card_a` | `card/A-default` | title, body, mobile/desktop image, CTA, appearance | responsive picture; port CMS markup to template | B1 — implemented 2026-08-26 |
| `card_b` | `card/B-media` | title, body, image, CTA, image position | media chooser | B1 — implemented 2026-08-27 |
| `categories_a` | `categories/A-grid-images` | items[] manual: label, image, URL; slider toggle | repeated items | B1 — implemented 2026-08-27 |
| `categories_b` | `categories/B-grid-patterns` | items[] manual: label, URL, pattern/appearance | repeated items | B1 — implemented 2026-08-27 |
| `embed_a` | `embed/A-basic` | provider, video URL/id, title, aspect ratio, privacy options | URL/provider validation | B1 — implemented 2026-08-27 |
| `generic_content_a` | `generic-content/A-text` | heading, rich text, alignment/width | sanitizer contract required | B1 — implemented 2026-08-25 |
| `generic_content_b` | `generic-content/B-visual` | heading, body, media, CTA, layout | media chooser | B1 — implemented 2026-08-27 |
| `modal_a` | `modal/A-simple` | trigger label, title, body, close labels | `x-htmldialog`, unique IDs | B1 — implemented 2026-08-27 |
| `product_highlights_c` | `product-data/C-highlights` | highlights[] manual: title, text, image | content-only mode, no product context | B1 — implemented 2026-08-27 |
| `shortcuts_a` | `shortcuts/A-simple` | items[]: label, URL, icon/image | repeated items | B1 — implemented 2026-08-27 |
| `slider_a` | `slider/A-basic` | slides[]: media/content/CTA; arrows/dots/eager | `x-snap-slider`, repeated items | B1 — implemented 2026-08-27 |
| `slider_b` | `slider/B-marquee` | items[]: image/logo/link/alt; speed/direction | animation/accessibility controls | B1 — implemented 2026-08-27 |
| `testimonial_a` | `testimonial/A-simple` | quote, author, role, avatar/rating optional | semantic quote markup | B1 — implemented 2026-08-27 |
| `testimonial_b` | `testimonial/B-card` | quote, author, role, avatar/rating optional | semantic quote markup | B1 — implemented 2026-08-27 |
| `usp_a` | `usp/A-icons` | items[]: icon/image, title, text, URL optional | icon/media allowlist | B1 — implemented 2026-08-27 |
| `usp_b` | `usp/B-cards` | items[]: title, text, icon/image, CTA optional | repeated items | B1 — implemented 2026-08-27 |
| `usp_c` | `usp/C-compact` | items[]: icon, label/text | repeated items | B1 — implemented 2026-08-27 |

## Included conditionally — Batch 2

| Internal ID proposed | Hyvä UI source | Context/provider contract | Risk/dependency | Status |
|---|---|---|---|---|
| `category_grid_a` | adapted from `categories/A-grid-images` | manual category IDs; derive label, URL, image; retain selection order | store/category visibility + cache tags | B2 |
| `category_grid_b` | adapted from `categories/B-grid-patterns` | manual category IDs; derive label/URL; configured pattern | store/category visibility + cache tags | B2 |
| `map_a` | `map/A-default` | locations[] manual; lat/lng/address/marker content | Google Maps API key/config; CSP/privacy | B2 |
| `newsletter_popup_a` | `popup/A-newsletter-image` | title/body/image; trigger/delay/frequency; newsletter form | ReCaptcha, consent, customer state, unique IDs | B2 |
| `newsletter_popup_b` | `popup/B-newsletter-title` | title/body; trigger/delay/frequency; newsletter form | ReCaptcha, consent, customer state | B2 |
| `product_card_a` | `product-card/A-card-with-swatches` | one/manual product IDs; product view model/card renderer | wishlist/compare/reviews/swatches; cache identities | B2 |
| `product_slider_c` | `slider/C-product` | manual product IDs, selection order, limit, card template | batch load, `x-snap-slider`, cache identities | B2 |
| `product_specs_a` | `product-data/A-specs` | manual product ID; selected attribute set/codes | product context adapter; attribute output escaping | B2 |
| `product_accorditabs_b` | `product-data/B-accorditabs` | manual product ID; sections/attributes/reviews policy | review form/context + `x-collapse`; high coupling | B2/DEFER candidate |
| `product_reviews_a` | `product-reviews/A-basic` | manual product ID; review list/page size | review collection/form context + cache | B2/DEFER candidate |
| `product_reviews_b` | `product-reviews/B-minimal` | manual product ID; review summary/list policy | review context + cache | B2/DEFER candidate |

## Deferred or excluded inventory

| Hyvä UI source | Classification | Decision | Reason |
|---|---|---|---|
| `ajax-atc/A-simple` | system behaviour | EXCLUDE | replaces global add-to-cart integration, not CMS content |
| `breadcrumbs/A-simple` | page navigation | EXCLUDE | requires current page/catalog breadcrumb context |
| `buttons/A-basic` | design primitive | DEFER | CSS primitive, not standalone content widget |
| `category-filter/A-standard` | catalog page replacement | EXCLUDE | layered navigation context/layout replacement |
| `category-filter/B-elasticsuite` | catalog page replacement | EXCLUDE | Elasticsuite dependency and layered navigation context |
| `cookie-notice/A-full-width` | global compliance UI | EXCLUDE | global cookie configuration/state, not arbitrary CMS placement |
| `cookie-notice/B-overlay` | global compliance UI | EXCLUDE | global cookie configuration/state |
| `cookie-notice/C-simple-elegant` | global compliance UI | EXCLUDE | global cookie configuration/state |
| `error-page/A-simple` | page-specific content | DEFER | should be error-page implementation, not reusable arbitrary widget |
| `error-page/B-split` | page-specific content | DEFER | same as above |
| `footer/A-clean` | global layout replacement | EXCLUDE | replaces footer templates/assets |
| `footer/B-4-column-newsletter` | global layout replacement | EXCLUDE | multi-module footer layout replacement |
| `footer/C-mega` | global layout replacement | EXCLUDE | global footer architecture |
| `gallery/A-basic` | PDP replacement | EXCLUDE | product gallery layout/current product media context |
| `gallery/B-fancy` | PDP replacement | EXCLUDE | product gallery replacement |
| `gallery/C-grid` | PDP replacement | EXCLUDE | product gallery replacement |
| `gallery/D-splide` | PDP replacement | EXCLUDE | gallery replacement + Splide dependency |
| `header/A-clean` | global layout replacement | EXCLUDE | replaces header across modules |
| `header/B-compact` | global layout replacement | EXCLUDE | replaces header across modules |
| `header/C-stacked` | global layout replacement | EXCLUDE | replaces header across modules |
| `loaders/A-spinner` | UI primitive | DEFER | reusable primitive, not merchant content |
| `loaders/B-ping` | UI primitive | DEFER | reusable primitive, not merchant content |
| `loaders/C-dancers` | UI primitive | DEFER | reusable primitive, not merchant content |
| `menu-mobile/A-scroll` | global navigation replacement | EXCLUDE | header/menu/store/currency context |
| `menu-mobile/B-tabs` | global navigation replacement | EXCLUDE | global mobile menu replacement |
| `menu/A-simple-static-links` | global navigation replacement | EXCLUDE | global desktop navigation |
| `menu/B-4-column-megamenu` | global navigation replacement | EXCLUDE | global desktop navigation |
| `menu/C-vertical-dropdown-4-column` | global navigation replacement | EXCLUDE | global desktop navigation |
| `menu/D-shop-drowdown` | global navigation replacement | EXCLUDE | global desktop navigation |
| `minicart/A-classic` | global commerce UI | EXCLUDE | cart private content/current quote behaviour |
| `minicart/B-popover` | global commerce UI | EXCLUDE | cart private content/current quote behaviour |
| `notification/A-simple` | system messages replacement | EXCLUDE | Magento message queue/global layout |
| `notification/B-full-width` | system messages replacement | EXCLUDE | Magento message queue/global layout |
| `order-confirmation/A-clear` | checkout success replacement | EXCLUDE | order/customer context; checkout high-risk |
| `pagination/A-clean` | collection primitive | DEFER | needs collection/toolbar contract; not content alone |
| `scroll-to-top/A-simple` | global utility | EXCLUDE | page-global behaviour, not CMS content |
| `scroll-to-top/B-action` | global utility | EXCLUDE | page-global behaviour |
| `search-form/A-header` | global search UI | EXCLUDE | header/search provider context |
| `sticky-atc/A-simple` | PDP commerce behaviour | EXCLUDE | current product/cart configure context |
| `swatches/A-swatches-rounded` | design primitive | DEFER | CSS primitive consumed by product card, not widget itself |

## Batch gates

1. `banner_a` vertical slice passes before any other B1 implementation.
2. Repeated item codec passes PageBuilder/TinyMCE round-trip and payload-limit tests before accordion/slider/USP/categories.
3. Manual catalog provider/cache contract passes before any B2 product/category component.
4. `map_a` requires separate confirmation that Google Maps API/CSP/privacy setup is available.
5. Newsletter popup requires ReCaptcha/consent/frequency behaviour review; it must not reuse checkout/customer private data unsafely.
6. Product accorditabs/reviews may be deferred after provider spike if product-page coupling cannot be isolated cleanly.
