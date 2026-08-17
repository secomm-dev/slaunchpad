# Feature Spec — Admin VN 2-level address dropdown trên order form (SL-012)

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho ticket SL-012 (create + edit order address). Parent: FEAT-007. Mode A · Tier-2 (order/PII). -->
<!-- Decisions: DEC-025 (global mechanism + country adapter; sub_city generic 3rd level) · DEC-020 (ward = city level, name-string persistence tech-debt) · DEC-019 (generic Magento-default; VN → VietNamAddress) · DEC-17 (VN capability lives in VietNamAddress). -->
<!-- Note: admin order form là block-based `Magento_Sales`, không phải `ui_component` như customer address modal; approach wiring vẫn cần chốt trong implementation plan. -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Tier-2 — order/PII (AGENTS.md §9 L397-L398, AddressDropdown L421) → Mode A, escalate TL.

## Feature Overview

**Feature name**: Apply VN 2-level address dropdown to admin order create/edit address forms

**Ticket reference**: [SL-012](../tickets/SL-012-apply-address-dropdown-admin-order-form.md) · Parent [FEAT-007](../records/features/FEAT-007.md) · extends CMP-ADDR [FEAT-001](../records/features/FEAT-001.md)

**Feature type**: New admin surface

**Priority**: P2 / Medium-High

**Mode / Risk**: A · Tier-2 (chạm order + customer/PII data)

## Scope note

FEAT-007 pin architecture cho mọi admin address surface. Spec này chỉ bao phủ **SL-012**: admin **order create** (billing + shipping) và **order address edit**. Các surface khác có spec riêng: customer address (SL-011), Store Information + Shipping Origin (SL-013), MSI Source (SL-014).

---

## User Stories

- **US-001**: As an admin, I want VN order billing address to show province → ward dropdown so that I can enter the correct ward instead of free-typing a wrong or missing value.
- **US-002**: As an admin, I want VN shipping address to mirror billing behavior so that the "Same as billing" flow keeps region and ward in sync.
- **US-003**: As a maintainer, I want VN behaviour isolated in `Secomm_VietNamAddress` and the generic cascade left country-agnostic so that the order form follows DEC-019/025 and does not leak VN logic into the generic module.

## Acceptance Criteria

> Mỗi AC = một test case. AC-1..AC-8 bám theo ticket.

- [ ] **AC-1 (Admin Order Create — billing):** Khi tạo order mới và chọn `country = VN`, form billing render cascade `country → region → city` trong đó `city` là ward dropdown; giá trị submit đúng vào `order[billing_address][city]` dưới dạng ward name.
- [ ] **AC-2 (Admin Order Create — shipping):** Khi tạo order mới và chọn `country = VN`, form shipping render cascade giống billing; checkbox **"Same as billing"** copy đầy đủ `region` + `city` (ward) sang shipping, không làm rơi ward.
- [ ] **AC-3 (Admin Order Address Edit):** Khi mở edit cho một order đã lưu, form render cascade và pre-select đúng `region` + `city` (ward) đã persist trước đó; hydrate lại không reset ward.
- [ ] **AC-4 (Non-VN fallback):** Với country không phải `VN`, admin order form giữ hành vi Magento default; không render behavior VN-specific và không ép dropdown ward.
- [ ] **AC-5 (Persist):** Khi save từ admin order path, `city` (ward) persist vào `sales_order_address`; flow tạo order cũng giữ parity với `quote_address` để dữ liệu billing/shipping không lệch.
- [ ] **AC-6 (Validation):** Với `country = VN`, `city` là bắt buộc và `region` vẫn là bắt buộc như hiện tại; payload invalid phải bị chặn hoặc neutralise theo save path hiện có, không tạo order address sai ward.
- [ ] **AC-7 (Display):** Order view, PDF, email và frontend order detail hiển thị region + ward đúng cho địa chỉ VN, không mất ward sau khi lưu.
- [ ] **AC-8 (Regression):** Storefront checkout/OSC, cart estimate, customer address book, admin data CRUD, và existing order save flow không bị regress sau khi áp dụng dropdown mới.

## Technical Notes

- Admin order create/edit là **block-based `Magento_Sales` flow**. Đây không phải `ui_component` giống customer address modal, nên không thể port 1:1 các hook của SL-011.
- Nên reuse option provider `Model\Customer\Address\Config\Selector\City` để lấy ward theo `region_id`; data VN vẫn đi qua `Secomm_AddressDropdown` / `Secomm_VietNamAddress`, không duplicate master data.
- `Secomm_VietNamAddress` nên sở hữu label VN và ward behavior khi `country == 'VN'`; generic `Secomm_AddressDropdown` giữ cascade country-agnostic theo DEC-025.
- Persist path cần giữ parity cho `sales_quote_address` và `sales_order_address`; `same as billing` phải copy cả `city` (ward), không chỉ region.
- `sub_city` giữ nguyên như generic 3rd level cho non-VN country có data 3 cấp; VN 2-level không render `sub_city`.
- Wire-in path cụ thể chưa chốt: candidate chính là layout override cho `sales_order_create_index.xml` / `sales_order_view.xml` kết hợp JS hook cho order-address fields; lựa chọn cuối cùng cần chốt trong implementation plan.

### Files / Areas Affected

- **NEW**: `view/adminhtml/layout/sales_order_create_index.xml` — inject cascade vào order create form.
- **NEW**: `view/adminhtml/layout/sales_order_view.xml` — inject cascade vào order address edit view.
- `view/adminhtml/web/js/form/provider-mixin.js` — mở rộng selector sang `#order-billing_address_*` và `#order-shipping_address_*` hoặc thay bằng order-specific JS mới.
- `Observer/Order/Address/SaveSubCity.php` và `Observer/Quote/Address/SaveSubCity.php` — verify/extend admin submit path để persist ward đúng.
- `Model/Customer/Address/Config/Selector/City.php` — reuse ward option provider.
- `Secomm_VietNamAddress/` — label ward và VN-specific behavior.
- **KHÔNG affect**: storefront `view/frontend/*`, schema migration, customer-address admin flow, Store Information / MSI / other admin surfaces.

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| [DEC-025](../records/decisions/DEC-025.md) | decision | accepted | Global mechanism + country adapter; `sub_city` generic |
| [DEC-020](../records/decisions/DEC-020.md) | decision | accepted | Ward = city level; name-string persistence tech-debt |
| [DEC-019](../records/decisions/DEC-019.md) + DEC-17 | decision | accepted | Generic vs VN boundary |
| [FEAT-007](../records/features/FEAT-007.md) | feature | proposed | Parent architecture cho SL-011..014 |
| [SL-011](../tickets/SL-011-apply-address-dropdown-admin-customer-form.md) | ticket | proposed | Shared selector + VN adapter pattern |
| [SL-013](../tickets/SL-013-apply-address-dropdown-admin-store-information.md) | ticket | proposed | Shared ward source/label logic |
| [SL-014](../tickets/SL-014-apply-address-dropdown-admin-msi-source.md) | ticket | proposed | Shared ward source/label logic |
| [SL-003](./vn-cart-shipping-estimate.md) / [FEAT-005](../records/features/FEAT-005.md) | feature | proposed | Precedent cho `ValidateVietNamWard` + ward dropdown behavior |

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Order create form cơ chế khác customer modal nên hook sai điểm | H | H | Chốt wiring trong implementation plan; QC cả create và edit |
| "Same as billing" copy làm rơi ward nếu JS không hook đầy đủ | M | H | Test riêng flow same-as-billing trên billing và shipping |
| Persist lệch giữa `quote_address` và `sales_order_address` | M | H | Verify save path song song cho quote/order address |
| Display path không đi qua formatter chuẩn nên ward bị truncate | M | M | QC view/PDF/email/frontend order detail |
| Tier-2 order/PII data sai persist | L | H | TL review bắt buộc + end-to-end QC |

## Out of Scope

- Customer-address admin flow (`SL-011`).
- Store Information + Shipping Origin config (`SL-013`).
- MSI Source form (`SL-014`).
- Storefront generic cleanup / VN leak cleanup (`SL-007`).
- Canonical `ward_id` / `city_id` persistence path A (DEC-020 long-term follow-up).
- DB schema changes (`sub_city` column và infra giữ nguyên).

## Test Notes

- **Admin Order Create — billing:** `country = VN` → province → ward cascade hiện đúng; submit lưu ward vào `order[billing_address][city]`.
- **Admin Order Create — shipping:** same-as-billing copy giữ nguyên ward.
- **Admin Order Address Edit:** pre-select đúng region + ward từ data đã lưu.
- **Non-VN:** vẫn là Magento default, không có VN label/behavior rò rỉ.
- **Persist:** verify `sales_order_address.city` và parity `sales_quote_address`.
- **Display:** check order view, PDF, email, frontend order detail.
- **Regression:** checkout/OSC/customer address/admin CRUD không hỏng.

## Open Questions

> Mode A → architecture/scope unknowns cần SA/TL chốt trước implementation.

- **Q1 (SA/TL):** Cơ chế wire vào order form nên là `sales_order_create_index.xml` override, JS provider-mixin riêng cho order fields, hay layout/plugin khác?
- **Q2 (SA):** Label ward cho admin order form có dùng đúng source label từ `Secomm_VietNamAddress` hay cần fallback generic "City" cho non-VN?
- **Q3 (SA/TL):** Save path ưu tiên observer nào để bảo đảm ward persist đúng cho cả `quote_address` và `sales_order_address`?
