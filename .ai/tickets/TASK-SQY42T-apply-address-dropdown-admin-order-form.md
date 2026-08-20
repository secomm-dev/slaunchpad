# TASK-SQY42T — Apply VN 2-level address dropdown on admin order forms (create + edit order address)

**Legacy ID:** SL-012 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Feature (new admin surface)
**Priority:** Medium-High
**Estimate:** ~12–20h
**Mode:** A (chạm order + customer/PII data → Tier-2)
**Feature:** [FEAT-E2HM1J](../records/features/FEAT-E2HM1J.md) (parent — admin VN dropdown architecture) — extends CMP-ADDR ([FEAT-YVN39K](../records/features/FEAT-YVN39K.md)); related [FEAT-JSZQV3](../records/features/FEAT-JSZQV3.md)
**Spec ref:** [admin-vn-address-order-form.md](../specs/SPEC-TASK-SQY42T-admin-vn-address-order-form.md) (mini-spec — Mode A; approach chưa chốt — xem Open Questions)
**Risk tier:** Tier 2 (order management §9 L397 + customer data/PII L398; AddressDropdown L421)
**Author:** AI draft · **Date:** 2026-08-03 · **Status:** Proposed
**Related:** [TASK-4ZV5NG](TASK-4ZV5NG-apply-address-dropdown-admin-customer-form.md) (customer) · [TASK-8WSERX](TASK-8WSERX-apply-address-dropdown-admin-store-information.md) (store info) · [TASK-2V0AEV](TASK-2V0AEV-apply-address-dropdown-admin-msi-source.md) (MSI source)

## Model (DEC-FEATE2HM1J-001 — data-driven levels)

VN = **2 cấp**: `region` (Tỉnh/Thành) → `city` (Phường/Xã = ward); VN adapter không render `sub_city`. **`sub_city` = generic 3rd level (KHÔNG deprecate)** — non-VN 3 cấp vẫn dùng (DEC-FEATE2HM1J-001). VN logic ở `Secomm_VietNamAddress`; generic country-agnostic (DEC-FEATJSZQV3-003). Architecture: [DEC-FEATE2HM1J-001](../records/decisions/DEC-FEATE2HM1J-001.md).

## Description

Admin **ORDER form** — Order Create (billing + shipping) + order address edit — **chưa có cascade**: không layout override `sales_order_create`/`sales_order_view`, [provider-mixin.js](../../app/code/Secomm/AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js) chỉ target customer-address modal. Hệ quả: admin tạo/sửa order chọn country/region qua default, gõ `city` text tự do, **không có ward dropdown** → ward thiếu/sai trên order address nhập tay.

> Backend đã có nền tảng: observers `sales_quote_address_save_before` + `sales_order_address_save_before` ([events.xml:13-19](../../app/code/Secomm/AddressDropdown/etc/events.xml#L13-L19)) + cột trên `sales_order_address`/`quote_address` ([db_schema.xml:11](../../app/code/Secomm/AddressDropdown/etc/db_schema.xml#L11)). **Chỉ thiếu UI field + cascade.** (`sub_city` observer giữ generic cho non-VN 3-level; VN order 2-level không submit `sub_city` — DEC-FEATE2HM1J-001.)

## Acceptance Criteria

- [ ] AC-1: **Admin Order Create — billing** render cascade country→region→`city`(ward) khi VN; giá trị điền đúng `order[billing_address][city]` (ward name).
- [ ] AC-2: **Admin Order Create — shipping** tương tự AC-1; checkbox **"Same as billing"** copy đầy đủ region + `city`(ward) sang shipping.
- [ ] AC-3: **Admin Order address edit** (order đã tạo) → render cascade + **pre-select** region/`city` từ data đã lưu.
- [ ] AC-4: **Non-VN country** → Magento default (no cascade); generic (DEC-9/DEC-FEATJSZQV3-003).
- [ ] AC-5: **Persist** — `city` (ward) lưu vào `sales_order_address` qua admin path (verify observer `sales_order_address_save_before` bắt `city` do admin submit); parity `sales_quote_address` cho order-create flow.
- [ ] AC-6: **Validation** — `city` required khi VN; region required như hiện tại.
- [ ] AC-7: **Display** — order view, PDF, email, frontend order detail hiển thị region + ward (verify order-address format cover).
- [ ] AC-8: **Regression** — storefront checkout/OSC, cart estimate, customer address book, admin data CRUD, existing order address save flow nguyên vẹn.

## Technical Notes

- Admin order create address **không phải ui_component** như customer modal — block-based form (`Magento_Sales`) với JS `mage/backend`, field `order[billing_address][...]` / `order[shipping_address][...]`. → approach khác TASK-4ZV5NG (xem Open Questions).
- Reuse option provider `Model\Customer\Address\Config\Selector\City` (wards by region) — share với TASK-4ZV5NG/013/014.
- Persist: observer `Observer/Order/Address/SaveSubCity` + `Observer/Quote/Address/SaveSubCity` — verify bắt `city`(ward) từ admin submit; `sub_city` copy path = legacy.
- Tuân DEC-FEATJSZQV3-003, DEC-8 (boundary — admin order là project/Launchpad hay trong module? → OQ).

## Files/Areas Affected

- **NEW**: `view/adminhtml/layout/sales_order_create_index.xml` (+ `sales_order_customer_load` nếu cần) — inject cascade field.
- **NEW**: `view/adminhtml/layout/sales_order_view.xml` (order address edit).
- `view/adminhtml/web/js/form/provider-mixin.js` — extend target order-address selectors (`#order-billing_address_*`, `#order-shipping_address_*`) HOẶC new order-address JS.
- `Observer/{Order,Quote}/Address/SaveSubCity.php` — verify/extend admin submit path.
- Reuse `Model/Customer/Address/Config/Selector/City.php` (share TASK-4ZV5NG).
- `Secomm_VietNamAddress/` (label ward — DEC-FEATJSZQV3-003).
- **KHÔNG affect**: storefront `view/frontend/*`, `db_schema` (cột đã có), admin data CRUD.

## Risks

- Tier-2 (order + PII — §9): sai persist `city`(ward) → order address sai → fulfillment/delivery sai. Bắt buộc QC end-to-end + TL review.
- Order-create JS là native `Magento_Sales` (khác customer modal) → mixin approach ở TASK-4ZV5NG **không port trực tiếp**; approach chưa chốt → cần mini-spec (Mode A).
- "Same as billing" copy có thể miss `city`(ward) nếu JS không hook.
- Quote↔order address parity (order-create đi qua quote address).
- Display: order PDF/email format có thể không qua `customer_address_format`.

## Open Questions

- Q1: **Approach** — (a) override layout `sales_order_create_index.xml` + inject field, (b) extend `provider-mixin.js` cho order-address selectors, hay (c) LayoutProcessor plugin (pattern storefront)? — **SA/TL chốt trong mini-spec** (Mode A).
- Q2: Boundary (DEC-8) — admin order wiring trong module `Secomm_AddressDropdown` hay Launchpad project layer? — SA.
- Q3: Label ward → `Secomm_VietNamAddress` (DEC-FEATJSZQV3-003)? — SA.
- Q4: Observer `sub_city` giữ generic (non-VN 3-level, DEC-FEATE2HM1J-001) — không gỡ; VN order 2-level không submit. Confirm observer không can thiệp ward (`city`) persist. — verify.

## Dependencies

- [TASK-4ZV5NG](TASK-4ZV5NG-apply-address-dropdown-admin-customer-form.md) — share `Selector\City` + VN override.
- Coordinate [TASK-KCBDDT](TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md) nếu leak VN.
- Liên quan [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) GHTK — ward đầy đủ ở admin order giúp carrier rate/sync chính xác (không block).

## Definition of Done

- [ ] Mini-spec + implementation plan (Mode A) approved
- [ ] AI pre-review pass
- [ ] TL review approved (Tier 2 — order + PII)
- [ ] QC: admin order create (billing+shipping, same-as-billing) + order address edit (VN 2-level + non-VN fallback) + persist + display (view/PDF/email) + regression storefront
- [ ] DEC-FEATJSZQV3-003 compliance (no VN leak trong generic)
