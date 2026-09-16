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

---

# Corrective round 2026-09-16 — proofs bổ sung

## P7 — Call chain refund của Magento core (source-read; vendor KHÔNG nằm trong CodeGraph index)

CodeGraph index workspace chỉ phủ `app/code`; `vendor/magento` không được index nên trace tĩnh
không resolve được interface dispatch (GatewayCommandInterface). Call chain dưới đây được chứng
minh bằng ĐỌC SOURCE vendor 2.4.8-p5 trong container, anchor `file:line` tái lập được:

- `vendor/magento/module-sales/Model/Service/CreditmemoService.php:147-180` — `refund()`:
  `validateForRefund` → **`setState(STATE_REFUNDED) TRƯỚC gateway`** → `beginTransaction('sales')`
  → invoice `setIsUsedForRefund(true)` + `base_total_refunded +=` → `RefundAdapter → RefundOperation`
  → `creditmemoRepository->save` → `orderRepository->save` → `commit`; catch → `rollBack` + rethrow.
- `vendor/magento/module-sales/Model/Order/Creditmemo/RefundOperation.php:42-124` — `execute()`:
  require `cm.state == STATE_REFUNDED` && `cm.order_id == order.entity_id`; mutate mọi order refund
  totals; `$creditmemo->setDoTransaction(!$creditmemo->getPaymentRefundDisallowed() && $online)`;
  gọi `$order->getPayment()->refund($creditmemo)` tại dòng 118.
- `vendor/magento/module-sales/Model/Order/Payment.php:668+` — `refund()`:
  `$gateway->canRefund()` → **`$this->setCreditmemo($creditmemo)` (~:676)** → resolve capture txn
  qua `transactionRepository->getByTransactionId($invoice->getTransactionId(), …)` +
  `setTransactionIdsForRefund` (đặt `parentTransactionId`) → `gateway->refund` (command)
  → chỉ catch `LocalizedException`.
- `vendor/magento/module-sales/Model/Order/Creditmemo.php:245/419/433` — `getOrder`/`getInvoice`/`setInvoice`.

Hệ quả thiết kế (DEC-TASKCG6BM7-003): plugin PHẢI tự `setCreditmemo` + pin
`parentTransactionId` từ invoice transaction trước khi gọi RefundCommand trực tiếp, vì
`Payment::refund` chỉ làm việc đó bên trong flow riêng của nó mà plugin preempt.

## P8 — Finalize exact-once (không double accounting)

- `PendingRefundManager::finalizeSuccess`: `beginTransaction` → `SELECT … FOR UPDATE` row
  (`lockRefundRow`) → re-check `is_processed` dưới lock (race ⇒ commit + return false) →
  recovery branch khi creditmemo đã `STATE_REFUNDED` (chỉ update row) → invoice update →
  `RefundOperation->execute($cm,$order,true)` → creditmemo/order save → row `PROCESSED` → commit.
- Provider call trong path này: KHÔNG (marker `RefundOutcomeMarker` đã mark; `Payment::refund`
  chạy gateway command nhưng command return null ngay khi `isProviderAlreadyAsked`).
- Tests: `PendingRefundManagerTest::testFinalizeSuccessRunsNativeAccountingOnce` (asserts: invoice
  +125.0, RefundOperation đúng 1 lần với `$online=true`, row PROCESSED, marker set),
  `testFinalizeSuccessSkipsAccountingWhenRowAlreadyProcessed` (race), 
  `testFinalizeSuccessRecoversWhenCreditmemoAlreadyRefunded` (recovery),
  `testFinalizeSuccessRollsBackOnAccountingFailure` (rollback, budget giữ lại).

## P9 — Email dispatch claim (chống trùng mail đồng thời)

- `PaymentAttemptResource::claimEmailDispatch`: MỘT câu UPDATE có điều kiện
  `entity_id = ? AND (email_dispatch IS NULL OR email_dispatch <= cutoff)` — atomic ở DB;
  caller giữ row lock trong tx finalize ⇒ hai finalizer serialize, đúng MỘT thắng.
  Tests: `PaymentAttemptResourceTest::testClaimEmailDispatchIssuesSingleConditionalUpdate`
  (pin SQL where), `…ReturnsFalseWhenClaimHeld`.
- Release token-guarded: `email_dispatch = <token>` — owner cũ bị takeover không release claim
  của owner mới. Test: `testReleaseEmailDispatchIsTokenGuarded`.
- End-to-end unit: `OrderFinalizerTest::testConcurrentFinalizerLosingClaimDoesNotSend`
  (claim=false ⇒ `send` never), `testEmailFailureReleasesDispatchClaim` (send throw ⇒ release ⇒
  retry được), `testSuccessfulSendReleasesDispatchClaim`.

## P10 — Không có provider refund thứ hai

- `RefundCommand::execute` return null ngay khi `RefundOutcomeMarker::isProviderAlreadyAsked(orderId)`
  (skip TRƯỚC cả build request); marker được mark bởi plugin trên SUCCESS và bởi
  `finalizeSuccess` trước `RefundOperation->execute`. Provider chỉ được hỏi trong plugin request;
  cron chỉ query_refund.
- Tests: `RefundCommandTest::testMarkerSkipReturnsNullWithoutProviderCall`;
  `PendingRefundManagerTest::testFinalizeSuccessRunsNativeAccountingOnce` (marker set;
  RefundOperation chạy qua Payment::refund mock boundary).
