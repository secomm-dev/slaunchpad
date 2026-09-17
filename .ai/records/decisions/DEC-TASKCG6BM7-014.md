# DEC-TASKCG6BM7-014 — Round 7: phân loại response provider bằng typed exception (F30/D1)

Date: 2026-09-17. Status: accepted.

## D1 — "Không chứng minh được" ≠ "bị từ chối": tách FAIL khỏi UNKNOWN

Hiện tượng: `throwProviderFailure` gộp mọi response không parse được (thiếu return_code, non-numeric, số lạ) vào `LocalizedException` — và plugin xử lý `LocalizedException` như provider-confirmed refusal ⇒ `markConfirmedFail` + NHẢ claim cho một outcome CHƯA XÁC MINH ⇒ nguy cơ hoàn tiền kép (identity chưa xác minh nhưng slot mở cho refund mới). Chọn bắt buộc theo bảng:

- `/v2/refund` `return_code=1` → SUCCESS (money moved).
- `return_code=2` → CONFIRMED_FAIL (`LocalizedException` + safe provider-map message) — NHÁNH DUY NHẤT được `markConfirmedFail`/nhả claim.
- `return_code=3` → PROCESSING (+ 1 query_refund tức thời; query 2=FAIL chốt FAIL, 1=SUCCESS chốt SUCCESS, còn lại giữ PROCESSING vì refund ĐÃ được provider chấp nhận).
- `return_code` thiếu / non-numeric / số ngoài {1,2,3} / envelope malformed → `RefundProtocolException` (mới, extends `LocalizedException`, `Exception/RefundProtocolException.php`) → plugin `markUnknown` — GIỮ claim, giữ m_refund_id, cron đối soát bằng query_refund, KHÔNG BAO GIỜ phát /refund mới.

Transport exception (`RefundTransportException`, có sẵn) → `markUnknown` như cũ. Plugin catch theo thứ tự Transport → Protocol → LocalizedException.

## D2 — Vì sao typed exception chứ không mã hoá qua message

`LocalizedException` là hợp đồng bắt lỗi của cả `Payment::refund` lẫn plugin — một loại duy nhất không đủ 3 ngữ nghĩa (refused / protocol anomaly / transport). Typed class giữ tương thích bắt `LocalizedException` ở mọi nơi cũ, đồng thời cho plugin phân nhánh chính xác. Cron (query path) KHÔNG ném protocol exception — trả `?int` return_code và tự classify: rc=2 ⇒ terminate CONFIRMED_FAIL; rc∈{missing,non-numeric,unexpected} ⇒ consume budget + evidence `protocol_anomaly` (giữ claim).

## D3 — Bằng chứng

Unit RefundCommand: missing / "abc" / 999 ⇒ `RefundProtocolException` (KHÔNG phải refusal); rc=2 ⇒ `LocalizedException` refusal; rc=3 + query 2 ⇒ refusal; rc=3 + query anomaly ⇒ PROCESSING. Unit plugin: ProtocolException ⇒ `markUnknown`, claim giữ; LocalizedException ⇒ `markConfirmedFail`. Real-Magento Scenario 4: envelope missing return_code ⇒ row `unknown`, `active_claim=1`, `/refund` count = 1, sau đó cron query cùng m_refund_id đến SUCCESS.
