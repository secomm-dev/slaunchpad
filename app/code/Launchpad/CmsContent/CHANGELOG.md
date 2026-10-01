# Changelog

## 1.3.3 (unreleased — version bump khi unblock `setup:upgrade`, xem TASK-KMJV5Q)

- SLP-291 (TASK-KMJV5Q): legal links copyright bar footer (Terms & Privacy)
  edit được qua **Stores > Configuration > Launchpad > Footer > Legal Links** —
  4 field store-view scope (`terms_label` / `terms_url` / `privacy_label` /
  `privacy_url`); để trống = giữ fallback hiện tại (label dịch CSV + route tĩnh
  TASK-7EYJ4C) → 0 visual change khi chưa cấu hình. ViewModel `LegalLinks`
  (`ArgumentInterface`, require qua `$viewModels` registry của Hyva — engine
  inject cho mọi template); URL config: `http(s)://` passthrough, còn lại
  resolve qua `getUrl()`. Observer `admin_system_config_changed_section_launchpad_footer`
  clean `block_html` + `full_page` → storefront thấy ngay sau save (core 2.4.8
  không có cache.xml event-invalidation cho section custom). Output escape
  `$escaper` (XSS probe PASS). Không schema/patch → không cần `setup:upgrade`.

## 1.3.1 (2026-09-29)

- SLP-275 (TASK-7EYJ4C v4.3–v4.7): `SeedFooterBlocks` đổi store map theo
  **staging: store 1 = Tiếng Việt, store 4 = English; bỏ row store 0 fallback**
  (store view thiếu trong env bị skip qua `StoreManagerInterface` — local không
  có store 4 nên chỉ seed VI; store view nào không có row riêng sẽ không thấy
  footer block, by design). Content builders (`Model\FooterBlockContent`) sửa
  appearance theo config PageBuilder (column-group `default`, image
  `full-width`) — admin stage parse native thay vì fallback "HTML Code"
  (v4.3), cộng padding/rhythm mobile links accordion theo Figma (v4.6, CSS ở
  theme). Patch đã registered trong `patch_list` từ 1.3.0 nên không tự re-run;
  môi trường mới sẽ seed thẳng theo map mới.

## 1.3.0 (2026-09-28)

- SLP-275 (TASK-7EYJ4C): footer mock-up — newsletter chuyển từ homepage xuống
  footer, component footer = CMS block. Data patch `SeedFooterBlocks` seed 4
  identifier (`footer_newsletter` / `footer_links` / `footer_social` /
  `footer_trust_payments`) × 2 store (store 0 = EN fallback, store 1 = VI),
  idempotent theo (identifier, store) qua join `cms_block_store` — không dùng
  load-by-identifier (nguyên nhân duplicate `homepage-newsletter` 21/22).
  `footer_newsletter` bọc `{{block}}` `Newsletter\Subscribe` hiện có.
- SLP-275 v2 (cùng ngày, theo feedback user): content block đổi sang
  **PageBuilder markup** (Row / column-group / Heading / Text / Image / HTML)
  để admin sửa trực quan trong PB stage; anchor classes (`footer-links-group`,
  `footer-links-title`, `footer-links-list`, `footer-social-item`,
  `footer-trust-badge`, `footer-trust-payments`) đặt ở CSS Classes field.
  Builders tách ra `Model\FooterBlockContent` (dùng chung cho patch + reseed
  script). Section containers (bg/padding/width) + toàn bộ styling =
  **template/CSS-owned** (`footer.phtml` + `footer.css` selector cấu trúc,
  kèm counter cho bleed rules `body.cms-index-index` của homepage.css) —
  PB admin save không thể phá footer (strip-proof, pattern TASK-0NNZCW v4.5).
  Template `newsletter/subscribe.phtml` đổi sang Tailwind utilities trực tiếp
  (bỏ phụ thuộc class `lp-newsletter-*` CSS-owned của homepage.css) + responsive
  mobile (stack, nút full-width).

## 1.2.7 (2026-09-25)

- SLP-272 (TASK-JBHGNR): product card / PDP / Quick View hiển thị option hết
  hàng theo design. Data patch `EnableShowOutOfStockProducts` bật
  `cataloginventory/options/show_out_of_stock=1` (default scope) + invalidate
  indexer EAV / price / stock / inventory / catalogsearch — Hyvä disable option
  theo `jsonConfig.salable`. Theme: `swatches.css` style disabled = viền
  `--ds-border-gray-secondary`, chữ `--ds-text-gray-quinary`, gạch chéo 1px
  (dot màu giữ nguyên màu, gạch chéo trong dot); `homepage.css` override card
  theo cùng token, gỡ class mock-up `.hp-card-swatch*`; Quick View bỏ
  `opacity-40 pointer-events-none` để dùng chung style.
- SLP-272 CR1: color swatch selected — bỏ nền trắng sau dot (card / PDP /
  Quick View), viền selected là vòng tròn ôm quanh dot (card trước đó bo 6px
  do `.hp-card .swatch-option` → vuông bo góc).

## 1.2.6 (2026-09-25)

- BUG-QJWCNG: CTA các section homepage (Flash sale, Living Reimagined, rail
  "Go to Collection", video, editorial, blog) khớp Figma `2-10`: DS
  `btn-size-xl` (h48, padding 12/24, 22 bên phải vì có mũi tên), weight 500,
  mũi tên 16px nằm sau chữ (trước đó `::after` vùng chạm của `btn` làm mũi tên
  đè lên chữ). Variant theo section, không theo `button_type` PB: mặc định nền
  brand-500; rail brand-600; video outline brand-900; editorial nền trắng.
  Khôi phục `box-shadow` (outline + focus ring) mà `shadow-none` của
  `page-builder.css` đã tắt. Bỏ `color: #588f60 !important` của secondary
  (v4.10). Chỉ theme CSS (`homepage.css`), không đổi content / seed.
- BUG-QJWCNG CR1: nút ‹ › product rail (`.lp-nav-btn`) theo Figma — viền +
  chevron `--ds-primary-brand-500`, nền trong suốt, hover `--ds-primary-brand-50`,
  icon Heroicons outline `chevron-left/right` 24px (theme `carousel.phtml`, thay
  Lucide arrow 18px). Nút "Đăng ký" newsletter = DS `btn-size-l` (h44) + weight
  500 trên `btn btn-primary` sẵn có; bỏ màu `hp-*` / hex của hai nút — chỉ dùng
  token `--ds-*` trên `:root`.

## 1.2.5 (2026-09-25)

- Widget Flash Sale insert từ Admin không hiện: `widget.xml` không có parameter
  `template` (container trỏ tới option không tồn tại) → directive thiếu
  `template="…"`, block render rỗng. Thêm param `template` (default
  `Launchpad_CmsContent::product/widget/flash-sale.phtml`). Widget đã insert
  trước đó (thiếu `template`) cần mở lại form widget và Save/insert lại.
- Widget Flash Sale thêm option `title` (tuỳ chọn): heading nằm cùng hàng với
  countdown (`.lp-flash-head` / `.lp-flash-title` trong `homepage.css`, type
  scale giống PB heading homepage). Để trống = không render heading — homepage
  hiện tại (PB heading riêng) giữ nguyên.
- Flash Sale kéo được bằng chuột (desktop): root slider thêm `data-lp-drag` →
  `sliders-init.phtml` dùng chung `enableMouseDrag` của category slider (thả →
  trượt về mép card gần nhất; đã kéo thì không mở link card). `homepage.css`:
  cursor grab/grabbing, tắt snap + smooth scroll khi đang kéo. `enableMouseDrag`
  bỏ trạng thái kéo khi chuột đã nhả ngoài track (tránh hover làm cuộn track).
- Rail product widget (PB products carousel — theme override `carousel.phtml`)
  kéo được bằng chuột: root thêm `data-lp-drag`; `sliders-init.phtml` bật
  `enableMouseDrag` cho mọi `[data-lp-drag] > [data-track]` (idempotent). CSS
  drag chuyển sang `.hp-pb-track` (chung flash + rails).
- "Everyday more value": ẩn scroll track của rail ở mọi màn hình
  (`scrollbar-width: none` + `::-webkit-scrollbar`) — trước đó scrollbar
  classic 15px hiện dưới card (Chrome/Edge Windows).
- Hero slider: lớp mờ đáy ảnh theo Figma "Filter" (mobile `2151:16907`:
  trong suốt 65.5% → đen 48% ở đáy; desktop `2151:17108`: trong suốt 62.6% →
  đen 75% tại 84.9%) — `background-image` trên `.pagebuilder-overlay` của mọi
  slide (`homepage.css`), làm rõ title / description / CTA. Chưa áp lớp mờ
  đỉnh của design (dành cho header trong suốt đè hero — header hiện chưa đè).
- Product card: icon wishlist góc trên phải theo design (Product card
  `2151:17180` "Icon button"): sát góc (top/end 0), padding 8px, heart 24px,
  không nền / không bo tròn. Theme override
  `Magento_Catalog::product/list/wishlist/button.phtml` bỏ `btn p-2 rounded-full`
  (`btn` là @utility nên `.hp-card-wishlist .btn` ở layer components không đè
  được nền `--ds-bg-brand-soft-primary`) → class `.hp-card-wishlist-btn`.
- SLP-267 data patch `ConvertHomepageCategoryToSlider` giữ nguyên content từng
  store view (staging có page `home` riêng cho vi — store 1 — và en — store 4,
  đã dịch; patch chưa chạy trên staging). Trước: chỉ `getFirstItem()` (1 page),
  widget dựng từ 6 item seed cứng (label EN, URL `.html`, ảnh `.webp`) → page vi
  mất bản dịch + 4 tile admin thêm, còn heading "Danh Mục" (regex chỉ khớp
  "Category"), page còn lại giữ `.lp-tile` trong khi CSS/JS đã xoá. Nay: mọi page
  `home`; heading + items trích từ section của page; xoá heading theo vị trí
  (ngay trước column group); lỗi schema → giữ nguyên page + log. Kiểm chứng trên
  content staging (var/staging-home): vi 10 item / en 6 item khớp từng tile,
  phần ngoài Category byte-identical, 0 orphan style rule; apply() trong
  transaction (rollback) giữ store assignment, render đúng từng store.

## 1.2.4 (2026-09-25)

- SLP-271 (BUG-3XJSQG): "Living, Reimagined" — text đúng vị trí Figma. Ảnh
  (asset vuông, `height:auto`) không lấp cột media nên text bị đẩy xa ảnh
  (375: trống 175px dưới ảnh; 1920: trống 251px bên phải ảnh). `homepage.css`:
  ảnh `object-fit: cover` (crop giữ đáy như design) — mobile cao 535px, desktop
  tỉ lệ 940:620; cột text desktop bỏ padding/margin đáy → đáy button trùng đáy
  ảnh, text cách ảnh 32px. Chỉ theme CSS, không đổi content / seed.

## 1.2.3 (2026-09-24)

- SLP-269 (BUG-KATJXW): "Everyday more value" (PB columns `.lp-promo-card`)
  chạy như slider — `sliders-init.phtml` thêm mouse drag-to-scroll (thả → trượt
  tới mép card kế tiếp; đã kéo thì không mở link "→"); `homepage.css` thêm
  `scroll-snap` theo card + cursor grab/grabbing. Giữ nguyên columns/content.
- SLP-269 CR1: seed template — 4 column `.lp-promo-card` thêm `width:25%` trong
  `data-pb-style` (thiếu → PB stage tính 0%, section không chỉnh được trong Admin).
- SLP-269 CR2: nút "→" card promo do theme CSS sở hữu (mọi PB button type) — Admin
  sửa link không còn làm card mất nút tròn; seed template `<a>` = `pagebuilder-button-link`.

## 1.2.2 (2026-09-24)

- SLP-268 (BUG-118Z8T): hero slider — autoplay không còn kẹt pause sau khi chạm
  trên mobile. `hero-autoplay.phtml`: hover-pause chỉ cho chuột
  (`pointerType === 'mouse'`); touch pause khi `touchstart`, chạy tiếp khi ngón
  tay cuối rời (`touchend`/`touchcancel` trên `document`) — không phụ thuộc
  `pointerleave` (iOS Safari không luôn bắn). Nguyên nhân pause (hover / touch /
  tab ẩn) tách riêng, không ghi đè nhau.
- SLP-268: `homepage.css` — CTA hero theo Figma + DS mới: `btn-size-xl` (h48,
  Label L), nền `primary/brand-500`, hover brand-600, weight 500; title /
  description / button dùng `--font-sans` (Inter) thay stack mặc định của CMS JIT.
- SLP-268 CR1: hero kéo được bằng chuột (touch vẫn vuốt native) — thả tay quá
  ngưỡng (min 80px / 10% chiều rộng) → sang slide kế theo hướng kéo, dưới ngưỡng
  → về slide cũ; kẹp ở slide đầu/cuối; đã kéo thì không mở link slide.
  `homepage.css`: `.lp-hero-dragging` tắt snap + smooth scroll khi kéo.
- SLP-268 CR2: rê chuột lên hero hiện cursor `grab` (nút CTA vẫn `pointer`),
  đang kéo `grabbing` — chỉ áp cho thiết bị có chuột.

## 1.2.0 (2026-09-24)

- SLP-267 (TASK-HARDAR): homepage Category = widget `Secomm UI` → `categories_a`
  (1 PB **Text** element — double-click widget trong editor mở form đã điền sẵn) thay 6 PB columns `.lp-tile` — admin quản lý item
  (label/image/alt/url) trong form widget. Theme override template
  `Secomm/launchpad/Secomm_UiWidget/templates/components/categories/a.phtml`.
  - Data patch `ConvertHomepageCategoryToSlider` (idempotent, chỉ đổi khi page
    `home` còn block columns cũ / widget trong HTML Code element); dọn rule
    `data-pb-style` mồ côi (thiếu → Admin stage crash); seed template cập nhật.
  - `sliders-init.phtml`: bỏ JS dots viết tay cho columns; `[data-lp-tiles-slider]`
    dùng SnapSlider pager 1 dot / tile.
  - Slider tràn mép phải viewport mọi breakpoint; desktop 6 tile/khung, không nút ‹ ›
    (theme template + `homepage.css`).
  - Kéo chuột để cuộn category slider (`sliders-init.phtml`).

## 1.1.0 (2026-09-18)

- v4.3 (TASK-0NNZCW): newsletter = module block `Newsletter\Subscribe` (thay CMS
  widget); preference `ProductsList → FlashSaleList` (sale_end) — KHÔNG batch
  gallery trong createCollection (Elasticsuite plugin conflict — xem README);
  batch gallery trong templates. Card: bỏ compare, gallery slider + mini dots,
  swatch style theo design.

## 1.0.0 (2026-09-18)

- TASK-0NNZCW v4 (SLP-213): module khởi tạo.
  - Widget `FlashSaleList` (slider + countdown, option `sale_end`).
  - Block `Blog\Journal` (Magefan posts slider).
  - Data patch: CMS block `homepage-newsletter` (form subscribe).
