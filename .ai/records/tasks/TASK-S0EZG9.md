---
id: TASK-S0EZG9
type: task
title: 'Home: thêm action Back to Top'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-293
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-01
updated: 2026-10-01
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/back-to-top.phtml
  - app/design/frontend/Secomm/launchpad/Magento_Cms/layout/cms_index_index.xml
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv
  - app/design/frontend/Secomm/launchpad/i18n/en_US.csv
source_areas:
  - theme-tailwind-v4
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-01
supersedes: []
---

# [SLP][TASK-S0EZG9] Home: thêm action Back to Top

<!-- External ticket: SLP-293 — "[UI][Home][Back to Top] Thêm action Back to Top". -->
<!-- Mode C — embedded Mini-Spec. Theme-only (template + layout + i18n); không đụng vendor, không DB, không config. -->
<!-- Phạm vi: CHỈ trang homepage theo chỉ thị user ("Làm trước ở trang homepage") — mở rộng global là follow-up nếu PM muốn. -->

## Summary

Trang homepage (thường dài — hero + tiles + flash sale + nhiều rails sản phẩm) chưa có cách quay về đầu trang ngoài scroll tay. Thêm nút **Back to Top** dạng floating button:

- **Vị trí**: fixed góc **bottom-right** (user chốt).
- **Icon**: mũi tên hướng lên, **không text** (user chốt) — Lucide `arrow-up` qua `LucideIcons` VM (pattern `item.phtml` card).
- **Style**: **primary button** (user chốt) — `bg-hp-brand-dark` (#45744c olive, token `@theme` của homepage.css) + white icon, khớp pattern primary của theme (`.hp-btn-atc` trong card.css).
- **Behavior**: ẩn khi ở đầu trang; **hiện khi scrollY vượt quá 1 màn hình** (`window.scrollY > window.innerHeight` — user chốt); click → **smooth scroll** về top (user chốt; respect `prefers-reduced-motion` → `behavior:'auto'` — WCAG 2.3.3).

## Mini Spec

### Goal
- Nút floating Back to Top render **chỉ trên homepage** (`cms_index_index` handle), fixed bottom-right, không đè lên sticky header (z-50, top) và nằm dưới cart drawer/quickview (native `<dialog>` top layer — mọi z-index đều dưới).

### Expected Behavior
- Load homepage ở top → nút **ẩn** (không flash trước Alpine init — `x-cloak`).
- Scroll xuống quá 1 viewport height → nút **hiện** (transition fade+slide nhẹ).
- Scroll quay về < 1 viewport → nút **ẩn** lại.
- Click nút → trang cuộn **smooth** về `scrollY=0`; với `prefers-reduced-motion: reduce` → nhảy tức thời (`behavior:'auto'`).
- Mobile (375px): cùng behavior, kích thước icon-button 48px (touch target ≥ 44px a11y).
- Keyboard: `<button>` native focusable, `focus-visible` outline khớp theme, Enter/Space kích hoạt sẵn.

### Out of Scope
- Các trang khác (PLP/PDP/cart/checkout…) — mở rộng global là follow-up riêng nếu PM duyệt.
- Thiết kế hover state đặc biệt (dùng `hover:brightness-90` chung như primary button của theme).
- Config admin bật/tắt (không được yêu cầu).

## Approach

1. Template mới `Magento_Theme/templates/html/back-to-top.phtml`: Alpine component `initBackToTop` (pattern `alpine:init` + `Alpine.data` như `initFooterLinksAccordion` trong footer.phtml, TASK-7EYJ4C) — scroll listener `{passive:true}` + `x-show` + `x-cloak`; icon `$lucideIcons->arrowUpHtml('', 20, 20, ['aria-hidden' => 'true'])`; CSP `$hyvaCsp->registerInlineScript()`.
2. Layout: block additive trong `Magento_Cms/layout/cms_index_index.xml` (container `content`, `after="-"` — cùng precedent quickview.modal; file Home-only đã tồn tại từ TASK-K14RVZ/TASK-0NNZCW).
3. i18n: +1 key/file `"Back to top"` → vi `"Lên đầu trang"` (aria-label), en identity — cả `vi_VN.csv` + `en_US.csv` (BR-001).
4. Build Tailwind (`npm run build` as secomm) + cp `styles.css` sang `pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/` (F9 trap — bắt buộc sau MỖI build) + `cache:flush` as secomm.
5. Verify Playwright (probe trong `.ai/evidence/TASK-S0EZG9/`).

### Acceptance Criteria
- **AC-001**: Nút render chỉ trên homepage; không render trên PLP/PDP (handle-scoped).
- **AC-002**: Ẩn ở đầu trang (scrollY=0), hiện khi scrollY > window.innerHeight, ẩn lại khi cuộn về.
- **AC-003**: Click → scrollY về 0 với behavior smooth (không nhảy tức thời ở motion bình thường).
- **AC-004**: Icon arrow-up 20px trắng trên nền #45744c, không text; aria-label "Lên đầu trang" (vi) / "Back to top" (en).
- **AC-005**: Desktop 1440 + mobile 375 đều đúng vị trí bottom-right, không gây horizontal overflow; 0 pageerror.
- **AC-006**: Nút nằm dưới cart drawer/quickview khi các overlay đó mở (native dialog top layer).

### Test Cases (QC)
- TC-1: Homepage — load → scroll 1.5 màn → thấy nút → click → về top + nút ẩn.
- TC-2: Scroll vừa đúng ~0.5 màn → nút vẫn ẩn (threshold = 1 màn hình).
- TC-3: PLP (`/gear/bags` hoặc category bất kỳ) → không có nút.
- TC-4: Mobile 375 — mở nút, click, không overflow-x; footer accordion không bị che khuất chức năng (nút floating đè lên góc — chấp nhận pattern chuẩn).
- TC-5: Mở quickview/cart drawer khi nút đang hiện → nút không đè lên overlay.
- TC-6: Keyboard — Tab tới nút (khi visible), Enter → về top.
- AC-004/aria EN: verify framework-level dict (store-switch HTTP chết local — LL-0011; en live để QC demo).

## Implementation Notes

- **Template** `back-to-top.phtml`: Alpine `initBackToTop` (pattern `alpine:init` + `Alpine.data`, clone `initFooterLinksAccordion`/footer.phtml) — scroll listener `{passive:true}`; `x-cloak` chống flash pre-init; `x-show` + enter/leave transition classes (đều literal trong phtml → compile qua `@source`); icon `$lucideIcons->arrowUpHtml('', 20, 20, ['aria-hidden' => 'true'])` (Lucide `arrow-up`); CSP `$hyvaCsp->registerInlineScript()`.
- **Vị trí/z-index**: `fixed bottom-6 right-6 z-40` — header sticky là z-50 (top page, không spatial overlap); cart drawer + quickview = native `<dialog>` (top layer) luôn đè mọi z-index → nút không bao giờ đè overlay (verify structural AC-006: 4 dialog/page).
- **Threshold** `window.scrollY > window.innerHeight` (strict — đúng 1 màn hình vẫn ẩn, verify AC-002d).
- **Reduced motion**: `prefers-reduced-motion: reduce` → `behavior:'auto'` (nhảy tức thời) — WCAG 2.3.3; smooth mặc định per user.
- **i18n**: aria-label `__('Back to top')` — vi "Lên đầu trang" (CLI emulation TRANSLATED ✓), en identity CSV:951 (live EN = QC demo — LL-0011 + locale drift store 2).
- **0 vendor edit, 0 DB, 0 config** — theme-only, additive layout.

## Update

- **2026-10-01 — DEV DONE, chờ TL review (Mode C)**: implement theo Approach; verify **Playwright 13/13 PASS 0 pageerror** (desktop 1440 + mobile 375: hidden/visible threshold 1 màn hình, smooth 36 events, icon-only #45744c, keyboard Enter, native-dialog overlays, no overflow) + **curl AC-001 home-only** (PLP 0 match) + **dict VI TRANSLATED** (CLI emulation; EN = QC demo). F9 cp cả 2 locale (en_US statics bị wipe — tạo lại). Evidence `.ai/evidence/TASK-S0EZG9/` (RESULTS.md + verify.js + verify-dict.php + 2 png). Wording VI "Lên đầu trang" chờ TL duyệt. ⚠ **styles.css artifact MIXED** (ambient rules từ sources uncommitted session khác) — commit tách hunk hoặc theo quyết định TL. **Đã commit `96f98b9f`** (01-10, `dev/development/anhchong` — 12 files +381/−2: template + layout + 2 CSV + styles.css artifact + record/evidence/estimation; các file session khác left unstaged); chưa push — chờ TL review.
