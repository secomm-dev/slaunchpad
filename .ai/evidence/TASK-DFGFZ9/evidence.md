# Evidence — TASK-DFGFZ9 (COD identification ownership → Secomm_Cod)

Date: 2026-09-23 · Implementer: AI (plan approved by user acting SA/TL) · DEC-TASKDFGFZ9-001

## 1. Call-sites before / after

**Before** (`before-call-sites.txt`):
- `Secomm_Ghtk/Model/OrderSubmit/DefaultCodAmountResolver.php` L15 use / L34 ctor-inject / L41 `isCod()` — typehint `Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface`.
- `Secomm_ShippingCore/etc/di.xml` L77-78 preference (owner).
- 8 unit tests ShippingCore `Test/Unit/Model/Cod/`.

**After**:
- Cùng call-site Ghtk, typehint `Secomm\Cod\Api\CodPaymentMethodResolverInterface` (grep-verified: duy nhất 1 production call-site trước và sau).
- Preference `Secomm\Cod\...\CodPaymentMethodResolverInterface → Secomm\Cod\Model\ConfiguredCodPaymentMethodResolver` tại `Secomm/Cod/etc/di.xml`.
- 20 unit tests Secomm_Cod (8 resolver + 8 patch + 4 AclConsistency) + Ghtk 7 tests re-import.

Grep after: 0 stale reference `Secomm\ShippingCore\Api\Cod` trong production code (chỉ còn
CHANGELOG history + docblock "supersedes" + comment "moved out" có chủ đích).

## 2. Dependency graph before / after

Before: `Ghtk → ShippingCore (+AddressDropdown+VietNamAddress)`; ShippingCore → VietNamAddress; Ghn/GiaoHangNhanh/Ahamove/Launchpad_MageplazaTableRate → ShippingCore. ShippingCore sở hữu contract COD mà không tự consume.

After: giữ nguyên + `Ghtk → Secomm_Cod` (module.xml sequence 16). `Secomm_Cod` 0 dependency.
Verify-absent (grep): ShippingCore không tham chiếu `Secomm\Cod`; Secomm_Cod không tham chiếu carrier; Ghn/Ahamove không thêm edge.

## 3. Config path before / after

- Before: `secomm_shippingcore/cod/payment_methods` — group `cod` trong section `secomm_shippingcore`.
- After: `secomm_cod/payment_identification/payment_methods` — section `secomm_cod` "COD Settings" (tab `sales`, sortOrder 75, showInDefault-only, resource `Secomm_Cod::config`) → group `payment_identification` "Payment Identification" → field `payment_methods` (canRestore=1). ACL `Secomm_Cod::config` nest `Magento_Backend::admin → stores → stores_settings → Magento_Config::config`, XSD-valid (dùng `title=` attribute theo core; AiCommerce pattern `<label>` child bị phát hiện vi phạm XSD — KHÔNG reproduce).
- ShippingCore: group `physical` + section tab `sales` nguyên vẹn.

Runtime proof (`bin/magento config:show` sau `cache:flush`):
- `secomm_cod/payment_identification/payment_methods` → exit 0 (default node merge OK).
- `secomm_shippingcore/cod/payment_methods` → "path doesn't exist" (exit 1) — default node cũ đã remove khỏi merged config.
- control `secomm_shippingcore/physical/default_package_length` → exit 0 (physical untouched).

## 4. Migration (idempotent)

- `Secomm\Cod\Setup\Patch\Data\MigrateLegacyCodPaymentMethodConfig` — copy-only, dest-wins, preserve `(scope, scope_id)`, skip empty source, không xoá legacy rows, non-revertable; `cleanType('config')` chỉ khi có copy.
- `patch_list` DB: `Secomm\Cod\Setup\Patch\Data\MigrateLegacyCodPaymentMethodConfig` PRESENT (sau setup:upgrade).
- Local DB: legacy rows = 0 → patch no-op, new rows = 0 (không ghi row rỗng — đúng AC-4).
- 8 unit cases pass: copy default / preserve websites+stores / empty-source skip / dest-wins / dest+empty no-write / re-apply no-op / cache-clean-only-on-copy / empty-DB no-op.

## 5. Test results

- Scoped `Secomm_Cod|Secomm_Ghtk`: **257 tests / 632 assertions — 0 fail, 0 error** (9 PHPUnit deprecations = framework bootstrap, constant mọi subset, pre-existing pattern).
- Full secomm suite: 2501 tests / 217,768 assertions — 52 errors + 2 failures, **0 thuộc COD/Cod/Ghtk**. Phân loại: 44 = ShippingCore Zone-controllers/CarrierCoverage/CoverageFormDataProvider (stream TASK-G3K9V2 thiếu production files `CarrierRegistry.php`/`PolicyConfig.php` trong working tree — untracked dir, N-DEFECT external pre-existing); 7 = Secomm_Tracking EventNormalizer (pre-existing baseline, đã ghi trong memory); 3 = FulfillmentCore StatusMapResolver/InboundUpdateApplier (pre-existing parallel stream).
- `setup:di:compile` GREEN (không stale reference interface cũ).
- `setup:upgrade` GREEN; `app/etc/config.php` += `Secomm_Cod => 1` (line 458).
- XML: 9/9 file parse OK (simplexml); XSD schema-validate OK cho system.xml/acl.xml/module.xml/config.xml (cả Cod và ShippingCore-side edits).
- AclConsistencyTest (4): resource không orphan; section global-only; section/group/field nối đúng `CONFIG_PATH_PAYMENT_METHODS`; config.xml có default node.

## 6. Docs / workflow artifacts

- Architecture doc: Revision v11 note (header) + §4.1 rewrite (owner Secomm_Cod, dependency rules, config path, migration) + §8 (COD rule) + §21 (edges mới) + §22 (forbidden edges mới) + §27 module table (+Secomm_Cod, Ghtk depends) + §28 (freeze amendment v11, deferred-list update) + §29 (checklist ShippingCore + carrier).
- DEC-TASKDFGFZ9-001 (accepted, user acting SA/TL) + DECISIONS.md index + TASK-DFGFZ9 record (Mini-Spec embedded) + TASK-STC3NB amend-note.
- README/CHANGELOG: Secomm_Cod (mới), ShippingCore 0.23.0, Ghtk 2.3.0; USER_GUIDE Case 10 (+ fix stale claim "chưa carrier nào gửi COD" — GHTK pick_money đã live).

## 7. Còn lại (đề xuất QC/TL)

- Admin browser smoke (cần admin login): Stores → Configuration → Sales → COD Settings hiển thị; field global-only; ACL resource grant/revoke.
- `CashOnDelivery` core method chưa được bật ở store nào — merchant cần set codes ở path mới (copy patch đã sẵn sàng cho staging/prod có data cũ).
