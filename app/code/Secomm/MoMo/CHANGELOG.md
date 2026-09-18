# Changelog

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
