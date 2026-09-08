# BUG-H929MC (SLP-129) — Verification Results — 2026-09-08

## Scope verify

Phần **code fix** (dropdown tài khoản tràn trái trên mobile). Phần **navigation** (AC-004) chờ
dữ liệu Snowdog menu từ Admin — xem record §Notes cho TL.

## Root-cause evidence (trước fix)

- Homepage fetch local (`slaunchpad.localhost`, UA mobile): `initMobileMenu` count = **0**, không có
  markup Snowdog nào → nav không render (không phải CSS ẩn).
- DB `slaunchpad` (env.php): `snowmenu_menu` = **0 rows**, `snowmenu_store` = 0, `snowmenu_node` = 0.
  `setup_module.Snowdog_Menu` schema_version 0.2.8 (schema installed, data trống).
- `Snowdog/Menu/view/frontend/layout/default_hyva.xml`: remove `topmenu_mobile`/`topmenu_desktop`
  (nav native Hyvä) + `footer-static-links`; thay bằng block `Snowdog\Menu\Block\Menu`
  (`menu="hyva-topmenu-mobile|hyva-topmenu-desktop|hyva-menu-footer"`).
- `Snowdog\Menu\Block\Menu::loadMenu()` (app/code/Snowdog/Menu/Block/Menu.php:156): repository trả
  empty model khi identifier không tồn tại → render rỗng im lặng (không exception, không log).
- Dropdown clip: template vendor `customer-menu.phtml` nav `absolute right-0 w-40 sm:w-48 -me-4` —
  <sm icon nằm gần mép trái (flex-wrap header) → dropdown 160px mở sang trái tràn viewport.

## Fix applied

- `app/design/frontend/Secomm/launchpad/Magento_Customer/templates/header/customer-menu.phtml`
  (override mới, copy vendor): nav class `right-0 … -me-4` → `left-0 sm:left-auto right-0 … sm:-me-4`.
- `npm run build` (Tailwind v4.3.2) PASS as secomm; utilities mới `sm\:left-auto` + `sm\:-me-4`
  xuất hiện trong `web/css/styles.css` (grep = 1/1). `bin/magento cache:flush` OK.

## Playwright A/B verify (customer-menu-check.js, chromium headless)

| Viewport | nav.x | nav.right | width | fitsInViewport | stock anchor (gap tới btn.right+16) | Console |
|---|---|---|---|---|---|---|
| mobile 375×812 | 68.0 | 228.0 | 160.0 | **true** | (không áp dụng — chủ ý mở trái→phải) | clean |
| desktop 1280×800 | 1029.0 | 1221.0 | 192.0 | **true** | **0.0** (giữ stock chính xác) | clean |

- Trước fix (chính lý học từ markup stock): nav.right ≈ 112 (btn.right + 16 do `-me-4`) → nav.x ≈ −48
  → cắt ~48px ("Đăng nhập" thành "…ng nhập") — khớp screenshot QC SLP-129.
- Sau fix mobile: mở từ mép trái icon (68px) sang phải, dừng 228px < 375px — trọn viewport.
- Screenshots: `dropdown-mobile-375.png`, `dropdown-desktop-1280.png`.

## Markup verify sau flush

Rendered nav class (curl homepage, UA mobile):
`z-10 absolute left-0 sm:left-auto right-0 w-40 sm:w-48 mt-2 lg:mt-3 sm:-me-4 py-2 px-1 rounded-md shadow-lg bg-white overflow-auto`

## Còn lại

- AC-004 (navigation hiển thị): chờ Admin tạo menu Snowdog với identifier `hyva-topmenu-desktop`,
  `hyva-topmenu-mobile`, `hyva-menu-footer` + assign store views (record §Notes cho TL, phương án A/B).
- Regression desktop dropdown = stock (gap 0.0) — AC-002 PASS.
- QC browser đề nghị: guest + logged-in dropdown, cả vi/en store, thêm viewport 320px.

## Env note (reproducibility)

Playwright browsers cache `/home/secomm/.cache/ms-playwright` đang **root-owned** (session 09-07);
`npx playwright install` as secomm FAIL EACCES (`__dirlock`). Verify chạy as root với playwright 1.62.1
từ `/tmp/pw-cal/node_modules` (NODE_PATH) — khớp browser revision 1234 có sẵn. Nếu cần chạy as secomm:
`chown -R secomm:secomm /home/secomm/.cache/ms-playwright` trước.
