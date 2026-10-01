---
id: BUG-6AYPGS
type: bug
title: '[Block][Footer] footer_links không save được khi edit — PageBuilder validate-css-class reject "#" và "&" (entity round-trip) trong Tailwind arbitrary classes (SLP-290)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-290
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review  # 2026-10-01: implement + verify PASS (evidence .ai/evidence/BUG-6AYPGS/); pending: TL review + QC manual admin-save check
created: 2026-10-01
updated: 2026-10-01
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: approach Option A chốt bởi TL 2026-10-01 (semantic class + style trong theme footer.css thay vì nới validation global)
components:
  - app/code/Launchpad/CmsContent/Model/FooterBlockContent.php
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/footer.css
  - app/code/Launchpad/CmsContent/etc/homepage-content.html
  - app/code/Launchpad/CmsContent/Setup/Patch/Data/CreateHomepageNewsletterBlock.php
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/design/frontend/Secomm/launchpad/web/css/styles.css (build output)
  - app/design/frontend/Secomm/launchpad/web/tailwind/safelist/launchpad-cms.html (regen output)
  - app/code/Launchpad/CmsContent/CHANGELOG.md
source_areas:
  - cms-content
  - hyva-theme-frontend
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true  # follow-up: cms_page home + homepage-newsletter vẫn còn hex utilities
verified_against_commit:
last_verified: 2026-10-01
supersedes: []
---

# [SLP][BUG-6AYPGS] [Block][Footer] footer_links không save được khi edit — PageBuilder validate-css-class reject "#" trong Tailwind hex utilities (SLP-290)

<!-- External ticket: SLP-290 "[Block][Footer] Edit link đang không save được — Block id: footer_links khi edit text sẽ không save được với message 'Please enter a valid CSS class.'" (screenshot admin PB form kèm ticket) -->

## Summary + Root Cause

Block CMS `footer_links` (PageBuilder content, seed bởi `Launchpad_CmsContent`, TASK-7EYJ4C/SLP-275) không save được khi admin edit bất kỳ element nào mang hex-color utility: message "Please enter a valid CSS class."

**Root cause (verified bằng source, không đoán):**

1. Field **CSS Classes** của mọi PageBuilder form áp rule client-side `validate-css-class` (`vendor/magento/module-page-builder/view/adminhtml/ui_component/pagebuilder_base_form.xml:188`).
2. Rule được define tại `vendor/magento/module-page-builder/view/adminhtml/web/js/form/element/validator-rules-mixin.js:70` với regex `/^[a-zA-Z\d\-_\/:.\[\]&@()! ]+$/i` — allowed set chứa `[ ] : . & _ ( ) ! - /` nhưng **không có `#`**.
3. Content seed `FooterBlockContent::links()` chứa **4 hex utility trên 3 element**:
   - column `footer-links-group`: `border-b border-[#e4e7ec] py-5 lg:border-b-0 lg:py-0 lg:flex-1`
   - heading `footer-links-title`: `text-[#364153]`
   - list `footer-links-list`: `[&_a]:text-[#4a5565]` + `hover:[&_a]:text-[#101828]` (+ `[&_a]:transition-colors` — pass regex nhưng thuộc cùng cụm styling)

Các class `[&_ul]:m-0`, `[&_li]:py-1.5`, `[&_a]:text-[16px]` v.v. đều PASS regex (đã verify) — chỉ nhóm hex là bị chặn.

## Behavior chốt (TL, 2026-10-01 — Option A)

Hex colors chuyển hết vào **theme footer.css trên semantic class**; content chỉ giữ validation-safe utilities. Không nới validation global (Option B bị loại: làm yếu input validation cho mọi form PageBuilder + vendor-upgrade risk).

## Embedded Mini-Spec

**`FooterBlockContent::links()` (content):**
- Column: bỏ nguyên chuỗi `border-b border-[#e4e7ec] py-5 lg:border-b-0 lg:py-0 lg:flex-1` — footer.css **đã override sẵn** các utility này ở cả 2 breakpoint (desktop: `[column] > {border-bottom:0; padding:0; flex:1 1 0 !important}`; mobile F15: `padding-block:0; border-bottom:0` + divider `#e4e7ec` tự vẽ) → xóa không đổi render.
- Heading: bỏ `text-[#364153]`, giữ phần còn lại → màu chuyển sang `.footer-links-title { color: #364153 }` trong footer.css.
- List: bỏ `[&_a]:text-[#4a5565] [&_a]:transition-colors hover:[&_a]:text-[#101828]` → chuyển sang `.footer-links-list a { color:#4a5565; transition: color .15s cubic-bezier(.4,0,.2,1) }` + `a:hover { color:#101828 }` (đúng tham số transition của Tailwind `transition-colors`).

**footer.css:** thêm section "Typography & link colors (SLP-290)" (unlayered, cùng house style hiện có) + update header comment.

**DB:** reseed `footer_links` rows từ canonical content qua script backup-first `.ai/evidence/BUG-6AYPGS/reseed-footer-links-slp290.php` (theo đúng house pattern của SeedFooterBlocks: seed patch cố tình skip-if-exists, content update đi qua reseed script). Store map 0→4 EN / 1→1 VI; store thiếu row thì skip.

**Build:** regen safelist từ DB (sau reseed) → `npm run build` tailwind theme.

## Files Changed

| File | Thay đổi |
|---|---|
| `app/code/Launchpad/CmsContent/Model/FooterBlockContent.php` | bỏ 4 hex utility khỏi `links()` (cả 2 store view cùng builder); update docblock styling contract |
| `app/design/frontend/Secomm/launchpad/web/tailwind/theme/footer.css` | + màu title, link color/hover/transition; + header comment bullet |
| `app/code/Launchpad/CmsContent/etc/homepage-content.html` (Part 2) | token replace 4 hex → 4 class `lp-*` (raw-HTML button hexes giữ nguyên) |
| `app/code/Launchpad/CmsContent/Setup/Patch/Data/CreateHomepageNewsletterBlock.php` (Part 2) | ` bg-[#45744c]` → ` lp-bg-olive` (seed ↔ DB parity) |
| `app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css` (Part 2) | + 4 rule semantic `.lp-text-olive`/`.lp-bg-olive-dark`/`.lp-bg-olive`/`.lp-bg-mint` |
| `.ai/evidence/BUG-6AYPGS/` | reseed script, backup before/after content, proof regex, RESULTS.md |
| `app/code/Launchpad/CmsContent/CHANGELOG.md` | entry SLP-290 |

## Kết quả verify (2026-10-01)

| Check | Kết quả |
|---|---|
| Regex test — 4 chuỗi class mới vs đúng core rule | **PASS ×4**; chuỗi cũ FAIL (regression proof) — `.ai/evidence/BUG-6AYPGS/validate-css-class-check.txt` |
| DB `footer_links` sau reseed | `hex_present=0` (REGEXP `#[0-9a-fA-F]{3,6}`), 4465→3865 bytes, store 1; store 4 không có row local (skip đúng contract) |
| styles.css sau build | có đủ 3 rule mới; selector hex utility (`.text-\[\#4a5565\]` v.v.) **gone** khỏi bundle |
| Storefront DOM (curl 127.0.0.1 + Host header) | 4× `footer-links-group` (1 open), 4× heading + 4× list với class set mới; không còn hex utility trong footer-links region (hex còn lại = SVG fill icon social, không liên quan) |
| Visual parity | cùng hex + cùng property + footer.css unlayered (cùng pattern với rule F15 hiện có) → 0 visual change kỳ vọng |
| **Admin edit + save (manual)** | **PENDING QC** — env không có admin credential; validation là client-side regex, DB content đã = chuỗi PASS → QC mở admin edit 1 element bất kỳ của footer_links / homepage, save phải thành công |
| Part 2: rescan toàn bộ cms_page + cms_block (mọi tag có `data-content-type` vs core rule) | **ALL VALIDATED FIELDS CLEAN** |
| Part 2: styles.css sau build | có đủ 4 rule `.lp-text-olive` / `.lp-bg-olive-dark` / `.lp-bg-olive` / `.lp-bg-mint` đúng hex |
| Part 2: storefront home DOM (curl sau cache:flush) | HTTP 200; lp-* classes render; **0** hex class trên element có `data-content-type` |

## Follow-up (ngoài scope SLP-290 — đề xuất ticket riêng)

~~Cùng lỗi tiềm ẩn ở content khác~~ → **Part 2 (2026-10-01, cùng ticket — user báo "vẫn còn bị lỗi", đúng nhóm này):**

Chẩn đoán lại toàn bộ `cms_page` + `cms_block` qua đúng core rule cho thấy `footer_links` đã CLEAN từ Part 1; lỗi còn lại nằm ở **`cms_page home` (+ testpage1/2 + home store 2)** — element PB mang hex trong field css_classes:
- heading: `text-[#293e2d]` (3-4 heading)
- column `.lp-promo-card`: `bg-[#304b34]`
- column `.lp-usp`: `bg-[#e7f1e8]`
- figure icon USP: `bg-[#45744c]`

**Đính chính nhận định Part 1:** `homepage-newsletter` là raw HTML form (button/anchor **không có** `data-content-type`) → class trong markup KHÔNG đi qua field CSS Classes nên không gây lỗi save. Tuy vậy script migrate vẫn replace ` bg-[#45744c]` → ` lp-bg-olive` (value-identical, hover `hover:bg-[#35573a]` giữ nguyên) cho nhất quán; seed `CreateHomepageNewsletterBlock.php` cũng cập nhật cùng token để fresh-env parity. Các hex còn lại trong raw HTML (`bg-[#588f60]`, `text-[#304b34]`, `hover:bg-[#...]`, `border-[#...]`, `placeholder:`/`focus:` variants ở newsletter input) **không bị validate** → cố chủ ý.

**Fix Part 2 (cùng Option A):** token-aware replace (leading-space form — không đụng `hover:bg-...`) trong `homepage-content.html` + DB (script backup-first `migrate-home-hex-classes-slp290.php`, 6 rows: home store 1+2, testpage1/2, homepage-newsletter #21/#22) → 4 semantic class mới trong homepage.css (unlayered, cuối file): `.lp-text-olive` #293e2d, `.lp-bg-olive-dark` #304b34, `.lp-bg-olive` #45744c, `.lp-bg-mint` #e7f1e8.

**Part 3 (2026-10-01) — root cause thật của lỗi dai dẳng:** user screenshot field đã sạch hex mà vẫn fail → cơ chế verified: regex rule chấp nhận `&` nhưng không chấp nhận `;`; class chứa `&` (Tailwind `[&_x]` variants) round-trip qua HTML — admin form input nhận dạng escaped `[&amp;_ul]` (browser HIỂN THỊ `[&_ul]` nhưng giá trị validate chứa `;`) → FAIL. Class chứa `&` không thể save qua field này bất kể hex. Fix: field footer list chỉ giữ `footer-links-list mt-4`; toàn bộ `[&_ul]/[&_li]/[&_a]` styling chuyển sang footer.css (`.footer-links-list ul/li/a`); reseed lại (3865→3497 bytes); safelist 171→165. STRICT rescan toàn bộ cms_page+cms_block (regex AND không `&`): **ALL VALIDATED FIELDS CLEAN**.

**Lưu ý tooling:** reseed script cũ `TASK-7EYJ4C/reseed-footer-pb.php` dùng store map `[0,1]` — stale so với patch hiện tại (4 EN / 1 VI), chạy sẽ fatal "missing block row for footer_newsletter store 0" trên env local hiện tại. Đã bypass bằng script riêng của BUG này; TL cân nhắc update script cũ hoặc bỏ.

## Deploy note (staging có store 4 + store 1)

Sau khi pull code:
1. `sudo -u secomm php .ai/evidence/BUG-6AYPGS/reseed-footer-links-slp290.php` (backup-first)
2. `sudo -u secomm php .ai/evidence/BUG-6AYPGS/migrate-home-hex-classes-slp290.php` (backup-first)
3. `.ai/evidence/TASK-7EYJ4C/regen-safelist.php`
4. `npm run build` trong `app/design/frontend/Secomm/launchpad/web/tailwind/`
5. `bin/magento cache:flush`
