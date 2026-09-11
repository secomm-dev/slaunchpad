# Changelog

## [1.3.0] - 2026-09-10 (TASK-EDS9T5 corrective round 4 — strict callback payment identity + double-payment guard + sticky conflicts + explicit recovery exhaustion)

### Fixed (review blockers)
- **STRICT CALLBACK PAYMENT IDENTITY (Blocker 1)**: the IPN callback is only
  processed when the envelope carries `type: 1` (Order) — missing, non-numeric
  or `2` (Agreement) is answered "Invalid" (return_code 2) with ZERO lookup
  and ZERO mutation. Signed `amount`/`zp_trans_id` follow a strict trichotomy:
  absent/zero → authoritative `v2/query` fallback; positive integer → direct
  use; MALFORMED (e.g. `"12abc"`) → "Invalid" + critical log, never blind-cast
  to zero. Automatic finalization REQUIRES a proven positive `zp_trans_id`
  (callback or query): verified money with no provable provider identity is
  quarantined (`provider_transaction_unavailable`), no order. A callback
  zp_trans_id conflicting with the authoritative query zp_trans_id quarantines
  (`provider_transaction_conflict`) with BOTH identities preserved verbatim.
  The MAC-authenticated `data.app_id` is additionally compared against the
  configured app id (strict integer grammar) — mismatch is answered "Invalid"
  with zero mutation (configuration/cross-environment errors must not poison
  payment state).
- **DOUBLE-PAYMENT GUARD (Blocker 2)**: under the quote `SELECT ... FOR
  UPDATE` lock and BEFORE any reuse/stale/mint decision, Start inspects the
  quote's attempt history via the bounded
  `PaymentAttemptRepository::getBlockingAttemptByQuoteId()` (LIMIT 1,
  structured flags only — `payment_status IN (paid, finalized)` OR
  `requires_reconciliation = 1`). A blocking attempt refuses the new payment
  with a customer-safe message (quarantine → "under review — do not pay
  twice"; paid → "payment received / being finalized"; finalized → "order
  created") — no second PaymentAttempt and no second provider transaction can
  ever be minted for money-real evidence. Convergence stays owned by
  IPN/Return/PaymentRecovery (OrderFinalizer is never invoked under the quote
  lock). An unexpired INITIATED attempt under the lock is another Start's
  in-flight provider transaction and is refused retry-safe ("being
  initialized") instead of being stale-marked into a second transaction — at
  most ONE provider transaction per payment. Genuinely unpaid terminal
  attempts (FAILED/STALE/EXPIRED, no money-real evidence) may still retry.
- **STRUCTURED + STICKY CONFLICTS (Blocker 3)**: PAID + authoritative failure
  keeps PAID (never regressed) and sets the machine-readable
  `provider_state_conflict` quarantine; authoritative PAID evidence on a
  terminal FAILED/STALE/EXPIRED attempt now sets the structured
  `late_paid_terminal_state` quarantine (provider identity backfilled, status
  NOT broadened). Once set, `requires_reconciliation` + the FIRST
  reconciliation code are STICKY — no callback, Return, recovery run or Start
  ever clears them (verified: no production `setRequiresReconciliation(false)`).
- **EXPLICIT RECOVERY EXHAUSTION (Blocker 4)**: new declarative column
  `recovery_exhausted` (module 1.3.0). The bounded recovery worker's atomic
  claim UPDATE flips the marker exactly on the claim that consumes the last
  permitted query (`IF(recovery_attempts >= max, 1, recovery_exhausted)` —
  MySQL evaluates SET assignments left-to-right), refuses exhausted rows in
  its WHERE, and the selection filter skips them — an exhausted attempt draws
  NO further automatic provider queries. Exhaustion is logged CRITICAL (DB
  marker + log = explicit evidence) and is OPERATIONAL ONLY: it is never
  money-real evidence, never quarantines the attempt, and a later exact valid
  authenticated IPN still resolves the payment normally.

### Tests
- Secomm_ZaloPay unit suite: 157 → **188 tests, 669 assertions, 0 failures**
  (+31 round-4 tests covering the full 35-case corrective matrix; all
  round-1/2/3 tests preserved unchanged).

## [1.2.0] - 2026-09-10 (TASK-EDS9T5 corrective round 3 — official callback contract + strict amount + reconciliation quarantine + recovery worker)

### Fixed (review blockers)
- **Bad/missing browser checksum can no longer prevent payment verification**:
  the Return checksum mismatch is tamper EVIDENCE only (logged, never thrown
  before verification) — the authoritative `v2/query` always runs and solely
  decides the payment state (a forged browser redirect cannot gate a
  server-to-server query).
- **No stale PaymentAttempt saves after lock release**: `OrderFinalizer` no
  longer persists its (possibly stale, pre-rollback) attempt copy. Contract-
  mismatch evidence goes through
  `PaymentAttemptLifecycle::recordContractMismatch(appTransId, reason)`,
  which re-acquires the row lock and mutates the FRESH row — evidence can
  never regress FINALIZED, erase `order_id` or erase
  `provider_transaction_id`.
- **IPN answers the official ZaloPay callback contract**: always HTTP 200
  with `{"return_code": <int>, "return_message": <string>}` — 1 "Success",
  2 "Invalid", 0 "Temporary failure, please retry." (the legacy
  `{errors, messages}` body with HTTP 404/500 was NOT the provider
  protocol). The callback endpoint is POST-only
  (`HttpGetActionInterface` removed).
- **Provider amount is STRICTLY required for automatic order placement**:
  a missing or zero callback amount NEVER means "continue anyway" —
  the authoritative `v2/query` must confirm return_code 1 AND the EXACT
  snapshot amount before any PAID/finalize; query PROCESSING is retryable,
  query FAIL is an authoritative failure, paid-without-amount quarantines.
- **Structured reconciliation quarantine** (new columns
  `requires_reconciliation` + `reconciliation_code`, machine-readable codes
  `amount_mismatch` / `contract_mismatch` / `provider_transaction_conflict`
  / `amount_unavailable` — no free-text parsing). Amount mismatch and
  conflicting `zp_trans_id` (same `app_trans_id`, different authoritative
  id) keep the attempt money-real but quarantined; the finalizer REFUSES
  quarantined attempts for every generic caller, so a later valid callback
  can never silently auto-finalize them. Exact duplicate `zp_trans_id` is
  idempotent.

### Added
- **Lost-callback recovery worker** (official ZaloPay guidance: proactively
  QueryOrder after 15 minutes without a callback):
  `Service/PaymentRecovery` + cron `secomm_zalopay_payment_recovery_cronjob`
  (every 5 minutes). Bounded deterministic selection (non-terminal, unbound,
  not quarantined, past the callback window, `ORDER BY entity_id LIMIT n`),
  atomic conditional-UPDATE claim BEFORE any provider HTTP (a lost race
  skips; no DB lock is ever held across ZaloPay HTTP), then the SAME
  canonical services as IPN/Return (lifecycle → OrderFinalizer — never a
  second order-placement implementation). Config:
  `payment/zalopay/recovery_window` (15), `recovery_batch_size` (25),
  `recovery_max_attempts` (5).

### Changed
- Schema: `secomm_zalopay_payment_attempt` gains `requires_reconciliation`,
  `reconciliation_code`, `recovery_attempts` (setup_version 1.2.0).
- **BREAKING for ZaloPay server-to-server observers**: the IPN endpoint
  response changed from `{errors, messages}` (HTTP 404/500) to the official
  `{return_code, return_message}` (always HTTP 200).

## [1.1.1] - 2026-09-10 (TASK-EDS9T5 review-corrective — authoritative callbacks + concurrency + authorization hardening)

### Fixed (review blockers)
- **Browser Return can never terminally fail a payment**: the redirect is
  processed by authoritative `v2/query` ONLY — browser params (`status`,
  checksum) are lookup key/tamper evidence, never payment proof. The earlier
  race (browser `status != 1` marking the attempt FAILED before the query
  ran, so a later valid IPN could not create the order for a customer who
  actually paid) is eliminated. Real ZaloPay query semantics are used:
  return_code 1 = paid, 3 = processing (non-terminal, no mutation).
- **All callback state mutations are concurrency-safe**: new
  `Service/PaymentAttemptLifecycle` is the CANONICAL payment-state mutation
  service (`recordVerifiedPaid`, `recordVerifiedFailure`,
  `recordAmountMismatch`). Each operation: short DB transaction ->
  `SELECT ... FOR UPDATE` on app_trans_id -> re-evaluate the FRESH persisted
  status -> idempotent legal transition or evidence-only mutation -> save ->
  commit. No DB transaction is ever held across a ZaloPay HTTP call (remote
  verification happens before the lock; the OrderFinalizer runs its own
  separate transaction afterwards). `ReturnProcessor` and `IpnProcessor`
  both delegate here — no divergent callback logic.
- **placeOrder guard enforces the FULL persisted triple**: the guard
  validates the authorization grant against the PERSISTED PaymentAttempt
  row (attempt exists, its `quote_id` is the cart being placed AND its
  `app_trans_id` is the granted one) before consuming. The misleading
  quote-only `consumeForQuote()` API was REMOVED — a quote id, a PAID
  status, browser input or session state alone never authorizes an order.
- **Session ownership extracted**: `Service/SuccessSessionPreparer` (new) is
  the only writer of the customer success-session state, owned by the
  browser Return path. `OrderFinalizer` no longer depends on
  `Magento\Checkout\Model\Session` (pure order-placement boundary) and
  `IpnProcessor` never touches a session. IPN-first then late Return
  recovers the SAME finalized order AND rebuilds the customer session.

### Money-real late callback semantics
- Authoritative PAID evidence arriving on a FAILED/STALE/EXPIRED attempt:
  evidence AND the provider transaction identity (`zp_trans_id`) are
  persisted, the terminal state is NOT broadened, and NO order is created
  automatically.
- A later authoritative failure claim never regresses a recorded PAID/
  FINALIZED state.

### Known cases requiring MANUAL reconciliation (no automated subsystem)
There is deliberately NO reconciliation cron. The following states are
persisted with full evidence (`last_error` + `provider_transaction_id` +
provider status) and must be resolved manually (verify in the ZaloPay
merchant portal, then refund or create the order by hand):
1. **Amount mismatch** — provider confirmed a different amount than the
   snapshot (`last_error`: "…amount mismatch: paid X, snapshot Y"), attempt
   kept money-real, no order.
2. **Contract mismatch** — quote edited after payment / bound order broken
   (refused by OrderFinalizer), attempt kept money-real, no order.
3. **Late PAID evidence on a terminal unpaid attempt** — FAILED/STALE/
   EXPIRED attempt whose authoritative callback arrived late; state kept,
   provider identity preserved, no order.
4. **Conflicting authoritative evidence** — PAID recorded by an earlier
   proof, a later authoritative query reports failure; state kept, conflict
   recorded.

## [1.1.0] - 2026-09-10 (TASK-EDS9T5 — payment-first ONLY)

### Changed
- **PAYMENT-FIRST ONLY (breaking)**: a Magento Sales Order is created ONLY after
  ZaloPay verifies the payment server-side. No verified payment → no Sales Order.
  - `Controller/Payment/Start` builds the gateway redirect from the ACTIVE QUOTE
    (PaymentAttemptManagement: contract snapshot + attempt row + ZaloPay
    create-order API) — never places an order. No order-first fallback.
  - `IpnProcessor` is the canonical finalization path: MAC (key2) + amount
    verification, attempt `PAID`, then `OrderFinalizer` (exactly one order) —
    the customer browser is never required (closing the browser after paying
    still creates the order).
  - `ReturnProcessor`/`ReturnAction` stay UX + authoritative `v2/query`
    verification; IPN/Return races converge to one attempt and one order
    (FINALIZED duplicates recover the bound order + rebuild the success session).
  - Removed the `payment_first` configuration flag/system field — payment-first
    is the only flow (no legacy mode, no fallback, no compatibility switch).
- Renderer `zalopay-wallet.js`: Place Order only saves the payment method and
  redirects to the ZaloPay gateway (PayPal Express pattern); no order
  placement from the browser before the gateway.

### Added
- `Service/OrderPlacementAuthorization`: request-scoped, single-use grant bound
  to the exact (quote_id, attempt entity_id, app_trans_id); opened by
  `OrderFinalizer` around its own placeOrder call (try/finally clear).
- `Plugin/Quote/CartManagementPlaceOrderGuard`: server-side guard on
  `Magento\Quote\Model\QuoteManagement` refusing EVERY generic placeOrder
  (REST, payment-information, GraphQL, SOAP, stale checkout JS, OSC generic
  submit) for ZaloPay quotes without the internal grant — frontend, webapi_rest,
  graphql and webapi_soap. Non-ZaloPay quotes and admin order creation
  (`QuoteManagement::submit()`) are unaffected.

### Removed
- Order-first verification chain (replaced by IPN-first finalization):
  `Gateway/Command/{CompleteCommand, CompleteUpdateDetailsCommand, IpnCommand,
  IpnUpdateDetailsCommand, UpdateDetailsCommand, UpdateOrderCommand}`,
  `Gateway/Validator/{ReturnValidator, CompleteValidator}`,
  `Gateway/Response/{TransactionReturnHandler, TransactionCompleteHandler}`
  and their di.xml command-pool wiring (`ipn`, `complete`).
- `Gateway/Helper/TransactionReader::readOrderId()/isIpn()` (order-first helpers).

### Fixed
- Paid-but-no-order gap: a successful IPN no longer defers order creation to a
  browser return that may never come.
- Unpaid ZaloPay orders are impossible: PAID verification status alone does not
  authorize placement; only the internal single-use grant does.

### Security
- ZaloPay order creation is locked to the verified payment attempt contract:
  amount locked against the persisted VND snapshot, quote contract fingerprint
  re-checked before automatic placement; deterministic mismatches keep the
  attempt money-real (PAID) with last_error evidence and are acknowledged to
  stop infinite provider retries (manual reconciliation).

## [Unreleased] - 2024-07-30

### Added
- Mageplaza OSC layout adapter: onestepcheckout_index_index.xml reuses the existing
  zalopay.js renderer (compliant with .spec §15 — billing-step declares uiComponent)
- Plugin-based architecture to replace global preferences:
  - TotalMinMaxPlugin - ZaloPay currency conversion in min/max order validation
  - CreditmemoServicePlugin - Refund-specific processing
  - SuccessValidatorPlugin - 15-minute grace period for success page validation
  - CreditmemoPlugin - PROCESSING state (value 4) for credit memo states
- Return/IPN validation separation:
  - ReturnValidator - GET params callback validation
  - CompleteValidator - POST JSON IPN validation
  - IpnCommand - IPN command handler
  - CompleteUpdateDetailsCommand - Return command handler
  - IpnUpdateDetailsCommand - IPN update handler

### Changed
- Controllers `Start`, `Ipn` and `ReturnAction` migrated to composition
  (`implements HttpGetActionInterface` / `HttpPostActionInterface` +
  `CsrfAwareActionInterface`, injected dependencies instead of
  `extends \Magento\Framework\App\Action\Action` — removed the deprecated
  base-class inheritance flagged by static analysis (PHP6406). Behaviour
  unchanged: same routes, same CSRF semantics, legacy fall-through of
  `Start::executeLegacy()` (null when no order) and `Ipn` non-POST `null`
  result preserved.
- PHP 8.1-8.4 support via composer.json constraint update
- Migrated HTTP client from ZendClient to LaminasClient in Gateway/Http/Client/Zend.php
- Updated Gateway/Helper/Authorization.php:
  - Added EncryptorInterface injection for key1/key2 decryption
  - Implemented getKey1() and getKey2() with automatic decryption
- Removed deprecated ObjectManager::get() usage in Controller/Payment/Start.php
- Cleaned up unused constructor parameters in Controller/Payment/Ipn.php
- Removed dead code: writeLog() method from Ipn.php
- Fixed RefundCommand.php:
  - Replaced deprecated Creditmemo::STATE_PROCESSING with CreditmemoPlugin::STATE_PROCESSING
  - Updated namespace import for new plugin class
- Removed global preferences (6 total):
  - Magento/Sales/Model/Service/CreditmemoService → plugin
  - Magento/Sales/Model/Order/Creditmemo → plugin
  - Magento/Payment/Model/Checks/TotalMinMax → plugin
  - Magento/Checkout/Model/Session/SuccessValidator → plugin
  - Secomm/ZaloPay/Api/Data/RefundInfoInterface → removed (unused)
  - Secomm/ZaloPay/Api/Data/RefundTransactionInterface → removed (unused)

### Fixed
- Return flow crashed with "Undefined array key trans_data" in TransactionCompleteHandler
  because the Return redirect callback (GET: amount, appid, apptransid, bankcode,
  checksum, status...) carries NO trans_data and no zp_trans_id — only the IPN
  payload does. Split the handler: new TransactionReturnHandler (null-safe, persists
  apptransid as additional info, does NOT register a transaction since Return is
  non-authoritative) wired into CompleteUpdateDetailsCommand; TransactionCompleteHandler
  (registers zp_trans_id) kept for IpnUpdateDetailsCommand. Captured via added
  ReturnAction error logging.
- Ipn controller: declared missing $logger property (dynamic property deprecated in PHP 8.3).
- ReturnValidator (R9) was copy of CompleteValidator and crashed on Return flow:
  - TypeError: validateTotalAmount declared `array|string` but received float —
    fixed to `float`.
  - Inherited parent::validateTransactionId() checks `trans_data[zp_trans_id]`
    which only the IPN payload has — overrode to verify the app transaction id
    (apptransid) that the Return redirect actually carries.
  - Removed the incorrect MAC validation (Return uses different field names
    than the request, and Return is non-authoritative — IPN validates MAC).
- HTTP client migration completeness: replaced Zend-only API calls removed in
  LaminasClient — setConfig() → setOptions() and request() → send() (matches
  core Magento\Payment\Gateway\Http\Client\Zend on M2.4.8). Without this the
  GetPayUrl/Refund/RefundQuery requests crashed at runtime.
- Restored required <model>ZaloPayFacade</model> and <is_gateway>1</is_gateway>
  in config.xml — Adapter::isAvailable() requires is_gateway or the method never
  appears in checkout (per .spec payment-gateway.md §10)
- TotalMinMaxPlugin no longer throws on missing USD→VND currency rate: skips
  conversion when min/max order totals are empty and falls back gracefully so it
  does not hide the method from checkout
- Regenerated db_schema_whitelist.json to match db_schema.xml
- Added declare(strict_types=1) to all new PHP files per .spec/constitution.md §1
- PHP 8.3 compatibility verified via setup:di:compile

### Removed
- Duplicate Secomm\ZaloPay\Model\Ui\ConfigProvider + Gateway\Config\Config — the
  module already ships ZaloPayConfigProvider (registered in etc/frontend/di.xml)
- Duplicate CompositeConfigProvider registration from etc/di.xml (global) —
  per .spec §12 it must live only in etc/frontend/di.xml

### Security
- Removed hardcoded sandbox credentials from default configuration
- Implemented automatic key1/key2 decryption for encrypted backend_model

## [Unreleased] - Previous

### Fixed
- Fixed a bug that prevented redirection to Zalopay.
- Resolved an issue where refunds were not being processed correctly.
- Addressed a bug that sometimes caused the Zalo payment method not to be displayed.
- Change UX when an order is successfully placed using Zalopay on a mobile device

### Added
- Added additional status "processing" for better order tracking.
- Implemented a cron job to periodically check the refund status.
- Added configuration description for Zalo Pay.
- Introduced additional forms for the Zalo Pay payment method.
- Delete refund items that have been processed
- Validate Minimum order total and maximum order total
- Translate the module into English and Vietnamese.
- Handle errors code from Zalopay

### Improved
- Updated the IPN (Instant Payment Notification) functionality for better reliability.

### Updated
- Upgraded Zalopay version from v1 to v2.
- Restructured the API to enhance performance and security.
- Adjusted API parameters to align with the new Zalopay structure.
- Updated codebase to PHP version 8.1 for compatibility and optimization.

### Testing
- Conducted unit tests for controllers to ensure robust functionality.
