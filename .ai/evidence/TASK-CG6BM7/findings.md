---
id: EVIDENCE-TASK-CG6BM7-FINDINGS
work_item: TASK-CG6BM7
spec: SPEC-TASK-CG6BM7-zalopay-postfix-audit
kind: audit-findings
baseline_commit: a48de3cac477cada0882974151db7765c554aa25
pre_existing_zalopay_commit: 181dc1d0a0aae8acf825ea08f73848c704c0ef76
created: 2026-09-16
---

# Audit findings — Secomm_ZaloPay post-fix audit (TASK-CG6BM7)

Phạm vi: `app/code/Secomm/ZaloPay` trên baseline `a48de3c` (đã chứa commit prior-implementation `181dc1d`).
Đối chiếu contract chính thức docs.zalopay.vn (refund ASYNC + `v2/query_refund`; MAC query = `app_id|m_refund_id|timestamp`; `v2/query_refund` response KHÔNG có `refund_id`).

## Đã fix (BLOCKER/HIGH — xác nhận trong audit này)

| # | Vấn đề | Mức | Fix + bằng chứng |
|---|--------|-----|------------------|
| F1 | `"abc"` → 0.0 âm thầm trong `Rate::toNumericAmount` (biến đổi tiền sai số lớn nhất) | BLOCKER | Strict parser; sai → `LocalizedException`. RATE 8–15 (`Test/Unit/Gateway/Helper/RateTest.php`) |
| F2 | Email xác nhận có thể gửi trước payment verified/commit (§3) | HIGH | Email chỉ sau commit, FINALIZED-duplicate backfill có guard `email_sent`; exception email không rollback. EMAIL 1–7 (`Test/Unit/Service/OrderFinalizerTest.php`) |
| F3 | RefundCommand ném `\Exception`/CommandException — `Payment::refund` chỉ bắt `LocalizedException` → raw provider/transport text lọt UI | HIGH | LocalizedException-only; message từ provider map trên response hiện tại; raw text chỉ log. REFUND CMD 16–23 (`Test/Unit/Gateway/Command/RefundCommandTest.php`) |
| F4 | Refund cron: vòng query vô hạn cho provider FAIL; chọn lại mọi `is_processed=0` mãi mãi | HIGH | Bounded budget `query_attempts` (96 ≈ 24h với cron */15 — khớp `etc/crontab.xml` `secomm_zalopay_refund_cronjob`), FAIL terminal + `last_error` evidence, per-item `try/catch (\Throwable)`. REFUND CRON 24–30 (`Test/Unit/Cron/RefundCronjobTest.php`) |
| F5 | `ResponseMessagesHandler` đọc `return_code` không có guard; code 3 (PROCESSING) bị gắn cờ fraud | HIGH | `readReturnCode` null-safe; 1→approve; 2/unknown→error+fraud; 3→message, KHÔNG fraud; missing/non-numeric → no mutation. RESPONSE 31–34 (`Test/Unit/Gateway/Response/ResponseMessagesHandlerTest.php`) |
| F6 | `TransactionRefundHandler` set transaction id từ `refund_id` không kiểm tra — nhưng `v2/query_refund` KHÔNG trả `refund_id` theo docs | MEDIUM→fix | Guard null/empty trước `setTransactionId`; close-parent theo invoice `canRefund()`. |

## Ghi nhận KHÔNG fix (MEDIUM/LOW — theo §10 chỉ fix BLOCKER/HIGH)

| # | Finding | Mức | Lý do không fix trong task này |
|---|---------|-----|-------------------------------|
| M1 | `RefundQueryCommand::execute()` còn TODO stub (không được gọi trong runtime path — `getRefundQuery()` là entry thực) | MEDIUM | Dead code, không ảnh hưởng runtime; để TL quyết xoá hay implement |
| M2 | Dead plugin `generateMac` trên virtualType `ZaloPayRefundDataBuilder` (RefundCommand build MAC riêng) | MEDIUM | Không chạy trong path hiện tại; xoá là thay đổi DI diện rộng → cần TL duyệt |
| M3 | `RefundProcessor::processRefundStatus` map sai lệch docs: `-1` map "System error." (docs: REFUND_PENDING); thiếu 0/−32/−101/−999; trùng lặp −24/−25/−26 | MEDIUM | Hiển thị admin-only; các path hệ thống (cron last_error, RefundCommand message) dùng map này cho evidence — không đỉnh đến hành vi thanh toán. Đã đề xuất trong spec §2.5 cho task sau |
| M4 | Refund instant-SUCCESS (return_code 1 ngay) không persist row → cron không có evidence trail | LOW | Creditmemo đã REFUNDED đồng bộ; row chỉ dùng cho async pending. Mở rộng schema thêm cột nữa là over-engineering |
| M5 | `m_refund_id` re-submit residual risk (double-submit refund cùng creditmemo trước khi cron chốt) | MEDIUM | Cần claim/lock ở Payment layer — DEC-TASKCG6BM7-001 đã đánh giá và NOT-AUTHORIZED scope này |
| M6 | PHPCS +5 warnings style mới (MethodAnnotationStructure x4, VariableTranslation x2 từ message động provider-map; bù −1 LineLength) | LOW | Style-only, 0 error; VariableTranslation là hệ quả cố ý của message động từ provider map (không phải i18n target) |

## Validator .ai

- `.ai` validator: **26 FAIL pre-existing trên baseline `a48de3c`** (liên quan records/audit khác, ngoài scope). Các record của TASK-CG6BM7 (TASK/SPEC/PLAN/DEC-001/DEC-002) không tạo FAIL mới.
