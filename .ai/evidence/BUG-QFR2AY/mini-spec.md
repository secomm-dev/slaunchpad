# BUG-QFR2AY — Isolate MoMo/ZaloPay placeOrder guards from cross-payment DI failures

Requirement source: GitHub issue #20 (`[Checkout][HOTFIX] Isolate MoMo/ZaloPay placeOrder guards from cross-payment DI failures`) + Owner authorization comment (`BASE_SHA e38738a0`, branch `thanhle74/hotfix-cross-payment-placeorder-guard-isolation`). Mode D (production hotfix); Tier-2 area (checkout + payment guard).

## Mini Spec

### Goal
Real ZaloPay checkout (paid, `status=1`) aborted before Sales Order creation with
`Cannot instantiate interface Secomm\MoMo\Api\PaymentAttemptRepositoryInterface`, because both
`Secomm\MoMo` and `Secomm_ZaloPay` register GLOBAL `QuoteManagement::placeOrder()` guard plugins whose
constructors eagerly build target-only heavy dependencies (own `PaymentAttemptRepositoryInterface` +
own payment facade `MoMoFacade`/`ZaloPayFacade`). A construction failure in one module aborts every
other payment method's placement before the guard reaches its non-target no-op path.

### Expected Behavior
- Guard plugin construction requires NO target-only dependency: method discrimination uses an
  ObjectManager-injected scalar `methodCode` (the facade is not needed to compare a string);
  the attempt repository is wired as a Magento Proxy, constructed only on first use.
- Non-MoMo/ZaloPay placement never resolves the other module's attempt repository or facade:
  guard construction succeeds with the repository unavailable, and the non-target path
  (load quote → compare method code → return null) touches neither.
- Target-method semantics unchanged: payment-first policy, persisted-attempt triple validation
  (quote_id + attempt_id + order_ref / app_trans_id), single-use grant consumption, block messages,
  admin/non-target methods unaffected.
- MoMo and ZaloPay get the identical isolation pattern (no asymmetric failure).

### Constraints / Rules
- Do NOT weaken the persisted-attempt triple validation or single-use authorization.
- No provider API change, no ReturnProcessor/classifier change, no refund change, no schema migration.
- Magento-native isolation only (scalar DI argument + generated `\Proxy`, no new dependencies).
- Plugin stays registered globally on `Magento\Quote\Model\QuoteManagement` (all web areas).
- `OrderPlacementAuthorization` stays a shared, eagerly-constructed internal singleton (grant handoff
  guard ↔ OrderFinalizer depends on its process-wide instance; it has zero constructor deps).

### Out of Scope
- ZaloPay/MoMo ReturnProcessor redesign; payment recovery; refund.
- Bitbucket sync/deploy; manual repair of the already-paid ZaloPay transaction (post-hotfix task).

### Acceptance Criteria
1. Cross-payment construction coupling removed: a MoMo-only repository/facade being unresolvable can
   no longer abort a ZaloPay placement (and symmetric).
2. Guard security semantics unchanged for the owning method (existing block/grant tests stay green,
   updated only for the constructor shape).
3. DI compile succeeds and generates `PaymentAttemptRepository\Proxy` for both modules; runtime
   ObjectManager resolves both guards and the QuoteManagement plugin chain with both modules enabled.
4. Coverage: DI-contract tests pin the isolation wiring (scalar methodCode, Proxy repository,
   facade removed); guard unit tests prove non-target placement never touches the attempt repository;
   full Secomm_MoMo + Secomm_ZaloPay PHPUnit green; PHPCS (Magento2, severity ≥ 6) clean on changed
   files.
5. Evidence in `.ai/evidence/BUG-QFR2AY/`; branch pushed non-force to GitHub origin under the issue's
   Owner authorization; issue updated READY_FOR_REVIEW with TIP SHA.

## Approach

Smallest Magento-native isolation, applied symmetrically to both modules:

1. **Guard constructor**: `MethodInterface $method` → `string $methodCode`.
   The only use of the facade was `getCode()` for method discrimination. A scalar DI argument
   (matching the facade's `code` argument in each module's di.xml) removes the entire
   `MoMoFacade`/`ZaloPayFacade` construction edge (Adapter + value handler pool + validator pool +
   command pool + HTTP client chain) from every placeOrder call of every payment method.
2. **Attempt repository → Proxy**: di.xml wires the guard's `attemptRepository` argument as
   `Secomm\MoMo\Model\PaymentAttemptRepository\Proxy` / `Secomm\ZaloPay\Model\PaymentAttemptRepository\Proxy`.
   The generated proxy defers the real repository (and its ResourceConnection graph) to first use —
   reached only after the quote is confirmed as the owning method. Guard stores the proxy behind the
   unchanged `PaymentAttemptRepositoryInterface` type; no PHP semantic change.
3. **Guard is otherwise untouched** — no-op path, block paths, grant peek/consume, logging.

Validation recipe (established in TASK-3083BD / TASK-NCDCWR): throwaway copy `/tmp/m2r` mounted at
`/var/www/html` in container `m2r-php` (markoshust/magento-php:8.3-fpm, PHP 8.3.20);
`php -l` → PHPUnit (`dev/tests/unit/phpunit-secomm.xml`, both module suites) → PHPCS Magento2 ≥6 on
changed files → `setup:di:compile` (wiped generated/code, verifies proxy generation) → runtime
ObjectManager resolution check of both guards + `QuoteManagement` construction.
