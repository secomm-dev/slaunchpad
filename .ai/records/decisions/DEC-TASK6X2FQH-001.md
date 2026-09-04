---
id: DEC-TASK6X2FQH-001
title: VietQR auto-cancel cron — scope chỉ vietqr_pending, frequency qua preset dropdown + config_path, deadline là cutoff cứng
status: accepted
owners: [dev, tl]
decision_type: architecture
approval_date: 2026-09-04
created: 2026-09-04
last_verified: 2026-09-04
verified_against_commit:
supersedes: []
superseded_by: []
work_items: [TASK-6X2FQH, TASK-N35E28]
---

# Decision Record: VietQR auto-cancel cron (TASK-6X2FQH)

<!-- CANONICAL DECISION STORE. Accepted 2026-09-04 (user as TL authority per session; scripted QC pass, final TL review pending before merge). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

Order VietQR ở `vietqr_pending` ở lại mãi nếu customer bỏ cuộc — cần auto-cancel theo timeout config. Chạm payment (Tier 2). Spec: `SPEC-FEAT-ZKD4VA` AC-024..AC-028 + §4.11.

## Decisions

- **D-1 — Cron chỉ hủy `vietqr_pending` (configured New Order Status), không bao giờ hủy `vietqr_awaiting_payment_confirm`.** Customer đã confirm = tiền có thể đã về; merchant reconcile thủ công (reconciliation đã out-of-scope từ spec gốc). Filter theo method `secomm_vietqr` + status configured — không lọc theo state `pending_payment` (module dùng custom status state `new`, lọc sai sẽ miss toàn bộ).
- **D-2 — Cron frequency = dropdown preset (5/10/15/30/60 phút), option value là literal cron expression; `crontab.xml` dùng `<config_path>` trỏ về path field `payment/secomm_vietqr/autocancel_frequency`.** Loại raw-expression field (expression sai → cron chết im lặng) và fixed schedule (merchant cần tune). Gotcha: `system_file.xsd` giới hạn `config_path` 3 segments → không thể đặt crontab path trong system.xml; hướng ngược lại (field lưu path thường, crontab đọc runtime) không cần backend conversion nào.
- **D-3 — Deadline là cutoff cứng cho customer.** Quá deadline (cron chưa kịp chạy, ≤ 1 interval): ẩn form Submit + notice chuyển tiếp "đơn sẽ được hủy trong ít phút". Không cho submit trong window này — submit → `awaiting_confirm` → cron skip → order thoát auto-cancel mãi mãi.
- **D-4 — Timeout chỉ đơn vị phút (default 1440 = 24h), cutoff tính UTC từ `created_at`.** Không unit selector (2h = 120 phút). Anchor `created_at` đủ vì order sinh thẳng vào `vietqr_pending`; nếu sau này merchant hay reset order cũ về pending → đổi anchor sang status-history timestamp mới nhất.
- **D-5 — Email template đọc payment snapshot trực tiếp + services (Config, Timezone) inject qua layout `<arguments>`; không dùng PaymentInfo block (session-dependent, email chạy trong cron/admin context) và không ObjectManager trong template.**

## Consequences

- Thời điểm hủy thật = deadline + tối đa 1 interval frequency (QC/QA không được file bug "hủy trễ").
- Đổi frequency có thể trễ ~1h để áp dụng (schedule entries sinh trước ~1h).
- Email deadline đọc config lúc gửi (không snapshot) — đổi timeout không ảnh hưởng deadline đơn cũ (deadline tính từ `created_at` của đơn).
- Cron phụ thuộc cron daemon/system crontab chạy thật — không có cron thì không có gì hủy (xem risk trong 06).
