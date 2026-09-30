---
id: TASK-HARDAR
type: task
title: '[Homepage] Category section quản lý dạng slider (Secomm UI categories_a) thay PB columns (SLP-267)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-267
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-24
updated: 2026-09-24
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Secomm_UiWidget/templates/components/categories/a.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/code/Launchpad/CmsContent/view/frontend/templates/sliders-init.phtml
  - app/code/Launchpad/CmsContent/etc/homepage-content.html
  - app/code/Launchpad/CmsContent/Setup/Patch/Data/ConvertHomepageCategoryToSlider.php
  - app/code/Launchpad/CmsContent/CHANGELOG.md
  - app/code/Launchpad/CmsContent/README.md
  - app/code/Secomm/UiWidget/Plugin/ValidateWidgetParameters.php
  - app/code/Secomm/UiWidget/Test/Unit/Plugin/ValidateWidgetParametersTest.php
  - app/code/Secomm/UiWidget/CHANGELOG.md
source_areas:
  - theme-homepage
  - cms-content
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-24
supersedes: []
---

# [SLP][TASK-HARDAR] [Homepage] Category section quản lý dạng slider (SLP-267)

<!-- External ticket: SLP-267 "Home category nên được quản lý dưới dạng slider, không nên dùng column trong page builder". Mode C — embedded Mini-Spec + Approach. -->

## Summary

Section **Category** trên CMS page `home` hiện là 1 `column-group` gồm 6 PB `column` (`.lp-tile`: image + heading, **không có link**). Slider chỉ là giả lập bằng CSS (`row:has(.lp-tile)` flex rail / grid 6) + JS dots viết tay trong `sliders-init.phtml`. Admin muốn thêm/bớt/sắp xếp category phải thao tác column-group 12-grid → dễ vỡ layout.

Lịch sử (TASK-0NNZCW): v4.9.7 dùng PB html element chứa raw slider markup → Admin PB stage không render → v4.9.8 quay lại columns. Widget directive `Secomm UI` (đã dùng cho `embed_a` REAL SPACES trên cùng page) round-trip được qua stage → dùng component `categories_a` (repeater items label/image/alt/url, max 12).

## Mini Spec

### Goal
Category trên homepage được quản lý như **một slider**: một widget `Secomm UI` → `categories_a` với danh sách item (thêm/bớt/sắp xếp trong form widget), không còn PB columns.

### Expected Behavior
1. Admin > Content > Pages > Home: section Category là **1 PB HTML Code element** chứa directive `{{widget type="Secomm\UiWidget\Block\Widget\SecommUi" component="categories_a" …}}`; mở form widget thấy heading "Category" + 6 item (Chair, Table, Cabinet, Lighting, Bed, Nightstand).
2. Storefront `<64rem` (mobile/tablet): tiles 172px trượt ngang (scroll-snap, `SnapSlider`), track **tràn ra mép phải viewport**, **1 dot / tile** bên dưới (chỉ 1 pill active 24px `#588f60`, inactive 8px `#a9ccae`), click dot → track trượt tới tile.
3. Storefront `≥64rem`: **slider** 6 tile / khung content (gap 1.5rem), track tràn ra mép phải viewport (tile dư ló ra), **không dot, không nút ‹ ›** (round 6 — user tắt); cuộn bằng kéo chuột / trackpad. *(Round 4 — user: "không thấy slider, slider cần mở ra bên phải"; thay grid 6 cột tĩnh ban đầu.)*
4. Tile giữ look hiện tại (nền `#e7f1e8`, rounded-2xl, p-4, ảnh h-24 `object-fit: contain` rounded-lg, label 14px/20px medium `#293e2d` uppercase) và **là link** tới category URL.
5. `mobile_slider = No` → mobile hiển thị grid 2 cột, không dot.

### Constraints / Rules
- Không sửa `Secomm_UiWidget` module / vendor — override template trong theme `Secomm/launchpad` (pattern README UiWidget cho phép).
- Payload tạo bằng `Secomm\UiWidget\Model\Parameter\Codec` và phải PASS `Validator` (schema v1) — không tự chế base64.
- Backup content `home` trước khi đổi DB; chỉ thay đúng block Category (heading + column-group), giữ nguyên phần còn lại.
- Data patch idempotent: chỉ đổi khi content còn đúng block cũ; không đè content đã được admin sửa.
- Tailwind v4 CSS-first; không tạo `tailwind.config.js`.

### Out of Scope
- Nội dung vi_VN cho label (CMS content dùng chung mọi store — BR-001 follow-up có sẵn).
- Đổi visual/design tile.
- Dọn safelist `launchpad-cms.html`.

### Acceptance Criteria
- [x] AC1: Content DB + seed template không còn `lp-tile` / column-group Category; có 1 directive `categories_a` với `payload=` hợp lệ (decode + validate PASS, 6 items).
- [x] AC2: Storefront 375: 6 tiles × 172px overflow ngang, 6 dots, click dot 3 → scroll, active sync; 0 pageerror; không h-scroll trang.
- [x] AC3: Storefront 1440: grid 6 cột, dots ẩn, mỗi tile là `<a href>` đúng URL.
- [x] AC4: CSS/JS cũ cho `.lp-tile` / `.lp-tiles-dots` đã gỡ; section khác không đổi.
- [ ] AC5: Admin PB stage render page `home` với HTML element chứa directive; sau Save, storefront vẫn còn markup `data-secomm-ui-component="categories_a"` (trap LL-0039).

## Approach

1. Theme override `Secomm/launchpad/Secomm_UiWidget/templates/components/categories/a.phtml`: `section.lp-cat-slider` → heading → `[data-lp-tiles-slider]` > `[data-track].lp-cat-track` > `a.lp-cat-tile` (img + label).
2. `homepage.css`: gỡ 2 block trùng `row:has(.lp-tile)` + `.lp-tiles-dots`; viết lại `.lp-cat-*` theo look tile hiện tại (mobile rail 172px, desktop grid 6, dots mobile-only).
3. `sliders-init.phtml`: gỡ JS tiles-dots viết tay; `[data-lp-tiles-slider]` init `SnapSlider` **không** groupPager (1 dot / tile).
4. Content: script dùng `Codec` + `Validator` sinh directive; thay block Category trong `etc/homepage-content.html`; data patch `ConvertHomepageCategoryToSlider` (depends `SeedHomepageContent`, idempotent) áp cho DB (local chạy patch class trực tiếp — setup:upgrade sau này no-op).
5. Build Tailwind (+ copy `en_US` styles.css — bẫy F9), `cache:flush`, verify Playwright 375/1440 + admin stage; evidence `.ai/evidence/TASK-HARDAR/`; CHANGELOG `Launchpad_CmsContent` 1.2.0.

**Mặc định đã chọn (cần PM confirm, không chặn):** URL tile — Chair → `/living-room/living-room-seating.html`, Table → `/living-room/living-room-tables.html`, Cabinet → `/living-room/living-room-storage.html`, Lighting → `/living-room/living-room-lighting.html`, Bed → `/bedroom/beds.html`, Nightstand → `/bedroom/nightstands.html` (admin sửa được trong form widget).

## Progress

- 2026-09-24: record + Mini-Spec + Approach tạo; bắt đầu implement.
- 2026-09-24: implement xong (AC1–AC4 PASS). **AC5 chưa verify** — tạo admin user tạm để test PB stage round-trip bị từ chối quyền → cần người verify tay.

## Implementation

- Theme override `Secomm_UiWidget/templates/components/categories/a.phtml` (`.lp-cat-slider` / `[data-lp-tiles-slider]` / `[data-track].lp-cat-track` / `a.lp-cat-tile`); `mobile_slider=No` → `.lp-cat-slider--static` (grid 2 mobile).
- `homepage.css` (file CRLF — giữ nguyên; không đụng block SLP-266 hero đang sửa song song): viết lại `.lp-cat-*` (tile look v4.9.8, ảnh `object-fit: contain`), desktop `grid-auto-flow: column` + 6 tile/khung (>6 item cuộn ngang); gỡ 2 block trùng `row:has(.lp-tile)` + `.lp-tiles-dots`; selector loại trừ `:not(:has(.lp-tile))` → `:not(:has(.lp-cat-slider))`.
- `sliders-init.phtml`: gỡ JS tiles-dots; `groupPager: false` cho `[data-lp-tiles-slider]`.
- Data patch `ConvertHomepageCategoryToSlider`: payload dựng bằng `Codec` + `Validator` (schema v1); tìm column-group chứa `lp-tile ` bằng đếm depth `<div`, bỏ heading "Category" liền trước; 2nd pass = null (idempotent ✓). Local DB: chạy `apply()` trực tiếp (patch_list chưa ghi — `setup:upgrade` sau sẽ no-op).
- Content: `home` page_id 2 — `lp-tile` 6→0, `categories_a` 1, `embed_a` + `payload=` giữ nguyên (2), row 14 không đổi.

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| php -l (patch, template, sliders-init) | PASS | — |
| PHPCS Magento2 sev≥6 (patch + template) | 0 error / 0 warning | `phpcs.txt` (sliders-init: 3 warning có sẵn — CRLF + 2 dòng cũ) |
| Payload decode + `Validator::validate('categories_a', 1)` | PASS, 6 items | — |
| Tailwind build | PASS (`.lp-cat-*` có, `.lp-tile`/`.lp-tiles-dots` = 0) | — |
| Playwright 375 | 6 tiles ×172, flex overflow, 6 dots, click dot 3 → scroll 0→360 active=2, no h-scroll, 0 pageerror | `verify-cat-slider.txt`, `cat-375.png` |
| Playwright 768 | như 375; page h-scroll=true do `.lp-promo-card` (Everyday more value) — **có sẵn, ngoài scope** | `cat-768.png` |
| Playwright 1440 | grid 6 × 210px, pager ẩn, 6 `<a href>` đúng URL, 0 pageerror | `cat-1440.png` |
| AC5 Admin stage + Save round-trip | **NOT RUN** (permission denied tạo admin user tạm) | — |

Backup: `home-content-before-cat-widget.txt`, `homepage-content-tpl-before-cat-widget.html`; after: `home-content-after-cat-widget.txt`.

## Handoff / còn mở

1. **AC5 (manual)**: Admin > Content > Pages > Home → stage render; mở HTML Code element Category → directive `categories_a`; (tuỳ chọn) Insert Widget → Secomm UI để sửa item; Save → storefront còn `data-secomm-ui-component="categories_a"` **và** `embed_a` (trap LL-0039: PB save có thể cắt `payload=`).
2. PM confirm mapping URL tile (mặc định ở Approach).
3. Deploy: `setup:upgrade` chạy patch (no-op nếu content đã đổi); media `wysiwyg/homepage/cat-*.webp` đã có từ v4.12.
4. Finding ngoài scope: 768px page h-scroll do `.lp-promo-card` rail.

## Correction round 1 (2026-09-24) — Admin không view/edit được content Home

**User report**: sau khi cập nhật slider, Admin > Pages > Home không xem/sửa được content.

- **Root cause**: khi thay column-group, các element bị xoá (column-group/line, 6 column, figure, img) để lại **26 rule mồ côi** `#html-body [data-pb-style=ID]` trong `<style>` đầu content. `vendor/magento/module-page-builder/view/adminhtml/web/js/stage-builder.js` `convertToInlineStyles()` gọi `document.querySelector(selector).setAttribute(...)` **không null-check** → TypeError → stage không render. Storefront không ảnh hưởng, server log không có lỗi. Content gốc (trước SLP-267) = 0 selector null → lỗi do thay đổi của task này.
- **Fix**: `ConvertHomepageCategoryToSlider::pruneOrphanStyles()` — bỏ selector/rule có `data-pb-style` không còn element, bỏ `@media{}` rỗng; `convert()` gọi prune. Áp cho DB `home` + seed template (backup `home-content-before-orphan-prune.txt`; after `home-content-after-orphan-prune.txt`). Phần ngoài `<style>` giữ nguyên byte-by-byte; rule ids 87 → 61, orphan 26 → 0.
- **Verify**: `verify-stage-inline-styles.js` (mô phỏng convertToInlineStyles + JSON.parse background-images) — before-cat-widget 0 null / before-prune **32 null** / after-prune 0 / template 0. Storefront `verify-cat-slider.js` 375/768/1440 vẫn PASS, 0 pageerror. PHPCS patch 0.
- **AC5 vẫn cần verify tay trên Admin** (không tạo được admin user tạm).

## Correction round 2 (2026-09-24) — admin sửa widget dễ dàng (HTML Code → Text element)

**User feedback**: widget hiện dạng HTML Code (raw directive + payload base64) → không biết sửa thế nào.

- **Nguyên nhân**: `lib/web/mage/adminhtml/wysiwyg/widget.js` `initOptionValues()` chỉ pre-fill form widget khi ở WYSIWYG (TinyMCE); trong textarea HTML Code, "Insert Widget" luôn mở form rỗng → admin phải nhập lại toàn bộ item.
- **Fix**: directive chuyển sang **PB Text element** (`data-content-type="text"`). TinyMCE plugin `magentowidget` encode nguyên văn directive vào `id` (Base64.idEncode) của placeholder → round-trip byte-exact; **double-click placeholder → form Secomm UI mở sẵn heading + 6 item** (ComponentOptions đọc payload).
  - Patch `ConvertHomepageCategoryToSlider`: `buildElement()` → Text; `convert()` thêm nhánh chuyển widget từ HTML Code element sang Text (giữ nguyên directive/item). Áp DB + seed template (chỉ đổi `html`→`text`, độ dài không đổi; 2nd pass null). Backup `home-content-before-text-element.txt`, after `home-content-after-text-element.txt`.
  - CSS: sau Save, TinyMCE bọc directive trong `<p>` → 2 `<p>` chỉ chứa whitespace quanh `<section>` → `[data-content-type="text"]:has(> .lp-cat-slider) > p { margin-block: 0 }` (p cao 0).
- **Verify**: stage simulation 0 null selector (after-text-element + template); storefront 375/768/1440 PASS, mô phỏng `<p>` wrap → empty p height 0/0; 0 pageerror; PHPCS patch 0.
- **Lưu ý admin**: Secomm UI chưa khai báo `placeholder_image` trong `widget.xml` → placeholder trong editor hiện icon mặc định (kiểu error) của Magento; chức năng không ảnh hưởng. Muốn icon riêng = sửa `Secomm_UiWidget` (ngoài scope).
- **AC5 vẫn cần verify tay** (Admin: double-click widget → sửa → Insert → Save → storefront còn `categories_a` + `embed_a`).

## Correction round 3 (2026-09-24) — Insert Widget từ editor Text báo "An error has happened during application run"

- **Root cause** (`var/log/exception.log` 09:36:10): `TypeError: Secomm\UiWidget\Plugin\ValidateWidgetParameters::beforeGetWidgetDeclaration(): Argument #4 ($asIs) must be of type bool, null given` ← `Magento\Widget\Controller\Adminhtml\Widget\BuildWidget::execute()` lấy `as_is` từ POST; `lib/web/mage/adminhtml/wysiwyg/widget.js` chỉ thêm `&as_is=1` khi KHÔNG có WYSIWYG → insert từ TinyMCE (Text element — round 2) truyền `null`. Bug có sẵn trong `Secomm_UiWidget` (mọi insert Secomm UI qua WYSIWYG đều lỗi), lộ ra khi chuyển sang Text element. Content DB không bị ảnh hưởng (update_time vẫn 09:18).
- **Fix** (ngoài scope ban đầu — module Secomm-owned, bug fix tối thiểu): `$asIs` bỏ type `bool` → `mixed`, pass-through nguyên giá trị như core `Widget::getWidgetDeclaration($type, $params = [], $asIs = true)` (untyped). Validation payload không đổi.
- **Test**: `Test/Unit/Plugin/ValidateWidgetParametersTest.php` (null / '1' / true pass-through + widget type khác không validate) — 4 PASS; full `Secomm_UiWidget` unit suite 101 tests / 533 assertions PASS (warning Allure config = môi trường). PHPCS: 0 ngoài EOL CRLF có sẵn của working tree.
- **Ngoài scope, ghi nhận**: `exception.log` 09:26/09:35 có `ValueError: DOMDocument::loadXML(): Argument #1 ($source) must not be empty` (UiComponent `DomMerger` khi render 1 admin listing grid) — không liên quan widget/SLP-267.

## Correction round 4 (2026-09-24) — "section category không thấy slider, slider cần mở ra bên phải"

- **Hiện trạng**: admin đã Save widget OK (09:50, 7 item — TinyMCE bọc `<p>`, CSS round 2 xử lý). Desktop là grid-auto-flow 6 cột + scrollbar ẩn + không dot/nút → item 7 khuất, không có cách cuộn → "không thấy slider"; track chưa tràn mép phải.
- **Fix**:
  - Template: header (heading + nút `[data-prev]`/`[data-next]` desktop-only) đưa vào trong `[data-lp-tiles-slider]` → SnapSlider quản lý nút (ẩn khi không overflow, disable đầu/cuối). `aria-label` dùng `__('Previous')` / `__('Next')` (đã có trong vi_VN/en_US CSV).
  - CSS: track `margin-inline-end: calc(50% - 50vw)` + `padding-inline-end: calc(50vw - 50%)` mọi breakpoint → tràn mép phải, content box = bề rộng section; desktop `flex-basis: calc((100% - 7.5rem) / 6)` (6 tile/khung, tile dư ló ra; cuộn hết tile cuối thẳng mép content). Bỏ hướng `cqi` (Chromium test cũ không hỗ trợ — không phụ thuộc container units). Dot: chỉ marker current đầu tiên là pill (SnapSlider set current cho mọi tile in-view).
- **Verify** (`verify-cat-bleed.js`, 7 item): 375/768 — tile 172, tile kế ló ra, 7 dots, 1 pill active; 1440/1920 — 6 tile trong khung (210/212px), tile 7 ló ra, nút ‹ › hiện, next → scroll 0→233/236 + next disabled/prev enabled; 0 pageerror; không h-scroll (768 = `.lp-promo-card` có sẵn). Screenshots `cat-bleed-*-initial.png`, `cat-bleed-*.png`.
- **Lưu ý**: 1440 container 90rem ≈ full màn → vùng tràn chỉ ≈ padding row (tile 7 ló một mép nhỏ); màn rộng hơn ló nhiều hơn. Muốn ló rõ ở 1440 → giảm còn ~5.5 tile/khung (đổi design, cần confirm). Track dư 7px so với `clientWidth` trong headless = scrollbar cổ điển 15px (`body` `overflow-x: clip` cắt) — giống Flash/Promo.

## Correction round 5 (2026-09-24) — "không drag and drop được; không hiện button arrow"

- **Drag**: scroll gốc chỉ nhận touch/trackpad, chuột không kéo được. Fix `sliders-init.phtml` `enableMouseDrag()` cho track `[data-lp-tiles-slider]`: pointer mouse (ngưỡng 5px) → `scrollLeft` theo chuột, `.is-dragging` tắt snap + `pointer-events:none` trên tile; thả → `scrollTo` smooth về tile gần nhất; chặn click link sau khi kéo + native `dragstart` (ảnh/link). CSS `cursor: grab/grabbing` (`pointer: fine`).
- **Arrow**: test headless 1024/1280/1366/1536/1440/1920 (8 item) nút đều hiện + cuộn được. Nguyên nhân khả dĩ phía user: (1) browser giữ `styles.css` cũ — dev-mode URL static không đổi version (`version1790239764`, `Cache-Control: public` không max-age) → bump `pub/static/deployed_version.txt` → `1790244637`; (2) viewport < 64rem (tablet / zoom) → trước đây chỉ dots. Fix: nút ‹ › hiện từ **≥48rem** (tablet + desktop); mobile < 48rem giữ dots (design).
- **Verify** `verify-cat-drag.js`: 375/768/1440 — kéo 250px → scroll 250 khi kéo, thả snap về tile (180/180/233), không điều hướng; click thường mở đúng category URL; arrows: 375 ẩn / 768 & 1440 hiện; cursor grab/grabbing; 0 pageerror.

## Correction round 6 (2026-09-24) — "tắt hiện nút mũi tên của slider"

- Gỡ markup nút `[data-prev]`/`[data-next]` (`.lp-cat-nav`) khỏi theme template `categories/a.phtml` + CSS `.lp-cat-nav*` khỏi `homepage.css` (compiled `styles.css`: 0 `lp-cat-nav`). Giữ: tràn mép phải, kéo chuột, dots mobile/tablet, desktop 6 tile/khung không dot. Bump `deployed_version.txt` + `cache:flush`.
- Verify `verify-cat-drag.js`: 375/768/1440 arrows không hiện; kéo 250px → snap về tile (180/180/233); không điều hướng khi kéo; click mở đúng URL; 0 pageerror. PHPCS template 0.
