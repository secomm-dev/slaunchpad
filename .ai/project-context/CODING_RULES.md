# Secomm Launchpad — Coding Rules

> **This file is GENERATED.** Do not edit by hand in the client project — the source of truth lives in the toolkit's `coding-rules/` directory. To change a rule, update the toolkit source and regenerate.

## Source

`project-context/CODING_RULES.md` is produced by merging two toolkit source files:

1. `coding-rules/shared-rules.md` — cross-stack rules (always included).
2. `coding-rules/magento-rules.md` — the detected stack's rules (covers Luma, Hyvä, Headless).

A project-specific "Hyvä / Tailwind v4" overlay and the blueprint §15 `coding_rules_override` items are appended at generation time. The top 15–20 most-enforced rules are summarized in `AGENTS.md` Section 7.2. This file is the full reference.

## Generator instructions

At generation time, the generator:

1. Concatenates `shared-rules.md` then `{stack}-rules.md` (shared first; stack rules add to, never repeat, shared rules).
2. Rewrites the header to the client project name and stamps the generation date.
3. Writes the result here as `project-context/CODING_RULES.md`.
4. Appends the Hyvä/Tailwind v4 project overlay + the blueprint §15 `coding_rules_override` verbatim.
5. Extracts the `[BLOCK]` and `[WARN]` items into the `AGENTS.md` Section 7.2 summary.

```yaml
merge:
  base: coding-rules/shared-rules.md
  overlay: "coding-rules/magento-rules.md"
  output: project-context/CODING_RULES.md
  summary_target: "AGENTS.md Section 7.2"
```

## Placeholders

- generation_date: 2026-07-14
- stack_name: magento-hyva
- project_name: Secomm Launchpad

---

## Shared Rules

<!-- Source: coding-rules/shared-rules.md — verbatim -->

> **Audience:** AI agents and developers writing code in ANY Secomm eCommerce project (Magento, Shopify, Headless/Next.js, Laravel).
> **What this is:** Enforceable engineering rules — specific, measurable, checkable by AI pre-review. Not theory.
> **Relationship to other docs:** This file + one `coding-rules/{stack}-rules.md` are merged into `project-context/CODING_RULES.md` at generation time. The top 15–20 rules are summarized in `AGENTS.md` Section 7.2. Shared rules ALWAYS apply; stack files add stack-specific rules on top and MUST NOT repeat what is here.

---

### How AI Pre-Review Checks These Rules

Every rule below is phrased so an automated check can pass/fail it:

- Rules with a **number** (e.g. "max 30 lines", "max 3 params") → mechanical check.
- Rules with **DO / DON'T** → pattern match against the code.
- Rules marked **[BLOCK]** → pre-review must flag and block the PR if violated.
- Rules marked **[WARN]** → pre-review logs a warning for TL review.

When a rule cannot be mechanically verified, it is not included here. "Write clean code" is not a rule.

---

### 1. SOLID — Applied to eCommerce

Not textbook definitions. How the principles are enforced in this codebase.

#### 1.1 Single Responsibility

- One class / module = one reason to change.
- **[BLOCK]** Controllers handle only: validate input → delegate to a service/action → return a response. No business logic in controllers.
- One business operation per service/action class.

| DO | DON'T |
|---|---|
| `CalculateShippingRateService` | `OrderService` that creates, charges, ships, and notifies |
| `ApplyDiscountAction` | `OrderHelper` with `create()`, `refund()`, `export()` |
| `ValidateCartItemsAction` | A repository that also sends emails |

#### 1.2 Open/Closed

- Extend behavior by composition, not by editing existing code.
- Adding a new payment method / shipping carrier / discount type → add a new class implementing the existing interface. **[BLOCK]** Never add another `elseif`/`case` to an existing handler.
  - Magento → plugins / observers / new carrier model, not class rewrites.
  - Shopify → app extensions / Functions, not edits to a merchant theme's core section.
  - Laravel → new interface implementation bound in the container, not a branch in an existing service.
  - Next.js → new strategy component behind the same prop interface, not a prop-driven `switch`.

#### 1.3 Liskov Substitution

- A subtype must be substitutable for its base type.
- **[BLOCK]** If you override a method, keep the same contract: parameter types, return type, and thrown exceptions. Do not override a method to `throw new \LogicException('not supported')` — implement a different interface instead.

#### 1.4 Interface Segregation

- Small, focused interfaces. Consumers depend only on what they call.
- DO: `CanCalculateTax`, `CanApplyDiscount`, `CanValidateStock`.
- DON'T: `OrderProcessorInterface` with 20 methods that every implementer must stub.

#### 1.5 Dependency Inversion

- Depend on abstractions (interfaces), not concrete classes. Inject; do not `new` inside business logic.
- **[BLOCK]** Magento → constructor DI only; `ObjectManager` is forbidden outside integration tests, fixtures, and factories.
- Laravel → resolve through the service container / constructor injection; never `app()->make()` inside a controller or action body.
- Next.js → call a service-layer function, never `fetch()` directly inside a component.

---

### 2. Function & Method Standards

#### 2.1 Size

- **[WARN]** Max **30 lines** per function (excluding comments and blank lines). If longer → extract.
- Each function does ONE thing.

#### 2.2 Naming

- Verb + noun for actions: `calculateShippingRate()`, `validateCartItems()`, `applyDiscountRule()`.
- Boolean accessors use `is/has/can/should`: `isEligibleForDiscount()`, `hasActiveSubscription()`, `canInvoice()`.
- Event handlers use `on` + event: `onOrderPlaced()`, `onPaymentReceived()`.
- **[WARN]** No abbreviations in public APIs: `getCustomerGroup()`, not `getCustGrp()`.

#### 2.3 Parameters

- **[WARN]** Max **3 parameters**. If more → use a DTO / parameter object / typed config object.
- **[BLOCK]** No boolean flags that change a function's behavior — split into two functions.

| DO | DON'T |
|---|---|
| `sendOrderConfirmation($order)` | `sendEmail($type, $includeTracking = false, $formatHtml = true)` |
| `sendShippingNotification($shipment)` | one `notify()` toggling four behaviors |

#### 2.4 Return Values

- **[WARN]** One return type per function — no mixed/union returns unless explicitly modeled (e.g. a Result object).
- Return early for guard clauses to reduce nesting.

```
// DO — early returns, flat
if (!$order) { return null; }
if (!$order->canInvoice()) { return null; }
return $this->invoice($order);

// DON'T — deep nesting
if ($order) {
    if ($order->canInvoice()) {
        // deeply nested logic
    }
}
```

---

### 3. Commenting & Documentation

#### 3.1 Docblocks — REQUIRED for all public functions

```
/**
 * Calculate the shipping rate for a cart based on weight and destination.
 *
 * Uses the carrier rate table from vendor_shipping_rate. Falls back to the
 * configured flat rate when no matching rule is found (CR-042).
 *
 * @param CartInterface $cart
 * @param AddressInterface $destination
 * @return float Shipping rate in base currency
 * @throws NoShippingRateException If no carrier serves the destination
 */
```

Requirements:
- First line: **WHAT** the function does (not HOW).
- Additional lines: business context, edge cases, fallback behavior — the *why*.
- `@param` with type and a meaningful name; `@return` with type and unit; `@throws` if it can throw.

#### 3.2 Inline Comments

- Explain **WHY**, not WHAT. The code already shows what.
- DO: `// B2B customers get shipping after discount (CR-042)`
- DO: `// Minimum order $500 for wholesale — skip discount below threshold`
- DON'T: `// loop through items`
- DON'T: `// check if amount > 500`

#### 3.3 TODO / FIXME Format

```
// TODO(SHOP-123): Migrate to Checkout Extensibility after Plus upgrade — @owner
// FIXME(BUG-456): Race condition on concurrent cart updates — needs queue
// HACK: Workaround for Magento core issue — remove after upgrade to 2.4.9
```

Every TODO/FIXME carries a ticket id and an owner. **[WARN]** Bare `// TODO:` with no ticket is flagged.

#### 3.4 No-Comment Zones

- **[BLOCK]** Do not commit commented-out code — delete it (git keeps history).
- Do not write changelogs in comments — use commit messages.
- Do not restate obvious code.

---

### 4. Error Handling

#### 4.1 General

- **[BLOCK]** Never swallow exceptions silently. `catch (e) { /* nothing */ }` is forbidden.
- **[WARN]** Catch the **specific** exception type, not a catch-all. `catch (NoSuchEntityException $e)`, not `catch (\Exception $e)`.
- Either log-and-rethrow OR handle-and-continue — never log, continue, and hide the failure.
- Include context in messages: `Failed to sync order #12345 to ERP: connection timeout`, not `sync failed`.

#### 4.2 eCommerce-Specific

- **Payment errors [BLOCK]:** always log order id, amount, currency, method, gateway transaction id, gateway response code. NEVER log card number, CVV, or full PAN.
- **Inventory errors [BLOCK]:** fail explicitly. Never silently allow oversell.
- **Price calculation [BLOCK]:** throw on any ambiguity. A wrong price is worse than an error page.
- **Order state [BLOCK]:** validate transitions. Never allow invalid jumps (e.g. `pending` → `complete` skipping `processing`).

#### 4.3 API Error Response Format

Consistent shape across every API:

```json
{
  "error": true,
  "code": "SHIPPING_RATE_NOT_FOUND",
  "message": "No shipping rate available for destination",
  "details": { "country": "VN", "weight_kg": 45.5 }
}
```

- `code` is machine-readable, `UPPER_SNAKE_CASE`.
- `message` is human-readable.
- `details` carries context for debugging.
- **[BLOCK]** Never expose stack traces, internal file paths, SQL, or credentials in an API response.

---

### 5. Security Rules (eCommerce-Focused)

#### 5.1 Input Validation

- **[BLOCK]** Validate ALL input: query params, request body, headers, file uploads. Whitelist allowed values; do not blacklist.
- Validation on the server. Client-side validation is UX, not security.

#### 5.2 Output & Storage

- **[BLOCK]** Escape all dynamic HTML output (framework escaping: Magento `escapeHtml`, Laravel Blade `{{ }}`, React JSX auto-escaping, Liquid auto-escape).
- **[BLOCK]** Parameterized queries only. NEVER string-concatenate SQL.
- **[BLOCK]** No internal paths, stack traces, or credentials in responses or logs.

#### 5.3 Authentication & Authorization

- Verify permissions on every request, not just by hiding UI. **[BLOCK]** UI hiding is not authorization.
- Use the framework's auth mechanism — do not roll custom auth.
- Enforce session/token expiry. Re-authenticate admin for sensitive operations (refund, delete, price override).

#### 5.4 eCommerce Trust Boundaries

- **Price [BLOCK]:** NEVER trust a client-supplied price. Always recalculate server-side.
- **Stock [BLOCK]:** validate stock server-side before order confirmation.
- **Coupon [BLOCK]:** validate server-side — check usage limits, expiry, customer eligibility, cart conditions.
- **PII:** encrypt customer data at rest; mask in logs (`j***@example.com`).
- **Payment:** PCI scope — never log or store raw card data. Use the gateway token.
- **Admin actions:** audit-log who changed what and when.

---

### 6. Unit Test Standards

#### 6.1 Structure — AAA

```
// Arrange
$cart = $this->createCartWithItems([...]);
$customer = $this->createWholesaleCustomer();

// Act
$rate = $this->shippingCalculator->calculate($cart, $customer);

// Assert
$this->assertSame(25.50, $rate);
```

#### 6.2 Naming

The test method name tells the story. Pattern: `test_{action}_{expected_result}_{condition}`.

```
test_calculate_shipping_returns_zero_for_free_shipping_eligible_cart
test_apply_discount_throws_exception_when_coupon_expired
test_wholesale_customer_sees_tier_pricing_for_bulk_orders
```

#### 6.3 What to Test

- Happy path (expected input → expected output).
- Edge cases: boundary values, empty collections, zero amounts.
- Error cases: invalid input, missing dependencies, timeouts.
- Business rules: pricing tiers, discount conditions, shipping rules.
- State transitions: order status changes, cart updates.

#### 6.4 What NOT to Test

- Framework internals (don't test that DI works).
- Getters/setters with no logic.
- Third-party library internals.
- Raw UI rendering (unless it is a component test).

#### 6.5 Test Data

- Use factories/builders, not hardcoded arrays.
- Each test creates its own data — no shared mutable state.
- Meaningful values: `$price = 99.99`, not `$price = 1`.
- Cover edge values: `0`, negative, max int, empty string, `null`, unicode.

#### 6.6 Mocking

- Mock external dependencies (APIs, databases, filesystem, clock).
- Do not mock the thing under test.
- Prefer fakes over mocks for complex dependencies.
- Verify mock interactions only when the interaction IS the behavior under test.

---

### 7. Performance Patterns

#### 7.1 Database

- **[BLOCK]** No N+1 queries. Use eager loading / joins / batch queries.
- Index columns used in `WHERE`, `ORDER BY`, `JOIN`.
- Avoid `SELECT *` — select only the columns you use.
- Paginate large result sets — never load all rows.

#### 7.2 Caching

- Cache expensive computations and stable API responses.
- TTL reflects the freshness/performance tradeoff.
- **[WARN]** Cache keys MUST include every parameter that affects the result.
- Invalidate on data change — do not rely on TTL alone for critical data (price, stock).

#### 7.3 Code

- Hoist computation out of loops when it can be done once.
- Use early returns to skip unnecessary work.
- Lazy-load heavy dependencies.
- Batch operations: bulk insert/update, not row-by-row.

#### 7.4 eCommerce-Specific

- **Product listing:** cap default page size; optimize image loading (next image, lazy, responsive `srcset`).
- **Cart:** minimize recalculation — recompute only what changed.
- **Checkout:** keep external API calls off the critical path; go async where possible.
- **Search:** use a dedicated engine (OpenSearch / Algolia), not database `LIKE`.

---

### 8. Git & Code Organization

#### 8.1 Commit Messages

Format: `type(scope): description`. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `perf`.

```
feat(shipping): add weight-based rate calculation for B2B customers
fix(checkout): correct tax calculation for multi-store orders
refactor(catalog): extract price calculation to a dedicated service
test(order): add tests for the partial refund flow
```

#### 8.2 Branch Naming

```
feature/TICKET-123-add-wholesale-pricing
bugfix/TICKET-456-fix-shipping-rate
hotfix/TICKET-789-checkout-crash
```

#### 8.3 One PR = One Concern

- **[WARN]** Do not mix a feature with unrelated refactoring.
- Do not fix a bug and add a feature in the same PR.
- Found an unrelated issue while coding? Create a separate ticket.

---

### 9. Pre-Review Quick Checklist

Run this before requesting TL review. Every item is pass/fail.

- [ ] No controller contains business logic (SRP).
- [ ] No function exceeds 30 lines; no function takes >3 params.
- [ ] No boolean flag parameters.
- [ ] No swallowed exceptions; no catch-all without rethrow.
- [ ] No commented-out code; every TODO/FIXME has a ticket.
- [ ] No client-trusted price/stock/coupon — all revalidated server-side.
- [ ] No raw card data logged; PII masked.
- [ ] No N+1 queries; large reads paginated.
- [ ] No `SELECT *`; no string-concatenated SQL.
- [ ] Public functions have docblocks (WHAT + why context).
- [ ] Tests follow AAA; edge + error cases covered, not just happy path.
- [ ] Commit messages follow `type(scope): description`.

---

## Magento Rules

<!-- Source: coding-rules/magento-rules.md — verbatim -->

> **Scope.** Shared rules (`shared-rules.md`) ALWAYS apply to every stack (SOLID, function standards, comments, error handling, security, unit tests, performance, git, review-code checklist). This file adds **Magento-only** rules on top. Where a shared rule has a Magento-specific concrete form, the Magento form is shown briefly with a pointer — the principle itself is not re-explained.
>
> **Enforcement.** Each rule is marked **[BLOCK]** (pre-review must block merge) or **[WARN]** (log for TL, retro review). Every rule is mechanically checkable.
>
> **Merge target.** When this toolkit is generated into a client project, this file merges into `project-context/CODING_RULES.md`, and the summary lives at `AGENTS.md` Section 7.2. AI agents read both while coding.
>
> **PHP version.** Targets PHP 8.1+ (Magento 2.4.4+). Use `readonly` properties, typed properties, constructor property promotion, named arguments, enum where applicable.

---

### 1. Module Structure

#### 1.1 Required files and folders

Every module MUST contain at minimum:

```
app/code/Vendor/Module/
├── composer.json
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml              (can be empty, but must exist if any DI customization)
│   ├── events.xml          (per-area, only if observers exist)
│   ├── webapi.xml          (only if REST endpoints exposed)
│   ├── db_schema.xml       (only if tables owned)
│   └── db_schema_whitelist.json  (MANDATORY companion of db_schema.xml)
├── Api/                    (service contracts — interfaces only)
├── Api/Data/               (data interfaces)
├── Model/                  (concrete implementations)
├── etc/frontend/  etc/adminhtml/  etc/webapi_rest/  etc/graphql/
├── view/frontend/  view/adminhtml/  view/base/
├── Controller/             (thin controllers)
├── Plugin/                 (interceptors)
├── Observer/               (event subscribers)
├── ViewModel/              (Hyvä / UI-component arguments)
└── Test/Unit/  Test/Integration/
```

**[BLOCK]** Missing `registration.php` or `module.xml`.
**[BLOCK]** `db_schema.xml` present without a matching `db_schema_whitelist.json`.
**[WARN]** Empty `di.xml`/`events.xml` left in place (delete unused).

#### 1.2 `registration.php`

Standard form. Component type `MODULE`.

```php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_SpecialPricing',
    __DIR__
);
```

**[BLOCK]** Module name does not match `Vendor_Module` PascalCase.
**[BLOCK]** Hardcoded absolute path instead of `__DIR__`.

#### 1.3 `module.xml` — sequence

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_SpecialPricing" setup_version="1.0.0">
        <sequence>
            <module name="Magento_Catalog"/>
            <module name="Magento_CatalogRule"/>
        </sequence>
    </module>
</config>
```

**[BLOCK]** Sequence lists a module that is not a real compile-time dependency (e.g. listing `Magento_Checkout` just to "be safe"). Sequence only when you extend a config, override XML, or depend on table DDL order.
**[WARN]** Missing sequence when overriding `catalog_product_prices.xml` or similar layout/config merging.

#### 1.4 `composer.json`

Must declare `magento2-module` type, autoload PSR-4 mapping to `app/code`, and runtime dependencies on Magento modules.

```json
{
  "name": "vendor/module-special-pricing",
  "description": "Customer-group tier pricing overrides for B2B catalog.",
  "type": "magento2-module",
  "version": "1.0.0",
  "require": {
    "php": "~8.1.0||~8.2.0",
    "magento/framework": ">=103.0",
    "magento/module-catalog": ">=104.0"
  },
  "autoload": {
    "files": ["registration.php"],
    "psr-4": { "Vendor\\SpecialPricing\\": "" }
  }
}
```

**[BLOCK]** Missing `magento2-module` type or `registration.php` in autoload files.
**[WARN]** No `require` on a Magento core module the code actually imports from.

#### 1.5 Naming

- Vendor name must be descriptive: `Vendor_SpecialPricing`, `Vendor_StorePickup`, `Vendor_KlarnaAdapter`. **[BLOCK]** on `Vendor_Helper`, `Vendor_Utils`, `Vendor_Core`, `Vendor_Common`, `Vendor_Misc` — these are dumping grounds, not modules.
- Module name = single bounded capability. If you find classes for catalog, checkout, and customer in one module, split into three modules.
- Class names: `CheckoutStepManager`, not `Helper`, not `Processor`, not `Service` (ambiguous suffix).

---

### 2. Dependency Injection

Shared rule: SOLID, DI not ObjectManager. Magento concrete form below.

#### 2.1 Constructor injection with `readonly`

**[BLOCK]** Use `ObjectManager` anywhere except `Test/`, fixture scripts, and factory `create()` internals.

```php
final class ApplyTierPrice
{
    public function __construct(
        private readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        private readonly \Vendor\SpecialPricing\Model\Config $config,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {}

    public function execute(int $productId, int $customerGroupId): ?float
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
        // ...
        return $finalPrice;
    }
}
```

**[BLOCK]** Constructor property without `readonly` (unless it genuinely must mutate).
**[BLOCK]** Injecting a concrete class when a service-contract interface exists (e.g. inject `ProductRepositoryInterface`, not `ProductRepository`).
**[BLOCK]** More than 7 constructor dependencies. If exceeded, the class violates SRP — split it. [WARN] at 6.

#### 2.2 `di.xml` patterns

**Prefer plugins over preferences.** A preference swaps an entire class for all callers; a plugin is surgical.

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <!-- GOOD: surgical behavior change -->
    <type name="Magento\Catalog\Model\Product">
        <plugin name="vendor_specialprice_product" type="Vendor\SpecialPricing\Plugin\ProductTierPricePlugin"/>
    </type>

    <!-- GOOD: data argument injection -->
    <type name="Magento\Checkout\Model\CompositeConfigProvider">
        <arguments>
            <argument name="configProviders" xsi:type="array">
                <item name="vendor_storepickup" xsi:type="object">Vendor\StorePickup\Model\Checkout\ConfigProvider</item>
            </argument>
        </arguments>
    </type>

    <!-- LAST RESORT: preference for an interface with no Magento default -->
    <preference for="Vendor\SpecialPricing\Api\TierPriceRepositoryInterface"
                type="Vendor\SpecialPricing\Model\TierPriceRepository"/>
</config>
```

**[WARN]** New `<preference>` for a Magento core concrete class (high blast radius — prefer a plugin). Escalate under shared governance "Architecture decisions" → Tier 2.
**[BLOCK]** Preference pointing to a class that does not implement the interface it replaces.
**[WARN]** `VirtualType` used without a comment explaining why a real class was insufficient.

---

### 3. Plugins (Interceptors)

#### 3.1 Choose the type by intent

| Intent | Use |
|---|---|
| Change or validate the **input arguments** before the method runs | `before` |
| Change or augment the **return value** | `after` |
| **Replace** the method's logic entirely, or short-circuit | `around` (rare) |

#### 3.2 `before` / `after` / `around` signatures

```php
final class ProductTierPricePlugin
{
    // before: modify inputs, return array of new args (or null to keep originals)
    public function beforeGetTierPrice(
        \Magento\Catalog\Model\Product $subject,
        $qty,
        $customerGroupId
    ): array {
        // snap negative qty to 1
        return [$qty > 0 ? (float) $qty : 1.0, $customerGroupId];
    }

    // after: receive original result, return replacement
    public function afterGetTierPrice(
        \Magento\Catalog\Model\Product $subject,
        $result,
        $qty,
        $customerGroupId
    ) {
        if ($result === null || $this->config->isB2bDiscountEnabled()) {
            return $result;
        }
        return min((float) $result, $this->b2bFloor);
    }

    // around: MUST call $proceed as the 2nd positional param and return the correct type
    public function aroundGetTierPrice(
        \Magento\Catalog\Model\Product $subject,
        callable $proceed,
        $qty,
        $customerGroupId
    ) {
        if (!$this->config->isTierPricingEnabled()) {
            return $proceed($qty, $customerGroupId);   // delegate untouched
        }
        $original = $proceed($qty, $customerGroupId);
        return $this->adjust($original);
    }
}
```

**[BLOCK]** `around` plugin that does not call `$proceed()`.
**[BLOCK]** `around` plugin that calls `$proceed()` with wrong args or wrong order.
**[BLOCK]** `around` plugin that returns a different type than the original method (causes silent type coercion bugs downstream).
**[WARN]** `around` used when `before`/`after` would suffice. `around` disables other plugins on the same method.
**[WARN]** `sortOrder` other than the default `10` without an inline comment naming the conflicting plugin it orders against.

#### 3.3 Where plugins cannot work

**[BLOCK]** Plugin on `__construct`, `__wakeup`, `__sleep`, `private`, or `static` methods — Magento cannot intercept them; it will silently do nothing.
**[BLOCK]** Plugin on a `final` class or `final` method.
**[WARN]** Two plugins in the same project targeting the same method — confirm `sortOrder` resolves the conflict deliberately.
**[WARN]** Plugin on another module's plugin (pluginception) — brittle, escalate to TL.

---

### 4. Observers

#### 4.1 Observers are for side effects only

**[BLOCK]** Observer that mutates the return value of the dispatched event (e.g. setting `$observer->getEvent()->getProduct()->setPrice(...)` to fake a price override). Use a plugin on the producing method instead. Observers fire unpredictably across modules and break order assumptions.

Allowed side effects: audit logging, sync to external PIM/ERP, index invalidation, notification dispatch, cache tag clearing.

```php
final class SyncOrderToErp implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        private readonly \Vendor\Erp\Api\ErpOrderClientInterface $erp,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {}

    public function execute(\Magento\Framework\Event\Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof \Magento\Sales\Api\Data\OrderInterface) {
            return; // defensive — event payload changed
        }
        try {
            $this->erp->pushOrder($order);
        } catch (\Vendor\Erp\Api\ErpUnavailableException $e) {
            $this->logger->error('ERP sync deferred', ['increment_id' => $order->getIncrementId(), 'reason' => $e->getMessage()]);
            // do NOT rethrow — observer failure would break the order save flow
        }
    }
}
```

**[BLOCK]** Observer rethrows an exception that propagates into the order save / checkout flow (payment capture rollback risk).
**[WARN]** Observer doing heavy work (HTTP call, large loop) synchronously in the request — push to a queue or cron.

#### 4.2 Area scoping

```xml
<!-- global: only if the event genuinely spans frontend + adminhtml -->
etc/events.xml
<!-- frontend-only -->
etc/frontend/events.xml
<!-- admin-only -->
etc/adminhtml/events.xml
```

**[WARN]** Order/customer event observer registered globally when it should be frontend-only (admin actions trigger double sync).
**[WARN]** Observer listening for `controller_action_predispatch` to gate routes — use ACL or a router instead.

---

### 5. Database & Declarative Schema

**[BLOCK]** New `InstallSchema.php`, `UpgradeSchema.php`, `InstallData.php`, `UpgradeData.php`, or `mysql4-install` files. Declarative schema (`db_schema.xml`) is the only allowed path for new tables/columns. Recurring data setup via `Setup/Patch/Data/*` is allowed for data only, never DDL.

#### 5.1 Full table example

```xml
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="vendor_storepickup_location" resource="default" engine="innodb"
           comment="Physical pickup locations for store-pickup shipping method">
        <column xsi:type="int" name="location_id" padding="10" unsigned="true" nullable="false"
                identity="true" comment="Primary key"/>
        <column xsi:type="varchar" name="code" length="64" nullable="false"
                comment="Unique human-readable location code, e.g. SYD-CBD"/>
        <column xsi:type="varchar" name="name" length="255" nullable="false"
                comment="Display name shown at checkout"/>
        <column xsi:type="decimal" name="latitude" scale="8" precision="11" nullable="true"
                comment="GPS latitude for distance sorting"/>
        <column xsi:type="decimal" name="longitude" scale="8" precision="11" nullable="true"
                comment="GPS longitude for distance sorting"/>
        <column xsi:type="smallint" name="is_active" padding="2" nullable="false" default="1"
                comment="1 = selectable at checkout, 0 = hidden"/>
        <column xsi:type="timestamp" name="created_at" on_update="false" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Row creation time"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="location_id"/>
        </constraint>
        <constraint xsi:type="unique" referenceId="VENDOR_STOREPICKUP_LOCATION_CODE">
            <column name="code"/>
        </constraint>
        <index referenceId="VENDOR_STOREPICKUP_LOCATION_IS_ACTIVE" indexType="btree">
            <column name="is_active"/>
        </index>
    </table>

    <!-- FK example with explicit on_delete -->
    <table name="vendor_storepickup_order_link">
        <column xsi:type="int" name="link_id" padding="10" unsigned="true" nullable="false" identity="true"
                comment="Primary key"/>
        <column xsi:type="int" name="order_id" padding="10" unsigned="true" nullable="false"
                comment="FK to sales_order.entity_id"/>
        <column xsi:type="int" name="location_id" padding="10" unsigned="true" nullable="false"
                comment="FK to vendor_storepickup_location.location_id"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="link_id"/>
        </constraint>
        <constraint xsi:type="foreign" referenceId="VENDOR_STRPK_LNK_ORDER_ID_SALES_ORDER_ENTITY_ID"
                    table="vendor_storepickup_order_link" column="order_id"
                    referenceTable="sales_order" referenceColumn="entity_id" onDelete="CASCADE"/>
        <constraint xsi:type="foreign" referenceId="VENDOR_STRPK_LNK_LOC_ID_VENDOR_LOC_LOCATION_ID"
                    table="vendor_storepickup_order_link" column="location_id"
                    referenceTable="vendor_storepickup_location" referenceColumn="location_id" onDelete="CASCADE"/>
    </table>
</schema>
```

**[BLOCK]** Column without a `comment` attribute.
**[BLOCK]** Money column declared as `float` instead of `decimal` (precision loss). Use `decimal scale="4" precision="12"`.
**[BLOCK]** camelCase column names (`orderStatus`). Use `snake_case` (`order_status`).
**[BLOCK]** `on_delete` missing on a foreign key — explicit `CASCADE`/`SET NULL`/`RESTRICT` required.
**[WARN]** Table without a relevant index on columns used in `WHERE`/`ORDER BY` (perf — shared rule).

#### 5.2 Whitelist generation

**[BLOCK]** PR with changed `db_schema.xml` but unchanged `db_schema_whitelist.json`. Always run before commit:

```bash
bin/magento setup:db-declaration:generate-whitelist --module-name=Vendor_StorePickup
```

#### 5.3 No direct SQL

**[BLOCK]** `SELECT`/`INSERT`/`UPDATE`/`DELETE` written as raw SQL strings or via `\Magento\Framework\DB\Adapter\Pdo\Mysql::query()` outside of a repository/resource model. Use a `ResourceModel` or `SearchCriteriaBuilder` + repository.

```php
// DON'T
$connection->query("UPDATE sales_order SET status = 'complete' WHERE entity_id = " . (int) $id);

// DO
$order = $this->orderRepository->get($id);
$order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
$order->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
$this->orderRepository->save($order);
```

---

### 6. Service Contracts & API

#### 6.1 Interface split

- `Api/` = service operations (methods that DO something).
- `Api/Data/` = data transfer objects (getters/setters only).
- `Model/` = implementations.
- `etc/di.xml` declares `<preference>` for each interface.

```php
// Api/TierPriceRepositoryInterface.php
namespace Vendor\SpecialPricing\Api;

interface TierPriceRepositoryInterface
{
    /**
     * @param int $productId
     * @param int $customerGroupId
     * @return \Vendor\SpecialPricing\Api\Data\TierPriceInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $productId, int $customerGroupId): \Vendor\SpecialPricing\Api\Data\TierPriceInterface;
}
```

**[BLOCK]** Interface in `Api/` with a method that returns a concrete `Model` class instead of an `Api/Data` interface.
**[BLOCK]** Missing `@throws` on a method that throws `NoSuchEntityException`/`LocalizedException`.

#### 6.2 `webapi.xml` + ACL

```xml
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">
    <route url="/V1/vendor/tier-price/:productId/:customerGroupId" method="GET">
        <service class="Vendor\SpecialPricing\Api\TierPriceRepositoryInterface" method="get"/>
        <resources>
            <resource ref="Vendor_SpecialPricing::tier_price_view"/>
        </resources>
    </route>
    <route url="/V1/vendor/tier-price" method="POST">
        <service class="Vendor\SpecialPricing\Api\TierPriceRepositoryInterface" method="save"/>
        <resources>
            <resource ref="Vendor_SpecialPricing::tier_price_manage"/>
        </resources>
    </route>
</routes>
```

**[BLOCK]** Route with `ref="Magento_Backend::admin"` instead of a dedicated ACL resource (over-broad privilege).
**[BLOCK]** Write operation (POST/PUT/DELETE) missing an ACL `ref`. Anonymous routes (`<resources/>` empty) for write ops are **[BLOCK]**.
**[WARN]** GET route exposing customer or order data without an ACL or customer-self-resource.

#### 6.3 GraphQL resolver

```graphql
# etc/schema.graphqls
type Query {
    vendorStorePickupLocations(
        postcode: String,
        currentPage: Int = 1,
        pageSize: Int = 20
    ): VendorStorePickupLocationResults @resolver(class: "Vendor\\StorePickup\\Model\\Resolver\\Locations")
    @cache(cacheIdentity: "Vendor\\StorePickup\\Model\\Resolver\\Data\\LocationIdentity")
}

type VendorStorePickupLocation {
    location_id: Int
    code: String
    name: String
    latitude: Float
    longitude: Float
    is_active: Boolean
}

type VendorStorePickupLocationResults {
    items: [VendorStorePickupLocation]
    total_count: Int
    page_info: PageInfo @resolver(class: "\\Magento\\Framework\\GraphQl\\Query\\Resolver\\PageInfo")
}
```

```php
final class Locations implements \Magento\Framework\GraphQl\Query\ResolverInterface
{
    public function __construct(
        private readonly \Vendor\StorePickup\Api\LocationRepositoryInterface $locationRepository,
        private readonly \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context, // injected by factory
        private readonly \Magento\Framework\AuthorizationInterface $authorization
    ) {}

    public function resolve(
        \Magento\Framework\GraphQl\Config\Element\Field $field,
        $context,
        \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (false === $this->authorization->isAllowed('Vendor_StorePickup::location_view')) {
            throw new \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException(
                __('You are not authorized to view pickup locations.')
            );
        }
        $pageSize = max(1, min(100, (int) ($args['pageSize'] ?? 20)));
        $currentPage = max(1, (int) ($args['currentPage'] ?? 1));

        $result = $this->locationRepository->getList(
            $args['postcode'] ?? null,
            $currentPage,
            $pageSize
        );

        return [
            'items' => $this->mapItems($result->getItems()),
            'total_count' => $result->getTotalCount(),
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => (int) ceil($result->getTotalCount() / $pageSize),
            ],
        ];
    }
}
```

**[BLOCK]** Resolver throwing a generic `\Exception` (breaks the GraphQL error envelope). Use `GraphQlInputException` / `GraphQlNoSuchEntityException` / `GraphQlAuthorizationException`.
**[BLOCK]** Resolver that does not enforce authorization for customer/order data.
**[WARN]** Resolver without pagination on a list type (unbounded result set).
**[WARN]** Resolver that calls repository per-item inside a loop (N+1 — see Section 10).

---

### 7. High-Risk Code: Checkout / Payment / Shipping / Order / Price

These areas are **Tier-2 escalations** under shared governance (CTO/SA, 4h response). Every change requires explicit AC and a release gate.

#### 7.1 Money math

**[BLOCK]** Any money value stored, compared, or summed as a PHP `float`. Use `float` only for the final cast to display. Internal math uses strings + `bcmath`.

```php
// DON'T — float comparison, will misbehave around .10/.20 boundaries
if ($order->getGrandTotal() === $payment->getAmountPaid()) { ... }

// DO
$lineTotal = '0';
foreach ($items as $item) {
    $lineTotal = bcadd($lineTotal, (string) $item->getRowTotalInclTax(), 4);
}
// round once, at the boundary
$lineTotal = bcmul(bcadd($lineTotal, '0', 4), '1', 2);
if (bccomp((string) $order->getGrandTotal(), $lineTotal, 2) === 0) { ... }
```

**[BLOCK]** Round-then-sum tax on a cart. Magento's rule: compute tax **per line item**, then sum the lines, then round the total once. Round-then-sum drifts cents that fail tax audits.
**[WARN]** New `round()` call on a money value without a comment explaining which currency rounding is intended (base vs display).

#### 7.2 Partial invoice / refund math

**[BLOCK]** Custom invoice/refund calculation that does not respect `Magento\Sales\Model\Order\Invoice\Total*` collectors. Partial invoices must recompute tax/discount per the qty being invoiced, not by dividing the order total.
**[WARN]** Refund amount derived from `order.grand_total - already_refunded` instead of using `CreditmemoFactory` with the item qtys.

#### 7.3 Order state machine

**[BLOCK]** Code that sets `order.state` to a value not in the state transition map without using `\Magento\Sales\Model\Order::setState()` with a comment naming the business reason. Allowed transitions are defined by `Magento\Sales\Model\Order\State` and the payment method config.

```php
// DON'T
$order->setState('processing');
$order->setStatus('processing');

// DO — document the deviation
// State set manually because the webhook confirms capture before Magento's normal flow.
$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
$order->setStatus('custom_payment_captured');
```

#### 7.4 Payment logging

**[BLOCK]** Logging any of: full PAN, CVV, cardholder name combined with PAN, expiry, or any token that is reversible to the PAN. PCI DSS 3.4. Payment modules log **only** `last4`, `brand`, and a non-reversible reference.

```php
// DON'T
$this->logger->info('Card received', ['number' => $card->getNumber(), 'cvv' => $card->getCvv()]);

// DO
$this->logger->info('Payment authorised', [
    'increment_id' => $order->getIncrementId(),
    'last4' => substr($card->getNumber(), -4),
    'brand' => $card->getBrand(),
    'reference' => $response->getPaymentReference(),
]);
```

#### 7.5 Payment webhook idempotency

**[BLOCK]** Webhook controller that processes the same external event twice on retry. Use an idempotency key (provider's event ID) persisted with a unique index, and short-circuit on duplicate.

```php
public function execute(): \Magento\Framework\Controller\ResultInterface
{
    $event = $this->decodeWebhook($this->getRequest());
    try {
        $this->markEventProcessed($event->getEventId()); // INSERT into vendor_payment_event with UNIQUE(event_id)
    } catch (\Magento\Framework\DB\Adapter\DuplicateException $e) {
        return $this->ackResult(); // already handled — ack so provider stops retrying
    }
    $this->paymentProcessor->apply($event);
    return $this->ackResult();
}
```

#### 7.6 Multi-currency

**[WARN]** Code reading `order.grand_total` for display on the frontend when the storefront uses a display currency — read `order.order_currency_code` and `grand_total` together, or use `\Magento\Directory\Model\Currency` to format. Base currency is `base_grand_total`.

#### 7.7 Price / stock / coupon — never trust the client

Shared security rule applies. Magento concrete form:

**[BLOCK]** Frontend POST that sends a `price` or `discount_amount` value which the backend applies without re-resolving from catalog/rule tables. Always re-derive price from `\Magento\Catalog\Model\Product\Type\Price` and discount from `\Magento\SalesRule\Model\RulesApplier`.
**[BLOCK]** Cart total or stock-reservation logic that reads `qty` from the request body as authoritative without `StockStateProvider::verifyQuoteItem`.
**[BLOCK]** Coupon code applied without re-validating against `\Magento\SalesRule\Model\Coupon` + rule conditions server-side.

---

### 8. Luma Frontend (legacy)

Luma is the default Magento frontend: RequireJS, Knockout, jQuery, LESS. Treat as legacy — minimize new Luma work; prefer Hyvä or headless for net-new builds.

#### 8.1 Mixins over file overrides

**[BLOCK]** Overriding a core JS file by copying it to `app/code/Vendor/Module/view/frontend/web/js/...` with the same path. Use a RequireJS mixin.

```js
// view/frontend/requirejs-config.js
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/summary/abstract-total': {
                'Vendor_StorePickup/js/view/summary/abstract-total-mixin': true
            }
        }
    }
};

// view/frontend/web/js/view/summary/abstract-total-mixin.js
define(['underscore'], function (_) {
    'use strict';
    return function (target) {
        return target.extend({
            getPureValue: function () {
                var base = this._super();
                return base + (this.storePickupFee || 0);
            }
        });
    };
});
```

**[WARN]** jQuery used where `document.querySelector`/`Element.closest` works.
**[WARN]** Knockout template editing inside `node_modules`-style core paths rather than via layout XML `template` argument override.

#### 8.2 Layout XML

```xml
<!-- view/frontend/layout/checkout_index_index.xml -->
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="checkout.root">
            <arguments>
                <argument name="jsLayout" xsi:type="array">
                    <item name="components" xsi:type="array">
                        <item name="checkout" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <item name="steps" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="shipping-step" xsi:type="array">...</item>
                                    </item>
                                </item>
                            </item>
                        </item>
                    </item>
                </argument>
            </arguments>
        </referenceBlock>
    </body>
</page>
```

**[WARN]** Whole-component tree copy instead of `<referenceBlock>` + targeted child override.

---

### 9. Hyvä Frontend (Tailwind + Alpine.js)

Hyvä ships **no RequireJS, no jQuery, no Knockout, no UI Components**. **[BLOCK]** any of these being imported in Hyvä module code. Use Alpine.js + Tailwind + ViewModels.

#### 9.1 ViewModel over Block method

A block method called from a `.phtml` couples the template to a PHP class and breaks caching. Inject data via a ViewModel implementing `\Magento\Framework\View\Element\Block\ArgumentInterface`.

```php
final class StoreLocatorViewModel implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    public function __construct(
        private readonly \Vendor\StorePickup\Api\LocationRepositoryInterface $locationRepository,
        private readonly \Magento\Directory\Model\TopupRegions $regions
    ) {}

    /** @return \Vendor\StorePickup\Api\Data\LocationInterface[] */
    public function getActiveLocations(): array
    {
        return $this->locationRepository->getActiveList();
    }

    public function isPickupEnabledForQuote(): bool
    {
        return $this->locationRepository->hasActiveLocations();
    }
}
```

```xml
<!-- view/frontend/layout/checkout_index_index.xml -->
<referenceBlock name="checkout.container">
    <block name="store-pickup"
           template="Vendor_StorePickup::checkout/store-pickup.phtml"
           class="Magento\Framework\View\Element\Template">
        <arguments>
            <argument name="view_model" xsi:type="object">Vendor\StorePickup\ViewModel\StoreLocatorViewModel</argument>
        </arguments>
    </block>
</referenceBlock>
```

```php
// in the .phtml
/** @var \Magento\Framework\View\Element\Template $block */
/** @var \Vendor\StorePickup\ViewModel\StoreLocatorViewModel $viewModel */
$viewModel = $block->getViewModel();
$locations = $viewModel->getActiveLocations();
?>
<div x-data="storePickup(<?= /* @noEscape */ $block->getLocationsJson() ?>)"
     x-init="loadInitial()"
     x-show="enabled"
     x-bind:disabled="loading">
    <ul>
        <template x-for="loc in filteredLocations" :key="loc.location_id">
            <li>
                <span x-text="loc.name"></span>
                <span x-text="hyva.formatPrice(loc.fee)"></span>
                <button type="button" @click="select(loc.location_id)">Select</button>
            </li>
        </template>
    </ul>
</div>
```

**[BLOCK]** Block class extending `\Magento\Framework\View\Element\Template` and adding public methods used in `.phtml` for business data — promote to a ViewModel. Block methods allowed: escaping, formatting helpers only.
**[WARN]** Alpine state stored on `window` instead of in an Alpine `$store` or the component `x-data`.

#### 9.2 Alpine.js component pattern

```js
// view/frontend/web/js/store-pickup.js
export default (initialLocations = []) => ({
    locations: initialLocations,
    selectedId: null,
    loading: false,
    enabled: true,

    init() {
        // x-init callback
        this.selectedId = this.selectedFromQuoteCookie();
    },
    get filteredLocations() {
        return this.locations.filter((l) => l.is_active);
    },
    select(id) {
        this.selectedId = id;
        this.persistToQuote(id);
    },
    async persistToQuote(id) {
        this.loading = true;
        try {
            await fetch(`${window.checkoutConfig.baseUrl}rest/V1/vendor/storepickup/select`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ location_id: id }),
            });
        } finally {
            this.loading = false;
        }
    },
});
```

**[WARN]** `fetch()` without error handling and without a `finally` to clear the loading flag.
**[WARN]** Alpine side-effect (fetch, mutation) inside a getter — getters must be pure.

#### 9.3 Tailwind

**[BLOCK]** Dynamic class concatenation — Tailwind's JIT purge cannot see them:
```html
<!-- DON'T -->
<span class="text-<?= $brandColor ?>-500"></span>
```
```html
<!-- DO — full literal classes; safelist them in tailwind.config if driven by data -->
<span class="<?= $isWarning ? 'text-red-500' : 'text-green-500' ?>"></span>
```

**[WARN]** `@apply` used in a global CSS file. `@apply` belongs in component-scoped CSS (e.g. `view/frontend/web/css/source/_components.less` analog). Use utilities directly in markup for layout.
**[WARN]** Custom color missing from `hyva-themes.json` / theme config — must extend the theme, not inline a hex.

#### 9.4 Cross-component state

Use Alpine `$store` for shared state (e.g. cart count, selected pickup location) instead of re-fetching in each component. Register the store once:

```js
document.addEventListener('alpine:init', () => {
    Alpine.store('pickup', { locationId: null, fee: 0 });
});
```

#### 9.5 Formatting and icons

- Prices: `hyva.formatPrice(value, showSign)` — never hand-roll number formatting.
- Icons: Heroicons helper (`$heroicons->heroiconOutline('truck')`), not raw SVG strings duplicated per template.

---

### 10. Headless / GraphQL Backend

When Magento is the API for a Next.js / external frontend, resolvers and schema design ARE the contract.

#### 10.1 Schema design

- One type per domain entity. Do not flatten cart/order/product into one mega-type.
- Lists return a `*Results`/`*Connection` wrapper with `items`, `total_count`, and `page_info` — never a bare `[T]` for paginated data.
- Mutations return a typed payload (`type XResponse { x: X user_errors: [UserError!]! }`), not a bare scalar.

**[BLOCK]** New `Query` field returning a list without pagination args (`pageSize`/`currentPage`).
**[BLOCK]** Mutation returning a bare `String`/`Boolean` instead of a typed response.

#### 10.2 Resolver performance — no N+1

**[BLOCK]** Resolver that loads related entities one-at-a-time inside the item loop. Use the `BatchResolverInterface` or pre-fetch by a list of IDs.

```php
// DON'T — N+1, one product repo call per cart item
final class ProductThumbnailResolver implements ResolverInterface {
    public function resolve(...) {
        foreach ($value['items'] as &$item) {
            $product = $this->productRepository->getById($item['product_id']); // N calls
            $item['thumbnail'] = $product->getThumbnail();
        }
        return $value['items'];
    }
}

// DO — BatchResolverInterface, one query
final class ProductThumbnailBatchResolver implements BatchResolverInterface {
    public function __construct(
        private readonly \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
    ) {}

    public function resolve(BatchResolverContext $context): array {
        $productIds = array_unique(array_column($context->getItemsValue(), 'product_id'));
        $collection = $this->collectionFactory->create();
        $collection->addIdFilter($productIds)
                   ->addAttributeToSelect('thumbnail');
        $thumbnails = [];
        foreach ($collection as $product) {
            $thumbnails[$product->getId()] = $product->getThumbnail();
        }
        return array_map(
            fn($item) => ['thumbnail' => $thumbnails[$item['product_id']] ?? null],
            $context->getItemsValue()
        );
    }
}
```

#### 10.3 Authorization per field

**[BLOCK]** Field resolver returning customer or order data without checking `$context->getUserId()` or `isAllowed()`. Treat every field that touches PII as needing an authz check, not just the top-level query.

```php
if ($context->getUserId() !== (int) $order->getCustomerId()
    && !$this->authorization->isAllowed('Magento_Sales::actions_view')) {
    throw new GraphQlAuthorizationException(__('Cannot view another customer\'s order.'));
}
```

#### 10.4 Caching headers

**[WARN]** Resolver returning catalog/marketing data without a `@cache` directive and `cacheIdentity`. Customer-scoped data MUST NOT set a public cache identity — **[BLOCK]** if it does.

#### 10.5 Error envelope

All resolver errors go through `GraphQLException` subclasses. Map error codes to a documented contract so the frontend can branch:

```php
throw new \Magento\Framework\GraphQl\Exception\GraphQlInputException(
    __('Coupon code "%1" is not valid.', [$code])
);
throw new \Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException(
    __('No product with SKU "%1".', [$sku])
);
```

**[BLOCK]** Catching and swallowing resolver exceptions, returning `null`. The frontend loses the failure signal.

---

### Appendix A — Magento-specific pre-review checklist (additions to shared checklist)

- [ ] `registration.php` + `module.xml` + `composer.json` present and consistent.
- [ ] `module.xml` `<sequence>` lists only true compile-time deps.
- [ ] No `InstallSchema`/`UpgradeSchema`/`InstallData`/`UpgradeData`; DDL only in `db_schema.xml`.
- [ ] `db_schema_whitelist.json` regenerated and committed alongside `db_schema.xml`.
- [ ] Every column has a `comment`; money columns are `decimal`; columns are `snake_case`.
- [ ] All FKs declare `on_delete`.
- [ ] No `ObjectManager` outside `Test/`/fixtures/factories.
- [ ] Constructor deps ≤ 7; all properties `readonly`; interfaces preferred over concretes.
- [ ] Every `<plugin>` target is a public non-final method on a non-final class.
- [ ] Every `around` plugin calls `$proceed()` and returns the correct type.
- [ ] `sortOrder` ≠ 10 has an inline comment naming the conflict.
- [ ] Observers do side-effect work only; never rethrow into order/checkout save.
- [ ] `events.xml` is area-scoped (frontend/adminhtml), not global unless intentional.
- [ ] All write webapi routes have an ACL `ref`; read routes for PII have one too.
- [ ] GraphQL list fields are paginated; resolvers use batches (no N+1).
- [ ] GraphQL resolvers throw `GraphQL*Exception` subclasses only.
- [ ] Money math uses strings + `bcmath`; tax computed per-line-then-rounded.
- [ ] Payment logs contain no PAN/CVV/full-cardholder+expiry.
- [ ] Payment webhook is idempotent via unique event-id index.
- [ ] Hyvä code imports no jQuery/RequireJS/Knockout; data via ViewModel.
- [ ] Tailwind classes are literal strings (no `text-{{var}}`).
- [ ] Luma JS uses mixins, not file copies; layout XML uses `referenceBlock` + targeted override.

---

## Hyvä / Tailwind v4 (Project-Specific)

> This project's frontend is **Hyvä 3.x** (default theme 1.5.2). Interactive components use **Alpine.js + Magewire 1.13**. Custom themes live under `app/design/frontend/Secomm/{launchpad,launchpad_fashion}/`.

### Tailwind CSS v4 — CSS-first config (no `tailwind.config.js`)

- **[BLOCK]** Do NOT create or reference a `tailwind.config.js`. This project uses **Tailwind CSS v4** with CSS-first configuration via `@theme` and `@source` directives in `tailwind-source.css`. *(This corrects older Hyvä docs that reference a `tailwind.config.js` and a `safelist` array — that pattern does not apply here.)*
- Theme tokens (colors incl. oklch primary/secondary, fonts, spacing) are defined with `@theme { ... }` inside `tailwind-source.css`. Extend the theme there, not via a JS config.
- Class scanning scope is declared with `@source` directives. **Dynamic class names must be in `@source`/safelist scope** or the Tailwind v4 build will purge them.

### Component conventions

- **[BLOCK]** No React/Vue — frontend components use Hyvä patterns: **Alpine.js + Magewire 1.13**; UI is `.phtml`-driven, no JS framework.
- **Prefer ViewModels over Blocks** for template data — inject classes implementing `Magento\Framework\View\Element\Block\ArgumentInterface` via layout XML; do not add business-data methods to Block classes.
- Templates are `.phtml` in the `Hyva_*` namespace / `Secomm_*` theme namespace under `app/design/frontend/Secomm/*/`.
- **[BLOCK]** Dynamic class concatenation (`class="text-<?= $brandColor ?>-500"`) — Tailwind's scanner cannot see it. Use full literal classes; if a class is data-driven, ensure it is reachable by an `@source` directive.

### Theme toolchain

- Theme Tailwind toolchain lives in `app/design/frontend/Secomm/launchpad/web/tailwind/`. After class/token changes, run the Hyvä Tailwind build (`tailwind-source.css` → `styles.css`) and commit the compiled output.
- `hyva-themes.json` holds theme color/icon config — extend the theme there, do not inline hex values in templates.

### Magewire

- Use Magewire 1.13 for server-driven reactive components instead of bespoke AJAX controllers. Keep Magewire actions thin; heavy work belongs in a service/action class.

--- END GENERATED ---

## Project Overrides

> Pasted verbatim from `PROJECT_AI_BLUEPRINT.md` Section 15 (`coding_rules_override`). These override the shared/stack rules above where they conflict.

- Tailwind CSS v4 — CSS-first config via @theme/@source in tailwind-source.css; DO NOT create a tailwind.config.js
- Frontend components use Hyvä patterns: Alpine.js + Magewire 1.13; phtml-driven, no React/Vue
- Custom module vendor prefixes: Secomm_ (project), Mageplaza_* are third-party (do not modify in place — extend via plugin/preference)
- All new PHP targets PHP 8.2+ (8.2–8.4 compatible); strict_types + Magento coding standard
- Storefront strings must be added to both vi_VN.csv and en_US.csv translation dictionaries
