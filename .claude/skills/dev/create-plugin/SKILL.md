# Create Magento 2 Plugin (Before / Around / After)

## Purpose
Use this skill to modify the behavior of a core or third-party Magento class method without rewriting it — the preferred extension pattern per AGENTS.md. Plugins let you alter input arguments, output values, or wrap logic.

## Prerequisites
- Read `AGENTS.md` Section 7.2 (Coding Standards) — prefer plugins over preferences
- Read `project-context/05-conventions.md` for the plugin folder convention (e.g. `Plugin/`)
- The target class and exact method signature (check `vendor/magento/...` source)
- A module already created (use the `create-module` skill first)

## Input
- **Target class** (e.g. `Magento\Catalog\Model\Product`)
- **Target method** (e.g. `getName`, `getPrice`, `addProduct`)
- **Goal** (modify input, modify output, or replace logic)
- **Area** (`global`, `frontend`, `adminhtml`)

## Decision Guide
| Goal | Plugin type | Method name |
|------|-------------|-------------|
| Modify / validate input arguments before the original runs | **before** | `before{MethodName}` |
| Modify / wrap the return value after the original runs | **after** | `after{MethodName}` |
| Replace or fully control the original logic | **around** (use sparingly — must call `$proceed()`) | `around{MethodName}` |

Use **before** or **after** whenever possible. `around` breaks other plugins and caching if `$proceed()` is skipped or called conditionally without care.

## Generated Files
- `Plugin/{Area}/{PluginName}.php`
- `etc/{area}/di.xml` (created or updated)

## Step-by-Step

### Step 1: Inspect the original method signature
Open the target class and copy the EXACT method signature including type hints. The plugin parameter types MUST match.

Example target — `Magento\Checkout\Model\Cart::addProduct`:
```php
public function addProduct($productInfo, $requestInfo = null) { ... }
```

### Step 2: Create the plugin class
Three complete examples on real Magento classes.

**A) before — modify input (cap quantity added to cart)**

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Plugin\Frontend\Checkout;

use Magento\Checkout\Model\Cart;

class LimitCartQtyPlugin
{
    private const MAX_QTY = 10;

    /**
     * Cap add-to-cart quantity to MAX_QTY before the original runs.
     * Returns array of modified arguments (must match original param order).
     */
    public function beforeAddProduct(Cart $subject, $productInfo, $requestInfo = null): array
    {
        if (is_array($requestInfo) && isset($requestInfo['qty'])) {
            $qty = (float) $requestInfo['qty'];
            if ($qty > self::MAX_QTY) {
                $requestInfo['qty'] = self::MAX_QTY;
            }
        } elseif ($requestInfo instanceof \Magento\Framework\DataObject) {
            $qty = (float) $requestInfo->getQty();
            if ($qty > self::MAX_QTY) {
                $requestInfo->setQty(self::MAX_QTY);
            }
        }
        // For before plugins, return an array of the args in the same order as the original method.
        return [$productInfo, $requestInfo];
    }
}
```

**B) after — modify output (append a suffix to product name)**

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Plugin\Catalog;

use Magento\Catalog\Model\Product;

class ProductNamePlugin
{
    /**
     * after plugin: receives the original result and returns the modified result.
     * Type of $result MUST match the return type of getName() (string).
     */
    public function afterGetName(Product $subject, $result): string
    {
        if ($subject->hasData('is_store_pickup_only')) {
            return $result . ' (Pickup only)';
        }
        return (string) $result;
    }
}
```

**C) around — wrap logic (cache-aware custom price fetch)**

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Plugin\Catalog;

use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

class ProductPricePlugin
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * around plugin.
     * IMPORTANT: $proceed is the SECOND parameter, after $subject.
     * Any further params mirror the original method signature (excluding $subject).
     */
    public function aroundGetPrice(Product $subject, callable $proceed)
    {
        if ($subject->getData('custom_erp_price_override')) {
            try {
                // Custom branch — do NOT call $proceed().
                return (float) $subject->getData('erp_price');
            } catch (\Throwable $e) {
                $this->logger->error('ERP price fetch failed, falling back', [
                    'product_id' => $subject->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Fall through to original.
            }
        }
        // Default branch — always call $proceed() so other plugins and the original logic still run.
        return $proceed();
    }
}
```

### Step 3: Register the plugin in `di.xml`

Choose the area: `etc/di.xml` (all areas), `etc/frontend/di.xml`, or `etc/adminhtml/di.xml`.

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <!-- before plugin: cap cart qty (frontend only) -->
    <type name="Magento\Checkout\Model\Cart">
        <plugin name="acme_limit_cart_qty"
                type="Acme\StorePickup\Plugin\Frontend\Checkout\LimitCartQtyPlugin"
                sortOrder="10"
                disabled="false"/>
    </type>

    <!-- after plugin: product name suffix (global) -->
    <type name="Magento\Catalog\Model\Product">
        <plugin name="acme_product_name_suffix"
                type="Acme\StorePickup\Plugin\Catalog\ProductNamePlugin"
                sortOrder="20"
                disabled="false"/>
    </type>
</config>
```

`sortOrder` controls execution order when multiple plugins target the same method (lower runs first for before/around, result flows through after plugins in sortOrder). `disabled="true"` disables a plugin without removing the XML.

### Step 4: Compile and verify

```bash
bin/magento setup:di:compile
bin/magento cache:clean
```

## Coding Rules Applied
- **Prefer plugins over preferences** (AGENTS.md 7.2): plugins are non-breaking and chainable; preferences replace the whole class
- **before/after preferred over around**: around plugins skip other plugins and break interceptors caching if `$proceed()` is not called
- **Type hints MUST match the original signature** — wrong types cause `setup:di:compile` failures or runtime `TypeError`
- **`sortOrder` always set**: omitting it defaults to 0 and causes unpredictable ordering when other modules add plugins to the same method

## Verification
- [ ] `bin/magento setup:di:compile` succeeds with no "Plugin does not exist" errors
- [ ] `grep -r "Interceptor" generated/code/ | grep <PluginName>` — the generated interceptor class exists
- [ ] Functional test: add a product to cart with qty=20 → cart qty is capped to 10 (before plugin)
- [ ] For the after plugin: product page shows the suffix on a `is_store_pickup_only` product
- [ ] No `TypeError` in `var/log/exception.log` after exercising the path

## Common Mistakes
- **Wrong parameter order in `around`**: the signature is `aroundMethod($subject, $proceed, ...$originalArgs)`. Putting `$proceed` first or mixing in extra params breaks the call. The callable is ALWAYS the second parameter.
- **Using `after` to change input arguments**: `after` only sees the result, not the original arguments (you only get `$subject` + `$result`). To change input, use `before` and return the modified args array.
- **Forgetting `sortOrder`**: when two modules plugin the same method, missing sortOrder causes intermittent order-dependent bugs in production that never reproduce locally.
- **Plugin on `__construct`**: plugins cannot intercept constructors (interceptors are created AFTER construction). Use a preference or a factory plugin instead.
- **Returning the wrong type in `after`**: if original returns `string`, your `after` MUST also return `string`. Returning null causes `TypeError` in strict mode.
- **Skipping `$proceed()` in `around`**: breaks Magento's plugin chaining — later plugins and the original never run, and interceptors caching is invalidated.
- **Final classes / private methods**: plugins cannot intercept `final` classes, `final` methods, or `private` methods. Check the target before writing the plugin.
