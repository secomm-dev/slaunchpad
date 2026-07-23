# Create Magento 2 Event Observer

## Purpose
Use this skill to react to Magento events for side effects — logging, syncing to an external system, sending notifications, queuing background work. Observers are for **event-driven side effects only**, never for modifying the return value of a core method (use a plugin for that).

## Prerequisites
- Read `AGENTS.md` Section 7.2 — observers for side effects ONLY, not for modifying core flow
- Read `project-context/05-conventions.md` for the observer folder convention
- Confirm the event exists by grepping `vendor/magento/module-*/etc/events.xml` or `vendor/magento/module-*/etc/{area}/events.xml`
- A module already created (use `create-module` first)

## Input
- **Event name** (e.g. `checkout_cart_product_add_after`)
- **Scope** (`global`, `frontend`, `adminhtml`)
- **What to read from the event** (which keys the dispatcher attaches)
- **Side effect** (log, sync, notify, enqueue)

## Generated Files
- `Observer/{Area}/{EventName}Observer.php`
- `etc/{area}/events.xml`

## Step-by-Step

### Step 1: Find the event and its payload keys
Search the Magento source for where the event is dispatched to learn the data keys.

```bash
grep -rn "dispatch('checkout_cart_product_add_after'" vendor/magento/module-checkout/
```

Result shows something like:
```php
$this->_eventManager->dispatch(
    'checkout_cart_product_add_after',
    ['quote_item' => $quoteItem, 'product' => $product]
);
```
So the payload keys are `quote_item` and `product`.

### Step 2: Create the observer class
Implement `\Magento\Framework\Event\ObserverInterface`. Keep logic light — observers run synchronously in the request flow.

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Observer\Frontend\Checkout;

use Acme\StorePickup\Model\Inventory\StorePickupReservation;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Psr\Log\LoggerInterface;

class CartAddAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly StorePickupReservation $reservation,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Sync the added quote item to the pickup reservation service.
     * Side effect only — never throw or change the cart return value here.
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var QuoteItem $quoteItem */
            $quoteItem = $observer->getEvent()->getData('quote_item');
            /** @var ProductInterface $product */
            $product = $observer->getEvent()->getData('product');

            if ($quoteItem === null || $product === null) {
                return;
            }

            $sku = (string) $product->getSku();
            $qty = (float) $quoteItem->getQty();
            $quoteId = (int) $quoteItem->getQuoteId();

            $this->reservation->hold($sku, $qty, $quoteId);

            $this->logger->info('Store pickup reservation held', [
                'sku' => $sku,
                'qty' => $qty,
                'quote_id' => $quoteId,
            ]);
        } catch (\Throwable $e) {
            // NEVER let an observer exception break the customer's checkout.
            // Log and move on — the failure is a side-effect failure, not a cart failure.
            $this->logger->error('Store pickup reservation failed on cart add', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
```

### Step 3: Register in `events.xml` (choose the correct scope)

**Frontend** — `etc/frontend/events.xml` (runs only on storefront):

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="checkout_cart_product_add_after">
        <observer name="acme_storepickup_cart_add_after"
                  instance="Acme\StorePickup\Observer\Frontend\Checkout\CartAddAfterObserver"
                  shared="false"/>
    </event>
</config>
```

**Adminhtml** — `etc/adminhtml/events.xml` (e.g. react to `sales_model_service_quote_submit_before`):

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="sales_model_service_quote_submit_before">
        <observer name="acme_storepickup_order_submit_before"
                  instance="Acme\StorePickup\Observer\Adminhtml\OrderSubmitBeforeObserver"
                  shared="false"/>
    </event>
</config>
```

**Global** — `etc/events.xml` (runs in BOTH frontend and admin, e.g. `catalog_product_save_after`):

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="catalog_product_save_after">
        <observer name="acme_storepickup_product_save_after"
                  instance="Acme\StorePickup\Observer\Catalog\ProductSaveAfterObserver"
                  shared="false"/>
    </event>
</config>
```

Use `shared="false"` when the observer holds request-scoped state (most do). It creates a fresh instance per dispatch, avoiding stale data when the same observer fires multiple times in a request.

### Step 4: Compile and verify

```bash
bin/magento setup:di:compile
bin/magento cache:clean
```

## Coding Rules Applied
- **Observers for side effects ONLY** (AGENTS.md 7.2): logging, sync, notifications, queue dispatch — not for changing a method's return value (use a plugin for that)
- **Never throw out of an observer**: wrap logic in try/catch; an unhandled exception breaks the originating flow (e.g. cart add, order place)
- **Correct scope per area**: register in `frontend/events.xml`, `adminhtml/events.xml`, or global `events.xml` — never in a route-specific file
- **Confirm the event exists** by reading the dispatch call before wiring an observer

## Verification
- [ ] `bin/magento setup:di:compile` succeeds
- [ ] Add a product to cart on the frontend → `var/log/debug.log` (or your log channel) contains "Store pickup reservation held"
- [ ] `tail -f var/log/debug.log` while adding to cart shows the log line in real time
- [ ] Observer does NOT appear in `bin/magento module:status` errors
- [ ] Force a failure (e.g. disconnect the sync service) → cart add still succeeds, only the error is logged

## Common Mistakes
- **Registering in the wrong scope**: putting a frontend-only event in `etc/events.xml` (global) means it also runs during admin actions, often double-firing or running under a different session context. Use `etc/frontend/events.xml` for storefront events.
- **Using an observer to change a return value**: `checkout_cart_product_add_after` fires AFTER `addProduct` returns its value — your observer cannot change what the customer sees. Use a `before`/`after` plugin on the cart method instead.
- **Heavy logic blocking the request**: a 500ms ERP sync inside `checkout_cart_product_add_after` makes every add-to-cart feel slow. Move slow work to a message queue (`Magento\Framework\MessageQueue\PublisherInterface`) and let the observer just publish.
- **Reading `$observer->getProduct()` instead of `getData('product')`**: Magento's magic getters work but silently return null on key mismatch. `getData('key')` is explicit and surfaces typos in debugging.
- **Forgetting `shared="false"`**: the same observer instance is reused across dispatches in a single request; if you cached `$currentQuote` on it, the second event reads stale data.
- **Typo in `instance=` path**: a wrong namespace silently disables the observer (no error, just no effect). Verify with `bin/magento setup:di:compile` which fails on bad class refs.
