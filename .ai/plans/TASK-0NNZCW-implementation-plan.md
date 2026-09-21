# Implementation Plan — TASK-0NNZCW (SLP-213): Homepage Layout Mock-up

| Specification | Embedded Mini-Spec trong `.ai/records/tasks/TASK-0NNZCW.md` (spec_status: VALID) |
|---|---|
| Mode | B |
| Risk | medium (display-only, theme layer, không §12) |
| Owner | Dev (AI-assisted) → TL review → QC |

## Specification
Embedded Mini-Spec — record `TASK-0NNZCW`: Goal / Expected Behavior / Constraints / Out of Scope / AC-1..AC-9.

## Kiến trúc chọn

**Cơ chế build:** theme-layer layout + template (không CMS-widget) — homepage là mock-up placeholder-driven, cần full control full-bleed; CMS `home` giữ rỗng (chỉ layout `cms_index_index` render blocks). Khi có data thật, từng section có thể tách sang `Secomm_UiWidget` widget/CMS block — cấu trúc template partial giúp migrate sau.

**Full-bleed + overflow pattern:** section `w-full` (cha trực tiếp của `content` container không bị max-width); inner container `mx-auto max-w-[1200px] px-4 md:px-8`; slider track breakout bằng `overflow-x-auto snap-x` trên track full-width bên trong section (track nằm ngoài inner container theo trục X: heading trong container, track `w-full` + `px-[container-offset]` để item đầu align mép container — pattern "full width and overflow" của ticket).

**Slider:** CSS scroll-snap + Alpine scrollBy (pattern `slider_a` UiWidget); dots = scroll listener tính index. Mọi `x-data` kèm `x-defer` (LL-0027).

**Countdown:** Alpine `setInterval` 1s, target = hôm nay 23:59:59 local (mock-up), `tabular-nums` chống layout shift, teardown trong `x-init` return.

## Files (tạo mới / sửa)

Theme `app/design/frontend/Secomm/launchpad/`:

| File | Thao tác |
|---|---|
| `Magento_Cms/layout/cms_index_index.xml` | **Sửa** — thêm container `homepage.content` + 10 block (giữ block `quickview.modal`) |
| `Magento_Theme/templates/html/homepage/hero-slider.phtml` | mới |
| `Magento_Theme/templates/html/homepage/categories.phtml` | mới |
| `Magento_Theme/templates/html/homepage/flash-sale.phtml` | mới |
| `Magento_Theme/templates/html/homepage/value-banners.phtml` | mới |
| `Magento_Theme/templates/html/homepage/collection-feature.phtml` | mới |
| `Magento_Theme/templates/html/homepage/product-carousel.phtml` | mới (reusable — heading + products array argument) |
| `Magento_Theme/templates/html/homepage/real-spaces.phtml` | mới |
| `Magento_Theme/templates/html/homepage/promo-banner.phtml` | mới |
| `Magento_Theme/templates/html/homepage/usp.phtml` | mới |
| `Magento_Theme/templates/html/homepage/journal.phtml` | mới |
| `Magento_Theme/templates/html/homepage/product-card.phtml` | mới (partial — $cardData) |
| `web/tailwind/theme/homepage.css` | mới — import vào `tailwind-source.css` |
| `web/tailwind/tailwind-source.css` | **Sửa** — +1 dòng `@import "./theme/homepage.css";` |
| `i18n/vi_VN.csv` + `i18n/en_US.csv` | **Sửa** — +chuỗi mới (2 file mirror) |

Không đụng: `Secomm_UiWidget`, vendor, `config.php`, module nào khác.

## Phases

1. **Layout + skeleton**: cms_index_index.xml + 10 template rỗng render section theo thứ tự — kiểm tra homepage render trước khi chi tiết.
2. **Full-bleed CSS + slider**: homepage.css tokens + breakout; hero slider 6 slide + dots; category carousel; flash-sale slider; value-banners slider; 3 product carousel.
3. **Product card** theo design (AC-5) + flash sale countdown (AC-6).
4. **Sections tĩnh**: collection-feature, real-spaces, promo-banner, usp, journal.
5. **i18n + build + verify**: 2 CSV; `npm run build`; cp styles.css → pub/static (F3); `cache:flush`; Playwright vi/en × 1440/375; screenshots + RESULTS.md; update record `in_review` + estimation row.

## Verify plan (AC mapping)

- AC-1/3/4/5/6: Playwright — section count + thứ tự, hero full-bleed (rect.width == viewport.width), track scrollWidth > clientWidth (overflow peek), body `scrollWidth <= viewport` (không overflow-x trang), card elements đủ (badge/swatch/stock/rating), countdown tick ≥ 1 lần.
- AC-7: probe render store vi (chuỗi VI) + en identity; grep 2 CSV.
- AC-8: curl PLP/PDP/cart smoke — không CSS mới (homepage.css chỉ load trang home qua bundle theme — mọi template mới chỉ render cms_index_index); regression quickview modal block vẫn render.
- AC-9: console messages 0 error trên home.

## Traps đã nhắc trong plan

- F3 static quick-deploy: cp tay `pub/static/frontend/Secomm/launchpad/.../styles.css` sau build.
- CSV root-owned → chown nếu Write lỗi (pattern BUG-SDZPCD).
- Alpine MutationObserver OFF → x-data + x-defer mọi component.
- CLI/PHP chạy as `secomm` (shell session = root).
- 1 viewport/process Playwright (OOM box); probe `/tmp/pw-cal` NODE_PATH.

## Rollback

Xóa file mới + revert 2 file sửa (`git checkout -- theme files`); `npm run build` + cp artifact + cache:flush. Không ảnh hưởng DB.
