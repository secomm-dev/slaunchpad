# TASK-7EYJ4C (SLP-275) — Evidence Notes

Ngày: 2026-09-28 · Mode B · Status: **in_review (chờ TL)**

## Delivered (v2 — PageBuilder content, theo feedback user)
- Theme `Secomm/launchpad/Magento_Theme`: override `html/footer.phtml` (section containers
  template-owned; accordion Alpine với structural fallback + default-open), `html/footer/copyright.phtml`
  (config `design/footer/copyright` + legal links), layout `default.xml` (remove `footer-static-links`,
  `footer-cms-content`, `footer-column-store`; thêm 4 CMS block children), `tailwind-source.css`
  (+`@source` module template, path 6×`../` + `code/`), `web/tailwind/theme/footer.css`
  (structural selectors + layout guard counter homepage.css bleed + counter split-image rules),
  `web/images/chevron-down.svg` (mask chevron accordion).
- Module `Launchpad_CmsContent` 1.3.0: `Model\FooterBlockContent` (builders PB markup dùng chung),
  data patch `SeedFooterBlocks` (4 identifier × store 0 EN + store 1 VI; idempotent theo
  (identifier, store)), template `newsletter/subscribe.phtml` → Tailwind utilities, CHANGELOG + README.
- CMS: 8 block rows = **PageBuilder content** (Row/column-group/Heading/Text/Image/HTML + anchor
  classes ở CSS Classes field) — reseed bằng `reseed-footer-pb.php` (backup-first, 8 backup
  `block-*-before-pb.html`); remove newsletter row khỏi 2 page `home` (backup byte-exact);
  config `design/footer/copyright` = "©2026 SECOMM Launchpad".
- i18n: +3 phrase × vi_VN + en_US (CRLF). Assets: 13 PNG Figma → `pub/media/wysiwyg/footer/`.
- Safelist: regen quét footer blocks (165 classes sau v2 — content không còn tailwind utilities) —
  `regen-safelist.php`.

## Store map v4.7 (09-29) — patch seed store 1 (VI) + store 4 (EN), bỏ store 0
- Patch `SeedFooterBlocks` theo staging: `STORE_ID_VI = 1`, `STORE_ID_EN = 4`; **không có row
  store 0 fallback** — store view thiếu row riêng = không có footer (by design).
- Guard `storeExists()` (StoreManagerInterface): env thiếu store view → skip (local không có
  store 4; FK `cms_block_store.store_id → store` sẽ nổ nếu insert cứng).
- Local sau reseed: 4 row store 1 (VI) #55–58; EN local (store 2) trống footer. Verify EN:
  staging, hoặc emulation CLI — `verify-blocks-per-store.php` đang hard-code store 1/2, đổi sang
  1/4 khi dùng trên staging.
- Staging deploy: patch chưa có trong patch_list của staging → `setup:upgrade` sẽ apply với map
  mới (seed store 1 + 4). Local: patch đã registered nên không tự re-run.

## F15 (09-29) — mobile links accordion theo Figma 2717:86198
- 3 lỗi user report: chevron collapse invisible (`::after` w20 **h0** — verify cũ PASS ảo vì chỉ
  assert computed mask), dư line cuối (builders gắn border-b cả 4 group), padding/rhythm sai
  (group py-5 + list mt-4 thay vì container gap-16 + title→list 24).
- Fix structural trong block mobile của footer.css (đè utility classes — strip-proof): chevron
  height 20px; gap 16px trên column-line (`!important` — homepage bleed gap 1rem !important);
  group pad dọc 0 + divider chỉ `:not(:last-child)`; title→list mở 24px.
- Spec nguồn: get_design_context Figma node **2717:86198** ("Accordion" mobile: px-24 py-32
  gap-16, chevron 20×20, nav list gap-12, divider giữa thôi). Verify DOM + screenshot
  `slp275-links-mobile-fixed.png` / `-toggle.png`; 30/30 PASS (desktop mobile-only rules).

## F14 (09-29) — container padding `.row-full-width-inner` → counter footer.css
- Sau khi scope v4.9.2 (F13), padding **tầng dưới bị che trước giờ lộ ra**: page-builder.css
  `[row full-width] > .row-full-width-inner { @apply container }` = padding-inline 8→24px
  (Tailwind container step) + max-width steps + mx-auto → footer content lệch phải 24px so với
  main (user report `padding-inline: calc(var(--spacing) * 6)`).
- Fix: `footer.page-footer [row full-width] > .row-full-width-inner { padding-inline: 0 }`
  (footer.css, specificity (0,4,1) > (0,3,0)). Wrapper footer.phtml đã owning container
  (max-w-[1440px] px-6 lg:px-10).
- Verify đo cạnh trái thật: **1813 → main 219 / footer 219 (0)**; 1440 → 40/40 (0); 375 → footer
  24px (design px-6; main là card-slider không so sánh được). inner padding-inline 0 ở cả 3
  viewport. 30/30 PASS. `slp275-footer-aligned-1813.png`.
- **Lesson (F14)**: đè một rule bleed bằng `:not(footer *)` xong phải re-check tầng rule dưới nó
  (trước đó bị !important che) — leak có thể có nhiều tầng.

## F13 (09-29) — homepage.css v4.9.2 padding bleed → scoped `:not(footer *)`
- User report: `padding: 0 2.5rem 0 0 !important` (rule "Living, Reimagined") apply lên inner của
  4 row footer trên homepage → lệch. Fix tại nguồn homepage.css (comment v4.9.3) + `npm run build`
  (vi_VN = symlink tự tươi, en_US không tồn tại trong pub/static).
- Rule này KHÔNG còn match row nào ở main (homepage hiện chỉ có contained/full-bleed; full-bleed
  không có `[data-element=inner]`) → trước fix hiệu ứng duy nhất là phá footer. Verify: footer
  inner padding-right 24px (không 40), margin counter v4.1 giữ 0px, 30/30 PASS,
  `slp275-homepage-footer-after-padding-scope-fix.png`.
- **Trap audit script**: `CSSStyleRule.cssRules` (nested CSS API) = list rỗng NHƯNG truthy →
  pattern `if (r.cssRules) { walk(children); continue; }` bỏ qua mọi style rule → báo sai "0 rule
  bleed". Bản sửa test `selectorText` TRƯỚC rồi mới recurse khi `cssRules.length`.
- Kết quả audit đúng: **12 rule `cms-index-index` vẫn match footer DOM** (margin 3rem/4rem,
  grid 1fr + 940fr/467fr, img cover 535px + aspect-ratio + object-fit, padding-bottom 0,
  column-group width 100%) — tất cả đang bị footer.css counter giữ, 30/30 assert. Scope-all
  `:not(footer *)` cho 12 rule = đề xuất TL (F9 durable).
- Red block giữa homepage = video placeholder (`aspect-video`, `bg-[var(--play-*)]`) — by design.

## Admin UX (v4.3 — FIX column-group + image appearance, admin parse native 4/4)

⚠️ **Root cause "HTML Code" lần này KHÔNG phải stale tab**: builders v3.2 đặt appearance sai —
column-group cần `default` (column_group.xml chỉ khai default), figure image cần `full-width`
(image.xml chỉ khai full-width) → stage parse reject → fallback HTML Code. Giống v3.2 (column
cần `full-height`) nhưng chiều ngược lại — lesson: **appearance phải đúng theo từng content-type
config, và thay đổi content PB phải verify CẢ admin stage** (storefront không validate nên 30/30
vẫn pass trong khi admin vỡ). Verify 09-29: 4/4 block parse native (DOM byType + screenshots
`admin-stage-after-fix-*.png`). Lưu ý: F5 trang edit nếu tab cũ (stage load lúc mở trang).

**Riêng block `footer_links_block` #2 "Footer Links Block"** vẫn hiện HTML Code — ĐÚNG, vì
content nó là raw HTML (sample Luma/Hyvä), không phải PB. 0 tham chiếu → đề xuất xóa (TL).

## Admin UX (v3.2 — native PB elements, 0 HTML element)

⚠️ **Nếu thấy "HTML Code" trong admin**: F5 reload trang edit block (stage load content lúc mở
trang, không tự refresh). **Tuyệt đối không Save trên tab cũ** — ghi đè ngược content cũ vào DB;
lỡ bấm thì chạy lại `reseed-footer-pb.php`. Root cause v3.2: column `data-appearance="default"`
không tồn tại trong column.xml → stage parse reject → buildEmpty fallback (record F13/v3.2).
Admin mở Content > Blocks thấy **toàn bộ element PB thuần, không có khối HTML code**:
- `footer_links`: 4 Column > **Heading element** (title) + **Text element** (danh sách link —
  WYSIWYG visual, sửa label/link tại chỗ).
- `footer_social`: Heading + 4 Column, mỗi column 1 **Image element** (swap qua media gallery).
- `footer_trust_payments`: 2 Column — badge = Image element; payments = nested column-line với
  **8 Image element** riêng (đổi thứ tự/thêm bớt bằng PB UI).
- `footer_newsletter`: Heading + **Text element** chứa directive `{{block}}` (mở WYSIWYG trực quan,
  KHÔNG còn dialog "Edit HTML Code"; directive là code-owned — admin không đụng; static-block
  widget không dùng được do Hyva CMS JIT 2-pass).
- Tailwind classes nằm ở **CSS Classes field** từng element (typography/colors/spacing, gồm
  arbitrary variants `[&_a]:*` / `[&_li]:*`). Thêm class mới → `regen-safelist.php` + `npm run build`.
- `footer.css` (215 dòng) chỉ giữ phần class không tự owns được: guard counter homepage.css
  bleed (specificity+importance), column-line containers (element tự sinh, không có field),
  accordion chrome (chevron ::after + collapse), img sizes (bleed), figure reset.
- PB admin save có thể strip anchor classes — layout vẫn đứng (structural CSS + JS fallback +
  default-open runtime); custom styling admin tự thêm nếu bị strip thì re-enter.

## Verify (29/29 PASS ×2 cold + FPC warm)
- Playwright vi 1440 + 375: structure/section order, 4 cột đều 308px, form 1002+118, accordion
  toggle + aria-expanded, chevron CSS mask, social 40px (lazy-load scroll-aware), badge 64,
  payments 32/hàng 4, copyright config, 0 pageerror/4xx, regression homepage/trainers/login 0 leak.
- **PB-strip simulation**: strip toàn bộ anchor classes khỏi content → layout giữ nguyên qua
  structural CSS (cột đều, màu link đúng).
- Newsletter submit e2e FPC warm (v1): subscriber tạo rồi xóa. Store scoping: emulation CLI
  store 1 vi / store 2 en (`verify-blocks-per-store.php`); store-switch HTTP chết local (LL-0011).

## Provenance notes (trung thực)
- Backup 2 trang home (v1): script chạy bản đầu **ghi backup FAIL** nhưng vẫn UPDATE — đã fix
  (throw trước UPDATE) và tái dựng backup **byte-exact** (validate đúng số byte remove 652/645).
- v2 reseed: backup-first đúng chuẩn (8/8 backup trước update).
- setup:upgrade fail đầu run = VN-address checksum autocrlf (F1 record) — đã fix env; `.gitattributes`
  chờ TL.
- 09-29 13:44 reset+reseed qua data patch trực tiếp (`seed-footer-blocks.php --reset`): VI
  byte-identical pre/post, legacy `footer_links_block` #2 không bị đụng. **Backup flaw**: tên file
  theo identifier, 2 row store ghi đè nhau → chỉ backup VI (store 1) còn trên disk (chi tiết ở
  header script). `setup:upgrade` không chạy vì declarative schema pending (Ahamove — việc khác,
  §12); patch đã registered trong `patch_list` nên setup:upgrade sau này không re-apply.
  Re-verify 30/30 PASS sau `cache:clean block_html full_page translate`.

## Pending TL (block release, không block review code)
1. `.gitattributes` `*.csv text eol=lf` (durable fix F1) — repo-level, cần TL commit.
2. `@source` UiWidget line chết (F3) — pre-existing, sửa 1 token nhưng ngoài scope task.
3. homepage.css bleed (F9): cân nhắc scope `:not(footer *)` cho các rule `body.cms-index-index`
   row/column/image khi task TASK-0NNZCW được chạm tới — hiện đã counter ở footer.css.
4. Language switcher phải được bù ở **header** trước release (task này chỉ remove khỏi footer).
5. Dọn duplicate CMS block `homepage-newsletter` (id 21/22) — ticket riêng đề xuất.

