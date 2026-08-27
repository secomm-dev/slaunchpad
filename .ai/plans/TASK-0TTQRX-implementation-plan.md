# TASK-0TTQRX Implementation Plan — Scaffold Secomm_Tracking (module + config + db_schema)

| Field | Value |
|---|---|
| Specification | tickets/TASK-0TTQRX-tracking-scaffold.md (`## Mini Spec`, embedded — ID TASK-0TTQRX) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md (FULL, VALID) §3 |
| Mode | C · Tier 2 (DB schema — bảng mới) |

> **Status:** Retro-canonical — distilled 2026-08-27 từ work đã dev-complete + runtime verified. Mọi task đánh dấu theo trạng thái thực.

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| Declarative schema only | 2 bảng riêng `secomm_tracking_event` (outbox) + `secomm_tracking_delivery` (log) — không đụng core table; cấm InstallData/UpgradeSchema | spec §3 |
| Default-off toàn bộ | Mọi enable flag = 0; token dùng `Backend\Encrypted` — module là runtime no-op cho tới khi deliberately configured | AC-3/AC-5 |
| Cron shell no-op | Schedule đăng ký `* * * * *` nhưng `OutboxFlush::execute()` rỗng — impl thuộc TASK-VRKJKQ; exit nhanh không giữ lock | ticket |
| Không require Magefan | Browser pipeline đọc dataLayer runtime, không class dependency — composer require chỉ Magento core modules | D1/D2 |
| i18n + README + CHANGELOG | BR-001 vi/en parity; CODING_RULES [WARN] | rules |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Registration + module.xml (sequence Sales/Quote/CatalogSearch/Config/Cron) | `composer.json`, `registration.php`, `etc/module.xml` | ✅ |
| 2 | Admin config tree 4 groups (General/Meta/TikTok/Refund — Meta removed sau DEC-002) | `etc/config.xml`, `etc/adminhtml/system.xml`, `etc/acl.xml` | ✅ |
| 3 | Declarative schema outbox + delivery | `etc/db_schema.xml`, `etc/db_schema_whitelist.json` | ✅ |
| 4 | Cron shell + typed Config reader | `etc/crontab.xml`, `Cron/OutboxFlush.php`, `Model/Config.php` | ✅ |
| 5 | i18n vi/en + README + CHANGELOG | `i18n/*`, docs | ✅ |
| 6 | Runtime verify | enable + upgrade + compile | ✅ user-verified (AC-1) |

## Runtime fixes (lessons)

- **db_schema XSD element order:** `<constraint>` phải nằm trực tiếp dưới `<table>` (không có wrapper `<constraints>`) và **trước** `<index>` — lỗi làm `se:up` fail ở validate phase (không hủy DB). Pattern đối chiếu core: `module-sales/etc/db_schema.xml`.
- **Module từng biến mất khỏi `app/etc/config.php`** sau một lần reset → observer im lặng hoàn toàn. Check `grep Secomm_Tracking app/etc/config.php` khi module "chạy nhưng không fire".

## QC / Evidence

- AC-1 xanh (enable + upgrade + compile, user confirm 2026-08-24 sau XSD fix).
- AC-2 (SHOW INDEX) + AC-4 (token encrypted) — verify bằng mắt qua config form masked display.
