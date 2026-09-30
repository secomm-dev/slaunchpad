---
id: TASK-7EYJ4C
type: task
title: 'Footer Layout Mock-up — footer theo design Figma, newsletter chuyển xuống footer, component = CMS block (SLP-275)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-275
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-28
updated: 2026-09-29
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only theme layer + CMS content — không chạm §12 (payment/checkout/order/DB schema/security); DB writes chỉ là CMS content/config qua data patch + script có backup (không schema migration)
components:
  - app/design/frontend/Secomm/launchpad
  - app/code/Launchpad/CmsContent
source_areas:
  - hyva-theme-frontend
  - cms-content
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: working tree (chưa commit — dev branch anhchong)
last_verified: 2026-09-29
supersedes: []
---

# [SLP][TASK-7EYJ4C] Footer Layout Mock-up — footer theo design Figma, newsletter chuyển xuống footer, component = CMS block (SLP-275)

<!-- External ticket: SLP-275 "Footer Layout Mock-up". Dùng design home page trong Figma
     (desktop node 2717-85933, mobile node 2717-86177); newsletter của homepage chuyển xuống
     footer; các component của footer là CMS block. Mode B — Embedded Mini-Spec + Approach. -->

## Ticket + AC

**Ticket**: SLP-275 — Footer Layout Mock-up.
- Design desktop: Figma `5MpBw9VaNgFdct3UDWABuV` node `2717-85933` (1440×780).
- Design mobile: Figma node `2717-86177` (375×1070).

**AC**:
1. Footer storefront match design cả desktop 1440 lẫn mobile 375: newsletter band → links 4 cột (mobile: accordion, group đầu mở) → social → trust/payment band → copyright bar.
2. Newsletter bị remove khỏi body CMS page `home` (cả store vi lẫn en) và render trong footer trên mọi trang.
3. Các component footer là CMS block (`footer_newsletter`, `footer_links`, `footer_social`, `footer_trust_payments`) — client edit được qua Admin Content > Blocks; content phân store vi/en.
4. Copyright lấy từ config `design/footer/copyright` (Content > Design > Configuration > Theme > Footer > Copyright).
5. Language switcher bị remove khỏi footer.
6. Styling CMS content = Tailwind classes trên markup CMS (safelist regen + JIT), hạn chế CSS thuần.

## Embedded Mini-Spec

**Hiện trạng (verify 09-28)**:
- Footer hiện 100% vendor Hyvä: container `footer` → `footer-content` (`Magento_Theme::html/footer.phtml`) với children `footer-static-links` (Company/Legal hardcode), `footer-cms-content` (block `footer_content` — không tồn tại trong DB → rỗng), `footer-copyright` (hardcode "© 2020 Hyva Themes B.V.", `getCopyright()` bị comment) + language switcher (qua `footer`? — thấy trong rendered HTML). Child theme chưa override bất kỳ file footer nào.
- Newsletter homepage: cuối CMS page `home` (page 2 store 1 vi, page 13 store 2 en) có row `.lp-newsletter` (heading + `{{block class="Launchpad\CmsContent\Block\Newsletter\Subscribe" template="Launchpad_CmsContent::newsletter/subscribe.phtml"}}`). Form + validation (hyva.formValidation) tái sử dụng nguyên vẹn.
- CMS block `homepage-newsletter` bị duplicate: block_id 21 & 22 đều store 0 (patch idempotency hỏng do load-by-identifier nhặt nhầm) — artifact, không còn được template nào dùng (form đi qua `{{block}}`).
- Tailwind safelist cơ chế có sẵn: `web/tailwind/source/tailwind-source.css` scan `@source "./safelist/**/*.html"`; `launchpad-cms.html` GENERATED từ DB (pattern `reseed-homepage.php`).
- FPC **BẬT** local (F3 TASK-0NNZCW). curl probe: dùng `curl -H "Host: slaunchpad.localhost" http://127.0.0.1/` (hosts entry không resolve từ WSL).

**Thiết kế**:
- Theme `Secomm/launchpad` (child theme shadow vendor path, không đổi vendor):
  - Override `Magento_Theme::html/footer.phtml`: render lần lượt children CMS (mỗi block content tự bọc full-bleed section bg của nó), exception `footer-copyright` render ở bottom bar riêng.
  - Override `Magento_Theme::html/footer/copyright.phtml`: `$block->getCopyright()` (config) + links Terms & Conditions / Privacy Policy.
  - `Magento_Theme/layout/default.xml` (file có sẵn — thêm node additive): remove `footer-static-links` + `footer-cms-content`; thêm 4 block `Magento\Cms\Block\Block` con của `footer-content` với block_id `footer_newsletter` / `footer_links` / `footer_social` / `footer_trust_payments`.
  - Accordion mobile cho links: wrapper Alpine `x-data` trong footer.phtml quanh child `footer-links`; JS bind click trên `.footer-links-title` (không nhét Alpine directive vào CMS content — bền với client edit). CSS thuần chỉ cho accordion chrome (chevron + mobile collapse); phần còn lại Tailwind classes trong CMS content.
- Module `Launchpad_CmsContent`: data patch `SeedFooterBlocks.php` — 4 identifier × 2 row store (store 0 = en fallback, store 1 = vi), idempotent theo (identifier, store); newsletter block bọc `{{block}}` Subscribe hiện có. Bump module version + CHANGELOG.
- CMS page `home`: script remove row `.lp-newsletter` cuối content, backup vào `.ai/evidence/TASK-7EYJ4C/` trước khi UPDATE (không sửa qua admin — PB payload trap).
- Config: seed `design/footer/copyright` = "©2026 SECOMM Launchpad" (scope default, cả 2 store kế thừa; store vi/en override sau nếu client cần).
- Assets: social icons (4), payment icons (8), badge Bộ Công Thương export từ Figma → `pub/media/wysiwyg/footer/`; CMS content tham chiếu `{{media url="wysiwyg/footer/..."}}`.
- Safelist: mở rộng generator scan thêm 4 footer block → regen `launchpad-cms.html` → `npm run build`.

**Links map** (lowercase; ưu tiên route/page có sẵn):
Company: `about-us` (page), `sales/guest/form/`, `search/term/popular/`, `contact` | Legal: `privacy-policy-cookie-restriction-mode` (page), `terms-and-conditions` (placeholder) | Our Company: `about-us`, `blog` (Magefan), `our-stores` (placeholder), `sustainability` (placeholder) | My Account: `customer/account/`, `sales/order/history/`. Social: `#`.

**Verify**:
1. `setup:upgrade` (as secomm) → 8 block row mới; blocks render đúng store.
2. curl 2 store, homepage + 1 trang catalog: đủ 5 section đúng thứ tự, không còn `.lp-newsletter` trong main, footer không còn language switcher, form newsletter present.
3. FPC: hit lần 2 (warm) vẫn render đủ (Hyva CMS JIT 2-pass + `{{block}}`).
4. Mobile 375 Playwright (`/tmp/pw-cal`): accordion mở/đóng, layout sections; desktop 1440: 4 cột tĩnh.
5. Submit newsletter POST `/newsletter/subscriber/new` có form_key → success behavior như homepage hiện tại.
6. 2 store có heading vi/en đúng (`HÃY LIÊN HỆ VỚI CHÚNG TÔI!` / `SUBSCRIBE TO OUR NEWSLETTER`).

## Decisions (TL/user chốt 09-28)
- Language switcher bỏ khỏi footer (chức năng sẽ chuyển lên header — **scope riêng**, phải bù trước khi release; ticket này chỉ remove).
- URL lowercase; social URL `#`; copyright từ design config.
- Styling CMS = Tailwind classes trực tiếp, JIT build qua safelist; hạn chế CSS thuần (chỉ accordion chrome).

## Findings
- (09-28) **F1 — `setup:upgrade` bị chặn project-wide (pre-existing, đã surface)**: patch `Secomm_VietNamAddress ResyncVnAdminPre2025SnapshotMappingPatch` fail checksum dataset vì **git `core.autocrlf=true` smudge LF→CRLF khi checkout** các `Files/*.csv` — contract sha256 trong manifest khớp **git blob (LF)**, không khớp worktree (CRLF). Commit đúng, local env sai. Fix env-level: `tr -d '\r'` toàn bộ `Files/*.csv` (worktree về LF) → checksum khớp → upgrade pass. Status "M" trên các CSV là stat-dirty do autocrlf (git add = no-op content). **Durable fix cần TL duyệt: `.gitattributes` `*.csv text eol=lf`** (repo-level, ngoài scope task).
- (09-28) **F2 — `Tiktok_Tiktok` + `Secomm_TiktokHyva` outdated cản request**: sau `cache:flush`, cờ "db up-to-date" mất → mọi request trả error page vì 2 module mới (từ merge gần đây) chưa có setup rows. setup:upgrade sau fix F1 đã cài → site sống lại. Không đụng 2 module này.
- (09-28) **F3 — `@source` Tailwind v4: depth 5×`../` (line UiWidget hiện có) resolve ra `app/design/app/code/...` = KHÔNG TỒN TẠI → @source chết im lặng**. Path đúng từ `web/tailwind/` tới `app/code` là **6×`../` + `code/...`** (đã verify `realpath`). Line `@source` mới cho `Launchpad_CmsContent` dùng path 6-ups đúng; **line UiWidget cũ để nguyên (pre-existing, ngoài scope) — cần TL quyết sửa** (hiện UiWidget templates không được scan utilities).
- (09-28) **F4 — template module ngoài `@source` theme**: class Tailwind trong `Launchpad_CmsContent` template (subscribe form) không compile nếu không có line `@source` riêng → form vỡ (button full-width, input 34px). Đã thêm @source + rebuild.
- (09-28) **F5 — trust band `w-full` không override ở lg**: payments wrapper `grid w-full ... lg:flex lg:max-w-none` thiếu `lg:w-auto` → chiếm hết width, `lg:justify-between` vô hiệu (badge+icons lệch trái). Fix content patch + DB sync + `lg:w-auto`.
- (09-28) **F6 — newsletter submit hoạt động trên FPC warm**: subscriber #4 tạo thành công qua form footer trên trang FPC hit (form_key cached vẫn được controller chấp nhận — same behavior homepage). Test data đã xóa.
- (09-28) **F7 — store-switch local chết (LL-0011)**: cả `?___store=`, route `stores/store/redirect` lẫn cookie `store=launchpad_en` đều không đổi store. EN content verify qua **store emulation CLI** (`verify-blocks-per-store.php`) — cả 4 block resolve đúng content vi/en theo store view.
- (09-28) **F8 — `Magento\Cms\Block\Block` deprecated hint (2.4.8)**: Hyvä vendor layout cũng dùng chính class này — giữ nguyên để parity vendor; ghi nhận cho upgrade sau.
- (09-28) **F9 — homepage.css bleed vào footer (v2)**: các rule `body.cms-index-index [row][appearance=full-width] [column-line]:has(>[column]) { display:grid !important }` và split-section `[column]:has(>[image]) > [image] img { position:absolute; width/height:100% !important }` (TASK-0NNZCW) match cả footer khi render trên homepage (body.cms-index-index) → footer vỡ grid/icons phóng to. Counter trong `footer.css` scope `footer.page-footer` + specificity (0,5,1)/(0,6,x) ngang cao hơn + import sau homepage.css. TL lưu ý: homepage.css nên tự scope `:not(footer *)` khi được chạm tới (ngoài scope task này).
- (09-28) **F10 — stale `pub/static` CSS re-materialize**: developer mode static.php GHI lại file vào `pub/static/frontend/.../css/styles.css` ở lần serve đầu; các verify sau ăn bản stale phải `rm` lại trước mỗi lần build (trap F9 cũ của TASK-0NNZCW, tái hiện 2 lần trong run).
- (09-28) **F11 — `page-builder.css @apply w-full` trên column**: mọi `[data-content-type=column]` mặc định `width:100%` → 4 column nowrap = chồng nội dung (chỉ 1 icon visible). Footer css đặt `width: auto/1 1 0 !important` theo section. Cộng `loading="lazy"`: img dưới fold có `naturalWidth=0` khi probe chưa scroll — assertion size phải scroll vào view trước.
- (09-28) **F12 — selector ancestor order**: rule `footer.page-footer [row][appearance] .footer-section-*, …` sai thứ tự tổ tiên (wrapper template nằm NGOÀI row) → không match; đúng là `.footer-section-* [row][appearance] …`. Specificity guard tính lại: guard (0,4,1)/(0,5,1) vs homepage (0,5,1) — tie + import sau = thắng.
- (09-29) **F13 — homepage.css v4.9.2 padding rule bleed**: `padding: 0 2.5rem 0 0 !important` trên `[row full-width/bleed]:has(column-group/line) [data-element="inner"]` match 4 row footer trên homepage → lệch. Fix: `:not(footer *)` tại nguồn (comment v4.9.3). Rule này giờ không còn match row nào ở main (homepage evolve — chỉ còn contained/full-bleed, full-bleed không có inner) → độc hại duy nhất cho footer, 0 rủi ro regression khi scope. Audit hệ thống (sửa bug `CSSStyleRule.cssRules` rỗng-truthy khiến bản đầu báo sai 0) còn **12 rule cms-index-index match footer** — all countered by footer.css; scope-all `:not(footer *)` = decision TL.
- (09-29) **F14 — page-builder.css container padding trên `.row-full-width-inner`**: `[row full-width] > .row-full-width-inner { @apply container }` cho footer row inner padding-inline 8→24px (responsive step) chồng lên wrapper `px-6 lg:px-10` → lệch 24px. Trước v4.4 bị rule v4.9.2 (!important) che; scope xong thì lộ. Fix: counter `padding-inline: 0` ở footer.css (F14 comment). Bài học: khi đè 1 rule bleed bằng scope, phải re-check các rule tầng dưới trước đó bị nó che.
- (09-29) **F15 — mobile links accordion lệch design + chevron ảo**: (a) `::after` chevron thiếu height → 0px, invisible; verify cũ assert computed mask = PASS ảo — **assert pseudo-element phải kèm box dimensions**. (b) builders gắn border-b cả 4 group → dư line cuối; divider đúng design = giữa các group thôi. (c) rhythm design mobile (node 2717:86198) = container gap-16 + group không py, khác desktop model "group tự padding" — spec mobile phải đo từ frame mobile, không suy từ desktop.

## Updates
- **09-29 (v4.7 — store map của `SeedFooterBlocks` theo staging: store 1 = VI, store 4 = EN, bỏ
  store 0)**: request user. Patch đổi thành `STORE_ID_VI = 1` / `STORE_ID_EN = 4` (index 0 → EN
  store 4, index 1 → VI store 1), **không còn row store 0 fallback** — store view không có row
  riêng sẽ không thấy footer block (by design). Guard mới `storeExists()` qua
  `StoreManagerInterface`: store view không tồn tại trong env bị **skip** thay vì nổ FK
  (`cms_block_store.store_id` → `store`) — local chỉ có store 0/1/2 (không có 4) nên reseed local
  cho ra **4 row store 1 (VI) duy nhất** (#55–58, legacy `footer_links_block` #2 nguyên vẹn);
  staging (có store 4) sẽ seed đủ VI+EN khi `setup:upgrade` apply patch lần đầu (chưa có trong
  patch_list của staging). Layout tham chiếu block bằng **identifier** → không bị ảnh hưởng.
  Hệ quả local: EN store local (store 2 `launchpad_en`) giờ không có footer rows (đúng map mới);
  store-switch local vốn chết (LL-0011) nên không test HTTP EN được — verify EN chuyển sang
  staging (hoặc emulation CLI với store 4 nếu cần, script `verify-blocks-per-store.php` đang
  hard-code store 1/2 — nhớ đổi khi dùng). README + CHANGELOG 1.3.1 cập nhật. Verify: audit DB
  sạch, cache clean, **30/30 PASS** trên store mặc định (VI).
- **09-29 (v4.6 — F15: mobile links accordion theo đúng Figma — chevron vô hình, dư line cuối,
  rhythm padding)**: report user 3 lỗi ở section links mobile 375. Đối chiếu get_design_context
  Figma node **2717:86198** ("Accordion": container px-24 py-32 + gap-16, divider CHỈ giữa các
  group — không line sau group cuối, chevron 20×20, title→list mở = 24, nav list gap-12):
  1. **Chevron invisible từ v1** — `::after` có width 20px nhưng height 0 (flex item trong
     heading `items-center`) → verify cũ chỉ assert computed mask nên PASS ảo. Fix `height: 20px`.
  2. **Dư line ở item cuối** — builders gắn `border-b` cho cả 4 group. Fix structural:
     border 0 trên mọi group + `:not(:last-child)` mới giữ divider 1px #e4e7ec (giữa nhóm thôi).
  3. **Padding/rhythm sai design** — groups tự mang `py-5` (20px) + list mt-4 (16). Fix:
     gap 16px trên column-line (`!important` — homepage.css bleed `gap: 1rem !important` match
     cùng element), group padding dọc 0, title→list khi mở = 24px. Tất cả trong block mobile
     `@media (max-width: 63.97rem)` của footer.css — strip-proof (đè utility classes, admin
     không thể mất bằng cách sửa content). Rebuild. Verify đo DOM: gap 16 / pad 0 / chevron
     20×20 / border 0 group cuối / list mở 24px; screenshot `slp275-links-mobile-fixed.png`
     + `-toggle.png`; **30/30 PASS** (desktop không đổi — rules mobile-only).
- **09-29 (v4.5 — F14: container padding của `.row-full-width-inner` làm footer lệch 24px, counter
  ở footer.css)**: report user sau fix v4.4 — `padding-inline: calc(var(--spacing) * 6)` vẫn ăn
  vào footer. Nguồn: page-builder.css `[row full-width] > .row-full-width-inner { @apply
  container }` → Tailwind container step (max-width steps + padding-inline 8→24px responsive).
  Trước đây rule v4.9.2 (`padding: 0 2.5rem 0 0 !important`) đè nên không thấy; sau khi scope
  `:not(footer *)` (v4.4) thì padding container lộ ra → content footer lệch phải 24px so với
  cột content main. Fix: counter `footer.page-footer [row full-width] > .row-full-width-inner
  { padding-inline: 0 }` (specificity (0,4,1) > (0,3,0), không cần !important) — wrapper
  footer.phtml (`max-w-[1440px] px-6 lg:px-10`) đã owning container job. Verify đo cạnh trái
  thật (title "Công ty" vs title "Living, Reimagined"): **1813 = 219/219 (diff 0), 1440 =
  40/40 (diff 0)**; mobile 375 = footer 24px theo design (main anchor là card-slider, không
  so sánh trực tiếp). inner padding-inline = 0px ở cả 3 viewport. **30/30 PASS**. Screenshot
  `slp275-footer-aligned-1813.png`. Ghi chú: container step cũng cho inner `max-width` steps +
  `margin-inline auto` — không bao giờ bind trong wrapper ≤1440 nên chỉ cần zero padding.
- **09-29 (v4.4 — F13: homepage.css v4.9.2 padding rule bleed vào footer, scope `:not(footer *)`
  tại nguồn)**: report user — `padding: 0 2.5rem 0 0 !important` (homepage.css rule "Living,
  Reimagined" v4.9.2, subject `[data-element="inner"]`) match cả `row-full-width-inner` của 4 row
  footer (đều full-width + có column-group/line) khi render trên homepage → footer lệch phải.
  Fix tại nguồn theo ghi nhận TL (F9): thêm `:not(footer *)` vào 4 selector + comment v4.9.3
  (F13); rebuild Tailwind (vi_VN = symlink tự tươi). **Sự thật thú vị: rule này giờ không còn
  match row `full-width` nào ở main** (homepage đã evolve — chỉ còn contained/full-bleed,
  full-bleed không có inner; các card "Living, Reimagined" hiện là lp-promo-card) → hiệu ứng
  duy nhất của nó là phá footer; `mainInnersWith40 = 0` trước lẫn sau fix → **0 rủi ro
  regression homepage**. Verify: footer inner paddingRight = 24px (không còn 40px); counter
  v4.1 vẫn giữ margin-block-start = 0px trên 4 row; **30/30 PASS**; screenshot
  `slp275-homepage-footer-after-padding-scope-fix.png`. **Audit hệ thống (script đã sửa — bản
  đầu báo sai 0 do `CSSStyleRule.cssRules` là list rỗng nhưng truthy khiến walk bỏ qua mọi style
  rule)**: **12 rule cms-index-index vẫn match footer DOM**, tất cả đang bị footer.css counter
  giữ (margin 3rem/4rem, grid 1fr/940fr467fr, img cover 535px/aspect-ratio, padding-bottom 0,
  column-group width) — 30/30 assert các vị trí đứt gãy. Đề xuất TL (F9 durable): scope
  `:not(footer *)` cho cả 12 khi được chạm homepage.css lần tới, footer.css counter thành dư
  phòng thủ. Khối red lớn giữa homepage = video placeholder (`aspect-video` +
  `bg-[var(--play-*)]`, section "KHÔNG GIAN THỰC TẾ") — by design, 0 4xx, không liên quan.
- **09-29 (v4.3 — FIX "một block vẫn là HTML Code" trong admin: appearance column-group + image)**
  **Root cause (mirror của v3.2, phát hiện nhờ admin probe thực tế với tài khoản admin)**: PB
  stage parse content theo config content-type — `column_group.xml` chỉ khai appearance
  `default`, `image.xml` chỉ khai `full-width` (chính là default của image); builders v3.2 đặt
  `full-height` trên **column-group** (áp nhầm fix v3.2 của `column`) và `default` trên
  **figure image** → `getAppearanceConfig()` trả undefined → reader throw → `buildFromContent`
  reject → `stage-builder buildEmpty` fallback bọc TOÀN BỘ content thành 1 element **HTML Code**.
  `footer_newsletter` parse được vì chỉ có row/heading/text — đúng pattern report "chỉ newsletter
  apply" tái diễn. **Storefront không validate appearance → 30/30 vẫn PASS** — verify matrix cũ
  không thể phát hiện; **bài học: thay đổi content PB phải verify cả admin stage**. Control:
  markup admin thật (page `home`) = column-group `default` / image `full-width`. Fix
  `Model\FooterBlockContent`: column-group `full-height`→`default` (4 chỗ + dedupe
  `data-background-images` trùng), figure `default`→`full-width` (3 chỗ); 0 CSS impact
  (selectors không bám appearance của column-group/figure). Reseed → block #47–54. Verify:
  audit DB sạch; storefront **30/30 PASS**; **admin stage parse native 4/4** (links: row +
  column-group + column-line + 4 column + 4 heading + 4 text; social: + 4 image; trust: 2
  column-group + 10 column + 9 image; newsletter: row + heading + text) — screenshots
  `admin-stage-after-fix-*.png` trong evidence (kèm `evidence-before-fallback-*.png`). **Còn lại
  1 block vẫn HTML Code: `footer_links_block` #2 "Footer Links Block"** — sample Luma/Hyvä cũ
  với content raw HTML (không phải PB, không fix markup được), **0 tham chiếu** (code/CMS
  pages/widgets) → đề xuất xóa cùng đợt dọn duplicate `homepage-newsletter` #21/22 (Pending TL).
  Phụ: seed script backup flaw đã fix (tên file theo identifier+store); admin grid label
  "COLUMN n (NAN/12)" do `data-grid-size` trên column (cosmetic — home markup không có attr này,
  cân nhắc bỏ khi chạm tới); static warning `$open` possible-undefined (destructuring optional,
  guard `!empty()` — pre-existing v2, cosmetic).
- **09-29 (v4.2 — validate đường data patch canonical + re-verify sau reseed)**: phiên trước đã
  chạy `seed-footer-blocks.php --reset` (13:44): backup → `revert()` → `apply()` trực tiếp (bypass
  `setup:upgrade`). Kết quả audit: 8 row (#31–38) tái tạo từ builders hiện hành — 0 HTML element,
  column `full-height` đúng (links 4 / social 4 / trust 10), v4 styles đủ; **`revert()` scoped đúng
  4 identifier** — row legacy `footer_links_block` #2 (Hyvä, 07-28) sống sót. So sánh pre/post:
  VI (store 1) byte-identical cả 4 block; trust identical cả 2 store (images-only) → reseed **không
  đổi nội dung**, chỉ chứng minh patch canonical chạy đúng. ⚠️ Flaw backup script: tên file theo
  identifier mà mỗi identifier có 2 row store → row sau ghi đè row trước, **backup EN (store 0)
  mất** — chấp nhận: content là code-owned (FooterBlockContent builders) + VI identical + audit
  cấu trúc EN = builder output (đã ghi chú trong header script). `setup:upgrade` vẫn KHÔNG chạy:
  `setup:db:status` = declarative schema pending từ working-tree edit `Secomm_Ahamove db_schema.xml`
  (việc khác, §12) — lý do cũ (VN-address checksum) đã hết (CSV LF, git-clean), header seed script
  đã sửa cho đúng. Patch **đã có sẵn trong `patch_list`** → khi setup:upgrade chạy sau này sẽ không
  re-apply (không đè edit admin). Ghi nhận env (ngoài scope): `setup_module` chỉ 57 rows, thiếu
  Launchpad_*/Secomm_* (vết DB restore/DROP 09-23). Re-verify sau reseed: `cache:clean
  block_html full_page translate` + Playwright **30/30 PASS** (margin 4 row = 0px, accordion,
  PB-strip sim, 0 pageerror/4xx). Screenshots post-reseed copy vào evidence.
- **09-29 (v4.1 — fix `margin-block-start: 4rem !important` dư giữa các section footer)**: report
  user. Root cause: homepage.css `#html-body.cms-index-index [row][appearance=full-width]
  :has([column-group/column-line]) { margin-block-start: 4rem !important }` — specificity (1,5,1) —
  bleed vào footer trên homepage (các row footer đều full-width + có column). Counter trong
  footer.css: `#html-body footer.page-footer .footer-section-* [row][appearance=full-width]
  :has([column-group/line])` = (1,6,1) thắng; kèm margin-block-end 0. Verify thêm assertion:
  computed margin-block-start của cả 4 row footer = 0px → **30/30 PASS**. Ghi chú: các homepage.css
  rules dạng `#html-body...:has(...)` viết cho section homepage trước khi footer tồn tại — TL cân
  nhắc scope `:not(footer *)` khi chạm lại homepage.css (cùng F9).
- **09-29 (v4 — design fidelity pass theo design context Figma node 2717-85933)**: spec đo được từ
  get_design_context — heading newsletter/social = Inter Medium 500 / 20px / lh 28 / tracking 0 /
  text-black (bỏ semibold + tracking 0.08em + #101828); title cột links = label-l Medium 500 /
  #364153; link list = body-2 #4a5565; footer padding-top 80px; **border-b mỗi band**
  (newsletter/social #e5e7eb, trust #d1d5dc); form newsletter gap 8px + max-w 1184 (px-128) +
  input px-14 + placeholder #364153 + button px-20; social pt-16/pb-32; copyright pt-32/pb-24
  pl-40/pr-24 + gap legal 16px + © #364153 / legal #4a5565. Apply: builder classes (heading,
  title, list) + footer.phtml (wrappers) + copyright.phtml + subscribe.phtml + footer.css
  (fallback link color #4a5565). Verify 29/29 PASS (input 1058 = 1184-118-8 đúng spec);
  screenshots 1440/375 mới.
- **09-29 (v3.2 — ROOT CAUSE thật của "vẫn HTML Code")**: `appearanceConfig` (stage-builder.ts) trả `undefined` khi `data-appearance` không có trong appearances config của type → `.reader` throw → promise reject → **`buildEmpty` fallback gói TOÀN BỘ content thành 1 element `html` (label "HTML Code")** (stage-builder.ts:320-337). `column.xml` chỉ khai `full-height` (default) / `align-top` / `align-center` / `align-bottom` — **KHÔNG có `default`**; builders đặt `data-appearance="default"` trên Column → mọi block có column (links/social/trust) đứt; newsletter (Row>Heading+Text, không column) parse OK — khớp chính xác report "chỉ newsletter apply". Fix: columns → `data-appearance="full-height"` + `data-background-images="{}"` (mirror markup admin thật, homepage dùng y hệt). Audit DB sau reseed: 22/22 column full-height, 0 invalid, 36 background-images đủ. Verify 29/29 PASS. Bài học: content PB seed phải dùng đúng appearance tên trong `<appearances>` của content type — lệch 1 tên = cả block rơi HTML Code fallback.
- **09-28 (v3.1 — theo feedback "vẫn còn HTML code")**: audit DB cho thấy element `data-content-type="html"` duy nhất còn lại = `{{block}}` newsletter (chính là chỗ mở dialog "Edit HTML Code"). Đổi sang **Text element** chứa directive (mở WYSIWYG trực quan; directive render qua cùng template filter). Audit sau reseed: **0 HTML elements** trên cả 8 block row. Verify 29/29 PASS (form render + toàn bộ matrix). Lưu ý admin: Text element của links chứa `<ul><li>` — mở là WYSIWYG visual (default), toggle HTML chỉ khi cần; directive `{{block}}` newsletter là code-owned — admin không đụng tới.
- **09-28 (v3 — theo feedback "bỏ HTML Code, dùng element PB thuần + classes, giảm footer.css")**: content chỉ còn native PB elements — links = Heading + **Text element** (WYSIWYG, ul/li); social/trust payments/badge = **Image elements** (swap qua media gallery, payments = nested column-line 8 Image column); HTML element duy nhất còn lại = 1 dòng `{{block}}` newsletter (bắt buộc code-side: form_key + formValidation; widget thường dính Hyva CMS JIT 2-pass). Typography/colors/spacing chuyển sang **Tailwind classes trong CSS Classes field** (kể cả arbitrary variants `[&_a]:*`, `[&_li]:*`); `footer.css` giảm còn ~180 dòng chỉ giữ: guard counter homepage.css bleed (classes thua specificity+importance — F9), column-line containers (element tự sinh không có field), accordion chrome, img sizes (bleed), figure reset. Reseed v3 qua `reseed-footer-pb.php` (backup-first 8 file). Lưu ý ownership: img sizes + guard phải ở CSS — `[&>img]` classes thua `body.cms-index-index` !important rules.
- **09-28 (v2 — theo feedback user "giống homepage: PB components + classes trong CSS Classes")**: content 4 block đổi từ raw HTML sang **PageBuilder markup** (admin sửa trực quan trong PB stage): newsletter = Row > Heading + HTML(`{{block}}`); links = Row > column-group(4 Column) > Heading + Text(ul/li); social = Row > Heading + 4 Column(Image); trust = Row > 2 Column(Image badge | HTML payments). Anchor classes ở CSS Classes field; toàn bộ section styling chuyển sang `footer.phtml` (template-owned wrappers) + `footer.css` (structural selectors + guard counter homepage.css bleed, F9). Builders tách ra `Model\FooterBlockContent`; reseed qua `.ai/evidence/TASK-7EYJ4C/reseed-footer-pb.php` (backup-first, 8 backup `block-*-before-pb.html`); chevron accordion chuyển sang CSS `::after` mask (bỏ SVG khỏi content); Alpine init có structural fallback + default-open group đầu khi mất class.
- **09-28 (v3) Verify**: Playwright **29/29 PASS** (full matrix như v2; payment icons giờ = nested Image columns — selector verify cập nhật; screenshots mới 1440/375). **Sự cố trong run**: 2 lần tool-write footer.css bị corruption (marker rác trong CSS) — phát hiện bằng brace-balance + marker grep, sửa bằng line-surgery; bài học: validate file mới ghi trước khi build. Playwright **29/29 PASS ×2** (cold + FPC warm): thêm social icons 40px (lazy-load scroll-aware), badge 64px, payments 32px, PB-strip simulation (strip toàn bộ anchor classes → 4 cột đều 308px + link color structural OK); regression trainers/login 0 leak. Screenshots cập nhật trong evidence.
- **09-28 (v1)**: Theme + patch shipped: theme override `footer.phtml` (5 section, accordion Alpine mobile-only, copyright bar), `copyright.phtml` (config `design/footer/copyright` + legal links), `default.xml` (remove `footer-static-links` / `footer-cms-content` / `footer-column-store`, thêm 4 CMS block children); data patch `SeedFooterBlocks` (4 identifier × store 0 EN + store 1 VI, idempotent theo (identifier, store)); template newsletter form đổi sang Tailwind utilities; `footer.css` accordion chrome; i18n +3 phrase × 2 CSV; assets 13 file `pub/media/wysiwyg/footer/`; home pages remove newsletter row (backup byte-exact trong evidence); safelist regen mở rộng footer blocks (211 classes) + @source module template.
- **09-28 (v1) Verify**: Playwright vi 1440 + 375 = **21/21 PASS** (5 fail duy nhất = nhóm store-switch en — LL-0011 pre-existing; EN verify bằng emulation: 4 block × 2 store OK); accordion mobile toggle OK (aria-expanded sync); newsletter submit end-to-end OK (subscriber tạo + dọn); regression homepage/trainers/login: form + groups + 0 leak + 0 pageerror + 0 4xx; screenshots `evidence/TASK-7EYJ4C` + `/tmp/pw-cal/slp275-*.png`.
- **09-28 (v1) Pending TL**: (1) approve code + `.gitattributes` durable fix (F1); (2) quyết định line `@source` UiWidget chết (F3); (3) confirm language switcher sẽ được bù ở header trước release; (4) dọn duplicate `homepage-newsletter` (block 21/22) — đề xuất kèm ticket sau.
