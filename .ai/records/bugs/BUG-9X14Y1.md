---
id: BUG-9X14Y1
type: bug
title: '[CmsContent] Flash Sale widget — sale_end quá khứ ẩn toàn bộ PB row, mất content page (SLP-159)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-159
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review  # 2026-10-01: implement + verify ALL AC PASS (evidence .ai/evidence/BUG-9X14Y1/); chờ TL review + QC eyeball visual
created: 2026-10-01
updated: 2026-10-01
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material — behavior khi hết hạn do user/TL chốt trực tiếp (method a: ẩn cả section, heading chuyển vào widget title), không phải kiến trúc
components:
  - app/code/Launchpad/CmsContent/Block/Product/FlashSaleList.php
  - app/code/Launchpad/CmsContent/view/frontend/templates/product/widget/flash-sale.phtml
  - app/code/Launchpad/CmsContent/etc/homepage-content.html
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/code/Launchpad/CmsContent/CHANGELOG.md
source_areas:
  - cms-content
  - hyva-theme-frontend
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-01
supersedes: []
---

# [SLP][BUG-9X14Y1] [CmsContent] Flash Sale widget — sale_end quá khứ ẩn toàn bộ PB row, mất content page (SLP-159)

<!-- External ticket: SLP-159 "[LA-03] Flash Sale / Countdown widget" — QC feedback: khi sale_end < thời điểm hiện tại => mất toàn bộ content page. Widget tạo từ commit ba3e4da7 (SLP-159). -->

## Summary + Root Cause

Widget Flash Sale (`Launchpad_CmsContent`, block `FlashSaleList` + template `flash-sale.phtml`) **không có guard server-side cho sale hết hạn**:

1. `FlashSaleList::getSaleEndMillis()` chỉ parse `sale_end` trả epoch-ms — quá khứ vẫn trả về bình thường; template luôn render đầy đủ section.
2. Client-side (~1s sau load), Alpine `lpFlashCountdown` thấy `end <= Date.now()` → `hide()` → `this.$el.closest('[data-content-type="row"]').style.display = 'none'` — **ẩn toàn bộ Page Builder row** chứa widget, không kiểm tra row còn chứa gì khác.

Hiện trạng content (DB local, 4 page: #2/#13 Home Page, #8/#10 testpage): heading `Siêu sale`/`Flash sale` là element `data-content-type="heading"` **riêng**, nằm CÙNG row với widget → hết hạn mất cả heading + slider; mọi content khác admin thêm vào row đó cũng biến mất. Trên page build 1 row duy nhất → mất toàn bộ content page.

Luôn có flash-of-content: server render đầy đủ rồi client mới ẩn; crawler/JS-off thấy sale đã hết hạn.

## Behavior chốt (user, 2026-10-01 — method a)

Sale hết hạn → **ẩn cả section** (heading + countdown + slider). Heading chuyển vào widget qua option `title` sẵn có (CSS `.lp-flash-title` đã cùng type scale với PB heading từ v4.9.9), sau đó server-side không render gì khi hết hạn.

## Embedded Mini-Spec

**`FlashSaleList` (block):**
- `isSaleEnded(): bool` — `getSaleEndMillis() <= time()*1000`. `sale_end` rỗng/sai format → fallback "nửa đêm kế tiếp" (giữ nguyên logic hiện có) → không bao giờ kết thúc server-side (evergreen daily countdown).
- Override `getCacheLifetime()` (protected, parent `AbstractBlock`) — cap lifetime = `min(parent, giây còn lại đến sale_end, floor 1s)`; parent không có lifetime → không đổi. Lý do: `ProductsList::_construct()` mặc định `cache_lifetime=86400`; không cap thì render "sale active" được serve từ block cache đến 24h SAU khi hết hạn.

**`flash-sale.phtml` (template):**
- Render gate: `if ($items && !$block->isSaleEnded())` → hết hạn render TRỐNG (không có HTML flash-sale trong page source; heading đã nằm trong widget nên cả section biến mất sạch).
- JS guard giữ lại (phòng FPC serve HTML cũ sau khi hết hạn) nhưng thu hẹp scope: `closest('[data-content-type="row"]')` → `closest('.lp-flash')` — chỉ ẩn root widget (đã chứa title), KHÔNG bao giờ ẩn content PB ngoài widget; check hết hạn chạy ngay trong `init()` (không chờ 1s).

**Theme CSS (`homepage.css`):**
- `.lp-flash-head` mobile gap `gap-3` → `gap-5` + bỏ `!mt-5` trên wrapper html của flash section trong content — giữ pixel-parity với layout cũ (trước: heading → 20px → pill → 20px → slider).
- Giữ nguyên rule desktop `row:has(.lp-flash) h2[data-content-type='heading']` — backward-compat cho content chưa migrate.

**Content migration (fixture + DB):** các page dùng widget bỏ heading PB riêng, set `title` trên widget directive, bỏ `!mt-5` wrapper. Fixture: `etc/homepage-content.html` (source cho `SeedHomepageContent` — chỉ seed lần đầu; môi trường có sẵn update DB/manual theo release notes).

## Acceptance Criteria

- **AC1** — `sale_end` tương lai: section render đủ (title + countdown + slider), không đổi so với hiện tại khi sale còn hạn.
- **AC2** — `sale_end` < hiện tại: page source KHÔNG chứa markup `lp-flash` nào; mọi content khác của page (các row/section khác) nguyên vẹn; KHÔNG có JS ẩn row.
- **AC3** — cache: `getCacheLifetime()` = min(86400, giây còn lại); sale 1h tới → ~3600; đã hết hạn → 1; rỗng → đến midnight; block cache hết hạn đúng lúc hết sale.
- **AC4** — `sale_end` rỗng: evergreen countdown, không ẩn server-side.
- **AC5** — visual parity mobile + desktop khi sale còn hạn (title trong widget thay heading PB, spacing giữ 20px rhythm).
- **AC6** — `sale_end` sai format: fallback midnight, không lỗi.

## Approach

1. Code block + template + CSS theo mini-spec.
2. Tailwind build (`web/tailwind/`).
3. Migrate content: fixture + 4 page DB local (script có assert).
4. Verify: cache:clean → curl (future/expired/rỗng), CLI probe `getCacheLifetime`/`isSaleEnded`, evidence → `.ai/evidence/BUG-9X14Y1/`.
5. CHANGELOG + record status; pre-review → TL review.

## Scope check / Rollback

Không chạm §12 (CMS content rendering, module Secomm-owned). Staging/prod cần deploy code + update CMS content (release notes). Rollback: revert commit + revert content (heading PB trả lại như cũ).
