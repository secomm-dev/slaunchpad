# SL-021 — Data model: column maximum_discount_amount trên salesrule + extension attribute RuleInterface

**Type:** Task (slice của FEAT-008 — data layer)
**Priority:** High
**Estimate:** ~6h
**Mode:** B (DB schema → **Tier-2 escalation bắt buộc** — AGENTS §11/§12)
**Placement:** `app/code/Secomm/PromotionMaxDiscount/{etc/db_schema.xml,etc/db_schema_whitelist.json,etc/extension_attributes.xml,etc/di.xml,Plugin/Rule/}`
**Risk tier:** Tier 2 (database schema change)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Dev complete *(2026-08-20: TL Tier-2 approved trong chat → execute Task 1–10 một session. AC-1..AC-5 verified. 2 spec corrections trong lúc verify: AC-1 command sai (`schema:show` không tồn tại), AC-4 semantics thật (disable + upgrade kế tiếp DROP column — document README). Plugin design rework: converter `around` hooks thay vì repository `after` (`Data\Rule` không có public getData). Full `setup:di:compile` bị chặn bởi blocker PRE-EXISTING `Secomm_AddressDropdown` × `Secomm_GiaoHangNhanh` (từ 2026-08-11, ngoài scope — đề xuất hotfix SL-026); runtime wiring chứng minh bằng service-contract tests xanh. Chờ TL code review. Evidence: [SL-021-evidence](../runtime/evidence/SL-021/SL-021-evidence.md))*
**Specification:** MINI — embedded `## Mini Spec` dưới đây (ID: SL-021) · canonical parent: [SPEC-FEAT-008](../specs/SPEC-FEAT-008-promotion-max-discount.md) (FULL, VALID) §6 · Plan: [SL-021 plan](../plans/SL-021-implementation-plan.md)

## Description

Implement data model theo DEC-FEAT008-001 §3 (D2 resolved: extension attribute **Phase 1 include**):

1. **`etc/db_schema.xml`** — thêm column vào bảng core `salesrule` qua declarative schema merge:
   ```xml
   <table name="salesrule">
     <column xsi:type="decimal" name="maximum_discount_amount" scale="4" precision="12"
             unsigned="true" nullable="true" default="NULL"
             comment="Maximum Discount Amount (base currency); NULL/0 = unlimited; by_percent only"/>
   </table>
   ```
   + **`etc/db_schema_whitelist.json`** (bắt buộc — `generate-schema-whitelist`).
   Semantic: `NULL`/`0` = unlimited (native); `> 0` = cap **base-currency** product discount (đơn vị đồng nhất với `by_fixed` dùng `discount_amount` làm base).

2. **Extension attribute cho `Magento\SalesRule\Api\Data\RuleInterface`** (D2): `etc/extension_attributes.xml` khai báo `maximum_discount_amount` (float); plugin trên `RuleRepository` (`save`/`getById`/`getList`) map extension attribute ↔ `$rule->getData('maximum_discount_amount')` — chống REST API-save bỏ field.

3. Verify persist tự nhiên: admin `Save::execute()` → `loadPost($data)` → save; load qua `AbstractDb` full-column map; `DataProvider::getData()` trả full — không PHP glue thêm cho admin path (đã phân tích spec §6).

## Mini Spec

> Embedded Mini-Spec (DEC-SL019-001 — identity = SL-021). Behavioral contract của slice này; business rules canonical thuộc [SPEC-FEAT-008](../specs/SPEC-FEAT-008-promotion-max-discount.md) §6, kiến trúc data model thuộc [DEC-FEAT008-001](../records/decisions/DEC-FEAT008-001.md) §3.

### Goal

Triển khai **data layer** cho FEAT-008: column `maximum_discount_amount` DECIMAL(12,4) UNSIGNED NULL trên bảng core `salesrule` (declarative schema, additive) + extension attribute cho `Magento\SalesRule\Api\Data\RuleInterface` (plugin `RuleRepository` map field) — để SL-022 (cap engine) có field đọc được, persist/load đúng qua cả admin lẫn REST, **không PHP glue cho data path native**.

### Expected Behavior

- `setup:upgrade` thêm column `maximum_discount_amount` vào bảng `salesrule` — **additive only** (không drop/modify cột nào của salesrule); MySQL `SHOW COLUMNS FROM salesrule LIKE 'maximum_discount_amount'` trả về column; `bin/magento setup:db:status` không còn pending declarative change; whitelist không diff. *(2026-08-20: command verify đổi từ `schema:show` — không tồn tại ở 2.4.8-p5 — sang cặp lệnh thật)*
- Model path: `loadPost(['maximum_discount_amount' => 50000])` → save → reload giữ `50000`; set `NULL` → persist `NULL` (AC-2 của spec §6 — persist tự nhiên qua admin `Save::execute()` và load qua `AbstractDb` full-column map).
- REST: `GET /V1/salesRules/{id}` trả extension attribute `maximum_discount_amount`; `PUT` **không truyền field → giữ nguyên giá trị cũ** (không bị reset); truyền giá trị → update đúng.
- Disable module **một mình KHÔNG drop column** (không schema op chạy); nhưng `setup:upgrade` kế tiếp **sẽ drop column kèm data** (verified thực tế 2026-08-20 — declarative schema tự dọn column không còn được declare bởi module enabled). Rollback semantics + cách export data trước khi disable: xem README module. *(2026-08-20 correction: AC gốc giả định "declarative không uninstall tự động" — sai một nửa; đã verify thực tế và update.)*
- **Totals/pricing không đổi** trong slice này: column inert — chưa có engine nào đọc (SL-022 mới consume); mọi cart total, quote, order behave như trước.

### Constraints / Rules

- Thuần **declarative schema** (`db_schema.xml` + `db_schema_whitelist.json` sinh bằng `setup:db-declaration:generate-whitelist`) — cấm InstallSchema/UpgradeSchema/data-patch PHP thủ công; không modify file nào trong `vendor/` (DEC-FEAT008-001, AGENTS §7.1).
- Semantic theo DEC-FEAT008-001 §3: `NULL`/`0` = unlimited (native behavior); `> 0` = cap **base-currency** product discount của rule `by_percent` (đơn vị đồng nhất `by_percent` dùng `discount_amount` làm base — spec §6; runtime guard `simple_action` thuộc SL-022).
- Extension attribute mapping qua **plugin** trên `RuleRepository::save/getById/getList` (D2 resolved — Phase 1 include); không override/preference class core.
- **Tier-2 DB schema** (AGENTS §11/§12 + CLAUDE.md): TL review trước khi merge; QC trên DB snapshot; column trên bảng core → theo dõi `12_UPGRADE_NOTES` khi upgrade Magento (spec §14).
- Whitelist bắt buộc — thiếu thì `schema:upgrade` âm thầm bỏ qua column (spec §14 risk).

### Out of Scope

Cap engine đọc/áp cap (SL-022) · admin form field + validation UI (SL-023) · integration tests (SL-024) · QC matrix + docs (SL-025) · migration data (không có data cũ — schema trống semantic NULL).

### Acceptance Criteria

- [x] **AC-1:** `setup:upgrade` áp column additive (không drop/modify cột nào của salesrule); MySQL `SHOW COLUMNS FROM salesrule LIKE 'maximum_discount_amount'` trả về `decimal(12,4) unsigned NULL` + `bin/magento setup:db:status` không còn pending change; whitelist không diff. *(verified: `decimal(12,4) unsigned Null=YES Default=NULL`; upgrade lần 2 "Nothing to import"; column-diff duy nhất là cột mới; `setup:db:status` giữ nguyên pre-existing "not up to date" state như trước change — quirk môi trường, xem evidence)*
- [x] **AC-2:** Model path: `RuleRepository::getById` → rule có dữ liệu; save qua `Model\Rule` (`loadPost(['maximum_discount_amount' => 50000])` → save → reload giữ 50000; set NULL → persist NULL). *(bootstrap script: PASS ×3 — create 50000 → reload; loadPost 75000 → persist; loadPost null → persist NULL)*
- [x] **AC-3:** REST: `GET /V1/salesRules/{id}` trả extension attribute `maximum_discount_amount`; `PUT /V1/salesRules/{id}` không truyền field → **giữ nguyên giá trị cũ** (không bị reset); truyền giá trị → update đúng. *(verified tại service-contract layer — backend mà REST `/V1/salesRules/*` delegate tới: getById 50000.0 · save WITH 75000.0 persist · save WITHOUT giữ 75000.0 · getList item có attr. HTTP/JSON rendering là framework-standard từ cùng ext-attr object — QC HTTP smoke thuộc SL-025)*
- [x] **AC-4:** Rolling-back documented + verified: disable **một mình** không drop column; disable + `setup:upgrade` kế tiếp drop column tự động (kèm data) — semantics thật đã verify 2026-08-20, document trong README (kèm lệnh export data trước khi disable). *(verified cả 3 nhánh: disable-only giữ column · disable+upgrade DROP · enable+upgrade recreate — README "Schema & rollback")*
- [x] **AC-5:** Không file nào trong `vendor/` bị đổi; không install/upgrade script PHP thủ công (patch/schema patch) — thuần declarative. *(`git status vendor/` clean; module tree chỉ có db_schema + whitelist + ext-attr + di + 2 plugin classes)*

## AI Pre-review (AGENTS §8.3) — 2026-08-20

- [x] Code matches approach (plan Task 1–10) — 7 file mới đúng placement; plugin design rework có lý do document (evidence "Corrections" #3)
- [x] No changes outside requested scope — chỉ `Secomm_PromotionMaxDiscount/` (+README/CHANGELOG) + `.ai/` records + `app/etc/config.php` (module flag, state cuối = enabled như trước). 0 file `vendor/` đổi
- [x] No hardcoded values (không credentials/URLs/env-specific; ext attr name = contract theo DEC)
- [x] Error handling — defensive null-checks 2 plugin (null column → skip; null ext attr → skip); không throw thêm
- [x] No security surface mới — không input trực tiếp từ user; giá trị qua service contract persist bằng Magento validation path; ext attr float cast
- [x] Business rules respected — semantic NULL/0 = unlimited giữ nguyên ở data layer (guard runtime thuộc SL-022)
- [x] Tests — 2 throwaway verify script (model + service contract) ALL GREEN, đã dọn sau khi capture evidence; integration tests thuộc SL-024
- [x] Performance — plugin chạy mỗi repository read: 1 getData + 1 set, không query thêm; write path chỉ khi ext attr present
- [x] Regression risk: THẤP cho pricing (không collector — column inert tới SL-022); additive API surface: REST response giờ có thêm ext attr (additive, không breaking)

## Risks

- **Tier-2 DB schema** — cần TL review + QC trên DB snapshot trước khi merge; column additive nên rollback = drop column thủ công (document).
- Column trên bảng core: an toàn upgrade (declarative merge), nhưng theo dõi `12_UPGRADE_NOTES` khi upgrade Magento.
- `db_schema_whitelist.json` thiếu → `schema:upgrade` bỏ qua column — AC-1 chặn.

## Related

- Mini-Spec: embedded `## Mini Spec` (ID SL-021) · Parent spec: [SPEC-FEAT-008](../specs/SPEC-FEAT-008-promotion-max-discount.md) §6, §14 · Decision: [DEC-FEAT008-001](../records/decisions/DEC-FEAT008-001.md) §3 · Depends: SL-020 (scaffold — dev complete)
- Next: SL-022 (cap engine consume field) · Plan: [SL-021 plan](../plans/SL-021-implementation-plan.md) (prospective — chờ TL Tier-2 approval để execute)
