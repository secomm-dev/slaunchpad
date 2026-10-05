# TASK-FYN3HC (SLP-296) — Verify: product old price typography

Figma spec (screenshot ticket): old price = **Body/Body 3** — 14px / 20px / 400 / `#99A1AF` / line-through.

## Method

Playwright probe (`slp296-probe.js`, `slp296-probe2.js` — chromium 1440px), đo
`getComputedStyle()` của `.old-price .price` trên từng surface, site
`http://slaunchpad.localhost/` (store vi_VN default). Product mẫu có special
price: `norrland-throw.html` (id 2120).

## Matrix — AFTER (final run, 10-01)

| Surface | font-size | line-height | weight | color | line-through | PASS |
|---|---|---|---|---|---|---|
| Homepage PB-carousel card (`.hp-pb-products .hp-card`) | 14px | 20px | 400 | rgb(153,161,175) | yes | ✅ |
| PLP card — search result (`catalogsearch/result/?q=norrland`) | 14px | 20px | 400 | rgb(153,161,175) | yes | ✅ |
| PDP (`.product-info-main .old-price .price`) | 14px | 20px | 400 | rgb(153,161,175) | yes | ✅ |
| PDP main price (regression — must stay clamp 1.5–2.5em) | 36px (unchanged) | — | — | — | no | ✅ |

## BEFORE (run 1, sau build nhưng trước khi hiểu cascade — serves as before-state)

| Surface | font-size | line-height | weight | color |
|---|---|---|---|---|
| Homepage card | **12.25px** (0.875em × 14px context) | 20px | 400 | #99a1af |
| PDP | **16px** (var = 1em) | 24px | 400 | oklch ink (đen) |

## Cascade root cause (note for TL)

- `product-price.css` (unlayered): `.price-box .price { font-size: var(--price-font-size, text-base) }`
  thắng mọi rule layered (Tailwind v4: unlayered > layered bất kể specificity).
- Card: `.old-price` set biến `0.875em` → em resolve trên context 14px của card = 12.25px.
  Layered rule `card.css` (`@apply text-sm leading-5 …`) chỉ thắng color/line-height/weight
  (unlayered không set), thua font-size.
- PDP: `page-catalog.css` set biến `1em` = 16px + không có màu.

## Static deploy note (local env)

`setup:static-content:deploy -f --theme Secomm/launchpad vi_VN en_US` báo deploy xong
(4939 files/theme) nhưng **không ghi đè** `pub/static/.../css/styles.css` đã tồn tại
(mtime giữ nguyên 16:10, nội dung cũ). Khắc phục: `cp` source → deployed cho vi_VN +
en_US (developer mode serve file tĩnh trực tiếp). md5 3 file khớp sau copy.
Deploy artifacts `pub/static/**` là gitignored — chỉ source build output
`web/css/styles.css` vào git.

## Line endings

Cả 2 file sửa (`page-catalog.css`, `card.css`) + build output giữ **LF** (0 CR —
check `grep -c $'\r'`), an toàn với trap autocrlf (dataset checksum).
