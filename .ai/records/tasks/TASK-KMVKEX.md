---
id: TASK-KMVKEX
type: task
title: 'Homepage: section CTA secondary theo style guide Button DS (SLP-297 follow-up)'
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
decision_assessment: display-only theme CSS — không chạm §12; 0 DB edit (secondary/primary class trên content giữ nguyên, variant render do CSS quyết)
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
source_areas:
  - theme-tailwind-v4
  - hyva-theme-frontend
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 50212e87 (working tree)
last_verified:
supersedes: []
---

# [SLP][TASK-KMVKEX] Homepage: section CTA secondary theo style guide Button DS

<!-- User (02-10): "Secondary button trên homepage đang không theo style guide". -->

## Summary

Override section-CTA hiện tại (BUG-QJWCNG/SLP-268) ép **mọi** `button-item` CTA (cả primary lẫn secondary) thành "Button Variation size XL" nền đặc `--lp-cta-*` (default brand-500 + chữ trắng) — "variant follows the section". Hệ quả: 2 nút có class `pagebuilder-button-secondary` trên homepage render sai style guide DS:

| Nút | Section | Hiện tại | Style guide (Figma Button 2410:26107 XL+Secondary+Trailing) |
|---|---|---|---|
| "Khám phá ngay" → /flash-sale | Siêu sale | brand-500 đặc + trắng | **bg brand-100 `#e7f1e8` + text brand-700 `#35573a`**, Label-L 16/24 Medium, radius 6, icon 16 trailing, XL (h48) |
| "Sofa có sẵn – Giao ngay" → /sofas | Đón thu cùng sofa mới | trắng + `#293e2d` (row set `--lp-cta-bg:#fff`) | như trên |

Hover (Figma 2410:26307): **bg brand-300 `#a9ccae` + shadow-lg** (`0 10px 15px -3px rgb(0 0 0/.1), 0 4px 6px -2px rgb(0 0 0/.06)` = `--btn-design-shadow-lg` có sẵn trong button.css), text giữ brand-700. Focus: giữ ring chung (`--ds-effects-focus-ring-primary` = brand-200).

## Mini Spec

### Goal
- Section CTA có class `pagebuilder-button-secondary` trên homepage render đúng style guide DS Button (Figma 2410:26107/26307): bg brand-100 + text brand-700, hover brand-300 + shadow-lg — không đổi gì CTA primary.

### Expected Behavior
- `a.pagebuilder-button-secondary` trong `button-item` (ngoài slide hero + `.lp-promo-card` — giữ nguyên exclusion của BUG-QJWCNG) → DS Secondary: brand-100/brand-700, hover/active brand-300 + shadow-lg.
- CTA primary (hero, rails, các section còn lại) **không đổi** — vẫn `--lp-cta-*` (variant follows the section, SLP-269).
- Row-level `--lp-cta-*` vẫn override được secondary khi section muốn khác (inherit thua giá trị đặt trực tiếp trên element — nhưng rule mới đặt trực tiếp nên thắng cả row vars: đúng ý "secondary theo guide" cho toàn homepage).
- Kích thước/arrow/padding-end: giữ nguyên từ rule BUG-QJWCNG (XL + arrow trailing).

### Out of Scope
- `@utility btn-secondary` trong components/button.css đang dùng brand-200/brand-800 — **lệch Figma component hiện hành (brand-100/brand-700)**: flag TL quyết (thay đổi site-wide ngoài phạm vi homepage).
- 7 button mũi tên round `/accessories` (section "Giá tốt") + 2 nút mang class rác `hcms-page-2-*` — content hygiene, flag riêng.
- Nút promo card (`.lp-promo-card`) — có design riêng, đang đúng.

## Approach

1. `homepage.css`, ngay sau rule hover generic của BUG-QJWCNG (~line 1056): thêm 2 rule class-driven (default + `:is(:hover,:active)`) cho `a.pagebuilder-button-secondary` với cùng `:not()` exclusion — specificity ngang rule generic, đặt sau để thắng.
2. Build Tailwind + F9 cp static 2 locale + cache:flush.
3. Verify: audit lại 13 CTA (secondary bg/color đúng, primary không đổi); screenshot flash + sofa sections; 0 pageerror.

### Constraints / Rules
- Tailwind v4 CSS-first — không tạo `tailwind.config.js`; rule mới đặt sau rule generic BUG-QJWCNG (specificity ngang, thắng theo thứ tự cascade).
- Giữ nguyên exclusion `:not([data-content-type="slide"] *, .lp-promo-card *)` — hero + promo card có design riêng (SLP-268).
- Variant primary vẫn theo section (`--lp-cta-*`, SLP-269) — chỉ secondary đổi theo guide; 0 DB edit, 0 class change trong content.
- Rebuild + F9 cp static 2 locale + cache:flush sau mỗi lần sửa `theme/*.css`.

### Acceptance Criteria
- **AC-001**: Flash CTA (vi+en) bg `#e7f1e8`, text `#35573a`, h48, radius 6, arrow trailing màu chữ.
- **AC-002**: Sofa CTA bg `#e7f1e8` (không còn trắng ad-hoc).
- **AC-003**: Hover cả 2 → bg `#a9ccae` + shadow-lg, text giữ `#35573a`.
- **AC-004**: Primary CTAs (hero "Xem Chi Tiết", "Xem Ngay", "Bộ Sưu Tập", "Xem tất cả", rails) nguyên state cũ — bg đặc không đổi.
- **AC-005**: 0 pageerror, không overflow mới.

## Update

- **2026-10-02 — DEV DONE, chờ TL review (Mode C)**: implement 2 rule class-driven trong `homepage.css` (đặt sau rule generic BUG-QJWCNG — specificity ngang, thắng theo thứ tự; cũng thắng row `--lp-cta-*` ad-hoc). Verify Playwright **4/4 AC PASS 0 pageerror**: cả 2 secondary ("Khám phá ngay" flash + "Sofa có sẵn – Giao ngay") bg `rgb(231,241,232)`/text `rgb(53,87,58)`/h48/radius 6; hover → `rgb(169,204,174)` + shadow-lg, text giữ nguyên; primary không đổi (Xem Ngay brand-500, Bộ Sưu Tập #45744c row var, Xem tất cả brand-500); overflow −7 (sạch). Build + F9 cp 2 locale + cache:flush. Evidence `.ai/evidence/TASK-KMVKEX/` (2 png + audit + verify scripts). Flag TL: (1) `@utility btn-secondary` (button.css) dùng brand-200/800 — lệch Figma component hiện hành brand-100/700, site-wide cần TL quyết; (2) 7 button round `/accessories` + class rác `hcms-page-2-*` — content hygiene. Code chưa commit.
