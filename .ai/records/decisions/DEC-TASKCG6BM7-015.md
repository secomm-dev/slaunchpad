# DEC-TASKCG6BM7-015 — Round 7: PSLP không bao giờ thành UNKNOWN + cron selection terminal-explicit (F32/E2 + F33/E1)

Date: 2026-09-17. Status: accepted.

## D1 — Tách hai loại budget: provider reconciliation vs local finalize

`provider_success_local_pending` nghĩa là provider SUCCESS ĐÃ CHỐT, chỉ còn accounting cục bộ chưa xong — recovery là thuần LOCAL (không `/refund`, không `query_refund`); budget 96 chỉ có ý nghĩa cho provider reconciliation. Lỗi cũ: `consumeQueryBudget` flip `>= 96` sang `unknown` ⇒ hủy ngữ nghĩa "money đã out", và selection `query_attempts < 96` đá PSLP khỏi cron ⇒ tiền treo vĩnh viễn. Chọn: (1) `consumeQueryBudget` KHÔNG BAO GIỜ đổi `refund_state` (chỉ increment + evidence; exhaustion = critical log); (2) selection thêm nhánh OR `refund_state='provider_success_local_pending'` ⇒ PSLP luôn được chọn finalize bất kể attempts; (3) exhaustion của row non-PSLP = drop khỏi selection ở state hiện tại (unknown/processing/provider_request_started giữ block + claim — quarantine, không bao giờ tự hiểu là "an toàn để hoàn lại").

## D2 — Cron selection explicit theo state, terminal bị loại ở tầng SQL

Selection mới: `is_processed=0 AND refund_state IN (initiating, provider_request_started, processing, unknown, provider_success_local_pending) AND (query_attempts < 96 OR refund_state = PSLP)` — `confirmed_success`/`confirmed_fail` không bao giờ vào provider query flow (thay vì dựa side-effect saturate budget). Defense-in-depth: `processRefund()` guard đầu hàm với terminal state ⇒ return (kể cả row cũ tồn tại từ các round trước). Step 2a (CM đã REFUNDED ⇒ bookkeeping) vẫn chạy qua `markConfirmedSuccess` CAS cho row non-terminal còn sót.

## D3 — Cron path table (ngữ nghĩa cuối cùng)

`initiating` → grace policy (DEC-008); `provider_request_started` → reconciliation grace rồi query cùng m_refund_id; `processing`/`unknown` → query cùng m_refund_id (rc=1 finalize; rc=2 terminate CONFIRMED_FAIL; khác ⇒ consume budget); `provider_success_local_pending` → finalizeSuccess THUẦN LOCAL, fail ⇒ consume budget + giữ PSLP (attempt sau retries vô hạn selection-wise); terminal ⇒ không được chọn.

## D4 — Bằng chứng

Unit: PSLP attempts=95 finalize fail ⇒ vẫn PSLP; attempts=96 ⇒ vẫn được chọn bởi `getUnprocessedRefunds` (OR clause); `/refund`+`query_refund` = 0 trong PSLP recovery; confirmed_fail ⇒ `getRefundQuery` + `finalizeSuccess` NEVER. Real-Magento Scenario 3 (processing → cron query SUCCESS → accounting 1 lần) và Scenario 6 (PSLP recover: counters 0/0, CM REFUNDED).
