---
id: TASK-SFGVW0
type: task
title: 'Flash sale section: new background gradient + countdown redesign (SLP-297)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-297
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-02
updated: 2026-10-02
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only theme layer + module template — không chạm §12 (payment/checkout/order/DB schema/security); 0 DB edit, 0 config, 0 vendor
components:
  - app/code/Launchpad/CmsContent/view/frontend/templates/product/widget/flash-sale.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv
  - app/design/frontend/Secomm/launchpad/i18n/en_US.csv
source_areas:
  - hyva-theme-frontend
  - theme-tailwind-v4
  - cms-content
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 50212e87 (working tree — code task khác left unstaged)
last_verified: 2026-10-02
supersedes: []
---

# [SLP][TASK-SFGVW0] Flash sale section: new background gradient + countdown redesign (SLP-297)

<!-- External ticket: SLP-297 "Edit section flash sale với new background color và countdown" —
     Figma 2151:17103 / node 3229-145220 (desktop 1440). Mode C — embedded Mini-Spec. -->
<!-- User chốt (02-10): mobile = scale down; CTA giữ nguyên (đã có sẵn trong DB PB content);
     label vi = giờ/phút/giây; gradient exact = linear-gradient(278deg, #DD5800 2.83%, #C10000 98.02%). -->

## Summary

Section Flash sale trên homepage (widget `Launchpad_CmsContent` FlashSaleList, TASK-0NNZCW) đổi skin theo Figma `3229-145220`:

- **Nền section**: gradient `linear-gradient(278deg, #DD5800 2.83%, #C10000 98.02%)` (user chốt exact CSS) — full-bleed tới 2 mép viewport.
- **Title**: trắng (cả mobile + desktop; trước giờ `#293e2d`/đen). Size giữ Heading-3 Inter Bold 36/40 `-0.5px` (v4.9.9 đã đúng).
- **Countdown**: bỏ pill "Sale ending in 00:00:00" (icon clock + label) → 3 box `hr / min / sec`: nền `white/30`, `rounded-lg` (8px), số Heading-4 Inter Medium 30/36, label Label-M Inter Medium 14/20, ngăn cách `:` cùng cỡ số — text trắng. Figma pill cũ `hidden=true` = bị thay thế.
- **CTA**: giữ nguyên (user chốt — buttons element "Khám phá ngay"/"Explore all" → `/flash-sale` đã tồn tại trong DB PB content, nằm ngoài widget).
- **Mobile**: scale down desktop (user chốt — không có frame mobile riêng): số 24/28, unit 12/16, gap nhỏ hơn.
- **Label i18n**: en `hr/min/sec` (identity), vi `giờ/phút/giây` (user chốt).

## Mini Spec

### Goal
- Section flash sale có nền gradient full-bleed + countdown 3 box, khớp Figma desktop; mobile scale down.

### Expected Behavior
- Nền gradient phủ toàn section (row PB), tràn tới 2 mép viewport (pattern bleed `calc(50% - 50vw)` — an toàn vì `body.cms-index-index { overflow-x: clip }` AC-4).
- Title "Siêu sale"/"Flash sale" trắng trên nền gradient.
- Countdown đếm từng giây, 3 box riêng biệt; khi sale hết → **toàn section biến mất** như cũ (server gate `isSaleEnded()` + client `hide()` scoped `.lp-flash` — BUG-9X14Y1 giữ nguyên; nền gradient cũng tự tắt theo vì row selector `:has(.lp-flash)` hết match).
- Product slider, dots mobile, card pipeline (PLP parity): **không đổi**.
- CTA button PB: không đổi.

### Out of Scope
- CTA restyle (design có style mới brand-100 nhưng user chốt giữ nguyên).
- Product card (Sale badge `#c10007` + nền ảnh đã khớp design sẵn).
- Widget options mới (không thêm param — gradient cố định theo design).

## Approach

1. **Nền + title (homepage.css, code-owned — KHÔNG sửa DB/PB admin, tránh trap RAW quote/`payload=`):**
   - `body.cms-index-index [data-content-type="row"]:has(.lp-flash)` → background gradient + `margin-inline: calc(50% - 50vw)` / `padding-inline: calc(50vw - 50%)` (full-bleed, inner geometry giữ nguyên) + `padding-block: 1.5rem` (design 24px top/bottom).
   - `.lp-flash-title`: `text-black` → `text-white`, `lg:text-[#293e2d]` → `lg:text-white`; rule PB-heading legacy v4.9.9 đổi `#293e2d` → `#ffffff` (consistency nếu page khác dùng PB heading).
2. **Countdown (flash-sale.phtml + homepage.css):** markup 3 `.lp-countdown-cell` (num + unit) + 2 `.lp-countdown-sep` trong `.lp-countdown-box`; Alpine `lpFlashCountdown` đổi `left: 'HH:MM:SS'` → `h/m/s` riêng (cùng tick interval 1s, giữ `hide()` scoped `.lp-flash` + FPC-staleness guard). CSS: box `bg-white/30 rounded-lg px-2 py-1 gap-[2px]`; num `w-8 lg:w-10 text-[24px] lg:text-[30px] leading-7 lg:leading-9 font-medium tabular-nums`; unit `text-xs lg:text-sm font-medium`; sep cùng cỡ num. Giữ rule desktop absolute cho chế độ không-title (no-title mode).
3. **i18n (BR-001):** `__('hr')`/`__('min')`/`__('sec')` server-side trong phtml → CSV: en identity `hr/min/sec`, vi `giờ/phút/giây` (cả 2 file; chú ý trailing-newline trap khi append). Dịch server-side → không cần SCD.
4. **Build:** `npm run build` (web/tailwind, as secomm) + cp `styles.css` → `pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/` (F9 trap) + `cache:flush` as secomm.
5. **Verify:** curl homepage (vi) — markup box + label "giờ/phút/giây" + gradient rule trong CSS build; label en qua CLI store emulation (store-switch HTTP chết local — LL-0011). Evidence `.ai/evidence/TASK-SFGVW0/`.

### Constraints / Rules
- Tailwind v4 CSS-first (`@theme`/`@source` trong `tailwind-source.css`) — KHÔNG tạo `tailwind.config.js`; utility trong phtml phải nằm trong `@source` scope (CmsContent đã có dòng `code/Launchpad/CmsContent/view/**/*.phtml`).
- Storefront strings cả 2 CSV `vi_VN.csv` + `en_US.csv` (BR-001); dịch server-side `__()` trong phtml — không cần SCD.
- Không sửa DB content/PB admin (trap RAW quote/`payload=`/validate-css-class) — nền + title code-owned qua CSS `:has(.lp-flash)` (pattern v4.9.9).
- Không refactor ngoài scope: giữ nguyên server gate `isSaleEnded()`, client `hide()` scoped `.lp-flash` (BUG-9X14Y1), slider/dots/card pipeline, CTA PB, widget options.
- Không commit/push — code chờ TL review; 0 vendor edit, 0 config, 0 schema.

### Acceptance Criteria
- **AC-001**: Section flash sale có nền gradient `linear-gradient(278deg, #DD5800 2.83%, #C10000 98.02%)`, tràn full 2 mép viewport (1440), không gây horizontal scrollbar.
- **AC-002**: Title section màu trắng cả mobile + desktop; size/weight/tracking giữ nguyên (36/40 Bold -0.5px desktop).
- **AC-003**: Countdown render 3 box (nền white/30, bo tròn 8px) + 2 dấu `:`, số đếm đúng HH/MM/SS theo `sale_end`, cập nhật mỗi giây.
- **AC-004**: Label hiển thị "giờ/phút/giây" ở store vi, "hr/min/sec" ở store en (CSV, BR-001).
- **AC-005**: Mobile 375: box scale down (số 24px, unit 12px), grid 2 cột slider + dots giữ nguyên hành vi cũ.
- **AC-006**: Khi `sale_end` đã qua: server render nothing; client stale-FPC → `hide()` ẩn đúng `.lp-flash` (không ẩn cả row); nền gradient tắt theo (row `:has()` hết match), CTA PB còn lại trên nền trắng (hành vi như hiện trạng).
- **AC-007**: CTA button + product card không đổi; 0 pageerror console.

### Test Cases (QC)
- TC-1: Homepage vi desktop 1440 — nền gradient full-bleed, title trắng, countdown 3 box đếm đúng (so với `sale_end`), label giờ/phút/giây.
- TC-2: Homepage mobile 375 — box scale, không overflow-x, slider grid 2 cột + dots hoạt động.
- TC-3: Đổi `sale_end` sang quá khứ (staging/test) → section biến mất hoàn toàn (kể cả FPC stale → client hide), nền tắt.
- TC-4: Store en — label hr/min/sec (CLI emulation hoặc QC demo).
- TC-5: Click CTA "Khám phá ngay" → vẫn tới `/flash-sale`, style không đổi.

## Implementation Notes

- Nền đặt qua CSS `:has()` trên row — cùng pattern v4.9.9 (title); lý do không dùng PB row background admin: bg PB nằm trên inner *contained* (không full-bleed được) + trap admin stage (RAW quote/`payload=`/validate-css-class).
- `padding-block` 24px theo design frame (title y=24; CTA bottom edge 783/807). Khoảng cách với section lân cận tăng thêm 24px top/bottom — verify cold render so design.
- Alpine: 3 reactive string (`h/m/s`) thay 1 chuỗi — giữ `Math.max(0, ...)`, `padStart(2,'0')`; giờ > 99 (sale > 4 ngày) tràn width w-10 — chấp nhận như hiện trạng (pill cũ cũng vậy), design hiển thị tối đa 99.
- Bỏ SVG clock + phrase "Sale ending in" khỏi phtml (pill bị thay); phrase vẫn giữ trong CSV (không gây hại, có thể dùng chỗ khác).
- `hp-countdown` (homepage.css ~line 56) là block legacy khác cho CMS content — KHÔNG đụng.

## Update

- **2026-10-02 — DEV DONE, chờ TL review (Mode C)**: implement theo Approach; verify **Playwright 8/8 AC PASS 0 pageerror** (desktop 1440 + mobile 375: gradient `linear-gradient(278deg, #DD5800 2.83%, #C10000 98.02%)` full-bleed — row == body width, pixel mép trái `#c20100`; title trắng; 3 box alpha 0.3 radius 8 tick đúng, 3 box cùng height sau fix `min-w`+`nowrap` (hour 462h wrap "46/2" với `w-10` cứng ban đầu); label giờ/phút/giây; mobile scale 24/28 min-w 32; grid 2 cột giữ nguyên; AC-006 DOM-test nền tắt theo `.lp-flash`, CTA còn lại). F9 cp static 2 locale + `cache:flush`. En label chỉ verify mức CSV (locale drift store 2 — LL-0011). Evidence `.ai/evidence/TASK-SFGVW0/` (RESULTS.md + verify.js + 2 png). 0 DB edit, 0 config — nền code-owned qua `:has()`; code chưa commit — chờ TL review.
