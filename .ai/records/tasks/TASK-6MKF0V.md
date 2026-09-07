---
id: TASK-6MKF0V
type: task
title: 'Retire sub_city legacy layer — depth-2 only qua profile engine (không còn sub_city submission/UI/admin/DB)'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID            # mini-spec embedded; user directive 2026-09-03 (SA/TL authority)
specification_ref: Embedded Mini-Spec
risk: high
status: completed
priority: high
decision_assessment: material
decisions: [DEC-TASK6MKF0V-001]
components:
  - CMP-ADDR
  - CMP-VNADDR
changes_project_state: true
source_areas:
  - app/code/Secomm/AddressDropdown/
  - app/code/Launchpad/Osc/
created: 2026-09-03
updated: 2026-09-03
owner: [dev]
related_tickets: [TASK-K09G8Y, TASK-FMAN1B, TASK-9EX975]
---

# [SLP][FEAT-2PZQKJ][TASK-6MKF0V] Retire sub_city legacy layer — depth-2 only qua profile engine (không còn sub_city submission/UI/admin/DB)

## Summary

Project hoàn toàn mới (0 orders, 0 customer addresses có sub_city — verified DB 2026-09-03; 5 quote rows = rác QC dev). Quyết định user (SA/TL authority, chat 2026-09-03): `vn_admin_pre_2025` render **depth-2 qua profile engine** (recursive city), KHÔNG dùng sub_city nữa — loại toàn bộ legacy sub_city layer khỏi cả 2 module address.

## Mini Spec

### Goal

Toàn bộ pipeline address (storefront legacy + OSC copies + schema renderer + admin + server plugins + GraphQL + DB) không còn reference sub_city như một cấp dữ liệu hoạt động; `vn_admin_pre_2025` 3-level render qua city `depth=2` của profile engine.

### Expected Behavior

- Cascade (mọi surface) = region → city levels theo profile schema; 2-level profile (vn_admin_2025) → 2 select; 3-level profile (vn_admin_pre_2025) → 3 select từ depth-1/depth-2 city rows.
- Submit payload không còn `custom_attributes[sub_city]` / `extension_attributes.sub_city`; quote/order/customer address columns `sub_city` không còn được ghi.
- GraphQL `GetListSubCity` removed khỏi schema surface.

### Constraints / Rules

- Không edit Mageplaza in-place (AC-O2); OSC coupling chỉ trong Launchpad_Osc (DEC-TASKFMAN1B-001).
- DB schema change → Tier 2; chạy sau batch address đang chờ TL review (TASK-3T3NSV/ADT94K/9394A9/J9AVGK) để tránh entangle 2 changesets chưa review.
- DROP cột/bảng sub_city: project mới, data = 0 (verified) → an toàn; vẫn cần schema patch + whitelist update đúng quy trình.
- i18n: xoá chuỗi sub-city khỏi `vi_VN.csv`/`en_US.csv`/`en_VN.csv` kèm module.

### Out of Scope

- Option A (Alpine schema cascade cho OSC — TASK-FMAN1B) — task này là PREREQUISITE dọn đường, không thay thế.
- Remove legacy mixins/copies toàn phần (TASK-K09G8Y) — chỉ chạm phần sub_city của chúng.
- Admin surfaces (TASK-9EX975, deferred) — chỉ bỏ SubCity CRUD controller/menu/ACL thuộc legacy layer.

### Acceptance Criteria

- AC-1: grep `sub_city|SubCity` trong 2 module chỉ còn: DB schema drop-patch, i18n removed-lines, CHANGELOG/README mentions lịch sử.
- AC-2: OSC + default checkout + cart estimate: submit thành công, payload không mang sub_city; QC L3 không regression rates/payment.
- AC-3: swap scheme `vn_admin_pre_2025` → legacy renderer (flag `legacy`) hiển thị đủ 3 cấp qua depth (hoặc gated: legacy renderer chỉ hỗ trợ 2-level + message chỉ dùng schema renderer cho 3-level — chốt ở plan).
- AC-4: `setup:upgrade` drop sạch cột `sub_city` (quote_address, sales_order_address, customer_address_entity) + bảng `directory_city_sub_city`; unit tests xanh sau xoá (84+48).
- AC-5: GraphQL schema không còn `GetListSubCity`; unit test tương ứng removed/cập nhật.

## Approach

Plan pha (Mode A, TL review trước khi code pha có DB/contract change):

1. **JS storefront** — bỏ sub-city select + `loadSubCities` + hidden input + sync `custom_attributes[sub_city]` khỏi: Secomm_AddressDropdown default-checkout copies (shipping/billing), Launchpad_Osc copies (shipping/billing), cart estimation JS.
2. **Server plumbing** — bỏ `SaveToQuote` sub_city handling, `AddSubCityFieldToAddressEntity`, `MapperPlugin`, `DataProvider*`, `AddressRendererPlugin`, source/column config `Model/Customer/Address/*SubCity*`, `Mapper/SubCityNameDataMapper`, `Model/SubCity*`.
3. **Admin legacy** — xoá `Controller/Adminhtml/SubCity/*` + menu/ACL tương ứng.
4. **GraphQL** — remove `GetListSubCity` khỏi `schema.graphqls` + resolver + unit tests.
5. **DB** — schema patch DROP `directory_city_sub_city` + cột `sub_city` (3 address tables); update whitelist; verify `setup:upgrade` trên DB sạch.
6. **i18n/docs** — dọn chuỗi + README/CHANGELOG cả 2 module + DECISIONS index.

## Verification

- [x] AC-1: grep `sub_city|SubCity|sub-city` toàn app chỉ còn: `etc/db_schema.xml` (drop-patch comment), i18n removed-lines (đã lọc sạch), CHANGELOG/README historical, và comment TASK-6MKF0V có chủ ý (RendererMode, Plugin/Model/Shipping, di.xml, requirejs-config, schema-edit.phtml, system.xml). Launchpad_Osc + VietNamAddress: sạch theo slice 1.
- [x] AC-4: **APPLIED 2026-09-03 (TL duyệt chạy ngay)**. Lần upgrade 1 KHÔNG drop gì vì whitelist đã bị remove entry — cơ chế `Diff::canBeRegistered`: destructive op (drop) chỉ được register khi object VẪN CÓ trong whitelist; re-add entry vào whitelist (giữ db_schema.xml trống) → upgrade 2 drop sạch cả 2 bảng + 3 cột. Sau drop, whitelist regenerate về declarations-only. Data patch `RemoveSubCityCustomerAttribute` applied (EAV attr 143 removed, 0 values); row rác `customer_form_attribute` (attribute_id=143) xoá trực tiếp (1 row, dead reference, chỉ tồn tại do lịch sử DB này). Verify: `SHOW TABLES LIKE '%sub_city%'` = 0; information_schema cột `sub_city` = 0; `eav_attribute` = 0. Dump diff declarative-vs-DB: 0 op liên quan sub_city (315 op modify_column pre-existing toàn app — core/vendor tables, charset drift, ngoài scope task; `setup:db:status` đã stale TRƯỚC task này).
- [x] AC-5: GraphQL `GetListSubCity` (field + input + type) đã remove khỏi `schema.graphqls` ở slice trước; unit tests tương ứng removed; `setup:di:compile` sạch; AddressDropdown unit tests 50 tests / 114 assertions xanh (warning duy nhất = Allure extension config — pre-existing).
- [x] AC-2: **QC-3 guest checkout smoke PASS 3/3** (2026-09-03, GraphQL headless: createEmptyCart → … → placeOrder): fashion_en/vn order 000000088, fashion_vi/vn order 2000000001, fashion_en/US order 000000089 — payload KHÔNG mang `sub_city` / `custom_attributes[sub_city]` / `extension_attributes.sub_city`. Ghi chú: allowed countries = US,VN nên AU bị core `QuoteAddressFactory` chặn đúng luật ("Country is not available") — không phải regression. Phát hiện + fix regression trong QC: `AddressRendererPlugin` còn gọi `$this->addressHelper` sau khi slice 2 bóc injection `Helper\Address` (method `getCityNameByDefaultName` là method city thuần, KHÔNG thuộc sub_city layer) → restore injection; 4 warning cũ đều pre-fix, 0 warning sau fix.
- [x] AC-3: **Resolved theo nhánh "gated"** — legacy renderer chỉ hỗ trợ scheme 2-level; 3-level (vn_admin_pre_2025) phải dùng schema renderer. Căn cứ: runtime chỉ có VN_ADMIN_2025 (0 depth-2 rows); swap scheme cần TASK-9394A9 tooling (TL decision); shim `GetListCityGraphql` filter theo `region_id` thôi — không lọc `parent_city_id IS NULL`, trên scheme 3-level sẽ trả flat list lẫn cả 2 tầng. Ghi enforcement note đến khi TASK-9394A9 + TASK-9EX975 (Slice A) land.
- Evidence: `.ai/runtime/evidence/TASK-6MKF0V/` (`qc3-guest-checkout-smoke.md` + `qc_checkout_smoke.py`)

## Execution log

### 2026-09-03 — slice 2: Secomm_AddressDropdown (toàn bộ sub_city layer) — file-level DONE, DB migration chờ TL gate

- **Server plumbing**: xoá `Api\Data\SubCity*`, `Command/SubCity/*`, `Mapper/SubCity*`, `Model\Data\SubCity*`, `Model\Customer\Address\{Attribute\Source\SubCity, Config\Column\SubCity, Config\Selector\SubCity}`, `Block\{Form\SubCity*, Adminhtml\SubCity, Address\Field\SubCity}`, `Controller\Adminhtml\SubCity/*` (+ menu/ACL/ext-attrs XML tương ứng), GraphQL `GetListSubCity` (resolver + schema), `Model\Constant::SUBCITY_CODE`; import/export bóc cột sub_city; `Plugin\Cart\LayoutProcessorPlugin` bóc fieldset attrs; city grid View action (dead route) removed; `Plugin\Customer\Address\Save`/`Plugin\Model\Shipping`/`AddressRendererPlugin` giữ logic city, chỉ bóc nhánh sub_city; `sub_city_listing` UI component removed.
- **Frontend (Luma)**: `shipping-address-dropdown.js` + `billing-address-dropdown.js` (cascade stops at city; rename `setupCityCascade`/`initializeCityCascadeElements`), `address-dropdown.js` (customer form), `shipping-estimation-mixin.js`, `shipping-save-processor/default-mixin.js` (payload bóc `extension_attributes.sub_city`), `checkout-data-resolver-mixin.js`, `view/shipping.js`/`shipping-mixin.js`/`billing-address-mixin.js` (bóc `subCityValidate`), `address-renderer/default-mixin.js` (bóc `getSubCityName`); XOÁ `set-shipping-information-mixin.js` + `set-billing-information-mixin.js` (chỉ tồn tại để inject ext-attr → dead wrapper) + dọn requirejs-config; 3 template `.html` bóc render block; `styles.css` giữ chỉ còn 5 rule city-related; Hyvä `templates/hyva/address/edit.phtml` bóc field + Alpine fetch/populate sub-cities; `templates/address/edit.phtml` bóc sub-city updater keys.
- **Admin**: `config/address-city.js` rewrite (loadCities 1 cấp), `form/provider-mixin.js` (savedCity only), grid `custom-provider.js` (bóc `sub_city_listing`), `customer_address_form.xml` (bóc field sub_city), config `city.phtml` + `Block\...\Field\City` (bóc subCitySelectId), `default-address.html` (bóc render sub_city).
- **DB (file-level)**: `db_schema.xml` DROP `directory_city_sub_city` + `directory_city_sub_city_name` + cột `sub_city` trên `quote_address`/`sales_order_address`/`customer_address_entity`; whitelist synced; PATCH MỚI `Setup/Patch/Data/RemoveSubCityCustomerAttribute.php` (removeAttribute entity `customer_address` + xoá giá trị `customer_address_entity_varchar` trước khi remove; creator patch cũ đã xoá file vì apply đã ghi trong patch_list — data patch applied KHÔNG được edit).
- **DB state verified (pre-upgrade)**: DB `fashion_launchpad` hiện còn: bảng `directory_city_sub_city(+_name)`, cột `sub_city` trên 3 bảng, EAV attr `sub_city` (attribute_id=143, entity_type_id=2=customer_address, 0 values, 1 row `customer_form_attribute`). Tất cả sẽ được drop/remove khi `setup:upgrade` chạy.
- **i18n**: `en_US.csv` lọc sạch 20 dòng SubCity/sub-city (kể cả nhóm label admin CRUD legacy).
- **Docs**: CHANGELOG entry `TASK-6MKF0V: sub_city layer removed (2026-09-03)`; README section Hierarchical Filtering cập nhật (depth via `parent_city_id`).
- **Verify**: `php -l` sạch (PHP mới sửa), `node --check` sạch (JS), whitelist JSON valid, `setup:di:compile` OK, unit tests 50/114 xanh, validator 20 FAIL = baseline cũ của record khác (0 FAIL liên quan task/module).
- **DB migration (TL duyệt chạy ngay 2026-09-03)**: upgrade 1 với whitelist đã remove entry → KHÔNG drop gì ("Nothing to import" = app:config:import, không phải schema). Root cause: `Diff::canBeRegistered` chỉ cho phép destructive op khi object còn nằm trong whitelist — remove entry khỏi whitelist = bảo Magento "không phải của tôi, đừng đụng". Fix: giữ entry sub_city trong whitelist (db_schema.xml trống) → upgrade 2 drop sạch 2 bảng + 3 cột; EAV attr + patch applied; dọn 1 row rác `customer_form_attribute`; regenerate whitelist về declarations-only (generated names chuẩn hoá: `DIR_REGION_CITY_REGION_ID_DIR_COUNTRY_REGION_REGION_ID`…). `setup:db:status` còn stale do 315 modify_column pre-existing toàn app (charset drift, cả bảng core/vendor — KHÔNG liên quan, ngoài scope, đừng chạy upgrade để "sửa" trừ khi có review riêng).
- **Sửa whitelist ban đầu bị sai hướng** (Edit đầu chèn nhầm thay vì xoá — phát hiện, rewrite sạch bằng Write; rồi lại phát hiện chiều whitelist đúng là PHẢI GIỮ entry đến khi drop xong).

**Status: slice 2 DONE 2026-09-03 (address_dropdown) — DB applied. QC-3 PASS 3/3 + AC-3 gated → task COMPLETE, chờ TL review.**

#### QC 2026-09-03 (cont.): fresh-DB regression từ slice 1 — phát hiện bởi user, đã fix

- **Finding (user):** review Setup/Patch thấy chỉ còn `RemoveSubCityCustomerAttribute` và nghi fresh DB không có phần nạp city data. Kiểm chứng: **chuỗi bootstrap empty-DB tồn tại và chỉ break ở CSV legacy** — `db_schema.xml` tạo tables declarative; data patches chạy đúng dependency chain `InstallVietNamAddressPatch` → `SeedVnProfileMembership` → `ImportVnAdmin2025SchemePatch` (verified trên patch_list của DB này + region rekey `VN-01` đã chạy).
- **Root cause:** `InstallVietNamAddressPatch` import `VN_Address_2Level.csv` qua legacy entity `Secomm\AddressDropdown\Model\Import\AddressDropdown` có `needColumnCheck = true` — sau slice 2, `validColumnNames` còn 7 cột trong khi CSV header vẫn 9 cột (`sub_city_default_name`/`sub_city_name`) → `AbstractEntity` (`vendor/.../AbstractEntity.php:818`) đánh lỗi `ERROR_CODE_INVALID_ATTRIBUTE` → `validateSource` throw → patch **nuốt exception (`logger.error`)** → `setup:upgrade` vẫn success nhưng DB trống region/city. Ghi chú slice 1 trước đó ("2 cột inert — importer không đọc") SAI — importer đọc header để validate.
- **Fix:** strip 2 cột chết khỏi `VN_Address_2Level.csv` (python csv round-trip; verify 0 rows có giá trị sub_city trước khi strip; 3.321 rows + UTF-8 no BOM + CRLF giữ nguyên; consumers khác chỉ docblock, không parser nào đọc file này ngoài legacy importer). Verify zero-write bằng importer thật: colNames ⊆ validColumnNames, parse 3.321 rows, 0 invalid-attribute error.
- **Bài học:** LL-0006.

#### Follow-up TL decision (2026-09-03): bỏ hẳn legacy 2-level bootstrap

- **User (TL authority): "nên bỏ luôn file 2 level và patch data InstallVietNamAddressPatch"** — chấp nhận: xoá `Files/VN_Address_2Level.csv` + `Setup\Patch\Data\InstallVietNamAddressPatch` (patch đã applied trên DB này — xoá file patch creator là hợp lệ, record trong `patch_list` giữ nguyên; LL-0005 lineage).
- **Chuỗi fresh-DB mới**: `ImportVnAdmin2025SchemePatch` (deps `[]` — chạy ĐẦU, tự bootstrap toàn bộ từ empty DB: regions + units + snapshot/registry + membership + `address/profiles/mapping` + `active_scheme`, một transaction; verify `setActiveSchemeConfig` tự set mapping) → `SeedVnProfileMembership` (deps `[ImportVnAdmin2025SchemePatch]` — belt-and-braces idempotent: upsert region claims + mapping only-if-unset). Đảo deps so với trước (trước: Install → Seed → Import vì Import cần legacy data có sẵn để rekey; giờ Import tự tạo nên phải chạy trước Seed).
- **An toàn DB đang chạy**: cả 3 patch đã trong `patch_list` → đổi thứ tự deps không re-run gì trên DB này; `CurrentDatasetRekeyMatcher` giữ nguyên (DB cũ có legacy rows vẫn cần bridge khi re-import scheme — matcher match DB rows, KHÔNG đọc file đã xoá).
- **Verify**: `php -l` 2 patch sạch; grep app-code 0 ref còn lại tới `InstallVietNamAddressPatch`/`VN_Address_2Level` (chỉ docs đã update + 1 comment test minh hoạ); unit tests VietNamAddress 84/250 + AddressDropdown 50/114 xanh (warning duy nhất = Allure pre-existing); `Files/` chỉ còn 2 dataset canonical.

#### QC 2026-09-03 (AC-2/AC-3)

- QC-1/QC-2: static assets deployed không còn sub_city; GraphQL `GetListSubCity` vắng khỏi introspection (direct query "Cannot query field"), `GetListCity` BC shim vẫn sống; `addressSchema` (vn_admin_2025, 2 levels) + `addressLocations` (HCM 168 ward depth-1; en "An Dong" / vi "An Đông") đúng.
- QC-3: 3/3 smoke PASS (bảng + chi tiết trong evidence `qc3-guest-checkout-smoke.md`).
- **Regression fix trong QC**: `Plugin/AddressRendererPlugin.php` restore injection `Helper\Address $addressHelper` (slice 2 bóc thừa — `getCityNameByDefaultName` là method city thuần, không thuộc sub_city layer; warning "Undefined property addressHelper" 4 lần pre-fix, 0 sau fix + `cache:flush`).
- AC-3 gated: legacy renderer = 2-level only (GetListCity shim không lọc `parent_city_id` → 3-level sẽ flat-mix sai); 3-level đi schema renderer. Swap scheme + tooling = TASK-9394A9 (TL decision), admin surfaces = TASK-9EX975 Slice A.

### 2026-09-03 — slice 1: Launchpad_Osc (JS copies) + VietNamAddress (data/i18n) — user directive, chạy trước TL review plan (changeset chung TASK-FMAN1B round-4)

- **Launchpad_Osc** (`view/frontend/web/js/action/{shipping,billing}-address-dropdown.js`): bỏ sub-city select + error div, `loadSubCities` (GraphQL `GetListSubCity`), `bindSubCityChange`, `updateSubCityDropdown`, `subCityVisible`, `triggerValidSubCity`, hidden input `sub_city`, sync `custom_attributes[sub_city]`/`extension_attributes.sub_city`, prefill `cachedSubCity`; rename `setupCitySubCity`→`setupCityCascade`, `initializeCitySubCityElements`→`initializeCityCascadeElements`. Cascade = Country → Region → Ward; round-4 layout giữ nguyên. `node --check` OK; `pub/static/frontend/Magento/luma/vi_VN/Launchpad_Osc/...` là symlink → tự current.
- **VietNamAddress**: xoá `Files/VN_Address.csv` (dataset 3-level legacy sub_city, 0 tham chiếu code — nguồn canonical là `VN_ADMIN_*_import.csv`); lọc chuỗi SubCity/sub-city khỏi `en_US.csv`/`vi_VN.csv`/`en_VN.csv` (chuỗi chỉ dịch legacy admin CRUD của AddressDropdown — phase kế sẽ xoá CRUD, fallback English trên dead surface); README cập nhật provenance. Giữ `VN_Address_2Level.csv`.
- **Verify**: unit tests VietNamAddress 84/84 pass (250 assertions); grep residue sạch 2 module (trừ fixture header + doc historical).
- **Còn lại (đúng pha trong record này)**: AddressDropdown copies (default checkout + cart), server plumbing (SaveToQuote/AddSubCityFieldToAddressEntity/mapper/renderer), admin CRUD, GraphQL `GetListSubCity`, DB schema drop, i18n AddressDropdown.

**Status: slice 1 DONE 2026-09-03 (_launchpad_osc + vietnam_address) — các phase server/DB vẫn chờ TL review (Tier 2) như plan.**

## Related records

- Decision: DEC-TASK6MKF0V-001 (sub_city retirement, depth-2 only).
- Parent: FEAT-2PZQKJ (profile engine); liên quan FEAT-YA2C0W (PRE_2025 scheme).
- Cleanup siblings: TASK-K09G8Y (remove copies + legacy mixins sau Option A), TASK-FMAN1B (OSC integration), TASK-9EX975 (admin surfaces).
- Điều kiện tiên quyết: batch address hiện tại (TASK-3T3NSV + Phase B/C/D) TL review + commit xong.
