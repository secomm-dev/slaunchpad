---
id: BUG-118Z8T
type: bug
title: '[Homepage] Hero slider — mobile ngừng autoplay sau khi chạm; desktop button lệch design (SLP-268)'
project_code: SLP
parent:
external_refs:
  tickets: SLP-268
  figma: https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2-10
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review  # 2026-09-24: implement + verify tự động ALL PASS; chờ test thiết bị thật (AC5)
created: 2026-09-24
updated: 2026-09-24
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/code/Launchpad/CmsContent/view/frontend/templates/homepage/hero-autoplay.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/code/Launchpad/CmsContent/CHANGELOG.md
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

# [SLP][BUG-118Z8T] [Homepage] Hero slider — mobile autoplay + desktop font/button (SLP-268)

<!-- External ticket: SLP-268 "Fix section hero slider ở mobile khi người dùng chạm vào xem sau đó, không thấy slider tự động chuyển. Ở desktop kiểm tra lại font size, button so với design (Figma 2-10). Note: nhớ so với design system mới được pull code về". Mode C — embedded Mini-Spec + Approach. -->

## Summary

**Mobile.** `hero-autoplay.phtml` pause autoplay bằng `pointerenter` / `pointerleave` cho MỌI pointer type. Với touch, resume phụ thuộc hoàn toàn vào `pointerleave` sau khi thả tay; nếu trình duyệt không bắn (iOS Safari sau `pointercancel` khi cuộn / nhấn giữ link) thì `paused` kẹt `true` → dot ngừng chạy, slider không tự chuyển. `visibilitychange` còn ghi đè thẳng `paused` (mất trạng thái hover). Chrome 150 emulation (tap, vuốt ngang, vuốt dọc, nhấn giữ, kéo ngắn) luôn bắn `pointerleave` → không tái hiện; nghi WebKit iOS — cần xác nhận thiết bị thật.

**Desktop 1440 (Chrome 150, trước fix)** so với Figma `2151:17109`:

| | Figma | Hiện tại |
|---|---|---|
| Title | Inter Bold 60/72, -1 | 60/72 ✓ — font-family **system** (`ui-sans-serif`) ✗ |
| Description | Inter 18/28 | 18/28 ✓ — font-family **system** ✗ |
| Button | Label L 16/24 Medium, h48, 24×12, r6, `primary/brand-500` #588f60 | **14/20, 400, h36**, bg **#45744c** (brand-600) ✗ |

Root cause desktop:
1. `page-builder.css` `:is(a, button, div).pagebuilder-button-primary { @apply btn btn-primary }` **unlayered** → thắng mọi utility CMS (`@layer utilities`). Từ SLP-246 `btn` mặc định = size S (h36, label-m 14/20), `btn-primary` = brand-600.
2. Token DS: `token-transformer.mjs:155` sinh `--ds-global-typography-font-weight-*: 500px` (có `px`) → invalid → weight về inherit (400). Ảnh hưởng mọi `btn` — **ngoài scope**, báo owner SLP-246.
3. Class `font-sans` trong CMS được Hyvä CMS JIT biên dịch thành stack mặc định Tailwind, không phải `--font-sans` (Inter) của theme.

## Mini Spec

### Goal
Hero slider homepage tự chuyển slide lại sau khi người dùng chạm/vuốt trên mobile; desktop title/description/button khớp Figma + design system mới.

### Expected Behavior
1. Touch (mobile/tablet): đặt ngón tay trên hero → dot tạm dừng; thả tay (tap, vuốt, nhấn giữ, cuộn trang bắt đầu từ hero) → dot chạy tiếp từ tiến độ đang dở và slider tự chuyển khi dot đầy — không phụ thuộc `pointerleave`.
2. Chuột (desktop): hover hero → pause; rời chuột → chạy tiếp (như cũ).
3. Tab ẩn → pause; hiện lại → chạy tiếp nếu không còn nguyên nhân pause khác (hover / touch).
4. Button hero dùng DS `btn-size-xl` (h48, px24 py12, Label L: 16/24 ≥768px, 14/20 mobile theo token), weight 500, nền `--ds-primary-brand-500` (#588f60), hover/active brand-600, radius 6, không arrow.
5. Title / description / button hero render font `Inter` (`--font-sans`).

### Constraints / Rules
- Không đổi CMS content / seed / DB — sửa bằng theme CSS + template JS.
- Không sửa `token-transformer.mjs` / token generated (thuộc SLP-246) — hero tự set weight 500 qua `--btn-font-weight`.
- JS vanilla, CSP-safe (`$hyvaCsp->registerInlineScript()`), `prefers-reduced-motion` vẫn tắt autoplay.
- File CRLF giữ CRLF; không đụng hunk chưa commit của SLP-269 / SLP-274 cùng file.
- Build Tailwind chỉ bằng `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` (không `npm run build` — bước generate ghi đè token).

### Out of Scope
- Heading tablet 768–1279 (DS heading-1 = 48, markup nhảy 30 → 60 ở 1024) — Figma không có frame tablet.
- Lỗi `font-weight: …px` toàn site (SLP-246) và `font-sans` của CMS JIT ở các section khác.
- Đổi tốc độ autoplay / hiệu ứng dot.

### Acceptance Criteria
- [x] AC1: Chrome 150 iPhone 13 — sau tap / vuốt ngang / vuốt dọc / nhấn giữ 1.5s: `lp-dot-paused` = false sau khi thả, dot progress tăng và slide đổi trong ≤ 5s.
- [x] AC2: Mô phỏng WebKit (touch KHÔNG kèm `pointerleave`): dispatch `touchstart` → `touchend` ⇒ autoplay chạy tiếp (trước fix: kẹt).
- [x] AC3: 1440 — button hero: 16px / 24px / 500, h48, padding 12/24, radius 6px, bg rgb(88,143,96); hover bg rgb(69,116,76); title 60/72, description 18/28; font-family bắt đầu bằng `Inter`.
- [x] AC4: 375 — button h48, 14/20 / 500, bg #588f60; title 30/36, description 16/24, Inter. Không pageerror; button PB ngoài hero không đổi.
- [ ] AC5 (manual): iOS Safari + Android Chrome thật — chạm/vuốt hero xong slider vẫn tự chuyển.

## Approach

1. `hero-autoplay.phtml`: thay biến `paused` đơn bằng tập nguyên nhân pause (`hover` / `touch` / `hidden`). `pointerenter`/`pointerleave` chỉ xử lý `pointerType === 'mouse'`. Touch: `touchstart` (passive) → pause; `touchend` / `touchcancel` ở `document` (chắc chắn nhận dù ngón tay rời khỏi hero) khi `touches.length === 0` → bỏ pause. `visibilitychange` → nguyên nhân `hidden`.
2. `homepage.css`: rule unlayered cho `[data-content-type="slide"] .pagebuilder-button-primary` — `@apply btn-size-xl`, `--btn-bg` brand-500, `--btn-font-weight: var(--font-weight-medium)`; rule `:hover` / `:active` riêng (vì `--btn-bg` ở specificity cao hơn rule hover của `btn`); `font-family: var(--font-sans)` cho title/description.
3. Build Tailwind, `cache:flush`, verify Playwright Chrome 150 theo AC1–AC4; evidence `.ai/evidence/BUG-118Z8T/`; CHANGELOG `Launchpad_CmsContent` 1.2.2.

## Progress

- 2026-09-24: phân tích (repro Chrome 150 không tái hiện mobile; đo desktop); record + Mini-Spec + Approach; bắt đầu implement.
- 2026-09-24: implement xong; verify Playwright Chrome 150 ALL PASS (AC1–AC4). AC5 chờ test tay.

## Implementation

- `hero-autoplay.phtml` (CRLF giữ nguyên): `pauseReasons` (Set) + `setPaused(reason, on)` thay biến `paused` đơn. `pointerenter`/`pointerleave` chỉ khi `pointerType === 'mouse'`; `touchstart` (passive) trên slider → `touch`; `touchend`/`touchcancel` trên `document` với `touches.length === 0` → bỏ `touch`; `visibilitychange` → `hidden`. Logic fill rAF / arm / advance không đổi — thả tay thì dot chạy tiếp từ tiến độ dở.
- `homepage.css` (CRLF; chèn sau rule "hero slide: button KHÔNG arrow"): `[data-content-type="slide"] .pagebuilder-button-primary` → `@apply btn-size-xl` + `--btn-bg: var(--ds-primary-brand-500)` + `--btn-font-weight: var(--font-weight-medium)`; `:is(:hover, :active)` → brand-600; `[data-element="content"] :is(h1, h2, h3, p)` → `font-family: var(--font-sans)`.
- Tailwind: `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` — diff `styles.css` = đúng 3 rule mới. `cache:flush`.
- `CHANGELOG.md` `Launchpad_CmsContent` 1.2.2. README không có mục hero — không đổi.
- Không đổi CMS content / seed / DB.

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| php -l `hero-autoplay.phtml` | PASS | — |
| PHPCS Magento2 sev≥6 | 0 error / 1 warning (CRLF — convention file hiện có) | `phpcs.txt` |
| Tailwind build | PASS, `styles.css` +3 rule | — |
| Playwright Chrome 150 — iPhone 13 touch (tap, nhấn giữ 1.5s, vuốt ngang, vuốt dọc từ hero), chuỗi WebKit giả lập (touch không `pointerleave`), hover chuột | ALL PASS | `verify-hero.cjs`, `verify-hero.txt` |
| 1440: button 16/24/500 h48 w169 12×24 r6 #588f60, hover #45744c; title 60/72; desc 18/28; Inter | PASS (khớp Figma 2151:17112, w169) | `hero-1440.png`, `hero-1440-content.png` |
| 375: button h48 14/20/500 #588f60; title 30/36; desc 16/24; Inter | PASS | `hero-375.png` |
| iOS Safari / Android Chrome thật | NOT RUN | — |

## Handoff / còn mở

1. **AC5 manual**: test trên iPhone Safari + Android Chrome thật — chạm / vuốt / nhấn giữ hero, rồi thả: slider phải tự chuyển. Chrome emulation không tái hiện bug gốc, nên AC2 (mô phỏng WebKit) là bằng chứng gián tiếp.
2. **Button mobile**: đang theo token DS Mobile `label-l` = 14/20; Figma mobile frame `2151:16912` hiển thị 16/24 → cần designer xác nhận (có thể frame mobile chưa set variable mode Mobile).
3. **Báo owner SLP-246** (ngoài scope): `token-transformer.mjs:155` thêm `px` vào font-weight (`--ds-global-typography-font-weight-*: 500px`) → mọi `btn` DS đang weight 400; và `.pagebuilder-button-primary` ngoài hero vẫn là size S / brand-600 (h36, 14px).
4. CMS JIT `font-sans` = stack mặc định Tailwind ở mọi section CMS khác (không phải Inter) — ngoài scope, cân nhắc fix chung.
5. Cùng file với WIP chưa commit SLP-269 / SLP-274 (`homepage.css`) — khi commit, tách hunk "SLP-268 (BUG-118Z8T)" + CHANGELOG 1.2.2 + `hero-autoplay.phtml`.

## Correction round 1 (2026-09-24) — hero kéo được (draggable)

**User**: "cần dragable cho hero slider". Trước đó hero chỉ vuốt được bằng touch; chuột không kéo được (browser không có drag-to-scroll native).

### Mini Spec (bổ sung)
- **Goal**: desktop kéo hero bằng chuột như slider.
- **Expected Behavior**: nhấn giữ chuột + kéo ngang ⇒ track chạy theo chuột (tắt snap + smooth scroll khi kéo); thả tay khi đã kéo ≥ min(80px, 10% chiều rộng track) ⇒ sang 1 slide theo hướng kéo, dưới ngưỡng ⇒ về slide cũ; kẹp ở slide đầu/cuối (không vòng lại). Đã kéo (> 5px) ⇒ không mở link slide; click thường vẫn mở. Touch giữ vuốt native. Autoplay: đang hover chuột nên tạm dừng khi kéo; `slideChange` arm lại dot cho slide mới.
- **Constraints**: chỉ `pointerType === 'mouse'`; listener gắn trên `.lp-hero-slider` và lấy track lúc `pointerdown` (vì track do PB carousel tạo trong `DOMContentLoaded`, thứ tự chạy không cố định); drag chạy cả khi autoplay tắt / reduced-motion (khi reduced-motion thì `scrollTo` dùng `auto`).
- **Out of Scope**: kéo vòng từ slide cuối về slide đầu; cursor `grab` khi chưa kéo (slide là link nên giữ `pointer`, chỉ hiện `grabbing` khi đang kéo) — *(đổi ở CR2)*.
- **Acceptance Criteria**:
  - [x] AC6: 1440 — kéo trái 400px ⇒ `scrollLeft` = offset slide 2 (1441); khi kéo track theo chuột, `scroll-snap-type: none`; kéo phải ⇒ về 0; kéo 40px ⇒ về slide cũ; kéo phải ở slide đầu ⇒ vẫn 0; kéo quá slide cuối ⇒ kẹp 2882; sau khi settle snap bật lại; URL không đổi sau khi kéo; click thường ⇒ `/collection`; 0 pageerror. AC1–AC4 chạy lại vẫn PASS.

### Implementation CR1
- `hero-autoplay.phtml`: thêm một listener `DOMContentLoaded` riêng, không phụ thuộc guard autoplay. Idempotent qua `data-lp-hero-drag`. Ngưỡng 5px mới bắt đầu kéo, `setPointerCapture`, đích = offset slide (`offsetLeft` — slide cách nhau `gap` 16px nên không dùng `clientWidth`), `scrollTo` rồi bỏ `.lp-hero-dragging` ở `scrollend` (fallback 700ms). `click` capture nuốt 1 lần sau khi kéo; chặn `dragstart` (không ghost link).
- `homepage.css`: `.lp-hero-slider [data-track].lp-hero-dragging` → `scroll-snap-type: none`, `scroll-behavior: auto`, `user-select: none`, cursor `grabbing`. Build Tailwind: +2 rule. `cache:flush`.
- CHANGELOG 1.2.2 thêm mục CR1. Evidence: `verify-hero.cjs` / `verify-hero.txt` (ALL PASS).

## Correction round 2 (2026-09-24) — cursor bàn tay mở khi rê chuột

**User**: "hiện bàn tay mở ngay khi rê chuột lên hero".

- **Expected Behavior**: thiết bị có chuột (`hover: hover` + `pointer: fine`): rê lên hero (track + link slide) ⇒ `grab`; nút CTA vẫn `pointer`; đang kéo ⇒ mọi phần tử (kể cả CTA) `grabbing`; kéo xong ⇒ về `grab`. Touch không đổi.
- **Implementation**: `homepage.css` — `@media (hover: hover) and (pointer: fine)` đặt `grab` cho `[data-track]` + `a`, `pointer` cho `.pagebuilder-slide-button`; rule `.lp-hero-dragging` mở rộng thành `:is(a, button)`. Build: +2 rule, sửa 1 rule. `cache:flush`.
- [x] AC7: 1440 — slide `grab`, CTA `pointer`, khi kéo cả hai `grabbing`, sau khi kéo về `grab`. Chạy lại AC1–AC6 vẫn ALL PASS (`verify-hero.txt`).
