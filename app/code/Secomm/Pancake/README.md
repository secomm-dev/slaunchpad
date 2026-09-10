# Secomm_Pancake — DEPRECATED

This module is **deprecated** and retained only as an empty registration stub.

## Use instead

- **Secomm_PancakeFunction** — POS API client, payload builder, inbound parser, status/warehouse catalogs, status mapper.
- **Secomm_PancakeBridge** — Magento wiring (admin UI, cron, CLI, webhook, FulfillmentCore pools).

## Action required

Disable this module and enable Bridge + Function:

```bash
php bin/magento module:disable Secomm_Pancake
php bin/magento module:enable Secomm_PancakeFunction Secomm_PancakeBridge
php bin/magento setup:upgrade
php bin/magento cache:flush
```
