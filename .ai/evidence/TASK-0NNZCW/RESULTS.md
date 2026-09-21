# TASK-0NNZCW (SLP-213) — Homepage Layout Mock-up — Verification Results

Ngày verify: 2026-09-17 · Env: local (slaunchpad.localhost, MAGE_MODE=developer, theme Secomm/launchpad)

> **v3.1 (09-17, chỉ thị user 3)**: banner/hero styling chuyển vào **CMS content** (inline styles per element — PageBuilder-editable); product widget styling giữ trong code (`hp-pb-*`, `hp-card-*`). Dead CSS banner/hero từ v3 đã dọn. Seed script v3.1: `pb-seed-v31.php` (evidence). Verify lại **vi 1440 + vi 375: 11/11 PASS**.
>
> **Trap F9 (v3.1)**: `pub/static/.../styles.css` là **regular file materialized cũ** (không symlink) → build OK nhưng trang serve bundle stale, CSSOM thiếu rules v3 → layout sập trong khi check geometry từng PASS lúc bundle cũ còn giá trị. Fix: `rm` file materialized → dev mode symlink lại source. (Lặp pattern LL-0015/LL-0029 F3.)
>
> **v3 (09-17, chỉ thị user 2)**: homepage chuyển thành **PageBuilder content** trong CMS page `home`
> (Admin-editable, banner kéo thả). Layout XML không còn block section nào; theme chỉ giữ: remove demo Hyvä,
> quickview modal, CSS. Hero = PB **slider**, banner = PB **banner** (kéo thả), products = PB **products carousel**
> qua template override `Magento_PageBuilder::.../carousel.phtml` (items qua `product_list_item` pipeline —
> card restyle v2 giữ nguyên giá trị). Countdown/HTML sections = PB **text** content với class `hp-*` compiled.
> Verify **vi 1440 + vi 375: 11/11 PASS mỗi suite** (v3 script). Backup content cũ: `home-content-before-v3.txt` (empty).
>
> **v2 (09-17 chiều)**: product card = restyle `item.phtml` (pipeline chung PLP + widget); sections render
> sản phẩm thật qua ProductsList blocks (layout XML — superseded bởi v3).

## Kết quả Playwright (script `hp-verify.js`, 1 viewport/process)

| Suite | Kết quả | AC |
|---|---|---|
| vi @1440×900 | **12/12 PASS** | AC-1..AC-9 |
| vi @375×812 | **12/12 PASS** | AC-1..AC-9 |
| en @1440 (`?___store=launchpad_en`) | live-block env pre-existing (store 2 locale=vi_VN — TASK-K14RVZ) | bù dict-level |

### Chi tiết check (vi, cả 2 viewport)
- **AC-1** sections render đúng thứ tự design: hero → Danh mục → Siêu sale → Giá trị hơn mỗi ngày → Không gian sống → Phòng khách → Phòng ngủ → Phụ kiện (+ Đến bộ sưu tập) → KHÔNG GIAN THẬT → Sofa mới cho mùa thu → Vì sao chọn chúng tôi → Bài viết.
- **AC-2** header + footer giữ nguyên hiện trạng (0 change).
- **AC-3** hero full-bleed: width slide = width `main` (1425 = 1440 − 15 scrollbar headless; mobile 360 = 375 − 15).
- **AC-4** slider full width + overflow: tracks tràn = hero + categories + banners + carousel (desktop) / + flash sale (mobile 2 cols); `scrollWidth − clientWidth` của document = −15px → **không horizontal scroll trang**. (Flash sale desktop 4 items < 5 cols → không tràn là đúng data.)
- **AC-5** card anatomy (thật): form `product_addtocart_form` + formkey + photo + wishlist overlay + price-box + stock + rating + ATC button + quickview dispatch + name — đủ hooks; 18 cards thật (Flash sale 4 đèn `Lighting`, Phòng khách 4 `Seating` — Meridian/Sylvan/Orbit/Atlas, Phòng ngủ 2 `Beds`, Phụ kiện 8 `Tableware` — Terra).
- **PLP regression** (`/women/tops-women.html`): 12 cards, 12 ATC forms, quickview/wishlist/price/stock OK, ảnh thật, console sạch; PDP related slider 3 cards card mới.
- **Furniture category URL 404** (`living-room-tables.html`…): category 42/43/46/49/60 không active/không gán store 1 trong nav — **store data pre-existing**, không thuộc change set (homepage vẫn lấy được sản phẩm vì conditions filter ở collection level).
- **AC-6** countdown tick đo được: `00:59:57 → 00:59:55` (Alpine setInterval 1s, tabular-nums).
- **AC-7 (vi)** render VI: "Danh mục", "Siêu sale", "Phòng khách"…hits 3/3.
- **AC-8** quickview modal vẫn wired trên homepage (`open-quickview` present — block `quickview.modal` giữ nguyên trong layout).
- **AC-9** console 0 error/pageerror.

### AC-7 (en) — dict-level bù vì env
Live en store render VI (hits vi=3/3) = **env pre-existing**: store 2 `launchpad_en` locale = vi_VN (đã flag từ TASK-K14RVZ, chờ TL/DevOps khôi phục `general/locale/code` store 2). Bù dict-level (script python, evidence trong log phiên):
- 52 key mới có mặt ở **cả** `vi_VN.csv` + `en_US.csv`; en rows **identity EXACT**; vi rows bản dịch VI (0 row vi==en).
- Không tạo duplicate key mới (3 dup tìm thấy đều pre-existing: `Email` dòng 174, `Comments`, `Enter your comment here` — trước điểm append).

## Regression
- PLP `women/tops-women.html` HTTP 200, PDP `atlas-pouf.html` HTTP 200, cart HTTP 200 — 0 class `hp-*` leak ra các trang khác (template mới chỉ render qua `cms_index_index`; CSS override `.columns` scoped `body.cms-index-index`).

## Fixes trong lúc verify (root cause + evidence)
1. **`.hp-card` render rỗng lúc đầu** — product-card.phtml đọc `$cardData` biến thường thay vì `$block->getData('card_data')` → early-return. Fix template. (Ngoài ra **full_page cache bật trong env này** phục vụ HTML stale che fix — phải `cache:flush`/tắt full_page khi verify loop; đã enable lại as-found.)
2. **Hero không full-bleed** — Hyvä default theme `.columns` cap 1280px + padding; `<attribute class>` trên container không ăn (Hyva render main của riêng nó) → override scoped `body.cms-index-index main .columns { max-width:none; padding-inline:0 !important }` (SLP-160 precedent) + `.hp-track-hero { padding-inline: 0 }` cho hero sát mép.
3. **Demo content Hyvä default theme render trước hero** ("Hyvä Theme" block, slider-1 "…Inspirations", slider-2 "Popular Products" từ `vendor/hyva-themes/magento2-default-theme/Magento_Theme/layout/cms_index_index.xml`) → `referenceBlock remove` cho `hero`/`slider-1`/`slider-2` trong cms_index_index của child theme (scoped homepage; không đụng config `show_homepage_demo_content`).

## Screenshots (full page)
- `home-vi-1440-full.png` — desktop khớp design, đủ 10 section.
- `home-vi-375-full.png` — mobile: hero full-bleed + dots, Danh mục 2 tiles + peek, Siêu sale 2 cards, USP stack, carousels 2-col.
Screenshot en v2 không chụp (live-en bị chặn env — xem AC-7; screenshot en v1 placeholder đã xóa để tránh gây hiểu nhầm).

## v3 — lưu ý verify/QC
- **Admin PageBuilder stage chưa verify trong session** (cần login admin): QC phải mở Content > Pages > Home Page, kiểm tra stage load + kéo thả banner/products hoạt động. Content seed là PageBuilder master format chuẩn (data-content-type/slide/banner/products + widget directive).
- **CMS content store-shared** (`cms_page.content` một giá trị — schema verify): content seed = tiếng VI; EN store view hiển thị cùng content. Per-store CMS content = module third-party (follow-up nếu cần).
- Ảnh trong content = gradient placeholder inline-style — khi user cung cấp file asset (hero/promo/real-spaces/split/journal), upload vào `pub/media/homepage/` rồi thay background-image trong Admin (hoặc nhờ dev).
- Product carousels: không arrows/dots mặc định trên vài section do PB root attrs (`data-show-dots` đặt trong content); scroll-snap + scroll tay hoạt động mọi section.
- `full_page` cache: **đang bật** (as-found), verify loop tắt/bật đúng quy trình.

## As-found / restored
- `full_page` cache: bật → tắt trong verify loop → **đã bật lại** (as-found).
- Không có DB/config change thuộc change set (demo blocks Hyvä remove bằng layout code, không phải config).

---

# v4 (09-18) — Figma LAUNCHPAD CORE, desktop 1440 + mobile 375

Scope user: hero slider + product widget slider; flash sale widget custom option
(`sale_end`); restyle product card theo Figma; REAL SPACES = video embed (YouTube
6HIi9IzqoNM); Journal = Magefan blog slider (mobile slider, desktop 3-up); tạo
newsletter block; mọi CSS của PageBuilder content qua class (safelist Tailwind JIT).

## Verify (Playwright, store vi 1440 + 375)

- Homepage: **19/20 PASS mỗi suite**. FAIL duy nhất = card đầu tiên flash sale
  (product không có manufacturer + out-of-stock → không ATC/brand) — data-dependent;
  aggregate 32 card: name/price/stock/rating/quickview/wishlist = 32/32, ATC 28/32
  (4 non-saleable), brand 0 (DB chưa có manufacturer data — placeholder như thiết kế).
- Slider: 4 track (flash + 3 rails), đều overflow + peek; rail nav arrows scroll OK
  (desktop), ẩn mobile; pager dots = group-pager theo số card hiển thị.
- Countdown "Kết thúc sau HH:MM:SS" tick mỗi giây (target = sale_end rỗng → nửa đêm
  kế theo store TZ).
- Video: iframe youtube-nocookie src=6HIi9IzqoNM render (full-page screenshot để
  iframe trắng do lazy — xác nhận bằng DOM probe).
- Journal: 3 bài Magefan (post 2/3/4), mobile slider peek (887>360) + 3 dots,
  desktop 3-up fit (dots tự ẩn).
- Newsletter: cms_block `homepage-newsletter` (id 21, data patch 290) + form render.
- Console sạch desktop + mobile; body không overflow-x (overflow-x: clip).
- Regression: catalogsearch 12 cards + category 4 (direct URL) 12 cards — quickview,
  ATC form đủ; 0 pageerror.

## Files (v4)

- Module `Launchpad_Homepage` (mới): FlashSaleList widget (option `sale_end`),
  Block\Blog\Journal, templates flash-sale.phtml + journal.phtml, data patch
  newsletter block, README/CHANGELOG.
- Theme: item.phtml (card restyle: badge "New arrival" xanh, chip -% đỏ #c10007
  tại price row, brand #99a1af, name 16→18 medium, meta row stock+rating 1 hàng,
  Quick view outline button đáy card — eye icon; bỏ quickview khỏi hover overlay),
  carousel.phtml (thêm nav [data-page-builder-slider-nav], dọn safelist-comment cũ
  + endif mồ côi), homepage.css (lp-* v4 + card tokens design), tailwind-source.css
  (@source safelist), safelist/launchpad-cms.html (GENERATED — 212 class từ DB),
  i18n: "New arrival" 2 CSV.
- CMS: content v5 seed (evidence home-content-v5-seeded.html; backup
  home-content-before-v5.txt) — flash = FlashSaleList widget, rails = PB products
  carousel (data-show-dots/arrows + template PB carousel), REAL SPACES = PB video
  element, Journal = {{block}} Launchpad blog, newsletter = lp-newsletter + block 21.

## Findings (v4)

- **F9 — safelist Tailwind cho DB content**: class trong cms_page/cms_block không
  được scan → purge. Comment-in-phtml KHÔNG reliable; file safelist html thật
  (`@source "./safelist/**/*.html"`) hoạt động (verify built CSS chứa cả arbitrary
  values + `!`-important v3-prefix). Regen safelist sau mỗi lần sửa content (script
  trong RESULTS này: PDO → class tokens → div soup).
- **F10 — Tailwind v4 vẫn nhận `!mt-8` prefix** (legacy important) trên build 4.3.2.
- **F11 — snap-slider shim** (`Hyva_Theme::page/js/plugins/snap-slider.phtml`) chọn
  version theo ThemeLibrariesConfig; Alpine directive `x-snap-slider` (v3) không
  register tin cậy được → module templates init thủ công `new SnapSlider(el,
  {autoPager:true, groupPager:true})` ở DOMContentLoaded (pattern PB
  widgets/carousel.phtml — hero slider chạy cùng cơ chế, đã verify).
- **F12 — PB columns flex-basis:100% trên mobile** đè width cố định của tile/promo
  trong rail → override `flex: 0 0 auto` scoped `body.cms-index-index .lp-rail >
  [data-content-type="column"]` (tile 172px / promo 320px chuẩn design, có peek).
- **F13 — PB video element**: seed master format `<div data-content-type="video">`
  + iframe youtube; theme page-builder.css đã có aspect-ratio 16/9; wrap thêm
  lp-video CSS width 100%.
- **F14 — carousel.phtml cũ chứa orphan `<?php endif; ?>`** sau safelist-comment
  (ParseError khi dọn) — đã remove toàn bộ block legacy.

## Notes / known limitations (v4)

- Hero = 3 slides (design vẽ 6 dots) — thêm slide = edit PageBuilder (ảnh hero-2/3
  không tồn tại trong media; slides 2-3 đang trỏ living-reimagined-1/2.jpg).
- Content CMS dùng chung store (vi/en) — copy EN theo design (như v3).
- Brand card rỗng toàn site: attribute `manufacturer` chưa có data (placeholder).
- "Exclusive" badge vàng: không có attribute tương ứng — bỏ (chỉ "New arrival").
- Admin re-save products element trong PageBuilder có thể strip data-show-dots/
  data-show-arrows (attr custom ngoài schema PB) — re-check sau khi admin edit.
- Category desktop: design là rail 12 tile + dots ẩn; hiện tại giữ grid 6 (content
  hiện có 6 tile) — chờ data đủ 12 tile.

## v4.1 (09-18) — fix hero text/button position + center newsletter (user feedback)

- **Hero**: PB `page-builder.css` đặt poster overlay `justify-content:center; align-items:center`
  UNLAYERED (specificity 0,3,0) → utility `justify-end` (layered) thua → copy đứng giữa hero.
  Fix unlayered scoped: `justify-content:flex-end; align-items:flex-start` + track
  `padding-block:0` (hero flush 1024). pb: seed mang `!pb-20/lg:!pb-24` (important,
  utilities layer) — với important cascade ĐẢO layer: unlayered important THUA →
  đặt `padding-block-end:7.25rem !important` trong `@layer base` (layer sớm nhất
  thắng important). Desc: bỏ wrap do `max-w-[640px]` (CSS `max-inline-size:none`).
  Kết quả đo (1440): title left=40 top=732 (design 744), button 856–908 left=40
  (design 860–908 — bottom exact), dots 916–960 center (design 928–960 — bottom exact).
- **Newsletter**: inner container `items-start` → title/form dính trái. Fix unlayered
  `[data-element="inner"] { align-items:center; text-align:center }`. Đo: title
  centerX=712, form centerX=713 (vw 1440 ≈ tâm ✓).
- Verify lại: 19/20 PASS mỗi suite (FAIL duy nhất giữ nguyên — data-dependent);
  screenshots cập nhật.

## v4.2 (09-18) — 6 fix theo feedback: category slider, hero autoplay, 2-up mobile, promo, USP gap, product widget

Verify Playwright (1440 + 375): **10/10 PASS**:
1. Category mobile = slider ngang thật (nowrap + scrollable, 1 hàng, tile 172px + peek).
2. Hero autoplay (module `Launchpad_Homepage` block `hero-autoplay.phtml`, interval theo
   data-autoplay-speed, pause hover/hidden tab/reduced-motion, loop về slide 1) +
   slides **fixed height** 750/1024 (CSS) → không giật khi chuyển slide.
3. Product mobile **đúng 2 item/view** (176px, hết half-peek: --snap-cols 2; track
   padding-inline 0 mobile / 0 2.5rem desktop).
4. Promo "Everyday more value": mobile slider 320px; desktop RAIL bleed ra viewport
   (4×360×502 — bỏ lg:grid trong content, card size code-owned).
5. USP mobile gap 12px (sm 24 / lg 40).
6. Product widget: flash desktop 4-up + peek (cols 4.1), rails cách nhau 24px như design.

### Findings (v4.2)

- **F15 — Hyva_CmsTailwindJit (HYBRID mechanism)**: module prefix class lúc render +
  prepend CSS sinh tại ADMIN SAVE. Content seed bằng PDO BYPASS persist observer →
  CSS module STALE. Token có trong stale CSS → được prefix `hcms-page-2-<token>` +
  style bởi module; token MỚI/không có → giữ nguyên → phải do safelist theme build
  lo. **Bug module với token `!`-đầu-căn-base** (`!flex`): rewrite thành
  `!hcms-page-2-flex` (bang-first) nhưng sinh CSS `.hcms-page-2-\!flex` (bang-sau)
  → KHÔNG khớp → `display:flex` không bao giờ áp (rails stacked rows). Workaround:
  bỏ `!` khỏi token trong content (dùng `flex` thường). Sau khi admin mở Content >
  Pages và save lại, module sẽ regenerate CSS đầy đủ cho content hiện tại.
- **F16 — cascade war tổng**: page-builder.css rules UNLAYERED thắng mọi @layer;
  important trong content (`!utility`, utilities layer) thắng unlayered important
  (cascade đảo với important) → override cần `@layer base` + `!important` (base =
  layer sớm nhất, thắng important). Rules css của theme cạnh tranh với PB phải đặt
  unlayered (cuối homepage.css).
- **F17 — autoplay + track**: PB carousel script tạo `[data-track]` trong
  DOMContentLoaded handler của NÓ (đăng ký sau vì script render cuối body) → script
  chạy trước phải lazy-query track trong interval, không query lúc DCL.

## v4.3 (09-18) — !-class audit, newsletter fix, product card per design

1. **!-audit**: mọi token `!`-đầu trong content đã kiểm tra trên live HTML —
   **0 broken `!hcms-*` token** (token `!flex` đã bỏ ở v4.2; còn lại = unprefixed
   → safelist theme lo, hoặc prefixed-đúng → module JIT CSS lo).
2. **Newsletter**: root cause = v5.1 seed từ evidence file còn giữ placeholder
   `__NEWSLETTER_BLOCK_ID__` → widget `block_id` vô nghĩa → render rỗng. Fix:
   thay widget CMS static block bằng `{{block class="Launchpad\Homepage\Block\Newsletter\Subscribe"
   template="Launchpad_Homepage::newsletter/subscribe.phtml"}}` (module block —
   getUrl đúng, i18n __(), miễn nhiễm widget-usage-map second-pass skip).
3. **Product card**:
   - Compare: đã remove khỏi card (markup + unused viewmodel wiring).
   - Configurable options: pipeline Hyvä sẵn có (handle catalog_list_item +
     renderer `Magento_Swatches::product/listing/renderer.phtml`) — trước giờ
     không render vì **catalog demo không có product configurable trong category
     hiển thị** (cat 15 có MH01/MH02/MH03 configurable → test được). Style lại
     theo design (fieldset/legend sr-only, option box 48×32, color dot 32 round,
     border #d1d5dc) — verify cat 15: 96 swatch-option render + box 48×36 ✓.
   - **Ảnh dạng slider**: media = snap-slider các ảnh gallery (tối đa 4) + mini
     dots (active pill 24×8 #588f60) — SnapSlider ẩn dot khi chỉ 1 ảnh (29/32
     card 1 ảnh → fallback single photo = đúng hành vi, data-dependent).
4. **BUG NGHIÊM TRỌNG đã phát hiện khi batch gallery**: `addMediaGalleryData()`
   gọi trong `createCollection()` (bất kỳ subclass CatalogWidget nào) →
   **Elasticsuite virtual-category plugin (around plugin) reload collection
   sau khi method trả về → mất page limit + điều kiện** (8 items → 162, trang
   homepage 648 card/14MB). Fix: gọi `addMediaGalleryData()` trong TEMPLATE sau
   khi createCollection + plugin đã chạy xong (flash-sale.phtml + PB
   carousel.phtml). Verify: 32 card, console sạch.

Verify v4.3: desktop + PLP (cat 15) + mobile = **PASS toàn bộ** (chi tiết trong
verify script output; 1 check FAIL là ngưỡng cũ ">=20 sliders" — hành vi đúng
hiện tại là slider chỉ render khi product có ≥2 ảnh).

## v4.4 (09-18) — dot progress-fill + sửa frontend vỡ sau admin save

**Sự cố**: user save PageBuilder trong Admin → frontend vỡ CSS. Root cause: PB
round-trip STRIP custom classes khỏi phần tử structural (row inner, column-group,
slide wrapper, overlay — `lp-rail`, `lp-section`, `lp-tiles`, `lp-slide-overlay`
mất hết) + DROP raw iframe trong video element. Class trên column/card/content
HTML (h2/p bên trong) sống. DB backup: `home-content-after-admin-save.txt`.

**Fix**:
1. Re-seed v5.4 (canonical v5.3 + video chuyển sang **PB html element** `.lp-video`
   — iframe sống qua save; native video content-type đã drop iframe).
2. **Hero save-proof CSS**: height/bg-size/bg-position (wrapper) + flex-column/
   min-height 100%/padding-inline (overlay) — CSS-owned qua selector
   `.lp-hero-slider .pagebuilder-slide-wrapper/.pagebuilder-poster-overlay`,
   không phụ thuộc class có thể bị strip.
3. **Dot progress-fill** (story-style): marker active fill trái→phải theo
   `data-autoplay-speed` (keyframes lp-dot-fill + --lp-dot-duration), advance
   NGAY at animationend (không còn setInterval), pause hover/tab (pause state),
   re-arm trên slideChange (swipe/click dot thủ công). Reduced-motion: giữ hành
   vi cũ (không autoplay).
4. `reseed-homepage.php` (evidence): script tái sử dụng — re-seed + regen
   safelist; sau đó `npm run build` + `cache:flush`.

**WORKFLOW sau khi save PageBuilder trong Admin**: nếu layout vỡ ở group-level →
chạy reseed-homepage.php → npm run build → cache:flush. Class ở column/card/wysiwyg
sống qua save — chỉ group/slide/overlay là rủi ro (hero đã CSS-owned; các group
khác cần re-seed).

Verify v4.4: **9/9 PASS** (hero copy 732/40 save-proof, video, dot fill 0→0.45→
advance, 32 card, newsletter, console sạch) + regression v4.2 suite 10/10 + mobile OK.

## v4.5 (09-18) — STRIP-PROOF: homepage render đúng kể cả khi admin save strip class

Sự cố lặp: re-seed v5.4 bị save admin (16:11, từ tab đang giữ content cũ) đè →
vỡ CSS lần 2. Chấm dứt vòng lặp bằng kiến trúc mới:

1. **Toàn bộ section styling → CSS-owned** qua structural selectors
   (`data-content-type` / `data-appearance` + `:has()` của feature SỐNG qua save:
   module markup `.lp-flash/.lp-journal/.lp-video/.lp-newsletter-form`, column
   classes `.lp-tile/.lp-promo-card/.lp-usp`, products element). PageBuilder
   KHÔNG BAO GIỜ strip data-attributes → homepage render đúng từ BẤT KỲ content
   shape nào. Lưu ý: column-group nằm trong `[data-element="inner"]` — selector
   phải là descendant, KHÔNG `>`.
2. **Video = `{{block class="Launchpad\Homepage\Block\Video"}}`** (module
   template) — directive sống qua save, iframe render từ module (không thể mất).
3. Hero slide/overlay CSS-owned (từ v4.4) — selector đổi sang
   `[data-content-type="slider"]` (thay class .lp-hero-slider).

**Bằng chứng**: mô phỏng admin-save (strip TOÀN BỘ class khỏi row/inner/group/
slide-wrapper/overlay bằng script) → seed → render: hero 732/40 + 1024px, tiles
grid 6 (193px), USP 4 cols, split 2 cols, video iframe, newsletter căn giữa,
32 card, promo 360, dots 3 — **tất cả đúng** (screenshot
`home-simulated-save-1440.png`). Content canonical v5.5 đã restore
(`home-content-v5_5-seeded.html`; regression 13/14 — 1 FAIL = ngưỡng cũ slider
count, hành vi đúng là slider chỉ khi product ≥2 ảnh).

Hệ quả: admin save PageBuilder KHÔNG còn phá homepage nữa (cả styling lẫn
video). Content classes còn lại chỉ là decorative hooks.

## v4.5.1 (09-21) — nút Subscribe mất style

Root cause: form newsletter chuyển sang module template (app/code) — **nằm ngoài
`@source "../../**/*.phtml"` của theme** → utility chỉ dùng ở đó không bao giờ
compile (`bg-hp-brand-dark`, `hover:bg-hp-ink`, `text-hp-ink`,
`focus:border-hp-brand`; journal: `aspect-[437/520]`, `hover:text-[#35573a]`).
Fix: styling chuyển vào lp-* component classes trong homepage.css
(`.lp-newsletter-input/.lp-newsletter-btn/.lp-journal-media img/.lp-journal-title a`),
template chỉ còn class semantic. Verify: button bg rgb(69,116,76) + trắng 44px
radius 6, input viền #d1d5dc, journal img aspect 437/520 ✓.

## v4.5.2 (09-21) — newsletter form layout

Toàn bộ layout form (mx-auto/w-full/max-w-1136/flex-row/gap) cũng đang dùng
utility trong module template (không được @source scan) → form không giãn. Đã
chuyển vào `.lp-newsletter-form` (homepage.css), template chỉ còn
`class="lp-newsletter-form"`. Verify @1680: form 1136px centered ✓, input
1006px + nút Đăng ký #45744c ✓, band #f8f6ee ✓ (screenshot newsletter-band.png).
LƯU Ý user: hard-refresh browser (Ctrl+F5) nếu vẫn thấy bản cũ — styles.css cache.

## v4.5.3 (09-21) — bỏ nút Configure + fix arrow promo centering

1. **Card bỏ nút "Cấu hình"** (grouped/bundle branch trong item.phtml — demo có
   bundle+grouped trong category flash/rails nên 2 card hiện "Cấu hình" ×2).
   Design không có nút này; cấu hình đi qua Quick view / product page
   (configurable card vẫn show swatch options qua pipeline Hyvä
   `catalog_list_item` — handle được load global qua Magento_PageBuilder
   default.xml, verify: cat 15 render 96 swatch-option).
2. **Arrow promo card căn giữa trong circle**: page-builder.css (UNLAYERED) ép
   `inline-block` lên `[data-content-type="button-item"] a` → đè `inline-flex`
   (layered) → icon lệch. Fix unlayered scoped:
   `row:has(.lp-promo-card) [data-content-type="buttons"] [data-element="link"]
   { display:inline-flex; align-items:center; justify-content:center }`.
   Verify: circle 36×36, icon centeredX/Y = true (promo-arrow-fixed.png).

## v4.5.4 (09-21) — swatch options trên card bị vỡ (Haven Platform Bed)

Root cause: Hyvä `.swatch-option` là **grid** (stack hidden-radio + label vào
cùng grid-area 1/1); CSS restyle trước đây ghi đè `display: flex` → radio
`size-full` thành flex item thứ hai, đẩy chữ option tràn khỏi box. Fix: giữ
nguyên grid của Hyvä, restyle qua custom properties của component
(`--swatch-size/--swatch-px/--swatch-py/--swatch-radius`) + màu/border/selected
(`:has(:checked)` → #293e2d; disabled → dashed 45%).

Verify trên cat 49 (Haven Platform Bed, configurable size + color):
- Queen 64×32, King 49×32 — text **inside** box (overflow = false)
- 3 color dot 32×32 tròn, có màu thật (cream/tan/green — data mới của PM)
- Selected state viền đậm ✓ (card-haven-fixed.png)

## v4.6 (09-21) — 4 issue trước push QC

1. **Save admin vỡ layout (root cause thật tìm ra)**: PB save sinh thêm
   `#html-body [data-pb-style=HASH]{...}` — **ID-specificity CSS** từ model của
   nó (display/width/flex-direction...) đè mọi selector class/attr; cộng thêm
   16 selector `:has(...) > [column-group]` sai combinator (group nằm trong
   `[data-element="inner"]`, không phải con trực tiếp của row). Fix:
   importantize toàn bộ tầng strip-proof/unlayered (92+ !important) + sửa toàn
   bộ `>` → descendant. Verify trên content admin-saved thật: hero 1024, tiles
   6-col, promo 360×502, USP 4-col, split 2-col, 32 card, no h-scroll ✓.
2. **Flash sale ẩn khi hết countdown**: server-side (sale_end quá khứ →
   script ẩn row ngay khi parse) + runtime (Alpine tick đạt 0 → ẩn row, gồm cả
   heading/CTA). Verify: sale_end quá khứ → rowDisplay none ✓; content đã restore.
3. **Options card giống design**: text box min 48×32 (Queen 64×32/ King 49×32 —
   text inside), color dot 24px **single-border** (label border transparent),
   selected = viền #293e2d (bỏ inset shadow), disabled dashed 45%.
   (card-haven-polish.png)
4. **Video = Secomm UI widget `embed_a`**: directive
   `{{widget type="Secomm\UiWidget\Block\Widget\SecommUi" component="embed_a"
   schema_version="1" payload="<codec base64url>"}}` (Codec::encode; youtube
   nocookie + poster ytimg + nút play như design). Theme build thêm
   `@source "../../../../../app/code/Secomm/UiWidget/view/**/*.phtml"` (5 mức `../`
   — 4 hoặc 6 đều sai resolve). Row CSS key đổi sang
   `:has([data-secomm-ui-component="embed_a"])`. Verify: grid 16/9 full-width ✓.

**QUY TẮC BUILD MỚI**: thêm source/import cho module ngoài theme — path relative
từ `web/tailwind` = 5× `../` tới project root; chỉ cần `@source` (import css của
module chỉ khi dùng utility trang trí riêng).

## v4.7 (09-21) — ATC hidden + card gallery viewmodel + root-cause save-breakage

1. **Save admin vỡ layout (root cause thật, thay cho giả thuyết trước)**: PB save
   sinh `#html-body [data-pb-style=HASH]{display/width/flex...}` — **ID-specificity
   CSS** đè mọi selector; cộng 16 rule sai combinator (`>` — column-group nằm trong
   `[data-element="inner"]`). Fix: importantize tầng strip-proof/unlayered + `>` →
   descendant. Verify trên content admin-saved (03:53) ✓: mọi section đúng.
2. **ATC trên card**: dùng `hidden` class (design card không có ATC) — container
   `.hp-card-actions hidden`; hooks vẫn trong markup khi cần bật lại.
3. **Card gallery = viewmodel `Launchpad\Homepage\ViewModel\ProductGallery`**:
   batch data (addMediaGalleryData) reuse; nếu thiếu → ProductRepository full-get
   fallback. Kết quả: Cane (simple, 4 ảnh) = slider 4 slides + dots ✓.
4. **Haven Platform Bed slider=false = DATA**: card render configurable PARENT
   (pid 2144) — parent gallery chỉ có 1 ảnh (4 ảnh nằm ở child variant 2138).
   Slider kích hoạt khi product ≥2 ảnh. → PM thêm ≥2 ảnh vào gallery của
   configurable PARENT (Admin → Products → Haven Platform Bed → Images) thì card
   slide. Không phải bug frontend.
5. **Video = Secomm UI widget embed_a** (đã render: grid 16/9 + poster/nút play +
   youtube-nocookie). Theme build: `@source "../../../../../app/code/Secomm/
   UiWidget/view/**/*.phtml"` (5× `../` từ web/tailwind; 4/6 đều sai).

Full homepage verify: hero 1024 + dot progress running, tiles 180 (6-col), promo
360×502, USP 4-col, video embed, flash countdown, newsletter, 32 card, ATC
hidden ×32, no h-scroll, 0 pageerror ✓.

## v4.8 (09-21) — column-line fix (user chỉ ra root cause render sai)

Save PageBuilder **convert column-group → column-line** (round-trip converter) —
mọi selector strip-proof theo column-group mất tác dụng. Fix: 22 selector được
pair thành `[column-group], [data-content-type="column-line"]` — MỖI fragment
đều mang full scope `body.cms-index-index …` (lỗi đầu: fragment thứ hai
`[column-line]` không có scope → áp global + sai measured).

Class convention chốt (user): class đặt trực tiếp trong **CSS Classes (tab
Advanced)** của từng element PB — trường này SỐNG qua save; render sai còn lại
là do column-line conversion, đã phủ cả 2 content type.

Video = Secomm UI widget `embed_a` render **poster + nút play** (loading=auto,
x-data lazy — iframe mount khi click, probe iframe=false là đúng thiết kế).

Final verify (content admin-saved 04:27, không re-seed): hero 1024 + dot
progress, tiles 180 (6-col), promo 360, USP 4-col, video poster/play, flash
countdown, newsletter centered, 32 card ATC hidden, no h-scroll, 0 pageerror ✓.
