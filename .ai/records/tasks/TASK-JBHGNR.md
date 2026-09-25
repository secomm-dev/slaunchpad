---
id: TASK-JBHGNR
type: task
title: '[Product card] Hiển thị option out of stock theo design (SLP-272)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-272
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-25
updated: 2026-09-25
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions:
  - "2026-09-25 (user): Option A — bật cataloginventory/options/show_out_of_stock=1 toàn site (sản phẩm OOS hiện trên PLP/search/widget); style OOS áp cho Card + PDP + Quick View"
decision_assessment: material-config
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Launchpad/CmsContent/Setup/Patch/Data/EnableShowOutOfStockProducts.php
  - app/code/Launchpad/CmsContent/CHANGELOG.md
  - app/code/Launchpad/CmsContent/README.md
  - app/design/frontend/Secomm/launchpad/web/tailwind/components/swatches.css
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/quickview/modal.phtml
source_areas:
  - theme-catalog
  - catalog-config
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-25
supersedes: []
---

# [SLP][TASK-JBHGNR] [Product card] Hiển thị option out of stock theo design (SLP-272)

<!-- External ticket: SLP-272 "product card cần hiện thị out of stock các option như design". Mode C — embedded Mini-Spec + Approach. -->

## Summary

Card configurable (homepage rails + PLP — cùng `Magento_Catalog::product/list/item.phtml`) **không render** option hết hàng: `cataloginventory/options/show_out_of_stock = 0` → Magento/MSI lọc child OOS khỏi `getUsedProducts()` và `jsonConfig.salable` rỗng. Hyvä `configurable-options` đã hỗ trợ disable option theo `optionConfig.salable` (swatch-item có `:disabled="optionIsDisabled"`) nhưng không có data. Style disabled hiện tại (dashed + opacity 45%) cũng khác design.

Design (`.ai/evidence/TASK-0NNZCW/figma-card-shot.png`): text option OOS (`M`) — viền nhạt gray-200, chữ gray-400, 1 đường chéo mảnh xám từ góc dưới-trái lên góc trên-phải; color swatch OOS — dot giữ nguyên màu, đường chéo xám mảnh cắt ngang dot.

## Mini Spec

### Goal
Option (swatch text / color) của product configurable mà child tương ứng hết hàng vẫn hiển thị trên product card (và PDP / Quick View) ở trạng thái OOS theo design — không chọn được.

### Expected Behavior
1. `cataloginventory/options/show_out_of_stock = 1` (default scope) sau `setup:upgrade` (data patch); indexer liên quan (EAV, price, stock, inventory, catalogsearch) được invalidate để cron/reindex build lại.
2. Card configurable: option có **không child salable nào** hiển thị disabled ngay khi chưa chọn gì; khi chọn 1 option attribute khác, option của các tổ hợp OOS bị disabled theo tổ hợp (hành vi Hyvä `optionConfig.salable`).
3. Text swatch OOS: viền `--ds-border-gray-secondary`, chữ `--ds-text-gray-quinary`, đường chéo 1px `--ds-border-gray-secondary` góc dưới-trái → trên-phải; viền liền (không dashed), không opacity; hover không đổi viền; cursor `not-allowed`.
4. Visual (color/image) swatch OOS: dot giữ màu 100%, đường chéo 1px `--ds-border-gray-primary` trong phạm vi dot.
5. Áp dụng giống nhau cho card, PDP, Quick View (style global `.swatch-option` + override card `.hp-card`).
6. Sản phẩm OOS (simple / configurable hết mọi child) hiện trên PLP/search/widget với trạng thái "Out of stock" (decision user — Option A).

### Constraints / Rules
- Không sửa vendor / Hyvä core JS — dùng pipeline `salable` sẵn có.
- Không đụng cart/checkout/order; add-to-cart vẫn do server re-validate stock.
- Config set bằng data patch (admin vẫn đổi được), **không** pin `app/etc/config.php`.
- Tailwind v4 CSS-first; token DS (`--ds-*`), không hardcode màu mới; không tạo `tailwind.config.js`.

### Out of Scope
- Thay đổi dòng "In stock" / nhãn "Out of stock" của card.
- Tooltip / text "Hết hàng" trên swatch.
- Render server-side trạng thái disabled (tránh flash trước Alpine init `x-defer="intersect"`) — follow-up nếu cần.
- Sắp xếp OOS xuống cuối listing.

### Acceptance Criteria
- [ ] AC1: Config `show_out_of_stock` = 1 sau khi chạy patch; patch idempotent (lần 2 không đổi gì).
- [ ] AC2: Card configurable có child OOS render đủ option (kể cả option OOS); option OOS `disabled`, không chọn được.
- [x] AC3 (text: thật qua tổ hợp; color: synthetic `aria-disabled` — xác nhận lại sau AC1): Style text / color OOS khớp design (viền, chữ, đường chéo, dot giữ màu) trên card homepage + PLP; PDP + Quick View cùng style.
- [ ] AC4: Option còn hàng không đổi style (default / hover / checked); add-to-cart option còn hàng hoạt động.
- [x] AC5: Tailwind build PASS; 0 pageerror; PHPCS patch 0 error.

## Approach

1. Test data local: chọn 1 configurable (size + color) hiển thị trên homepage/PLP, set một số child OOS (backup qty/status để restore).
2. Data patch `Launchpad\CmsContent\Setup\Patch\Data\EnableShowOutOfStockProducts`: `WriterInterface::save('cataloginventory/options/show_out_of_stock', '1')` default scope + invalidate indexer `catalog_product_attribute`, `catalog_product_price`, `cataloginventory_stock`, `inventory`, `catalogsearch_fulltext` (tương đương side effect khi admin Save — backend model + plugin `ShowOutOfStockConfig`). Module `Launchpad_CmsContent` đã sở hữu phần product card (`ViewModel\ProductGallery`).
3. `swatches.css`: disabled state → viền/chữ token disabled + đường chéo (`linear-gradient(to bottom right …)` trên label cho text; `::after` grid-area 1/1 cho visual), bỏ dashed/opacity.
4. `homepage.css`: rule `.hp-card .swatch-option:has(:disabled)` theo cùng token (card override màu viền/chữ nên phải set lại); gỡ class orphan mock-up `.hp-card-swatches/.hp-card-swatch/.hp-card-swatch-out`.
5. Reindex + build Tailwind + `cache:flush`; verify Playwright (Chrome 150) card homepage/PLP, PDP, Quick View; evidence `.ai/evidence/TASK-JBHGNR/`; CHANGELOG/README `Launchpad_CmsContent`.

## Progress

- 2026-09-25: analysis (`/task`) + user chốt Option A + phạm vi Card/PDP/Quick View; record + Mini-Spec + Approach tạo; bắt đầu implement.
- 2026-09-25: implement xong code + CSS. **Áp config vào DB local bị auto-mode chặn** (Modify Shared Resources) → AC1/AC2 chờ user chạy lệnh (Handoff §1).

## Implementation

- Data patch `Launchpad\CmsContent\Setup\Patch\Data\EnableShowOutOfStockProducts` — `WriterInterface::save(show_out_of_stock, '1')` default scope + invalidate 5 indexer (side effect tương đương Admin Save: backend `ShowOutOfStock` + plugin `ShowOutOfStockConfig` → EAV; MSI plugin price/search đọc cùng flag).
- `swatches.css` (global — card / PDP / Quick View): `:has(:disabled)` / `[aria-disabled=true]` → viền `--ds-border-gray-secondary`, chữ `--ds-text-gray-quinary`, `--swatch-strike` = `linear-gradient(to bottom right …)` 1px (góc dưới-trái → trên-phải); text swatch vẽ trên label, color swatch vẽ trên dot `> span` (dot giữ màu 100%, line `--ds-border-gray-primary`), image swatch `::after`. Bỏ dashed + opacity 80%.
- `homepage.css`: card override `border-color/color` bằng cùng token cho swatch text (visual giữ ring transparent); gỡ `.hp-card .swatch-option` dashed/opacity .45 + class mock-up orphan `.hp-card-swatches/.hp-card-swatch/.hp-card-swatch-out`.
- `quickview/modal.phtml`: bỏ `cursor-pointer` + `:class` `opacity-40 pointer-events-none` (utility layer đè style disabled); input vẫn `:disabled="!isValueAvailable(...)"`.
- Line endings: working tree nhiều file CRLF có sẵn (HEAD = LF) — giữ nguyên, diff thực (`--ignore-cr-at-eol`) nhỏ.

## Validation

Test data local (backup `evidence/TASK-JBHGNR/stock-backup.json`, script `setstock.php` → restore xem Handoff §3): OOS `bed-haven-{queen,king}-sage` (Sage hết toàn bộ), `sofa-meridian-3seat-*` (3 Seat hết toàn bộ) + `sofa-meridian-corner-charcoal` (tổ hợp), `table-ovale-8seat`.

| Check | Kết quả | Evidence |
|---|---|---|
| Baseline (config 0) | Root cause xác nhận: card/PDP bed-haven **mất Sage**, sofa **mất 3 Seat**, table-ovale **mất 8 Seat** | `verify-baseline.txt`, `card-*-baseline.png` |
| Text OOS style (card, tổ hợp Charcoal → Corner disabled) | viền rgb(229,231,235), chữ rgb(153,161,175), gạch chéo, opacity 1, cursor not-allowed; option còn hàng không đổi | `verify-css-only.txt`, `card-2176-charcoal-css-only.png` |
| Color OOS style (synthetic `aria-disabled` trên Oatmeal) | card 24×24 / PDP 30×30: gạch chéo trong dot, dot opacity 1 | `verify-visual-strike.txt`, `visual-strike-{card,pdp}.png` |
| Quick View (sofa, chọn Charcoal) | 3 Seat + Corner disabled + style OOS (GraphQL trả đủ value kể cả khi config 0) | `verify-quickview.txt`, `quickview-sofa-charcoal.png` |
| Tailwind build | PASS; `hp-card-swatch*` = 0 trong `styles.css` | — |
| PHPCS Magento2 sev≥6 | patch 0/0; modal.phtml 0 error (16 warning có sẵn: line length + CRLF) | — |
| pageerror | 0 | — |
| AC1 config = 1 + idempotent | **NOT RUN** (auto-mode chặn ghi DB config) | — |
| AC2 card/PDP render option OOS disabled ngay khi load | **NOT RUN** (phụ thuộc AC1) | — |

## Handoff / còn mở

1. Áp patch local + reindex + verify (user chạy):
   ```bash
   php -r 'require "app/bootstrap.php";$om=\Magento\Framework\App\Bootstrap::create(BP,$_SERVER)->getObjectManager();$om->create(\Launchpad\CmsContent\Setup\Patch\Data\EnableShowOutOfStockProducts::class)->apply();'
   php bin/magento indexer:reindex catalog_product_attribute catalog_product_price cataloginventory_stock inventory catalogsearch_fulltext && php bin/magento cache:flush
   node .ai/evidence/TASK-JBHGNR/verify-oos-options.js after
   ```
   Kỳ vọng: card/PDP bed-haven có Sage disabled (gạch chéo trong dot), sofa có 3 Seat disabled, table-ovale có 8 Seat disabled.
2. Deploy: `setup:upgrade` chạy patch → cron reindex indexer invalid (hoặc `indexer:reindex`). PLP/search sẽ bắt đầu hiện sản phẩm OOS (decision user).
3. Restore test data stock sau khi verify: `php .ai/evidence/TASK-JBHGNR/setstock.php restore .ai/evidence/TASK-JBHGNR/stock-backup.json` + reindex `inventory cataloginventory_stock`.
4. Follow-up (out of scope): render `disabled` server-side để tránh flash trước Alpine init (`x-defer="intersect"`).

## Correction round 1 (2026-09-25) — color option selected: bỏ nền trắng, viền selected bo tròn quanh dot

- **Hiện trạng** (`verify-color-selected-before.txt`): card — label visual nền `#fff` + `border-radius: 6px` (rule chung `.hp-card .swatch-option` set cứng radius/bg, đè `--swatch-radius`) → ring selected thành ô vuông bo góc trên tile trắng; PDP / Quick View — ring tròn nhưng có đĩa trắng (`--form-bg`) sau dot.
- **Fix**: `swatches.css` `[data-swatch-type="visual"]` → `background-color: transparent`; `homepage.css` `.hp-card .swatch-option[data-swatch-type="visual"]` → `border-radius: var(--swatch-radius)` (9999px) + `background-color: transparent`.
- **Verify** `verify-color-selected.js` (chọn Sage — sofa-meridian): card 32×32 radius 9999px / PDP + Quick View 38×38 radius 80px, bg `rgba(0,0,0,0)`, ring `#293e2d` tròn quanh dot; 0 pageerror (`color-selected-{card,pdp,quickview}-after.png`). Gạch chéo OOS color vẫn đúng (`verify-visual-strike.txt`).
- CHANGELOG: entry SLP-272 chuyển thành **1.2.7** (session khác đã thêm 1.2.5/1.2.6, bản cũ trùng số 1.2.4 với SLP-271).
