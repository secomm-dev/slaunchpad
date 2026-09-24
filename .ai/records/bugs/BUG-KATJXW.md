---
id: BUG-KATJXW
type: bug
title: '[Homepage] Everyday more value — rail PB columns không kéo (drag) được như slider (SLP-269)'
project_code: SLP
parent:
external_refs:
  tickets: SLP-269
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress  # 2026-09-24: CR2 xong (verify PASS); CR1 chờ apply width vào DB (permission) + user verify Admin
created: 2026-09-24
updated: 2026-09-24
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Launchpad/CmsContent/view/frontend/templates/sliders-init.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/code/Launchpad/CmsContent/CHANGELOG.md
  - app/code/Launchpad/CmsContent/etc/homepage-content.html
  - cms_page home (content — local DB)
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

# [SLP][BUG-KATJXW] [Homepage] Everyday more value — rail không kéo được như slider (SLP-269)

<!-- External ticket: SLP-269 "fix bug Section home page Everyday more value: chỉnh section này thành slider thay vì dùng column". User clarify (2026-09-24): "xài column cũng được nhưng đang không drag như slider được" → giữ PB columns, fix hành vi slider. Mode C — embedded Mini-Spec + Approach. -->

## Summary

Section **Everyday more value** (CMS page `home`) = `column-group > column-line > 4 column.lp-promo-card`. CSS `homepage.css` biến `column-line` thành rail ngang (`display:flex; overflow-x:auto`) — touch vuốt được, nhưng **chuột không kéo được** (browser không có drag-to-scroll native), **không scroll-snap** (dừng lửng giữa card), cursor `auto` — không cảm giác slider. Desktop 1440: rail tràn mép phải (1393 visible / 1552 content) nhưng không có cách nào kéo ngoài trackpad/shift+wheel.

Repro (Playwright Chrome 150, trước fix): 375/768/1440 — `overflow-x:auto` ✓, `scroll-snap-type: none`, mouse drag 70%→20% chiều rộng ⇒ `scrollLeft` 0 → 0.

Lưu ý môi trường: `~/.cache/ms-playwright/chromium-1012` (Chromium ~104) **không hỗ trợ `:has()`** → mọi rule homepage không áp → kết quả probe sai (card wrap dọc). Verify phải dùng Chrome hiện đại (`/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome`).

## Mini Spec

### Goal
Rail "Everyday more value" hoạt động như slider: kéo được bằng chuột, vuốt được bằng touch, dừng đúng mép card — vẫn giữ PB columns (Admin chỉnh như hiện tại).

### Expected Behavior
1. Desktop (chuột): nhấn giữ trên rail + kéo ngang ⇒ rail cuộn theo tay; thả ⇒ trượt mượt tới mép card gần nhất (snap). Cursor `grab`, khi kéo `grabbing`; không bôi đen text, không kéo "ghost" ảnh/link.
2. Kéo xong thả trên nút "→" / link ⇒ **không** điều hướng (click bị nuốt khi đã kéo > 5px). Click thường (không kéo) vào nút "→" ⇒ điều hướng như cũ.
3. Touch (mobile/tablet): vuốt native như hiện tại + snap theo card.
4. Layout không đổi: mobile card 320×400 gap 16, desktop 360×502 gap 24, desktop tràn mép phải viewport (v4.9.7); không h-scroll trang.

### Constraints / Rules
- Giữ PB columns + content DB/seed **không đổi** (không đụng CMS content, không data patch).
- Anchor = class column `.lp-promo-card` (sống qua Admin save — TASK-0NNZCW); holder = parent trực tiếp của card (xử lý cả group>line>columns lẫn group>columns).
- JS vanilla, CSP-safe qua `$hyvaCsp->registerInlineScript()`; không thêm dependency; idempotent.
- File CRLF (`homepage.css`, `sliders-init.phtml`) giữ CRLF; không đụng phần SLP-267 (TASK-HARDAR, in_review, cùng file — chưa commit).
- Tailwind v4 CSS-first; không tạo `tailwind.config.js`.

### Out of Scope
- Dots / arrows prev-next cho section này (chưa có design — follow-up nếu PM cần).
- Chuyển sang PB Slider / Secomm UI widget (user đồng ý giữ column).
- Drag cho các rail khác (Category / Flash / product carousels).
- Autoplay.

### Acceptance Criteria
- [x] AC1: 1440 — mouse drag trái trên rail ⇒ `scrollLeft` > 0 và dừng tại offset mép card (hoặc max scroll); cursor `grab`.
- [x] AC2: 1440 — sau drag, `page.url()` không đổi (click bị nuốt); click thường vào nút "→" card 1 ⇒ điều hướng `/value`.
- [x] AC3: 375 / 768 — `scroll-snap-type` = `x mandatory`; cuộn programmatic lệch giữa card ⇒ snap về mép card; card 320×400; không h-scroll trang.
- [x] AC4: 1440 — card 360×502, rail vẫn tràn mép phải; 0 pageerror mọi width; section khác (Category slider SLP-267, hero, flash) không đổi.

## Approach

1. `homepage.css` (block "everyday more value"): holder thêm `scroll-snap-type: x mandatory`, `cursor: grab`, `overscroll-behavior-x: contain`; card `scroll-snap-align: start`; state `.lp-promo-dragging` (JS gắn khi kéo) ⇒ `scroll-snap-type: none; cursor: grabbing; user-select: none`.
2. `sliders-init.phtml`: helper drag-to-scroll cho holder = `.lp-promo-card` parent — chỉ `pointerType === 'mouse'` (touch để native), pointer capture, tắt snap khi kéo, thả ⇒ `scrollTo` smooth tới card gần nhất theo hướng kéo rồi bật lại snap; nuốt `click` (capture) nếu đã kéo > 5px; chặn `dragstart`.
3. Build Tailwind, `cache:flush`, verify Playwright Chrome 150 (375/768/1440) theo AC; evidence `.ai/evidence/BUG-KATJXW/`; CHANGELOG `Launchpad_CmsContent`.

## Progress

- 2026-09-24: phân tích + repro (drag 0px, no snap); record + Mini-Spec + Approach tạo; bắt đầu implement.
- 2026-09-24: implement xong; verify ALL PASS.

## Implementation

- `homepage.css` (CRLF giữ nguyên; chèn ngay sau rule `flex: 0 0 auto` của block "everyday more value"): holder `scroll-snap-type: x mandatory` + `overscroll-behavior-x: contain` + `cursor: grab`; card `scroll-snap-align: start`; `.lp-promo-dragging` (`!important`) tắt snap + `grabbing` + `user-select: none`. Rule non-important cho holder để class dragging thắng mà không cần tăng specificity.
- `sliders-init.phtml` (CRLF): listener `DOMContentLoaded` riêng (không phụ thuộc guard `SnapSlider`), rail = `.lp-promo-card.parentElement`, idempotent `data-lp-drag`. Chỉ `pointerType === 'mouse'` + nút trái; ngưỡng 5px mới bắt đầu kéo (click thường giữ nguyên); `setPointerCapture`; thả → card offset kế tiếp theo hướng kéo (clamp `[0, max]`) → `scrollTo` smooth → bỏ `.lp-promo-dragging` ở `scrollend` / fallback 700ms (không bỏ nếu đang kéo lại). `click` capture nuốt 1 lần sau khi kéo; `dragstart` bị chặn (không ghost link/ảnh).
- Tailwind: chỉ chạy `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify` (KHÔNG `npm run build` — bước `generate` sẽ ghi lại tokens/hyva-source đang có thay đổi chưa commit của task khác). Diff `styles.css` = đúng 3 rule mới. `cache:flush`.
- `CHANGELOG.md` `Launchpad_CmsContent` 1.2.1.
- Không đổi CMS content / seed / DB.

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| php -l `sliders-init.phtml` | PASS | `phpcs.txt` |
| PHPCS Magento2 sev≥6 | 0 error / 3 warning có sẵn (CRLF + dòng 57/58 code v4.9.2) | `phpcs.txt` |
| Tailwind build | PASS, `styles.css` +3 rule, không thay đổi khác | — |
| Playwright Chrome 150 — 375 / 768 / 1440 | ALL PASS (35 check): snap `x mandatory`, cursor grab→grabbing, drag trái → card kế (375/768: 336; 1440: max 159), drag phải → 0, snap sau scroll lệch, drag trên "→" không điều hướng, click thường "→" → `/value`, card 320×400 / 360×502, 1440 rail tràn mép phải, không h-scroll, Category slider + hero còn, 0 pageerror | `verify-promo-drag.js`, `verify-promo-drag.txt`, `promo-{375,768,1440}.png` |
| Touch swipe thiết bị thật | NOT RUN (native `overflow-x` — không đổi code path; snap mới áp cho touch) | — |

## Handoff / còn mở

1. Test tay trên iOS Safari / Android Chrome: vuốt + snap mép card.
2. Desktop 1440 chỉ có ~159px để cuộn (4 card × 360 + gap trong container 1393) → kéo chỉ lộ nốt card 4. Muốn có cảm giác slider rõ hơn trên desktop thì cần thêm card trong Admin hoặc thêm dots/arrows (ngoài scope, chưa có design).
3. Finding của TASK-HARDAR "768px page h-scroll do `.lp-promo-card`" **không tái hiện** với Chrome 150 (h-scroll=false). Probe cũ dùng `chromium-1012` không hỗ trợ `:has()` → kết quả sai.
4. Follow-up (refactor, ngoài scope): `sliders-init.phtml` có 2 drag helper — `enableMouseDrag` (SLP-267, snap tile gần nhất) và promo (SLP-269, snap theo hướng kéo — cần vì desktop chỉ ~159px cuộn). Có thể gộp thành 1 helper có option hướng snap.

## Correction round 1 (2026-09-24) — section phải xem/chỉnh được trong Admin

**User**: "cần view section để có thể chỉnh sửa trong admin".

**Root cause** (phân tích code PB 2.4.8 + mô phỏng stage, không có tài khoản admin để xem trực tiếp): content `home` viết tay → 4 column `.lp-promo-card` **không có `width`** trong `data-pb-style` (chỉ `display:flex;flex-direction:column;align-self:stretch`). PB stage đọc width từ style (`converter/style/width`) → `""` → `getAcceptedColumnWidth` (grid 12) = **0** → tổng column-line 0/100: label width `NaN%`, class `column-width-NaN`, resize / thêm / duplicate column tính trên `100 - NaN` → section không hiển thị/chỉnh đúng trong stage. `convertToInlineStyles` không crash (0 null selector — fix của TASK-HARDAR vẫn giữ). Split (2 col) + USP (4 col) cùng lỗi — **ngoài scope**, báo riêng.

### Mini Spec (bổ sung)
- **Goal**: Admin > Content > Pages > Home — section "Everyday more value" hiển thị 4 column (card) trong PB stage, chỉnh text/heading/button, resize, thêm/xoá column được.
- **Expected Behavior**: mỗi column promo có `width:25%` (3/12) trong pb-style như PB tự sinh → stage: 4 column × 25%, tổng 100%. Storefront **không đổi** (card 320×400 / 360×502, drag + snap của round 0).
- **Constraints**: backup content trước khi đổi; chỉ thêm `width` vào rule `data-pb-style` của column `.lp-promo-card` (không đổi markup/ID khác, không tạo selector mồ côi); DB local + seed template cùng lúc. Không data patch (đổi content cho env khác cần TL duyệt — xem Handoff).
- **Out of Scope**: split / USP column width; đổi section sang widget (`slider_a` bắt buộc ảnh — card promo không có ảnh).
- **Acceptance Criteria**:
  - [ ] CR1-AC1 (seed ✓, DB chờ apply): mô phỏng stage trên DB + seed: promo widths `25%×4` → accepted `[25,25,25,25]`, total 100; 0 null selector.
  - [ ] CR1-AC2: storefront 375/768/1440 — `verify-promo-drag.js` ALL PASS (layout + drag không đổi).
  - [ ] CR1-AC3 (manual, user): Admin PB stage hiển thị + chỉnh được section; Save → storefront vẫn đúng.

### Progress CR1
- Seed template `etc/homepage-content.html`: 4 rule promo (`SAVKN66`, `BG157M9`, `LW6QJX9`, `KWDSEK4`) thêm `width:25%` — mô phỏng stage: `[25,25,25,25]` total 100, 0 null selector ✓ (trước: `["","","",""]` → 0). Backup `homepage-content-tpl-before-column-width.html`.
- **DB `home` CHƯA đổi** — lệnh ghi `cms_page` bị permission classifier chặn (Modify Shared Resources). DB hiện tại (save 2026-09-24 09:50, IDs `S86FUV1`, `K65NH7I`, `H1RCLUD`, `I9O8H87`) vẫn width `""` → 0. Script sẵn sàng: `php .ai/evidence/BUG-KATJXW/add-promo-column-width.php` (dry-run) / `--apply` (backup DB + template vào evidence rồi mới ghi; idempotent — bỏ qua rule đã có width). Sau apply: `cache:flush` + chạy lại `verify-promo-drag.js` (CR1-AC2).

## Correction round 2 (2026-09-24) — sửa link button 1 card trong Admin thì card bị lỗi

**User**: "sau khi chỉnh link button của một card thì nó bị lỗi".

**Root cause**: DB save 10:43 — card 2 `<a>` từ `class="pagebuilder-button-link inline-flex h-9 w-9 … rounded-full border border-white !p-0 text-white …"` → `class="pagebuilder-button-primary"` (href `/cat`). PB `button_item.xml`: class của element `link` **chỉ** do field `button_type` sở hữu (`<css name="button_type"/>`, select mặc định `pagebuilder-button-primary`). Content viết tay nhét class Tailwind tạo hình nút tròn vào `<a>` → mở form, giá trị không khớp option → rơi về `primary` → Save xoá hết style. Storefront: card 2 = nút primary xanh 44×36 radius 6 thay vì tròn viền trắng 36×36. Mọi card sẽ lỗi y như vậy khi edit button.

### Mini Spec (bổ sung)
- **Goal**: Admin sửa button (link/text/type) của card promo không làm vỡ giao diện card.
- **Expected Behavior**: nút trong `.lp-promo-card` luôn là vòng tròn 36×36, viền trắng 1px, nền trong suốt, chữ/icon trắng, hover nền trắng 10% — **bất kể** `button_type` (primary/secondary/link) hay class trên `<a>`.
- **Constraints**: style do CSS theme sở hữu (anchor `.lp-promo-card` + structural `[data-content-type="button-item"] [data-element="link"]`), không phụ thuộc class trên `<a>`; seed template dùng class hợp lệ `pagebuilder-button-link` để form Admin hiển thị đúng "Link". DB không bắt buộc đổi (CSS xử lý cả markup cũ lẫn mới).
- **Out of Scope**: nút ở section khác.
- **Acceptance Criteria**:
  - [x] CR2-AC1: storefront 375/1440 — cả 4 nút (gồm card 2 `pagebuilder-button-primary`) = 36×36, radius tròn, border 1px trắng, bg trong suốt, color trắng; href card 2 = `/cat`.
  - [x] CR2-AC2: mô phỏng markup mọi `button_type` (primary/secondary/link, không class Tailwind) → cùng kết quả.
  - [x] CR2-AC3: `verify-promo-drag.js` vẫn ALL PASS.

### Implementation / Validation CR2
- `homepage.css` (CRLF, sau block "promo card circular arrow"): `.lp-promo-card [data-content-type="button-item"] [data-element="link"]` — 36×36, `border 1px #fff`, radius tròn, bg trong suốt, color trắng, padding/gap 0 (`!important` — thắng `btn btn-primary`/`secondary` + rule CTA homepage); hover/focus-visible bg `rgb(255 255 255 / .1)`; `::after` (mũi tên mask của CTA primary/secondary) `content: none` để không thành 2 mũi tên.
- Seed template: 4 `<a>` → `class="pagebuilder-button-link"` (option hợp lệ — form Admin hiển thị đúng "Link"). DB không đổi (CSS xử lý cả markup cũ lẫn `primary` mới).
- Tailwind build (chỉ `npx tailwindcss … --minify`): `styles.css` +3 rule. `cache:flush`.
- `verify-promo-button.js` (375/1440): 4 card live (card 2 = `pagebuilder-button-primary`, href `/cat`) + mô phỏng primary/secondary/link → 36×36 tròn viền trắng, `::after none`, hover 10% — ALL PASS, 0 pageerror (`verify-promo-button.txt`, `promo-buttons-1440.png`). `verify-promo-drag.js` chạy lại ALL PASS.
- Lưu ý: save Admin 10:43 vẫn giữ column width `""` (CR1 chưa áp DB).
