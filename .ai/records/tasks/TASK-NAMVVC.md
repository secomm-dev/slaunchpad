---
id: TASK-NAMVVC
type: task
title: 'Product List Layout Mock-up — PLP grid + list + empty state + product card thống nhất toàn site (SLP-235)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-235
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-09-30
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only theme layer (template + CSS + CSV) — không chạm §12 (payment/checkout/order/DB schema/security); rủi ro chính = item.phtml là card pipeline chung sitewide (homepage rails/flash + PB carousel + search + compare) → regression matrix bắt buộc
components:
  - app/design/frontend/Secomm/launchpad
source_areas:
  - hyva-theme-frontend
  - catalog-plp
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: working tree (chưa commit — dev branch anhchong)
last_verified: 2026-09-30
supersedes: []
---

# [SLP][TASK-NAMVVC] Product List Layout Mock-up — PLP grid + list + empty state + product card thống nhất toàn site (SLP-235)

<!-- External ticket: SLP-235 "Product List Layout Mock-up". Scope: apply design cho section
     product list + grid (KHÔNG header/footer); product card áp cho TẤT CẢ các trang kể cả homepage.
     Design Figma 5MpBw9VaNgFdct3UDWABuV:
     - Product grid desktop : node 2330-39642 (1440×4470)
     - Product list desktop : node 2622-41324 (1440×7571)
     - Empty state          : node 2330-39793 (1440×1995)
     - Mobile grid          : node 2330-39378 (375×3517)
     - Filter panel mobile  : node 2330-39556 (375×750)
     - Sort dropdown open   : node 2330-39557 (320×332)
     Ticket analysis 09-30 (5 agent: figma grid/list, codebase plp-map/contracts/calibration). -->

## Ticket + AC

**Ticket**: SLP-235 — Product List Layout Mock-up. Apply design cho section product list và grid,
không bao gồm header/footer. Apply product card cho tất cả các trang bao gồm cả homepage.

**Decisions chốt bởi user 09-30** (session analysis):
1. **Homepage card = giống product grid** — card thống nhất, ATC visible toàn cục (bỏ `hidden` của SLP-213).
2. **"Recently view" slider: tạm chưa làm** — out of scope ticket này.
3. **Compare icon: bật lại** — net-new trên card (container `catalog.list.item.addto` hiện rỗng).
4. **Filter: chỉ apply design** — giữ behavior navigate-immediate của Magento/Smile (KHÔNG batch-apply,
   KHÔNG live count); CTA mobile "Show results" chỉ đóng panel.
5. **Sort: giữ default Magento** — restyle-only, không thêm option ("Popular" bỏ), giữ behavior
   select + direction toggle hiện có.
6. **Empty state: chỉ làm category PLP** — search page sẽ có design sau (search chỉ nhận card mới
   qua pipeline chung).

**AC**:
1. AC-001 — PLP grid mode desktop 1440: 3 cột (card 321 / col-gap 24 / row-gap 40), card đúng anatomy
   design (badge → brand + rating → name → size chips → swatches → giá sale xanh + old price
   strikethrough → In stock → hàng [ATC flex-1][heart][eye][compare]), tokens màu/typography/radius
   theo spec Figma; toolbar (mode switch trái + sort phải) + pager 3 khối đúng spec.
2. AC-002 — PLP list mode desktop: row full-width 1 cột, ảnh vuông 1:1 bên trái, content phải có
   description clamp ~2 dòng (list-only), ATC compact (w=200) + 3 icon inline bên phải cùng hàng,
   row gap 32, không separator.
3. AC-003 — Mobile 375: grid 2 cột; toolbar = nút "Filters (3)" + "Sort by" (KHÔNG mode switcher —
   giữ vendor `invisible md:visible`); filter panel full-screen theo node 2330-39556 (header Filter +
   X, chips, accordion, CTA sticky đáy — click CTA chỉ đóng panel); sort dropdown open state theo node
   2330-39557 (selected nền `#45744c` chữ trắng); list mode mobile = stack + scale font theo mobile grid.
4. AC-004 — Empty state category: icon tròn + "No products found" + description + nút "Clear filters"
   (primary) + "Back to %1" (outline, %1 = category cha); render từ override `list.phtml` nhánh empty.
5. AC-005 — Card mới áp dụng toàn site (homepage rails/flash + PageBuilder carousel + search + compare
   page): ATC visible; AJAX add-to-cart qua Monsoon hoạt động (không reload, mở QuickCart drawer theo
   config); Quick view mở từ card trên mọi trang có block `quickview.modal`; wishlist + compare hoạt động.
6. AC-006 — Toolbar/pager/sort/limiter restyle giữ nguyên behavior: `initToolbar`/`changeUrl`/params
   `product_list_mode|order|dir|limit|p`, default sort nguyên bản, config limiter nguyên bản.
7. AC-007 — Filter sidebar (desktop) + panel mobile giữ behavior navigate-immediate; active-filter chips
   + "Clear all" hoạt động theo anchor hiện có; sửa skip-link target `#products-list` → `#product-list`.
8. AC-008 — i18n (BR-001): mọi string mới có trong CẢ `i18n/vi_VN.csv` + `i18n/en_US.csv`; vi render
   đúng, en identity; `cache:clean translate` as secomm.
9. AC-009 — Regression PASS: homepage rails/flash/PB carousel render đúng với card mới, PDP
   related/upsell slider, search page (card + filter), cart drawer; 0 console error; không overflow-x;
   store vi (+ en qua emulation CLI — store-switch HTTP chết local LL-0011).
10. AC-010 — Release: build Tailwind + cp styles.css sang `pub/static/frontend/Secomm/launchpad/
    {vi_VN,en_US}/css/` (F9 trap; en_US css hiện MISSING trong pub/static); evidence + estimation row.

## Embedded Mini-Spec

### Goal

PLP (category) hiển thị đúng design: grid 3 cột / list 1 cột / empty state, filter sidebar + panel
mobile, toolbar + pager; product card thống nhất (ATC visible) trên mọi trang render card.

### Hiện trạng (verify 09-30 — plp-map + contracts agent)

- **Card đã có từ SLP-213** (commit `ad402ae5`): `Magento_Catalog/templates/product/list/item.phtml`
  (307 dòng) là template duy nhất cho mọi card qua `Hyva\ProductListItem` VM `getItemHtml()`;
  ATC có trong markup nhưng bị `hidden` cứng (dòng 190, class `hp-card-actions hidden`); wishlist
  overlay top-right OK; quickview button OK (dispatch `open-quickview` + sku); swatches qua
  `getProductDetailsHtml` → `Magento_Swatches::product/listing/renderer.phtml` (theme swatch-item.phtml);
  gallery mini-dots qua `Launchpad_CmsContent\ProductGallery` + plugin batch `ListProductGallery`.
  **Thiếu**: compare (container `catalog.list.item.addto` rỗng), badge "Exclusive" (chỉ có New arrival
  + chip discount `-%1%`), ATC visible.
- **Toolbar/pager 100% vendor Hyvä** — chưa override lần nào (`toolbar.phtml` + viewmode/sorter/
  limiter/amount + `Magento_Theme::html/pager.phtml`). Config DB trống → default: `list_mode=grid-list`,
  `grid_per_page=12` (12,24,36), `list_per_page=10`, `default_sort_by=position`.
- **Layered nav = Smile Elasticsuite Hyva module** (`catalog/layer/view.phtml` — Alpine accordion per
  filter + mobile collapse <768px; `filter/attribute.phtml` — checkbox 16px + count, anchor
  navigate-immediate). Chưa có active-filter chips styled, chưa có "Clear all".
- **Grid classes hiện tại** (vendor `list.phtml:59-61`): `grid-cols-1 sm:grid-cols-2 md:grid-cols-1
  lg:grid-cols-2 xl:grid-cols-3` — lệch design (cần 3 cột từ lg).
- **Empty state** = vendor `list.phtml:29-34` (`message info empty`, text cũ); search = core
  `Magento_CatalogSearch::result.phtml` (template khác — out of scope design mới).
- **Recently viewed**: vendor block `recently_viewed_products.default_widget_plp` ifconfig
  `catalog/recently_products/show_on_plp` không có field admin + 0 row DB → chưa từng render.
  Tạm chưa làm (decision #2).
- **CSS**: card rules nằm `web/tailwind/theme/homepage.css:36-253` (global `.hp-card*`); PLP css =
  `page-catalog.css` (34 dòng); theme tree CLEAN, các file dirty trong repo không liên quan PLP.
- **Sót debug**: `item.phtml:145` comment `<!-- gallery-debug ... -->` in ra production — dọn trong scope.

### Expected Behavior

**Grid card (desktop, card 321×597, radius 8, shadow xs)**: media 1:1 bg `#f8f6ee` + badge absolute
top-left (pill radius 12, px8/py4, 12px Medium) — New arrival `#588f60`/chữ `#cfe3d1`, Sale `-%`
`#c10007`/trắng, Exclusive `#bc9e53`; brand 14 Regular `#99a1af` (flex-1) + rating: 1 star `#ffb900`
20px + score 14 Medium `#1e2939` + "(n)" 14 `#588f60`; name 16 Medium `#101828` (clamp 1 dòng); size
chips (text swatch) px12/py6 radius 6 border-2 — selected bg `#f4f9f4`/border `#588f60`/chữ `#304b34`,
default border `#e5e7eb`, OOS bg `#f3f4f6`/chữ `#99a1af` + strike 45°; color swatch dot 32px — selected
ring `#588f60` + gap trắng 2px, OOS opacity-50 + X; price 20 Medium `#588f60` + old price strikethrough
14 `#99a1af`; stock dot 12px `#00a63e` + "In stock" 16 Regular; action row: ATC flex-1 h≈36 bg
`#45744c` radius 6 chữ trắng 14 Medium + cart icon 16px SAU text, rồi 3 icon button 32px tròn bg
`#e7f1e8` (heart/eye/scale) gap 4.

**List row (desktop 1014×437, radius 12)**: ảnh 437×437 vuông trái, content flex-1 px24/py8; thêm
**description clamp 48px (~2 dòng) 14/20 `#6a7282`** (list-only); name single-line ellipsis; ATC
compact w=200 + 3 icon inline phải cùng hàng; row gap 32, không separator.

**Toolbar h50**: mode switcher (border `#d1d5dc` radius 8 p8, 2 nút 32px grid/list; ẩn mobile — giữ
vendor) + sort ("Sort by: …" 16 Medium, border radius 6; dropdown open: selected bg `#45744c` trắng).
**Pager h76 px24/py16**: trái "Items %1 to %2 of %3"; giữa arrows 44px + page buttons px20/py10
radius 6 (active bg `#e7f1e8` chữ `#293e2d`); phải "Show" + limiter w80.

**Sidebar w322 (gutter 24)**: "Filter" 24 Medium `#293e2d`; "Active filters (N)" + "Clear all" 14
Medium; chips bg `#f4f9f4` radius 4 p8 12 Medium + X; accordion rows 18 Medium `#293e2d` + chevron,
không divider; checkbox row h40 + count 12 `#6a7282` + checkbox 24×24 radius 4.
**Mobile**: 2 cột; toolbar = nút "Filters (N)" (icon sliders) + Sort; filter panel full-screen
(header + X + chips + accordion + CTA sticky "Show results" — đóng panel); list mobile = stack +
scale font.

**Empty state category**: icon tròn placeholder + "No products found" (heading lớn) + description +
"Clear filters" (primary `#45744c`) + "Back to %1" (outline) — %1 = tên category cha (parent category);
fallback khi không có parent: ẩn nút Back (không dùng label "Lighting" trong mock — placeholder).

**H1/breadcrumb**: H1 heading-1 60/72 Bold -1px `#101828` (mobile scale theo node 2330-39378);
breadcrumb 14 Medium + chevron 12.

### Constraints / Rules

1. **ATC form contract (Monsoon_HyvaAjaxAddToCart)**: giữ NGUYÊN `form.product_addtocart_form`
   (formkey block + hidden input product/options) và nút ATC `<button type=submit>` NỘI TIẾP form;
   KHÔNG đổi class form; KHÔNG re-add `#product_addtocart_form` vào config selector DB (PDP
   double-submit). Form phải còn `<button>` con (loader bind button đầu tiên).
2. **Rating guard `is_object`** (LL-0030, item.phtml:259-270) giữ nguyên — bỏ = 500 toàn PLP.
3. **Swatch**: restyle qua custom properties `--swatch-*`; KHÔNG đè display `.swatch-option` (vỡ
   stacking radio/label — comment homepage.css:73-77); giữ `:disabled="optionIsDisabled"` +
   `data-validate`/`data-validation-container` (SLP-183).
4. **Price**: giữ `x-data="initPriceBox()"` bọc price box + event `update-prices-<productId>` +
   BlockJsDependencies price-box; không đổi payload format.
5. **Item block cache** (`product_list_item_block_cache_enabled` = true, 3600s): KHÔNG branch card
   theo customer/session/request trong template — variant grid/list qua `view_mode`, trang qua CSS
   container scoping hoặc layout args.
6. **Load-bearing, không đụng**: `Hyva_SmileElasticsuite/layout/hyva_catalog_category_view.xml`
   (compat fix swatch renderer RuntimeException), `Magento_Swatches/templates/product/swatch-item.phtml`
   (throws khi thiếu option data), layout args `hyva_js_block_dependencies` wishlist/compare
   (`catalog_list_item.xml:16-21`).
7. **Compare net-new**: dùng `initCompareOnProductList().addToCompare(<id>)` (JS đã render nhờ
   dependency trên); verify presence ở context widget (homepage rails/flash) — nếu thiếu, thêm
   dependency cùng cơ chế (không copy JS).
8. **Tailwind v4 CSS-first**: không `tailwind.config.js`; class static trong `@source` scope (theme
   phtml/xml đã scan sẵn); template mới nằm ngoài theme → thêm `@source` (precedent
   `Launchpad_CmsContent`, tailwind-source.css:24-29); không concatenation động.
9. **i18n BR-001**: string mới vào CẢ 2 CSV (vi_VN + en_US mirror) + `cache:clean translate` as
   secomm; `.phtml __()` là server-side — không cần SCD.
10. **Build/deploy traps**: CLI/`bin/magento` LUÔN as secomm; sau `npm run build` phải cp
    `web/css/styles.css` → `pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/` (F9 — rm bản
    stale trước); CSV check trailing newline; chạy verify store vi qua HTTP, en qua emulation CLI.
11. **`.columns` cap**: PLP là 2columns-left — scoping `body.catalog-category-view` cho rule phá cap
    (precedent SLP-160); audit ngược homepage.css bleed khi đụng card rules (F13/F14 footer).
12. **Design placeholder KHÔNG copy nguyên văn**: checkbox rows phẳng giữa accordion + "Top seller"
    trùng 2 lần; "Releventce" typo + duplicate "Price (High to low)" trong sort mock; "Show 6" mobile;
    "Back to Lighting" → dùng tên category cha thật; CTA "Show (##) results" → "Show results"
    (restyle-only không có live count — **wording TBD chờ PM duyệt**).

### Out of Scope

- Header + footer (SLP-275 riêng); **"Recently view" slider** (tạm chưa làm — decision #2; vendor
  block vẫn không render như cũ); **search page design tổng thể + empty state search** (chờ design
  sau — search chỉ nhận card mới tự động qua pipeline); batch-apply filter + live count; sort options
  mới ("Popular" bỏ — giữ default); mobile list-mode dedicated design (scale font); PDP; Admin/store
  data (attribute labels, category names, sort option labels per store); mọi area §12.

### Acceptance Criteria

Xem mục **AC** đầu record (AC-001..AC-010) — testable, mỗi AC có bước verify trong plan.

## Approach (3 wave — chi tiết trong plan)

1. **Wave 1 — Card (~4-5h)**: item.phtml un-hide ATC + action row 2 variant (grid flex-1 / list
   compact) + compare icon net-new + badge Exclusive + tokens card; tách card CSS nếu cần; dọn
   gallery-debug; verify AJAX ATC + quickview + wishlist + compare + regression homepage/PB.
2. **Wave 2 — PLP wrapper + toolbar + filter (~4-5h)**: override `list.phtml` (grid 3 cột + empty
   state), toolbar 5 templates + pager restyle giữ contract, Elasticsuite layer view/attribute/state
   restyle + chips + "Clear all" + panel mobile (Alpine overlay + X + CTA) + sort dropdown + nút
   Filters mobile; fix skip-link.
3. **Wave 3 — Polish + i18n + release (~3-4h)**: H1/breadcrumb, empty-state actions (Clear filters
   strip filter params, Back to %1), CSV vi/en, build + F9 cp, Playwright matrix, evidence, estimation.

## Evidence

`.ai/evidence/TASK-NAMVVC/` (sẽ tạo khi implement — RESULTS.md + screenshots + probe scripts).

## Update 2026-09-30 — IMPLEMENT DONE (3 wave, chờ TL review)

- **Wave 1 — Card**: item.phtml rewritten (badge Sale/New/Exclusive, brand+rating row, name truncate,
  price 20px green + old price, stock dot #00a63e, action row [ATC][wishlist][quickview][compare] —
  ATC submit ĐỨNG ĐẦU form cho Monsoon loader; dọn gallery-debug). Wishlist button.phtml icon 16px
  + hp-card-action-btn. Card CSS tách homepage.css:36-253 → **card.css** (restyle theo spec Figma).
- **Wave 2 — PLP + toolbar + filter**: override list.phtml (grid-cols-2 lg:grid-cols-3, empty state
  icon+text+Clear filters+Back to %1 qua `$block->getLayer()->getCurrentCategory()` — VM
  CurrentCategory không được set() nên không dùng được), toolbar.phtml (+ nút Filters mobile dispatch
  `open-filters`), sorter.phtml (+ label "Sort By"), Elasticsuite view.phtml (panel mobile full-screen
  + scrim + X + CTA "Show results", Clear all, skip-link fix #product-list), attribute.phtml
  ([label][count][checkbox 24px]), state.phtml (chips). **DEVIATION plan: viewmode/limiter/amount/pager
  = CSS-only restyle** (vendor structure khớp design, giảm diff-surface upgrade).
- **Wave 3 — i18n + regression**: +10 key/file CSV (wording VI chờ duyệt), build + F9 cp vi/en +
  cache:clean (translate/layout/block_html/full_page) as secomm. Playwright **W1 16/16 + W2 22/22 +
  W3 regression PASS** (empty state VI/URL sạch/Quay lại %1, compare POST+redirect uenc, wishlist
  request, quickview modal, ATC +1, pager ?p=2, homepage 32 card 0 overflow, PDP, search) — chi tiết
  `.ai/evidence/TASK-NAMVVC/RESULTS.md` + 10 screenshots + 5 probes.
- **Flags chờ TL/PM**: badge priority (Sale>New>Exclusive), attribute `exclusive` data-driven (chưa
  tồn tại — badge render khi PM tạo), name truncate 1 dòng, sort dropdown open = native popup,
  mode-switcher active = #f4f9f4, wording VI chờ duyệt, EN store = QC demo (LL-0011), deviation
  CSS-only toolbar/pager.

## Update 2026-09-30 — Feedback round 1 (5 fix, DEV DONE chờ TL review)

1. **List mode 2 item/hàng**: root cause = `grid-cols-2` base + `grid-cols-1` trong nhánh list
   (2 utility trùng property — conflict cascade, list thắng nhầm grid-cols-2; IDE đã warn
   cssConflict). Fix: đưa `grid-cols-2` vào nhánh grid, list nhánh riêng `grid-cols-1` —
   không còn class conflict. Verify: list `?product_list_mode=list` = 1 cột width 1008.
2. **Custom sort dropdown** (Figma 2330-39557): native `<select>` thay bằng Alpine dropdown —
   trigger bordered "Sắp xếp theo <label> ⌄" (chevron xoay khi mở), menu absolute với option
   đang chọn bg `#45744c` trắng; click option gọi `changeUrl('product_list_order', …)` qua
   Alpine scope chain của toolbar (contract giữ nguyên); `@click.outside` + Escape đóng.
   Verify: menu mở/đóng, chọn Giá → `?product_list_order=price`, trigger value cập nhật.
3. **Sao rỗng khi không rating**: bỏ fallback `summaryHtml` (5 sao rỗng của Hyvä) — render
   cluster chỉ khi score > 0; giữ lời gọi `getReviewsSummaryHtml` cho side-effect (populate
   rating data). Verify: Luna Wall Fireplace (không rating) 0 svg sao; 12/12 card bags có
   rating vẫn hiện.
4. **Card gallery không drag**: `enableMouseDrag` (SLP-267) chỉ wire cho tiles + `[data-lp-drag]`
   — wire thêm cho `[data-lp-card-slider]` + **CSS thiếu**: `.is-dragging` chỉ định nghĩa cho
   `.lp-cat-track`/`.hp-pb-track` → thêm `.hp-card-gallery.is-dragging { scroll-snap-type: none;
   user-select: none }` vào card.css (không có rule này Chrome re-snap scrollLeft ngay mỗi
   pointermove → drag "chết"). Verify: scrollLeft đổi sau kéo chuột + click-sau-kéo không điều
   hướng (suppression của enableMouseDrag).
5. **Pagination** (Figma 2330-39756): override mới `Magento_Theme::html/pager.phtml` — page/jump
   h-11 radius-6 16px medium, current = bg `#e7f1e8` không border, arrows 44×44 radius-6,
   disabled opacity-70, bỏ `btn btn-secondary rounded-3xl` (utility không đè được bằng CSS
   layer — cùng pattern wishlist button). Logic 100% giữ nguyên (URLs, anchor, jump, aria).
   Verify: class + current tint trên `?p=2`.

## Update 2026-09-30 — Feedback round 2 (3 fix, DEV DONE chờ TL review)

1. **"Bộ lọc đang áp dụng (N)" hiện khi 0 filter** (cả nút Filters mobile): root cause —
   `Navigation::getActiveFilters()` của Smile trả về **INDEX CÁC FACET ĐANG MỞ RỘNG** (N facet
   đầu theo config `default_expanded_facets_count` + facet có request param), KHÔNG phải filter
   đang áp dụng → trang sạch vẫn "3". Fix cả 2 chỗ (view.phtml + toolbar.phtml): count =
   `getChildBlock('state')->getActiveFilters()` (block State = ground truth). Verify: trang sạch
   ẩn row + nút không count; `?activity=Gym` → "(1)" + 1 chip + nút "Bộ lọc (1)".
2. **Grid/list icon vỡ**: `.modes` `w-20` (80px) < content (2 nút 32px + gap 16 + padding 16 +
   border 2 = 98px) → overflow cắt; thêm nữa icon background-image 24px của vendor vẽ top-left
   trong nút 32px. Fix CSS: `width: fit-content` (auto bị grid stretch 238px) + icon
   `background-size: 16px; background-position: center` (design icon 16px giữa nút 32px).
   Verify: w=98 fit đúng, icon centered.
3. **Tick attribute chọn**: cascade conflict — box mang `bg-white` VÀ Alpine thêm
   `bg-hp-brand-dark` (cùng layer utilities, thứ tự stylesheet quyết định → trắng thắng) → tick
   trắng trên nền trắng = vô hình (svg opacity=1 nhưng không thấy). Fix: bỏ `bg-white` +
   `border-[#d1d5dc]` khỏi class base trong attribute.phtml, default màu chuyển sang
   `.lp-filter-item-box` ở `@layer components` trong page-catalog.css (utilities THẮNG components
   layer khi selected); path check thêm `stroke="white"` inline (không phụ thuộc utility).
   Verify: `?activity=Gym` → box rgb(69,116,76) + border xanh + tick opacity 1.

## Update 2026-09-30 — Feedback round 3 (mobile style theo 2330-39378, DEV DONE)

1. **ATC icon-only mobile**: dưới md nút ATC = vuông 36px icon cart (text `hidden md:inline`,
   aria-label giữ nguyên); ≥md grid = flex-1 full text như cũ, list mode `w-[200px]` không đổi
   (list chỉ tồn tại ≥md). CSS: `.hp-btn-atc` base w-9 + media md mở rộng.
2. **Title 1 hàng + "…"**: root cause — anchor `.hp-card-name` mặc định inline → `truncate`
   vô hiệu → title dài wrap. Fix: thêm `block` vào rule card.css.
3. **Gap mobile quá to**: `gap-x-6 gap-y-10` (24/40 là số desktop) áp cả mobile → responsive
   `gap-x-3 gap-y-4 md:gap-x-6 md:gap-y-10`.
4. **2 nút Bộ lọc/Sắp xếp 1 hàng**: filters button flex-1 + text wrap 2 dòng. Fix CSS mobile:
   `white-space: nowrap` + font 14px + padding-inline gọn + `.lp-sorter-value` max-width ellipsis
   + ẩn nút đảo chiều sort (design mobile không có) + gap 8px. Verify: sameRow, không overlap,
   "Bộ lọc" 1 dòng.
Content padding card mobile giảm `px-3 pb-3` (md giữ px-4). Verify round3.js 6/6 PASS
(375: toolbar 1 hàng, ATC 36×36 text ẩn, gap 12/16, title block+nowrap; 1440: ATC 168px giữ text).

## Update 2026-09-30 — Feedback round 4 (pagination mobile theo 2330-39394, DEV DONE)

- **Root cause vụ "3 khối 1 hàng"**: CSS mobile r3 `display:flex` áp cho CẢ bottom toolbar
  (cùng class `.toolbar-products`) → grid col-span/order của vendor vô nghĩa, amount + limiter
  xích thành 1 hàng với pager. Fix: template toolbar.phtml thêm class phân biệt
  `toolbar-top` / `toolbar-bottom` (`getIsBottom()`); rule flex mobile scope lại chỉ
  `.toolbar-top`.
- **Bottom mobile = 2 hàng ghost theo design**: pages hàng 1 (page/arrow/jump
  `border-color + background transparent`, `padding-inline .75rem`), current giữ tint
  `#e7f1e8`; amount trái + limiter phải hàng 2 (grid mặc định — amount span-2, limiter
  span-2); `row-gap: .75rem`.
- Desktop giữ nguyên border `#d1d5dc` (design 2330-39756); current vẫn border-transparent.
- Verify round4.js 6/6 PASS (grid 2 hàng, ghost transparent mobile, tint current, desktop
  border #d1d5dc — probe đầu sai 2 chỗ: so sánh top thay vì center trên items-center, và hit
  nhầm current button). Chưa commit — chờ TL.

## Update 2026-09-30 — Feedback round 5 (breadcrumb + page title theo 2330-39701/39540, DEV DONE)

- **Override mới** `Magento_Theme::html/breadcrumbs.phtml`: separator "/" → chevron 12px,
  breadcrumb 14px Medium #101828 (item cuối cùng màu đen — bỏ text-fg-secondary của vendor),
  bỏ bg-surface/shadow-sm; ld+json schema giữ nguyên.
- **Root cause H1 sai scale từ wave 3**: selector cũ
  `.page-title-wrapper.product .page-title` KHÔNG khớp DOM (wrapper thật =
  `div.container.flex` con trực tiếp của `.page-main`) → utilities text-3xl/lg:text-4xl
  (36/40px) thắng suốt. Fix selector `.page-main .page-title` + tracking design **-1px**
  (trước dùng -0.025em = -1.5px @60); mb-0.
- **Border-bottom khối title**: `.page-main > .container.flex` border-bottom #e5e7eb +
  pb-24px (khớp design Frame 6: block pb-24 + border-bottom).
- Verify round5.js 7/7 PASS (60px/700/-1px/mb-0 desktop, 40px mobile, chevron svg,
  trắng-sạch breadcrumb, border #e5e7eb + pb 24). Chưa commit — chờ TL.

## Update 2026-09-30 — Feedback round 5b (sai khoảng cách breadcrumb/title, DEV DONE)

- **Root cause**: `.page-main` margin-top **32px** của theme → gap breadcrumb→H1 = 32px
  (design Frame 6: breadcrumb 44 + **gap 12** + H1 72 + pb **25** = 153px).
- Phát hiện song song: user đã tự override `Magento_Theme::html/title.phtml` (wrapper
  `md:border-b md:border-[#e5e7eb] md:pb-6`, H1 `page-title container`) — utilities chưa
  build lúc probe (border width 0). Đồng hồ sơ: XÓA rule r5 của tôi
  (`.page-main > .container.flex` — dead vì wrapper không còn class container), giữ
  utilities trong title.phtml làm SSOT; build lại CSS.
- Fix: `page-main margin-top: 0.75rem` scoped catalog/search; section product-list
  `md:pt-10` (gap border→toolbar = 40px theo design; giữ `md:pb-8`; mobile giữ 0 theo
  chỉnh user).
- Verify round5b.js: desktop gap 12/72/25/border #e5e7eb 1px/toolbar 40 PASS (25 = đúng
  design, assertion ban đầu ghi nhầm 24); mobile gap 12 PASS.
- ENV: `pub/static/frontend/Secomm/launchpad/` bị WIPE lần 3 trong ngày (17:29, root
  deploy — vi_VN mất sạch, en_US root-owned) — đã tái tạo + chown. Cần DevOps chốt lại
  quy trình deploy static trên local.
