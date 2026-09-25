# Changelog — Secomm_Cod

## 1.3.0 — 2026-09-23 (TASK-DFGFZ9 phase 3 closure — atomic per-order claim, DEC-TASKDFGFZ9-004)

### Added
- `secomm_cod_collection.active_order_claim` (bigint nullable) + UNIQUE constraint: cột
  application-managed — mọi row live (PENDING|UNKNOWN|SUBMITTED|RECOVERED, amount > 0) mang
  claim = magento_order_id; FAILED → NULL (release). MySQL unique enforce TỐI ĐA 1 active
  claim per order ở mức engine.
- `Model\CodClaimConflictException` (extends LocalizedException) — attempt thua cuộc nhận
  exception với message cite holder (carrier + reference).
- `REASON_INVALID_ORDER_AMOUNT` — grand_total âm là data defect → REJECTED (fail-loud).

### Changed
- `CollectionLedger::recordPending` → **INSERT-first**: claim được quyết bởi engine unique
  index; duplicate-key discriminate same-reference re-arm (restore PENDING, no-downgrade
  SUBMITTED|RECOVERED) vs claim conflict; `markNotSubmitted(FAILED)` sets
  `active_order_claim = NULL` (release); UNKNOWN giữ claim.
- `SingleCollectionCodResolver`: XOÁ gate VND + `SUPPORTED_CURRENCY` — decision trả
  **order currency**; `grand_total` < 0 → REJECTED `invalid_order_amount`; grand_total = 0
  → **COLLECTIBLE 0.0** (COD order không thu gì vẫn giữ classification COD — KHÔNG đổi
  thành NOT_COD). Currency SUPPORT = carrier concern.

### Tests
Decision: collectible(0.0) OK + negative throw + reason mới; resolver 16 (USD passthrough,
zero stays COD, negative REJECT, frozen currency replay); ledger 18 (claim binds, INSERT-first
duplicate paths: re-arm / no-downgrade / claim conflict với holder visible + invisible;
FAILED releases / UNKNOWN keeps).

## 1.2.0 — 2026-09-23 (TASK-DFGFZ9 phase 3 — product final state, DEC-TASKDFGFZ9-003)

### Added
- Collection ledger `secomm_cod_collection` (db_schema + whitelist): `magento_order_id` +
  UNIQUE (carrier_code, provider_reference) + amount/currency/status
  PENDING|SUBMITTED|RECOVERED|FAILED|UNKNOWN — nguồn sự thật cross-carrier của rule
  một-thu-một-lần; carriers chỉ REPORT attempt.
- `Api\CodCollectionLedgerInterface` + `Model\CollectionLedger` (recordPending idempotent
  no-downgrade; markSubmitted/markNotSubmitted guards; findFrozenAmount; findCollectedPrior).
- `Api\CodCollectionAttemptInterface` + `Model\CodCollectionAttempt` — định danh attempt
  hiện tại (carrier + provider reference) do caller build.

### Changed
- `CodCollectionResolverInterface::resolve(Order, Shipment, ?CodCollectionAttemptInterface $attempt)`
  — nullable nhưng REQUIRED (không default); `CodCollectionPriorInterface` + `CodCollectionPrior`
  XOÁ (caller-supplied prior bị bypass — audit CONFIRMED; frozen + prior giờ do resolver tự
  đọc ledger).
- `DefaultCodPaymentMethodResolver` (đổi tên từ `ConfiguredCodPaymentMethodResolver`):
  `isCod()` mặc định hardcode `cashondelivery` (Magento core, `Magento_OfflinePayments` —
  method inactive đến khi merchant bật); KHÔNG đọc config.

### Removed
- Toàn bộ config surface + migration: `etc/adminhtml/system.xml` (section "COD Settings"),
  `etc/adminhtml/acl.xml` (`Secomm_Cod::config`), `etc/config.xml`, DataPatch
  `MigrateLegacyCodPaymentMethodConfig` + tests tương ứng (pre-release: chưa có client;
  fresh-install là trạng thái chuẩn — KHÔNG cần migration).

### Tests
`DefaultCodPaymentMethodResolverTest` (6) + `CollectionLedgerTest` (12) mới;
`SingleCollectionCodResolverTest` rewrite 15 tests (frozen replay qua ledger; cross-carrier
prior REJECT; null-attempt không bypass; partial; USD; deposit-paid); xoá AclConsistency +
patch + ConfiguredResolver tests.

## 1.1.0 — 2026-09-23 (TASK-DFGFZ9 phase 2 — COD amount decision ownership, DEC-TASKDFGFZ9-002)

### Added
- `Api\CodCollectionResolverInterface::resolve(Order, Shipment, ?CodCollectionPriorInterface)` +
  `Api\CodCollectionDecisionInterface` (STATUS_COLLECTIBLE|NOT_COD|REJECTED, amount + currency +
  reason CURRENCY_UNSUPPORTED|PARTIAL_SHIPMENT|COD_ALREADY_COLLECTED) + `Api\CodCollectionPriorInterface`
  (opaque `getProviderShipmentReference()` — caller owns identity, resolver owns policy).
- `Model\SingleCollectionCodResolver` — P1 policy: một collection/order bằng `grand_total` theo
  order currency; VND-only (≠ VND → reject, không convert); partial shipment → reject; prior
  khác shipment → reject; non-COD không bị chặn; KHÔNG deposit support. Swappable qua preference.
- `Model\CodCollectionDecision` + `Model\CodCollectionPrior` (final VO, static factories,
  LogicException guards).
- di.xml preference `CodCollectionResolverInterface → SingleCollectionCodResolver`.
- +20 unit tests (7 decision VO, 13 resolver — bao gồm deposit-paid vẫn thu grand_total).

### Không đổi
Identification contract (`CodPaymentMethodResolverInterface` + config + admin section) giữ
nguyên shape — chỉ docblock trỏ sang sibling contract. Phase-1 DataPatch giữ nguyên.

## 1.0.0 — 2026-09-23 (TASK-DFGFZ9 — initial release, COD identification ownership move)

### Added
- `Api\CodPaymentMethodResolverInterface::isCod(string): bool` + `Model\ConfiguredCodPaymentMethodResolver`
  (config `secomm_cod/payment_identification/payment_methods` — comma-separated, exact
  case-sensitive match trên code đã trim, rỗng/malformed → safe false, không default COD method;
  đọc default scope). Port từ `Secomm_ShippingCore` (TASK-STC3NB) khi ownership chuyển — DEC-TASKDFGFZ9-001.
- Admin config: section `secomm_cod` "COD Settings" (tab `sales`, sortOrder 75, global-only) →
  group `payment_identification` "Payment Identification" → field `payment_methods` (`canRestore=1`);
  ACL `Secomm_Cod::config` nest dưới `Magento_Config::config` (self-consistent với system.xml).
- DataPatch `MigrateLegacyCodPaymentMethodConfig` — copy `secomm_shippingcore/cod/payment_methods`
  → path mới: copy-only, destination-wins (không ghi đè lựa chọn đã cấu hình), preserve
  `(scope, scope_id)`, không xoá legacy rows, non-revertable có chủ đích.
- Unit tests (20): 8 resolver (port nguyên semantics), 8 patch (copy scope/default, preserve
  website+store, empty-source skip, dest-wins, re-apply no-op, cache-clean chỉ khi copy,
  empty-DB no-op), 4 AclConsistency (ACL orphan, global-scope, path concatenation, config.xml default).

### Consumers
`Secomm_Ghtk` (sequence += Secomm_Cod) — `DefaultCodAmountResolver` đổi typehint sang contract
này trong cùng change (interface ShippingCore bị xoá không adapter).
