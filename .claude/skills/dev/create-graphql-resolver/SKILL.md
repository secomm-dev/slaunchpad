# Create a Custom GraphQL Resolver (Headless / Deep)

## Purpose
Use this skill when a headless storefront needs custom GraphQL data that Magento core does not expose — a new query (read) or mutation (write) backed by custom schema and business logic. This is the deep, standalone GraphQL skill; for the lightweight "REST + GraphQL" combo use `create-api-endpoint`.

## Prerequisites
- Read `AGENTS.md` Section 7.2 — GraphQL resolvers implement `FieldResolverInterface`, throw `GraphQLException` for typed errors
- Read `project-context/03-tech-stack.md` (confirm headless) and `project-context/05-conventions.md`
- A module already created (use `create-module`)
- Familiarity with GraphQL type system (type / input / query / mutation)

## Input
- **Operation** (query or mutation)
- **Type name** (e.g. `ProductReminderSetting`)
- **Arguments** (e.g. `sku`, `customer_id`)
- **Auth requirement** (anonymous, customer-self, admin)
- **Return shape** (fields and nested types)

## Generated Files
- `etc/schema.graphqls`
- `Model/Resolver/ProductReminderSetting.php`
- (optional) `Model/Resolver/Data/ProductReminderSetting.php` — a data provider for batched loading

## Step-by-Step

### Step 1: Extend the GraphQL schema
`etc/schema.graphqls`. A syntax error here disables the ENTIRE GraphQL API — every query 500s. Validate after every edit.

```graphql
# New type returned by the resolver
type ProductReminderSetting @doc(description: "Customer's reminder settings for a product") {
    sku: String @doc(description: "Product SKU")
    customer_id: Int @doc(description: "Customer ID")
    email: String @doc(description: "Reminder email")
    threshold_qty: Float @doc(description: "Quantity at which to remind")
    is_enabled: Boolean @doc(description: "Whether the reminder is active")
}

# Input for the mutation
input ProductReminderSettingInput @doc(description: "Payload to save a reminder setting") {
    sku: String @doc(description: "Product SKU")
    email: String @doc(description: "Reminder email")
    threshold_qty: Float @doc(description: "Quantity threshold")
    is_enabled: Boolean @doc(description: "Active flag")
}

extend type Query {
    productReminderSetting(
        sku: String!
    ): ProductReminderSetting @resolver(class: "Acme\\StorePickup\\Model\\Resolver\\ProductReminderSetting") @doc(description: "Returns the current customer's reminder setting for a product")
}

extend type Mutation {
    saveProductReminderSetting(
        input: ProductReminderSettingInput!
    ): ProductReminderSetting @resolver(class: "Acme\\StorePickup\\Model\\Resolver\\SaveProductReminderSetting") @doc(description: "Creates or updates a reminder setting for the current customer")
}
```

Notes:
- Use `extend type Query` / `extend type Mutation` to add fields without redeclaring the type.
- `@resolver` class path uses double backslash `\\`.
- Every field should carry `@doc` — required by the GraphQL schema validator in strict mode.

### Step 2: Implement the query resolver
`Model/Resolver/ProductReminderSetting.php`:

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model\Resolver;

use Acme\StorePickup\Api\ProductReminderRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface as GraphQlContext;

class ProductReminderSetting implements ResolverInterface
{
    public function __construct(
        private readonly ProductReminderRepositoryInterface $reminderRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        /** @var GraphQlContext $context */

        // 1) Authorization — this is customer-self data; reject anonymous callers.
        if (!$context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(
                __('The current customer is not authorized to access reminder settings.')
            );
        }
        $customerId = (int) $context->getUserId();
        if ($customerId === 0) {
            throw new GraphQlAuthorizationException(__('A valid customer session is required.'));
        }

        // 2) Type-validate the required argument.
        $sku = $args['sku'] ?? '';
        if ($sku === '') {
            throw new GraphQlInputException(__('Required argument "sku" is missing.'));
        }

        // 3) Confirm the product exists (gives a typed 404-style error instead of a null).
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(
                __('Product with SKU "%1" does not exist.', [$sku])
            );
        }

        // 4) Fetch the reminder (may not exist yet — return a disabled default).
        try {
            $reminder = $this->reminderRepository->getByCustomerAndSku($customerId, $product->getSku());
            $data = [
                'sku' => $reminder->getSku(),
                'customer_id' => $customerId,
                'email' => $reminder->getEmail(),
                'threshold_qty' => (float) $reminder->getThresholdQty(),
                'is_enabled' => (bool) $reminder->getIsEnabled(),
            ];
        } catch (NoSuchEntityException $e) {
            // Sensible default when no setting exists yet.
            $data = [
                'sku' => $product->getSku(),
                'customer_id' => $customerId,
                'email' => '',
                'threshold_qty' => 0.0,
                'is_enabled' => false,
            ];
        }

        // 5) Attach the model for any nested field resolvers.
        $data['model'] = $reminder ?? null;
        return $data;
    }
}
```

### Step 3: Implement the mutation resolver
`Model/Resolver/SaveProductReminderSetting.php`:

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model\Resolver;

use Acme\StorePickup\Api\Data\ProductReminderInterfaceFactory;
use Acme\StorePickup\Api\ProductReminderRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface as GraphQlContext;

class SaveProductReminderSetting implements ResolverInterface
{
    public function __construct(
        private readonly ProductReminderRepositoryInterface $reminderRepository,
        private readonly ProductReminderInterfaceFactory $reminderFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        /** @var GraphQlContext $context */

        if (!$context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(
                __('You must be logged in to save a reminder setting.')
            );
        }
        $customerId = (int) $context->getUserId();

        $input = $args['input'] ?? [];
        $sku = is_string($input['sku'] ?? null) ? trim($input['sku']) : '';
        $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';

        if ($sku === '') {
            throw new GraphQlInputException(__('Field "sku" is required.'));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GraphQlInputException(__('Field "email" must be a valid email address.'));
        }

        $reminder = $this->reminderFactory->create()
            ->setSku($sku)
            ->setCustomerId($customerId)
            ->setEmail($email)
            ->setThresholdQty((float) ($input['threshold_qty'] ?? 0))
            ->setIsEnabled((bool) ($input['is_enabled'] ?? false));

        try {
            $saved = $this->reminderRepository->save($reminder);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        return [
            'sku' => $saved->getSku(),
            'customer_id' => (int) $saved->getCustomerId(),
            'email' => $saved->getEmail(),
            'threshold_qty' => (float) $saved->getThresholdQty(),
            'is_enabled' => (bool) $saved->getIsEnabled(),
        ];
    }
}
```

### Step 4: Batch / data-loading awareness
If the field is queried in a list (e.g. inside a `products { items { reminder_setting {...} } }` query), avoid N+1 DB calls. Implement a `BatchResolverInterface` or use the `BatchResponse` pattern from `Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface`, grouping all child SKUs into one repository lookup. For single-entity queries the per-field resolver above is correct.

### Step 5: Compile and validate the schema
```bash
bin/magento setup:di:compile
bin/magento cache:clean
# Validate the merged schema (fails fast on syntax errors):
bin/magento dev:graphql:validate-schema
```

## Coding Rules Applied
- **Resolver implements `FieldResolverInterface`** (AGENTS.md 7.2): signature `resolve(Field $field, $context, ResolveInfo $info, array $value, array $args)`
- **Throw `GraphQLException` subclasses**: `GraphQlAuthorizationException`, `GraphQlInputException`, `GraphQlNoSuchEntityException` — so the client receives typed errors with correct HTTP-level semantics
- **Authorization via customer context**: `$context->getExtensionAttributes()->getIsCustomer()` + `$context->getUserId()` — never trust a `customer_id` argument from the client for self-scoped data
- **Type-validate every argument**: cast and validate before use; do not trust client-supplied types
- **Avoid N+1 in list contexts**: switch to a batch resolver when the field is nested inside a list query

## Verification
- [ ] `bin/magento dev:graphql:validate-schema` exits 0 (catches `schema.graphqls` errors)
- [ ] Query as an anonymous customer:
  ```graphql
  query { productReminderSetting(sku: "WH01") { sku email is_enabled } }
  ```
  returns `AuthorizationException` → confirms auth gate
- [ ] Query as a logged-in customer with a valid customer token → returns the fields or a disabled default
- [ ] Mutation:
  ```graphql
  mutation { saveProductReminderSetting(input: {sku:"WH01", email:"a@b.com", threshold_qty:3, is_enabled:true}) { is_enabled } }
  ```
  returns the saved `is_enabled: true`
- [ ] Invalid email: `saveProductReminderSetting(input:{sku:"WH01", email:"bad"})` returns a typed `GraphQlInputException`
- [ ] No N+1: enable the DB query log (`bin/magento dev:query-log:enable`) and run the query inside a `products{}` list — query count should be O(1) batch, not O(n)

## Common Mistakes
- **Syntax error in `schema.graphqls`**: a missing brace, bad type, or unquoted string disables the ENTIRE GraphQL API — every query across the site returns a 500. Always run `bin/magento dev:graphql:validate-schema` after editing.
- **Not type-validating arguments**: trusting `$args['sku']` (which could be an array from a crafted query) causes `TypeError`. Cast and validate.
- **Leaking data without an auth check**: a resolver that returns customer data based on a client-supplied `customer_id` argument lets any caller read any customer's data. Always derive identity from `$context->getUserId()`.
- **Returning a plain array without `model`**: nested field resolvers cannot access the underlying entity. Pass `'model' => $entity` when downstream fields need it.
- **Throwing a raw `Exception`**: the client sees a generic `"Internal server error"` with no detail. Use `GraphQLException` subclasses so the error carries a code and message.
- **Using `@resolver` with a single backslash**: `"Acme\StorePickup\Model\Resolver\X"` parses wrong; the class is never found. Use double backslash `\\` inside the string.
- **Forgetting `extend` keyword**: declaring `type Query { ... }` instead of `extend type Query { ... }` conflicts with the core schema and fails validation.
- **N+1 in list queries**: a per-field resolver called inside `products { items { reminder_setting } }` fires one DB query per product. Use a batch resolver or a shared data loader.
