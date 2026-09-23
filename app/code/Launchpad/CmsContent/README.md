# Launchpad_CmsContent

Homepage custom widgets + blocks for LAUNCHPAD CORE (TASK-0NNZCW, SLP-213).

## Purpose

Theme layer giữ markup trong CMS PageBuilder content; module này chứa phần
**PHP-side** mà PageBuilder không tự có:

| Component | Loại | Ghi chú |
|---|---|---|
| `FlashSaleList` | widget (extends `Magento\CatalogWidget\Block\Product\ProductsList`) | Product slider + countdown "Sale ending in". Option thêm: `sale_end` (store time, `YYYY-MM-DD HH:MM:SS`; rỗng = nửa đêm kế tiếp), `products_count`, `condition`. |
| `Block\Blog\Journal` | block | Latest Magefan blog posts → slider mobile / grid desktop. Tham số `count` (default 3). |
| Newsletter CMS block | data patch | `identifier=homepage-newsletter` — form subscribe, chèn qua `{{widget type="Magento\Cms\Block\Widget\Block" block_id="…"}}`. |

## Templates

- `view/frontend/templates/product/widget/flash-sale.phtml` — countdown + `x-snap-slider` (auto-pager, group-pager; dots chỉ hiện khi track overflow).
- `view/frontend/templates/blog/journal.phtml` — card blog: ảnh aspect 437/520, title, excerpt.

## Liên quan theme

- Card product: `Secomm/launchpad/Magento_Catalog/templates/product/list/item.phtml` (`hp-card-*`).
- PB products carousel override: `Secomm/launchpad/Magento_PageBuilder/templates/catalog/product/widget/content/carousel.phtml`.
- CSS: `web/tailwind/theme/homepage.css` (lp-* section classes) + safelist `web/tailwind/safelist/launchpad-cms.html`.

## Lưu ý

- `sale_end` parse theo `TimezoneInterface::getConfigTimezone()` (store scope).
- Newsletter form action `/newsletter/subscriber/new/` — assumes Magento chạy ở domain root.
- CMS block content dùng chung cho mọi store view (schema `cms_page.content`/`cms_block.content` không per-store) — copy theo design (EN), BR-001 per-store = follow-up.
