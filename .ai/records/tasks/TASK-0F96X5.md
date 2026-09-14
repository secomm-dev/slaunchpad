---
id: TASK-0F96X5
type: task
title: '[Product list] Translate product-list sections: inventory + verification'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-212
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-14
updated: 2026-09-14
ticket_ref: SLP-212
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Smile ElasticSuite (Hyva compat)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n (readonly — không sửa trong scope này)
  - vendor/hyva-themes/magento2-default-theme/Magento_Catalog/templates/product (readonly reference)
  - vendor/hyva-themes/magento2-smile-elasticsuite (readonly reference)
  - vendor/smile/elasticsuite/src/module-elasticsuite-core/view/frontend/templates/footer.phtml (readonly reference)
source_areas:
  - theme-i18n-dictionaries
  - hyva-catalog-plp-templates
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 0dc1c0d1
last_verified: 2026-09-14
supersedes: []
---

# [SLP][TASK-0F96X5] [Product list] Translate product-list sections: inventory + verification

<!-- External ticket: SLP-212. Screenshot ticket: demo PLP /living-room/living-room-seating, annotation tím "Bộ Lọc" + mũi tên đỏ chỉ "MUA THEO". Scope ticket: translate các section thuộc product list. -->

## Summary

Ticket yêu cầu translate các section còn thiếu trên product list page. Điều tra thực tế (không suy đoán — scan template + quét DOM live 2 store):

1. **Core PLP sections đã dịch sạch từ các đợt trước.** Toàn bộ phrase `__()` của toolbar/sorter/limiter/viewmode/amount, sidebar filter ( Magento_LayeredNavigation + Smile ElasticSuite Hyva compat `catalog/layer/{view,filter/default,filter/slider}.phtml`), product cards, pager, empty-state đã có trong theme dict (904 keys, gồm +107 từ BUG-M33P7N/SLP-132 commit `59ac409f`). DOM VI store xác nhận: "Sắp xếp theo", "Bộ lọc sản phẩm", "Thêm vào giỏ", "Thêm vào danh sách yêu thích", "Chế độ xem sản phẩm", "Hiển thị số mục mỗi trang"… render VI hết.
2. **Yêu cầu thực của screenshot = wording "Shop By"** (demo đang render "MUA THEO" theo HEAD). Việc đổi `"Shop By": "Mua theo" → "Bộ lọc"` **đã có sẵn trong working tree (uncommitted, dòng 603 vi_VN.csv)** do session song song 09-14 — đúng ý annotation. Demo render "MUA THEO" vì demo = HEAD (chưa commit + deploy), không phải thiếu key.
3. **Các chuỗi English còn nhìn thấy trên PLP KHÔNG thuộc lớp CSV dict** — phân loại đầy đủ ở Findings dưới: attribute labels/options + category/product names = **store data (Admin)**; 3 chuỗi rơi vào **code path không qua `__()`** (cần override template nếu muốn dịch); 2 chuỗi footer global thiếu key CSV nhưng **không thuộc "section thuộc product list"** → flag chờ TL/PM, không tự ý thêm (scope rule).

**Kết quả dev (Mode C): 0 thay đổi code cần làm trong scope ticket.** Deliverable = inventory có bằng chứng + danh sách follow-up chờ quyết định.

## Mini Spec

### Goal
- Chốt danh sách chuỗi chưa dịch trên PLP (cả 2 store view), phân loại: thiếu key CSV / store data / code path không qua dict / đã cover.
- Xác định action cần làm cho từng nhóm; không sửa gì ngoài scope ticket.

### Expected Behavior
- VI store: mọi UI chrome thuộc product-list section render tiếng Việt.
- EN store: render EN (identity sạch), không rò VI.
- Wording "Shop By" = "Bộ lọc" (theo annotation ticket + edit sẵn có).

### Approach
1. Inventory DOM-driven (mạnh hơn template-driven): fetch PLP local 2 store (`?___store=launchpad_en` thuần — LL-0026/memory store-switch), extract text nodes + aria/title/alt/placeholder + `<option>`, decode entities (LL-0005).
2. Đối chiếu từng chuỗi EN-looking với theme dict + truy source template/Block để xác định có đi qua `__()` hay không.
3. Phân loại + flag decision; không append CSV ngoài scope.
4. Bằng chứng: `.ai/evidence/TASK-0F96X5/` (DOM extracts 2 store + scan script + RESULTS).

## Findings (phân loại đầy đủ)

### A. Store data → Admin (KHÔNG dịch được qua CSV)
Hiển thị trên PLP nhưng là giá trị store-scope trong DB (precedent BUG-KFJ49A/SLP-183: out of scope → Admin):
- **Attribute labels**: `Color`, `Size`, `Style` (local) / demo có sẵn VI: "Màu sắc", "Chất liệu", "Giá" → demo đã cấu hình store label VI trong Admin; local chưa. Đường dịch: Admin → Stores → Attributes → Manage Labels.
- **Attribute option values**: Black/Blue/…/XL/XS (demo: "3 Seat", "Corner"…) — Admin store view options.
- **Category names** breadcrumb (Men/Tops/Jackets; demo: Living Room/Seating), **product names**, **store name** ("Tiếng Việt"/"English" trong switcher), **footer CMS links** (About/Company/Climate/Legal/Privacy/Terms).

### B. Code path KHÔNG qua `__()` — cần override template nếu muốn dịch (Tier 1 decision)
| Chuỗi | Source | Ghi chú |
|---|---|---|
| `Breadcrumb` (aria-label) | `Magento_Theme/templates/html/breadcrumbs.phtml` (Hyvä) — hardcode, không `__()` | screen-reader only; override verbatim + bọc `__()` + 2 row CSV nếu TL duyệt |
| `Grid` / `List` (title tooltip view-mode) | `Toolbar::getModes()` ← config `catalog/frontend/list_mode` — template render `$label` trực tiếp không `__()` | aria-label bao ngoài đã VI ("Chế độ xem sản phẩm - %1"); impact thấp |
| `Close (Esc)` (title) | `Mageplaza_SocialLoginPro/templates/hyva/header/modal.phtml` — hardcode | **File đang modified bởi TASK-7P5RJP (session khác) — không đụng**; không phải PLP section |

### C. Thiếu key CSV thật (path `__()` có sẵn) — footer GLOBAL, không thuộc section product list → chờ TL/PM duyệt scope
| Key | Source | Đề xuất VI |
|---|---|---|
| `Language` | `Magento_Store/templates/switch/languages.phtml:41` — có `__()` nhưng theme dict thiếu key | `Ngôn ngữ` |
| `Search engine powered by %1` | `module-elasticsuite-core/view/frontend/templates/footer.phtml` — Smile không ship vi_VN (chỉ de/en/fr/nl); theme dict thiếu key | `Công cụ tìm kiếm bởi %1` (hoặc tắt qua config `isEsLinkEnabled`) |

Nếu TL duyệt extend scope: append 2 row vào `i18n/vi_VN.csv` + identity mirror `en_US.csv`, theo LL-0010 (2-cột QUOTE_ALL, no-dup-key) + `cache:flush` as secomm.

### D. Đã cover — không cần làm
- Core PLP sections (danh sách ở Summary) — VI sạch cả DOM 2 store, EN identity sạch (0 rò VI trên EN store — chỉ store name + ™ product name).
- `OK` (price filter button): identity row có chủ đích (LL-0005), render "OK" cả 2 locale — chấp nhận.
- Wording "Shop By" → "Bộ lọc": **đã có trong working tree** (uncommitted, vi_VN.csv:603, session song song 09-14). Chờ commit + deploy demo là hết finding "MUA THEO". Commit cần tách hunk khỏi en_US.csv ("Track your order" +1 row của session khác).

## Verification

- **DOM live 2 store local** (category `men/tops-men/jackets-men.html`, HTTP 200 sau khi search engine đã chuyển `elasticsuite` — blocker 500 cũ không còn): VI store 239 strings, EN store 240 strings; EN-looking còn lại đã phân loại ở Findings; EN store 0 rò VI. `.ai/evidence/TASK-0F96X5/plp-{vi,en}.txt`
- **Template scan**: 54 phrase `__()` trong 17 template PLP (Magento_Catalog list/toolbar + LayeredNavigation + Smile Hyva compat layer) — 0 missing so với merged dict.
- **Dict check** các key PHP-rendered (Position/Product Name/Price/Relevance/Sort By/Set * Direction/Show/Page/…): đủ.
- Framework/render-level store emulation (LL-0007) không cần — không có key mới trong change set.

## Follow-ups đề xuất (Tier 1 — chờ TL/PM)
1. Duyệt extend scope thêm 2 row CSV nhóm C, hoặc tách ticket translate footer/global riêng.
2. Quyết nhóm B: có override template cho `Breadcrumb`/`Grid`/`List` không (screen-reader/tooltip only) — nếu có, làm ticket riêng (chạm Hyvä template override).
3. Admin: cấu hình store label VI cho attribute labels/options + category names trên local (demo đã có sẵn) — đưa vào checklist store data.
4. Release: commit working-tree CSV (tách hunk: `Shop By` vi-only; `Track your order` en-only của session khác) + deploy demo → đóng finding "MUA THEO" trên screenshot.
5. BUG-M33P7N (SLP-132) record `in_progress` — sau khi đợt này chốt, cân nhắc chuyển QC/release cùng lúc để khớp trạng thái.
