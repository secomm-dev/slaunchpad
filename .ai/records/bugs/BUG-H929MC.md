---
id: BUG-H929MC
type: bug
title: "[Header][UI][Mobile] Header mất toàn bộ navigation (mobile + desktop) và dropdown tài khoản bị cắt lệch trái trên mobile"
project_code: SLP
parent:
external_refs:
  ticket: SLP-129
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-08
updated: 2026-09-08
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Snowdog_Menu 0.2.8 + Mageplaza_SocialLoginPro
decisions: []
decision_assessment: pending-tl-menu-data
components:
  - app/design/frontend/Secomm/launchpad/Magento_Customer/templates/header
source_areas:
  - theme-templates
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-08
supersedes: []
---

# [SLP][BUG-H929MC] [Header][UI][Mobile] Header mất toàn bộ navigation (mobile + desktop) và dropdown tài khoản bị cắt lệch trái trên mobile

<!-- External ticket: SLP-129. Screenshot QC (slaunchpad-demo.secomm.vn, mobile): header không có menu điều hướng (không có hamburger); mở icon tài khoản → dropdown "Đăng nhập / Tạo một tài khoản" bị cắt một nửa lệch trái ra ngoài viewport (chú thích "Thiếu menu"). -->

## Summary

Header storefront thiếu navigation khi xem ở mobile. Chẩn đoán cho thấy **hai lỗi độc lập**:

1. **Navigation mất toàn bộ (mọi breakpoint, không riêng mobile)**: `Snowdog_Menu` (install từ SLP-29, đã commit `90d3681a`) đăng ký layout `view/frontend/layout/default_hyva.xml` — remove 2 block nav native của Hyvä (`topmenu_mobile`, `topmenu_desktop`) và thay bằng `Snowdog\Menu\Block\Menu` trỏ tới identifier `hyva-topmenu-mobile` / `hyva-topmenu-desktop` (+ `hyva-menu-footer`, đồng thời remove `footer-static-links`). Bảng `snowmenu_menu` hiện **0 rows** → `Block\Menu::loadMenu()` nhận empty model từ repository → render rỗng im lặng → header không còn menu nào, footer links cũng mất.
2. **Dropdown tài khoản bị cắt trái trên mobile**: template vendor `Magento_Customer::header/customer-menu.phtml` neo dropdown bằng `absolute right-0 … -me-4` (w-40 dưới sm). Trên <sm, row icon nằm bên trái dưới logo (flex-wrap của header) → account button nằm gần mép trái → dropdown 160px mở **sang trái** → tràn khỏi viewport ~30–60px, chữ bị cắt ("Đăng nhập" → "…ng nhập").

## Mini Spec

### Goal
- Dropdown tài khoản không còn bị cắt trên mobile (≥320px), giữ nguyên hành vi desktop.
- Navigation storefront hiển thị trở lại theo đúng hệ menu đã chọn (Snowdog — quyết định từ SLP-29).

### Constraints / Rules
- Không sửa `app/code/` third-party (Mageplaza, Snowdog, Hyvä vendor) — fix ở tầng theme override.
- Tailwind v4 CSS-first: class mới phải nằm trong `@source` scope (`@source "../../**/*.phtml"` của theme), không tạo `tailwind.config.js`.
- BR-001: không thêm string mới (fix thuần layout/CSS — không đụng i18n).

### Out of Scope
- **Nội dung menu Snowdog** (node nào, category nào) — store data, tạo trong Admin: Stores → [Snowdog] Manage Menus, cần business xác nhận danh mục → flag TL (xem Notes).
- Logo "Magento" default trên demo + home content demo Hyvä — store data Admin.
- Hành vi dropdown khi logged-in nhiều item — cùng nav element, fix áp dụng chung; QC smoke thêm.

### Acceptance Criteria
- AC-001: Mobile (375px) — mở dropdown tài khoản (guest): dropdown nằm trọn trong viewport, không bị cắt chữ (verify markup class + visual).
- AC-002: Desktop (≥1280px) — dropdown giữ vị trí stock (neo phải icon tài khoản, `-me-4`), regression sạch.
- AC-003: `npm run build` Tailwind thành công; `sm:left-auto` + `sm:-me-4` xuất hiện trong `web/css/styles.css` build.
- AC-004: Navigation: sau khi Admin tạo menu với 3 identifier (`hyva-topmenu-desktop`, `hyva-topmenu-mobile`, `hyva-menu-footer`) và assign store views → hamburger + nav render (QC verify; phần data — xem Notes cho TL).

## Root Cause

1. `app/code/Snowdog/Menu/view/frontend/layout/default_hyva.xml` — `referenceBlock topmenu_mobile/topmenu_desktop remove="true"`, thêm block `Snowdog\Menu\Block\Menu` (`menu="hyva-topmenu-mobile"` / `hyva-topmenu-desktop`), `move topmenu-desktop` ra `header.container`, remove `footer-static-links`. `Block\Menu::loadMenu()` (Menu.php:156) → `menuRepository->get($identifier, $storeId)` trả empty model khi không có record → render rỗng, **không có error/log**. Truy vấn DB xác nhận: `snowmenu_menu` 0 rows; HTML homepage fetch local không có markup `initMobileMenu`/`Snowdog` nào (count = 0).
2. `vendor/hyva-themes/magento2-default-theme/Magento_Customer/templates/header/customer-menu.phtml:42` — `<nav class="z-10 absolute right-0 w-40 sm:w-48 mt-2 lg:mt-3 -me-4 …">`. Với `left`+`right` đều đặt và width xác định (w-40), CSS over-constrained resolution bỏ `right` ở LTR → chỉ cần thêm `left-0` ở mobile là dropdown mở sang phải; `sm:` trở lên khôi phục neo phải gốc.

## Fix

1. **Dropdown overflow (code, task này)**: child theme override `Magento_Customer/templates/header/customer-menu.phtml` — copy vendor template, đổi nav class: thêm `left-0 sm:left-auto` + đổi `-me-4` → `sm:-me-4` (mobile mở theo cạnh trái button vào vùng trống; ≥sm hành vi stock).
2. **Navigation (data — out of scope code, handoff TL/Admin)**: tạo menu Snowdog với đúng 3 identifier và assign store views (hướng dẫn từng bước trong Notes). Không insert DB trực tiếp, không disable module (ngược chủ đích SLP-29 + đã đầu tư compat Tailwind v4 tại BUG-NTQ0H2).
3. `npm run build` (Tailwind v4) + `bin/magento cache:flush` (chạy as secomm theo env convention).

## Verification

> Dev + verify tự động **DONE 2026-09-08** — chi tiết số đo + screenshots: `.ai/evidence/BUG-H929MC/RESULTS.md`

- AC-001 ✅ Playwright 375px: dropdown `nav.x=68 → nav.right=228` (< 375), không cắt chữ; console sạch. Screenshot `dropdown-mobile-375.png`.
- AC-002 ✅ Playwright 1280px: anchor stock chính xác (gap tới `btn.right+16` = **0.0**); console sạch. Screenshot `dropdown-desktop-1280.png`.
- AC-003 ✅ `npm run build` PASS (Tailwind v4.3.2); `sm\:left-auto` + `sm\:-me-4` grep = 1/1 trong `styles.css`; rendered markup sau `cache:flush` chứa class mới.
- AC-004 ⏳ **pending** — chờ Admin tạo menu Snowdog (3 identifier + assign store views) → QC verify hamburger + desktop nav + footer links.

## Notes cho TL review

**Quyết định cần TL/PM: nội dung + cách phục hồi navigation (SLP-129 phần menu).** Hai phương án:

- **A (khuyến nghị — khớp chủ đích SLP-29):** Admin tạo 3 menu trong Snowdog Menu → Manage Menus, identifier chính xác `hyva-topmenu-desktop`, `hyva-topmenu-mobile`, `hyva-menu-footer`, assign store views (Tiếng Việt + English; tạo bản per-store nếu nội dung khác ngôn ngữ), node type "Category" mirror cây danh mục (tên category hiển thị đã là store data — xem CURRENT_STATE mục store-data). Sau khi tạo: flush block_html cache. **Lưu ý** footer hiện cũng mất links vì `footer-static-links` bị remove — cần tạo `hyva-menu-footer` để không hụt footer.
- **B:** disable `Snowdog_Menu` → nav native Hyvä (topmenu từ categories) trở lại ngay, không cần data. Nhưng: ngược quyết định SLP-29, mất megamenu/accordion (TASK-ZQ9ZE1) và compat đã làm (BUG-NTQ0H2); phải đền bù footer links. Chỉ cân nhắc nếu business từ bỏ Snowdog.

> Raw debug: `.ai/evidence/BUG-H929MC/` (RESULTS.md + screenshots).
