# Changelog

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
