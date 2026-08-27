# TASK-0TTQRX — Scaffold Secomm_Tracking (module skeleton + config + db_schema)

**Type:** Task (slice của FEAT-31X6N2 — foundation scaffold)
**Priority:** High
**Estimate:** ~6h
**Mode:** C (registration + config + declarative schema; observer logic = 0; nhưng db_schema chạm Tier-2 DB → TL review bắt buộc)
**Placement:** `app/code/Secomm/Tracking/` (NEW)
**Risk tier:** Tier 2 (DB schema — bảng outbox/delivery mới)
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete — chờ verify cuối (AC-2 SHOW INDEX + AC-4 encrypted check) *(2026-08-24: 15 files scaffold; static xanh — XML parse ×6, whitelist khớp schema 100%, CSV vi/en parity 29 entries; fix 1 lỗi db_schema XSD element order trong se:up đầu; `setup:upgrade` xanh sau fix. Runtime verify do user chạy — AC-1 enable+upgrade CONFIRMED; di:compile + SHOW INDEX pending)*
**Specification:** MINI — embedded `## Mini Spec` dưới đây (ID: TASK-0TTQRX) · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) (FULL, VALID) §3 · Plan: [TASK-0TTQRX plan](../plans/TASK-0TTQRX-implementation-plan.md)

## Description

Scaffold module `Secomm_Tracking` theo module boundary DEC-FEAT31X6N2-001 (D2):

- `composer.json` (require: `magento/module-sales`, `magento/module-quote`, `magento/module-catalog-search` — không require Magefan: interaction qua dataLayer output, không class dependency), `registration.php`, `etc/module.xml` (sequence tương ứng).
- `etc/config.xml` (default: mọi vendor disabled, refund disabled, test_mode off, debug off) + `etc/adminhtml/system.xml` + `acl.xml` — config tree `Secomm → Tracking` (tabs: General enable, GA4/GTM, Meta CAPI, TikTok Events API, Refund, Debug) — credentials field dùng `\Magento\Config\Model\Config\Backend\Encrypted`.
- `etc/db_schema.xml` + `db_schema_whitelist.json`: bảng `secomm_tracking_event` (outbox: entity_id PK, event_name, event_id UNIQUE với vendor, payload JSON, vendors, status pending/sent/failed/skipped, attempts, next_attempt_at, created_at, updated_at) + `secomm_tracking_delivery` (log: event_id, vendor, direction, http_status, response_summary, created_at).
- `Cron/OutboxFlush.php` shell trống (throw-safe) + `etc/crontab.xml` (`* * * * *`) — impl thuộc TASK-VRKJKQ.
- `i18n/vi_VN.csv` + `en_US.csv` (admin labels, BR-001).
- `README.md` + `CHANGELOG.md` (CODING_RULES [WARN]).

## Mini Spec

> Embedded Mini-Spec (DEC-TASKE0SK0H-001 — identity = TASK-0TTQRX). Behavioral contract của slice; canonical thuộc [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md).

### Goal

Foundation cho FEAT-31X6N2: module `Secomm_Tracking` load được, config admin đầy đủ (default-off toàn bộ), schema outbox/delivery tạo bảng — **zero tracking behavior**.

### Expected Behavior

- Enable + `setup:upgrade` + `setup:di:compile` xanh; hệ thống behave như trước khi có module (không observer/plugin/cron logic — cron shell no-op).
- 2 bảng mới tồn tại với index đúng (`event_id`, `status`+`next_attempt_at` composite cho flush query).
- Admin config UI truy cập được (role Secomm → Tracking), mọi flag default OFF → runtime không gửi gì dù module enabled.

### Constraints / Rules

- Không modify `Magefan/*`, `Mageplaza/*`, `vendor/*` (DEC-FEAT31X6N2-001; AGENTS §7.1).
- Declarative schema only — không migration thủ công (InstallData/UpgradeSchema bị cấm).
- Token/credential config field: backend model Encrypted; giá trị default rỗng.
- PHP 8.2+, `strict_types`, Magento coding standard.

### Out of Scope

Mọi logic: normalizer, hasher, adapters, observers, cron flush, dataLayer plugin (TASK-NNKTRM/E0NG8Z/VRKJKQ/8FZ8YX) · admin delivery grid UI (làm ở TASK-WY5JRN nếu cần) · GTM container setup (config-side checklist TASK-E0NG8Z).

### Acceptance Criteria

- [ ] **AC-1:** `bin/magento module:enable Secomm_Tracking` + `setup:upgrade` + `setup:di:compile` pass; config.php thêm entry `Secomm_Tracking => 1`.
- [ ] **AC-2:** 2 bảng `secomm_tracking_event` + `secomm_tracking_delivery` tồn tại sau upgrade, UNIQUE (event_id, vendor) đúng, index composite có mặt (SHOW INDEX verify).
- [ ] **AC-3:** Admin Stores → Config → Secomm → Tracking mở được, đủ 6 section; default mọi enable = 0 (config.xml verify).
- [ ] **AC-4:** Token fields lưu encrypted vào DB (core_config_data giá trị obfuscated khi đọc raw).
- [ ] **AC-5:** Runtime no-op: với module enabled + mọi flag off, place 1 order test → không row nào xuất hiện ở 2 bảng; checkout response time không đổi.

## Approach

1. Tạo cây module theo Description (file-by-file, theo precedent scaffold `Secomm_PromotionMaxDiscount` TASK-3R6X8E).
2. `bin/magento module:enable Secomm_Tracking && bin/magento setup:upgrade && bin/magento setup:di:compile`.
3. Verify bảng + index qua `SHOW CREATE TABLE`.
4. Verify admin config + default-off qua core_config_data + UI click-through.
5. Static check: grep không có class reference tới Magefan/Mageplaza namespace.

## Risks

- db_schema trực tiếp trên DB dev (Tier-2): rollback = disable module + drop 2 bảng (tên riêng `secomm_tracking_*`, không đụng core table).
- Cron no-op mỗi phút phải chắc chắn exit nhanh (không lock).

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §3 · **DEC:** [DEC-FEAT31X6N2-001](../records/decisions/DEC-FEAT31X6N2-001.md)