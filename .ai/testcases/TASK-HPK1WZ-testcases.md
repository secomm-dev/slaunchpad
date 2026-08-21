# Test Cases: TASK-HPK1WZ — MaxDiscount QC manual matrix (FEAT-JKZM68)

**Source:** SPEC-FEAT-JKZM68 §15 AC-001..015 (nhóm chưa cover bởi TASK-4HYX6Y integration) · Ticket TASK-HPK1WZ
**Scope note (TL 2026-08-21):** e2e **không Mollie** — place order offline (checkmo). Vị trí field admin: TL duyệt **cuối Actions fieldset**.
**Engine-level đã cover ở TASK-4HYX6Y** (không lặp lại): AC-001..007, 012, 013, 014, phần lớn 010.

## Group 1 — Admin UX (AC-008/009)

### TC-001: Field hiển thị đúng vị trí + gating theo action
- **Precondition**: Admin > Marketing > Promotions > Cart Price Rules > New rule (vi_VN admin)
- **Steps**: 1. Mở tab Actions 2. Xem Apply = "Percent of product price discount" 3. Quan sát field "Số tiền giảm giá tối đa" (cuối Actions fieldset) 4. Đổi Apply → "Fixed amount discount" 5. Quan sát field
- **Expected Result**: by_percent → field visible + editable, có note "Giới hạn tổng giảm giá sản phẩm…"; action khác → field ẩn + disabled; form không lỗi (regression C1 TASK-67GGPR)
- **Priority**: High | **Type**: Functional

### TC-002: Validation ≥ 0 + số học
- **Precondition**: Rule edit, Apply = by_percent
- **Steps**: 1. Nhập "-5000" → save 2. Nhập "abc" → save 3. Nhập "50000.75" → save 4. Nhập "0" → save
- **Expected Result**: (1)(2) bị chặn client (validate-number, zero-or-greater); (3)(4) persist — 50000.75 lưu DECIMAL(12,4); 0 = unlimited
- **Priority**: High | **Type**: Negative

### TC-003: Round-trip NULL/empty + đổi action giữ giá trị (AC-009)
- **Precondition**: Rule by_percent + cap 50000 đã lưu
- **Steps**: 1. Edit rule — field hiển thị 50000 2. Xóa sạch field → save → mở lại 3. Đổi Apply → by_fixed → save → mở lại 4. Đổi về by_percent → mở lại
- **Expected Result**: (2) persist NULL (unlimited) — totals trở về native; (3)(4) giá trị trong DB không bị xóa khi field disabled (AC-3 TASK-67GGPR), calculation by_fixed không bị cap
- **Priority**: High | **Type**: Business Rule

### TC-004: Note + label i18n (BR-001)
- **Steps**: 1. Admin locale vi_VN → xem label + note 2. Switch en_US → xem lại
- **Expected Result**: "Số tiền giảm giá tối đa" / "Maximum Discount Amount" + note dịch đủ 2 locale
- **Priority**: Medium | **Type**: Functional

### TC-005: Regression form Actions tab (AC-015)
- **Steps**: 1. Mở rule có sẵn (sample "Half Price Sale") — mọi field native 2. Save không đổi gì
- **Expected Result**: Các field Discount Amount/Qty/Step/Free Shipping/Apply to Shipping/stop rules hoạt động + persist như trước module
- **Priority**: High | **Type**: Regression

## Group 2 — Storefront totals hiển thị

### TC-006: Cart + OSC hiển thị discount đã cap
- **Precondition**: Rule 20% cap 50.000 VND active; cart 2 items 2M/3M VND
- **Steps**: 1. Xem cart page 2. Vào OSC checkout 3. So Discount line vs tính tay (native 1.000.000 → 50.000)
- **Expected Result**: Cart + OSC Discount = −50.000; grand total trừ đúng 50.000; Σ line discount gộp == 50.000
- **Priority**: High | **Type**: Functional

### TC-007: Breakdown per-rule qua totals API/GraphQL phản ánh cap
- **Steps**: 1. `GET rest/V1/carts/totals` (guest masked quote) hoặc GraphQL `cart { discounts }` 2. So per-rule amount
- **Expected Result**: Entry rule capped == cap (50.000) trên base + display; rule uncapped khác (nếu có) giữ native amount (C1 TASK-5H8WKE)
- **Priority**: High | **Type**: Integration

### TC-008: Shipping discount không bị cap (AC-010)
- **Precondition**: Rule by_percent cap 50k + "Apply to Shipping" = Yes + free-shipping benefit
- **Steps**: 1. Cart có shipping method 2. So shipping discount line vs cap
- **Expected Result**: Shipping discount giữ nguyên native (ngoài cap, không cộng vào N); product discount bị cap
- **Priority**: High | **Type**: Business Rule

## Group 3 — Edge merchant

### TC-009: Cap nhỏ bất thường (1 VND) + cap > native
- **Steps**: 1. Cap = 1 → totals discount −1 (LRM integer) 2. Cap = 999.999.999 (> native) → identical native
- **Expected Result**: (1) đúng 1 VND; (2) no-op byte-identical totals (AC-002/003)
- **Priority**: Medium | **Type**: Edge Case

### TC-010: Đổi cap giữa 2 phiên checkout
- **Steps**: 1. Customer A ở checkout với cap 50000 2. Admin đổi cap → 200000 save 3. Customer A reload totals
- **Expected Result**: Recollect phản ánh cap mới 20k-đúng-contribution; không cache stale
- **Priority**: Medium | **Type**: Edge Case

### TC-011: Rule auto + coupon song song (AC-014)
- **Precondition**: R1 auto cap 50k + R2 coupon cap 30k
- **Steps**: 1. Chỉ R1 → 50k 2. Apply coupon R2 → R1 50k + R2 30k độc lập
- **Expected Result**: Mỗi rule ≤ cap riêng; coupon semantics native
- **Priority**: High | **Type**: Business Rule

### TC-012: Mageplaza ExtraFee tương tác totals
- **Steps**: 1. Cart có ExtraFee + capped rule 2. So grand total = subtotal − cap + fee + shipping
- **Expected Result**: Fee chạy sau collector 310 thấy số đã cap; không drift
- **Priority**: Medium | **Type**: Integration

## Group 4 — OSC e2e không Mollie (Tier-2 checklist)

### TC-013: E2E capped coupon → place order offline
- **Steps**: 1. Add 2 items + apply capped coupon 2. OSC qua các bước (address VN cascade BR-002) 3. Reload trang checkout ×3 giữa chừng 4. Place order **checkmo** 5. Mở order admin
- **Expected Result**: (3) totals không drift sau reload lặp (AC-012 UI); (4) place order OK; (5) order item discount == quote capped (20k/30k), order Discount Total −50k
- **Priority**: High | **Type**: E2E

### TC-014: Regression OSC không capped rule (AC-015)
- **Steps**: 1. Cart KHÔNG rule capped (coupon thường / không coupon) 2. Full checkout flow
- **Expected Result**: Hành vi như baseline trước module (so screenshot/totals trước merge)
- **Priority**: High | **Type**: Regression

### TC-015: Module disable → totals native
- **Steps**: 1. `bin/magento module:disable Secomm_PromotionMaxDiscount` 2. Xem lại cart có rule capped
- **Expected Result**: Totals trở về native (collector rời chuỗi); re-enable → cap hoạt động lại (limitation disclosed TASK-5H8WKE — QC verify lần đầu runtime)
- **Priority**: Medium | **Type**: Regression

---
*Composite (configurable/bundle) gap từ TASK-4HYX6Y: nếu QC env có configurable thật, chạy thêm TC-006 variant với configurable — kiểm tra order-item copy kỹ (xem evidence 4HYX6Y ghi chú kỹ thuật).*
