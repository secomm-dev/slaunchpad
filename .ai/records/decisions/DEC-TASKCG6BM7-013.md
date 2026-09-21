# DEC-TASKCG6BM7-013 — Round 7: retention bằng chứng hoàn tiền 90 ngày (F36/C1) + FK không cascade (F35/A5)

Date: 2026-09-17. Status: accepted.

## D1 — Cleanup chỉ xóa evidence CONFIRMED_SUCCESS quá 90 ngày

Cleanup cũ xóa MỌI `is_processed=1` ngay cron đầu tiên — xóa luôn identity/evidence hoàn tiền (m_refund_id, amount) quá sớm: mất khả năng đối soát/audit. Chọn: `RefundCleanupCronjob::REFUND_EVIDENCE_RETENTION_DAYS = 90` (constant, không config mới trong task này); eligibility: `is_processed=1 AND refund_state='confirmed_success' AND updated_at < UTC now - 90d`. Mọi state khác (confirmed_fail, unknown, processing, provider_request_started, provider_success_local_pending, initiating) KHÔNG BAO GIỜ bị cleanup dù quá hạn — nếu sau này cần chính sách riêng cho FAIL/UNKNOWN phải có quyết định ghi nhận riêng. `updated_at` (on_update=true, UTC do init statement) đủ làm anchor tuổi.

## D2 — FK `zalo_pay_refund.credit_memo_id → sales_creditmemo.entity_id`: CASCADE → NO ACTION

Row `zalo_pay_refund` ở state UNRESOLVED là bằng chứng tài chính (m_refund_id, identity provider, evidence reconcile) — biến mất theo cascade khi CM/order bị xóa là mất bằng chứng đối soát tiền thật. Chọn `onDelete="NO ACTION"` (MariaDB thực thi như RESTRICT): xóa CM đang được tham chiếu bị từ chối ở tầng DB ⇒ evidence sống. Đánh đổi: admin xóa order/CM có refund row sẽ gặp FK error thay vì xóa sạch — chấp nhận có chủ đích (order chứa bằng chứng hoàn tiền không phải rác xóa được tự do; policy xóa sâu phải xử lý refund row trước). `SET NULL` bị loại: mất credit_memo_id làm kẹt mọi finalize cục bộ sau này (finalizeSuccess cần CM). Schema đổi thật ⇒ bắt buộc `setup:upgrade` + `SHOW CREATE TABLE` + proof xóa thật trong evidence.

## D3 — Bằng chứng

Retention: matrix unit 6 trạng thái (fresh success giữ / success >90d xóa được / fail-unknown-processing-PSLP-initiating giữ). FK: `SHOW CREATE TABLE zalo_pay_refund` sau `setup:upgrade` hiển thị NO ACTION; thử DELETE CM thật có refund row UNRESOLVED ⇒ DB từ chối (error 1451), row còn nguyên; DELETE sau khi row resolved+quá retention vẫn bị chặn FK (chỉ cleanup cron xóa row trước rồi CM mới xóa được).
