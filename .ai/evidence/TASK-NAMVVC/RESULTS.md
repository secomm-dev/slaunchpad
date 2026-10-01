# TASK-NAMVVC (SLP-235) — Product List Layout Mock-up — Verification Results

Ngày: 2026-09-30 · Env: local dev (store vi qua HTTP; en = emulation/QC demo — LL-0011) · Build: Tailwind rebuild + cp pub/static vi/en (F9) + cache:clean (layout/block_html/full_page/translate) as secomm.

## Suites (Playwright — /tmp/pw-cal, probes trong evidence dir)

### Wave 1 — Card (wave1.js + t2-counter.js) — 16/16 PASS
- PLP 12 card: ATC visible trong action row (in-flow, y=833 không còn là overlay media), 3 icon
  (wishlist/quickview/compare) đủ, card trắng radius 8, 0 `gallery-debug`, sale badge render
  (1 product giảm giá).
- ATC AJAX: không navigation, URL giữ nguyên, counter `[x-text="summaryCount"]` 0→1, drawer/dialog
  mở theo config `checkout/options/ajax_cart_open_after_add_to_cart`.
- Homepage: 32 card ATC visible, không overflow-x (delta −7).

### Wave 2 — PLP + toolbar + filter (wave2.js + w2b.js) — 22/22 PASS
- Grid 3 cột @1440 (vendor cũ 1/2/1/2/3 → `grid-cols-2 lg:grid-cols-3`), mobile 375 = 2 cột
  (design 2330-39378).
- Toolbar mobile: nút "Filters (N)" (count = active filters từ catalog.leftnav) + sorter "Sort By"
  bordered control + label; mode switcher ẩn mobile (display:none), visible desktop.
- Filter sidebar: heading "Filters" + "Active filters (N)" + "Clear all" (URL category sạch —
  `$block->getLayer()->getCurrentCategory()`; VM Hyvä CurrentCategory không được ai `set()` nên
  KHÔNG dùng được), accordion restyle (không card border), item row [label][count "(n)"][checkbox
  24×24], skip-link đã fix `#products-list` → `#product-list` (bug sẵn có của vendor).
- Mobile panel: mở qua event `open-filters` → `.lp-filter-panel-open` = fixed full-screen + scrim +
  header X + CTA "Show results" (restyle-only: đóng panel, filter navigate-immediate giữ nguyên);
  X và CTA đều đóng; sidebar ẩn hoàn toàn trên mobile khi panel đóng (x-show `!isMobile || panelOpen`).
- Desktop: panel inline bình thường; filter item = anchor navigate-immediate giữ behavior.
- Locator note: pager container KHÔNG có class `.pager` (nav = `.pages`) — probe dùng đúng selector.

### Wave 3 — Regression tổng (wave3.js + w3b/w3c) — PASS
- Empty state (GIÁ filter 74.99-75 → 0 products): icon + "Không tìm thấy sản phẩm" + desc +
  "Xóa bộ lọc" (URL sạch) + "Quay lại %1" (category cha); VI render đúng (cache:clean translate).
- Compare e2e: POST `catalog/product_compare/add` + redirect về PLP (uenc) — vendor contract.
- Wishlist: request `wishlist/index/add` fired (cần scroll vào view trước — `x-defer="intersect"`
  init timing; behavior vendor bình thường).
- Quickview: modal mở, không điều hướng (SLP-157 regression).
- ATC card 2: counter +1. Pager: `?p=2` navigate PASS (w3c.js).
- Homepage: 32 card + 0 overflow. PDP: load + ATC form present. Search: card + filter sidebar render.
- **0 pageerror mọi page**; mobile card ATC trong viewport (x=24, w=91 @375).

## Thay đổi code (11 files: 5 sửa, 6 mới)

| File | Loại |
|---|---|
| `Magento_Catalog/templates/product/list/item.phtml` | sửa — card SLP-235 (un-hide ATC, action row 4 nút, badge Sale/New/Exclusive, brand+rating row, xóa gallery-debug) |
| `Magento_Catalog/templates/product/list.phtml` | **override mới** — grid 2/3 cột, empty state (icon + text + Clear filters + Back to %1) |
| `Magento_Catalog/templates/product/list/toolbar.phtml` | **override mới** — + nút Filters mobile (event `open-filters`, count active filters) |
| `Magento_Catalog/templates/product/list/toolbar/sorter.phtml | **override mới** — + label "Sort By" |
| `Magento_Catalog/templates/product/list/wishlist/button.phtml` | sửa — icon 16px + class hp-card-action-btn |
| `Hyva_SmileElasticsuite/.../layer/view.phtml` | **override mới** — panel mobile + Clear all + skip-link fix |
| `Hyva_SmileElasticsuite/.../layer/filter/attribute.phtml` | **override mới** — item [label][count][checkbox 24px] |
| `Magento_LayeredNavigation/templates/layer/state.phtml` | **override mới** — chip markup |
| `web/tailwind/theme/card.css` | **mới** — card rules tách từ homepage.css + restyle |
| `web/tailwind/theme/page-catalog.css` | sửa — toolbar/pager/sidebar/panel/title (unlayered) |
| `web/tailwind/theme/index.css` + `homepage.css` | sửa — import card.css / remove card section |
| `i18n/{vi_VN,en_US}.csv` | sửa — +10 key/file (wording VI chờ TL duyệt) |

Toolbar/pager/viewmode/limiter/amount = **CSS-only restyle** (vendor structure khớp design) — ít hơn
plan (plan ghi override 5 file; thực tế chỉ toolbar + sorter override, còn lại CSS) — deviation
đã ghi, giảm rủi ro re-diff khi upgrade.

## Flags chờ TL/PM

1. **Badge priority**: Sale -% > New arrival > Exclusive (mỗi card 1 badge) — 3 dòng code nếu đổi.
2. **"Exclusive" = data-driven**: attribute `exclusive` (tên có thể đổi qua block arg
   `exclusive_attribute`); attribute CHƯA tồn tại trong catalog → badge chỉ render khi PM tạo
   attribute + set value (không migration/DB change trong change set).
3. **Name truncate 1 dòng** (theo grid spec extract); list-agent từng đọc "grid wraps" — 1 dòng
   nếu PM muốn 2 dòng.
4. **Sort dropdown open state**: native select popup (không style được cross-browser) — control
   border/label đã đạt design; design 2330-39557 selected-green chỉ đạt trên option của vài browser.
5. **Mode-switcher active state**: design không định nghĩa → chọn bg tint #f4f9f4 (token trong spec).
6. **Wording VI 10 key chờ duyệt** (1 dòng CSV/key nếu chỉnh).
7. **EN store**: verify live bị chặn store-switch (LL-0011) → en identity-by-CSV + QC trên demo.
8. DEVIATION từ plan: toolbar/pager/viewmode/limiter/amount = CSS-only (plan ghi override) —
   vendor structure khớp design, giảm diff-surface khi upgrade.
9. `"Show results"` VI "Xem kết quả" (design "Show (##) results" — restyle-only không có live count,
   đã chốt với user).