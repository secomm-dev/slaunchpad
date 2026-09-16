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

---

# Corrective round 2 proofs (2026-09-16)

## P11 — Magento refund validation PASS trước provider (BLOCKER F10)

- Mirror source: `Service/CreditmemoRefundPreflight.php:69-108` — 3 checks 1:1
  `CreditmemoService::validateForRefund` (2.4.8-p5, protected, `CreditmemoService.php:189-219`):
  existing-non-open CM (:192-197) → invalid order (:199-203) → rounded over-refund với message
  `formatTxt` (:205-218); supplementary `baseGrandTotal <= 0` (:104-108).
- Call site: `Plugin/Model/Service/CreditmemoRefundPlugin.php:121-127` — preflight chạy SAU
  in-flight guard, TRƯỚC pin transaction context (:133-141), TRƯỚC `RefundCommand::execute`
  (:148), TRƯỚC mọi `registerPending` (:161/:185) và TRƯỚC `$proceed()` (:178/:198) ⇒ không có
  provider I/O lẫn persistence trước khi validation pass.
- Core `refund()` gọi `validateForRefund()` bên trong `$proceed()` (CreditmemoService.php
  :147-180) — preflight không thay thế mà ĐI TRƯỚC; core vẫn re-validate trong `$proceed()` trên
  nhánh SUCCESS (defense in depth).
- Tests: `CreditmemoRefundPreflightTest` (9 — từng check parity + boundary
  refund-up-to-remaining-balance PASS) + `CreditmemoRefundPluginTest` matrix provider-never:
  `testOverRefundProviderNeverCalled`, `testNonOpenCreditmemoProviderNeverCalled`,
  `testInvalidOrderProviderNeverCalled`, `testZeroOnlineAmountProviderNeverCalled`
  (`RefundCommand::execute` + `registerPending` + `$proceed` NEVER),
  `testValidRefundValidatedBeforeProviderOnce` (order assertion: preflight → provider).

## P12 — Blocking semantic theo refund_state (BLOCKER F11)

- `Service/PendingRefundManager.php:108-118` — `hasInFlight` = `refund_state IN (processing,
  unknown)`, KHÔNG còn `is_processed`/`query_attempts` ⇒ `attempts == MAX` không tự mang nghĩa
  "safe to refund"; chỉ `confirmed_fail` và `confirmed_success` nằm ngoài filter.
- `consumeQueryBudget` (:199-206) — quarantine → `unknown` khi attempts chạm MAX (mọi path tiêu
  budget đều unconfirmed). `terminate` (:228-238) — state tường minh, default `unknown`.
  `finalizeSuccess` — cả hai bind update (:275-283 recovery, :318-326 fresh) set
  `confirmed_success`. `registerPending` set `processing` (:158).
- `Cron/RefundCronjob.php:245-249` — provider FAIL truyền `confirmed_fail` (tiền chưa ra ⇒ mở
  khóa); 3 terminate còn lại (missing CM :140, state drift :159, malformed payload :181) giữ
  default `unknown`.
- Tests: `PendingRefundManagerTest` — `testHasInFlightBlocksProcessingAndUnknownStatesOnly`
  (pin filter, không điều kiện attempts), `testConsumeQueryBudgetQuarantinesToUnknownAtCap`
  (transport-exhausted → unknown), `testConsumeQueryBudgetBelowCapKeepsProcessing`,
  `testTerminateDefaultsToUnknownQuarantine`, `testTerminateConfirmedFailReleasesBlockingState`,
  `testFinalizeSuccessLandsConfirmedSuccessState`; `RefundCronjobTest` — FAIL ↦ `confirmed_fail`,
  state-drift ↦ `unknown`. Ma trận kịch bản của TL: processing blocks; transport-exhausted
  blocks (unknown); protocol-anomaly-exhausted blocks (cùng path quarantine); local-finalize-
  exhausted blocks (cùng path quarantine); confirmed FAIL không block; SUCCESS không block.

## P13 — Call-chain proof (source-backed, CodeGraph worktree index 116.367 nodes)

1. Guard + validation: `CreditmemoRefundPlugin::aroundRefund` (:115 hasInFlight → :127
   `CreditmemoRefundPreflight::validateRefundable` — mirror `CreditmemoService.php:189-219`).
2. Provider (MỘT lần): `:148 RefundCommand::execute` → v2/refund; PROCESSING → immediate
   v2/query_refund (`RefundCommand::resolveProcessing:213`).
3. PROCESSING/UNKNOWN pending: `:161/:185 PendingRefundManager::registerPending` — creditmemo →
   `STATE_PROCESSING`, row `refund_state=processing`, KHÔNG mutate totals, KHÔNG `$proceed()`.
4. Cron query: `Cron/RefundCronjob.php:199 RefundQueryCommand::getRefundQuery` (re-sign payload
   từ row) — budget tiêu trên transport (:201), terminate terminal (:140/:159/:181/:245).
5. SUCCESS → finalize: `RefundCronjob.php:215 PendingRefundManager::finalizeSuccess` →
   `lockRefundRow` FOR UPDATE (:262) → NATIVE accounting (:297-316: invoice setIsUsedForRefund +
   baseTotalRefunded, `RefundOperation::execute` với marker skip, creditmemo `STATE_REFUNDED`,
   order save) → row `confirmed_success`. Full refund → Magento core tự đẩy order CLOSED (sau
   khi `total_refunded` đầy — ref RefundOperation/Payment::refund core).
6. CodeGraph evidence: `codegraph_search CreditmemoRefundPreflight` → class + validateRefundable
   (:69); `codegraph_trace CreditmemoRefundPreflight → RefundCommand` — dừng ở dynamic dispatch
   như kì vọng (plugin orchestration qua injected properties); bodies kèm anchor đã đối chiếu
   file:line từng hop ở trên. Giới hạn trung thực: callers/callees tràn sang Magento dev/tests
   trong worktree index — chain được chứng minh bằng anchor file:line + unit tests, không chỉ
   graph edges.
