# TASK-EDS9T5 — Test result

## Kết quả

| Suite | Kết quả |
|---|---|
| `Secomm_ZaloPay` Test/Unit (docker PHP 8.3.20) | **OK (78 tests, 254 assertions)** |
| Full `phpunit-secomm.xml` suite | 866 tests, 15 errors — **giống hệt baseline** |

## Regression check (so với CLEAN_BASE 3b26189e, đo lại trên detached worktree của chính SHA đó)

| | Base | Branch | Δ |
|---|---|---|---|
| ZaloPay Test/Unit | 61 test OK | 78 test OK | +17 test, 0 fail |
| Full suite | 849 tests / 15 errors | 866 tests / 15 errors | +17 test (= +17 ZaloPay), cùng 15 lỗi |

15 lỗi pre-existing (PHP 8.3 constructor strictness, KHÔNG liên quan):
`Secomm_Tracking EventNormalizerTest` (7) + `Secomm_PromotionMaxDiscount MaxDiscountCapTest` (8).

## Ma trận §18 — ánh xạ case → test

- **Pre-redirect / no order before verification**: StartTest (integration, empty session → cart);
  PaymentAttemptManagementTest (7, base) — initiate/isInitiable/reuse fingerprint.
- **IPN-first, không browser**: IpnProcessorTest::testIpnMarksAttemptPaidAndFinalizesWithoutBrowserReturn,
  ::testTransientFinalizationFailureKeepsPaidAndIsRetryable.
- **IPN/Return race → 1 attempt 1 order**: OrderFinalizerTest::testConcurrentPlacementIsRecoveredByReservedOrderId,
  ::testDuplicateFinalizeReturnsExistingOrderWithoutPlacing, ::testDuplicateFinalizeRebuildsEmptySuccessSession;
  IpnProcessorTest::testDuplicateIpnOnPaidAttemptRetriesFinalization, ::testDuplicateIpnOnFinalizedAttempt….
- **Contract snapshot / fingerprint mismatch**: QuoteContractFingerprintTest (10, base);
  OrderFinalizerTest::testQuoteTotalChangedRefusesPlacement, ::testContractFingerprintMismatchRefusesPlacement,
  ::testQuotePaymentMethodChangedRefusesPlacement, ::testInactiveQuoteWithoutOrder…, ::testInactiveQuoteWithMatchingOrderBindsExistingOrder.
- **PAID retry semantics (transient vs deterministic)**: IpnProcessorTest::testAmountMismatchMarksPaidAndRecordsReconciliationError,
  ::testContractMismatchKeepsPaidStateAndAcknowledges, ::testTransientFinalizationFailure…;
  OrderFinalizerTest::testCaptureFailureRollsBackTransaction, ::testNonPayableAttemptRefusesFinalization,
  ::testFinalizedWithoutBoundOrderIsRefused, ::testFinalizedWithWrongOrderBindingIsRefused.
- **placeOrder authorization / guard**: OrderPlacementAuthorizationTest (7);
  CartManagementPlaceOrderGuardTest (7 — block/pass-through/unknown quote/null payment/string id);
  OrderFinalizerTest assertions: grant đúng (42, 9, '260826_1000_000000123'), clear trong finally,
  mismatch/recovery KHÔNG mở grant.
- **Abandoned → no order**: không nhánh nào tạo order ngoài finalizer (§19 proof);
  ReturnProcessorTest (10, base — processing/failed/cancelled không order).
- **Controller hermetic**: IpnTest (404 unknown), ReturnActionTest (missing/unknown ref → cart),
  StartTest (cart redirect khi không payable). *Integration suite: ENVIRONMENT-BLOCKED local
  (cần Magento test framework + DB; toàn bộ Test/Integration của repo đều không chạy được local) —
  source đã nhất quán với flow mới; unit suite phủ đầy đủ hành vi processor/service/guard.*

## Ghi chú hiệu chỉnh giữa kỳ

- Sửa guard: `QuoteRepositoryInterface` không tồn tại trong M2.4.8 → dùng
  `CartRepositoryInterface` (bắt được ngay nhờ unit mock autoload — "class does not exist").
- Số test baseline trong memory cũ (101/885) thuộc branch cũ đã bỏ; baseline CHUẨN của
  clean base 3b26189e là 61/849 (đo lại trực tiếp, xem command-output.txt §3).

---

# CORRECTIVE ROUND 2 (TL review 4 BLOCKER) — kết quả test

## Số liệu

| Suite | Kết quả |
|---|---|
| `php -l` toàn module | 97/97 file OK |
| Unit Secomm_ZaloPay (`Test/Unit`) | **OK — 112 tests, 351 assertions** (78 → +34) |
| Full suite branch | 900 tests / 3320 assertions / **7 errors** |
| Full suite base 3b26189e (đo lại cùng điều kiện: detached worktree + `setup:di:compile`) | 849 tests / 3165 assertions / **7 errors** |
| So sánh | 849 + 51 (ZaloPay 61→112) = 900 — **0 regression**, 7 lỗi pre-existing Tracking (giống hệt base) |
| PHPCS Magento2 severity 10 | 0 errors, 1 warning pre-existing (zalopay.html — file không đụng) |
| `setup:di:compile` | OK |
| Integration | **ENVIRONMENT-BLOCKED** — KHÔNG claim PASS |

Ghi chú phương pháp: số "15 errors" của base ở round 1 là measurement artifact — base worktree
thiếu thư mục `generated/` (gitignored) nên 8 test MaxDiscountCap mock thiếu method `getDiscounts`
của `Magento\Quote\Api\Data\CartItemExtensionInterface` (interface GENERATED). Sau khi compile base,
baseline đúng là **849/7**. Branch 900/7 ⇒ sai khác tuyệt đối = 0.

## Mapping 27 test case bắt buộc → test thực tế

| # | Case | Test |
|---|---|---|
| 1 | browser status != paid không tự mark FAILED | `ReturnProcessorTest::testBrowserFailureStatusCannotMarkFailedByItself` |
| 2 | browser failed + query PAID → finalize | `ReturnProcessorTest::testBrowserFailedButAuthoritativeQueryPaidFinalizesOrder` |
| 3 | browser paid + query processing → no order | `ReturnProcessorTest::testBrowserPaidButQueryProcessingPlacesNoOrder` |
| 4 | browser paid + query failure → no order / terminal đúng | `ReturnProcessorTest::testBrowserPaidButAuthoritativeQueryFailureTransitionsFailed` |
| 5 | Return quyết định trên row FRESH đang lock | `ReturnProcessorTest::testDecisionIsMadeOnLockedFreshRowNotStaleCopy` |
| 6 | IPN quyết định trên row FRESH đang lock | `IpnProcessorTest::testDecisionIsMadeOnLockedFreshRowNotStaleCopy` |
| 7 | Return stale không regressed FINALIZED | `ReturnProcessorTest::testStaleReturnCannotRegressFinalizedAttempt` |
| 8 | IPN stale không regressed FINALIZED | `IpnProcessorTest::testLateCallbackCannotRegressFinalizedAttempt` |
| 9 | Return/IPN race → đúng 1 order | `testDecisionIsMadeOnLockedFreshRowNotStaleCopy` (cả 2 suite) + lifecycle FOR UPDATE + finalizer idempotent |
| 10 | PAID duplicate retry finalization | `IpnProcessorTest::testDuplicateIpnOnPaidAttemptRetriesFinalization` |
| 11 | FINALIZED không bao giờ đi lùi | `PaymentAttemptLifecycleTest` (transition map terminal, assert cả applyVerifiedPaid/recordVerifiedFailure) |
| 12 | MAC sai → không mutate | `IpnProcessorTest` (assert `lockByAppTransId` KHÔNG được gọi) |
| 13 | amount mismatch → không auto-place | `ReturnProcessorTest` + `IpnProcessorTest` + `PaymentAttemptLifecycleTest::testAmountMismatch*` |
| 14 | contract mismatch → giữ evidence, no order | `IpnProcessorTest::testContractMismatchKeepsPaidStateAndAcknowledges` + ReturnProcessor contract test |
| 15 | PAID lịch sử không authorize placeOrder generic | `CartManagementPlaceOrderGuardTest::testPaidAttemptWithoutGrantIsBlocked` |
| 16 | grant quote-only không đủ | `testQuoteOnlyGrantInsufficient` + `OrderPlacementAuthorizationTest::testQuoteOnlyConsumeApiIsRemoved` |
| 17 | attempt_id sai → block | `testWrongAttemptQuoteIdIsBlocked` |
| 18 | app_trans_id sai → block | `testWrongAppTransIdIsBlocked` |
| 19 | triple khớp persisted → đúng 1 placeOrder | `testExactPersistedTriplePlacesOrder` |
| 20 | grant consume đúng 1 lần | `OrderPlacementAuthorizationTest::testConsumeIfMatchesIsSingleUse` + `testSecondPlaceOrderIsBlocked` |
| 21 | grant cleared khi placeOrder exception | `OrderFinalizerTest` (grant/clear-finally, giữ từ round 1) |
| 22 | IPN finalize KHÔNG đọc/ghi checkout session | `SessionOwnershipContractTest` (4 reflection test) |
| 23 | IPN first → Return sau phục hồi order + session | `ReturnProcessorTest::testLateReturnAfterIpnFinalizationRecoversOrderAndPreparesCustomerSession` |
| 24 | Return first → IPN sau idempotent | `IpnProcessorTest::testDuplicateFinalizedAttemptRecoversBoundOrder` |
| 25 | thanh toán OK không Return → đúng 1 order | `IpnProcessorTest::testIpnMarksAttemptPaidAndFinalizesWithoutBrowserReturn` |
| 26 | abandoned/unpaid → no order | path query != 1 + failure transitions (Return/Ipn suites) |
| 27 | non-ZaloPay không bị ảnh hưởng | `CartManagementPlaceOrderGuardTest::testNonZaloPayQuotePassesThrough` |

## Contract tests bổ sung

- `SessionOwnershipContractTest`: `OrderFinalizer` không có dependency/property/call `Checkout\Session`;
  `IpnProcessor` tương tự; `SuccessSessionPreparer` là writer duy nhất.
- `OrderPlacementAuthorizationTest::testQuoteOnlyConsumeApiIsRemoved`: `method_exists` chứng minh
  `consumeForQuote` đã xoá, `peekForQuote` + `consumeIfMatches` là API duy nhất.
- `PaymentAttemptLifecycleTest` (15 test): lock→re-evaluate fresh→transition idempotent; terminal
  PAID evidence không broaden state; backfill provider tx không overwrite; rollback trên row ẩn
  mất và trên save failure.

# TASK-EDS9T5 — Test result, ROUND 3 (2026-09-10)

## Kết quả

| Suite | Kết quả |
|---|---|
| `Secomm_ZaloPay` Test/Unit (docker PHP 8.3.20) | **OK (157 tests, 530 assertions)** |
| Full `phpunit-secomm.xml` suite | 945 tests / 7 errors — 7/7 pre-existing Tracking, giống baseline |

Regression: base 3b26189e = 849/7 → round 2 = 900/7 → **round 3 = 945/7**. Δ ZaloPay +45,
0 fail, 0 lỗi ngoài bộ pre-existing.

## Ma trận 30 case round 3 → test thực tế

| # | Case | Test |
|---|---|---|
| 1 | bad checksum không terminal-mutate | `ReturnProcessorTest::testBadChecksumNeverBlocksAuthoritativeVerification`, `testMissingChecksumStillVerifiesAuthoritatively` |
| 2 | browser fail (kể cả checksum xấu) + query SUCCESS ⇒ order | `ReturnProcessorTest::testBrowserFailedButAuthoritativeQueryPaidFinalizesOrder`, `testBadChecksumNeverBlocksAuthoritativeVerification` |
| 3 | browser success + query FAIL ⇒ không order | `ReturnProcessorTest::testBrowserPaidButAuthoritativeQueryFailureTransitionsFailed`, `testBadChecksumWithAuthoritativeFailurePlacesNoOrder` |
| 4 | Return luôn dùng authoritative result | 3 test checksum + `testBrowserFailureStatusCannotMarkFailedByItself`, `testBrowserPaidButQueryProcessingPlacesNoOrder` |
| 5 | mismatch evidence re-lock FRESH row | `PaymentAttemptLifecycleTest::testRecordContractMismatchQuarantinesNonFinalizedRow` |
| 6 | stale writer không regress FINALIZED | `PaymentAttemptLifecycleTest::testStaleMismatchWriterCannotRegressFinalizedRow` |
| 7 | stale writer không xoá order_id / provider id | `PaymentAttemptLifecycleTest::testMismatchEvidenceNeverErasesOrderOrProviderIdentity` |
| 8 | callback success = schema `{return_code,return_message}` chuẩn | `IpnTest::testSuccessOutcomeAnswersOfficialContract` (+`testProcessorReceivesDecodedCallbackPayload`) |
| 9 | duplicate callback = ACK success hợp lệ | `IpnTest::testDuplicateCallbackAcknowledgedWithSuccessBody` + `IpnProcessorTest::testDuplicateCallbackOnFinalizedAttemptReturnsDocumentedSuccessAck`, `...OnPaidAttemptRetriesFinalization` |
| 10 | transient = response retry chuẩn (rc 0) | `IpnTest::testTransientFailureAnswersDocumentedRetryBody`, `testUnknownOutcomeFallsBackToRetryableBody`, `testProcessorExceptionStillAnswersDocumentedRetryBody` |
| 11 | MAC sai = invalid chuẩn (rc 2), không mutate | `IpnTest::testInvalidCallbackAnswersDocumentedInvalidBody` + `IpnProcessorTest::testMacFailureReturnsInvalidOutcomeWithoutMutation` |
| 12 | unknown attempt theo policy chuẩn | `IpnTest::testUnknownAttemptFollowsDocumentedInvalidPolicy` + `IpnProcessorTest::testUnknownAttemptReturnsDocumentedInvalidOutcome` |
| 13 | amount THIẾU ⇒ không order (fallback query quyết định) | `IpnProcessorTest::testMissingAmount*` (5 case: exact/processing/wrong/amountless/transport/fail) |
| 14 | amount 0 ⇒ không order | `IpnProcessorTest::testZeroAmountIsConfirmedByAuthoritativeQueryBeforeFinalize` (0 chưa từng tự finalize) |
| 15 | amount SAI ⇒ không order | `IpnProcessorTest::testWrongAmountQuarantinesWithStructuredEvidence` + lifecycle `testRecordAmountMismatchKeepsMoneyRealWithEvidence` |
| 16 | amount EXACT ⇒ được finalize | `IpnProcessorTest::testExactAmountIpnFinalizesWithSuccessOutcome` |
| 17 | mismatch ⇒ quarantine CẤU TRÚC | lifecycle: `...QuarantinesNonFinalizedRow`, `testFirstReconciliationCodeIsKept`; IPN assert `getRequiresReconciliation()` + `RECON_AMOUNT_MISMATCH` |
| 18 | quarantine không bao giờ auto-finalize sau | `IpnProcessorTest::testQuarantinedAttemptCannotAutoFinalizeOnLaterValidCallback` + `OrderFinalizerTest::testQuarantinedAttemptIsNeverAutoFinalized`, `testQuarantinedFinalizedRowIsRefusedToo` |
| 19 | zp_trans_id conflict ⇒ quarantine, giữ id đầu | `IpnProcessorTest::testConflictingProviderTransactionIdQuarantinesWithoutOrder` + lifecycle `...KeepingFirstIdentity`, `...OnStaleRowQuarantines` |
| 20 | duplicate zp_trans_id đúng id = idempotent | `IpnProcessorTest::testExactDuplicateProviderTransactionIdIsIdempotent` + lifecycle `...NeverQuarantines` |
| 21 | recovery SUCCESS+exact ⇒ đúng 1 order | `PaymentRecoveryTest::testLostCallbackWithAuthoritativeSuccessFinalizesExactlyOnce` |
| 22 | recovery PROCESSING ⇒ không order, retry sau | `PaymentRecoveryTest::testProcessingQueryLeavesAttemptForLaterRun` |
| 23 | recovery FAIL ⇒ terminal an toàn | `PaymentRecoveryTest::testAuthoritativeFailRecordsTerminalFailure` |
| 24 | recovery mismatch/conflict ⇒ quarantine, không order | `PaymentRecoveryTest::testWrongAmountQuarantinesWithoutOrder`, `testAmountlessPaidQueryQuarantinesWithoutOrder`, `testQuarantinedFreshRowIsNeverFinalized`, `testFinalizerContractRefusalIsContainmentNotError` |
| 25 | recovery batch bounded + deterministic | `PaymentRecoveryTest::testSelectionIsBoundedAndDeterministic` |
| 26 | claim TRƯỚC HTTP, không lock qua HTTP | `PaymentRecoveryTest::testClaimRunsBeforeHttpAndNoLockSpansTheCall`, `testLostClaimSkipsAttemptWithoutProviderHttp` |
| 27 | cron+IPN+Return đồng thời ⇒ đúng 1 order | hợp thành: claim-race (26) + lifecycle idempotent/fresh-row (`testDecisionIsMadeOnLockedFreshRowNotStaleCopy`, `testRecordVerifiedPaidOnAlreadyPaidRowIsIdempotentNoSave`) + finalizer recover (`testConcurrentPlacementIsRecoveredByReservedOrderId`) |
| 28 | IPN session-free | `IpnTest::testControllerIsSessionFree`, `testControllerIsPostOnly` + `SessionOwnershipContractTest` (4 test, giữ nguyên) |
| 29 | Return chuẩn bị success session | `ReturnProcessorTest::testFreshFinalizationPreparesCustomerSuccessSession`, `testLateReturnAfterIpnFinalizationRecoversOrderAndPreparesCustomerSession` (giữ nguyên) |
| 30 | placeOrder generic bị exact-grant chặn | `OrderFinalizerTest` (grant/clear assertion + `testNonPayableAttemptRefusesFinalization`) + `CartManagementPlaceOrderGuardTest` (round 2, giữ nguyên) |

Blocker-mismatch contract shift (round 2 → 3): `testQuoteTotalChangedRefusesPlacement`,
`testContractFingerprintMismatchRefusesPlacement`, `testInactiveQuoteWithoutOrderKeepsPaidAttemptForReconciliation`,
`testFinalizedWithoutBoundOrderIsRefused`, `testFinalizedWithWrongOrderBindingIsRefused` giờ
assert evidence **qua lifecycle** (`recordContractMismatch` đúng 1 lần) + `save` KHÔNG BAO GIỜ
được gọi — chính là proof "không stale save" của Blocker 2.

Integration: ENVIRONMENT-BLOCKED — không claim PASS.

---

# Round 4 (TL review 4 BLOCKER — ma trận 35 case, HEAD 5285ab78 → corrective round 4)

## Kết quả round 4

| Suite | Kết quả |
|---|---|
| `Secomm_ZaloPay` Test/Unit (docker PHP 8.3.20) | **OK (188 tests, 669 assertions)** — 157 cũ giữ nguyên + 31 mới, 0 fail |
| Full `phpunit-secomm.xml` suite | **976 tests / 7 errors** — 7 lỗi pre-existing Tracking, GIỐNG base 3b26189e (849/7) và round 3 (945/7); 0 regression |
| PHPCS Magento2 severity 10 (toàn module) | **0 errors** (1 warning pre-existing `zalopay.html`, file không đụng) |
| php -l | ALL CLEAN (8 file production + 4 file test) |
| `setup:di:compile` | **OK** — park Mageplaza SocialLogin(Pro) (pre-existing vendor hybridauth, chứng minh round 3), restore nguyên vẹn |
| Integration | ENVIRONMENT-BLOCKED — KHÔNG claim PASS |

## Ma trận round 4 — ánh xạ case → test

| # | Case | Test |
|---|---|---|
| 1 | callback thiếu `type` ⇒ KHÔNG order, INVALID | `IpnProcessorTest::testCallbackWithoutTypeIsInvalidWithoutMutation` (repository + finalizer `never`) |
| 2 | `type=2` (Agreement) ⇒ KHÔNG order | `IpnProcessorTest::testAgreementCallbackTypeIsInvalidWithoutMutation` |
| 3 | `type=1` (Order) được nhận | `IpnProcessorTest::testOrderCallbackTypeOneIsAccepted` |
| 4 | amount EXACT + thiếu zp_trans_id ⇒ KHÔNG finalize trực tiếp; query phải chứng minh identity | `IpnProcessorTest::testExactAmountWithMissingProviderIdRequiresQueryIdentityProof` (finalizer nhận id TỪ QUERY) |
| 5 | amount EXACT + zp_trans_id=0 ⇒ KHÔNG finalize trực tiếp | `IpnProcessorTest::testExactAmountWithZeroProviderIdCannotFinalizeDirectly` (query 3 ⇒ retryable, zero mutation) |
| 6 | thiếu id + query SUCCESS + exact + id hợp lệ ⇒ finalize | `IpnProcessorTest::testMissingProviderIdWithSuccessfulExactQueryFinalizes` |
| 6b | verified money KHÔNG có id từ cả 2 proof ⇒ quarantine `provider_transaction_unavailable` | `IpnProcessorTest::testVerifiedMoneyWithoutAnyProviderIdQuarantinesWithoutOrder` + lifecycle `testProviderIdentityUnavailableQuarantinesWithoutOrder` |
| 7 | callback id A ≠ query id B ⇒ quarantine `provider_transaction_conflict`, giữ CẢ HAI id | `IpnProcessorTest::testCallbackProviderIdConflictingWithQueryIdQuarantinesBothIdentities` + lifecycle `testProviderIdentityConflictPreservesBothIdentities` |
| 8 | app_id khác configured ⇒ INVALID, zero mutation | `IpnProcessorTest::testAppIdMismatchIsInvalidWithoutMutation` |
| 9 | amount malformed `"12abc"` ⇒ INVALID, không cast-mù, không query | `IpnProcessorTest::testMalformedSignedAmountIsInvalidWithoutMutationOrQuery` |
| 10 | zp_trans_id malformed ⇒ INVALID | `IpnProcessorTest::testMalformedSignedProviderIdIsInvalidWithoutMutation` |
| 11 | attempt PAID tồn tại ⇒ Start KHÔNG tạo PaymentAttempt thứ 2 | `PaymentAttemptManagementTest::testPaidAttemptBlocksSecondPaymentAndProviderTransaction` (save `never`) |
| 12 | PAID ⇒ KHÔNG provider transaction thứ 2 | cùng test trên (`commandPool->get` `never`) |
| 13 | PAID + finalize tạm lỗi ⇒ payment thứ 2 vẫn bị chặn | cùng cấu trúc guard trên — chặn theo structured flags, không phụ thuộc finalizer |
| 14 | FINALIZED + quote còn active (bất thường) ⇒ chặn | `PaymentAttemptManagementTest::testFinalizedAttemptBlocksSecondPayment` |
| 15 | requires_reconciliation ⇒ chặn đến khi reconcile | `PaymentAttemptManagementTest::testQuarantinedAttemptBlocksSecondPayment` |
| 16 | late-paid terminal ⇒ chặn payment thứ 2 | `PaymentAttemptManagementTest::testLatePaidTerminalAttemptBlocksSecondPayment` (FAILED + quarantine `late_paid_terminal_state` — structured flags, KHÔNG parse last_error) |
| 17 | provider-state-conflict ⇒ chặn | quarantine `provider_state_conflict` set `requires_reconciliation=1` (lifecycle `testPaidRowPlusAuthoritativeFailureIsStructuredQuarantine`) ⇒ cùng blocking query (15) |
| 18 | FAILED THẬT SỰ unpaid ⇒ được tạo payment mới | `PaymentAttemptManagementTest::testOrdinaryUnpaidFailureAllowsFreshPayment` (row FAILED không bị đụng, attempt mới ACTIVE) |
| 19 | expired/unpaid thường ⇒ payment mới | `PaymentAttemptManagementTest::testExpiredUnpaidAttemptMayStartFreshPayment` (stale + mint) |
| 20 | ACTIVE contract KHÔNG đổi ⇒ reuse URL | `PaymentAttemptManagementTest::testDuplicateStartReusesActiveAttemptWithoutNewTransaction` (giữ nguyên round trước) |
| 21 | ACTIVE contract đổi ⇒ stale + attempt mới an toàn khi không money-real | `testSameAmountWithChangedContractIsNotReused`, `testChangedQuoteTotalStalesOldAttemptAndCreatesNew` (giữ nguyên) + guard 15/16 chặn khi có money-real |
| 22 | Start đồng thời ⇒ TỐI ĐA 1 provider transaction | `PaymentAttemptManagementTest::testConcurrentStartDuringInitializationRefusesInsteadOfMintingSecond` (INITIATED chưa expire ⇒ refuse "being initialized", KHÔNG stale-mark; cả hai Start đều qua CÙNG quote FOR UPDATE lock) |
| 23 | PAID + authoritative FAIL ⇒ status vẫn PAID | lifecycle `testRecordVerifiedFailureNeverRegressesPaidRow` + `testPaidRowPlusAuthoritativeFailureIsStructuredQuarantine` |
| 24 | PAID + authoritative FAIL ⇒ requires_reconciliation=true | `testPaidRowPlusAuthoritativeFailureIsStructuredQuarantine` |
| 25 | reconciliation_code chỉ đúng conflict | cùng test assert `RECON_PROVIDER_STATE_CONFLICT` |
| 26 | SUCCESS sau đó KHÔNG auto-finalize hàng quarantine | lifecycle `testLaterSuccessCannotAutoFinalizeProviderStateConflictRow` (save `never`, code giữ nguyên) + IPN round-3 `testQuarantinedAttemptCannotAutoFinalizeOnLaterValidCallback` + OrderFinalizer gate |
| 27 | FAILED + late PAID ⇒ structured | lifecycle `testLatePaidOnFailedRowSetsStructuredQuarantine` (`late_paid_terminal_state`, id giữ nguyên) |
| 28 | STALE + late PAID ⇒ structured | `testLatePaidOnStaleRowSetsStructuredQuarantine` |
| 29 | EXPIRED + late PAID ⇒ structured | `testLatePaidOnExpiredRowSetsStructuredQuarantine` |
| 30 | callback bình thường KHÔNG BAO GIỜ xóa quarantine | lifecycle `testNormalCallbacksNeverClearTheReconciliationQuarantine` + grep production: KHÔNG có `setRequiresReconciliation(false)` |
| 31 | recovery hết budget ⇒ exhaustion explicit | `PaymentRecoveryTest::testClaimCarriesTheExplicitExhaustionMarkerAndNeverQuarantines` (WHERE `recovery_exhausted = 0` + IF-flip trong payload) + `testExhaustionIsLoggedCriticallyWhenTheMarkerIsPersisted` |
| 32 | exhausted không còn được query tự động | `PaymentRecoveryTest::testExhaustedRowsAreNeverSelectedAgain` (selection filter `neq 1`) + WHERE re-check của claim (31) |
| 33 | IPN hợp lệ sau exhaustion VẪN resolve | `IpnProcessorTest::testRecoveryExhaustedAttemptIsStillResolvedByValidIpn` (attempt `recovery_exhausted=true` + IPN exact ⇒ SUCCESS, PAID, không quarantine) |
| 34 | exhaustion ≠ money-real | `testClaimCarriesTheExplicitExhaustionMarkerAndNeverQuarantines` (payload KHÔNG có key `requires_reconciliation`) + `testMidBudgetClaimDoesNotFlipTheExhaustionMarker`; marker không nằm trong điều kiện blocking của Start |
| 35 | recovery + IPN + Return đồng thời ⇒ đúng 1 order | hợp thành: `testClaimRunsBeforeHttpAndNoLockSpansTheCall` (claim→HTTP→lifecycle→finalize) + `testLostClaimSkipsAttemptWithoutProviderHttp` (writer thắng cuộc owns) + lifecycle idempotent/fresh-row + finalizer recover-by-reserved-order-id (round 3, giữ nguyên) |

Blocker 1 phụ (identity contract): `testVerifiedMoneyWithoutAnyProviderIdQuarantinesWithoutOrder` (6b),
`testProviderIdentityConflictPreservesBothIdentities`, `testProviderIdentityUnavailableQuarantinesWithoutOrder`.

Integration: ENVIRONMENT-BLOCKED — không claim PASS.

# Round 5 (small corrective trên reviewed HEAD 1c6fe65b — 2 blocker: AbstractDb OR-filter + sticky quarantine change flag)

## Kết quả

| Metric | Giá trị |
|---|---|
| ZaloPay unit suite | **192 tests / 690 assertions — OK (0 fail)** (round 4: 188/669 → +4 test, +21 assertion) |
| php -l (5 file đổi) | CLEAN |
| PHPCS Magento2 severity=10 | **0 errors / 0 warnings** (112 PHP file; warning duy nhất còn lại là knockout template pre-existing) |
| setup:di:compile | PASS (9/9, park Mageplaza SocialLogin(Pro) — pre-existing đã chứng minh round 3) |
| Full Secomm regression | **980 tests / 3659 assertions / 7 errors** — baseline 976/7 → 980/7 (+4), cả 7 errors = Secomm\Tracking\EventNormalizerTest PRE-EXISTING |
| DB integration (live MySQL) | ENVIRONMENT_BLOCKED — không có DB trong môi trường unit; KHÔNG claim PASS. Bù lại: real-query test chạy vendor code THẬT (xem dưới) |

## Blocker 1 — `getBlockingAttemptByQuoteId` dùng SAI signature OR của AbstractDb

- **Bệnh**: form `[['attribute' => …, 'in' => …], ['attribute' => …, 'eq' => 1]]` là
  signature của EAV collection. Collection này là
  `PaymentAttemptCollection extends Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection`
  — với AbstractDb, mỗi PHẦN TỬ của `$field` được coi là TÊN FIELD và
  `$condition[$key]` là điều kiện của nó ⇒ form cũ phá vỡ lúc build query
  (chứng minh thực nghiệm: `Warning: Array to string conversion` tại
  `zend-db/library/Zend/Db/Adapter/Abstract.php:1048` qua `quoteIdentifier`).
- **Fix**: signature song song hợp lệ duy nhất của AbstractDb:
  `addFieldToFilter([payment_status, requires_reconciliation], [['in' => [paid, finalized]], ['eq' => 1]])`
  ⇒ SQL logic sinh ra: `quote_id = :quote_id AND (payment_status IN ('paid','finalized') OR requires_reconciliation = 1)`.
  Giữ nguyên: `quote_id` filter, `entity_id DESC`, `setPageSize(1)` (bounded
  LIMIT 1), structured flags ONLY (không parse last_error).

## REQUIRED REAL-QUERY TEST — chứng minh ở mức collection/SQL thật

`Test/Unit/Model/PaymentAttemptRepositoryTest.php` — 2 test, 2 mức:

1. **Signature** `testBlockingLookupUsesValidAbstractDbParallelArrayOrSignature`:
   assert repo gọi ĐÚNG `addFieldToFilter([PAYMENT_STATUS, REQ_RECONCILIATION],
   [['in' => [PAID, FINALIZED]], ['eq' => 1]])` (ghi args theo thứ tự gọi), +
   `setOrder(entity_id, DESC)` + `setPageSize(1)`.
2. **REAL SQL** `testBlockingLookupRendersRealCollectionSql`: production method
   chạy trên COLLECTION THẬT (subclass của `PaymentAttemptCollection`,
   `_construct()` no-op, resource mock) + adapter `Pdo\Mysql` partial mà chỉ
   stub 3 primitive phụ thuộc PDO (`select`, `_connect`, `_quote`) — toàn bộ
   chuỗi còn lại chạy VENDOR CODE THẬT: `AbstractDb::addFieldToFilter`
   (OR-join song song) → `_translateCondition` → `quoteIdentifier` (ZF1) →
   `prepareSqlCondition` (template `{{fieldName}}`) → `quoteInto` → Select thật
   (`Magento\Framework\DB\Select`). Assert trên `getSelect()->getPart(WHERE)`:
   - `[0]`: `` `quote_id` = 42 ``
   - `[1]`: `` (`payment_status` IN('paid','finalized')) OR (`requires_reconciliation` = 1) ``
   - KHÔNG xuất hiện `'failed'` / `'expired'` / `'stale'` trong điều kiện chặn
   - FROM bound đúng alias `main_table`

**Kiểm chứng 2 chiều (test bắt được bug)**: chạy 3 test mới trên code CŨ
(revert tạm) — cả 3 FAIL/ERROR: signature test thấy form cũ
`['attribute'=>…]`; real-SQL test ERROR
`Array to string conversion @ Zend/Db/Adapter/Abstract.php:1048`; lifecycle
test thấy `save` không được gọi. Khôi phục fix ⇒ 3/3 PASS.

## Bug 2 — STICKY quarantine bị mất vì mutator trả false

- **Bệnh**: `recordVerifiedFailure` PAID-branch bỏ qua kết quả của
  `markRequiresReconciliation(...)`; nếu flag flip false→true NHƯNG last_error
  đã đúng sẵn message ⇒ return false ⇒ KHÔNG save ⇒ mất quarantine.
- **Fix** (pattern tổng hợp đã dùng ở các mutator khác):
  `$changed = $this->markRequiresReconciliation(...); if (message khác) { setLastError; $changed = true; } return $changed;`
- **Audit toàn bộ mutator callback round-4** (grep
  `markRequiresReconciliation` tại lifecycle:172/241/294/404/467/525):
  chỉ DUY NHẤT PAID-branch của `recordVerifiedFailure` dính class-bug này.
  Các mutator còn lại tổng hợp đúng `$changed`:
  `recordTerminalPaidEvidence` (markRequiresReconciliation ‖ backfill ‖
  setLastError), `applyMoneyRealQuarantine`, `quarantineProviderTransactionConflict`
  (`$changed = mark … || $changed; setLastError ⇒ true`), `recordContractMismatch`
  (non-finalized: `$changed = mark …`; message khác ⇒ true; else return $changed),
  `recordAmountMismatch` (return true — thừa save, KHÔNG mất).
  Không có thay đổi lifecycle nào khác.

## Ma trận round 5 (case bắt buộc → test)

| # | Case | Test |
|---|---|---|
| 1 | Blocking OR-filter dùng signature AbstractDb hợp lệ | `PaymentAttemptRepositoryTest::testBlockingLookupUsesValidAbstractDbParallelArrayOrSignature` |
| 2 | SQL thật: `quote_id` AND (IN(paid,finalized) OR req_recon=1) | `PaymentAttemptRepositoryTest::testBlockingLookupRendersRealCollectionSql` (WHERE parts thật) |
| 3 | PAID chặn Start | giữ round-4 `testPaidAttemptBlocksSecondPaymentAndProviderTransaction` + SQL test (IN-list) |
| 4 | FINALIZED chặn Start | giữ `testFinalizedAttemptBlocksSecondPayment` + SQL test |
| 5 | requires_reconciliation chặn Start | giữ `testQuarantinedAttemptBlocksSecondPayment` + SQL test |
| 6 | FAILED thường KHÔNG chặn | giữ `testOrdinaryUnpaidFailureAllowsFreshPayment` + SQL test (không 'failed') |
| 7 | EXPIRED thường KHÔNG chặn | giữ `testExpiredUnpaidAttemptMayStartFreshPayment` + SQL test (không 'expired') |
| 8 | STALE thường KHÔNG chặn | NEW `testStaleUnpaidAttemptMayStartFreshPayment` + SQL test (không 'stale') |
| 9 | PAID + FAIL: flag flip false→true với last_error đã đúng ⇒ save VẪN xảy ra | NEW `PaymentAttemptLifecycleTest::testPaidQuarantineFlagFlipPersistsEvenWhenMessageAlreadyMatches` |
| 10 | Audit các mutator khác: không còn lost `$changed` | grep callsites (trên) + test round-3/4 idempotency giữ nguyên |

---

# ADDENDUM (continuation 2026-09-11): DB integration thật đã chạy được

Dòng "DB integration (live MySQL): ENVIRONMENT_BLOCKED" ở bảng trên phản ánh
trạng thái lúc phiên trước — MariaDB thật của stack Docker sau đó đã available
(`slaunchpad-db-1`, MariaDB 10.4), nên integration end-to-end đã thực hiện và
**PASS 15/15** (exit 0). Chi tiết đầy đủ + query thật captured từ repository +
disclosed substitutions + repro: `db-integration/README.md` (cùng thư mục).

Tóm tắt: production `getBlockingAttemptByQuoteId()` chạy verbatim từ worktree
qua `Pdo\Mysql` thật trên DB throwaway `zalopay_r5_it` (CREATE→DDL từ
db_schema.xml→test→DROP; DB `magento` shared không đụng). Query repository tự
sinh (captured):

```
SELECT `main_table`.* FROM `secomm_zalopay_payment_attempt` AS `main_table`
WHERE (`quote_id` = '990101') AND ((`payment_status` IN('paid', 'finalized'))
OR (`requires_reconciliation` = 1)) ORDER BY entity_id DESC LIMIT 1
```

PAID / FINALIZED / requires_reconciliation=1 chặn (hydrated row round-trip
đúng); FAILED/EXPIRED/STALE thường + recovery_exhausted-đơn-thuần không chặn;
latest-entity-first + LIMIT 1 xác nhận trên SQL thật.
