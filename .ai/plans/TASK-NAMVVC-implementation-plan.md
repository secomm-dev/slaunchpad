# Implementation Plan — TASK-NAMVVC (SLP-235): Product List Layout Mock-up

| Specification | Embedded Mini-Spec trong `.ai/records/tasks/TASK-NAMVVC.md` (spec_status: VALID) |
|---|---|
| Mode | B |
| Risk | medium (display-only theme layer, không §12; nhưng item.phtml = card pipeline chung sitewide → regression matrix bắt buộc) |
| Owner | Dev (AI-assisted) → TL review → QC |

## Specification

Embedded Mini-Spec — record `TASK-NAMVVC`: Goal / Hiện trạng / Expected Behavior / Constraints / Out
of Scope / AC-001..AC-010. Decision points user đã chốt 09-30: card thống nhất (ATC visible toàn cục),
recently-viewed tạm chưa làm, compare icon bật lại, filter + sort = apply design only, empty state chỉ
category PLP.

## Kiến trúc chọn

**Card variant grid/list**: KHÔNG template riêng — dùng `view_mode` (grid/list) mà card hiện đã nhận
(`md:flex-row md:w-2/6 media` khi list) + CSS container scoping (`body.catalog-category-view`) cho các
khác biệt còn lại. Lý do: item block cache 3600s cấm branch theo request context; card render qua 1
template duy nhất cho cả PLP/PB/widget.

**Card CSS**: tách `.hp-card*` từ `homepage.css:36-253` sang `web/tailwind/theme/card.css` (import từ
`theme/index.css`, giữ `homepage.css` cho lp-*/hp-* section) — giúp card dùng chung PLP + PB không
chứa rule homepage-only, giảm bleed. Nếu tách gây churn lớn → fallback: sửa tại chỗ trong
`homepage.css` (TL quyết khi thấy diff).

**Filter panel mobile**: wrap sidebar Elasticsuite trong Alpine overlay (`x-data` + `x-show` full-screen,
header + X + CTA sticky) — tái dùng collapse logic `isMobile` sẵn có của `view.phtml`; không đổi
behavior item bên trong (anchor navigate-immediate). CTA + X chỉ toggle panel.

**Toolbar/pager**: override copy-verbatim + restyle (giữ `initToolbar`/`changeUrl`/`data-role`/params) —
pattern mọi override trước (SLP-160/224: verbatim trừ diff, re-diff khi upgrade).

**Empty state**: override `Magento_Catalog::product/list.phtml` (copy verbatim phần non-empty —
toolbar calls + eager loading; chỉ đổi nhánh empty + grid classes). "Clear filters" = URL category
không query params; "Back to %1" = parent category URL, ẩn khi không có parent.

## Files (tạo mới / sửa)

Theme `app/design/frontend/Secomm/launchpad/`:

| File | Thao tác |
|---|---|
| `Magento_Catalog/templates/product/list/item.phtml` | **Sửa** — un-hide ATC (bỏ `hidden` dòng 190), action row grid (flex-1) / list (compact 200), + compare icon (`initCompareOnProductList`), + badge Exclusive, tokens, xóa gallery-debug comment |
| `Magento_Catalog/templates/product/list/wishlist/button.phtml` | Sửa nhẹ (nếu cần restyle icon button tròn) — giữ markup contract `initWishlist`/`data-addto=wishlist` |
| `Magento_Catalog/templates/product/list.phtml` | **Override mới** (copy vendor Hyvä) — grid classes 3 cột lg, container scoping, empty state mới (icon + text + Clear filters + Back to %1) |
| `Magento_Catalog/templates/product/list/toolbar.phtml` | **Override mới** — restyle, giữ contract |
| `Magento_Catalog/templates/product/list/toolbar/viewmode.phtml` | **Override mới** — restyle switcher |
| `Magento_Catalog/templates/product/list/toolbar/sorter.phtml` | **Override mới** — restyle select + dropdown style (selected `#45744c`) |
| `Magento_Catalog/templates/product/list/toolbar/limiter.phtml` | **Override mới** — restyle "Show" |
| `Magento_Catalog/templates/product/list/toolbar/amount.phtml` | **Override mới** — "Items %1 to %2 of %3" style |
| `Magento_Theme/templates/html/pager.phtml` | **Override mới** — pager 3 khối theo design |
| `Hyva_SmileElasticsuite/templates/catalog/layer/view.phtml` | **Override mới** — "Filter" heading, chips active + Clear all, accordion style, mobile panel wrapper (Alpine + X + CTA) |
| `Hyva_SmileElasticsuite/templates/catalog/layer/filter/attribute.phtml` | **Override mới** — checkbox 24×24 + count style (giữ anchor/x-for contract) |
| `Magento_LayeredNavigation/templates/layer/state.phtml` | **Override mới (nếu cần)** — chips active style |
| `web/tailwind/theme/card.css` | **Mới** (tách từ homepage.css) hoặc giữ homepage.css — card rules theo spec |
| `web/tailwind/theme/page-catalog.css` | **Sửa** — PLP layout/toolbar/sidebar/empty styles |
| `web/tailwind/theme/index.css` | **Sửa** — import card.css (nếu tách) |
| `i18n/vi_VN.csv` + `i18n/en_US.csv` | **Sửa** — +keys: `Sale`, `Exclusive`, `Clear filters`, `Back to %1`, `No products found`, `We couldn't find any products that matches your current filters. Try adjusting your selection to discover more products.`, `Filters`, `Show results` (wording TBD PM) |
| `Magento_Theme/templates/html/breadcrumbs.phtml` | Chỉ nếu CSS không đủ restyle breadcrumb/H1 (ưu tiên CSS qua `page-main.title`) |

Không đụng: vendor, `Monsoon_HyvaAjaxAddToCart` templates (theme override SLP-264 giữ nguyên),
`Hyva_SmileElasticsuite/layout/hyva_catalog_category_view.xml` (load-bearing), `config.php`, module
nào khác, block recently-viewed (để nguyên trạng không render).

## Phases

1. **Wave 1 — Card (AC-005, 009)**: item.phtml action row + compare + Exclusive badge + tokens card +
   dọn debug comment; verify: ATC AJAX không reload + mở drawer, quickview, wishlist, compare (PLP +
   homepage rails/flash + PB carousel), card degrade grouped/bundle.
2. **Wave 2 — PLP + toolbar + filter (AC-001, 002, 003, 006, 007)**: list.phtml override (grid 3 cột +
   empty state), toolbar 5 file + pager restyle (sort/limiter/pager behavior nguyên bản), layer
   view/attribute/state override (chips + Clear all + panel mobile + sort dropdown style), fix
   skip-link `#product-list`; verify grid/list desktop 1440, mobile 375 (panel + nút Filters + sort),
   filter click vẫn navigate, sort/pager/limit đổi URL đúng.
3. **Wave 3 — Polish + i18n + release (AC-004, 008, 010)**: H1/breadcrumb, empty-state actions,
   CSV vi/en + `cache:clean translate`, build tailwind + F9 cp (vi + en), Playwright matrix, evidence,
   estimation row, validator `--check-records --check-identity --check-specs`.

## Verification matrix (Playwright 1440 + 375, store vi; en qua emulation CLI)

| Khu | Check |
|---|---|
| PLP grid | 3 cột, card anatomy + số đo spec, toolbar/pager, badge 3 loại, swatch chọn + giá update |
| PLP list | row stack đúng, description clamp, ATC compact + icons |
| PLP empty | category không product → icon + text + Clear filters + Back to %1 |
| ATC | AJAX 1 POST, không reload, drawer mở theo config; form contract intact |
| Quickview | mở từ card PLP + homepage; modal activity nguyên vẹn |
| Wishlist/Compare | add từ card PLP + homepage; compare sidebar page render |
| Filter | click item vẫn navigate (immediate), chips + Clear all, panel mobile mở/đóng, CTA đóng |
| Sort/Limit | changeUrl đúng param, default sort giữ, direction toggle |
| Search | card mới render, filter sidebar còn hoạt động (design tổng thể out of scope) |
| Homepage | rails/flash/PB carousel không vỡ (bleed guard homepage.css) |
| PDP | related/upsell slider + ATC (SLP-264) không regression |
| Console | 0 pageerror, không overflow-x |

## Rollback

Git revert working tree (chưa commit); không có DB write; pub/static cp tay phục hồi bằng build lại.
