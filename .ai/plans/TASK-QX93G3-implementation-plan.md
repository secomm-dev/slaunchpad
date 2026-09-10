# Kế hoạch triển khai: TASK-QX93G3 — Quick Cart drawer enhancement

| Field | Value |
|---|---|
| Specification | Embedded Mini-Spec — [TASK-QX93G3](../records/tasks/TASK-QX93G3.md) (VALID) |

> Mode B | Tier 1 (TL duyệt approach + 3 decisions qua chat 2026-09-10 → DEC-TASKQX93G3-001).
> **Status: Approved — "duyệt plan" (user, 2026-09-10). Không được xem implementation là complete trước QC.**
> Ticket: SLP-158 (LA-02) | Record: [TASK-QX93G3](../records/tasks/TASK-QX93G3.md) | Decision: [DEC-TASKQX93G3-001](../records/decisions/DEC-TASKQX93G3-001.md)

## Metadata

| Field | Value |
|---|---|
| Workflow mode | B |
| Risk | medium (coupon → cart totals path; không đụng OSC flow/payment/shipping logic/DB) |
| Decisions | DEC-TASKQX93G3-001 (coupon AJAX controller riêng; promo = CMS block, defer freeship; qty stepper − / + và input min 1) |
| Reviewer | TL (code review) → QC |
| Validation level | L2 component (drawer + module); L3 E2E checkout regression sau coupon (test gate, không phải code change) |
| Date | 2026-09-10 |

## 1. Hướng tiếp cận

Extend Hyvä native cart drawer (dialog-based, 1.5.2) theo 3 lớp, tận dụng tối đa những gì native đã có (remove/subtotal/CTA/loading/error/empty states — không đụng):

1. **Theme override** `Magento_Theme/templates/html/cart/cart-drawer.phtml` — verbatim-copy + minimal diff: chỉ thêm qty stepper trong item loop (markup + 3 method Alpine, reuse `hyvaAjaxFormMinicart` + endpoint core `checkout/sidebar/updateItemQty` — handler `updateItemQty()` vendor đã có sẵn nhưng không markup gọi) + 3 method Alpine cho stepper.
2. **Module mới** `Launchpad_QuickCart` — AJAX coupon endpoint `quickcart/coupon/post` (form_key validate → delegate `CouponManagement` trên session quote → JSON `{success, error_message}` khớp contract `hyvaAjaxFormMinicart`) + plugin `afterGetSectionData` trên `Magento\Checkout\CustomerData\Cart` thêm `coupon_code` vào cart section (để drawer biết trạng thái applied across page loads).
3. **Theme layout + coupon template** — `Magento_Theme/layout/default.xml` (child theme, merge additive) reference container `cart-drawer.totals.before` thêm 2 block: coupon form (block `Magento\Checkout\Block\Cart\Coupon` + template mới `coupon-form.phtml`, JS fetch riêng — KHÔNG route qua `hyvaAjaxFormMinicart` vì error path của nó gắn `item_id`) + CMS block `cart-drawer-promo` (render `''` khi chưa có — verified `Cms/Block/Block.php:69-84`).

Không dùng `hyva.postCart` cho drawer (chỉ tồn tại trên cart page, replace `#maincontent`). Không sửa Mageplaza/vendor in-place. Mageplaza_QuickCart giữ disabled.

### Alternatives đã loại

- Plugin core `couponPost` trả JSON khi AJAX — gắn behavior redirect của core, dễ vỡ khi upgrade (loại theo DEC-TASKQX93G3-001).
- GraphQL `applyCouponToCart` — cần cartId guest/customer riêng, nặng hơn mô hình session/form_key của drawer.
- Inline coupon + promo trong template override — tăng diff template (upgrade drift); container + layout giữ diff tối thiểu.
- Free-shipping progress — defer phase 2 (nguồn threshold chưa chốt).

## 2. Files affected

| File | Thay đổi | AC |
|---|---|---|
| `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/cart/cart-drawer.phtml` | Override mới — verbatim + qty stepper (markup + `changeQty`/`submitQty`/debounce) | AC-001, 002, 003, 006, 007 |
| `app/design/frontend/Secomm/launchpad/Magento_Theme/layout/default.xml` | Merge — reference `cart-drawer.totals.before`: coupon block + CMS promo block | AC-004, 005 |
| `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/cart/coupon-form.phtml` | Template mới — input + Apply/Cancel, fetch `quickcart/coupon/post`, inline error, chip applied | AC-004 |
| `app/code/Launchpad/QuickCart/registration.php` + `etc/module.xml` | Module mới (requires Magento_Checkout, Magento_Quote) | AC-004 |
| `app/code/Launchpad/QuickCart/etc/frontend/routes.xml` | frontName `quickcart` | AC-004 |
| `app/code/Launchpad/QuickCart/Controller/Coupon/Post.php` | AJAX endpoint — form_key → `CouponManagement::set/remove` session quote → JSON | AC-004 |
| `app/code/Launchpad/QuickCart/etc/frontend/di.xml` + `Plugin/CustomerData/CartSectionCouponCode.php` | Plugin thêm `coupon_code` vào cart section | AC-004 |
| `app/code/Launchpad/QuickCart/README.md` + `CHANGELOG.md` | Ship cùng module ([WARN] §7.2) | — |
| `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` + `en_US.csv` | +aria-label stepper (reuse key coupon có sẵn từ SLP-128/133) | AC-007 |
| `app/etc/config.php` | Regen bởi `setup:upgrade` (module mới, không schema) | — |

## 3. Steps

1. **Module** `Launchpad_QuickCart`: registration, module.xml, routes, controller, plugin, di, README/CHANGELOG → `setup:upgrade` as secomm (không schema).
2. **Theme**: override `cart-drawer.phtml` (qty stepper), layout `default.xml`, template `coupon-form.phtml`.
3. **i18n**: +key aria stepper cả 2 CSV; verify reuse key coupon đã có.
4. **Build**: Tailwind rebuild (`web/tailwind/`) + grep CSS rule mới + `cache:flush`.
5. **Verify** (Playwright, vi/en): AC-001→007; E2E checkout regression sau coupon-from-drawer (guest + logged-in); evidence `.ai/evidence/TASK-QX93G3/`.
6. **Pre-review** → TL review → QC.

## 4. Risks & rollback

- Layout merge `default.xml` — smoke test header/pager default elements sau merge (gotcha BUG-5S2Z25 là handle file khác, theo dõi).
- Rollback: remove module (`module:disable`) + revert theme files — không schema, không data.
