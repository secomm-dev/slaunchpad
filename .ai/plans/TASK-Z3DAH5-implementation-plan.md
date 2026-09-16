# TASK-Z3DAH5 (SLP-157) — Implementation Plan: Quick View [LA-01]

> Mode B · Specification: Embedded Mini-Spec trong `.ai/records/tasks/TASK-Z3DAH5.md` (spec_status: VALID) · Created 2026-09-15

## Files changed

| # | File | Action | Purpose |
|---|------|--------|---------|
| 1 | `app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/list/item.phtml` | override mới (copy verbatim vendor `magento2-default-theme` + 1 diff) | Quick View trigger button trong cụm icons — dispatch window event `open-quickview` |
| 2 | `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/quickview/modal.phtml` | mới | Modal container toàn trang: native `<dialog>` + Alpine `initQuickView` (GraphQL lazy-load + configurable state + ATC fetch) |
| 3 | `app/design/frontend/Secomm/launchpad/Magento_Catalog/layout/catalog_category_view.xml` | mới | Đưa block `quickview.modal` vào `content` |
| 4 | `app/design/frontend/Secomm/launchpad/Magento_Catalog/layout/catalogsearch_result_index.xml` | mới | Như trên cho trang search |
| 5 | `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` + `en_US.csv` | +3 key/file | "Quick view" / "Choose an Option..." / "There was a problem adding your item to the cart." |

## Sequence

1. **item.phtml override** — cp verbatim; insert 1 `<button>` (Lucide `eye` icon, aria-label `__('Quick view') + name`) vào cụm `flex flex-wrap gap-2`; `@click` dispatch `open-quickview` `{detail:{sku}}`. Không đổi gì khác.
2. **modal.phtml** — mọi markup + JS trong 1 template (idiom Hyvä — component cùng template dùng nó):
   - PHP: Store VM `getStoreCode()` (GraphQL `Store` header), `formkey` block, labels qua `__()` + `escapeJs` cho JS side.
   - Markup: `<div x-data="initQuickView"><dialog x-htmldialog.noscroll="close">` — loading state, gallery (main image + thumbs), name, price block (final/regular/tier), configurable selects (matrix disable), qty input, ATC button, success/error state.
   - JS: fetch GraphQL (`Store` header, cache Map per sku) → build state; matrix: value khả dụng = variants khớp mọi selection hiện có & in-stock; price = variant final_price khi đủ selection; ATC = `fetch(action, {method POST, body: URLSearchParams(new FormData(form)), headers: X-Requested-With})` + append `___store` từ `location.search` → JSON: !backUrl → dispatch `reload-customer-section-data` + success view; backUrl → `location.href`; catch → message.
   - Class Tailwind: static strings toàn bộ.
3. **Layout** — 2 XML file mới, block class `Magento\Framework\View\Element\Template`, name `quickview.modal`, after product list trong `content`.
4. **CSV** — append 3 key (mirror BR-001).
5. **Build + flush** — `npm run build` (tailwind dir) + `bin/magento cache:flush` **as secomm** (LL: shell root → `sudo -u secomm`).
6. **Verify** — Playwright probe scripts (pattern SLP-158/SLP-160): AC-001..004 × vi/en + mobile 375 + regression card ATC + drawer count; GraphQL payload assert; dictionary check.

## Guards

- Không đụng: Mageplaza OSC/checkout, `checkout/cart/add` controller (dùng nguyên văn), cart-drawer SLP-158, form ATC card/PDP.
- Không module PHP mới; không GraphQL mutation; không custom options (scope).
- Regression bắt buộc: ATC card (form POST cũ) cả 2 store; drawer mở/đóng; PDP ATC.
