# Create Magento 2 Cron Job

## Purpose
Use this skill to schedule recurring background work — syncing inventory to an ERP, cleaning stale carts, sending reminder emails, generating reports. Cron runs off the web request via the Magento cron runner.

## Prerequisites
- Read `AGENTS.md` Section 7.2 — crontab.xml registration, PSR-3 logging
- Read `project-context/05-conventions.md` for cron group convention (default vs index vs custom)
- A module already created (use `create-module`)
- The Magento cron configured on the server (`bin/magento cron:run` or system crontab)

## Input
- **Job name** (e.g. `acme_storepickup_lowstock_sync`)
- **Schedule** (cron expression or config path)
- **Group** (`default`, `index`, or custom)
- **What it does** (e.g. push low-stock products to ERP)

## Schedule Expression Guide
5-field cron: `minute hour day-of-month month day-of-week`

| Expression | Meaning |
|------------|---------|
| `* * * * *` | every minute |
| `*/5 * * * *` | every 5 minutes |
| `0 * * * *` | every hour at :00 |
| `0 */2 * * *` | every 2 hours |
| `0 2 * * *` | daily at 02:00 |
| `0 0 * * 1` | every Monday 00:00 |
| `0 9,21 * * *` | twice daily at 09:00 and 21:00 |
| `30 1 * * *` | daily at 01:30 |

Field order is minute, hour, day-of-month, month, day-of-week. A common error is swapping minute and hour.

## Generated Files
- `Cron/LowStockSync.php` (the job class)
- `etc/crontab.xml` (registration)
- `etc/config.xml` (optional default schedule)
- `etc/adminhtml/system.xml` (optional admin-configurable schedule)

## Step-by-Step

### Step 1: Create the cron job class
Keep one job per class, with try/catch around the body and PSR-3 logging. Never let a cron throw — an uncaught exception marks the job as failed and can block the cron group.

```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\Cron;

use Acme\StorePickup\Api\LowStockPublisherInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class LowStockSync
{
    /** Config path for the low-stock threshold (set in system.xml) */
    public const XML_PATH_THRESHOLD = 'acme_storepickup/lowstock/threshold';

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly StoreManagerInterface $storeManager,
        private readonly LowStockPublisherInterface $publisher,
        private readonly LoggerInterface $logger,
        private readonly \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Find products below the threshold and publish them to the ERP sync queue.
     * Invoked by the Magento cron runner — must never throw.
     */
    public function execute(): void
    {
        $started = microtime(true);
        $threshold = (int) $this->scopeConfig->getValue(
            self::XML_PATH_THRESHOLD,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ?: 5;

        $this->logger->info('Acme low-stock sync: started', ['threshold' => $threshold]);

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['sku', 'name', 'type_id'])
                ->addAttributeToFilter('status', ['eq' => 1]);

            $synced = 0;
            foreach ($collection as $product) {
                try {
                    $stockItem = $this->stockRegistry->getStockItem($product->getId());
                    $qty = (float) $stockItem->getQty();

                    if ($stockItem->getManageStock() && $qty <= $threshold) {
                        $this->publisher->publish([
                            'sku' => $product->getSku(),
                            'name' => $product->getName(),
                            'qty' => $qty,
                            'synced_at' => date('c'),
                        ]);
                        $synced++;
                    }
                } catch (\Throwable $itemError) {
                    // Per-item failure must not abort the whole run.
                    $this->logger->warning('Acme low-stock sync: product failed', [
                        'product_id' => $product->getId(),
                        'error' => $itemError->getMessage(),
                    ]);
                }
            }

            $elapsed = round(microtime(true) - $started, 2);
            $this->logger->info('Acme low-stock sync: completed', [
                'synced' => $synced,
                'elapsed_sec' => $elapsed,
            ]);
        } catch (\Throwable $e) {
            // Catch-all so the cron group keeps running other jobs.
            $this->logger->error('Acme low-stock sync: fatal error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
```

### Step 2: Register in `etc/crontab.xml`
Two ways to set the schedule: inline `schedule`, or `config_path` (configurable from admin via `system.xml`). Prefer `config_path` for tunable jobs.

```xml
<?xml version="1.0"?>
<!--
/**
 * Copyright © Acme. All rights reserved.
 */
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">
    <group id="default">
        <!-- Hourly sync, schedule configurable from admin -->
        <job name="acme_storepickup_lowstock_sync"
             instance="Acme\StorePickup\Cron\LowStockSync"
             method="execute">
            <config_path>crontab/default/jobs/acme_storepickup_lowstock_sync/schedule</config_path>
        </job>
    </group>
</config>
```

### Step 3: Default schedule in `etc/config.xml`
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <crontab>
            <default>
                <jobs>
                    <acme_storepickup_lowstock_sync>
                        <schedule>0 * * * *</schedule>
                    </acme_storepickup_lowstock_sync>
                </jobs>
            </default>
        </crontab>
    </default>
</config>
```

### Step 4: Admin-configurable schedule in `system.xml`
`etc/adminhtml/system.xml`:
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <tab id="acme" translate="label" sortOrder="200">
            <label>Acme</label>
        </tab>
        <section id="acme_storepickup" translate="label" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="1">
            <label>Store Pickup</label>
            <tab>acme</tab>
            <resource>Acme_StorePickup::config</resource>
            <group id="lowstock" translate="label" sortOrder="10" showInDefault="1" showInWebsite="0" showInStore="0">
                <label>Low-Stock Sync</label>
                <field id="threshold" translate="label" type="text" sortOrder="10" showInDefault="1" showInWebsite="0" showInStore="0">
                    <label>Low-Stock Threshold</label>
                    <validate>validate-number</validate>
                </field>
            </group>
        </section>
    </system>
</config>
```

### Step 5: Apply config and run
```bash
bin/magento cache:clean config
# Run the cron group manually to test immediately:
bin/magento cron:run --group=default
# Or run the single job via the cron runner (Magento routes by job name):
bin/magento cron:run
```

## Coding Rules Applied
- **`crontab.xml` registration required** (AGENTS.md 7.2): a `Cron/*.php` class without `<job>` in `crontab.xml` never runs
- **PSR-3 logging**: `LoggerInterface` injected; log start, per-item failures, completion, and elapsed time
- **try/catch around the whole `execute()`**: a thrown exception marks the job as error and can stall the cron group's other jobs
- **Correct group**: `default` for general jobs, `index` only for indexers, custom group requires `cron_groups.xml`

## Verification
- [ ] `bin/magento cron:run --group=default` completes with no error in `var/log/magento.cron.log`
- [ ] `bin/magento cron:run` shows the job in the log; check `cron_schedule` table:
  ```sql
  SELECT * FROM cron_schedule WHERE job_code = 'acme_storepickup_lowstock_sync' ORDER BY scheduled_at DESC LIMIT 5;
  ```
  Status should be `success` after a run
- [ ] `tail -f var/log/debug.log` shows "Acme low-stock sync: started" / "completed" with `synced` count
- [ ] Manually drop a product's stock qty below the threshold → next run publishes it
- [ ] Force an exception (disconnect ERP) → job status is `error` in `cron_schedule`, error message in log, but other jobs in the group still run

## Common Mistakes
- **Wrong group name**: putting a heavy sync in `index` (shares the indexer lock and stalls reindex) or inventing a group name without a `cron_groups.xml` definition. Use `default` unless you have a specific reason and have defined a custom group.
- **Heavy logic without logging**: when the job fails you have no idea why. Always log start, end, counts, and per-item errors.
- **Not registering in `crontab.xml`**: the `Cron/LowStockSync.php` class sits there forever and never executes. The `<job>` element with the right `instance` and `method` is what wires it.
- **Wrong cron expression field order**: writing `* 0 * * *` thinking "hourly" actually fires every minute during the 0th hour (a flood). Hourly at :00 is `0 * * * *`.
- **Throwing out of `execute()`**: skips the success record, leaves the job in `running` forever in some versions, and can block subsequent runs. Wrap everything.
- **Using `config_path` but forgetting the default in `config.xml`**: the job silently never schedules because the path is empty. Always pair `config_path` with a `<default>` value.
- **Long-running job in `default` group**: blocks email/newsletter/sitemap crons. Move jobs >60s to a custom group with its own `cron_groups.xml`.
