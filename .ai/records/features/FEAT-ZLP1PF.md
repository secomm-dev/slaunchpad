---
id: FEAT-ZLP1PF
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: ZaloPay payment-first initiation — Phase 1 (quote → provider transaction → verified idempotent order)
mode: A                      # audited PayPal-core pattern, module-level implementation
specification_level: FULL
spec_status: VALID
specification_ref: ../../audits/ZALOPAY-PAYMENT-FIRST-PAYPAL-CORE-AUDIT-20260826.md
risk: high                   # payment flow — TL-reviewed architecture, rollout-flagged (default OFF)
status: done                 # Phase 1 scope §1–6 implemented; Phase 2 items listed below
created: 2026-08-26
updated: 2026-08-26
ticket_ref: null
decisions: []
decision_assessment: material
components:
  - CMP-ZALOPAY
source_areas:
  - app/code/Secomm/ZaloPay/Model/PaymentAttempt.php
  - app/code/Secomm/ZaloPay/Model/PaymentAttemptManagement.php
  - app/code/Secomm/ZaloPay/Model/QuoteContractFingerprint.php
  - app/code/Secomm/ZaloPay/Service/ReturnProcessor.php
  - app/code/Secomm/ZaloPay/Service/IpnProcessor.php
  - app/code/Secomm/ZaloPay/Service/OrderFinalizer.php
  - app/code/Secomm/ZaloPay/Controller/Payment/Start.php
  - app/code/Secomm/ZaloPay/etc/db_schema.xml
---

# FEAT-ZLP1PF — ZaloPay payment-first initiation, Phase 1

Implementation record for the audited payment-first architecture
(`.ai/audits/ZALOPAY-PAYMENT-FIRST-PAYPAL-CORE-AUDIT-20260826.md`, commit
dc9a48ac). The audit file is historical and untouched; THIS file records what
was actually built.

## 1. Flow as built (audited target, PayPal Express pattern)

```
Active Quote (zalopay set via set-payment-information; NO placeOrder)
  └─ Start /zalopay/payment/start  [payment/zalopay/payment_first = 1]
       └─ PaymentAttemptManagement::initiate()
            ├─ guards (active, items, method) → collectTotals
            ├─ snapshot: amount_vnd = Rate::getVndAmountByCurrency(quote_currency, grand_total)  ← locked
            ├─ TX-A: quote-row FOR UPDATE → reuse ACTIVE+matching? → return (no provider call)
            │        else markStale old → reserveOrderId+save (if empty)
            │        → INSERT attempt INITIATED (app_trans_id UNIQUE, persisted BEFORE provider call)
            ├─ HTTP v2/create (get_pay_url)            ← OUTSIDE any DB transaction
            └─ markActive(pay_url) → save
  └─ redirect pay_url
  └─ ZaloPay → IPN (server-to-server, key2 MAC over raw `data`)
       └─ IpnProcessor: attempt-first lookup → ACTIVE→PAID (zp_trans_id), order_id stays NULL
         Phase 1 NEVER places orders from IPN. Duplicate on FINALIZED ⇒ 200 no-change.
  └─ ZaloPay → Return (browser redirect, NOT payment proof)
       └─ ReturnProcessor: FINALIZED?⇒success → checksum(key2, best-effort) → status≠1⇒FAILED
         → v2/query (authoritative) → rc=3 keep ACTIVE / rc≠1 FAILED
         → amount==PERSISTED snapshot? mismatch ⇒ PAID + last_error + throw (never auto-place)
         → markPaid (explicit) → OrderFinalizer
            TX-B: attempt-row FOR UPDATE (app_trans_id) → FINALIZED?⇒return bound order
                  → CartManagement::placeOrder(quote_id)  ← THE ONLY order creation
                    NoSuchEntity (quote already submitted) ⇒ recover by reserved_order_id
                  → markFinalized(order_id) → capture PENDING_PAYMENT (zp_trans_id)
            → session Last* mirrors (core Onepage::saveOrder pattern) → success page
```

- **sales_order exists before redirect: NO.** Nothing in Start/initiate touches
  CartManagement; the ONLY `placeOrder` call site is `OrderFinalizer`, reached
  after v2/query verification.
- **MSI reservation before redirect: NO.** Reservations are written by the
  core MSI plugin on `OrderManagementInterface::place`, which only runs at
  `placeOrder` (T4). No custom reservation code exists in the module.
- **Inventory timeline:** T0 Start → quote ACTIVE, no order, no reservation;
  T1 paying at ZaloPay → still nothing; T2 IPN → attempt PAID only;
  T3 Return+verified → placeOrder → order + reservation + capture;
  T4 duplicate Return/IPN → FINALIZED short-circuit, no second order/reservation.

## 2. Idempotency (data-level, not PHP flags)

| Guarantee | Mechanism |
|---|---|
| one provider transaction = one attempt row | `UNIQUE(app_trans_id)` on `secomm_zalopay_payment_attempt`; INITIATED row persisted BEFORE the HTTP call |
| one attempt = one Magento order | `UNIQUE(order_id)` + terminal FINALIZED + `SELECT … FOR UPDATE` on attempt row inside OrderFinalizer TX-B |
| refresh/retry Start creates no second provider transaction | reuse ACTIVE attempt when `isReusable()` (pay_url + TTL) AND snapshot amount matches; mismatch → old attempt markStale (explicit), new attempt minted |
| quote submitted twice | CartManagement uses ACTIVE quote → second submit throws NoSuchEntity → recovered by `reserved_order_id` lookup (no duplicate order) |
| HTTP never under lock | v2/create and v2/query run outside all DB transactions |

State machine (`Model/PaymentAttempt::TRANSITIONS`): initiated→active|failed|stale;
active→paid|failed|stale|expired; paid→finalized; terminals have no exits.
Every transition is explicit — illegal moves throw
`Illegal ZaloPay payment attempt transition "%1" -> "%2"`. A SUCCESS
transaction is never reused as a new attempt (`isReusable()` requires ACTIVE).

## 3. Amount locking (case 7/8)

Exact VND integer snapshot persisted at initiation
(`Rate::getVndAmountByCurrency(quote_currency, grand_total)` once, never
re-run). Return compares v2/query `amount`; IPN compares trans_data `amount`
— both against the PERSISTED value, no FX recompute. Mismatch ⇒ transition
PAID (money is real) + `last_error` "Amount mismatch: paid X, snapshot Y." +
critical log + customer-facing failure; NO automatic order placement.
Changed quote total on re-Start ⇒ old attempt STALE + new attempt with new
snapshot (retry_count+1) — never silent re-validation.

## 4. Rollout & compatibility

- `payment/zalopay/payment_first` (default **0** = legacy order-first; admin
  config under Stores > Configuration > Sales > Payment Methods > ZaloPay).
- `payment/zalopay/attempt_ttl` minutes (default 15, mirrors ZaloPay's own
  unpaid-order expiry).
- Frontend (`zalopay-wallet.js`): payment-first → `setPaymentMethodAction` +
  `redirectOnSuccessAction` (PayPal Express pattern), NO `placeOrder()` before
  redirect. Legacy flag off → unchanged `placeOrder()` path.
- **Mageplaza OSC (case 11/12):** OSC's own Place Order button places the
  order order-first; `Start::executePaymentFirst` falls back to the legacy
  branch whenever `PaymentAttemptManagement::isInitiable()` is false
  (session quote inactive ⇒ order already created by OSC). No DOM hacks.
  Phase 1 ships the standard-checkout payment-first path + OSC via fallback
  (documented deviation; see Phase 2).

## 5. New storage

`secomm_zalopay_payment_attempt` (declarative db_schema + whitelist):
quote_id (FK CASCADE→quote), reserved_order_id, app_trans_id (UNIQUE),
provider_transaction_id (zp_trans_id), pay_url, provider_status,
payment_status, amount (unsigned int VND), currency, order_id (UNIQUE,
FK SET NULL→sales_order), last_error, retry_count, store_id, timestamps,
expires_at. **No sensitive payment data, no MAC keys/card data** — references
and lifecycle state only.

## 6. Removed code (evidence-verified, refund subsystem untouched)

| File | Evidence |
|---|---|
| `Observer/RestoreQuoteObserver.php` + `etc/frontend/events.xml` | observed event `restore_quote_after_payment_failed` dispatched NOWHERE in the module or core |
| `Plugin/Model/Checkout/Session/SuccessValidatorPlugin.php` | Reflection-based hack on private Session state; default SuccessValidator passes because OrderFinalizer sets LastQuoteId/LastSuccessQuoteId/LastOrderId/LastRealOrderId/LastOrderStatus (mirrors core `Onepage::saveOrder`) |
| `Plugin/Model/Service/CreditmemoServicePlugin.php` | literal no-op (`$result = $proceed(); return $result;`) |

Corresponding `di.xml` plugin/observer blocks removed. Refund command stack,
RefundCron, creditmemo state plugin — untouched.

## 7. Tests

- Unit (`dev/tests/unit`): **40 tests, 134 assertions, 40/40 PASS** —
  PaymentAttemptTest (state machine, 7), PaymentAttemptManagementTest
  (initiation contract incl. cases 1/2/3/5/6/7/8 + guards + TTL + provider
  rejection, 6), IpnProcessorTest (cases 9/10 + MAC 500 + mismatch + late
  callback, 7), ReturnProcessorTest (verify-order contract incl. explicit
  ACTIVE→PAID before finalize, 10), OrderFinalizerTest (duplicate finalize,
  place+bind+capture, NoSuchEntity recovery, non-payable refusal, 4) +
  pre-existing Authorization/TotalMinMax tests.
  Test-driven bug catches worth noting: reuse path was still calling the
  provider (case 6 failure → fixed with the `isReusable()+amount` short-circuit
  in `initiate()`), and ACTIVE→FINALIZED was unreachable (fixed by making the
  v2/query-confirmed PAID transition explicit in ReturnProcessor).
- Integration: `Test/Integration/Model/PaymentAttemptRepositoryTest.php`
  (CRUD, lookups, UNIQUE(app_trans_id) enforcement, FOR UPDATE hydration,
  active-newest-first). **NOT RUN — environment blocker**, evidence:
  - The module had no wired integration install config; local
    `dev/tests/integration/etc/*.php` were created from `.dist` templates
    using compose `MYSQL_INTEGRATION_*` credentials (gitignored —
    `dev/tests/integration/.gitignore` covers `/etc/*.php`).
  - 4 sandbox-install attempts, 3 distinct environmental failures:
    (a) AMQP validation (`setup:install` validates the connection when
    `--amqp-*` is passed; compose service name mismatch) → amqp keys removed;
    (b)+(c)+(d) `DomainException: The default website isn't defined` in
    `WebsiteRepository->getDefault()` during `installSchema`, stack:
    `Installer->installSchema → ObjectManagerProvider->createCliCommands →
    Session\Config->__construct → App\Config->getValue (store scope) →
    StoreManager → StoreResolver → store_website (empty at schema stage)`.
    Stock Magento does not construct `Session\Config` there — a third-party
    module's di.xml drags a session-coupled type into console-command DI;
    reproducible regardless of `generated/code` state (the shared container
    regenerates ~1058 compiled classes during the install itself, and the
    setup process autoloads them from the main `generated/code` path).
  - Conclusion: sandbox install of THIS project (Hyvä + Mageplaza OSC +
    Magefan GTM suite) into the integration DB is blocked at project level,
    not by ZaloPay code. Remediation options for Phase 2 / CI: candidate
    `--disable-modules` isolation of the offending module(s), or a dedicated
    integration runner container. Unit suite carries the Phase 1 verification
    weight meanwhile.
- PHPCS Magento2: **0 errors** on all new/modified files (warnings only,
  matching pre-existing module style). `setup:di:compile`: success.

## 8. Known Phase 1 limitations (explicit, not silent)

1. **OSC hybrid:** Mageplaza OSC stays order-first via the legacy fallback
   until a dedicated OSC renderer override ships (Phase 2 candidate).
2. **Parallel Start race (two tabs):** quote-row lock serializes the
   decision, but both requests may still mint their own attempt+provider
   transaction when totals differ mid-flight; only the matching one is
   reusable, others go STALE. Bounded, observable, no duplicate orders.
3. **IPN never places orders (by design):** PAID-without-order attempts
   depend on the customer returning. Phase 2 reconciliation cron should
   finalize verified-PAID attempts past a grace window (T2.5).
4. **EXPIRED transition** exists in the state machine but nothing marks it
   yet — TTL currently only affects reuse (`isReusable`). Phase 2 sweeper.

## 9. Delivery

- Branch `dev/development/thanhle`; commit recorded in the final report.
- Flag default OFF ⇒ production behaviour byte-identical until enabled.

## 10. Open for Phase 2

- Reconciliation cron (verified-PAID finalize + EXPIRED sweeper + mismatch
  queue from `last_error`).
- OSC native payment-first renderer (remove fallback dependency).
- Admin grid / CLI for attempts (`secomm_zalopay_payment_attempt`).
- Parallel-Start tightening (per-quote single-flight token) if ops requires.

## 11. TL review fixes (commit 1bd469b2 → NOT APPROVED, 2026-08-26)

The TL review of Phase 1 commit `1bd469b2` raised two lifecycle blockers.
Both fixed below; the historical audit and §1–10 above are untouched
records of the original delivery.

### 11.1 BLOCKER 1 — paid snapshot vs CURRENT quote (fixed)

**Root cause:** the return flow verified `provider amount == attempt
snapshot` but `placeOrder($attempt->getQuoteId())` then ran against the
CURRENT mutable quote (T0 500000 → snapshot → cart edited to 700000 →
customer pays 500000 → order placed at 700000).

**Fix — payment contract fingerprint** (`Model/QuoteContractFingerprint.php`):
- At Start, inside the quote-locked TX section (after `reserveOrderId`),
  the contract state is normalized and hashed (sha-256) into
  `payment_attempt.contract_hash` (varchar 64, nullable — legacy rows stay
  null and never compare equal).
- Inputs: quote_id, reserved_order_id, store_id, quote currency,
  grand_total, base_grand_total, provider VND amount, per-item
  `sku|product_id|qty` over ALL items incl. children (child SKUs carry
  configurable/bundle selections; sorted so cart order is irrelevant),
  shipping method, coupon code, applied rule ids, sha-256 of normalized
  shipping + billing address fields.
- **No sensitive data persisted:** addresses are hashed (the fingerprint
  needs equality, not content), no MAC keys, no secret material — the
  hash is a comparison checksum, not a signature.
- Before automatic finalization, `OrderFinalizer` reloads the CURRENT
  quote and validates: quote exists + payment method is `zalopay` +
  `collectTotals()` + FX-rate amount == attempt amount + fingerprint
  `hash_equals` against the persisted hash. Mismatch ⇒
  `ContractMismatchException` ⇒ TX rolled back, attempt **stays PAID**
  (`order_id` NULL, money is real, never FAILED), reason persisted to
  `last_error` AFTER the rollback (so it survives), critical log,
  customer-safe message in ReturnProcessor → reconciliation/manual/refund
  path (Phase 2 cron / admin).
- Reuse gate tightened: an ACTIVE attempt is reused only when amount AND
  fingerprint both match; otherwise it is STALE-marked and a fresh attempt
  with a fresh hash is minted.
- Inactive/absent quote (safe recovery): order is recovered by
  `reserved_order_id` ONLY when bound to this attempt (increment id +
  quote id + payment method `zalopay`); otherwise the same mismatch path
  keeps the attempt PAID for reconciliation.
- Environment note: this project's PHP 8.3.20 runtime does NOT define
  `JSON_SORT_KEYS` (verified `defined()` false in the container) — key
  ordering is done by recursive `ksort` instead of the json flag.

### 11.2 BLOCKER 2 — duplicate FINALIZED return lost success session (fixed)

**Root cause:** ReturnProcessor short-circuited FINALIZED attempts with an
early `return 'checkout/onepage/success'`, bypassing OrderFinalizer — the
single owner of LastQuoteId / LastSuccessQuoteId / LastOrderId /
LastRealOrderId / LastOrderStatus. A duplicate return with a new/lost
checkout session landed on the success page with an EMPTY session.

**Fix:** the short-circuit is removed. `OrderFinalizer::finalizeOrRecover()`
(legacy `finalize()` kept as alias) is the ONLY code that writes the
success session: FINALIZED attempts load the bound order, validate the
binding (order exists, increment/quote/payment match), rebuild the
session keys idempotently, and return success. Broken binding (order
missing / wrong order) ⇒ `ContractMismatchException` ⇒ customer-safe
error, never a hollow success page. ReturnProcessor just delegates.

### 11.3 Transaction atomicity — re-checked with source evidence

Question: if `captureOrder()` throws, can `{attempt FINALIZED, order
persistence, MSI reservation}` be partially committed? **No — verified:**

- `vendor/magento/framework/DB/Adapter/Pdo/Mysql.php` (begin/commit/
  rollBack, ~lines 371–435): real `BEGIN` only at
  `$_transactionLevel === 0`, real `COMMIT` only at level 1. **No
  savepoints** — nested begin/commit are counters. A nested `rollBack`
  only poisons the unit (`_isRolledBack`); any later outer `commit()`
  throws `ERROR_ROLLBACK_INCOMPLETE`, so the outermost catch's rollBack
  performs the real `ROLLBACK`. Nested Magento transactions are FLATTENED
  into one atomic unit, not independent scopes.
- The `placeOrder` chain opens NO transaction of its own
  (`module-quote` uses `QuoteIdMutex` advisory locks, not `beginTransaction`).
- MSI reservations run via
  `AppendReservationsAfterOrderPlacementPlugin` on
  `OrderManagementInterface::place` (module-inventory-sales di.xml),
  synchronously on the same connection ⇒ inside OrderFinalizer's TX.
- ZaloPay `capture` is `Magento\Payment\Gateway\Command\NullCommand`
  (module di.xml) — local state only, no HTTP inside the TX.

**Decision:** OrderFinalizer's single TX already gives the required
all-or-nothing guarantee; the intermediate `PAID → ORDER_CREATED →
FINALIZED` redesign is unnecessary and was NOT introduced. Capture stays
inside the TX (a capture exception rolls back the order too — attempt
returns to PAID-for-reconciliation, no orphan order).

### 11.4 Review test matrix (all required cases covered)

`Test/Unit/Model/QuoteContractFingerprintTest.php` (10): stable
fingerprint; qty change (same total); item swap (same total); shipping
method change (same total); address change; coupon change; item order
irrelevance; amount change; virtual quote stability; null-hash rejection.

`Test/Unit/Service/OrderFinalizerTest.php` (13, rewritten): B1 — same
quote ⇒ place+bind+capture; total changed ⇒ no order (stays PAID +
last_error); fingerprint mismatch (qty/items/shipping-method at same
total) ⇒ no order; payment method changed ⇒ no order; inactive quote
without matching order ⇒ safe recovery state; inactive/absent quote with
matching bound order ⇒ idempotent recovery; FINALIZED duplicate returns
existing order without placing. B2 — FINALIZED duplicate with existing
session; FINALIZED with empty session rebuilt (all 5 keys); FINALIZED
without bound order refused; FINALIZED bound to wrong order refused.
Plus non-payable refusal and capture-failure rollback (expects real
`rollBack`, no session writes).

`PaymentAttemptManagementTest`: + same-amount-changed-contract not reused
(fresh fingerprint persisted); creation asserts the hash lock.
`ReturnProcessorTest` (11): + FINALIZED duplicate routes through
`finalizeOrRecover` (session rebuild owned by finalizer); + contract
mismatch surfaces the customer-safe message.

**Suite totals after review fixes: 61 tests, 196 assertions, 61/61 PASS**
(§7's 40/40 remains the Phase 1 historical count). PHPCS Magento2
(review gate §9 — warnings on custom code must be handled): a docblock
pass brought EVERY payment-first file (Service/, Model/PaymentAttempt*,
Model/QuoteContractFingerprint, Api/, Exception/, Controller/Payment/,
AppTransIdBuilder, ResourceModel/PaymentAttemptResource, Test/Unit/) to
**0 errors and 0 warnings**. The remaining 222 warnings in the module are
all in pre-existing legacy scaffold (Gateway/, Helper/, Plugin/,
Logger/, Model/Config*, Api/Data/RefundInterface, Test/Integration/) —
recorded here as known module debt per the gate's "record in project
status" rule; touching them is outside this review's scope.
`setup:di:compile`: success (re-run after the PHPCS pass). Integration:
still environment-blocked, unchanged evidence in §7 — NOT claimed as PASS.
