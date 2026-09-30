---
id: FEAT-W0XFV2
type: feature
title: '[Product card] Hiển thị brand (manufacturer) trên product card theo design (SLP-270)'
project_code: SLP
parent:
external_refs:
  tickets: SLP-270
  figma: https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17146
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review  # 2026-09-25: implement + verify tự động ALL PASS; chờ review
created: 2026-09-25
updated: 2026-09-25
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/list/item.phtml
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/homepage.css
  - app/design/frontend/Secomm/launchpad/web/css/styles.css
source_areas:
  - theme-product-card
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 92076998
last_verified: 2026-09-25
supersedes: []
---

# [SLP][FEAT-W0XFV2] [Product card] Hiển thị brand trên product card (SLP-270)

<!-- External ticket: SLP-270 "Hiện thị brand name của product ở trên product card như design. brand name là product attribute code manufacturer". Mode C — embedded Mini-Spec + Approach. -->

## Summary

Design card (Figma `2151:17146`, Content frame): `Brand Name` (h20) → 8px → `Product Name` (h56, 2 dòng) → 8px → Price.
Text brand: Label M — 14/20, weight 500, màu `#99A1AF` (= `--ds-text-gray-quinary`), không có text-case trong style (chữ "BRAND NAME" trong design là placeholder gõ sẵn chữ hoa).

Hiện trạng (TASK-0NNZCW): `item.phtml` đã đọc `getAttributeText('manufacturer')` và render `.hp-card-brand` **chỉ khi có brand**, tên cách brand 4px (`mt-1`), không có brand thì tên cách 8px (`mt-2`). Card nào không có brand sẽ lệch 20px so với card có brand cùng hàng. `.hp-card-brand` hard-code `#99a1af`.

Brand không hiện trên storefront vì (local DB, 2026-09-25):
- `manufacturer` (attribute_id 83) `used_in_product_listing = 0`, nên collection của PLP / CatalogWidget / FlashSaleList không load giá trị.
- Attribute chưa được gán vào attribute set nào đang có sản phẩm, 0 sản phẩm có giá trị (data hiện tại là data test).

**Quyết định của user (2026-09-25):** Admin tự cấu hình và gắn brand, không có data patch hay plugin. Brand hiển thị đúng như Admin nhập (không uppercase). Chỉ làm product card.

## Mini Spec

### Goal
Product card hiển thị brand (`manufacturer`) phía trên tên sản phẩm đúng như design. Các card cùng hàng luôn thẳng hàng dù có hay không có brand.

### Expected Behavior
1. Sản phẩm có `manufacturer` (và attribute được load trong listing): hiện option label phía trên tên, Label M 14/20 medium, màu `--ds-text-gray-quinary`, giữ nguyên chữ như Admin nhập.
2. Brand dài: 1 dòng, cắt bằng ellipsis (không đẩy tên xuống).
3. Không có brand: vẫn giữ 1 dòng trống cao 20px (`aria-hidden`) để tên / giá / swatch thẳng hàng với card có brand.
4. Tên luôn cách dòng brand 8px (design).
5. Áp dụng cho mọi nơi dùng `Magento_Catalog::product/list/item.phtml`: PLP (grid + list), PageBuilder products carousel, FlashSaleList.

### Constraints / Rules
- Chỉ sửa theme: `item.phtml` + khối `.hp-card-brand` trong `homepage.css`. Không data patch, không plugin, không đổi DB.
- Màu và typography dùng DS token có sẵn (`--ds-text-gray-quinary`, `--ds-type-label-m-*`), không thêm hex mới.
- `homepage.css` / `styles.css` đang có WIP của ticket khác (BUG-QJWCNG, flash head, hero filter). Chỉ sửa khối `.hp-card-brand`, rebuild `styles.css` bằng `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify`.

### Out of Scope
- Brand ở PDP, Quick View, mini-cart, compare, wishlist, …
- Cấu hình attribute (`Used in Product Listing`, gán vào attribute set) và nhập data brand: Admin làm (xem Handoff).
- Các lệch khác của card so với design (padding `product-info`, spacing price/swatch, …).

### Acceptance Criteria
- [x] AC1: Card có brand: brand hiện trên tên, font-size 14px, line-height 20px, weight 500, màu rgb(153,161,175), `text-transform: none`; tên cách brand 8px (±1).
- [x] AC2: Card không có brand: dòng brand trống cao 20px (`aria-hidden="true"`); top của tên bằng top của tên ở card có brand cùng hàng (±1).
- [x] AC3: Brand dài hơn chiều rộng card hiện 1 dòng với ellipsis, chiều cao 20px.
- [x] AC4: Homepage rails, Flash Sale, PLP grid: không h-scroll, 0 pageerror; chỉ `.hp-card-brand` và spacing tên thay đổi.

## Approach

1. `item.phtml`: luôn render `<span class="hp-card-brand">` (rỗng + `aria-hidden="true"` khi không có brand); wrapper tên luôn `mt-2`.
2. `homepage.css` `.hp-card-brand`: `block`, `min-h-5`, `truncate`; màu `var(--ds-text-gray-quinary)`; font-size / line-height / weight từ `--ds-type-label-m-*`.
3. Build Tailwind, `cache:flush`, verify Playwright Chrome 150 (AC1–AC4). Case có brand được verify bằng cách inject text vào `.hp-card-brand` trong trình duyệt (local không có data brand, không ghi DB).

## Progress

- 2026-09-25: phân tích `/task` + đo Figma `2151:17146`; user chốt: Admin tự cấu hình, giữ nguyên chữ, không brand thì để trống cho thẳng hàng. Record + Mini-Spec; bắt đầu implement.
- 2026-09-25: implement xong; verify Playwright Chrome 150 ALL PASS (41 check, AC1–AC4).

## Implementation

- `item.phtml`: `<span class="hp-card-brand">` luôn render; không có brand → rỗng + `aria-hidden="true"`. Wrapper tên luôn `mt-2` (bỏ `mt-1`/`mt-2` theo brand).
- `homepage.css` `.hp-card-brand`: `block min-h-5 truncate font-medium`, `color: var(--ds-text-gray-quinary)` (thay hex `#99a1af`), `font-size` / `line-height` từ `--ds-type-label-m-*`. Weight dùng `font-medium` vì token `--ds-type-label-m-font-weight` resolve ra `500px` (không hợp lệ).
- Tailwind: `npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify`. So với bản trước build, diff `styles.css` chỉ có rule `.hp-card-brand`. **Lưu ý khi commit:** `styles.css` và `homepage.css` đang có sẵn WIP của ticket khác (BUG-QJWCNG, flash head, hero filter), nên cần stage theo hunk. `cache:flush`.
- Không đổi DB, module, CHANGELOG (thay đổi chỉ ở theme).

## Validation

| Check | Kết quả | Evidence |
|---|---|---|
| Tailwind build, diff chỉ `.hp-card-brand` | PASS | — |
| AC1: 14px / 20px / 500 / rgb(153,161,175) / no transform; tên cách brand 8px | PASS (home 375/1440, PLP 375/1440, PLP list 1440) | `verify-brand.txt` |
| AC2: dòng trống 20px `aria-hidden`; tên cùng hàng lệch ≤ 0.02px | PASS | `verify-brand.txt`, `plp@1440-cards.png` |
| AC3: brand dài 1 dòng, ellipsis, cao 20px | PASS | `plp@1440-cards.png` (card 2) |
| AC4: không h-scroll, 0 pageerror | PASS | `verify-brand.txt` |

Case "có brand" được giả lập bằng cách ghi text vào `.hp-card-brand` trong trình duyệt (local không có data brand, không ghi DB). Luồng load thật (`getAttributeText('manufacturer')`) giữ nguyên code của TASK-0NNZCW, cần Admin cấu hình (Handoff) rồi kiểm lại trên staging.

## Ngoài scope (flag)

1. Token `--ds-global-typography-font-weight-strong: 500px` trong `generated/figma-design-tokens.css`: đơn vị sai, nên mọi `font-weight: var(--ds-type-*-font-weight)` bị bỏ qua (ví dụ `form.css` label). Lỗi ở token transformer / source, cần ticket riêng.
2. `item.phtml:145` còn comment debug `<!-- gallery-debug count=… err=… -->` render ra HTML của mọi card (có sẵn, không thuộc SLP-270).

## Handoff (Admin)

Để brand hiện trên card, Admin cần:
1. **Stores → Attributes → Product → `manufacturer`** → Storefront Properties → **Used in Product Listing = Yes** → Save.
2. **Stores → Attributes → Attribute Set**: kéo `manufacturer` vào các set đang dùng (Top, Bottom, Furniture, …).
3. Tạo option brand (Manage Options) và gán cho sản phẩm; flush cache (full page cache) nếu cần.
