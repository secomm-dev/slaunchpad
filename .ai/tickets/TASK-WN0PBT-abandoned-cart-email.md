# TASK-WN0PBT — Abandoned Cart Email: config, localize vi_VN, test end-to-end

**External ref:** SLP-46 / LC-19

**Type:** Task (config + localize + E2E test — extension đã cài sẵn, không build mới)
**Priority:** P2 (Medium)
**Estimate:** 4–8h
**Mode:** C (config/test, không chạm high-risk area — AGENTS §9/§12)
**Placement:** `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` (create) + Admin config (DB) + Docker crontab (container)
**Risk tier:** Low
**Author:** AI draft · **Date:** 2026-08-27 · **Status:** Completed *(Steps 0-5 done, all AC passed, cron verified, E2E tested via MailCatcher)*

## Description

Cài đặt, cấu hình và verify tính năng Abandoned Cart Email end-to-end sử dụng extension **Mageplaza AbandonedCart v4.6.9** (source-committed, Hyvä compatibility builtin).

Extension đã cài sẵn trong repo (`app/code/Mageplaza/AbandonedCart/`), module enabled, DB schema đã deployed. Task này bao gồm: (0) commit vendor upgrade 4.6.7→4.6.9, (1) verify DB schema, (2) bật Magento cron trong Docker, (3) cấu hình email schedule qua Admin UI, (4) localize email templates vi_VN, (5) test end-to-end.

**Schedule**: 2 email — sau 1 giờ (template 1) và sau 24 giờ (template 2). Không auto-coupon. Không SMS.

**SMTP**: Mageplaza SMTP → MailCatcher (dev: mailcatcher:1025, web UI port 1080).

## Acceptance Criteria

- [x] **AC-001 (E2E flow):** Main flow 'Abandoned Cart Email' chạy end-to-end: customer bỏ giỏ hàng → email #1 nhận sau 1h (template: abandoned_cart_1) → email #2 nhận sau 24h (template: abandoned_cart_2) → click restore link trong email → giỏ hàng khôi phục đúng items + quantities → checkout bình thường.
- [x] **AC-002 (Config toggle):** Config bật/tắt module và thay đổi email schedule (timing, template, số email) qua Admin UI (Stores > Configuration > Mageplaza > Abandoned Cart) mà không sửa core code.
- [x] **AC-003 (State handling):** Error/empty/loading state chính xử lý: MailCatcher nhận email (SMTP deliverability OK), Cart Board dashboard hiển thị abandoned carts, email log hiển thị trong Admin (Marketing > Abandoned Cart > Abandoned Carts).
- [x] **AC-004 (No regression):** QC verify bằng dữ liệu test — không phát sinh regression: checkout flow bình thường, giỏ hàng hoạt động, không JS/console errors, đăng ký/đăng nhập unaffected.

## Thực hiện

### Step 0 — Vendor upgrade commit
7 files 4.6.7→4.6.9 staged (Hyvä compat refactor + PHPCS + image URL bug fix + encrypted email param). Commit riêng: `chore(abandonedcart): update to v4.6.9 with Hyvä compat refactor`.

### Step 1 — Verify DB schema
`bin/magento setup:upgrade --keep-generated` → 4 bảng mới + 4 cột alter verified.

### Step 2 — Bật Magento cron
Crontab trong phpfpm container (app user), PHP path `/usr/local/bin/php`. Cron daemon started. Log confirms jobs executing.

### Step 3 — Configure email schedule (Admin UI, manual)
- General: enabled=Yes, 2 schedule rows (1h + 24h, template 1+2, no coupon), stop_sending_email=Yes
- Report: time_measure=5, cart_board=Yes

### Step 4 — Localize vi_VN
Tạo `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` — 24 translation strings cho templates 1+2 + partials (productlist, related_product, unsubscribe).

### Step 5 — Test E2E (manual)
Smoke test email từ Admin + full abandon/restore flow + MailCatcher verify + Cart Board verify.

## Out of Scope

- Custom email HTML/CSS design
- Client-specific business rules
- SMS notification (extension hỗ trợ, default disabled)
- Auto-apply coupon code
- Production SMTP configuration
- Production cron infrastructure
- Email templates 3-5 (chỉ dùng 1+2)
- Third-party integration (Klaviyo/Mailchimp/Dotdigital)

## Dependencies

| Item | Type | Status |
|------|------|--------|
| Mageplaza AbandonedCart v4.6.9 license | License | Cần verify ($149/năm Community) |
| Mageplaza_Core ^1.5.13 | Module | ✅ Installed |
| Mageplaza SMTP | Module | ✅ Enabled (MailCatcher) |
| Magento cron (Docker) | Infra | ✅ Running |

## Related artifacts

- **Record:** [TASK-WN0PBT.md](../records/tasks/TASK-WN0PBT.md)
- **Spec:** [SPEC-TASK-WN0PBT-abandoned-cart-email.md](../specs/SPEC-TASK-WN0PBT-abandoned-cart-email.md)
- **Plan:** [plan-abandonedcart-email.md](../plans/plan-abandonedcart-email.md)
- **Reference:** `project-context/11_CRON_QUEUE_INDEXER_CACHE.md` (cron jobs), `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md` (cron throughput risk)
