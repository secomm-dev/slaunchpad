# TASK-Q2BAHW Implementation Plan — Console command `paymentcore:expire:run` (QC trigger)

| Field | Value |
|---|---|
| Specification | tickets/TASK-Q2BAHW-paymentcore-expire-command.md (`## Mini Spec`) · Mode C |
| Decisions | DEC-FEATCSWYEJ-004 (logic chạy theo 2-layer guard) |

> **Mode C** · Tier 1 · Status: **Retro-canonical** — Dev complete + **runtime verified đầy đủ** (user chạy --dry-run, order-filter, full-run trên sandbox; output đúng từng nhánh).

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Code path riêng? | KHÔNG — command gọi đúng `ExpirePayments::execute()` của cron (không drift) | ticket Mini Spec |
| Filter order bỏ những gì? | Bỏ expiry-window check (QC nhắm đơn đã biết) — GIỮ status=active + toàn bộ guard trong CancelExpiredOrder | ticket |
| Dry-run đọc gì? | `getCandidates()` public mới — chỉ đọc collection, không đụng DB write | — |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Command (dry-run + order-id arg) | Console/Command/ExpirePaymentsCommand.php | ✅ |
| 2 | ExpirePayments optional filter + getCandidates | Model/Lifecycle/ExpirePayments.php | ✅ |
| 3 | DI registration | etc/di.xml (CommandList) | ✅ |
| 4 | Runtime verify | `--dry-run` list đúng; `<order_id>` xử lý 1 đơn; full-run qua pipeline + log channel riêng (sau fix Handler) | ✅ user QC 2026-08-25 |

## Remaining steps (human)

QC matrix C-1/C-2 điền evidence.

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Dry-run không đổi DB | user QC output "1 candidate(s) — nothing processed" |
| Cùng logic cron | cùng method execute() |
| Order-filter an toàn | đơn 66 chạy qua lock + state trước khi chạm cancel |
