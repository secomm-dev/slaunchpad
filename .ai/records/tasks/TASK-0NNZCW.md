---
id: TASK-0NNZCW
type: task
title: 'Homepage Layout Mock-up — build homepage theo design image upload (SLP-213)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-213
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review  # v4.8 09-21: column-line fix + final verify trên content admin-saved — chờ TL review
created: 2026-09-17
updated: 2026-09-21
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only theme-layer homepage mock-up — không chạm §12 (payment/checkout/order/DB/security); CMS page `home` hiện rỗng nên thay đổi chỉ ảnh hưởng trang chủ
components:
  - app/design/frontend/Secomm/launchpad
source_areas:
  - hyva-theme-frontend
  - cms-home-page
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-17
supersedes: []
---

# [SLP][TASK-0NNZCW] Homepage Layout Mock-up — build homepage theo design image upload (SLP-213)

<!-- External ticket: SLP-213. Build homepage theo design mock-up upload (desktop 1440 + mobile 375). Header KHÔNG implement. Note ticket: home page full width, cần restyle product widget, slider full width và overflow. Homepage CMS `home` hiện rỗng (verify DB 09-17) → build từ đầu ở theme layer. -->

## Ticket + AC

[SLP-213] Homepage Layout Mock-up — Create homepage by design image upload. Component not need to implement: Header. Note: home page is full width, need restyle product widget, slider full width and overflow.

- **AC-1**: Homepage render đủ 10 section theo đúng thứ tự design — (1) hero slider "Made for Living" 6 dots; (2) Category carousel tiles; (3) Flash sale + countdown + product slider + CTA "Explore all"; (4) "Everyday more value" banner slider; (5) "Living, Reimagined" split image + text + CTA; (6) 3 product carousel Living Room / Bedroom / Accessories + CTA "Go to Collection"; (7) REAL SPACES video block; (8) "New Sofa for Autumn" banner full-width; (9) "Why us?" 4 USP cards; (10) Journal 3 cards + CTA "View all post" — khớp layout desktop 1440 và mobile 375, không vỡ.
- **AC-2**: Header + footer giữ nguyên hiện trạng (ticket loại Header; footer + newsletter thuộc footer hiện trạng — out of scope).
- **AC-3**: Hero slider full-bleed chiếm trọn viewport width (không bị container max-width chặn), 6 dots, dot active + điều hướng hoạt động.
- **AC-4**: Các slider (category, flash sale, product carousels, value banners) full width + overflow: track tràn ra ngoài container đến mép phải viewport (peek item kế tiếp), scroll-snap ngang; body không bị overflow-x (không scroll ngang trang).
- **AC-5**: Product card (widget restyle) khớp design: badge góc trên trái, wishlist heart góc trên phải, ảnh, BRAND, Product name, giá + giá gốc gạch + badge "-20%" nền vàng, swatch dots, dòng "In stock" (dot xanh), rating ★4.5 (xxxx) — content placeholder.
- **AC-6**: Flash sale countdown "Sale ending in HH:MM:SS" đếm ngược client-side (Alpine), không gây layout shift.
- **AC-7**: i18n BR-001 — mọi chuỗi visible mới qua `__()`, có mặt ở cả `vi_VN.csv` + `en_US.csv` (en identity); store vi render VI, store en render EN.
- **AC-8**: Không regression — layout mới chỉ load trên `cms_index_index`; các trang khác (PLP/PDP/cart/checkout) không đụng; quickview modal trên homepage vẫn wired (block `quickview.modal` giữ nguyên).
- **AC-9**: Console sạch (0 JS error mới); Tailwind build thành công.

## Mini Spec

### Goal
Homepage trắng hoàn toàn (CMS `home` rỗng) — dựng layout mock-up khớp design image: 10 section, full-bleed, product card mới theo design, slider full width + overflow. Placeholder-driven (design dùng "Product name", "BRAND NAME", "xx,xxx xxXd") — cấu trúc template sẵn sàng wire data thật sau.

### Expected Behavior
Trang chủ store (mặc định + en) render 10 section đúng thứ tự, full-bleed, responsive 1440/375; slider cuộn ngang scroll-snap với overflow peek; countdown client-side; mọi text theo store locale qua theme dict.

### Constraints / Rules
- Tailwind v4 CSS-first — `@theme`/`@source` trong `tailwind-source.css`, KHÔNG tạo `tailwind.config.js`; class Tailwind viết static trong template (tránh dynamic concat bị purge — §7.2)
- Alpine.js + x-defer bắt buộc trên x-data (LL-0027 — MutationObserver OFF trong theme); không jQuery/RequireJS
- Placeholder = inline SVG data-URI / CSS gradient — không thêm binary asset
- Không hardcode store data (giá/link menu) — placeholder text đúng như design
- Chuỗi mới vào cả 2 CSV `i18n/{vi_VN,en_US}.csv` (wording VI draft — chờ TL duyệt)
- Chỉ theme layer `Secomm/launchpad`; không sửa `Secomm_UiWidget` module (contract/ID/schema module-owned — §04); không đụng vendor/Mageplaza
- Không commit/push (quy định project); CLI chạy as `secomm` (LL shell-trap); static artifact cp tay sang `pub/static` sau build (F3 quick-deploy)
- Alpine countdown phải idempotent-safe và không layout-shift (fixed-width tabular)

### Out of Scope
- Header (per ticket) + footer/newsletter (giữ hiện trạng)
- Wiring data thật: category/product/brand/manufacturer, blog Magefan, ảnh/video thương hiệu, quickview trigger trên card mock-up (sku placeholder không fetch được — follow-up khi có data)
- Store data Admin (swatch `color` rỗng trong DB — hiện tại card render swatch placeholder)
- `launchpad_fashion` variant theme

## Update 09-17 (user feedback 3): v3.1 — banner style trong CMS content, product style trong code

User chỉ thị: banner styling nằm trong CMS content (inline styles — chỉnh per-element trong PageBuilder), product widget CSS giữ trong code.
- Content re-seed (script `pb-seed-v31.php` trong evidence): hero slides + 4 value banners + sofa banner chuyển toàn bộ typography/màu/padding sang **inline styles** trong content; bỏ class `hp-banner-*`/`hp-hero-*`/`hp-btn-light` khỏi CSS (đã xóa).
- Product widget giữ code: `hp-pb-products/slider/track/pager/marker` + card `hp-card-*` (item.phtml).
- **F9**: pub/static styles.css materialized stale (regular file) che bundle mới — rm để dev symlink lại; thêm vào chuỗi LL-0015/LL-0029.
- Verify: vi 1440 + vi 375 = **11/11 PASS**; screenshot desktop v3.1 clean (banner copy đáy banner, USP/journal/category đúng).

## Update 09-17 (user feedback 2): approach v3 — homepage = PageBuilder content, editable trong Admin

User chỉ thị: content homepage phải sửa được trong **Content > Pages > Home Page**, banner widget **kéo thả** trong PageBuilder, không hard code layout.

Đã verify PageBuilder trên Hyvä stack này (vendor `hyva-themes/magento2-default-theme/Magento_PageBuilder`):
- `[data-content-type="products"][data-appearance="carousel"]` + `[data-content-type="slider"]` **tự chuyển snap-slider** qua `widgets/carousel.phtml` (blockOutputPatternMap inject theo regex — slider + products carousel OK, không slick/RequireJS).
- Product card trong PB widget = `product_list_item` (handle `catalog_list_item` add toàn cục) → **card restyled item.phtml (v2) tự áp cho PB products widget**.
- Banner/slide show-on-hover, buttons, tabs, parallax, background lazy — có widget override đủ.

### Kiến trúc v3
1. `cms_index_index.xml` **bỏ toàn bộ block section hardcode** — chỉ giữ: remove demo Hyvä (`hero`/`slider-1`/`slider-2`) + block `quickview.modal`.
2. Page `home` có **PageBuilder master format content** (seed bằng script, backup content cũ vào evidence; admin edit tiếp bằng kéo thả):
   - Hero = PB **slider** (6 slides, gradient placeholder đến khi có asset)
   - "Everyday more value" = row 4 **banner** PB (nền xanh, kéo thả được — đúng yêu cầu user)
   - "New Sofa for Autumn" = PB **banner** poster full-width
   - Flash sale + 3 carousel = PB **products** (appearance carousel, conditions per category — không heading trong widget, heading = text PB)
   - Category tiles / countdown flash sale / Living Reimagined / REAL SPACES / Why us / Journal = PB **text/HTML** content (chỉ dùng class hp-* đã compiled trong homepage.css — **classes Tailwind utilities trong DB content không được @source scan, sẽ bị purge nếu dùng**)
3. CSS: homepage.css thêm styling cho PB content types scoped `body.cms-index-index` (slider height/dots, banner, products track gutter).
4. **Hạn chế CMS (nêu rõ)**: `cms_page.content` là **một giá trị chung** cho mọi store view (schema, verify DB) → content seed bằng tiếng VI (BR-001 primary); EN store view hiển thị cùng content — per-store CMS content cần module riêng (follow-up). i18n keys heading từ v2 không còn render qua `__()` (giữ trong CSV, vô hại).
5. full-bleed rows cần CSS override `.columns` (giữ từ v1) + PB row appearance `full-bleed`/`full-width` (page-builder.css có sẵn).

## Update 09-17 (user feedback): approach v2 — product card = restyle product widget PageBuilder

User chỉ thị: "product là style lại từ product widget của Magento PageBuilder". Cơ chế đã verify trong code:
- PageBuilder Products content type sinh widget directive `Magento\CatalogWidget\Block\Product\ProductsList` (carousel appearance qua `Magento_PageBuilder::catalog/product/widget/content/carousel.phtml`).
- Trên Hyvä, card của widget ĐỒNG NHẤT với PLP: cả hai render qua `Hyva\Theme\ViewModel\ProductListItem::getItemHtml()` → block **`product_list_item`** → template `Magento_Catalog::product/list/item.phtml` (theme đang override — có quickview trigger TASK-Z3DAH5).
- **Restyle đúng 1 template `item.phtml`** = card mới áp cho PLP + PageBuilder/CatalogWidget + homepage.
- Homepage sections (flash sale + 3 carousel) thay placeholder bằng **ProductsList blocks thật** (child blocks, conditions per category — `conditions_encoded` sinh bằng `Magento\Widget\Helper\Conditions::encode`), items render qua `ProductListItem::getItemHtml` (đúng pipeline widget). Placeholder partial `product-card.phtml` bỏ.

### Approach v2 (chi tiết trong plan §addendum)

1. Restyle `Magento_Catalog::product/list/item.phtml` theo design card: badge overlay (discount % / "New"), wishlist heart overlay, brand (manufacturer), price + chip -XX% vàng, swatches (details renderers có sẵn), "In stock" dot, rating cuối card; ATC/quickview/compare = overlay hover-reveal (mobile luôn hiện) — **giữ nguyên mọi JS hook** (form product_addtocart_form, price-box events, initWishlist, open-quickview dispatch).
2. Layout homepage: 4 block `ProductsList` (conditions category tạm: Flash sale→46 Lighting; Living Room→42,43; Bedroom→49; Accessories→47,60 — **map chờ PM chốt**).
3. Xóa `product-card.phtml` placeholder + 3 key placeholder CSV (`badge`, `BRAND NAME`, `Product name...`).
4. Verify mở rộng: PLP regression (card restyle ảnh hưởng PLP!) + quickview trigger + ATC form + swatch area.

## Approach v1 (placeholder — superseded, giữ để trace)

1. Layout `Magento_Cms/layout/cms_index_index.xml`: container `homepage.content` + 10 block template `Magento_Theme::html/homepage/*.phtml` (giữ nguyên block `quickview.modal`).
2. Template partial `homepage/product-card.phtml` dùng chung cho flash sale + 3 carousel — data qua `$cardData` array placeholder.
3. CSS mới `web/tailwind/theme/homepage.css` import từ `tailwind-source.css`: full-bleed section (`w-full` + inner container), slider breakout (track `overflow-x-auto` + scroll-snap, margin âm phải), palette design tokens.
4. Alpine: slider điều hướng (scrollBy + dots), countdown `HH:MM:SS` — tất cả kèm `x-defer` hoặc init sau `DOMContentLoaded` an toàn.
5. i18n 2 CSV; build tailwind (`npm run build` as secomm) + cp artifact + `cache:flush`; Playwright verify vi/en × 1440/375 + evidence + estimation row.

## Update 09-18 (user feedback): v4 — build theo Figma LAUNCHPAD CORE (desktop 1440 + mobile 375)

User chỉ thị: implement tiếp từ nửa đã làm (content v4 seed theo Figma `5MpBw9VaNgFdct3UDWABuV`, desktop 2151:17103 / mobile 2151:16903); hero + product widget = slider; flash sale widget được phép thêm option; restyle product widget theo design; REAL SPACES = video embed (YouTube `6HIi9IzqoNM`); Journal = blog slider (mobile slider; 3 post đã tạo); tạo thêm newsletter block; **mọi CSS trong PageBuilder viết qua CSS Classes để Tailwind JIT build**; custom PHP viết vào **module `Launchpad`**.

1. Module mới **`Launchpad_Homepage`**: widget `FlashSaleList` (extends CatalogWidget ProductsList; option thêm `sale_end` store-time, rỗng = nửa đêm kế tiếp; template countdown "Sale ending in" + snap-slider dots-only) — flash sale seed qua PB html element `{{widget type=...}}`; block `Blog\Journal` (Magefan posts, template slider mobile/3-up desktop, seed qua `{{block}}`); data patch tạo CMS block `homepage-newsletter` (form subscribe, id 21).
2. Theme: `item.phtml` restyle theo design card (badge "New arrival" xanh `#588f60`, chip -% đỏ `#c10007` tại price row, brand `#99a1af`, name 16→18 medium, meta row stock+rating 1 hàng, **Quick view = outline button đáy card**; bỏ quickview khỏi hover overlay — ATC/compare giữ overlay hover-reveal); PB `carousel.phtml` thêm nav arrows `[data-page-builder-slider-nav]` (desktop, absolute top-right ngang hàng heading; mobile swipe+dots) + dọn safelist-comment legacy; `homepage.css` thêm lp-* v4 + design tokens; `tailwind-source.css` `@source "./safelist/**/*.html"`; safelist `launchpad-cms.html` GENERATED từ DB (212 class) — cơ chế chính thức cho class trong CMS content (F9).
3. CMS content **v5**: flash = FlashSaleList widget; 3 rails = PB products **carousel** (8 items, data-show-dots/arrows); REAL SPACES = PB **video element** (iframe YouTube); Journal = Launchpad blog block; newsletter band + block 21; hero show-arrows=false, slides 2-3 trỏ ảnh có sẵn (chi tiết: `evidence/TASK-0NNZCW/RESULTS.md` §v4 — findings F9–F14, limitations: 3/6 slides, content EN shared, brand data rỗng, admin re-save có thể strip data-show-* attrs).
4. Verify: Playwright vi 1440 + 375 = 19/20 PASS mỗi suite (FAIL duy nhất data-dependent — first card thiếu manufacturer/out-of-stock; aggregate 32 card structural 32/32); regression search + category-4 PLP OK; console sạch; body không overflow-x.

## Findings

- (09-17) CMS page `home` content rỗng; `cms_home_page = home`; 0 widget instance Secomm_UiWidget đang gắn home. Quickview modal đã wired trên `cms_index_index.xml` từ TASK-Z3DAH5 — giữ nguyên.
- (09-17) `color` attribute (id 93) `frontend_input=select` nhưng `eav_attribute_swatch` rỗng → swatch trên card mock-up = placeholder tĩnh.
- (09-17) `pub/media/banner/` (untracked trong git status) chỉ chứa 2 icon cũ (appliances.png…) không liên quan — không dùng làm asset homepage.
- **(09-17) F1 — Hyvä default theme ships demo homepage**: `vendor/hyva-themes/magento2-default-theme/Magento_Theme/layout/cms_index_index.xml` tự render block `hero` + `slider-1` + `slider-2` (demo "Hyvä Theme"/"Popular Products") trên homepage — gate bằng config `hyva_theme_general/demo_content/show_homepage_demo_content`. Child theme chưa từng remove → homepage cũ = demo content. Đã remove bằng `referenceBlock remove` trong cms_index_index child theme (code-side, scoped homepage; không đụng config). Homepage "cũ" trước task này = các block demo này.
- **(09-17) F2 — `<attribute name="class">` trên referenceContainer không ăn** với main.content của Hyvä (Hyva render main markup riêng) — dùng CSS override scoped `body.cms-index-index` thay thế.
- **(09-17) F3 — `full_page` cache BẬT trong local env này (dù MAGE_MODE=developer)**: template fix không ăn khi curl vì FPC serve HTML stale → `cache:flush`/tắt full_page khi verify loop; đã enable lại as-found. (Mở rộng LL-0015 F3 static-stale.)
- **(09-17) F4 — snap-track là `@layer components` class**, không `@apply` được trong Tailwind v4 (chỉ `@utility` apply được) → dùng cả 2 class trên markup (`snap-track hp-track ...`).
- **(09-17) F5 (v2) — `ProductsList::getProductCollection()` trả null nếu block chưa render**: collection được build trong `_beforeToHtml()` — từ template cha phải gọi `createCollection()` (public) thay vì `getProductCollection()`.
- **(09-17) F6 (v2) — conditions `category_ids` với `aggregator=any` (multi-category OR) trả 0 items**; single-category `all` hoạt động → 4 section dùng 1 category mỗi section (map tạm: Flash sale→46 Lighting, Living Room→42 Seating, Bedroom→49 Beds, Accessories→60 Tableware). Multi-category = follow-up (debug rule filter hoặc subquery). **Map category là placeholder — chờ PM chốt section→category.**
- **(09-17) F7 (v2) — furniture categories 42/43/46/49/60 trả 404 qua URL nav** (không active/không gán store 1) — store data pre-existing; homepage vẫn render products vì conditions filter collection-level.
- **(09-17) F8 (v2) — restyle `item.phtml` ảnh hưởng TOÀN BỘ card product-list sitewide** (PLP, PDP related/upsell/crosssell, CatalogWidget/PageBuilder products) — regression đã chạy: PLP 12 cards đủ hooks + ATC form + quickview + console sạch; PDP related 3 cards; **QC phải cover thêm list-mode PLP + swatch configurable + Monsoon ATC trên card mới**.

## Evidence

- `.ai/evidence/TASK-0NNZCW/RESULTS.md` — Playwright vi 1440/375 **11/11 PASS mỗi suite**, en 10/11 (AC-7 live-block env pre-existing store 2 locale=vi_VN — TASK-K14RVZ; bù dict-level: 52 key identity EN + VI bản dịch, 0 dup mới).
- Screenshots full page: `home-vi-1440-full.png`, `home-vi-375-full.png` (v3 — PageBuilder content, sản phẩm thật).
- **v3**: seed content script `/tmp/hp-pb-seed.php` (session ephemeral — nội dung content sống trong DB; backup cũ `home-content-before-v3.txt`); PB carousel override `Magento_PageBuilder/templates/catalog/product/widget/content/carousel.phtml` (items qua ProductListItem — giữ contract root/slider attrs cho Hyva PB carousel widget); 10 template homepage v1/v2 ĐÃ XÓA (content chuyển vào DB); dead CSS v1 dọn (hp-track*, hp-ph-*, hp-nav-btn).
- **Verify v3**: vi 1440 + vi 375 = **11/11 PASS mỗi suite** (sections order, hero slider full-bleed 6 slides, tracks overflow peek, card anatomy đủ hooks, countdown, VI content, quickview, console sạch); PLP regression PASS (12 cards + PDP related 3).
- **QC bắt buộc**: mở Admin Content > Pages > Home Page — PageBuilder stage phải load + banner/products kéo thả được (session này không login admin được).
- Regression: PLP/PDP/cart HTTP 200, 0 class `hp-*` leak; full_page cache restored as-found.
- Verify script: `/tmp/hp-verify.js` (session ephemeral — copy khi cần từ evidence RESULTS mô tả).

## Update 09-18 (user feedback): v4.1–v4.2 — position fixes + 6 chỉnh sửa slider/section

- v4.1: hero copy bottom-left + dots đúng Figma (unlayered CSS + `@layer base` important — F16); newsletter căn giữa.
- v4.2: category mobile slider thật (F15 — bỏ `!flex` qua module JIT bug); hero autoplay (module `hero-autoplay.phtml`, lazy track query — F17) + slide fixed height; product mobile đúng 2/view; promo mobile slider + desktop rail 360×502 bleed; USP mobile gap 12px; flash desktop peek 4.1; rails spacing 24px. Verify 10/10 PASS (1440 + 375), chi tiết `evidence/TASK-0NNZCW/RESULTS.md` §v4.1–v4.2.

## Update 09-18 (user feedback): v4.3 — audit !-class, newsletter, card options + gallery slider

- **!-audit**: 0 broken `!hcms-*` token trên live HTML (fix `!flex` từ v4.2; phần còn lại do safelist + module JIT CSS lo). Token `!`-mới thêm vào content PHẢI kiểm tra broken-form `!hcms-page-2-*` sau khi render.
- **Newsletter**: v5.1 seed từng dính placeholder `__NEWSLETTER_BLOCK_ID__` chưa replace → widget rỗng. Đã thay hẳn bằng `{{block}}` module `Launchpad\Homepage\Block\Newsletter\Subscribe` ( getUrl đúng, i18n, không dính widget-usage-map). CMS block `homepage-newsletter` (id 21) giữ lại cho Admin.
- **Card**: bỏ compare; configurable options render qua pipeline Hyvä mặc định (catalog_list_item handle + swatch renderer) — style theo design (48×32 box / 32px dot); **ảnh card = gallery slider** (tối đa 4 ảnh + mini dots, ẩn khi 1 ảnh).
- **BUG Elasticsuite (F18 — quan trọng)**: `addMediaGalleryData()` gọi trong `createCollection()` → Elasticsuite virtual-category around-plugin reload collection SAU method → mất page-limit + conditions (8→162 items, homepage 648 card/14MB). Fix: batch gallery trong TEMPLATE (flash-sale.phtml + PB carousel.phtml) SAU khi createCollection + plugin chạy xong. `createCollection` override đã bỏ — FlashSaleList giờ chỉ còn mục đích `sale_end`.
- Verify: homepage 32 card, PLP cat 15 (configurable) 96 swatch + 12 slider, console sạch, mobile 10/10.

## Update 09-18 (user feedback): v4.4 — dot progress + sửa vỡ CSS sau admin save

- Admin save PageBuilder STRIP custom classes trên structural elements (row inner/column-group/slide wrapper/overlay) + drop iframe video (backup: `home-content-after-admin-save.txt`). Re-seed v5.4: video → PB **html element** `.lp-video` (save-proof); hero slide/overlay **CSS-owned** qua selector cấu trúc (height/bg/cover/flex-column/min-height/padding-inline) — save sau này không phá hero.
- **Dot progress-fill story-style**: active dot fill trái→phải theo autoplay-speed (`lp-dot-fill` + `--lp-dot-duration`), advance on `animationend`, pause hover/tab, re-arm trên `slideChange`; reduced-motion tắt autoplay.
- Script tái sử dụng `evidence/TASK-0NNZCW/reseed-homepage.php` (re-seed + regen safelist) — workflow sau admin save: reseed (nếu vỡ group) → npm build → flush.
- Verify: 9/9 PASS desktop (hero 732/40 save-proof, dot fill + advance, video, 32 card, newsletter, console sạch) + regression 10/10 + mobile.

## Update 09-18 (user feedback): v4.5 — STRIP-PROOF architecture (chấm dứt vòng lặp vỡ CSS)

Re-seed v5.4 bị save admin đè (tab admin giữ content cũ) → vỡ lần 2. Chuyển kiến trúc: **toàn bộ section styling = CSS-owned** qua structural selectors (`data-content-type`/`data-appearance` + `:has()` feature còn sống — module markup, column classes); video = `{{block class="Launchpad\Homepage\Block\Video"}}` (directive + module template, không thể bị strip). Bằng chứng: mô phỏng strip toàn bộ class structural → render vẫn đúng 100% (hero/tiles/USP/split/video/newsletter/32 card). Admin save PageBuilder không còn phá homepage.

## Update 09-21 (user feedback + design screenshot): v4.9.7 — Category bỏ PB column thành HTML slider; 3 section bleed

User (kèm design screenshot): Category slider KHÔNG dùng PB column — slider tràn ra ngoài; 3 section (Category / Flash sale / Everyday more value) đều tràn product ra mép phải viewport.

1. **Category section tái cấu trúc content**: thay column-group 6 columns bằng **1 PB html element** (`lp-cat-slider` + `data-lp-tiles-slider` + `data-track` chứa 8 `lp-cat-tile` — 6 category thật + 2 lặp placeholder như design; ảnh/label trích từ columns cũ `cat-*.png`). Script `replace-cat-slider-v497.php` (backup `home-content-before-cat-slider.txt`, chỉ thay group, giữ heading + phần còn lại). Override CSS card slider mới `.lp-cat-*`; SnapSlider init qua `[data-lp-tiles-slider]` (sliders-init); dots mobile-only (ẩn ≥64rem — desktop không dot như design).
2. **Dọn CSS cũ**: bỏ block `row:has(.lp-tile)` (grid/flex cũ) + `.lp-tiles-dots` + JS tiles-dots v4.9.6 (superseded).
3. **Promo bleed lại** (revert phần v4.9.2): holder `margin-inline-end: calc(50% - 50vw)` — item 4 clip tại mép viewport (1440) như design screenshot.
4. **Root cause mới phát hiện**: pb-style save 08:32 render flash qua `hp-pb-slider/hp-pb-track` (cùng class rails) → (a) `width:100%` của flex-order rule + (b) rule "rails không bleed" v4.9.3 đều giết flash bleed. Fix: `width:auto` cho track bleed + tách flash bằng `.lp-flash` (class trên row, sống qua save): `#html-body .lp-flash .lp-pb-track { margin-inline-end: calc(50%-50vw); padding-inline: 0 2.5rem }` + `width:auto`.

Verify (375 + 1440, `verify-v497-three-bleeds.js`): cat track 8 tiles ×172, bleed tới viewport (r≈1433/368) ✓; dots 8 markers mobile visible / desktop hidden ✓, dot click scroll ✓; promo bleed + cards 360 ✓; flash bleed ✓; không h-scroll; 0 pageerror. Screenshots `sec-{cat,promo,flash}-1440.png`. **Cảnh báo: user đang edit Admin song song (3 lần save trong ngày 04:50/06:33/08:32) — nếu tab Admin giữ content cũ mà save thì replacement cat-slider bị ghi đè → chạy lại script.**

## Update 09-21 (user feedback): v4.9.6 — Category slider: mobile có dot, desktop không

User chỉ rõ + check design Figma mobile frame (2151:16903): Category (2151:16914) = **Category Tiles List slider ngang** (6 tiles × 172px, tràn viewport 375) + **pagination dots** (2151:16924, 116×32 centered) bên dưới; desktop = grid tĩnh không dot.

- **Dots tiles**: SnapSlider không dùng được (holder column-line không có `[data-track]` con) → viết dots tối giản trong `sliders-init.phtml` (`.lp-tiles-dots`): 1 dot/tile, click → `holder.scrollTo` cục bộ (không scrollIntoView — tránh trượt ancestor), sync `aria-current` theo scroll; **ẩn ≥64rem** (desktop grid tĩnh, không dot).
- **Tiles mobile co rút**: `!w-[172px]` trong content không được safelist compile → flex shrink tiles vừa hết container, không overflow → thêm `width:172px !important` cho tiles **<64rem** (desktop grid tự chia như cũ). Sau fix: 375 = 2 tiles visible + peek, overflow ✓ slider.
- Verify (`verify-v496-tiles-dots.js`): 375 — 6 tiles overflow, 6 dots dưới tiles (active 24px pill #588f60, inactive 8px #a9ccae), click dot 3 → scroll 0→540 + active sync ✓; 768 — slider + dots ✓; 1440 — grid 6 tiles, dots ẩn ✓; 0 pageerror.

## Update 09-21 (user feedback): v4.9.5 — mini-dots gallery card chỉ có ở flash sale, thiếu ở 3 rails

User report: dot image slider của product chỉ apply ở flash sale, 3 product widget dưới chưa. Root cause: PB carousel script (vendor `widgets/carousel.phtml`) → `new SnapSlider(slider)` với `slider.el.querySelector("[data-pager]")` quét **toàn bộ hậu duệ** của rail root — nhặt nhầm pager mini-dot của CARD ĐẦU TIÊN (tạo trước bởi sliders-init, cùng DOMContentLoaded) → `setupPager()` rebuild nó thành 8 markers của rail → mini-dots card đầu biến mất, pager rail nằm sai chỗ (trong card gallery). Flash sale KHÔNG qua PB script nên không lỗi.

Fix ([carousel.phtml override](../../../app/design/frontend/Secomm/launchpad/Magento_PageBuilder/templates/catalog/product/widget/content/carousel.phtml) + homepage.css): thêm `<nav data-pager>` RỖNG **TRƯỚC `[data-track]`** trong rail root — document-order query nhạt nav này đầu tiên → SnapSlider đổ 8 markers vào đó (thay vì xây dựng lại (rebuild) pager card); hiển thị xuống dưới cards bằng `flex order` (`.hp-pb-slider` flex column, track order 1, pager order 2 — nav ĐẶT TRƯỚC track trong DOM nhưng HIỂN THỊ sau). Verify: rail pager 8 markers (grouped hiển thị 2 chấm = 2 trang × 4 sản phẩm) dưới track ✓; cả 3 rail + flash: card gallery own pager 2 mini-dots "snap-marker" ✓; bấm mini-dot card → inner trượt, outer KHÔNG ✓; không có thanh cuộn ngang (no h-scroll), 0 lỗi (0 error).

## Update 09-21 (user feedback): v4.9.4 — dot hero: active màu khác + fill tự chạy

User report: dot hero không có active màu riêng, fill không chạy. 2 root cause:

1. **CSS animation fill bị cancel/restart liên tục**: SnapSlider dispatch `slideChange` ở MỌI IntersectionObserver tick (`handleInView`) — không chỉ khi đổi slide → `arm()` chạy ~mỗi vài trăm ms → remove/add `lp-dot-run` → animation `lp-dot-fill` restart, kẹt scaleX(0). Instrument (`getAnimations`) chốt: animation biến mất ~300ms sau arm dù class còn. **Fix**: fill chuyển sang **rAF** — `hero-autoplay.phtml` ghi `--lp-dot-progress` (0→1) lên marker, CSS `::after` chỉ `transform: scaleX(var(--lp-dot-progress))`; `arm()` chỉ re-arm khi active marker ĐỔI (dedupe `armedMarker`); advance trigger đổi từ `animationend` sang event tự phát `lp-dot-complete`. Pause hover/tab giữ nguyên qua JS (bỏ rule animation-play-state). Keyframes `lp-dot-fill` xóa (dead).
2. **Marker hero không có class `hp-pb-marker`** (chỉ `.snap-marker` — vendor widget không set data-marker-class cho slider) nên không ăn rule active pill của rails → thêm style riêng scoped `[data-content-type="slider"]`: inactive = chấm trắng mờ 8px `rgba(255,255,255,.55)`, active = pill xanh #588f60 24px; pager `position: absolute` đáy hero giữa (bottom 1.5rem, slider position:relative) + `pointer-events:none`/markers auto.

Verify (`verify-v494-dot-fill.js`): fill progression 0.14→0.35→0.57→0.78→0.99 → advance slide 2 → 0.11→…→0.97 (2 chu kỳ liền, không kẹt); active pill light-green base + fill #588f60 sweep; inactive trắng mờ; slide advance + wrap hoạt động; 0 pageerror. Screenshots `dots-zoom-{mid,late}.png`. **Bài học probe**: parse `matrix(...)` scaleX phải lấy thành phần ĐẦU (slice(7)) — slice(lastIndexOf(',')) đọc nhầm translateY=0 → chẩn đoán sai "kẹt 0" thêm 1 vòng.

## Update 09-21 (user feedback): v4.9.3 — container cố định 1360 + chỉ flash sale tràn

User chốt model cuối: màn 1440 hiện như design (content **1360px** = 1440 − 2×40, KHÔNG phải 1200/1456); màn lớn hơn — fullwidth vẫn full, contained **cố định**; **chỉ flash sale** tràn product ra viewport, rails giữ 4 products/slide.

- Generic: mọi row `contained` → `max-width: 90rem (1440 = 1360+2×40) + margin auto` — đè cap 1536 của PB lẫn pb-style 1280 per-row (save 06:33). Promo đổi 96rem → 90rem theo model chung.
- Rails (`.lp-pb-track`): bỏ bleed `margin-inline-end: calc(50% - 50vw)` ở ≥64rem (mobile giữ edge-to-edge swipe); **revert cols tăng v4.9.1** (4.75/5.15 — container cố định rồi nên card không phóng, không cần breakpoint); cols = 4 → card ~318-322px mọi màn.
- Flash (`.lp-flash-track`): giữ bleed + cols 4.1/4.85/5.25 theo viewport → card ~300-325 mọi màn.
- Verify (1440/1680/1920, `verify-v493-container-1360.js`): contained rows = viewport@1440 / **1440 cố định** @1680/1920 centered; rail track l=40/153/273, r=cóng container, KHÔNG bleed, card 318/322/322; flash track bleed tới viewport (r≈viewport−scrollbar) ✓; hero 1425/1665/1905 full ✓; promo clip tại container ✓; mobile 375 không h-scroll + rails vẫn swipe tràn (design mobile) ✓; 0 pageerror. Screenshot `hp-v493-1920-full.png`.

**F9 lần 4 (mechanism chốt)**: dev-mode materialize copy pub/static tại request ĐẦU TIÊN sau khi file missing — các build sau KHÔNG cập nhật copy đó → served stale ngầm. **Quy trình bắt buộc sau mỗi `npm run build`:** `cp web/css/styles.css pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/styles.css` (hoặc rm + verify served hash). Lần này verify hash served vs source mới phát hiện (rule mới không ở served CSS dù build OK).

## Update 09-21 (user feedback ×3): v4.9.2 — gallery dot isolation + promo clip tại container + split full width

Figma reconnect được (MCP `get_screenshot`, fileKey `5MpBw9VaNgFdct3UDWABuV`, node `2151:17164` = Living, Reimagined design 1439px) — design xác nhận: ảnh tràn **sát mép trái viewport**, cột text phải, section full width. Lưu `figma-split-design-2151-17164.png`.

1. **Dot gallery trong card làm trượt cả product slider**: 2 nguyên nhân — (a) click bubble lên root slider ngoài (SnapSlider listen click trên root `this.el`, `closest("[data-pager]")` khớp pager gallery); (b) quan trọng hơn: SnapSlider scroll bằng **`scrollIntoView`** cuộn MỌI ancestor cuộn được → `stopPropagation` thuần không đủ. Fix `sliders-init.phtml`: intercept **capture phase** trên root card gallery (click + ArrowKeys), `preventDefault + stopPropagation`, tự scroll track bằng `track.scrollTo()` (chỉ cuộn đúng element). Verify: bấm dot gallery → inner 0→302, outer 0→0 (cả 1440/1920); nav arrow slider ngoài vẫn chạy ✓.
2. **Everyday more value**: bỏ full-bleed (`margin-inline-end: calc(50% - 50vw)`), card giữ 360px, clip tại mép container; pb-style cap max-width 1280 → đưa về **1536 chuẩn** (contain các row khác) + margin auto. Verify 1440: item 4 visible 193px (clip "một khúc" như design), 1920: holder 1456 (clip 56px), không overflow viewport.
3. **Living, Reimagined full width**: user đổi row appearance sang `full-bleed` (save 06:33 — content hash đổi giữa session) → CSS split cover **cả 2 appearance**; bẫy mới: rule `full-bleed:has(buttons)` của chính mình (min-height 800 + padding 2.5rem/4rem, cùng specificity nhưng nằm sau) áp oan lên row → neutralize bằng prefix `#html-body` (specificity ID thắng). Verify: ảnh x=0 sát mép trái, text phải chừa 2.5rem, cả 1440/1920.

Regression: nav slider ngoài hoạt động, không h-scroll, 0 pageerror. Bài học cascade: rule cùng specificity !important — cái nằm sau thắng; khi 2 rule cùng bậc nhắm cùng element phải bump `#html-body`. Verify script `verify-v492-three-issues.js`, screenshots `section-{promo,split}-1440.png`.

## Update 09-21 (user decision): v4.9.1 — convention màn tiêu chuẩn 1920 + card giữ size design

User chốt: design 1440 nhưng **màn tiêu chuẩn implement = 1920**; lựa chọn phương án (AskUserQuestion): **content cap ~1456 giữa + nền full-bleed** (giữ proportions design), KHÔNG scale toàn bộ. Verify 1680/1920: mọi section ổn sẵn (contained cap 1456 centered, hero/video full-bleed, không h-scroll, 0 pageerror).

Polish duy nhất cần: **card rails/flash phóng theo viewport** (300px@1440 → 361@1680 → 394@1920, +30%) vì `--snap-cols` cố định = card = track/cols. Fix: breakpoint mới cùng tầng **unlayered + `!important`** (bản layered bị `--snap-cols: 4 !important` line ~894 đè — lần 1 không ăn): `≥105rem` cols 4.75/4.85, `≥120rem` cols 5.15/5.25 → card ~300–303px mọi desktop width, **màn rộng hiển thị THÊM sản phẩm** thay vì phóng card. Verify: 1440/1680/1920 = flash 300/302/301, rail 302/303/301, tile 180/223/223 (grid-fill trong cap — theo convention), USP 270/334/334, promo 360×502 fixed, newsletter centered, không h-scroll, 0 pageerror. Screenshots `hp-wide-v2-1680.png` + `hp-video-1920-scrolled.png`, probe `verify-wide-1920-convention.js`.

Flag (ngoài scope — module `Secomm_UiWidget`): poster video `loading="lazy"` + nền container = `--play-btn-bg` **YouTube red #ff0000** (`components/embed/a.phtml:51`) → khi poster chưa load (chậm/lazy) cả khối 16:9 hiện ĐỎ. Đề xuất PM/TL: đổi nền placeholder sang màu trung tính, giữ nút play đỏ brand (sửa thuộc `Secomm_UiWidget` — cần ticket riêng).

## Update 09-21 (user report): v4.9 — save-breakage round 2: column-line NEST + stale static + poster drop

User report: "homepage vẫn bị mất css khi change content và save trên admin" (save 04:50, sau verify v4.8 04:27). 3 root cause độc lập, đo bằng structural diff content + probe rendered:

1. **(MỚI — chưa từng thấy) save NEST `column-line` BÊN TRONG `column-group`** (group > line > columns thay vì group > columns): mọi rule container pair v4.8 áp layout lên **cả hai tầng** → group grid(4) có line làm 1 item → line rơi 1 track → USP 4 cột **38px**/cột (stack hẹp trái). Fix: toàn bộ container rule target phần tử **trực tiếp chứa columns** — guard `:has(> [data-content-type="column"])` trên 10 selector (tiles ×2, promo ×2, split ×2, USP ×3... theo media tier) + **neutralizer** `display:block!important; width:100%!important` cho wrapper `group:has(>line)` / `line:has(>group)`.
2. **F9 lần nữa — served `pub/static/.../vi_VN/css/styles.css` stale** (227853b) trong khi source build 11:36 có rule pair (230550b): USP chỉ vỡ vì served thiếu rule — `!w-full` trên columns (class sống) + PB flex row + không có grid override = full-width stack. Fix: `rm` stale copy → dev-mode serve lại (hash served = source, `d8958fb3`). **Workflow: mọi lần `npm run build` phải verify served hash, không cp là vỡ ngầm.**
3. **Save drop field `poster` khỏi payload widget `embed_a`** → REAL SPACES thành khối đỏ (không poster). Payload hiện = canonical trừ poster (decode so sánh). Fix: **surgical payload swap** (script `restore-poster-v49.php` — sanity decode `current == canonical-minus-poster`, backup `home-content-before-poster-restore.txt`, không đụng phần edit khác của user).

Verify sau fix (Playwright, content admin-saved của user giữ nguyên): desktop 1440 — USP grid 4×270px, poster `imgLoaded=true`, hero 1024, tiles 6-col, promo 360×502, video 16:9 full-bleed, newsletter centered, 32 card, markers 40 + `aria-current`, không h-scroll, 0 pageerror; mobile 375 — USP 1×344, hero 750, promo 320×400, video 203. Screenshots `hp-after-save-{1440,375}.png`, probe `verify-v49-save-breakage.js`.

**Cảnh báo còn lại cho user/PM khi edit content**: class anchor trên **columns** (`lp-tile`/`lp-usp`/`lp-promo-card`) sống qua save lần này nhưng nếu **xoá/tạo lại column** trong PB stage thì class mất → section mất styling (phải re-enter vào CSS Classes tab Advanced); row classes `lp-section`/`lp-flash`/`lp-journal`/`lp-video` bị strip lần này (flash/journal/video vẫn render đúng nhờ module template + structural anchor, chỉ mất spacing tuỳ section); `lp-dot` không mất (probe selector cũ sai — dots là `nav[data-pager] .snap-marker`).

## Update 09-21: v4.5.1–v4.5.3 — newsletter module-template styling + card Configure + arrow centering

- v4.5.1/2: template trong app/code KHÔNG được theme `@source` quét → utility chỉ dùng ở đó không compile (nút Subscribe mất style, form không giãn). Fix: toàn bộ styling form/journal chuyển thành lp-* component classes trong homepage.css (`.lp-newsletter-form/.lp-newsletter-input/.lp-newsletter-btn/.lp-journal-media img/.lp-journal-title a`).
- v4.5.3: bỏ nút "Cấu hình" (grouped/bundle branch) khỏi card — configurable vẫn show swatch qua handle `catalog_list_item` (load global qua PB default.xml); arrow promo card: PB ép inline-block (unlayered) đè inline-flex → fix unlayered scoped, icon centered trong circle 36×36.

## Update 09-21: v4.5.4 — swatch card vỡ do ghi đè display của Hyvä

Hyvä `.swatch-option` = grid stack (radio + label cùng ô); restyle cũ đặt `flex` → radio `size-full` thành flex item → chữ option tràn box. Fix: giữ grid Hyvä, restyle qua custom properties (`--swatch-size/--swatch-radius/...`) + selected/disabled states. Verify cat 49 (Haven Platform Bed): Queen/King text inside box, 3 color dot có màu, selected viền đậm.

## Update 09-21: v4.6 — fix save-breakage thật (pb-style ID CSS) + flash expiry + options polish + video Secomm widget

- Root cause save-breakage THẬT: PB save sinh `#html-body [data-pb-style=HASH]` ID-specificity CSS đè mọi selector + 16 selector sai combinator `>`. Fix: importantize tầng strip-proof/unlayered + `>` → descendant. Verify trên content admin-saved thật ✓.
- Flash sale ẩn khi hết countdown (server-side + runtime Alpine, ẩn cả row).
- Options card polish: text box 48px min, color dot 24px single-border, selected #293e2d.
- Video = Secomm UI widget `embed_a` (payload Codec base64url; directive `{{widget type="Secomm\UiWidget\Block\Widget\SecommUi" ...}}`); theme build thêm `@source` module templates (path 5× `../`).
- Content canonical: `home-content-v5_6-seeded.html`.

## Update 09-21: v4.8 — column-line fix (root cause render sai cuối cùng)

Save PB convert column-group → column-line; 22 selector strip-proof được pair `[column-group], [column-line]` (mỗi fragment full scope). Class convention chốt theo user: class trong CSS Classes (tab Advanced). Video = Secomm UI widget embed_a (poster + play, lazy). Final verify trên content admin-saved: toàn bộ section + card + video ✓.

## Evidence (v4, 09-18)

- Screenshots: `home-v4-vi-1440-full.png`, `home-v4-vi-375-full.png`; content v5 seeded: `home-content-v5-seeded.html`; backup: `home-content-before-v5.txt`; Figma shots: `figma-desktop-shot.png`/`figma-mobile-shot.png`/`figma-card-shot.png`.
- Verify chi tiết + findings F9–F14: `RESULTS.md` §"v4 (09-18)".

## Update 09-21: v4.12 — fix admin không load được content (user report) + revert {{view}} → {{media}}

**User report**: Admin > Content > Pages > Home Page — PageBuilder stage không render content.

- **Root cause 1 — JSON truncate (stage chết)**: template v4.11 sinh directive `{{view url="X"}}` có RAW double quotes trong `data-background-images` (JSON-in-attribute) → browser cắt attribute tại `"` đầu → `BackgroundImages.fromDom` JSON.parse fail ("Unterminated string at position 29") → stage init chết, content trắng. 4/37 attr bị (hero ×3 + sofa banner), 22 attr `<img src>` cùng lỗi (src cụt).
- **Root cause 2 — {{view}} không round-trip**: PB stage chỉ round-trip ổn định `{{media url=}}` trỏ media có thật; `{{view url=Module::…}}` bị stage regenerate thành `{{media url=Module::…}}` → preview 404 + Save kế ghi đè content hỏng. Chi tiết: **LL-0039**.
- **Fix**: (1) `etc/homepage-content.html` — 29 directive `{{view url=Launchpad_Homepage::images/homepage/*.webp}}` → `{{media url=wysiwyg/homepage/*.webp}}`; (2) copy 17 webp → `pub/media/wysiwyg/homepage/` (poster real-spaces.webp đã có sẵn); (3) update DB `cms_page` `home` (page_id 2) cùng nội dung — backup trước: `home-content-before-escape-fix-db.txt` + `home-content-fixed-escape-20260921.html`; (4) cache:flush.
- **Kèm theo**: `item.phtml` ParseError 17:07 (đã được sửa trong file, php -l PASS) — fileformerly root-owned, đã `chown secomm:secomm` (shell-trap LL).
- **Verify (Playwright, admin user tạm `claudetmp` — đã xóa)**: Admin edit page → expand Content → stage render **120 content-type** (row 14, column 16, heading 26, 0 pageerror, 0 console error, 0 HTTP 4xx, hero bg preview load từ media). Storefront 1440: rows 14 / slides 3 / headings 26 / cards 32 / 24 img homepage (23 loaded, 1 lazy) / 0 pageerror / 0 4xx / không overflow-x.
- **Deploy note (đổi so v4.11)**: content homepage giờ tham chiếu **media files** (`wysiwyg/homepage/*.webp`, 17 file) — runbook deploy phải ship `pub/media/wysiwyg/homepage/*.webp` (thay vì "0 media ref" như v4.11 ghi). Data patch `SeedHomepageContent` đọc đúng template đã fix — env mới seed chuẩn.
- **Flag còn mở**: hero slider hiện 3 slides (template seed) — design gốc 6 dots; 3/6 slides placeholder chờ ảnh user (đã ghi ở mục Còn mở). Manifest không đổi ngoài content/template + media files.
