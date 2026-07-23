# Create Magento 2 REST API + GraphQL Endpoint

## Purpose
Use this skill to expose custom data to a headless frontend or external integration — a REST endpoint (for integrations/ERPs) and/or a GraphQL resolver (for headless storefront). Both forms share the same service contract.

## Prerequisites
- Read `AGENTS.md` Section 7.2 — service contracts (interface + preference), ACL roles for webapi
- Read `project-context/03-tech-stack.md` (headless vs Luma) and `project-context/05-conventions.md`
- A module already created (use `create-module`)
- Decide exposure: REST only, GraphQL only, or both (recommended: both share the same `Api/` interface)

## Input
- **Domain entity** (e.g. product stock status, custom product badge)
- **Operations** (GET query / POST mutation)
- **Auth level** (anonymous, customer self, admin ACL)
- **GraphQL type name** (e.g. `ProductStockStatus`)

## Generated Files (REST)
- `Api/ProductStockStatusInterface.php` (service contract)
- `Api/Data/ProductStockStatusInterface.php` (DTO contract)
- `Model/ProductStockStatus.php` (service implementation)
- `Model/Data/ProductStockStatus.php` (DTO implementation)
- `etc/webapi.xml`
- `etc/di.xml` (preferences for both interfaces)

## Generated Files (GraphQL)
- `etc/schema.graphqls`
- `Model/Resolver/ProductStockStatus.php`

## Step-by-Step

### Step 1: Define the DTO contracts
Data Transfer Objects carry the response payload. Interface first.

`Api/Data/ProductStockStatusInterface.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Api\Data;

interface ProductStockStatusInterface
{
    public const SKU = 'sku';
    public const IS_IN_STOCK = 'is_in_stock';
    public const QTY = 'qty';

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @return bool
     */
    public function getIsInStock(): bool;

    /**
     * @return float
     */
    public function getQty(): float;

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku): self;

    /**
     * @param bool $isInStock
     * @return $this
     */
    public function setIsInStock(bool $isInStock): self;

    /**
     * @param float $qty
     * @return $this
     */
    public function setQty(float $qty): self;
}
```

`Model/Data/ProductStockStatus.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model\Data;

use Acme\StorePickup\Api\Data\ProductStockStatusInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

class ProductStockStatus extends AbstractExtensibleModel implements ProductStockStatusInterface
{
    /**
     * @inheritDoc
     */
    public function getSku(): string
    {
        return (string) ($this->getData(self::SKU) ?? '');
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getIsInStock(): bool
    {
        return (bool) $this->getData(self::IS_IN_STOCK);
    }

    public function setIsInStock(bool $isInStock): self
    {
        return $this->setData(self::IS_IN_STOCK, $isInStock);
    }

    public function getQty(): float
    {
        return (float) $this->getData(self::QTY);
    }

    public function setQty(float $qty): self
    {
        return $this->setData(self::QTY, $qty);
    }
}
```

### Step 2: Define the service contract
`Api/ProductStockStatusInterface.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Api;

interface ProductStockStatusInterface
{
    /**
     * Get stock status for a product by SKU.
     *
     * @param string $sku
     * @return \Acme\StorePickup\Api\Data\ProductStockStatusInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(string $sku): \Acme\StorePickup\Api\Data\ProductStockStatusInterface;
}
```

### Step 3: Implement the service
`Model/ProductStockStatus.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model;

use Acme\StorePickup\Api\Data\ProductStockStatusInterface;
use Acme\StorePickup\Api\Data\ProductStockStatusInterfaceFactory;
use Acme\StorePickup\Api\ProductStockStatusInterface as ServiceInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;

class ProductStockStatus implements ServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly GetProductSalableQtyInterface $getSalableQty,
        private readonly GetStockItemConfigurationInterface $getStockItemConfig,
        private readonly ProductStockStatusInterfaceFactory $dtoFactory,
        private readonly int $defaultStockId = 1
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(string $sku): ProductStockStatusInterface
    {
        // Throws NoSuchEntityException if the SKU does not exist — surfaced to client as 404 by webapi.
        $product = $this->productRepository->get($sku);

        try {
            $salableQty = (float) $this->getSalableQty->execute($product->getSku(), $this->defaultStockId);
            $stockItem = $this->getStockItemConfig->execute($product->getSku(), $this->defaultStockId);
            $isInStock = $stockItem->isManageStock()
                ? $salableQty > 0
                : true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Product exists but no stock record — treat as out of stock.
            $salableQty = 0.0;
            $isInStock = false;
        }

        return $this->dtoFactory->create()
            ->setSku($product->getSku())
            ->setIsInStock($isInStock)
            ->setQty($salableQty);
    }
}
```

### Step 4: Register preferences in `di.xml`
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <preference for="Acme\StorePickup\Api\ProductStockStatusInterface"
                type="Acme\StorePickup\Model\ProductStockStatus"/>
    <preference for="Acme\StorePickup\Api\Data\ProductStockStatusInterface"
                type="Acme\StorePickup\Model\Data\ProductStockStatus"/>
</config>
```

### Step 5: Expose via REST in `webapi.xml`
ACL role controls who can call it. `Magento_Backend::admin` requires admin token; anonymous endpoints use `resource ref="anonymous"`.

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">
    <route url="/V1/store-pickup/stock-status/:sku" method="GET">
        <service class="Acme\StorePickup\Api\ProductStockStatusInterface" method="get"/>
        <resources>
            <!-- Customer can read their own storefront stock status -->
            <resource ref="Magento_Customer::self"/>
        </resources>
    </route>
</routes>
```

### Step 6: Expose via GraphQL in `schema.graphqls`
Extend the schema with a new query and type. A syntax error here breaks the ENTIRE GraphQL schema — validate after editing.

```graphql
type Query {
    productStockStatus(sku: String!): ProductStockStatus @resolver(class: "Acme\\StorePickup\\Model\\Resolver\\ProductStockStatus") @doc(description: "Returns stock status and quantity for a product SKU")
}

type ProductStockStatus @doc(description: "Stock status for a product") {
    sku: String @doc(description: "Product SKU")
    is_in_stock: Boolean @doc(description: "Whether the product is in stock")
    qty: Float @doc(description: "Salable quantity")
}
```

### Step 7: Implement the GraphQL resolver
`Model/Resolver/ProductStockStatus.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Model\Resolver;

use Acme\StorePickup\Api\ProductStockStatusInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ProductStockStatus implements ResolverInterface
{
    public function __construct(
        private readonly ProductStockStatusInterface $service
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
        // Authorization: storefront resolver — require a customer or guest context.
        // For admin-only data, check $context->getExtensionAttributes()->getIsCustomer() === false and throw.
        if (!$context->getExtensionAttributes()->getIsCustomer()
            && $args['require_auth'] ?? false) {
            throw new GraphQlAuthorizationException(__('You must be logged in to view this stock status.'));
        }

        $sku = $args['sku'] ?? '';
        if ($sku === '') {
            throw new GraphQlInputException(__('Required parameter "sku" is missing.'));
        }

        try {
            $status = $this->service->get($sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('Product with SKU "%1" does not exist.', [$sku]));
        }

        return [
            'sku' => $status->getSku(),
            'is_in_stock' => $status->getIsInStock(),
            'qty' => $status->getQty(),
            'model' => $status, // optional: pass the DTO for nested resolvers
        ];
    }
}
```

### Step 8: Compile and verify
```bash
bin/magento setup:di:compile
bin/magento cache:clean
```

## Coding Rules Applied
- **Service contracts**: interface in `Api/` + preference in `di.xml`, DTO contract in `Api/Data/` — required for REST webapi and clean GraphQL resolution
- **ACL roles in webapi.xml**: `Magento_Customer::self` for customer-scoped, `Magento_Backend::admin` for admin-only — never expose admin data without an ACL resource
- **GraphQL resolver implements `FieldResolverInterface`**: throw `GraphQLException` subclasses (`GraphQlInputException`, `GraphQlAuthorizationException`, `GraphQlNoSuchEntityException`) so clients get typed errors
- **Authorization check before data return**: storefront resolvers must verify the customer context before leaking data

## Verification
- [ ] REST: `curl -H "Authorization: Bearer <customer_token>" "https://magento.test/rest/V1/store-pickup/stock-status/WH01"` returns JSON with `sku`, `is_in_stock`, `qty`
- [ ] REST 404 for missing SKU: `curl -i .../stock-status/NOPE` returns `404` with `"message": "The product that was requested doesn't exist."`
- [ ] GraphQL:
  ```graphql
  query { productStockStatus(sku: "WH01") { sku is_in_stock qty } }
  ```
  returns the typed fields
- [ ] `bin/magento setup:di:compile` succeeds (catches missing preferences)
- [ ] `bin/magento dev:graphql:validate-schema` (or a query via Altair) does not error — a bad `schema.graphqls` breaks the whole schema

## Common Mistakes
- **Missing interface preference in `di.xml`**: webapi throws "Class does not exist" / GraphQL resolver cannot construct the service. The interface must map to a concrete class via `<preference>`.
- **Exposing admin-only data without an ACL resource**: setting `<resource ref="anonymous"/>` on an endpoint that returns ERP prices or order data leaks it publicly. Always pair sensitive data with `Magento_Backend::admin` or a custom ACL.
- **Not throwing `GraphQLException`**: returning a plain array when something fails gives the client a `null` field with no error. Throw `GraphQlInputException` etc. so the client sees the reason.
- **Forgetting `@resolver` annotation in schema.graphqls**: the field resolves to `null` silently. The resolver class string uses double backslash `\\` in YAML/GraphQL.
- **Schema syntax error**: a missing brace or bad type in `schema.graphqls` disables the ENTIRE GraphQL API (every query 500s). Validate after every edit.
- **Not type-validating args**: trusting `$args['sku']` blindly allows type confusion. Validate and cast.
