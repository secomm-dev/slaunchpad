---
id: TASK-K09G8Y
type: task
title: 'Phase 3 — remove sub_city infrastructure (tables, columns, EAV, observers, KO stack) — GATED'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: high
status: proposed
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001]
decision_assessment: material
components:
  - CMP-ADDR
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/AddressDropdown/
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-K09G8Y] Phase 3 — remove sub_city infrastructure (tables, columns, EAV, observers, KO stack) — GATED

<!-- CANONICAL TASK RECORD — Phase 3, DESTRUCTIVE. Gate cứng: (a) prod/staging scan sub_city data sạch; (b) TASK-YQSS3M AC-001 đạt; (c) Tier-2 sign-off riêng. -->

## Summary

Drop toàn bộ hạ tầng `sub_city` sau khi mọi consumer đã chuyển: 2 bảng `directory_city_sub_city*`, cột `sub_city` trên `sales_order_address`/`quote_address`/`customer_address_entity`, EAV attribute, extension attributes, 4 observers + plugins sub_city, KO checkout mixins + CityData section + Luma template, admin SubCity grid/ACL/menu.

## Mini Spec

### Goal
Kết thúc cửa sổ BC; module chỉ còn hierarchy recursive + profile/schema.

### Expected Behavior
1. **Pre-flight gate (bắt buộc)**: SQL scan prod + staging: `directory_city_sub_city*` rows, `sub_city` non-empty trên 3 bảng address — bằng chứng lưu evidence; không sạch → data patch migrate giá trị sang path mới trước khi drop.
2. db_schema.xml bỏ 2 bảng + 3 cột; whitelist đồng bộ; declarative drop qua `setup:upgrade` (reversible = restore từ snapshot, không auto-rollback).
3. Gỡ: EAV attr (revertable patch), extension_attributes (4 khai báo), observers (`SaveSubCity` ×2, `SetSubCityValueObserver`, `SaveSubCityToAddressBookObserver`), plugins (`AddSubCityFieldToAddressEntity`, `AddSubCityImportAddressPlugin`, `MapperPlugin`, `DataProvidersPlugin`, SaveToQuote sub_city part), SubCity PHP surface (Api/Data/Model/ResourceModel/Mapper/Command/Block/Controller/Ui), KO mixins + requirejs map + CityData section + sections.xml + Luma `address/edit.phtml` + css, admin SubCity grid/form/menu/acl + i18n keys.
4. GraphQL `GetListSubCity` bị gỡ (đã deprecate ở TASK-YQSS3M) — release note breaking change nội bộ.
5. `SetSubCity`/renderer format plugin (`AddressRendererPlugin`, `SetSubCity`) chuyển format theo path mới (D2) trước khi gỡ phần sub_city.

### Constraints / Rules
- Chỉ chạy SAU khi TASK-YQSS3M AC-001 (không còn external reader) + gate pre-flight đạt.
- Tier-2 sign-off riêng cho destructive step; rollback = DB snapshot + revert commit.
- Messenger: mọi chuỗi gỡ kiểm tra 2 file i18n (BR-001).

### Out of Scope
- `is_default` column trên `directory_country_region` (đánh giá riêng — không thuộc sub_city).
- Admin Region/Country grid trùng core Directory (ghi Known Limitation).

### Acceptance Criteria
- AC-001: Pre-flight evidence đầy đủ (prod + staging scan) được TL phê duyệt trước drop.
- AC-002: `setup:upgrade` + `declarative:schema:diff` sạch; DB không còn bảng/cột sub_city.
- AC-003: Toàn app/code grep `sub_city|SubCity` → chỉ còn lại trong CHANGELOG/historic docs (evidence).
- AC-004: QC L3 full: checkout OSC + payment test, customer form, admin forms, GHN/GHTK rate + order sync, cart estimate.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 3, Step 9.

## Implementation Notes

Chưa triển khai (proposed — blocked by gates trên).

## Verification

- [ ] AC-001..004 — evidence: `.ai/runtime/evidence/TASK-K09G8Y/`

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — inventory + dead-code bổ sung làm evidence cho AC-003:

- **Quy mô**: 116/169 file src `Secomm_AddressDropdown` tham chiếu `sub_city|SubCity`; DB: 2 bảng `directory_city_sub_city(_name)`, 3 cột trên core (`quote_address`, `sales_order_address`, `customer_address_entity`), EAV attribute, 4 nhóm extension attributes, GraphQL `GetListSubCity` + field `sub_city_id`, ACL dead `::subcityname` (`etc/acl.xml:15`), 7 SubCity admin controllers (index/edit/new/save/delete/massdelete/view).
- **Dead code (xoá không cần gate)**: `Observer/SetSubCity.php` (không đăng ký trong `etc/events.xml`), `Block/Address/Field/SubCity.php` (khai template không tồn tại), `Model/Customer/Address/Config/Source/City.php`, `Model/Customer/Address/Config/Column/SubCity.php`, `view/adminhtml/web/js/form/element/city.js` (stub không được reference), `view/frontend/web/js/view/shipping.js`, `view/frontend/web/js/view/billing-address/list.js`, `view/frontend/web/template/shipping-address/address-renderer/default.html`, `Model/DataStorage::__destruct`, hàm `countItemsDeleted`, `GhnAddressMapper/Controller/Adminhtml/Ajax/CascadingOptions.php` (dead controller — removal thuộc TASK-YQSS3M).
- **UI mismatch đợt gỡ admin SubCity**: `Ui/Component/Listing/Column/CityBlockActions.php:32` — nút View của city grid mở subcity index (sai target); `:97-102` — delete confirm không đếm children (`parent_city_id`) → risk xoá node có con. Cần xử lý khi thay CRUD (cross-ref TASK-9EX975).
- **Live-path warning cho KO stack**: Luma checkout mixins (`action/shipping-address-dropdown.js`, `action/billing-address-dropdown.js`, `address-dropdown.js` + các mixin trong `requirejs-config.js`) VẪN LIVE qua Mageplaza OSC — handle `onestepcheckout_index_index` có `<update handle="checkout_index_index"/>` nên jsLayout injection của `Plugin/Checkout/LayoutProcessorPlugin` vẫn áp dụng. → KHÔNG xếp vào dead-code; điều kiện gỡ thêm: TASK-FMAN1B (OSC integration) xong trước, ngoài 2 gate hiện có.
- **`is_default` trên `directory_country_region`** (`etc/db_schema.xml:135-137`, đã ghi Out of Scope): audit xác nhận vi phạm target §2 (core table bị modify structure). Option nếu tách khỏi sub_city drop: MIGRATE sang marker/meta table riêng của module. **TL approved 2026-08-28**: hướng MIGRATE được chấp nhận — triển khai như schema change riêng (TL review khi execute), không gộp vào drop phase.

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted — D2 persist policy quyết định data patch migrate)
