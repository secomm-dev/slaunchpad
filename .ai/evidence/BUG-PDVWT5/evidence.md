# Evidence — BUG-PDVWT5 (SLP-134): Category Page Swatch Preview Tooltip Positioning Fix

**Date**: 2026-09-07 · **Environment**: Docker `launchpad-docker-phpfpm-1` (Magento 2.4.8-p5, PHP 8.3, Hyvä 1.5.2) · **Theme**: `Secomm/launchpad`

## 001 — PHP Syntax Validation (Docker)

```bash
docker exec launchpad-docker-phpfpm-1 php -l app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml
```

Output:
```
No syntax errors detected in app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml
```
- Exit code: 0

## 002 — Tailwind CSS Compilation

```bash
npm --prefix app/design/frontend/Secomm/launchpad/web/tailwind run build
```

Output:
```
> @hyva-themes/magento2-default-theme@3.0.0 build
> npm run generate && npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify

≈ tailwindcss v4.3.2
Done in 366ms
```
- Exit code: 0

## 003 — Cache Invalidation

```bash
docker exec launchpad-docker-phpfpm-1 php bin/magento cache:clean layout block_html full_page
```

Output:
```
Cleaned cache types:
layout
block_html
full_page
```
- Exit code: 0

## 004 — Code Review & Diff Summary

- **File**: [`app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml`](app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml)
- **Changes**:
  - Replaced legacy `offsetTop` / `offsetLeft` logic with `getBoundingClientRect()` to compute coordinates relative to the Viewport context required by `position: fixed`.
  - Positioned the bottom edge of the tooltip above the swatch top edge (`bottom: ${window.innerHeight - elRect.top}px`), allowing tooltip's `mb-4` class to maintain spacing for the arrow.
  - Centered horizontally via `left: ${left}px` where `left = elRect.left + elRect.width / 2` and `transform: translateX(-50%)`.
