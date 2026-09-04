---
id: TASK-CR4D1V
type: task
title: 'Security + perf fixes on existing touch-points — SQL binding, CityData cache key, SaveToQuote cast bug'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: B
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001]
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/Helper/Address.php
  - app/code/Secomm/AddressDropdown/Helper/Data.php
  - app/code/Secomm/AddressDropdown/CustomerData/CityData.php
  - app/code/Secomm/AddressDropdown/Plugin/Quote/SaveToQuote.php
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-CR4D1V] Security + perf fixes on existing touch-points — SQL binding, CityData cache key, SaveToQuote cast bug

<!-- CANONICAL TASK RECORD — Phase 2. [BLOCK] coding-rule violations phát hiện qua review (AGENTS §7.2: parameterized queries only). -->

## Summary

Fix nhóm khiếm khuyết có sẵn mà refactor đang chạm: (1) SQL string interpolation; (2) CityData single cache key không theo locale/store; (3) `(int)` cast nhầm tên thành số trong SaveToQuote; (4) extension attribute khai báo lặp.

## Mini Spec

### Goal
Vùng code Phase 2 chạm vào phải đạt coding rules [BLOCK] trước khi các task khác xây trên đó.

### Expected Behavior
1. `Helper/Address.php:77` (`LIKE '$cityDefaultName'`), `:163` (locale concat), `Helper/Data.php:95` (region_id concat) → parameterized (`?` binding / `addFieldToFilter`).
2. `CustomerData/CityData`: cache key thêm locale + store (`city_data_cache_key_<locale>_<storeId>`); hoặc nếu renderer mới khiến section không còn cần cho store bật flag schema → bỏ section ở store đó (chuẩn bị gỡ hẳn ở TASK-K09G8Y).
3. `Plugin/Quote/SaveToQuote.php:36`: bỏ `(int)` cast — giá trị là string name; đồng thời bỏ set trùng với observer path (giữ MỘT nguồn thật).
4. `extension_attributes.xml`: xoá dòng khai báo lặp `sub_city` cho `ShippingInformationInterface` (giữ 1).
5. Test cập nhật: `CityDataTest`, `DataTest` adjust theo fix.

### Constraints / Rules
- Fix có giới hạn (bounded): không refactor tổng thể helper (đó là các task khác); chỉ binding + cache key + dedupe plumbing.
- Self-fix bound 2 chu kỳ; quá thì escalate.

### Out of Scope
- Thiết kế lại CityData thành hierarchy provider (TASK-J49PRZ/K09G8Y); shipping plugin parse request (đánh giá riêng khi chạm TASK-YQSS3M).

### Acceptance Criteria
- AC-001: Không còn string-interpolation SQL trong 2 helper (grep audit + code review).
- AC-002: Hai store khác locale không ăn nhầm cache CityData (integration test 2 locale).
- AC-003: Save sub_city trên checkout không còn ghi giá trị `0`; quote address persist đúng 1 nguồn.
- AC-004: Test suite module xanh.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 2, Step 6.

## Implementation Notes

Chưa triển khai (proposed).

## Verification

- [ ] AC-001..004 — evidence: `.ai/runtime/evidence/TASK-CR4D1V/`

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng toàn module 2026-08-28 (gap analysis vs target architecture). 4 item trong Expected Behavior **confirmed** đúng vị trí (`Helper/Address.php:77`, `SaveToQuote.php:36`, extension_attributes duplicate, CityData cache key). Phát hiện thêm các khiếm khuyết cùng tính chất trên touch-point legacy — đề xuất mở rộng scope khi triển khai (**cần TL confirm**, vì rộng hơn mô tả hiện tại):

1. **Empty catch**: `Observer/SaveSubCityToAddressBookObserver.php:91-92` — `catch (\Exception $e) { }` nuốt toàn bộ → lỗi ghi address book im lặng.
2. **DI bypass + log sai level**: `Model/Export/AddressDropdown.php:255` — `ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface')`; cùng class có 3 lệnh `logger->error` dùng làm debug log.
3. **Catch-all swallow**: `Plugin/Model/Shipping.php:104,135`; `Model/Import/AddressDropdown.php:269-271`; `Model/Import/SaveAddressImport.php:231-233` — catch rộng rồi bỏ qua, khó chẩn đoán lỗi import/shipping.
4. **Mass-assignment**: `Plugin/AddSubCityFieldToAddressEntity.php:13,66-67` — `$httpRequest->getParam('sub_city')` gán thẳng custom attribute ở mọi area, không kiểm soát area/acl; docblock sai `@package Yireo\ExampleAddressFieldNote\Plugin`.
5. **Data patch revert sai entity**: `Setup/Patch/Data/AddNewAddressAttributeSubCityCustomer.php` — `revert()` gỡ attribute trên `Magento\Customer\Model\Customer::ENTITY` trong khi patch tạo trên `customer_address` → revert để lại rác.
6. **`Model/Import/DeleteAddressImport.php`**: `:108` `return false` trong path mong đợi entity/array (TypeError risk); `:118` `is_null($collection->getFirstItem())` luôn false (`getFirstItem` trả empty item, không null) → nhánh dead.
7. **`Plugin/ActionsPlugin.php:36`**: truy cập kết quả không null-check (NPE risk) + `getById` trong loop (N+1).
8. **SELECT * / concat trong SELECT**: `Helper/Data.php:122-125`, `Helper/Address.php:160` — hiệu năng + giữ pattern lân cận item 1.
9. **Whitelist drift**: 4 tên FK constraint trong `etc/db_schema.xml` lệch `etc/db_schema_whitelist.json` → mỗi `setup:upgrade` phát sinh DDL churn (schema hygiene; hoặc gộp vào TASK-K09G8Y khi sync whitelist drop phase).
10. **Integration test harness** (**TL approved 2026-08-28 — gán vào task này**): `dev/tests/integration` chưa có `install-config-mysql.php` / `config-global.php` → 3 Integration tests của module (`Test/Integration/CustomerData/CityDataTest.php`, `Test/Integration/Helper/DataTest.php`) không chạy được. Setup harness là điều kiện tiên quyết cho AC-002 (integration test 2 locale).

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted)
