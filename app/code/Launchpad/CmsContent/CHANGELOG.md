# Changelog

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
