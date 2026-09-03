---
id: TASK-WN0PBT
type: task
title: 'Install, configure, and test Abandoned Cart Email end-to-end'
project_code: SLP
parent: null
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-WN0PBT-abandoned-cart-email.md
risk: low
status: completed
created: 2026-08-27
updated: 2026-08-27
external_refs:
  xcorp: SLP-46
  xcorp_name: '[LC-19] Abandoned Cart Email'
decisions: []
decision_assessment: none-material
components: []
source_areas:
  - app/code/Mageplaza/AbandonedCart/
  - app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv (create)
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified:
supersedes: []
---

# [SLP][TASK-WN0PBT] Install, configure, and test Abandoned Cart Email end-to-end

## Bối cảnh (Context)

Extension Mageplaza AbandonedCart v4.6.9 đã source-committed trong repo, module enabled trong `config.php`, Hyvä compatibility builtin (templates + layout handles). Task này là **config + verify + test end-to-end** — không build code mới ngoài vi_VN translation CSV.

Khách hàng (external PM ref: SLP-46 / LC-19) yêu cầu: cài đặt/config cron, email template theo schedule, restore cart flow, test deliverability.

## Mini Spec

### Goal

Abandoned Cart Email chạy end-to-end: customer bỏ giỏ hàng → hệ thống gửi email nhắc theo schedule → customer click link khôi phục giỏ → giỏ hàng được restore.

### Expected Behavior

1. Magento cron chạy trong Docker, `mageplaza_abandonedcart_cron` execute mỗi phút.
2. Giỏ hàng idle > 5 phút (`time_measure`) được đánh dấu abandoned.
3. Email #1 gửi sau 1 giờ, email #2 gửi sau 24 giờ (template 1 + 2, không coupon).
4. Email render đúng vi_VN khi store locale = vi_VN.
5. Customer click restore link trong email → giỏ hàng được khôi phục với đúng items + quantities.
6. Config bật/tắt qua Admin UI, không sửa core code.

### Constraints / Rules

- Không sửa source Mageplaza (outside 4.6.9 vendor upgrade đã commit riêng).
- SMTP local test qua MailCatcher (port 1080) — không gửi email thật.
- Cron setup là dev-only (crontab trong container, mất khi rebuild).
- vi_VN translation qua `i18n/vi_VN.csv` — không rewrite template HTML.

### Out of Scope

- Custom email design/HTML.
- Client-specific business rules.
- SMS notification.
- Coupon auto-generation.
- Production SMTP config.
- Production cron infrastructure.

### Acceptance Criteria

- AC-001: Main flow 'Abandoned Cart Email' chạy end-to-end (abandon → email → restore → cart).
- AC-002: Config có thể bật/tắt hoặc thay đổi schedule mà không sửa core code.
- AC-003: Error/empty/loading state chính được xử lý (MailCatcher nhận email, Cart Board hiển thị).
- AC-004: QC có thể verify bằng dữ liệu test và không phát sinh regression flow liên quan.

## Plan

File plan: [plan-abandonedcart-email.md](../../plans/plan-abandonedcart-email.md)

Tóm tắt steps:

0. **Vendor upgrade commit** — 7 files 4.6.7→4.6.9 đã staged (Hyvä compat refactor + PHPCS + bug fix).
1. **Verify DB schema** — `bin/magento setup:upgrade`, check 4 bảng + 4 cột.
2. **Bật Magento cron** — cài crontab trong phpfpm container + start cron daemon.
3. **Configure email schedule** — Admin UI: 2 rows (1h + 24h, template 1+2, no coupon). Bật Cart Board.
4. **Localize vi_VN** — tạo `i18n/vi_VN.csv` với translation cho template strings.
5. **Test E2E** — smoke test email → full abandon/restore flow → verify MailCatcher + Cart Board.
6. **Revert test shortcuts** — đảm bảo config production-ready.

## Implementation Notes

- Cron chưa chạy trong Docker (blocker chính). Docker-magento `bin/cron start` cần crontab entry trước.
- SMTP trỏ MailCatcher (`host=mailcatcher:1025`), email logging on.
- Email schedule matrix (`general/email`) là ArraySerialized field — config qua Admin UI đơn giản hơn CLI.
- `time_measure` default = 5 phút. Cho test nhanh có thể giảm, revert trước khi deliver.

## Verification

- [x] AC-001 verified — E2E flow chạy: abandon → email → restore cart OK
- [x] AC-002 verified — config qua Admin UI, không sửa core
- [x] AC-003 verified — MailCatcher nhận email, Cart Board hiển thị
- [x] AC-004 verified — không regression

## Status Log

- 2026-08-27: Mint ID TASK-WN0PBT. Analyze ticket. Plan created. Vendor upgrade 4.6.9 staged (7 files).
- 2026-08-27: Steps 0-4 completed (DB schema, cron, vi_VN translations). Step 3+5 (Admin config + E2E test) done by developer. All AC passed. Task completed.

## Related records

- External: SLP-46 / LC-19
