# BUG-6AYPGS (SLP-290) — Evidence Results

Ngày: 2026-10-01 · Mode C · Option A (TL-approved)

## Root cause proof

- Rule field: `vendor/magento/module-page-builder/view/adminhtml/ui_component/pagebuilder_base_form.xml:188` → `<rule name="validate-css-class" xsi:type="boolean">true</rule>`
- Regex: `vendor/magento/module-page-builder/view/adminhtml/web/js/form/element/validator-rules-mixin.js:70` → `/^[a-zA-Z\d\-_\/:.\[\]&@()! ]+$/i` (không có `#`)
- Chi tiết test 4 chuỗi class mới + regression check cũ: `validate-css-class-check.txt`

## Reseed DB (backup-first)

```
$ sudo -u secomm php .ai/evidence/BUG-6AYPGS/reseed-footer-links-slp290.php
skip: no footer_links row for store 4 (nothing to update)
updated block_id=56 store=1 (Footer Links (VI)): 4465 -> 3865 bytes, backup block-footer_links-store1-before.html

$ DB check (REGEXP '#[0-9a-fA-F]{3,6}')
block_id=56 store=1 hex_present=0 len=3865
```

Lần chạy đầu fatal "Permission denied" do thư mục evidence tạo bằng root — chown secomm rồi chạy lại OK (trap BUG-SDZPCD).

## Safelist + build

```
regen-safelist.php: 181 -> 171 unique classes (hex utilities của footer rớt ra)
npm run build: Done in 467ms (tailwindcss v4.3.2)
```

Safelist còn 5 hex class (`bg-[#304b34]`, `bg-[#45744c]`, `bg-[#588f60]`, `bg-[#e7f1e8]`, `border-[#588f60]`) — nguồn = `cms_page home`, không phải footer → follow-up.

## Built CSS verification

`web/css/styles.css` sau build:
- CÓ: `.footer-links-title{color:#364153}` · `.footer-links-list a{color:#4a5565;transition:color .15s cubic-bezier(.4,0,.2,1)}` · `.footer-links-list a:hover{color:#101828}`
- KHÔNG còn: selector `.text-\[\#4a5565\]` / `.text-\[\#364153\]` / `hover\[\\&_a\]` (grep count = 0)

## Storefront verification

curl `http://127.0.0.1/ -H "Host: slaunchpad.localhost"` sau `cache:flush` (HTTP 200):

```
1× class="pagebuilder-column footer-links-group footer-links-open"
3× class="pagebuilder-column footer-links-group"
4× class="footer-links-title flex items-center justify-between text-[16px] font-medium leading-[24px] lg:pointer-events-none"
4× class="footer-links-list mt-4 [&_ul]:m-0 [&_ul]:list-none [&_ul]:p-0 [&_li]:py-1.5 [&_a]:text-[16px] [&_a]:leading-[24px]"
```

Footer-links region không còn hex utility (match hex duy nhất = SVG fill icon social `#1877F2` — ngoài block).

Lưu ý env: `slaunchpad.localhost` KHÔNG resolve được từ shell phiên này (HTTP 000, `/etc/hosts` không có entry) — verify qua `127.0.0.1` + Host header. HTTPS không listen (apache chỉ *:80).

## Pending

- QC manual: admin edit + save element footer_links (không có admin credential trong env).

---

# Part 2 (2026-10-01) — homepage content ("vẫn còn bị lỗi")

## Chẩn đoán

- Rescan `footer_links` DB: CLEAN từ Part 1 → nếu tab admin mở từ trước khi reseed, stage giữ giá trị cũ → hard refresh.
- Scan toàn bộ cms_page + cms_block (tag có `data-content-type`, class attr vs core rule): fail thật = `cms_page home` (store 1+2), `testpage1/2`:
  - heading: `text-[#293e2d]` · column `.lp-promo-card`: `bg-[#304b34]` · column `.lp-usp`: `bg-[#e7f1e8]` · figure USP icon: `bg-[#45744c]`
- `homepage-newsletter` = raw HTML (anchor không có data-content-type) → KHÔNG bị validate (đính chính Part 1).

## Migration

```
$ sudo -u secomm php .ai/evidence/BUG-6AYPGS/migrate-home-hex-classes-slp290.php
migrated cms_page #8 [testpage1] store=0
migrated cms_page #10 [testpage2] store=0
migrated cms_page #2 [home] store=1
migrated cms_page #13 [home] store=2
migrated cms_block #21 [homepage-newsletter] store=0
migrated cms_block #22 [homepage-newsletter] store=0
```

Token-aware (leading-space): `hover:bg-[#45744c]` (5) giữ nguyên — fixture check: 4+4+4+4 replace, raw-HTML hexes untouched.

## Verify

- Rescan toàn bộ (mọi tag có data-content-type): **ALL VALIDATED FIELDS CLEAN**
- styles.css: `.lp-text-olive{color:#293e2d}` · `.lp-bg-olive-dark{background-color:#304b34}` · `.lp-bg-olive{background-color:#45744c}` · `.lp-bg-mint{background-color:#e7f1e8}`
- Safelist: bare hex rớt (grep = 0); số 1 còn lại = substring của `hover:bg-...` raw HTML; +4 `lp-*` → 171=171
- Storefront home (curl 127.0.0.1 + Host, sau cache:flush): HTTP 200, lp-* render, 0 hex trên element có data-content-type

Backups: `cms_page-*-store*-before.html`, `cms_block-homepage-newsletter-store0-before.html` trong thư mục này.

---

# Part 3 (2026-10-01) — root cause thật của lỗi dai dẳng: "&" entity round-trip

User gửi screenshot: field **đã sạch hex** nhưng vẫn "Please enter a valid CSS class."

## Cơ chế (verified)

- Regex rule chấp nhận `&` nhưng KHÔNG chấp nhận `;`.
- Class chứa `&` (Tailwind `[&_x]` arbitrary variants) round-trip qua HTML: admin form
  input nhận dạng escaped `[&amp;_ul]` — browser HIỂN THỊ `[&_ul]` trong input (như
  screenshot) nhưng giá trị validate chứa `;` → FAIL. Class chứa `&` không thể save
  qua field này bằng mọi giá, bất kể hex.
- Test: raw form PASS / escaped `[&amp;_ul]` form FAIL (kèm `;`).

## Fix

Field chỉ giữ `footer-links-list mt-4`; toàn bộ `[&_ul]/[&_li]/[&_a]` variants chuyển
về footer.css (`.footer-links-list ul/li/a` — margin/padding-block .375rem/list-style,
font 16/24, color + transition như trước). Reseed lại footer_links (3865→3497 bytes);
safelist 171→165 (6 token `[&_x]` rớt); rebuild; flush.

## Verify

- STRICT rescan toàn bộ cms_page + cms_block (regex AND không chứa `&`): **ALL VALIDATED FIELDS CLEAN**
- styles.css: `.footer-links-list ul{margin:0;padding:0;list-style:none}` · `li{padding-block:.375rem}` · `a{color:#4a5565;font-size:16px;line-height:24px;transition:...}`
- DOM sau flush: 4× `class="footer-links-list mt-4"` (sạch), 4× heading pass
