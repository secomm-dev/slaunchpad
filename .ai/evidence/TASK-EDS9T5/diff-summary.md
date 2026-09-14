# TASK-EDS9T5 — Diff summary

Branch: `task/zalopay-payment-first` (base `3b26189e5d19e60ea3b778baa56bcc9650d69355`)

## Production code (payment-first ONLY)

| File | Thay đổi | Lý do |
|---|---|---|
| `Service/OrderPlacementAuthorization.php` | NEW | Grant request-scoped single-use, bind đúng (quote_id, attempt entity_id, app_trans_id); consume bởi guard; `grant()` throw nếu grant còn mở; `clear()` trong finally của finalizer |
| `Plugin/Quote/CartManagementPlaceOrderGuard.php` | NEW | Chặn mọi placeOrder generic (REST/payment-information/GraphQL/SOAP/stale JS/OSC) cho quote ZaloPay không có grant; quote khác ZaloPay đi qua; admin (`submit()`) không bị ảnh hưởng |
| `Service/OrderFinalizer.php` | EDIT | Ranh giới DUY NHẤT quote→Sales Order: mở grant trước `cartManagement->placeOrder` (OrderFinalizer.php:223), `clear()` trong finally; docblock "Sole quote -> Sales Order boundary" |
| `Service/IpnProcessor.php` | REWRITE | IPN = canonical path: MAC key2 + amount lock → PAID → `OrderFinalizer::finalizeOrRecover` NGAY (không cần browser). FINALIZED dup → ack 200; PAID dup → retry finalize; ContractMismatch → 200 ack (reconciliation); lỗi tạm thời → 500 (ZaloPay retry); unknown app_trans_id → null (controller 404) |
| `Controller/Payment/Start.php` | REWRITE | Payment-first: checkout quote → `isInitiable` → `initiate($quote)` (snapshot + attempt + ZaloPay create-order) → redirect payUrl. KHÔNG placeOrder. Fail an toàn giữ cart |
| `Controller/Payment/ReturnAction.php` | REWRITE | UX + uỷ quyền: missing apptransid → cart redirect + lỗi; còn lại uỷ quyền cho `ReturnProcessor` (v2/query + finalizer idempotent) |
| `Controller/Payment/Ipn.php` | REWRITE | Composition-only: parse payload → `IpnProcessor`; null → 404; http_code ≠ 200 set đúng; catch-all → 500 |
| `view/frontend/web/js/.../zalopay-wallet.js` | REWRITE | `placeOrder` = delegate `continueToZaloPay`: validate → selectPaymentMethod → `setPaymentMethodAction` (POST set-payment-information — KHÔNG order) → redirect `zalopay/payment/start`. Không flag, không legacy placeOrder |
| `Model/ZaloPayConfigProvider.php` | EDIT | Bỏ `paymentFirst` + tham `ConfigInterface` thừa; giữ `redirectUrl: zalopay/payment/start` |
| `etc/config.xml` | EDIT | Bỏ node `payment_first` — payment-first là flow DUY NHẤT |
| `etc/adminhtml/system.xml` | EDIT | Bỏ toggle admin `payment_first` (0 occurrence còn lại) |
| `etc/di.xml` | EDIT | Bỏ DI controller + command `ipn`/`complete` + type của 6 command đã xoá; ĐĂNG KÝ plugin `zalopay_place_order_guard` trên `Magento\Quote\Model\QuoteManagement` (sortOrder 10) |
| `etc/frontend/di.xml` | EDIT | Bỏ DI controller (giữ CompositeConfigProvider) |
| `Gateway/Helper/TransactionReader.php` | REWRITE | Chỉ còn `readPayUrl()` (bỏ `readOrderId`/`isIpn` — order-first) |
| `etc/module.xml` | EDIT | setup_version 1.0.0 → 1.1.0 |
| `i18n/en_US.csv`, `i18n/vi_VN.csv` | EDIT | +10 chuỗi customer-facing payment-first (EN/VI) |

### DELETED (chuỗi order-first cũ — `git rm`)

```
Gateway/Command/CompleteCommand.php            Gateway/Response/TransactionCompleteHandler.php
Gateway/Command/CompleteUpdateDetailsCommand.php  Gateway/Response/TransactionReturnHandler.php
Gateway/Command/IpnCommand.php                 Gateway/Validator/CompleteValidator.php
Gateway/Command/IpnUpdateDetailsCommand.php    Gateway/Validator/ReturnValidator.php
Gateway/Command/UpdateDetailsCommand.php       Gateway/Command/UpdateOrderCommand.php
```

## Tests

| File | Thay đổi |
|---|---|
| `Test/Unit/Service/OrderPlacementAuthorizationTest.php` | NEW — grant/consume/single-use/triple-bind/clear (7 test) |
| `Test/Unit/Plugin/Quote/CartManagementPlaceOrderGuardTest.php` | NEW — block/pass-through/case matrix (7 test) |
| `Test/Unit/Service/IpnProcessorTest.php` | REWRITE — IPN-first contract: finalize không browser, dup FINALIZED/PAID, MAC 500, amount-mismatch, ContractMismatch ack, transient 500, unknown → null (9 test, +3) |
| `Test/Unit/Service/OrderFinalizerTest.php` | EDIT — ctor +`OrderPlacementAuthorization`; assert grant đúng triple, clear trong finally, mismatch KHÔNG mở grant, recovery không grant |
| `Test/Integration/Helper/TransactionReaderTest.php` | TRIM — chỉ readPayUrl |
| `Test/Integration/Controller/Payment/{StartTest,ReturnActionTest,IpnTest}.php` | REWRITE — hermetic failure paths payment-first (không order khi không verify) |

## Docs/.ai

`README.md` (payment-first flow), `CHANGELOG.md` (1.1.0), `.ai/specs/SPEC-…md`,
`.ai/records/tasks/TASK-EDS9T5.md`, `.ai/records/decisions/DEC-TASKEDS9T5-001.md`,
`.ai/plans/PLAN-…md`, `.ai/evidence/TASK-EDS9T5/*`.

## KHÔNG đụng

`vendor/*`, `Mageplaza_*`, module Secomm khác (MoMo/LLMS/…), Bitbucket,
nhánh dev/development/*, worktree của task khác.

---

# CORRECTIVE ROUND 2 (TL review 4 BLOCKER) — diff summary

Base: `66f684468c9969e64e341f98ccafd475576ab138` → corrective commits trên `task/zalopay-payment-first`.
Tổng: 19 file (13 modified + 6 new), ~1114 insertions / ~630 deletions (chưa tính evidence files).

## Production (6)

| File | Thay đổi |
|---|---|
| `Service/PaymentAttemptLifecycle.php` | **NEW** — bộ máy chuyển trạng thái canonical: `recordVerifiedPaid` / `recordVerifiedFailure` / `recordAmountMismatch`; mỗi op = begin tx → `lockByAppTransId` (FOR UPDATE) → re-evaluate FRESH status → transition idempotent / evidence-only → save → commit; KHÔNG giữ tx qua HTTP |
| `Service/SuccessSessionPreparer.php` | **NEW** — writer DUY NHẤT của success-session (mirror Onepage::saveOrder), chạy trên Return path sau finalizer; swallow exception + log |
| `Service/ReturnProcessor.php` | **REWRITE** — quyết định CHỈ bằng `v2/query` (return_code 1=paid, 3=processing, không tự chế code); browser status/checksum chỉ là lookup/evidence; checksum sai → từ chối TRƯỚC query, không mutate; processing → không mutate; failure → `recordVerifiedFailure` (PAID/FINALIZED không bao giờ regressed); Finalized xong gọi SuccessSessionPreparer |
| `Service/IpnProcessor.php` | **REWRITE** — delegate lifecycle (lock + fresh re-eval); KHÔNG session; MAC fail → 500 không mutate; mismatch/contract-mismatch → ack 200 "Recorded for reconciliation" (evidence giữ lại); success → 200; FINALIZED early-ack xoá (dup IPN chạy lifecycle no-op + finalizer recovery) |
| `Service/OrderFinalizer.php` | EDIT — bỏ `Magento\Checkout\Model\Session` (ctor + `prepareSuccessSession` + alias `finalize()`); FINALIZED recovery không đụng session; `placeOrder` giờ ở dòng 207 (call site production DUY NHẤT) |
| `Service/OrderPlacementAuthorization.php` + `Plugin/Quote/CartManagementPlaceOrderGuard.php` | EDIT — xoá `consumeForQuote(quoteId)` (quote-only, misleading); thêm `peekForQuote` (read-only); guard: peek → load attempt theo `grant.attempt_id` → đối chiếu `quote_id == cartId` && `app_trans_id == grant` → mới `consumeIfMatches` (single-use). Session/browser/Registry/PAID-status không authorize |

## Tests (9)

| File | Thay đổi |
|---|---|
| `Test/Unit/Service/PaymentAttemptLifecycleTest.php` | **NEW** — 15 test: transition map, idempotency PAID/FINALIZED, late-PAID-evidence trên STALE/FAILED giữ evidence + identity, backfill, mismatch, rollback (row ẩn mất / save failure), lock-fresh-row proof, begin/commit/rollback asserts |
| `Test/Unit/Service/ReturnProcessorTest.php` | **REWRITE** — 13 test (case 1-5, 7, 13, 14, 23): REAL lifecycle wired; browser-status không authority; query-first; locked fresh row; recovery + SuccessSessionPreparer |
| `Test/Unit/Service/IpnProcessorTest.php` | **REWRITE** — 10 test (case 6, 8, 10, 13, 14, 22, 24, 25): REAL lifecycle; MAC fail → lockByAppTransId KHÔNG gọi; no-session; dup PAID retry finalization |
| `Test/Unit/Service/OrderPlacementAuthorizationTest.php` | **NEW** — 10 test: `testQuoteOnlyConsumeApiIsRemoved` (method_exists), peek không consume, consumeIfMatches triple + single-use |
| `Test/Unit/Plugin/Quote/CartManagementPlaceOrderGuardTest.php` | **NEW** — 10 test chạy `beforePlaceOrder` THẬT với REAL OrderPlacementAuthorization (case 15-20, 27) |
| `Test/Unit/Service/SuccessSessionPreparerTest.php` | **NEW** — 2 test: mirror Onepage session writes; swallow session failure |
| `Test/Unit/Service/SessionOwnershipContractTest.php` | **NEW** — 4 reflection test: OrderFinalizer/IpnProcessor không có Checkout\Session; SuccessSessionPreparer là writer duy nhất |
| `Test/Unit/Service/OrderFinalizerTest.php` | EDIT — xoá mọi session property/arg/assert; xoá `testDuplicateFinalizeRebuildsEmptySuccessSession`; thêm `testDuplicateFinalizeDoesNotTouchCheckoutSession` |
| `Test/Unit/Service/SessionStub.php` | giữ (dùng bởi SuccessSessionPreparerTest) |

## Docs / meta (4)

| File | Thay đổi |
|---|---|
| `etc/module.xml` | setup_version 1.1.0 → **1.1.1** |
| `CHANGELOG.md` | mục `[1.1.1]`: 4 blocker fix + money-real late-callback semantics + **4 case MANUAL reconciliation** (deliberately NO reconciliation cron — claim "Phase 2 cron" xoá sạch) |
| `.ai/records/decisions/DEC-TASKEDS9T5-002.md` | **NEW** — 5 quyết định corrective, verified_against_commit 66f68446 |
| `.ai/records/tasks/TASK-EDS9T5.md` | decisions: [DEC-001, DEC-002]; section "Kết quả corrective round 2" |

# ROUND 3 diff (trên da510930 — 17 file edit + 4 file NEW)

## Production (12)

| File | Thay đổi |
|---|---|
| `Api/Data/PaymentAttemptInterface.php` | +3 cột const (REQ_RECONCILIATION, RECONCILIATION_CODE, RECOVERY_ATTEMPTS) + 4 code RECON_* + 6 accessor |
| `Model/PaymentAttempt.php` | +6 typed accessor (bool/nullable string/int) |
| `Service/PaymentAttemptLifecycle.php` | recordContractMismatch (re-lock fresh row; FINALIZED evidence-only / khác quarantine contract_mismatch); conflict pre-check trong recordVerifiedPaid + recordAmountMismatch; quarantineProviderTransactionConflict (giữ id đầu, markPaid(null), RECON_PROVIDER_TX_CONFLICT); markRequiresReconciliation (idempotent, giữ code đầu) |
| `Service/OrderFinalizer.php` | +ctor PaymentAttemptLifecycle; gate `requires_reconciliation` TRƯỚC mọi xử lý; catch ContractMismatch ⇒ lifecycle->recordContractMismatch (xoá hẳn private recordContractMismatch stale-save); provider-id chỉ backfill khi `=== null` |
| `Service/ReturnProcessor.php` | checksum sai: throw ⇒ log-only (tamper evidence); query LUÔN chạy và quyết định (B1) |
| `Service/IpnProcessor.php` | REWRITE: 4 OUTCOME_*; MAC hash_equals key2; unknown ⇒ invalid; amount thiếu/0 ⇒ verifyByQuery (rc1+exact mới finalize; rc3 retryable; ≠1 authoritative-failure; amount 0/khác ⇒ quarantine); mismatch ⇒ recordAmountMismatch + ACK; conflict/quarantine ⇒ ACK không order (B3/B4/B5) |
| `Controller/Payment/Ipn.php` | REWRITE: HttpPostActionInterface-only (+CSRF); RESPONSE_BY_OUTCOME ⇒ LUÔN HTTP 200 `{return_code, return_message}` = 1 "Success" / 2 "Invalid" / 0 "Temporary failure, please retry."; exception ⇒ rc 0 (B3) |
| `Service/PaymentRecovery.php` | **NEW** (B6): selection bounded (non-terminal, unbound, chưa quarantine, recovery_attempts < max, created_at ≤ now−window, ORDER BY entity_id, LIMIT batch); claim = atomic conditional UPDATE (recovery_attempts+1) TRƯỚC HTTP; v2/query NGOÀI tx; rc1+exact ⇒ lifecycle → finalizer; rc3 skip; ≠1 ⇒ recordVerifiedFailure; amount lệch ⇒ quarantine; ContractMismatch ⇒ containment |
| `Cron/PaymentRecoveryCronjob.php` | **NEW**: wrapper cron, log summary khi có claim |
| `etc/db_schema.xml` | +3 cột: requires_reconciliation (bool), reconciliation_code (varchar 64), recovery_attempts (smallint) |
| `etc/db_schema_whitelist.json` | +3 cột tương ứng |
| `etc/config.xml` | +recovery_window 15 / recovery_batch_size 25 / recovery_max_attempts 5 |
| `etc/crontab.xml` | +job `secomm_zalopay_payment_recovery_cronjob` */5 * * * * |
| `etc/di.xml` | +type IpnProcessor/PaymentRecovery: commandPool = ZaloPayCommandPool |
| `etc/module.xml` | setup_version 1.1.1 → **1.2.0** |

## Tests (4 edit + 2 NEW)

| File | Thay đổi |
|---|---|
| `Test/Unit/Service/IpnProcessorTest.php` | REWRITE: outcome-string contract, ctor +commandPool mock, +B4 (missing/zero amount × 6 case), +B5 (conflict/duplicate/quarantine-later), quarantine assert cấu trúc |
| `Test/Unit/Service/PaymentAttemptLifecycleTest.php` | +7: contract-mismatch quarantine/FINALIZED evidence-only/stale-writer ×2/idempotent/conflict ×2/first-code-kept |
| `Test/Unit/Service/OrderFinalizerTest.php` | +ctor lifecycle; 5 mismatch test chuyển assert sang lifecycle (save NEVER) +2 quarantine gate |
| `Test/Unit/Service/ReturnProcessorTest.php` | 3 test checksum mới (query luôn chạy; browser chỉ evidence) |
| `Test/Unit/Controller/Payment/IpnTest.php` | **NEW** — 12 test: schema {return_code,return_message} per outcome, exception ⇒ rc0, payload decode, POST-only reflection, session-free |
| `Test/Unit/Service/PaymentRecoveryTest.php` | **NEW** — 13 test: case 21-26 (exact-one-order, processing, fail, mismatch ×4, bounded selection, claim-before-HTTP event log, lost-claim, batch-continue, transport-error) |

## Docs / meta (5)

| File | Thay đổi |
|---|---|
| `CHANGELOG.md` | mục `[1.2.0]`: 5 blocker fix + Added recovery worker + Changed schema/BREAKING callback response |
| `.ai/records/decisions/DEC-TASKEDS9T5-003.md` | **NEW** — 6 quyết định round 3, verified_against_commit da510930 |
| `.ai/records/tasks/TASK-EDS9T5.md` | section "Kết quả corrective round 3" |
| `.ai/evidence/TASK-EDS9T5/provider-contract-round3.md` | **NEW** — bằng chứng contract chính thức (4 URL docs.zalopay.vn, v2 generation) |
| `.ai/evidence/TASK-EDS9T5/{command-output,test-result,diff-summary}.md` | § round 3 |

---

# Round 4 diff (trên HEAD 5285ab78, branch task/zalopay-payment-first)

## Production (12 file)

| File | Thay đổi | Lý do (blocker) |
|---|---|---|
| `Service/IpnProcessor.php` | EDIT | B1: `type` gate ĐẦU TIÊN (≠1 ⇒ INVALID zero-mutation); app_id gate (so khớp configured, mismatch ⇒ INVALID); parse STRICT `amount`/`zp_trans_id` (absent/zero ⇒ query fallback, positive ⇒ direct, malformed ⇒ INVALID — không cast-mù); conflict callback-vs-query id ⇒ `recordProviderIdentityConflict`; không id từ cả 2 proof ⇒ `recordProviderIdentityUnavailable` |
| `Service/PaymentAttemptLifecycle.php` | EDIT | B3: PAID+FAIL ⇒ quarantine `provider_state_conflict`; late-PAID terminal ⇒ `late_paid_terminal_state`; NEW `recordProviderIdentityConflict` (giữ CẢ HAI id), `recordProviderIdentityUnavailable`, `applyMoneyRealQuarantine` chung |
| `Model/PaymentAttemptManagement.php` | EDIT | B2: dưới quote lock, TRƯỚC reuse/stale/mint — `getBlockingAttemptByQuoteId` ⇒ throw customer-safe (`blockingMessage`, quarantine ưu tiên); INITIATED chưa expire ⇒ refuse "being initialized" (tối đa 1 provider transaction) |
| `Model/PaymentAttemptRepository.php` | EDIT | B2: NEW `getBlockingAttemptByQuoteId` — OR-filter structured (paid/finalized/requires_reconciliation), ORDER BY entity_id DESC, LIMIT 1 (bounded) |
| `Api/PaymentAttemptRepositoryInterface.php` | EDIT | Khai báo `getBlockingAttemptByQuoteId` |
| `Service/PaymentRecovery.php` | EDIT | B4: selection + claim WHERE chặn `recovery_exhausted`; claim payload IF-flip marker (1 UPDATE duy nhất); log CRITICAL khi cạn budget |
| `Api/Data/PaymentAttemptInterface.php` | EDIT | B4: const `RECOVERY_EXHAUSTED`, `RECON_PROVIDER_STATE_CONFLICT`, `RECON_LATE_PAID_TERMINAL_STATE`, `RECON_PROVIDER_TX_UNAVAILABLE` + getter/setter marker |
| `Model/PaymentAttempt.php` | EDIT | B4: `getRecoveryExhausted`/`setRecoveryExhausted` |
| `Gateway/Helper/Authorization.php` | EDIT | B1d: `getAppId()` (config plain) cho defensive comparison |
| `etc/db_schema.xml` + `etc/db_schema_whitelist.json` + `etc/module.xml` | EDIT | B4: cột `recovery_exhausted` (declarative, ADD-only, safe) + whitelist + setup_version 1.3.0 |

## Tests (4 file, +31 test — 157 cũ giữ nguyên)

| File | Thay đổi |
|---|---|
| `Test/Unit/Service/IpnProcessorTest.php` | +13 (ma trận 1–10 + 6b + 33; payload helper thêm `type:1` + `app_id`) |
| `Test/Unit/Model/PaymentAttemptManagementTest.php` | +7 (ma trận 11–19, 22) |
| `Test/Unit/Service/PaymentAttemptLifecycleTest.php` | +8 (ma trận 23–30 + identity conflict/unavailable) |
| `Test/Unit/Service/PaymentRecoveryTest.php` | +4 (ma trận 31–34; helper `stubExhaustionRead`) |

## Docs

| File | Thay đổi |
|---|---|
| `.ai/records/decisions/DEC-TASKEDS9T5-004.md` | NEW |
| `.ai/evidence/TASK-EDS9T5/provider-contract-round4.md` | NEW (contract chính thức verbatim: type envelope, MAC toàn data, zp_trans_id, app_id, callback response, v2/query) |
| `.ai/evidence/TASK-EDS9T5/test-result.md` | APPEND round-4 (35-case matrix) |
| `.ai/evidence/TASK-EDS9T5/diff-summary.md` + `command-output.txt` | APPEND round-4 |
| `CHANGELOG.md` | NEW entry 1.3.0 |

# Round 5 diff (trên reviewed HEAD 1c6fe65b, branch task/zalopay-payment-first)

## Production (2 file)

| File | Thay đổi | Lý do (blocker) |
|---|---|---|
| `Model/PaymentAttemptRepository.php` | EDIT | B1: `getBlockingAttemptByQuoteId` đổi OR-filter sang signature song song hợp lệ của AbstractDb — `addFieldToFilter([payment_status, requires_reconciliation], [['in' => [paid, finalized]], ['eq' => 1]])` ⇒ SQL `quote_id = ? AND (payment_status IN ('paid','finalized') OR requires_reconciliation = 1)`. Form cũ `[['attribute' => …]]` là signature EAV — trên AbstractDb phá vỡ lúc build query (chứng minh: Array-to-string warning trong quoteIdentifier). Giữ nguyên quote_id filter + entity_id DESC + setPageSize(1) + structured flags only |
| `Service/PaymentAttemptLifecycle.php` | EDIT | B2: `recordVerifiedFailure` PAID-branch TỔNG HỢP `$changed` — `$changed = markRequiresReconciliation(...)`; message khác ⇒ setLastError + `$changed = true`; return `$changed`. Trước đây flag flip false→true với last_error đã đúng ⇒ return false ⇒ KHÔNG save ⇒ mất quarantine. Audit 6 callsites khác: KHÔNG còn instance thứ hai của class-bug |

## Tests (3 file, +4 test)

| File | Thay đổi |
|---|---|
| `Test/Unit/Model/PaymentAttemptRepositoryTest.php` | NEW, 2 test: (1) signature song song chính xác + order/limit; (2) REAL-QUERY — production method chạy trên collection thật + adapter Pdo\Mysql partial (chỉ stub select/_connect/_quote) ⇒ assert WHERE parts thật: `` `quote_id` = 42 `` + `` (`payment_status` IN('paid','finalized')) OR (`requires_reconciliation` = 1) `` + KHÔNG 'failed'/'expired'/'stale' + FROM bound `main_table` |
| `Test/Unit/Service/PaymentAttemptLifecycleTest.php` | +1: flag flip false→true với last_error đã đúng message ⇒ `save` đúng 1 lần (fail khi chạy trên code cũ — proven) |
| `Test/Unit/Model/PaymentAttemptManagementTest.php` | +1: STALE thường (không money-real, không quarantine) không block, không active — mint attempt mới |

## Docs

| File | Thay đổi |
|---|---|
| `.ai/evidence/TASK-EDS9T5/test-result.md` | + section Round 5 (kết quả + ma trận 10 case + two-direction proof) |
| `.ai/evidence/TASK-EDS9T5/command-output.txt` | + section Round 5 (preconditions, 2 hướng test, phpcs/di-compile/regression, audit) |
| `.ai/evidence/TASK-EDS9T5/diff-summary.md` | + section này |
| `.ai/records/tasks/TASK-EDS9T5.md` | + note round 5 |
