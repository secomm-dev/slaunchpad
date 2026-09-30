---
id: BUG-3XJSQG
type: bug
title: '[Homepage] Living, Reimagined — text lệch vị trí so với design (SLP-271)'
project_code: SLP
parent:
external_refs:
  tickets: SLP-271
  figma: https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17164
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review  # 2026-09-25: implement + verify tự động ALL PASS; chờ review
created: 2026-09-25
updated: 2026-09-25
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/code/Launchpad/CmsContent/CHANGELOG.md
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

# [SLP][BUG-3XJSQG] [Homepage] Living, Reimagined — canh text theo design (SLP-271)

<!-- External ticket: SLP-271 "Section home living, reimagined canh chỉnh text ở vị trí như design". Mode C — embedded Mini-Spec + Approach. -->

## Summary

Design (Figma `2151:17164` desktop 1439, `2151:16941` mobile 375):

| | Figma | Hiện tại (Chrome 150, trước fix) |
|---|---|---|
| Desktop 1440 | Section 620 cao; ảnh 940×620 sát mép trái; cột text x=972 (ảnh + 32), rộng 467 (text 427 + chừa 40 phải); text canh đáy, button **sát đáy section** | Ảnh vuông 931×931 (asset 1000×1000); đáy button cách đáy section **24px** (`lg:!pb-6`) |
| Desktop 1920 | (theo tỉ lệ 1440) | Ảnh chỉ rộng **1000px** trong cột 1251px → **trống 251px** giữa ảnh và text; text x=1283 |
| Mobile 375 | Ảnh 375×535; heading cách ảnh 16px; padding ngang 8 | Figure 535 cao nhưng ảnh chỉ 360 → **trống 175px** giữa ảnh và heading |

Root cause: `<img>` giữ `max-width:100%; height:auto` (data-pb-style) — không lấp cột media, nên text "trôi" xa ảnh; cộng padding-bottom 24px của cột text ở desktop. Class CMS JIT (`hcms-page-2-lg:!pb-6`) nằm trong `@layer utilities` với `!important` → rule `!important` unlayered của theme **không đè được**; phải override trong cùng layer với specificity cao hơn.

## Mini Spec

### Goal
Text section "Living, Reimagined" nằm đúng vị trí design so với ảnh ở mobile và desktop (1440 design, 1920 màn chuẩn).

### Expected Behavior
1. Ảnh luôn lấp đầy khung media (`object-fit: cover`, crop giữ đáy ảnh như Figma) — không còn khoảng trống giữa ảnh và text.
2. Desktop ≥1024: khung ảnh theo tỉ lệ design 940:620; cột text cách mép phải ảnh 32px, chừa 40px mép phải viewport; khối text canh đáy, đáy button trùng đáy ảnh (không padding-bottom).
3. Mobile/tablet <1024: ảnh full width cao 535px; heading cách đáy ảnh 16px; padding ngang giữ như hiện tại (8px mobile / 24px tablet).
4. Nếu text cao hơn khung ảnh (viewport hẹp ~1024), ảnh giãn theo chiều cao hàng (vẫn cover), không lộ khoảng trống.

### Constraints / Rules
- Chỉ sửa theme CSS (`homepage.css`); không đổi CMS content / seed / DB.
- Scope selector đúng section split (row full-width/full-bleed chứa column-group/line **và** column có image) — không ảnh hưởng promo cards, newsletter, section khác.
- Build Tailwind bằng `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` (không `npm run build`); `pub/static` styles.css là symlink.
- Không đụng WIP chưa commit của người khác (`hero-autoplay.phtml`, `form.css`, `README.md`).

### Out of Scope
- Button "Explore all": mũi tên `::after` đè giữa chữ + size S (h36) thay vì XL (h48) — lỗi button PB global (SLP-246), cũng gặp ở "View all post" (Journal) → ticket riêng.
- Nội dung text (design có 2 đoạn mô tả lặp — placeholder) và asset ảnh (vuông 1000×1000, design crop ngang).
- Font-family CMS JIT `font-sans` (stack mặc định, không phải Inter) — đã flag ở BUG-118Z8T.

### Acceptance Criteria
- [x] AC1 (1440): ảnh `object-fit: cover` lấp khung media; khung media tỉ lệ 940:620 (±2px); text x = mép phải ảnh + 32 (±1); mép phải text = viewport − scrollbar − 40 (±1); đáy button = đáy section (±1).
- [x] AC2 (1920): như AC1 — không còn khoảng trống giữa ảnh và cột text.
- [x] AC3 (375 mobile): ảnh render 375×535 (không trống); heading.top = ảnh.bottom + 16 (±1); text x = 8.
- [x] AC4 (1024): không trống dưới ảnh (ảnh cao = cao hàng); không h-scroll.
- [x] AC5: không h-scroll ở 375/1024/1440/1920; 0 pageerror; section promo "Everyday more value" + newsletter kích thước không đổi so với trước fix.

## Approach

1. `homepage.css` — sau khối v4.9.2 (split full width), thêm khối SLP-271 scope `row:is(full-width, full-bleed):is(:has(column-group), :has(column-line))`:
   - Column chứa image: `figure` mobile `height: 535px`; `img` `width/height: 100%`, `object-fit: cover`, `max-width: none` (`!important` — đè data-pb-style `#html-body` không-important).
   - ≥64rem: `button-item` `margin-bottom: 0` (page-builder.css `mb-2` unlayered, không important).
   - ≥64rem: `figure` `height: auto; aspect-ratio: 940 / 620; flex: 1 1 auto`.
   - Column chứa heading: padding-bottom 0 ở ≥64rem — đặt trong `@layer utilities` với `!important` (đè `hcms-page-2-lg:!pb-6` layered-important nhờ specificity cao hơn).
2. Build Tailwind, `cache:flush`, verify Playwright Chrome 150 theo AC1–AC5; evidence `.ai/evidence/BUG-3XJSQG/`.
3. CHANGELOG `Launchpad_CmsContent` 1.2.4 (section homepage do module này seed).

## Progress

- 2026-09-25: phân tích (đo Figma + trang local 375/1440/1920); record + Mini-Spec + Approach; bắt đầu implement.
- 2026-09-25: implement xong; verify Playwright Chrome 150 ALL PASS (AC1–AC5).

## Implementation

- `homepage.css` (LF; chèn sau khối v4.9.2 "split full width"), selector scope `row:is([data-appearance=full-width], [data-appearance=full-bleed])` + column `:has(> [data-content-type=image])` / `:has(> [data-content-type=heading])`:
  - `figure`: `position: relative`, `height: 535px`, `overflow: hidden`; ≥64rem `height: auto`, `aspect-ratio: 940 / 620`, `flex: 1 1 auto` (giãn theo hàng nếu text cao hơn).
  - `img`: `position: absolute; inset: 0; width/height: 100%; max-width: none; object-fit: cover; object-position: center bottom` — crop giữ đáy (đối chiếu Figma: bỏ ~320px phía trên asset 1000×1000, khớp cả desktop lẫn mobile). Không set `display` → giữ `pagebuilder-mobile-hidden/only`.
  - ≥64rem: `button-item { margin-bottom: 0 }` (đè `mb-2` của page-builder.css).
  - `@layer utilities { ≥64rem: cột heading padding-bottom: 0 !important }` — đè `hcms-page-2-lg:!pb-6` (CMS JIT, layered important) bằng specificity trong cùng layer.
- Tailwind: `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` — diff `styles.css` = đúng các rule mới (rule padding nằm trong `@layer utilities`). `pub/static/.../styles.css` là symlink → không cần copy. `cache:flush`.
- `CHANGELOG.md` `Launchpad_CmsContent` 1.2.4. Không đổi CMS content / seed / DB.

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| Tailwind build | PASS | — |
| 375: ảnh 375×535 cover, heading = đáy ảnh + 16, text x=8 | PASS | `split-375-{before,after}.png`, `figma-mobile-2151-16941.png` |
| 1024: text = ảnh + 32, chừa 40 phải, đáy button = đáy ảnh = đáy section | PASS | `verify-split.txt` |
| 1440: ảnh 931×614 (940:620), text x = 963 (= ảnh + 32), đáy button = đáy section | PASS | `split-1440-{before,after}.png`, `figma-desktop-2151-17164.png` |
| 1920: ảnh 1251×825, text x = 1283 (= ảnh + 32) — hết khoảng trống 251px | PASS | `split-1920-{before,after}.png` |
| Chỉ figure section split bị áp style; promo card 320×400 / 360×502 không đổi; không h-scroll; 0 pageerror | PASS | `verify-split.cjs`, `verify-split.txt` |

## Handoff / còn mở

1. **Button "Explore all"** (ngoài scope, lỗi global): mũi tên `::after` đang `position: absolute` đè giữa chữ ("Expl→re all", cũng gặp ở "View all post" Journal) và size S h36 thay vì XL h48 của design — cùng gốc SLP-246 (`.pagebuilder-button-primary { @apply btn btn-primary }`). Đề xuất ticket riêng.
2. Typography (ngoài scope): heading/desc render system font (CMS JIT `font-sans`), design Inter; heading mobile design weight thường, hiện bold.
3. Design có 2 đoạn mô tả (placeholder lặp) — content hiện 1 đoạn; không đổi.
4. Khi commit: chỉ `homepage.css` (khối SLP-271), `web/css/styles.css`, `CmsContent/CHANGELOG.md` 1.2.4, record + evidence — không lẫn WIP khác (`hero-autoplay.phtml`, `form.css`, `CmsContent/README.md`).
