---
id: DEC-TASKCG6BM7-003
title: 'ZaloPay async refund lifecycle: plugin-orchestrated (provider asked ONCE trước core) + pending-refund durable + finalize exact-once qua native accounting; PROCESSING ≠ Magento refunded'
status: proposed
owners: [sa, tl]
decision_type: correctness
approval_date: 2026-09-16
created: 2026-09-16
last_verified: 2026-09-16
verified_against_commit: 70416b7aa6889276818b72e311a9955cbf5ed578
supersedes: []
superseded_by:
amends: [DEC-TASKCG6BM7-001]
work_items: [TASK-CG6BM7]
---

# Decision: Async refund lifecycle (BLOCKER 1, corrective round)

## Bối cảnh — bug cũ và lỗ hổng mà audit trước đã bỏ lỡ

Audit round 1 đã đúng phần retry-bounded CHO CRON nhưng **bỏ lỡ tương tác giữa refund flow và
kế toán/order-state của Magento core**. Tại baseline `181dc1d`/`70416b7a`:

- `RefundCommand` tự persist pending state ngay khi `return_code = 3` (PROCESSING) rồi **để flow core
  chạy tiếp**: `CreditmemoService::refund` → `setState(REFUNDED)` TRƯỚC gateway → invoice
  `base_total_refunded +=` → `RefundOperation::execute` (+ toàn bộ order refund totals) →
  `Payment::refund` → gateway command → `ResponseMessagesHandler` với code 3 không door gì chặn —
  hậu quả: `total_refunded`/`qty_refunded` bị finalize khi tiền CHƯA xác nhận refund, và full refund
  đẩy order sang **CLOSED** trong khi ZaloPay vẫn đang xử lý (vi phạm invariant
  "ZaloPay PROCESSING ≠ Magento refund completed").
- Bằng chứng call chain core (đọc source Magento 2.4.8-p5, vendor không nằm trong CodeGraph index —
  xem proofs P7): `CreditmemoService::refund:147-180` (setState REFUNDED trước gateway, transaction
  sales) → `RefundOperation::execute:42-124` (mutate order totals; `$creditmemo->setDoTransaction(!disallow && $online)`; `Payment::refund` tại dòng 118) →
  `Payment::refund:668+` (`canRefund()` → **`setCreditmemo($creditmemo)`:676** → resolve capture txn
  qua `parentTransactionId` → gateway refund; chỉ bắt `LocalizedException`).

## Quyết định (kiến trúc "plugin-orchestrated")

1. **Provider được hỏi ĐÚNG MỘT LẦN trước khi core đụng vào bất kỳ total nào.**
   `Plugin/Model/Service/CreditmemoRefundPlugin` (around `CreditmemoService::refund`) gọi
   `RefundCommand` TRỰC TIẾP (payment's creditmemo + `parentTransactionId` được pin từ invoice
   transaction — replicate đúng những gì `Payment::refund` sẽ làm nội bộ). RefundCommand thành
   provider-only: KHÔNG persist state, KHÔNG đụng creditmemo/order; báo kết quả qua
   `RefundOutcome` (SUCCESS/PROCESSING).
2. **SUCCESS** → `RefundOutcomeMarker::markProviderAlreadyAsked(orderId)` rồi `$proceed()` — kế toán
   native của core chạy như refund sync (`Payment::refund` chạy gateway command nhưng command skip
   provider call nhờ marker ⇒ không có provider refund thứ hai).
3. **PROCESSING** → `PendingRefundManager::registerPending` (creditmemo → `STATE_PROCESSING`=4 +
   row `zalo_pay_refund` NOT_PROCESSED chứa payload `v2/query_refund` re-signable) + success message
   + **return TRƯỚC `$proceed()`** ⇒ total/order state KHÔNG đổi, order KHÔNG thể CLOSED.
4. **Transport failure** (outcome UNKNOWN) → RefundTransportException mang theo tracking outcome
   (payload query + m_refund_id built TRƯỚC khi gọi provider) → plugin registerPending với
   evidence `transport_error:` + ném lỗi trung thực. Cron reconcile theo `m_refund_id`
   (KHÔNG BAO GIỜ re-request — m_refund_id sinh random mỗi lần build, re-request mù = double refund).
5. **Cron finalize exact-once**: provider SUCCESS → `PendingRefundManager::finalizeSuccess` trong
   MỘT transaction, guard `SELECT ... FOR UPDATE` trên row + re-check `is_processed` dưới lock:
   invoice update → `RefundOperation->execute($cm,$order,true)` (native accounting) → creditmemo/
   order save → row PROCESSED, commit. KHÔNG tự set CLOSED (core tự chốt theo total_refunded);
   KHÔNG giả credit memo đã completed; recovery branch khi creditmemo đã REFUNDED sẵn chỉ hoàn tất
   bookkeeping.
6. **Retry classification (BLOCKER 2)**: transport (kể cả từ cron) → `consumeQueryBudget` +
   `transport_error:`; FAIL(2) → `terminate` + `refund_failed:`; payload malformed/thiếu
   m_refund_id/missing creditmemo/state drift → `terminate` + `reconcile_error:`; mã lạ →
   `protocol_anomaly:`. Mọi attempt thật đều có state progression đo được (`query_attempts+1`);
   evidence last_error chỉ chứa safe text (provider map/refund message), không có secret/raw internals.

## Lựa chọn đã loại bỏ

- Fake completed credit memo / tự set order CLOSED từ cron: giả kế toán, sai state — cấm.
- Đẩy order về PROCESSING sau khi totals đã update: không thể hoàn tác total_refunded một cách sạch.
- Queue async native (`Gateway\Command\GatewayCommand` async pattern / offline charging): không
  khớp model async của ZaloPay (refund accept-then-query) và đụng core sâu hơn.
- Re-request refund khi transport fail với m_refund_id mới: double-refund risk — cấm tuyệt đối.

## Hệ quả

- File mới: `RefundOutcome`, `RefundTransportException`, `RefundOutcomeMarker`,
  `PendingRefundManager`, `CreditmemoRefundPlugin`; rewrite: `RefundCommand`, `RefundCronjob`;
  di.xml đăng ký plugin `zalopay_creditmemo_refund_lifecycle` trên `CreditmemoService`.
- Ma trận test: refund lifecycle (PendingRefundManagerTest 11 test), retry/reconciliation
  (RefundCronjobTest 13 test), plugin decision (CreditmemoRefundPluginTest 11 test),
  provider-only command (RefundCommandTest 11 test).
- `DEC-TASKCG6BM7-001` (bounded budget 96×15ph) giữ nguyên; phần "transport không tốn budget" của
  evidence F4 round 1 bị bác bỏ — xem findings.md corrective section.
