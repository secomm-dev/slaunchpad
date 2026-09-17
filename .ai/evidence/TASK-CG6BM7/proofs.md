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

---

# Corrective round 3 proofs (2026-09-16)

## P14 — Real-DB concurrency evidence: atomic claim (MariaDB 10.4, container throwaway)

Container `zt-mariadb-evidence` (MariaDB 10.4, riêng biệt — KHÔNG đụng DB dev chung; đã xoá
sau khi thu evidence). Schema mirror ĐÚNG schema round 3: bảng `zt_concurrency.zalo_pay_refund`
với `active_claim` smallint NULL + 2 unique index `ZALO_PAY_REFUND_ORDER_ACTIVE (order_id,
active_claim)` / `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE (m_refund_id, active_claim)`.

| # | Kịch bản | Kết quả |
|---|----------|---------|
| E1 | claim đầu tiên `INSERT (order 1, m_refund_id R1, active_claim=1)` | COMMITTED ✓ |
| E2 | claim thứ hai CÙNG order 1 (R2) | rejected: `Duplicate entry ... for key 'ZALO_PAY_REFUND_ORDER_ACTIVE'` ✓ |
| E3 | claim cùng `m_refund_id` R1, order KHÁC (78) | rejected: `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE` ✓ (đúng một active attempt cho một refund identity) |
| E4 | 3 row lịch sử NULL-active cùng order (confirmed_success/confirmed_fail/unknown, is_processed=1) | cùng tồn tại ✓ — NULL-trick không bao giờ chặn dữ liệu lịch sử (đối xứng với F14) |
| E5 | release (`active_claim=NULL`) rồi claim mới cùng order | thành công ✓ (terminal nhả slot ⇒ refund sau FAIL/SUCCESS được) |
| E6 | đếm active row sau chuỗi E1–E5 | ĐÚNG 1 active row cho order ✓ |
| E7 | quarantine `unknown` giữ `active_claim=1` | INSERT thứ hai cùng order vẫn rejected ✓ — UNKNOWN block cả ở DB-level slot, không chỉ ở SELECT guard |
| E8 | 2 session song song thật (`INSERT ... ; SELECT ...` chạy đồng thời từ 2 connection) | session-A COMMITTED, session-B REJECTED duplicate-key ✓ — đúng MỘT winner, không cần application lock |

Hạn chế khai báo trung thực: container dùng MariaDB 10.4 image riêng (dev stack dùng MariaDB
khác version đang được share) — semantics unique-index-with-NULL là thuộc tính ANSI/InnoDB
ổn định; evidence không bao phủ deadlock/lock-waittimeout (không có trong design: INSERT đơn
autocommit không giữ lock xuyên HTTP).

## P15 — Call-chain round 3: claim TRƯỚC provider I/O (CodeGraph + anchor file:line)

- CodeGraph worktree index rebuild (`codegraph index -q`, sau khi toàn bộ file round 3 ổn):
  `executePrepared` có indexed caller duy nhất `RefundCommand::execute`
  (`Gateway/Command/RefundCommand.php:105` — contract public giữ nguyên);
  `acquireClaim` index tại `Service/PendingRefundManager.php:145`.
- Giới hạn index (giữ nguyên từ P13): dynamic dispatch qua injected property
  (`$this->pendingRefundManager->…`, `$this->refundCommand->…`) không resolve callers đầy đủ
  (`codegraph_callers markProcessing/finalizeSuccess` → rỗng) ⇒ chain được chốt bằng anchor
  file:line đọc trực tiếp, đối chiếu từng hop:
  - `Plugin/Model/Service/CreditmemoRefundPlugin.php`: guard :111-118 → pass-through :121-124
    → offline guard :126-132 → **preflight :142** → pin invoice txn :148-153 →
    **prepare (identity, NO I/O) :165** → **acquireClaim (ATOMIC, commit TRƯỚC I/O) :176** →
    **executePrepared (provider DUY NHẤT) :179** → markUnknown :184/:197 → markConfirmedFail
    :211 → markProcessing :223 → markProviderSuccessLocalPending :241 → markConfirmedSuccess :256.
    Thứ tự dòng: claim (176) < provider (179) — invariant `CLAIM_PERSISTED_BEFORE_PROVIDER_IO`
    + `STABLE_M_REFUND_ID_BEFORE_PROVIDER_IO` (identity do prepare :165 build trước claim).
  - `Cron/RefundCronjob.php`: PSLP shortcut :140-141 → `finalizeSuccess` :143 (KHÔNG có
    `refundQueryCommand` call nào phía trước trong nhánh này — bằng chứng
    `LOCAL_RETRY_RECALLS_PROVIDER_REFUND=NO`); REFUNDED bookkeeping :191-192; drift terminate
    :207-211; FAIL → terminate :294-298 + `releaseCreditmemoAfterFail` :299 (:332, STATE_OPEN
    :339).
  - `grep -rn registerPending app/code/Secomm/ZaloPay --include=*.php | grep -v Test` → 0
    (không còn remnant round 2).
- Tests pin thứ tự: `testValidRefundValidatedBeforeProviderOnce` order-assertion
  `['preflight','prepare','claim','provider']` (plugin test).

## P16 — Crash-recovery property (crash sau /refund KHÔNG tạo refund mới)

- Property: `prepare()` build `m_refund_id` ổn định TRƯỚC I/O (:165) → claim INSERT mang
  ĐÚNG identity đó (:176, manager :145-160 persist `m_refund_id` vào row) → provider (:179).
  Tại mọi điểm crash sau `/refund`, row tồn tại với `m_refund_id` provider đã thấy.
- Cron khôi phục theo row, KHÔNG theo request mới: `buildQuerySubject`
  (`RefundCronjob.php:361-383`) unserialize payload từ row + re-sign
  `app_id|m_refund_id|timestamp`; toàn bộ cron chỉ gọi `RefundQueryCommand` (query_refund) —
  `grep -n "refundCommand\|/refund"` trên RefundCronjob → không có path gọi provider refund.
- PSLP (`provider_success_local_pending`): row giữ m_refund_id + cron step 0 chỉ finalize
  local (:136-165) ⇒ provider SUCCESS + crash/trước-kịp-persist cũng không rơi vào "thử lại".
- Unit evidence: `testPslpRowFinalizesLocallyWithoutProviderQuery` (creditmemoRepository.get
  never, query never, finalizeSuccess once); `testUntrackableTransportStillMarksUnknownOnClaim`
  (outcome thiếu → unknown, không re-request); E3/E7 (DB: identity + quarantine slot).

## P17 — Round 4 (F17–F22) unit + flow evidence

- REQUIRED F17: `CreditmemoRefundPluginTest::testRealAdminUnsavedCreditmemoBindBeforeProvider` — CM `entity_id = NULL` lúc claim (không pre-stub); save-once gán 9012; `bindCreditMemo(claim, 9012)` đúng 1 lần; `executePrepared` đúng 1 lần; recorder `['save','bind','provider']` ⇒ PROVIDER_CALL_WITH_CREDITMEMO_ID_0 = NO.
- Bind gate race-safe: `bindCreditMemo` UPDATE guard `entity_id = ? AND active_claim = 1` (`testBindCreditMemoGuardedByActiveClaim`); trả false khi claim đã bị cron nhả (`testBindCreditMemoReturnsFalseWhenClaimReleased`) ⇒ plugin terminate abandoned + KHÔNG gọi provider (`testLostClaimBeforeBindNeverTouchesProvider`).
- Persist fail: `testLocalPersistFailureAbandonsClaimBeforeProvider` — evidence `abandoned_before_provider_io: local creditmemo persist failed: ...`, confirmed_fail, provider 0 lần, core 0 lần.
- F18 cron: `testUnboundInitiatingClaimIsAbandonedBeforeProviderIo` (repo.get 0 lần, query 0 lần, terminate confirmed_fail); `testInitiatingBoundWithOpenCreditmemoQueriesSameIdentity` + `testUnknownRowWithOpenCreditmemoQueriesInsteadOfDrift` (CM OPEN vẫn query CÙNG m_refund_id, không drift); CANCELED vẫn terminal reconcile.
- F19/F20: `BackfillRefundStateTest::testApplyBackfillsFourCohortsInEvidenceOrder` — 4 cohort WHERE pins + SQL claim ownership (MIN(entity_id) AS claim_id / WHERE refund_state = 'unknown' / GROUP BY order_id / ON r.entity_id = c.claim_id / SET r.active_claim = 1).
- F22: `testAcquireClaimForeignKeyViolationIsNotClaimConflict` — PDOException driver 1452 KHÔNG bị classify thành claim conflict.
- Suite: **306 tests / 1105 assertions OK** (container `slaunchpad-phpfpm-1`, PHP 8.3.20, PHPUnit 10.5.64).
- PHPCS Magento2 (app + Test): **0 errors** (warnings line-length/docblock, không block).

## P18 — F21 real setup:upgrade — DEFERRED cho TL (stack đã dựng sẵn)

- Đã dựng trong container `slaunchpad-phpfpm-1`: tree `/tmp/m2b` (copy hardlink Magento 2.4.8-p5, code round-4 stage tại `app/code/Secomm/ZaloPay`), MariaDB 10.11.19 container `zt-mariadb` (db `magento`, root/zt-f21-pw), OpenSearch 2.19.1 container `zt-opensearch`, network `zt-f21-net`.
- Các lần thử tự động: `setup:upgrade` trực tiếp trên DB trống lỗi data-phase core ("The default website isn't defined" — empty-DB cần setup:install); `setup:install` với cờ `--opensearch2-*` sai (option list 2.4.8-p5 chỉ có `--opensearch-*`, engine id `opensearch` theo `module-open-search/etc/search_engine.xml`). Chưa có lần chạy PASS ⇒ không bịa evidence; lệnh PASS dự kiến + các verify (SHOW CREATE TABLE, patch_list, seed legacy → re-run patch → F19 block/unblock) ghi ở validation.md round 4 cho TL chạy.

## P19 — Round 5 (F23/F24/F25): unit + REAL-DB evidence (MariaDB 10.6 disposable, NO-DEFER)

### F23 — LOCAL_READY / PROVIDER_REQUEST_STARTED boundary

- Unit (cron, clock mock `DateTime::timestamp` điều khiển được):
  - `testBoundInitiatingLocalReadyIsAbandonedNeverQueried` — row initiating CÓ bound CM (CM OPEN): `creditmemoRepository->get` 0 lần, `getRefundQuery` 0 lần, `consumeQueryBudget` 0 lần, terminate `abandoned_before_provider_io` + confirmed_fail đúng 1 lần ⇒ cron KHÔNG query LOCAL_READY, KHÔNG nhả qua query-fail (nhả qua terminate có chủ đích).
  - `testFreshProviderStartWithinGraceIsNeverQueried` — `provider_request_started_at` = now−30s (grace 120): query 0, budget 0, terminate 0, finalize 0 — request A giữ claim tới khi HTTP của nó xong/timeout.
  - `testStaleProviderStartQueriesSameMRefundId` — started_at = now−400s: `getRefundQuery` đúng 1 lần (CÙNG m_refund_id từ payload row), `consumeQueryBudget($row, null)` 1 lần, không terminate.
  - `testProviderStartMissingTimestampConsumesBudget` — started_at NULL: `consumeQueryBudget` 1 lần với evidence `reconcile:...`, query 0, terminate 0.
  - PROCESSING/UNKNOWN query như cũ (giữ round 4); PSLP local-only (giữ round 3).
- Unit (manager): `testMarkProviderRequestStartedPinsStateAndTimestamp` — UPDATE `zalo_pay_refund` SET state=`provider_request_started` + timestamp string UTC, WHERE `entity_id = ? AND active_claim = ? = 1`; affected 1 ⇒ true + data set trên model. `testMarkProviderRequestStartedFailsWhenClaimReleased` — affected 0 ⇒ false, model KHÔNG bị đổi.
- Unit (plugin): REQUIRED `testRealAdminUnsavedCreditmemoBindBeforeProvider` — recorder giờ là `['save','bind','start','provider']` (mark provider-start giữa bind và executePrepared). `testProviderStartMarkFailureNeverTouchesProvider` — mark false ⇒ terminate `provider-start state could not be persisted - provider I/O forbidden` + confirmed_fail + "could not be started", `executePrepared` 0 lần, core 0 lần.
- Blocking sets: `testHasInFlightBlocksProcessingAndUnknownStatesOnly` pin bộ `in` 5 state (initiating, provider_request_started, processing, unknown, provider_success_local_pending).
- Real DB (bootstrap script trên Magento thật + MariaDB 10.6, bảng `zalo_pay_refund` thật): CASE2 `acquireClaim` tạo row#9; CASE3 `markProviderRequestStarted` ⇒ true, DB row `refund_state=provider_request_started`, `provider_request_started_at` UTC persisted **trước** mọi provider HTTP (PROVIDER_START_DURABLE_BEFORE_HTTP); CASE4 simulate stale-release (`UPDATE active_claim=NULL`) rồi mark lại ⇒ **false** (claim-guarded UPDATE ảnh hưởng 0 row) ⇒ CLAIM_RELEASE_BEFORE_PROVIDER_COMPLETES = NO (cron không nhả claim trong grace; nếu nhả thì mark fail ⇒ plugin tự chặn HTTP).

### F24 — quoteInto một-placeholder-một-lời-gọi

- Unit: mock `quoteInto` trung thực Zend (int ⇒ bare, string ⇒ single-quoted) + `assertCount(2, $quoted)` (chỉ cohort 3 dùng quoteInto) + assertSame exact WHERE: `is_processed = 1 AND last_error IS NOT NULL AND last_error NOT LIKE 'refund_failed:%'`.
- Real SQL (MariaDB 10.6): cohort-3 WHERE chạy trực tiếp chọn DUY NHẤT entity 6 (`transport_error: cURL timeout`); entity 5 (`refund_failed: Insufficient balance`) chỉ match cohort-fail WHERE và giữ `refund_state=confirmed_fail` SAU khi cohort-success đã chạy ⇒ FAIL_COHORT giữ nguyên, không bị SUCCESS cohort đè.

### F25 — REAL setup:install / setup:upgrade / SHOW CREATE TABLE / migration claim proof

- Stack: container `slaunchpad-phpfpm-1` (PHP 8.3.20), tree `/tmp/m2b` (Magento 2.4.8-p5, module round-5 stage tại `app/code/Secomm/ZaloPay`), MariaDB **10.6.28** `zt-mariadb106` (10.11 bị 2.4.8 từ chối), OpenSearch 2.19.1 `zt-opensearch`, network `zt-f21-net`.
- `setup:install` PASS (exit 0): 358 module enabled (core + Secomm_ZaloPay; bên thứ 3 disabled tạm trong config.php throwaway — cli command list của chúng kéo `Session\Config` đọc default website trên DB rỗng ⇒ crash; nguyên nhân gốc ở tree test, KHÔNG phải code module).
- `SHOW CREATE TABLE zalo_pay_refund` (sau install thật): `credit_memo_id int(10) unsigned DEFAULT NULL`; `UNIQUE KEY ZALO_PAY_REFUND_ORDER_ID_ACTIVE_CLAIM (order_id, active_claim)`; `UNIQUE KEY ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE_CLAIM (m_refund_id, active_claim)`; `CONSTRAINT ZALO_PAY_REFUND_CREDIT_MEMO_ID_SALES_CREDITMEMO_ENTITY_ID FOREIGN KEY (credit_memo_id) REFERENCES sales_creditmemo(entity_id) ON DELETE CASCADE`; `provider_request_started_at datetime DEFAULT NULL` (tên index do MariaDB normalize, khớp referenceId).
- `setup:upgrade` PASS (exit 0) sau khi DROP cột `provider_request_started_at` + xóa patch entry: cột được tái tạo qua declarative delta (col_restored=1) + patch `BackfillRefundState` re-run (có lại trong patch_list).
- Legacy cohorts seed 7 row (5001×3 unresolved, 5002 resolved success, 5003 refund_failed, 5004 transport_error, 5005 unresolved): sau upgrade — 5001-A/B/C all `unknown`, **đúng 1** `active_claim=1` (entity 1 = MIN); 5002 `confirmed_success`; 5003 `confirmed_fail` (không bị đè); 5004 `unknown`; 5005 `unknown` (owner riêng) ⇒ FAIL/SUCCESS/AMBIGUOUS/UNRESOLVED cohorts + MULTIPLE_LEGACY_ROWS_ONE_CLAIM.
- Migration claim proof qua `acquireClaim` THẬT (Magento bootstrap, real DI): legacy unresolved order 5001 → REJECTED (`Zalopay: Another refund for this order is active or awaiting reconciliation.` — 1062 thật qua unique index thật); legacy resolved order 5002 → ALLOWED (row#9) ⇒ LEGACY_UNRESOLVED_BLOCKS_NEW_CLAIM=YES.
- `setup:di:compile` PASS (exit 0, "Generated code and dependency injection configuration successfully.") trên /tmp/m2b với đúng code round-5.

## P20 — Round 6 F26: race cron × local-bind phase + crash recovery stale (real DB + unit)

**Claim kiểm chứng**: fresh `LOCAL_READY` + cron chạy bất kỳ lúc nào trước provider-start ⇒ row KHÔNG bị đụng (không query, không terminate, không nhả claim) và request A tiếp tục `markProviderRequestStarted` thành công rồi gọi provider ĐÚNG 1 LẦN; stale `LOCAL_READY` (tuổi ≥ 300s) ⇒ cron nhả an toàn, không bao giờ query provider (provider call count = 0 cho crash recovery).

- **Real-DB** (script `/tmp/m2b/r6-proof.php`, bootstrap `Bootstrap::create` + area `crontab`, cron THẬT tạo qua DI với override `scopeConfig` — `isActive` dùng `isSetFlag`; MariaDB 10.6 `zt-mariadb106`; legacy evidence rows tạm `is_processed=1` để cô lập batch cron — stack disposable, bằng chứng round-5 đã ghi trong P19) — **ALL_CHECKS_PASSED 21/21**:
  - R1 fresh UNBOUND: `acquireClaim` thật (row initiating, `active_claim=1`, `created_at` age=0s < 300) → chạy `RefundCronjob::execute()` THẬT → DB verify **untouched** (state `initiating`, claim giữ, `last_error` NULL, `query_attempts` 0) → A reload row + `markProviderRequestStarted` ⇒ **true** → DB pin `provider_request_started` + `provider_request_started_at` NOT NULL.
  - R2 stale UNBOUND crash recovery: claim → UPDATE `created_at = UTC_TIMESTAMP() - 400s` → cron thật ⇒ `confirmed_fail`, `active_claim` NULL, `credit_memo_id` vẫn NULL (CM không bao giờ được load), evidence CHÍNH XÁC `abandoned_before_provider_io: stale LOCAL_READY claim - provider I/O impossible by construction` (prefix abandoned, KHÔNG phải `reconcile_error:`/`refund_failed:` ⇒ query path chưa từng chạy ⇒ **provider call count = 0**); trong CÙNG cron run đó row R1 (`provider_request_started`, trong grace 120s) vẫn **untouched** ⇒ F23 không regress.
  - R3 BOUND FK-thật: INSERT `sales_order` + `sales_creditmemo` thật (FK `ZALO_PAY_REFUND_CREDIT_MEMO_ID_*` thoả) → claim → `bindCreditMemo` thật ⇒ bound + vẫn LOCAL_READY → cron fresh ⇒ **untouched, giữ cả claim lẫn binding** → stale UPDATE → cron ⇒ nhả (`confirmed_fail` + claim NULL) nhưng **`credit_memo_id` giữ nguyên làm evidence**; evidence abandoned ⇒ step 1/2b/3-7 chưa từng chạy.
  - Side-proof (không chủ đích nhưng thật): hai lần chạy script sớm hơn trúng 1062 duplicate-key `(order_id, active_claim)` qua LocalizedException "Another refund for this order is active..." — atomic claim chặn đúng cả với row in-flight của run trước.
- **Unit** (`Test/Unit/Cron/RefundCronjobTest.php`, suite 316/1145 OK): fresh unbound/bound ⇒ terminate/consume/repo/command NEVER + row không đổi; stale unbound/bound ⇒ terminate đúng 1 lần với evidence EXACT + `confirmed_fail` + CM/repo/command NEVER; missing `created_at` ⇒ consumeQueryBudget đúng 1 lần + terminate NEVER; race composition `testRaceCronDuringLocalBindLeavesOwnerFullContinuation` (cron untouched ⇒ mark thành công cùng row instance).
- **Provider exactly-once** (race continuation): unit plugin REQUIRED `testRealAdminUnsavedCreditmemoBindBeforeProvider` pin recorder `['save','bind','start','provider']` + `executePrepared` đúng 1 lần sau mark thành công (round 5, giữ nguyên) — với real-DB R1 (cron untouched + mark true) ⇒ tổng hợp: cron giữa chừng không cản A gọi provider đúng 1 lần.
- **SCHEMA_CHANGED=NO**: `git diff` không đụng db_schema.xml/whitelist/Setup ⇒ bằng chứng round-5 (P19) vẫn hiệu lực, không bắt buộc setup:upgrade lại.

## P21 — Round 7: REAL-Magento lifecycle smokes (REAL core accounting + deterministic fake transport)

**Môi trường**: disposable REAL Magento 2.4.8-p5 (`/tmp/m2r` trong container `m2r-php`, mount `/var/www/html`; MariaDB 10.6 `zt-mariadb106` DB `m2r`; OpenSearch zt-opensearch; currency VND; 358 module core + Secomm_ZaloPay + env-only `Secomm_ZaloPayTestEnv` — fake provider transport NHƯNG accounting Magento/th persistence/CronJob/thực native `CreditmemoService::refund` đều THẬT qua `Bootstrap::create`). Counters fake provider flock-guarded `/tmp/zp/provider_counters.json`. SEAM duy nhất: HTTP transport (fake client inject qua di override argument `client` — class preference KHÔNG đủ vì client được binding qua virtualType explicit argument).

- **S1 REAL_SYNC_SUCCESS — PASS**: seed order+invoice+capture txn thật → REAL `CreditmemoLoader::load()` + `CreditmemoService::refund()` qua plugin. Kết quả DB: CM **state=2 REFUNDED**, `invoice_id` set, invoice `is_used_for_refund=1` + `base_total_refunded=110000`, order `total_refunded=base_total_online_refunded=110000` state closed, payment `base_amount_refunded_online=110000`, refund txn (parent capture), item qty_refunded=2/2; row `zalo_pay_refund`: `confirmed_success`, `is_processed=1`, `active_claim=NULL`, `credit_memo_id` bind đúng; counters `/refund=1`, `query=0`. Chứng minh F27 (validateForRefund PASS với CM OPEN pre-saved), F31 (ONLINE refund đúng), F28 (provider đúng 1 lần), native accounting exactly-once.
- **S2 ASYNC_RECOVERY (UNKNOWN → cron → SUCCESS) — PASS**: refund với envelope `return_code` missing ⇒ `RefundProtocolException` customer-safe, row `unknown` + `active_claim=1` + m_refund_id giữ, CM OPEN, `/refund=1`. Sau đó cron THẬT (`RefundCronjob` qua DI) với query rc=1: row → `confirmed_success`, claim NULL, CM REFUNDED + invoice_id bind, order `total_refunded=110000` online; counters `/refund=1` (KHÔNG BAO GIỜ re-ask), `query_refund=1` CÙNG m_refund_id `260917_2555_1789620905000432`. Chứng minh F30 (missing ≠ refusal), D1 identity-reconciliation.
- **S2b (rc=3 PROCESSING + query rc=1 in-line)**: `/refund=1`, `query=1`, ngay trong request chốt `confirmed_success` + accounting — DEC-014 resolveProcessing đúng.
- **S3 EXPLICIT_FAIL — PASS**: rc=2 ⇒ LocalizedException refusal, row `confirmed_fail` + `active_claim=NULL` (nhả claim cho retry hợp pháp), CM state=1 OPEN (không accounting), order `total_refunded=NULL`, `/refund=1`.
- **S4 MARKER_ISOLATION (same-process) — PASS**: MỘT PHP process chạy 2 refund tuần tự (order 10 + order 11, reset registry `current_creditmemo` giữa 2 loader run): cả 2 `confirmed_success`, 2 CM REFUNDED (11/12), `/refund=2` — marker one-shot keyed `credit_memo_id` KHÔNG leak giữa 2 refund (đối chứng: marker order-key cũ sẽ skip refund thứ 2).
- **S5 CAS_FOCUSED stale-writer (2 process thật) — PASS**: P1 `cas_race.php load 13` freeze snapshot row `unknown/claim=1`; P2 cron thật finalize `confirmed_success`; P3 `fire-terminate` từ snapshot STALE ⇒ CAS affected=0 ⇒ row VẪN `confirmed_success`, `last_error` NULL, ownership/claim/accounting intact. Chứng minh F29: stale writer không bao giờ overwrite owner.
- **Schema (F35)**: `setup:upgrade` PASS sau đổi FK; `SHOW CREATE TABLE zalo_pay_refund`: `CONSTRAINT ZALO_PAY_REFUND_CREDIT_MEMO_ID_SALES_CREDITMEMO_ENTITY_ID FOREIGN KEY (credit_memo_id) REFERENCES sales_creditmemo(entity_id) ON DELETE NO ACTION`.
- **Rate fix evidence**: trước fix — REAL smoke crash `TypeError: ...Rate::getVndAmount(): Argument #1 ($order) must be of type Magento\Sales\Model\Order, OrderAdapter given` (stack: `RefundCommand->readVndAmount` ← plugin `prepare`); sau fix — toàn bộ S1–S4 xanh. Retry-with-fresh-refund KHÔNG xảy ra ở row nào: mọi run có /refund đúng số mong đợi trong counters.
