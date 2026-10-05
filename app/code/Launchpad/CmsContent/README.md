# Launchpad_CmsContent

Homepage custom widgets + blocks for LAUNCHPAD CORE (TASK-0NNZCW, SLP-213).

## Purpose

Theme layer giữ markup trong CMS PageBuilder content; module này chứa phần
**PHP-side** mà PageBuilder không tự có:

| Component | Loại | Ghi chú |
|---|---|---|
| `FlashSaleList` | widget (extends `Magento\CatalogWidget\Block\Product\ProductsList`) | Product slider + countdown "Sale ending in". Option: `title` (tuỳ chọn, heading cùng hàng countdown; rỗng = không heading), `sale_end` (store time, `YYYY-MM-DD HH:MM:SS`; rỗng = nửa đêm kế tiếp), `products_count`, `template`, `condition`. |
| `Block\Blog\Journal` | block | Latest Magefan blog posts → slider mobile / grid desktop. Tham số `count` (default 3). |
| Newsletter CMS block | data patch | `identifier=homepage-newsletter` — form subscribe, chèn qua `{{widget type="Magento\Cms\Block\Widget\Block" block_id="…"}}`. |
| `Setup\Patch\Data\ConvertHomepageCategoryToSlider` | data patch | SLP-267: Category section PB columns → PB Text element chứa widget `Secomm UI` `categories_a` (slider quản lý bằng item). Chạy trên **mọi** page `home` (mỗi store view một page), widget dựng từ chính section của page đó (heading, label/ảnh/alt/URL/new-tab từng tile, đủ số lượng & thứ tự) — không thay bằng giá trị seed. Page không convert được (thiếu ảnh/URL, > 12 tile) giữ nguyên + log warning. Idempotent. |
| `Setup\Patch\Data\EnableShowOutOfStockProducts` | data patch | SLP-272: bật `cataloginventory/options/show_out_of_stock=1` (default scope) để card / PDP / Quick View giữ option hết hàng (Hyvä disable theo `jsonConfig.salable`) + invalidate indexer EAV / price / stock / inventory / catalogsearch. Hệ quả: sản phẩm OOS hiện trên PLP/search/widget. Admin vẫn đổi được (không pin `config.php`). |
| `Setup\Patch\Data\SeedFooterBlocks` | data patch | SLP-275: seed 4 CMS block footer (`footer_newsletter` / `footer_links` / `footer_social` / `footer_trust_payments`) × 2 store **theo staging (store 1 = VI, store 4 = EN; không có row store 0 fallback)** — store view không tồn tại trong env bị skip (local thiếu store 4 chỉ seed VI). Render qua layout `Magento_Theme` child theme (TASK-7EYJ4C). Idempotent theo (identifier, store) qua join `cms_block_store`. `footer_newsletter` bọc `{{block}}` `Newsletter\Subscribe`. Sau khi sửa block content có Tailwind classes mới: regen safelist + `npm run build` (xem safelist `launchpad-cms.html`). |
| `ViewModel\LegalLinks` | view model | SLP-291: label/URL 2 link pháp lý copyright bar (`Terms & Conditions` / `Privacy Policy`) đọc từ config `launchpad_footer/legal_links/*` (store view scope). Trống = fallback label dịch + route tĩnh (TASK-7EYJ4C). URL `http(s)://` passthrough, else `getUrl()`. Template `Secomm/launchpad .../footer/copyright.phtml` require qua `$viewModels`. |

## Config — Footer legal links (SLP-291)

**Stores > Configuration > Launchpad > Footer > Legal Links (Copyright Bar)** —
4 field, scope store view: `Terms Link Label/URL`, `Privacy Link Label/URL`.
- Điền URL route/path (`terms-and-conditions`) hoặc full `https://` — external giữ nguyên.
- Trống = fallback hiện tại; label là data per-store (không đi qua CSV translation —
  đặt label riêng cho store VI/EN).
- Save xong thấy ngay trên storefront (observer clean `block_html` + `full_page`).
- ACL: `Magento_Backend::content`.

## Templates

- `view/frontend/templates/product/widget/flash-sale.phtml` — countdown + `x-snap-slider` (auto-pager, group-pager; dots chỉ hiện khi track overflow).
- `view/frontend/templates/blog/journal.phtml` — card blog: ảnh aspect 437/520, title, excerpt.

## Liên quan theme

- Card product: `Secomm/launchpad/Magento_Catalog/templates/product/list/item.phtml` (`hp-card-*`).
- Option hết hàng (SLP-272): style `.swatch-option:has(:disabled)` trong `web/tailwind/components/swatches.css` (card / PDP / Quick View) + override màu card trong `homepage.css`.
- PB products carousel override: `Secomm/launchpad/Magento_PageBuilder/templates/catalog/product/widget/content/carousel.phtml`.
- CSS: `web/tailwind/theme/homepage.css` (lp-* section classes) + safelist `web/tailwind/safelist/launchpad-cms.html`.

## Lưu ý

- `sale_end` parse theo `TimezoneInterface::getConfigTimezone()` (store scope).
- Newsletter form action `/newsletter/subscriber/new/` — assumes Magento chạy ở domain root.
- CMS block content dùng chung cho mọi store view (schema `cms_page.content`/`cms_block.content` không per-store) — copy theo design (EN), BR-001 per-store = follow-up.
