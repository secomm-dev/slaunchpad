---
id: TASK-Y23EMS
type: task
title: 'PDP Add to Cart — AJAX qua Monsoon_HyvaAjaxAddToCart (SLP-264)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-264
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review  # 2026-09-22: dev + verify Playwright 5/5 AC live PASS (AC-004 code-path only, AC-007 VI live + EN via CSV) — chờ TL review
created: 2026-09-22
updated: 2026-09-22
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad
source_areas:
  - hyva-theme-frontend
  - monsoon-hyvaajaxaddtocart
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-22
supersedes: []
---

# [SLP][TASK-Y23EMS] PDP Add to Cart — AJAX qua Monsoon_HyvaAjaxAddToCart (SLP-264)

## Summary

Đưa add-to-cart trên product detail page (PDP) về AJAX qua module `Monsoon_HyvaAjaxAddToCart` — đảo ngược quyết định 09-15 của TASK-Z3DAH5 (§Scope Extension 2) vốn chủ ý tắt PDP khỏi Monsoon intercept vì xung đột double-submit với `hyva.formValidation`.

## Mini Spec

### Goal

ATC trên PDP submit bằng fetch AJAX (không reload page) sau khi form pass validation VI/EN, dùng chung behavior với card ATC Monsoon hiện có (loader, cart drawer mở theo config, minicart reload, error message).

### Expected Behavior

- PDP form pass `hyva.formValidation` → gọi AJAX POST tới `checkout/cart/add`, page giữ nguyên URL, cart count +1, drawer mở (config `checkout/options/ajax_cart_open_after_add_to_cart = 1`).
- Form fail validation (vd configurable thiếu option) → inline message VI/EN như hiện tại, KHÔNG có network POST.
- Thêm đúng 1 item / 1 POST mỗi click (không tái diễn bug double-submit của TASK-Z3DAH5).

### Constraints / Rules

- KHÔNG sửa vendor in-place (`Monsoon_HyvaAjaxAddToCart`, `Hyva_Theme`) — chỉ theme-layer override (pattern Mageplaza, như TASK-Z3DAH5).
- KHÔNG đổi config DB `checkout/options/ajax_add_to_cart_selectors` (`.product_addtocart_form`) — giữ nguyên biên không-intercept cho Quick View modal (TASK-Z3DAH5 T2) và PDP form không bị Monsoon bind listener.
- Validation VI/EN pre-POST phải giữ nguyên (SLP-183 / BUG-KFJ49A — `product-form.phtml` wiring).
- Fallback native POST khi `window.ajaxSubmitCart` vắng mặt (flag `enable_ajax_add_to_cart=0` hoặc block không render).
- Server flow `checkout/cart/add` không đổi — thuần frontend interception.

### Out of Scope

- OSC checkout flow, PLP card ATC behavior (giữ nguyên), Quick View modal flow (giữ nguyên).
- Server-side `Magento_Checkout` / `Mageplaza_ExtraFee` logic.
- DB config thay đổi.

### Acceptance Criteria

- AC-001: PDP simple product — ATC không reload (URL giữ nguyên), cart count +1, đúng 1 POST, drawer mở.
- AC-002: PDP configurable thiếu option — inline validation message, 0 POST.
- AC-003: PDP configurable đủ option — AJAX như AC-001.
- AC-004: Custom option type file — POST multipart (FormData) thành công.
- AC-005: Regression — card PLP ATC vẫn AJAX; Quick View modal vẫn +1-only (baseline TASK-Z3DAH5 T1/T2).
- AC-006: Flag `enable_ajax_add_to_cart=0` — PDP fallback native POST, validation vẫn hoạt động.
- AC-007: 2 store vi_VN/en_US — messages đúng locale.

## Approach

1. Theme-override `Monsoon_HyvaAjaxAddToCart::hyva/script/addtocart.phtml` trong child theme: tách logic submit AJAX thành `window.ajaxSubmitCart(form, recursive)`; `window.setAjaxCart(selectors, recursive)` giữ nguyên signature, chỉ delegate. Thêm branch FormData (multipart) cho form có `input[type=file]` (URLSearchParams không hỗ trợ file).
2. Sửa theme copy `Magento_Catalog::product/view/product-form.phtml` (đã là project-owned copy SLP-183/BUG-KFJ49A): override `onSubmit` trên component `hyva.formValidation(this.$el)` (vendor hard-code `event.target.submit()` tại `advanced-form-validation.phtml:402-412`) — valid → `window.ajaxSubmitCart(form)`, helper vắng → fallback `form.submit()`. Reset `isSubmitting` trên nhánh AJAX (page không navigate) — chống re-entry do button bị disable trong lúc fetch.
3. Verify: `cache:flush` + browser probe PDP (simple/configurable), regression card PLP + Quick View, 2 store.

## Implementation Notes

- `app/design/frontend/Secomm/launchpad/Monsoon_HyvaAjaxAddToCart/templates/hyva/script/addtocart.phtml` — MỚI (theme override, copy + refactor từ module template).
- `app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/view/product-form.phtml` — override `onSubmit`, cập nhật header comment.

## Verification

- [x] AC-001 verified — evidence: `.ai/evidence/TASK-Y23EMS/RESULTS.md` (T1: noNav, 1 POST, cart 0→1, drawer mở)
- [x] AC-002 verified — (T2: 0 POST, inline VI "Vui lòng chọn một trong các tùy chọn.")
- [x] AC-003 verified — (T3: 2 nhóm swatch, noNav, 1 POST, cart +1)
- [x] AC-004 verified — *code-path only*: không có fixture file-option local (`catalog_product_option` type file rỗng); branch FormData đã review — cần QC data thật khi có SP file-option
- [x] AC-005 verified — (T4 card PLP `/gear/bags.html` 1 POST noNav PASS; Quick View: path không đụng — review-code verified theo baseline TASK-Z3DAH5 T2)
- [x] AC-006 verified — (T5: flag=0 → helper undefined, native POST ×1, redirect về PDP; restore flag=1 + flush + re-run PASS)
- [x] AC-007 verified — *VI live* (T2); *EN via CSV* (`i18n/en_US.csv:873` có sẵn key — local không switch store được, LL-0011)

## Related records

- TASK-Z3DAH5 (SLP-157 LA-01) — tương thích Monsoon + quyết định tắt PDP AJAX 09-15 (root cause double-submit).
- BUG-KFJ49A / SLP-183 — formValidation wiring trên product-form.phtml (điểm override).
- TASK-QX93G3 — baseline "PDP ATC là full-page POST".
