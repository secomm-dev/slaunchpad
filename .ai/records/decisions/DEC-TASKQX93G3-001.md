---
id: DEC-TASKQX93G3-001
legacy_ids: []
title: 'Quick Cart drawer (SLP-158) — coupon đi AJAX controller riêng (module Launchpad_QuickCart); promo message = CMS block, defer free-shipping progress; qty stepper − / + và input, min 1'
status: accepted
owners: [tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-QX93G3]
---

# Decision Record: Quick Cart drawer implementation strategies (SLP-158)

<!-- ACCEPTED 2026-09-10 — approved by user acting as TL (chat AskUserQuestion sau "duyệt plan"; cả 3 chọn phương án Recommended). -->

## Context

SLP-158 yêu cầu extend Hyvä mini-cart drawer (qty/coupon/promo/CTA). Phân tích TASK-QX93G3 xác định drawer native đã có remove/subtotal/CTA/states; 3 quyết định mở là implementation strategies (Level 2). Coupon là path duy nhất chạm cart totals.

## Decision

1. **Coupon: AJAX controller riêng** trong module mới `Launchpad_QuickCart` (`quickcart/coupon/post`, delegate `CouponManagement` trên session quote, JSON `{success, error_message}` khớp contract `hyvaAjaxFormMinicart`). Loại: plugin core `couponPost` (gắn redirect behavior core), GraphQL (cartId guest/customer riêng, lệch mô hình session/form_key của drawer). Server-side revalidate native qua `CouponManagement`; thêm plugin `afterGetSectionData` trên `CustomerData\Cart` để expose `coupon_code` cho drawer state.
2. **Promo message: CMS block** identifier `cart-drawer-promo` per store view render vào container `cart-drawer.totals.before`. **Free-shipping progress: defer phase 2** — chờ business chốt nguồn threshold (config / Cart Price Rule / TableRate) trước khi build.
3. **Qty stepper: − / + kèm input số**, debounce ~500ms POST `checkout/sidebar/updateItemQty`, min=1 client clamp (bỏ → remove), max server validate (salable qty) → per-item error native.

## Consequences

- Module mới không schema; rollback = `module:disable` + revert theme files.
- E2E checkout regression bắt buộc trong test gate (coupon → totals feed OSC).
- Phase 2 mở: free-shipping progress (work item riêng khi business chốt threshold).
