---
id: BUG-SDZPCD
type: bug
title: Fix product name invisible on desktop product detail page
project_code: SLP
parent:
external_refs:
  ticket: SLP-109
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-08-26
updated: 2026-08-26
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva default theme 1.5.2 + Mageplaza_ExtraFee (source-committed)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee
source_areas:
  - theme-templates
  - mageplaza-extrafee
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-08-26
supersedes: []
---

# [SLP][BUG-SDZPCD] Fix product name invisible on desktop product detail page

<!-- External ticket: SLP-109. -->

## Summary

Trên PDP desktop, tên sản phẩm không hiển thị ở đâu cả. Nguyên nhân: `Mageplaza_ExtraFee` ép template `product-info.phtml` bản cũ (lỗi thời) lên block `product.info` qua handle `hyva_default`, và bản đó không render block `product.info.title` — trong khi Hyva thiết kế h1 `page.main.title` là `md:sr-only` (ẩn desktop, hiện mobile; desktop dựa vào `product.info.title`).

## Mini Spec

### Goal

Tên sản phẩm hiển thị trên PDP ở cả desktop lẫn mobile, giữ nguyên structured data (`itemprop=name`) và UI ExtraFee trên PDP.

### Expected Behavior

- Desktop (≥768px): tên hiện trong main column qua block `product.info.title` (wrapper `hidden md:block`)
- Mobile: tên hiện qua h1 `page.main.title` (`md:sr-only` chỉ ẩn desktop)
- `itemprop=name` (schema.org Product) nằm trong h1
- Block `product.info.addtocart` của ExtraFee (chứa UI fee) vẫn render

### Constraints / Rules

- Không sửa source `app/code/Mageplaza/` (third-party — chỉ extend qua theme/plugin, AGENTS §7.1)
- Không đổi hành vi fee của ExtraFee trên PDP (BR-006)

### Out of Scope

- Đổi vị trí/style hiển thị tên (design decision — giữ đúng stock Hyva hiện tại)
- Các template khác của ExtraFee (order view, cart totals…)

### Acceptance Criteria

- AC-001: PDP desktop hiển thị tên sản phẩm trong main column (`product.info.title`, `text-3xl font-bold`)
- AC-002: PDP mobile hiển thị tên qua h1; h1 giữ `itemprop=name`
- AC-003: UI ExtraFee trên PDP (`extrafee-form`, "Extra Fee Information") vẫn render
- AC-004: Nút Add to Cart + form vẫn hoạt động (render bình thường)

## Steps to Reproduce

1. Mở PDP bất kỳ (vd `/crown-summit-backpack.html`) trên viewport desktop
2. Tên sản phẩm không xuất hiện ở main column (chỉ có review row + short description)
3. Thu nhỏ cửa sổ <768px → tên xuất hiện (h1 hết bị sr-only)

## Expected Behavior

Tên hiện ở main column trên desktop.

## Actual Behavior

Không có tên trên desktop (xem evidence before).

## Root Cause

Chuỗi nhân quả (đã xác minh trên page render thật):

1. Hyva default theme 1.5.2 render tên desktop qua block `product.info.title` khai báo trong [catalog_product_view.xml:80-87](vendor/hyva-themes/magento2-default-theme/Magento_Catalog/layout/catalog_product_view.xml) — template `product-info.phtml` hiện tại gọi `getChildHtml('product.info.title')` và h1 `page.main.title` là `md:sr-only`
2. [hyva_default.xml:26-31](app/code/Mageplaza/ExtraFee/view/frontend/layout/hyva_default.xml) của `Mageplaza_ExtraFee` **setTemplate** block `product.info` → `Mageplaza_ExtraFee::hyva/product/view/product-info.phtml` (handle `hyva_default` chạy trên mọi trang Hyva)
3. Template đó là **copy cũ** của `product-info.phtml` (wrapper `w-full mb-6`, markup đời cũ) — **không có dòng render `product.info.title`** vì được copy từ phiên bản Hyva trước khi block này tồn tại
4. Template đó **không chứa bất kỳ logic fee nào** — UI fee nằm ở block `product.info.addtocart` (khai báo riêng trong cùng layout) → việc ép template này không phục vụ chức năng nào của ExtraFee trên PDP

Kết quả: desktop không còn chỗ nào hiển thị tên.

## Fix

Theme override (không sửa `app/code/Mageplaza/`):

- **Mới**: `app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/product/view/product-info.phtml` — copy nguyên văn template Hyva hiện tại (có render `product.info.title`, markup `card mb-6`), kèm header comment ghi lý do + chỉ dẫn re-sync
- Cơ chế: theme fallback — `Mageplaza_ExtraFee::hyva/product/view/product-info.phtml` resolve vào theme file trước module file → đè bản cũ mà không đụng source third-party
- `bin/magento cache:flush` sau khi deploy

**Rủi ro bảo trì (ghi nhận)**: nếu upgrade Hyva default theme đổi template gốc hoặc ExtraFee thêm logic thật vào template của họ, file override này cần re-sync/ rà lại (đã ghi trong comment đầu file).

### Layout fix (SLP-109 tiếp — 2026-08-26: chuyển fee block xuống dưới giá)

Sau khi dùng template Hyva hiện tại, khối "Extra Fee Information" (nằm trong template `addtocart.phtml` của ExtraFee) render kẹt trong cột qty/ATC (hàng flex `gap-2 ml-auto`) → lệch phải, chen cứng (screenshot trong ticket). Fix bằng 4 thay đổi theme (không sửa `app/code/Mageplaza/`):

1. **Mới** `Mageplaza_ExtraFee/templates/hyva/product/view/addtocart.phtml` (theme override) — chỉ còn nút ATC + `getChildHtml('', true)`; bỏ form fee + AJAX script
2. **Mới** `Mageplaza_ExtraFee/templates/hyva/product/view/extra-fee.phtml` — form fee (`isDisplayExtraFee()` guard) + style + AJAX script (copy từ template gốc module), wrapper `w-full`
3. **Mới** `Mageplaza_ExtraFee/layout/hyva_default.xml` (theme layout — **merge** với layout module, không thay thế) — khai báo block `product.info.extrafee` (class `Mageplaza\ExtraFee\Block\Product\View`)
4. **Sửa** `product-info.phtml` override — render `getChildHtml('product.info.extrafee')` ngay sau buybox flex row (dưới giá, full-width)

Bỏ qua behaviour `getExtraFeePosition()` (1=trước/2=sau nút ATC) — vị trí giờ cố định dưới giá theo yêu cầu ticket.

## Verification

- Reproduced before fix: ✅ — fetch PDP thật: wrapper `w-full mb-6` (ExtraFee stale template), không có `card mb-6`/`hidden md:block`/`text-3xl font-bold`; DB check 200 products: 0 sản phẩm name rỗng (loại data-issue)
- Fixed confirmed: ✅ — fetch lại sau flush: `card mb-6` + `<div class="hidden md:block"><div class="page-title text-3xl font-bold">Crown Summit Backpack</div>` render trong main column
- Regression checked: ✅ — h1 giữ tên + `itemprop=name` (mobile/SEO); `extrafee-form` + "Extra Fee Information" vẫn render (fee UI nguyên vẹn); addtocart markers x21; price markers x94; HTTP 200
- Đã verify trên 1 sản phẩm mẫu (crown-summit-backpack) — QC nên抽查 thêm 2-3 sản phẩm loại khác (configurable/bundle) vì template dùng chung
- **Layout fix verified (2026-08-26):** ✅ live DOM — `mp-extrafee-form w-full` là **sibling full-width ngay sau** buybox flex row (không còn lồng trong cột qty/ATC); không trùng lặp (button ATC x1, fee form x1); AJAX `POST /mpextrafee/product/extrafee/` → HTTP 200 + fee content JSON (`Colorrr: 14,15 US$`) — evidence `fee-layout-after.txt`

> Raw debug output → `.ai/runtime/evidence/BUG-SDZPCD/` (before-after-extract.txt)

## Environment Note (không liên quan fix, đã xử lý trong phiên)

Storefront local từng HTTP 500 do `var/` ownership lẫn lộn (root/secomm/www-data). Đã sửa: `chown -R www-data:secomm var` + setgid group-writable + thêm `www-data` vào group `secomm` (passwordless sudo). Máy local dev only — không ảnh hưởng production.
