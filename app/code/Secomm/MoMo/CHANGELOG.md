# Changelog

## [2.3.2] - 2026-09-21

### Fixed
- **Payment Action config key aligned with runtime (MOMO-05, issue #17)**:
  the admin field previously saved under
  `payment/momo_payment/momo_payment_action` — a key with NO runtime
  reader — while `OrderFinalizer::captureOrder()` (and Magento core
  placement via `getConfigPaymentAction()`) read the canonical
  `payment/momo_payment/payment_action`. Changing Payment Action in the
  admin therefore never took effect. The admin field now writes the
  canonical key, `Model\Config::KEY_PAYMENT_ACTION` is corrected to
  `payment_action` and is the single code-level source (used by
  `OrderFinalizer`). Default semantics unchanged (`authorize_capture` →
  capture after verification; any other/missing value finalizes without
  capture — now regression-tested).
- **No migration needed (compatibility decision)**: the legacy key never
  had a runtime reader and its select offered only `authorize_capture`, so
  every existing installation always ran the default — behavior is
  provably unchanged. Any orphaned `momo_payment_action` row in
  `core_config_data` is inert and may be deleted (see README legacy note).

## [2.3.1] - 2026-09-21

### Fixed
- **Browser-Return `resultCode` classification made fail-safe (MOMO-04,
  issue #16)**: `ReturnProcessor` previously coerced a missing `resultCode`
  to `-1` and treated EVERY code outside `0`/`7000`/`7002` as a verified
  failure — falsely failing attempts for `1000` (initiated), `9000`
  (authorized/success for the module's 1-step `captureWallet`/default
  autoCapture contract), request/system codes, missing/unparseable values
  and any unmapped future code. Now the authoritative `v2/query` outcome is
  classified ONLY through explicit provider-documented allowlists
  (developers.momo.vn contract, verified 2026-09-21): PAID `0`/`9000` (same
  amount + positive-transId + identity guards, same finalization path),
  PENDING `1000`/`7000`/`7002` (check back shortly, no mutation), VERIFIED
  FAILURE only for the documented final-failure allowlist (`98`, `99`,
  `1001`–`1007`, `1017`, `1026`, `2019`, `4001`, `4002`, `4100`), and
  EVERYTHING else — request/system codes, missing, unparseable, unmapped —
  is **AMBIGUOUS**: logged, no mutation, never false-failed; the customer
  gets a verification-unavailable/retry message.
- **Shared purchase-query classifier (single source of truth)**: extracted
  `Service\PurchaseQueryClassifier` + `Service\PurchaseQueryOutcome` and
  migrated BOTH authoritative purchase-query callers (browser
  `ReturnProcessor` MOMO-01/04 + recovery `PaymentRecovery` MOMO-03) onto
  it — the two paths can no longer drift apart. Refund classification stays
  separate (different semantics, notably `9000`). Each ambiguous outcome
  still logs its distinct cause (unparseable / request-system / unmapped).
- Unit coverage: new `Test/Unit/Service/PurchaseQueryClassifierTest.php`
  (every allowlist code + ambiguity reasons) and 6 new `ReturnProcessor`
  cases (`1000`→pending, `9000`→paid path, `10`/missing/unparseable/
  unknown→ambiguous, `1001`→verified failure); `PaymentRecovery` suite
  regression-pinned against the shared classifier.

## [2.3.0] - 2026-09-21

### Added
- **Bounded lost/delayed-IPN payment recovery (MOMO-03, issue #5)**: cron
  `secomm_momo_payment_recovery_cronjob` (every 5 minutes) proactively runs
  the authoritative `v2/query` for `active`/`paid` attempts with no bound
  order that are past the callback window, so money MoMo already accepted
  cannot stay stranded without an order when the IPN is lost (see incident
  #13).
- `Service\PaymentRecovery` — deterministic selection (non-terminal, unbound,
  un-quarantined, un-exhausted, in-budget, oldest first, page-limited), an
  atomic conditional-UPDATE claim BEFORE the provider HTTP (a lost race skips
  the row; no DB lock across MoMo calls), and outcomes routed ONLY through
  the canonical `PaymentAttemptLifecycle` + `OrderFinalizer` — verified PAID
  finalizes exactly one order; pending/ambiguous never mutate; transport
  failures and unparseable `resultCode` are AMBIGUOUS, never false failures.
- `secomm_momo_payment_attempt` additive columns `recovery_attempts` (per-row
  proactive query budget) + `recovery_exhausted` (explicit operational-only
  exhaustion marker — NOT money evidence, never a quarantine: a valid later
  IPN/Return still resolves the payment).
- Config defaults `payment/momo_payment/recovery_window` (15 min),
  `recovery_batch_size` (25), `recovery_max_attempts` (5).
- Unit coverage: `Test/Unit/Service/PaymentRecoveryTest.php` +
  `Test/Unit/Cron/PaymentRecoveryCronjobTest.php`.

### Fixed (correction round, 2026-09-21 — review of the reviewed TIP `7601df05`)
- **Recovery query classifier bounded by the documented MoMo result-code
  contract** (developers.momo.vn result-code table, verified 2026-09-21).
  The initial "any non-zero code = verified failure" rule was unsafe: `1000`
  (initiated) and `9000` (authorized — success for the module's 1-step
  `captureWallet`/default autoCapture contract) would be marked FAILED, and
  request-level codes (`10`/`11`/`12`/`45`/`47`…) treated as terminal
  transaction failures. Now:
  - PAID = `0`/`9000` only — both still pass amount + positive-transId +
    identity guards before `recordVerifiedPaid`/`finalizeOrRecover`;
  - PENDING = `1000`/`7000`/`7002` — no mutation;
  - VERIFIED FAILURE only for the documented final-transaction-failure
    allowlist (`98`, `99`, `1001`–`1007`, `1017`, `1026`, `2019`, `4001`,
    `4002`, `4100`);
  - request/system codes (`10`–`13`, `20`–`22`, `40`–`43`, `45`, `47`) and
    ANY unmapped code are **AMBIGUOUS** — logged, no mutation, never a
    false failure (fail-safe default, never FAILED).
  Regression suite grew 11 → 16 tests: `1000`→pending, `9000`→paid path,
  `10`→ambiguous, unmapped→ambiguous, `1001`→verified failure.

> Note: `composer.json` still declares `"version": "2.0.0"` — known drift vs
> this CHANGELOG (2.2.1 before this entry); left untouched, out of scope.

## [2.2.1] - 2026-09-21

### Fixed
- **Missing DI preference blocked non-MoMo orders (MOMO-01-HF1, issue #13)**:
  added the ObjectManager preference
  `Secomm\MoMo\Api\PaymentAttemptRepositoryInterface` →
  `Secomm\MoMo\Model\PaymentAttemptRepository`. The global
  `QuoteManagement` placeOrder guard and every payment-first service
  (PaymentAttemptManagement, IpnProcessor, OrderFinalizer,
  PaymentAttemptLifecycle, ReturnProcessor) resolve this interface;
  without the preference any `placeOrder` call — including ZaloPay and
  other non-MoMo methods — failed with "Cannot instantiate interface" at
  plugin instantiation. Guard behaviour is unchanged: non-MoMo quotes stay
  a no-op, MoMo quotes still require the persisted attempt-backed grant.
- Regression coverage: `Test/Unit/Di/PaymentAttemptDiBindingTest.php` pins
  the preference + the real repository/guard construction path (DI compile
  alone cannot catch a missing constructor preference — runtime-only).

## [2.2.0] - 2026-09-18

### Added
- **Native Credit Memo refunds made idempotent and uncertainty-safe (MOMO-02)**:
  the admin flow stays native Magento accounting; the module now owns provider
  request identity, response classification and reconciliation evidence.
- `secomm_momo_refund` table (db_schema + whitelist): full order/creditmemo/
  invoice linkage, amount, original MoMo `transId`, minted refund identity,
  classification, guarded lifecycle (`open_flag` NULL-trick UNIQUE index =
  at most one open refund per payment; terminal states release the slot),
  persisted on an **independent DB connection** so evidence survives the
  `CreditmemoService` sales-transaction rollback.
- `RefundCommand` (gateway `refund` command): durable identity opened BEFORE
  any provider call, echo-verified classification, guarded outcome transitions.
- `RefundResultClassifier` — refund responses carry NO signature (verified
  provider contract): SUCCESS requires intact echoes (requestId/orderId/
  amount, partnerCode conflict-intolerant) + `resultCode == 0`; `7002` is
  processing → UNKNOWN, never FAILED; malformed/echo-mismatch/transport →
  UNKNOWN. `classifyQuery` parses `/v2/gateway/api/refund/query` evidence
  conservatively (ambiguity never resolves to a terminal verdict).
- `RefundRequestManager` — stale-pending sweep (TTL 600s ≫ 45s HTTP timeout),
  open-row block (pending/unknown), budget drift guard, race-safe insert,
  `last_error` reserved for UNKNOWN rows.
- Operator CLI: `momo:refund:list` (evidence browser) and
  `momo:refund:resolve <requestId>` (query-only resolution; the refund is
  never re-posted with a new identity; SUCCESS outcome instructs an offline
  credit memo realignment).
- `Plugin\Sales\CreditmemoService` — backfills `creditmemo_id` onto the
  refund row post-commit (read-only on sales data).
- 45s HTTP timeout on refund transfers (MoMo documents a 30s refund minimum;
  the ~10s Laminas default would misclassify slow-but-successful refunds).
- Focused unit suites: classifier contract, command flow (block/refusal/
  unknown/transport), manager guards, identity minting, plugin backfill.

### Fixed
- Refund requests previously reused the purchase `orderId` (violates the MoMo
  contract: the refund's `orderId` MUST differ) and trusted HTTP status
  without a response-integrity check; refund `requestId`s are now minted per
  operation (provider idempotency key, ≥31 days).

### Fixed (correction round, 2026-09-21 — provider-contract boundary)
- `momo:refund:resolve` mints a FRESH query `requestId` per invocation and
  signs with it; the stored refund `requestId` stays immutable as
  refund-submission evidence (the query is a different API operation and
  never reuses the refund's provider idempotency key).
- `classifyQuery` requires an EXACT `refundTrans[].orderId` match — the
  single-entry fallback is removed; ambiguity/mismatch stays UNKNOWN.
- Direct refund SUCCESS requires strict provider data: amounts must be
  well-formed integers (no `(int)` cast of malformed values like
  `"150000abc"`), and `resultCode == 0` additionally requires a valid
  positive refund `transId` — malformed/unverifiable → UNKNOWN (AC5).
- Full provider non-final result-code set (Final Status = No: 10/11/12/13,
  20/21/22, 40/41/42/43/45/47, 7000, 7002, 9000) classifies UNKNOWN
  (`provider_processing`) and keeps the refund slot open — only
  provider-confirmed FINAL failures release the slot (FAILED). Previously
  only `7002` was non-final; codes like 21/7000 wrongly resolved FAILED.
- `momo:refund:resolve` now catches `Magento\Payment\Gateway\Http
  \ClientException` (the import pointed at a non-existent module class, so
  transport errors crashed the command instead of keeping the row unknown).

### Fixed (correction round 2, 2026-09-21 — query response binding)
- `classifyQuery` now validates the refund/query response's TOP-LEVEL
  identity echoes before reading any `refundTrans` evidence: the response
  must echo the exact fresh query `requestId` this invocation sent and the
  refund's `orderId`, and a conflicting `partnerCode` is rejected
  (absence tolerated, same rule as the direct refund path). A response
  that answers a different/stale query can no longer resolve the row.
- `1000` ("transaction initiated, waiting for user confirmation", Final
  Status = No) added to `NON_FINAL_RESULT_CODES` — UNKNOWN
  (`provider_processing`), never terminal FAILED, slot stays open.

## [2.1.0] - 2026-09-18

### Changed
- **Payment-first order finalization (MOMO-01)**: `NO verified MoMo payment →
  NO Magento Sales Order`. Starting checkout with MoMo no longer creates a
  Sales Order. The ACTIVE QUOTE survives payment; a `secomm_momo_payment_attempt`
  row freezes amount/currency/contract (sha-256 quote fingerprint) and the
  merchant reference (`order_ref`/`request_id`) before the provider call.
- Authoritative verification boundary: only a signature-valid MoMo callback
  whose `partnerCode`, `orderId`, `requestId`, `extraData` and `amount` match
  the attempt can move the attempt to `PAID` (money-real). The browser Return
  re-verifies server-side via `v2/query` — MoMo signs the query REQUEST; the
  response carries no signature, so identity = the `partnerCode`/`orderId`/
  `requestId` echoes against the exact request just sent (fresh per-query
  requestId; the create-time `request_id` stays create/IPN-only) — browser
  parameters never create an order. Result codes 7000 AND 7002 are
  non-terminal (zero mutation).
- Query-path correction (review fix): QueryValidator no longer requires an
  undocumented query-response signature (MoMo's query API returns none) —
  identity is the echo-of-exact-request + attempt check; QueryDataBuilder
  mints a fresh per-query `requestId` instead of reusing the create-time
  attempt `request_id`.
- Canonical finalizer: attempt-row `FOR UPDATE` lock → single-use order
  placement grant (verified via a `QuoteManagement::placeOrder` plugin) →
  exactly one order → invoice/capture per `payment_action` → attempt bound to
  the order (`order_id` unique). Duplicate Return/IPN callbacks recover the
  bound order instead of placing twice.
- Money-real is durable: if finalization fails after `PAID`, the attempt stays
  `PAID`, the IPN answers HTTP 500 so MoMo retries as the recovery driver;
  anomalies (amount/contract/identity conflicts) quarantine
  (`requires_reconciliation`, typed codes) for manual review — never silent
  cancellation.

### Added
- `secomm_momo_payment_attempt` table (db_schema) + `PaymentAttempt` model /
  `PaymentAttemptRepository` / `PaymentAttemptLifecycle` (state machine:
  initiated→active→paid→finalized, terminal states, reconciliation codes).
- `IpnProcessor` / `ReturnProcessor` (query-backed), `OrderFinalizer`,
  `OrderPlacementAuthorization`, `SuccessSessionPreparer` (rebuilds the 5
  checkout success keys), `OrderRefBuilder`, `QuoteContractFingerprint`,
  `CartManagementPlaceOrderGuard` plugin, `InitializeCommand` /
  `QueryDataBuilder` / `QueryTransactionCommand` / `QueryValidator`.
- Unit tests: 115 tests / 305 assertions green (state machine, IPN/Return
  verification chains, finalizer placement+recovery, initiation, guard
  plugin, fingerprint, signature validators, query request-builder/validator/
  command incl. 7000/7002 non-terminal regressions).

### Removed
- Order-first remnants: `Gateway/Command/NotifyCommand`,
  `Gateway/Response/TransactionHandler` and the redirect-time order creation
  in `Controller/Payment/Redirect`.

## [2.0.1] - 2026-08-27

### Fixed
- Return success no longer depends on the checkout session still holding its
  `Last*` keys (duplicate return / lost cookies used to fail the core
  SuccessValidator and send a paid customer to the cart page). New
  `Service/ReturnProcessor`: on `resultCode = 0` the order is resolved from
  MoMo's own `orderId` parameter (= increment id; session fallback), verified
  to be a MoMo order, and the five success-session keys are rebuilt exactly
  like core `Onepage::saveOrder` (`clearHelperData` first).
- `Controller/Payment/ReturnAction` now delegates to `ReturnProcessor`; a
  non-MoMo or unresolvable order surfaces a customer-safe error + cart
  redirect instead of a hollow success page. Non-zero `resultCode` keeps the
  historical lenient cart behaviour (no lookup, no session writes).

### Added
- Unit tests: `Test/Unit/Service/ReturnProcessorTest.php`,
  `Test/Unit/Service/SessionStub.php`,
  `Test/Unit/Controller/Payment/ReturnActionTest.php` (9 tests — suite now
  18 tests / 36 assertions, all passing).

### Changed
- Controllers `Redirect`, `Notify` and `ReturnAction` migrated to composition
  (`implements HttpGetActionInterface` / `HttpPostActionInterface` +
  `CsrfAwareActionInterface`, injected dependencies instead of
  `extends \Magento\Framework\App\Action\Action` — removed the deprecated
  base-class inheritance flagged by static analysis (PHP6406). Behaviour
  unchanged: same routes, same CSRF semantics, same results.

## [2.0.0] - 2024-07-30

### Added
- Full rebuild of Secomm_MoMo on **MoMo API v2** (redirect + IPN + refund),
  replacing the deprecated 2019 API 1.0 (RSA) implementation.
- `Magento\Payment\Model\Method\Adapter` facade (`MoMoFacade`) with Gateway
  CommandPool — replaces the deprecated `AbstractMethod` god-class.
- HMAC-SHA256 signature helper over the MoMo v2 rawSignature.
- Encrypted backend_model for `access_key` and `secret_key` (admin config).
- Return (GET, lenient) + Notify (IPN, authoritative) separation.
- ACL resource, composer.json, README, strict_types + docblocks on all files.

### Removed
- Entire 2019 codebase: `Model/MoMo.php` god-class (`AbstractMethod`), raw curl
  `HttpClient`, phpseclib v1 RSA encoder, `Controller/Transation/RefundOrder.php`
  scratch code (hardcoded order id + ObjectManager), MoMo API 1.0 endpoints.

### Fixed
- `module.xml`: `<sequence>` now nested inside `<module>` (was a sibling — invalid schema).
- `serect_key` typo → `secret_key` across system.xml / config keys / methods.
- Duplicate `CompositeConfigProvider` registration (now only in `etc/frontend/di.xml`).
- `config.xml`: default `active=0`, added required `is_gateway`/`can_*` flags.
