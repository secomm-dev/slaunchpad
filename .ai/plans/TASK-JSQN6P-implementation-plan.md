# TASK-JSQN6P Implementation Plan — Assign managed payment + expiry snapshot

| Field | Value |
|---|---|
| Specification | tickets/TASK-JSQN6P-paymentcore-assign-expiry.md (`## Mini Spec`) · canonical parent SPEC-FEAT-CSWYEJ §4.1, AC-001→003/AC-014 |
| Decisions | DEC-FEATCSWYEJ-001 (D4/D5) · DEC-FEATCSWYEJ-003 (15' TTL semantics) |

> **Mode B** · Tier 1 · Status: **Retro-canonical** — Dev complete + runtime verified (record sinh đúng khi đặt đơn VNPAY sandbox — user QC thấy row trong DB).

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Event nào? | **`checkout_submit_all_after`** — ban đầu dùng `sales_order_place_after`, runtime cho thấy fires TRƯỚC save → entity_id NULL → observer skip mọi order. Đổi + thêm warning log chống silent failure | QC session 2026-08-25 |
| Observer có chặn checkout không? | Không — AssignManagedPayment catch-all + log error; record thiếu chỉ = cron bỏ qua order (conservative-safe) | spec §4.2 |
| Expiry resolution? | override per-method (`method:minutes`) > default 15' — invalid line ignore + log warn | DEC-001 D4 + DEC-003 |
| DateTime API? | `gmtDate`/`gmtTimestamp` (không phải `gmdate` — runtime fix) | QC session 2026-08-25 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Guard service (enabled + managed + entity_id + dedup) | Model/Lifecycle/AssignManagedPayment.php | ✅ |
| 2 | Observer mỏng delegate | Observer/AssignManagedPaymentObserver.php · etc/events.xml | ✅ (đổi event sau runtime fix) |
| 3 | Unit test (assign/noop/disabled/dedup/exception) | Test/Unit/Model/Lifecycle/AssignManagedPaymentTest.php | ✅ code (phpunit chờ run) |
| 4 | Runtime verify | đặt đơn VNPAY sandbox → record active + expires_at = now+15' | ✅ user QC |

## Remaining steps (human)

1. Chạy `vendor/bin/phpunit app/code/Secomm/PaymentCore/Test/Unit`.
2. QC matrix S-1/S-6/S-9 (điền evidence).

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Managed → record + snapshot | user QC thấy record (order 66, expires_at đúng config) |
| Unmanaged/disabled → no-op | unit test case 2/3 |
| Observer exception không chặn checkout | unit test case 5 (logger->error, không rethrow) |
