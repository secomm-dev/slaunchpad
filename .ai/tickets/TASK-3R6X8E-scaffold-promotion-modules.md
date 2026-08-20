# TASK-3R6X8E — Scaffold Secomm_Promotion (base tối giản) + Secomm_PromotionMaxDiscount (skeleton)

**Legacy ID:** SL-020 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — foundation scaffold)
**Priority:** High
**Estimate:** ~4h
**Mode:** C (no business logic — chỉ module registration; không chạm risk category)
**Placement:** `app/code/Secomm/Promotion/` (NEW) + `app/code/Secomm/PromotionMaxDiscount/` (NEW)
**Risk tier:** Tier 1
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Dev complete *(2026-08-19: 14 files scaffold theo DEC-FEATJKZM68-001; static checks green; runtime verify green sau khi MySQL up — enable ✓ config.php L452-453, `setup:upgrade` "completed successfully", `setup:di:compile` success 0 error/warning, generated/metadata 0 ref → runtime no-op. Giờ đầu bị block MySQL down (SQLSTATE 2002) — đã resolve. Chờ TL code review (Tier 1). Evidence: [TASK-3R6X8E-evidence](../runtime/evidence/TASK-3R6X8E/TASK-3R6X8E-evidence.md))*
**Specification:** MINI — embedded `## Mini Spec` dưới đây (ID: TASK-3R6X8E) · canonical parent: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) (FULL, VALID) §3 · Plan: [TASK-3R6X8E plan](../plans/TASK-3R6X8E-implementation-plan.md)

## Description

Tạo scaffold 2 modules theo module boundary đã pin ở DEC-FEATJKZM68-001:

- **`Secomm_Promotion`** — base/foundation **tối giản**: `composer.json`, `registration.php`, `etc/module.xml` (v1.0.0, không sequence đặc biệt), `README.md` (mục đích nhóm Promotion), `CHANGELOG.md`. **Không code PHP nào** — anchor cho nhóm Promotion; shared contracts chỉ rút lên khi có module thứ hai (YAGNI, DEC-FEATJKZM68-001 §5).
- **`Secomm_PromotionMaxDiscount`** — skeleton feature module: `composer.json` (require `secomm/promotion`, `magento/module-sales-rule`, `magento/module-quote`), `registration.php`, `etc/module.xml` (sequence: `Secomm_Promotion`, `Magento_SalesRule`, `Magento_Quote`, `Magento_Sales`), `README.md`, `CHANGELOG.md`, `i18n/vi_VN.csv` + `en_US.csv` (placeholder), cây thư mục rỗng `Model/`, `Plugin/`, `Test/`.

PHP 8.2+, `strict_types`, Magento coding standard. KHÔNG tạo interface/class nào chờ dùng (over-engineering bị cấm theo DEC-FEATJKZM68-001).

## Mini Spec

> Embedded Mini-Spec (DEC-TASKE0SK0H-001 — identity = TASK-3R6X8E). Behavioral contract của slice này; business rules canonical thuộc [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md).

### Goal

Tạo foundation cho nhóm Promotion: `Secomm_Promotion` (base anchor tối giản) + `Secomm_PromotionMaxDiscount` (skeleton feature module) — để TASK-33J3RP..025 có nơi đặt code, đúng module boundary DEC-FEATJKZM68-001 §5, với **zero runtime behavior**.

### Expected Behavior

- Sau khi enable: 2 module load thành công, `setup:upgrade` + `setup:di:compile` xanh, và **hệ thống behave đúng như trước khi có module** — không total nào đổi, không listener/plugin/cron/collector nào được đăng ký (runtime no-op chứng minh được bằng generated metadata).
- `Secomm_Promotion` không chứa PHP class nào, `etc/` chỉ có `module.xml` — base KHÔNG mang business logic hay contract chờ dùng.
- `Secomm_PromotionMaxDiscount` khai báo dependency sequence đầy đủ (`Secomm_Promotion`, `Magento_SalesRule`, `Magento_Quote`, `Magento_Sales`) và có i18n placeholders vi_VN + en_US.

### Constraints / Rules

- Không modify `vendor/` (DEC-FEATJKZM68-001, AGENTS §7.1).
- PHP 8.2+, Magento coding standard; format theo precedent project (`Secomm_ShippingCore` header, composer format `secomm/module-addressdropdown`).
- BR-001: mọi string tương lai phải có cả vi_VN + en_US — scaffold dựng sẵn 2 file.
- CODING_RULES [WARN]: mỗi Secomm module phải có README + CHANGELOG.
- YAGNI (DEC-FEATJKZM68-001 §5): KHÔNG tạo interface/class lên base module chờ feature tương lai.

### Out of Scope

Mọi business logic, db_schema, collector, admin UI, tests functional (thuộc TASK-33J3RP..TASK-4HYX6Y) · shared contracts/interfaces trên base module · composer package publishing (module chạy qua `app/code`).

### Acceptance Criteria

- [x] **AC-1:** Cả 2 module enable thành công (`bin/magento module:enable Secomm_Promotion Secomm_PromotionMaxDiscount`); `setup:upgrade` + DI compile (`bin/magento setup:di:compile`) pass không error/warning mới. *(enable: config.php L452–453 = 1; upgrade: "Upgrade completed successfully"; compile: "Generated code and dependency injection configuration successfully.")*
- [x] **AC-2:** `Secomm_Promotion` chứa **duy nhất** composer/registration/module.xml/README/CHANGELOG — không PHP class, không etc config nào khác ngoài module.xml. *(verified by file listing)*
- [x] **AC-3:** `Secomm_PromotionMaxDiscount` module.xml sequence đúng (4 dependencies trên); composer require đúng theo DEC-FEATJKZM68-001 §5.
- [x] **AC-4:** README + CHANGELOG cho cả 2 module theo CODING_RULES [WARN] rule (purpose/features/how-to-work); CHANGELOG 1.0.0 entry.
- [x] **AC-5:** Cart totals + checkout OSC regression: module enable không thay đổi bất kỳ total nào (scaffold không có collector/listener). *(proof: `etc/` chỉ chứa `module.xml` — không sales.xml/events.xml/crontab.xml/di.xml; `grep -rl "Secomm_Promotion" generated/metadata/` → 0 file → không DI wiring nào được generate)*

## AI Pre-review (AGENTS §8.3) — 2026-08-19

- [x] Code matches approach note (ticket Description) — 14 files, đúng boundary DEC-FEATJKZM68-001 §5
- [x] No changes outside requested scope — chỉ 2 module dirs mới + `.ai/` records; 0 file `vendor/`/core đổi
- [x] No hardcoded values (credentials/URLs/env-specific)
- [x] Error handling N/A (scaffold không có runtime code)
- [x] No security surface (không input/output)
- [x] Business rules respected — BR-001 (i18n placeholders cả vi_VN + en_US)
- [x] Tests: N/A scaffold (TASK-5H8WKE/024 sở hữu tests) — static lint thay thế
- [x] Performance: N/A — không DI wiring nào được generate
- [x] Regression risk: THẤP — runtime no-op chứng minh bằng generated metadata

## Risks

Thấp — scaffold thuần. Chú ý duy nhất: naming/vendor prefix `Secomm_` và composer namespace `secomm/promotion` khớp convention project.

## Related

- Plan: [TASK-3R6X8E plan](../plans/TASK-3R6X8E-implementation-plan.md) (retro-canonical) · Mini-Spec: embedded `## Mini Spec` (ID TASK-3R6X8E) · Parent spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §3 · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §5 · Feature: [FEAT-JKZM68](../records/features/FEAT-JKZM68.md)
- Next: TASK-33J3RP (data model) → TASK-5H8WKE (cap engine)
