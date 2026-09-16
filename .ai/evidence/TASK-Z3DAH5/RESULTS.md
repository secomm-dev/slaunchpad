# TASK-Z3DAH5 (SLP-157) — Quick View [LA-01] — Verify Results

- Date: 2026-09-15
- Storefront local: `http://slaunchpad.localhost` (theme **Secomm/launchpad** — theme config đã bị session khác đổi sang `Hyva/default`, đã switch lại về launchpad theo chỉ thị user và GIỮ — không restore)
- Fixtures: simple standalone `table-runner-linen` (Linje Table Runner); configurable parent `table-ovale` (Ovale Dining Table, 1 option "Size"); PLP `dining.html`

## Automated verification — 24/24 PASS (`playwright-verify.txt`)

| Group | Checks | AC |
|---|---|---|
| T0 render vi | 12 trigger cards, aria "Xem nhanh <name>", modal container render | — |
| T1 simple (Linje Table Runner) | modal open + đúng tên; giá `77,83 $`; gallery 5 ảnh; ATC success banner "Bạn đã thêm Linje Table Runner vào giỏ hàng."; **no reload** (window flag sống + URL giữ nguyên); **mini-cart `summary_count` 0→1 qua `reload-customer-section-data`**; close (X) | AC-001, AC-003 |
| T2 configurable (Ovale Dining Table) | 1 select "Size"; guard thiếu option: alert "Bạn cần chọn tùy chọn cho sản phẩm.", cart không đổi; chọn đủ → ATC thành công, count 1→2; GraphQL query có `configurable_options` | AC-002, AC-003 |
| T3 a11y/tech | Esc đóng dialog; focus chuyển vào dialog khi mở; 0 non-ambient console/page errors | AC-004 |
| T4 mobile 375×720 | `scrollWidth=360` (no h-scroll); dialog 328px fit, scroll dọc trong dialog | AC-004 |
| T5 en store (`?___store=launchpad_en`) | 12 trigger aria "Quick view …"; modal labels identity (`Add to Cart`); ATC success EN identity, no reload | AC-001, BR-001 |
| T6 regression | ATC card cũ (form POST full-page, selector aria "Thêm vào giỏ …") vẫn hoạt động — count tăng, không bị intercept | — |

## Dictionary verify — 10/10 PASS (`qv-dict-verify.txt`, `verify-i18n.php`)

- vi_VN: 5/5 framework-level (merged dictionary qua `Translate::loadData`, pattern BUG-JMJYMJ)
- en_US: 5/5 file-level identity (Translate singleton giữ dict vi trong CLI — live identity đã verify Playwright T5b/T5c)

## Screenshots

- `qv-desktop-vi-configurable.png` — modal Ovale Dining Table: gallery, giá, "Còn hàng", select "Size" placeholder "Chọn một tùy chọn...", SL, ATC; trigger quick-view (icon mắt) trên card
- `qv-mobile-375-vi.png` — modal 328px vừa viewport, thumbnails, scroll dọc
- `qv-en-store.png` — modal store EN identity

## Findings/Traps ghi nhận (chi tiết trong record)

1. **Alpine chạy với MutationObserver OFF trên theme này** — mọi `x-data` phải đi kèm `x-defer` để được init; node clone/inject sau start không bao giờ tự init; **`<template x-if>` mount content mà directives KHÔNG được bind** (form trong x-if submit native) → dùng `x-show` + optional chaining guards.
2. **GraphQL `products(filter:{sku})` với sku của child variant trả về PARENT** (configurable/grouped) — card variant (vd "Terra Mug") mở modal ở parent (vd "Terra Stoneware Collection") — behavior Magento 2.4.8, chấp nhận cho UX Quick View (parent luôn ATC được).
3. Tầng page-cache theo URL (cache-buster bypass được) trả stale markup sau khi sửa template — `cache:flush` toàn bộ bắt buộc trước verify.
4. Fallback paths đã handle nhưng không trigger được trong test (OK — defensive): HTTP error → native form submit; JSON `backUrl` → navigate (server-side rejection).
