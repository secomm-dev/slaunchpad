---
id: EVIDENCE-TASK-CG6BM7-PROOFS
work_item: TASK-CG6BM7
spec: SPEC-TASK-CG6BM7-zalopay-postfix-audit
kind: codegraph-grep-proofs
baseline_commit: a48de3cac477cada0882974151db7765c554aa25
created: 2026-09-16
---

# §14 CodeGraph / static proofs — ZaloPay post-fix audit

Mỗi proof kèm lệnh tái lập (chạy từ root repo).

## P1 — Không còn đường gửi email trước payment-verified + commit

- Callgraph (`codegraph_trace OrderFinalizer → OrderSender`): đường duy nhất tới
  `OrderSender::send` là `OrderFinalizer::sendConfirmationEmail`, được gọi từ:
  1. fresh finalize — SAU `$dbConnection->commit()` (dòng `afterCommit` trong `finalize()`),
  2. FINALIZED-duplicate backfill — order đã FINALIZED từ transaction trước.
- `grep -n "sendConfirmationEmail\|->send(" app/code/Secomm/ZaloPay/Service/OrderFinalizer.php`
  → không có call site nào trước `commit()`; capture-fail/rollback path (`rollback()`)
  không tham chiếu OrderSender.
- Test: EMAIL 1–7 (`OrderFinalizerTest`) — capture fail → `send` never; email exception → order vẫn FINALIZED.

## P2 — FINALIZED duplicate không gửi mail trùng

- `OrderFinalizer::sendConfirmationEmail` guard `(int)$order->getEmailSent() === 1` → return sớm.
- `OrderSender` (Magento core) set `email_sent=1` sau khi gửi sync thành công → guard chặn đúng lần 2.
- Test: EMAIL 2 (`testDuplicateFinalizeOnEmailedOrderDoesNotResend`), EMAIL 3 (backfill khi `email_sent` null).

## P3 — Ownership cron refund

- `etc/crontab.xml`: `secomm_zalopay_refund_cronjob` → `Secomm\ZaloPay\Cron\RefundCronjob::execute`, `*/15`.
- `grep -rn "RefundCronjob" app/code --include=*.xml` → chỉ ZaloPay đăng ký.
- Budget 96 × 15 phút = 24h — khớp `MAX_QUERY_ATTEMPTS = 96` (DEC-TASKCG6BM7-001).

## P4 — Refund transitions (§14.4)

State machine chốt (cùng source-of-truth với code):
- `RefundCommand` return_code 3 → creditmemo `STATE_PROCESSING` (const từ `CreditmemoPlugin::STATE_PROCESSING`) + row `NOT_PROCESSED` (finally);
- cron return_code 1 → creditmemo `STATE_REFUNDED` + row `PROCESSED` + `last_error` NULL;
- cron return_code 2 → row giữ `NOT_PROCESSED` (evidence) + `query_attempts` saturate 96 + `last_error` = safe mapped message; creditmemo KHÔNG đổi;
- cron else (3/unknown/transport/malformed) → `query_attempts+1`, đến 96 → critical log, rơi khỏi selection.
- Tests: REFUND CMD 16–23 + REFUND CRON 24–30.

## P5 — ResponseMessagesHandler consumer semantics (§6, §14.5)

- CodeGraph: `ResponseMessagesHandler::handle` → đọc `return_code` → (1) `approve_messages`; (2/unknown) `setIsTransactionPending(false)` + `setIsFraudDetected(true)` + `error_messages`; (3) message KHÔNG fraud; else → no-op.
- Consumers: `di.xml` virtualType `ZaloPayRefundResponseHandler` mount vào refund response handler chain; `getState()` của command chain tiêu thụ `approve_messages`/`error_messages`.
- Tests: RESPONSE 31–34 (5 test).

## P6 — Không module lạ sở hữu ZaloPay refund state (§14.6)

- `grep -rn "zalo_pay_refund\|RefundInterface\|RefundModel\|CreditmemoPlugin" app/code --include="*.php" --include="*.xml" -l | grep -v "app/code/Secomm/ZaloPay"`
  → rỗng (không module nào ngoài ZaloPay đọc/ghi `zalo_pay_refund` hay cắm plugin creditmemo của ZaloPay).
- ExtraFee side (`app/code/Launchpad/MageplazaExtraFeeFix/...`) chỉ đọc `canCreditmemo` tổng quát — không tham chiếu ZaloPay; KHÔNG bị sửa (git diff chỉ đụng ZaloPay + .ai).
