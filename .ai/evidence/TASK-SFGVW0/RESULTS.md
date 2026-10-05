# TASK-SFGVW0 — Verify Results (SLP-297)

Ngày: 2026-10-02 · Env: local (`slaunchpad.localhost` → 127.0.0.1, developer mode) · Probe: Playwright headless (`NODE_PATH=/tmp/pw-cal/node_modules`), CSS build `web/tailwind` + cp static 2 locale (F9) + `cache:flush`.

## Kết quả — 8/8 AC PASS

| AC | Kiểm tra | Kết quả | Bằng chứng |
|----|----------|---------|-----------|
| AC-001a | Gradient trên row PB | **PASS** | computed `linear-gradient(278deg, rgb(221,88,0) 2.83%, rgb(193,0,0) 98.02%)` |
| AC-001b | Full-bleed 2 mép | **PASS** | row `x=0, w=1425` == `body.clientWidth` 1425 (layout viewport không scrollbar); pixel x=1 = `#c20100` (đỏ), pixel tới x=1424 màu gradient; dải trắng 1425–1440 = vùng scrollbar (headless render trắng, invisible trên browser thật — cùng pattern slider track sẵn có `trackRight=1433` bị clip ở 1425) |
| AC-001c | Không overflow-x | **PASS** | `scrollWidth − innerWidth = −7` |
| AC-002 | Title trắng | **PASS** | `.lp-flash-title` computed `rgb(255,255,255)` (desktop; mobile dùng chung rule `text-white`) |
| AC-003 | Countdown 3 box | **PASS** | 3 cell + 2 sep; alpha `0.3)` (Tailwind v4 compile `bg-white/30` → oklab alpha 0.3); radius `8px`; tick `462:34:44 → 462:34:42`; **3 box cùng height 66px** (sau fix `min-w` + `whitespace-nowrap` — hour 462h không còn wrap "46/2") |
| AC-004 | Label vi/en | **PASS** | render vi: `giờ/phút/giây` (server-side `__()`); en = identity CSV. Local `launchpad_en` cũng chạy locale `vi_VN` (drift store 2 — LL-0011) nên en chỉ verify được ở mức CSV + cơ chế dịch đã chứng minh |
| AC-005 | Mobile 375 | **PASS** | gradient ✓; num `24px/28px, min-w 32px, nowrap` ✓; full-bleed ✓; grid 2 cột `164px 164px` giữ nguyên ✓; không overflow ✓ |
| AC-006 | Sale hết → section tắt | **PASS** | DOM test: remove `.lp-flash` → row `background-image: none` (`:has()` hết match); CTA `a[href="/flash-sale"]` còn lại trong row (hành vi như hiện trạng) |
| AC-007 | 0 pageerror | **PASS** | desktop + mobile: `errors.length === 0` |

Screenshots: `slp297-desktop.png` (1440, clip row), `slp297-mobile.png` (375, clip row). Probe: `verify.js`.

## Lưu ý cascade (điều tra trong quá trình verify)

- Rule nền đặt trên `body.cms-index-index [data-content-type="row"]:has(.lp-flash)` — margin/padding của rule tính đúng; đo `getComputedStyle` ban đầu gây hiểu nhầm (pl=40px = chính là `calc(50vw − 50%)` resolve với CB 1360 của container, ml báo 0px do % margin lazy-resolve). Quyết định cuối dựa trên **geometry + pixel** (row == body width, mép trái/phải màu gradient), không phải giá trị computed từng property.
- `elementFromPoint` trả `null` ở mép — hệ quả hit-test với `overflow-x: clip` trên body; không phải lỗi layout.

## Không test được local (chuyển QC demo)

- EN label trực tiếp trên store (locale drift — LL-0011): CSV en identity, rủi ro ~0.
- Sale hết thật qua thời gian: gate server `isSaleEnded()` + client `hide()` **không đổi code** (đã verify tại TASK-0NNZCW / BUG-9X14Y1); phần mới — nền tắt theo — verify bằng DOM test AC-006.
