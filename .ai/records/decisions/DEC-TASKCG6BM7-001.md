---
id: DEC-TASKCG6BM7-001
title: 'ZaloPay refund cron: bounded retry bằng 2 cột schema mới (query_attempts/last_error) + terminal-FAIL explicit; KHÔNG thêm claim/lock architecture'
status: proposed
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-16
created: 2026-09-16
last_verified: 2026-09-16
verified_against_commit: a48de3cac477cada0882974151db7765c554aa25
supersedes: []
superseded_by:
work_items: [TASK-CG6BM7]
---

# Decision: Bounded refund-query lifecycle trên zalo_pay_refund (TASK-CG6BM7)

## Bối cảnh

Audit baseline `a48de3ca` (§4 của yêu cầu TL) xác nhận `RefundCronjob` hiện tại: một try/catch bọc
toàn bộ foreach (một item hỏng chặn cả batch), không budget (`is_processed = NOT_PROCESSED` được
query vô hạn), provider FAIL (return_code 2) bị `continue` âm thầm → loop mãi mãi, không bằng chứng
lỗi, và đọc `return_code` không an toàn key. Bảng `zalo_pay_refund` hiện không có cột attempt/status
nào ngoài `is_processed` boolean.

## Quyết định

1. **Schema mở rộng tối thiểu — 2 cột** trên `zalo_pay_refund`: `query_attempts` smallint unsigned
   NOT NULL DEFAULT 0, `last_error` text NULL. Justification: cần đúng 2 signals — budget truy vấn và
   bằng chứng lỗi cuối. Precedent đã tồn tại trong chính module:
   `secomm_zalopay_payment_attempt.recovery_attempts/recovery_exhausted/last_error` (cùng pattern
   bounded recovery). Không bảng mới, không config mới — cap là constant `MAX_QUERY_ATTEMPTS = 96`
   (~24 giờ ở cron 15 phút, generous với async refund của provider).
2. **Terminal state machine** (mỗi lần query): `return_code 1` → creditmemo STATE_REFUNDED (save qua
   repository) + row `is_processed = PROCESSED` + `last_error = NULL`; `return_code 2` → **terminal
   FAIL**: saturate `query_attempts = 96` + `last_error` = message an toàn từ RefundProcessor map,
   row giữ NOT_PROCESSED → tự rơi khỏi selection qua filter `query_attempts < 96` — KHÔNG query
   lại, KHÔNG loop, KHÔNG false-success, log critical một lần; `return_code 3`/unknown/missing
   code/transport exception/payload dị dạng → chỉ tăng `query_attempts` +1 (evidence qua log;
   `last_error` là terminal-only marker — chỉ FAIL ghi). Đạt cap → explicit exhaustion (log
   critical).
3. **Không claim/lock architecture** cho refund cron (khác PaymentRecovery): query refund là
   idempotent GET (v2/query_refund theo m_refund_id); hai worker cùng thấy return_code=1 chỉ dẫn tới
   cùng end-state (creditmemo REFUNDED + row PROCESSED) — benign idempotency. Thêm claim là over-
   engineering cho batch nhỏ. Concurrency risk được chấp nhận và documented.
4. **Cleanup-an-by-design**: `RefundCleanupCronjob` chỉ xoá PROCESSED; terminal-FAIL rows là
   NOT_PROCESSED → bằng chứng không bao giờ bị xoá. PROCESSED rows có `last_error` cleared khi
   success → cleanup không mất bằng chứng lỗi cuối.
5. **m_refund_id không đổi**: cron CHỈ query bằng m_refund_id đã lưu trong `additional_information`
   (idempotent); việc sinh m_refund_id mới chỉ xảy ra khi admin tạo creditmemo refund mới — provider
   dedup qua bookkeeping zp_trans_id/amount (sub_return_code -23 duplicate / -14 invalid amount).
   Residual risk documented trong evidence.

## Lựa chọn đã loại bỏ

- Reuse `is_processed` thành enum: phá contract `RefundInterface::PROCESSED/NOT_PROCESSED` hiện có
  (DB boolean + code phụ thuộc), migration semantics phức tạp hơn 2 cột mới.
- Thêm bảng `zalo_pay_refund_query_log`: schema bloat không cần thiết.
- Config cho cap: YAGNI — cap 96 là hằng số nghiệp vụ, không phải thay đổi theo môi trường.

## Hệ quả

- db_schema.xml + whitelist + RefundInterface/RefundData mở rộng (2 getter/setter pairs).
- Cron selection: `is_processed = 0 AND query_attempts < 96`.
- `setup:upgrade` cần chạy trên môi trường deploy (documented trong evidence).
