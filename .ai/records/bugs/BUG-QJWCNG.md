---
id: BUG-QJWCNG
type: bug
title: '[Homepage] CTA button các section lệch design (size, font, variant, mũi tên đè chữ)'
project_code: SLP
parent:
external_refs:
  tickets: '[TBD]'
  figma: https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2-10
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-25
updated: 2026-09-25
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/design/frontend/Secomm/launchpad/web/css/styles.css
  - app/code/Launchpad/CmsContent/CHANGELOG.md
  - app/design/frontend/Secomm/launchpad/Magento_PageBuilder/templates/catalog/product/widget/content/carousel.phtml
source_areas:
  - theme-homepage
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: daa0fb67
last_verified: 2026-09-25
supersedes: []
---

# [SLP][BUG-QJWCNG] [Homepage] CTA button các section lệch design

<!-- User request (2026-09-25): "Chỉnh lại style của các button cta trên homepage như design". Không có ticket Jira. Mode C — embedded Mini-Spec + Approach. -->

## Summary

Figma homepage `2-10`: mọi CTA text là "Button Variation" size XL — h48, padding 12 / 24 (phải 22 vì có icon trailing), gap 6, radius 6, Inter Medium 16/24, mũi tên 16px sau chữ. Variant theo section:

| Section | Figma node (desktop / mobile) | Variant design | Hiện tại (Chrome 150, 1440) |
|---|---|---|---|
| Flash sale "Explore all" | 2151:17157 / 2151:16939 | nền `primary/brand-500` #588f60, chữ trắng | PB secondary: nền #cfe3d1, chữ #588f60 |
| Split "Explore all" | 2151:17170 / 2151:16947 | brand-500, chữ trắng | PB primary: #45744c |
| Rail "Go to Collection" | 2151:17228 / 2151:16994 | `bg/brand-solid-primary` #45744c, chữ trắng | #45744c ✓ |
| Video "Explore" | 2151:17240 / 2151:17021 | outline 1px `border/brand-primary` #293e2d, chữ #293e2d, nền trong | PB secondary: nền #cfe3d1 |
| Editorial "Shop in-stock sofa" | 2151:17250 / 2151:17031 | nền trắng, chữ #293e2d | PB secondary: nền #cfe3d1, padding 8/16 |
| Blog "View all post" | 2151:17264 / 2151:17044 | brand-500, chữ trắng | PB primary: #45744c |

Mọi CTA trên: **h36, 14/20, weight 400** (design h48, 16/24, 500) và **mũi tên đè lên chữ**.

Root cause:
1. `page-builder.css` `:is(a, button, div).pagebuilder-button-primary|secondary { @apply btn btn-primary|secondary }` unlayered → thắng mọi class utility CMS (`@layer utilities`) — CMS đã viết đúng size/màu nhưng không có hiệu lực. Từ SLP-246 `btn` mặc định = size S. (Cùng root cause với hero SLP-268.)
2. `btn` (SLP-246) có `&::after` touch-target `position: absolute` + `translate(-50%, -50%)`; mũi tên CTA v4.10 cũng dùng `::after` nhưng không reset `position` → mũi tên nằm giữa nút, đè chữ.
3. Rule v4.10 `a.pagebuilder-button-secondary { color: #588f60 !important }` ép màu chữ secondary.
4. Token weight DS sinh `500px` (invalid, SLP-246) → weight 400 — set tay như hero.

## Mini Spec

### Goal
6 CTA text của các section homepage (ngoài hero) khớp Figma `2-10` về kích thước, chữ, màu và vị trí mũi tên.

### Expected Behavior
1. Mỗi CTA: DS `btn-size-xl` + `btn-icon-trailing` → h48, padding 12 / 24 / 12 / 22, gap 6, radius 6, weight 500, Label L (16/24 ≥ 768px; 14/20 mobile theo token DS như hero SLP-268).
2. Mũi tên 16px nằm **sau** chữ (trong flow), cùng màu chữ; không đè chữ.
3. Variant theo section (không phụ thuộc `button_type` PB — Admin đổi type không làm hỏng design, như promo card SLP-269):
   - Flash sale, Split, Blog: nền brand-500, chữ trắng; hover/active brand-600.
   - Rail "Go to Collection": nền brand-600, chữ trắng; hover/active brand-700.
   - Video: nền trong suốt, viền 1px + chữ `brand-900` (#293e2d); hover nền brand-50.
   - Editorial: nền trắng, chữ brand-900; hover nền brand-50.
4. Focus-visible giữ focus ring DS.

### Constraints / Rules
- Chỉ theme CSS (`homepage.css`) + rebuild `styles.css`; không đổi CMS content / seed / DB / template.
- Selector section theo cấu trúc PB (content type), không theo class row Admin (class row có thể mất khi save — xem `.lp-flash`).
- Hero (`[data-content-type="slide"]`) và nút tròn `.lp-promo-card` không đổi.
- Không đụng hunk WIP chưa commit trong `homepage.css` / CHANGELOG (1.2.5 Flash Sale title).
- Build: `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` (không `npm run build`).

### Out of Scope
- Nút điều hướng rail `‹ ›` (`.lp-nav-btn`): design viền + chevron #588f60 24px, hiện viền #d1d5dc + arrow #293e2d 18px — không phải CTA, báo lại.
- Nút "Đăng ký" newsletter footer (h36 vs design h44) — footer, không phải section homepage.
- Mobile Label L 14 vs Figma mobile 16 — theo quyết định token DS của hero (chờ designer, BUG-118Z8T handoff #2).
- Lỗi token font-weight `px` (SLP-246).

### Acceptance Criteria
- [x] AC1: 1440 — 6 CTA: h48, font 16px/24px/500, padding `12px 22px 12px 24px`, radius 6px, gap 6px; `::after` `position: static`, 16×16, nằm bên phải text (không giao text).
- [x] AC2: 1440 — màu nền/chữ/viền đúng bảng Expected Behavior #3; hover đúng màu hover; focus-visible có ring 4px.
- [x] AC3: 375 — 6 CTA h48, 14/20/500, cùng variant; một dòng.
- [x] AC4: Hero CTA (h48 brand-500, không mũi tên) và nút tròn promo card (36×36 viền trắng) không đổi; 0 pageerror.

## Approach

1. `homepage.css` (sau khối SLP-268 hero): selector chung `body.cms-index-index [data-content-type="button-item"] :is(a.pagebuilder-button-primary, a.pagebuilder-button-secondary)` loại trừ slide + promo card → `@apply btn-size-xl btn-icon-trailing`, `--btn-font-weight: var(--font-weight-medium)`, variant mặc định brand-500. `::after` mũi tên: `position: static; transform: none`.
2. Override variant theo section: rail = `row:has([data-content-type="products"])`; video = row contained chỉ heading/text/buttons (tái dùng selector "REAL SPACES copy" sẵn có); editorial = row full-bleed có buttons, không column-group.
3. Bỏ `color: #588f60 !important` + `gap: 0.5rem !important` của rule v4.10 (màu/gap nay do biến `--btn-*`).
4. Build Tailwind, `cache:flush`, verify Playwright Chrome 150 (AC1–AC4), evidence `.ai/evidence/BUG-QJWCNG/`; CHANGELOG `Launchpad_CmsContent`.

## Progress

- 2026-09-25: đo Figma + live 1440/375; record + Mini-Spec + Approach; bắt đầu implement.
- 2026-09-25: implement xong; verify Playwright Chrome 150 ALL PASS (56 check, AC1–AC4).

## Implementation

- `homepage.css`:
  - Rule v4.10 (CTA một dòng): bỏ `color: #588f60 !important` của secondary và `gap: 0.5rem !important`.
  - Rule mũi tên v4.10 `::after`: thêm `position: static; transform: none` (đưa về flow).
  - Khối mới sau các rule hero SLP-268: selector `[data-content-type="button-item"] :is(a.pagebuilder-button-primary, a.pagebuilder-button-secondary):not([data-content-type="slide"] *, .lp-promo-card *)` → `@apply btn-size-xl`, weight 500, `--btn-bg/--btn-color/--btn-shadow` lấy từ `--lp-cta-*` (mặc định brand-500 / trắng), `box-shadow: var(--btn-shadow)`; `:hover/:active` → `--lp-cta-hover-bg` (mặc định brand-600); `:focus-visible` → ring DS 4px. `@layer utilities` + `!important` cho `padding-inline-end: 22px` (phải thắng `!px-6` của CMS).
  - Row set `--lp-cta-*` (kế thừa xuống button): rail `:has(products)` = brand-600 / hover 700; video (selector "REAL SPACES copy" sẵn có) = trong suốt + ring 1px brand-900, chữ brand-900, hover brand-50; editorial (full-bleed có buttons, không column-group) = trắng, chữ brand-900, hover brand-50.
- Phát hiện thêm khi verify: `page-builder.css` `shadow-none` cho link trong `buttons` đè `box-shadow` của `btn` → focus ring DS trên nút PB đã mất từ SLP-246; khối mới set lại `box-shadow` cho 6 CTA. Hover không thêm shadow (giống hero).
- Tailwind: `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify`; `cache:flush`.
- `CHANGELOG.md` `Launchpad_CmsContent` 1.2.6. README không có mục CTA — không đổi.
- Không đổi CMS content / seed / DB / template.

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| Tailwind build | PASS | — |
| Playwright Chrome 150, 1440 + 375: size / font / padding / radius / gap, mũi tên static 16px bên phải chữ, màu + ring theo section, hover (1440), focus ring (1440), hero + promo không đổi, 0 pageerror | ALL PASS (56) | `verify-cta.cjs`, `verify-cta.txt` |
| So ảnh với Figma (6 CTA × 2 viewport) | Khớp | `cta-1440-*.png`, `cta-375-*.png` |
| Safari / Firefox | NOT RUN | — |

## Handoff / còn mở

1. **Design "Go to Collection" dùng `bg/brand-solid-primary` (#45744c, brand-600)**, các CTA filled khác dùng `primary/brand-500` (#588f60) — đã làm theo đúng design; cần designer xác nhận có chủ ý không.
2. **Mobile font**: CTA theo token DS Label L mobile = 14/20; Figma mobile vẽ 16/24 — cùng câu hỏi với BUG-118Z8T handoff #2.
3. **Ngoài scope, lệch design**: nút `‹ ›` rail (`.lp-nav-btn`, `carousel.phtml`) — design viền + chevron #588f60 24px, hiện viền #d1d5dc + arrow-left/right #293e2d 18px; nút "Đăng ký" newsletter h36 (design h44).
4. **Báo owner SLP-246**: `shadow-none` trong `page-builder.css` tắt `box-shadow` của `btn` cho mọi nút PB (mất focus ring) trên toàn site, không chỉ homepage.
5. Cùng file với WIP chưa commit của phiên khác (`homepage.css`, `styles.css`, `CHANGELOG.md`) — khi commit, tách hunk "BUG-QJWCNG" + CHANGELOG 1.2.6; `styles.css` là file build chung, cần rebuild lúc commit.

## Correction round 1 (2026-09-25) — nút ‹ › rail + nút newsletter, chỉ dùng màu `:root`

**User**: "Sửa luôn, nên nhớ là dùng màu của root nếu đã có sẵn, không tự thêm màu riêng cho trang cms home".

### Mini Spec (bổ sung)
- **Goal**: nút ‹ › của product rail và nút "Đăng ký" newsletter khớp Figma; mọi màu lấy từ token DS trên `:root` (`--ds-*`), không hex / không `--color-hp-*` mới.
- **Expected Behavior**:
  1. `.lp-nav-btn` (Figma 2151:17177 / 2151:17178, mobile 2151:16954 / 2151:16955): 48×48, radius 6, nền trong suốt, viền 1px `--ds-primary-brand-500`, icon chevron outline 24px (Heroicons `chevron-left/right`, stroke 2) màu `--ds-primary-brand-500`; hover nền `--ds-primary-brand-50`; disabled giữ opacity 40%; focus-visible ring DS.
  2. `.lp-newsletter-btn` (Figma 2717:85947 / 2717:86197): DS `btn-size-l` (h44, px20 / py10, Label L), weight 500, nền `--ds-bg-brand-solid-primary`, chữ `--ds-text-white`, hover/active `--ds-primary-brand-700` (DS `btn-primary`); mobile full width như hiện tại.
  3. Các CTA section (khối BUG-QJWCNG) đã chỉ dùng `--ds-*` — giữ nguyên.
- **Constraints**: không thêm màu mới; template chỉ đổi icon (`carousel.phtml`, theme-owned); không đổi input newsletter / logic subscribe.
- **Out of Scope**: input newsletter (design px14 / border `--ds-border-gray-primary` — không phải button); các rule cũ khác trong `homepage.css` vẫn dùng `hp-*` / hex (không phải button).
- **Acceptance Criteria**:
  - [x] AC5: 1440 + 375 — `.lp-nav-btn` 48×48, border `1px rgb(88, 143, 96)`, nền trong suốt, svg 24×24 path `M15 19l-7-7 7-7` / `M9 5l7 7-7 7`, color rgb(88, 143, 96); hover nền rgb(244, 249, 244).
  - [x] AC6: 1440 + 375 — `.lp-newsletter-btn` h44, padding `10px 20px`, 500, nền rgb(69, 116, 76), chữ trắng; hover rgb(53, 87, 58).
  - [x] AC7: `homepage.css` diff của BUG-QJWCNG không có hex / `hp-*` color mới; AC1–AC4 vẫn PASS.

### Implementation CR1
- `carousel.phtml` (theme): icon nút ‹ › `LucideIcons::arrowLeft/RightHtml(18)` → `HeroiconsOutline::chevronLeft/RightHtml(24)` (path trùng Figma `chevron-outline`). `php -l` OK.
- `homepage.css`: `.lp-nav-btn` (layer components) — `border-color` / `color` `--ds-primary-brand-500`, nền `--ds-bg-white-transparent`, hover `--ds-primary-brand-50`, focus-visible ring `--ds-effects-focus-ring-primary` (bỏ `#d1d5dc`, `#293e2d`, `hp-soft`). Xoá `.lp-newsletter-btn` trong layer components (`hp-brand-dark` / `hp-ink`, vốn thua `btn` ở layer utilities); thêm rule unlayered `.lp-newsletter-btn { @apply btn-size-l; weight 500 }` — màu lấy từ `btn-primary` của template (`bg/brand-solid-primary`, hover brand-700).
- Build Tailwind + `cache:flush`. CHANGELOG 1.2.6 thêm mục CR1.
- Verify: `verify-cta.cjs` mở rộng AC5–AC6 → **ALL PASS (62)**; ảnh `nav-1440.png`, `newsletter-1440.png`, `newsletter-375.png`. AC7: diff phần BUG-QJWCNG không có hex / `hp-*`; dòng hex duy nhất trong diff `homepage.css` là `.lp-flash-title` (`lg:text-[#293e2d]`) thuộc WIP Flash Sale title của phiên khác.
- Handoff #3 (nav + newsletter) đã xử lý. Còn mở: input newsletter vẫn `#d1d5dc` / `hp-ink` (design `--ds-border-gray-primary`, px14) — không phải button.

## Correction round 2 (2026-09-25) — bản dịch text CTA trong i18n

**User**: "dịch các text của button cta" → chọn "tạo một bản dịch trong i18n để sẵn là được" + bộ dịch đề xuất.

- Text nút là nội dung PB trong CMS page `home` (1 page cho mọi store) → không đi qua `__()`; `{{trans "…"}}` trong nút không dùng được vì PB `tag-escaper` escape `"` → `&quot;` khi Admin save. Theo user: chỉ chuẩn bị sẵn bản dịch trong i18n theme, **không đổi CMS content**.
- 6 key đã có sẵn trong `app/design/frontend/Secomm/launchpad/i18n/{vi_VN,en_US}.csv` (SLP-213). Đổi 2 giá trị `vi_VN` theo bộ đã chọn: `Shop Collection` → "Mua ngay" (trước: "Mua sắm bộ sưu tập"), `Go to Collection` → "Xem bộ sưu tập" (trước: "Đến bộ sưu tập"). Giữ: Explore all → Xem tất cả; Explore → Khám phá; Shop in-stock sofa → Mua sofa có sẵn; View all post → Xem tất cả bài viết. `en_US` = identity, không đổi. `cache:clean translate full_page`.
- Lưu ý: working copy `vi_VN.csv` + `en_US.csv` đã là CRLF từ trước (index LF, `git ls-files --eol`: `i/lf w/crlf`) — không do task này; giữ nguyên, sửa CRLF-aware. Khi commit cần chuẩn hoá về LF, nếu không diff sẽ thành cả file.
