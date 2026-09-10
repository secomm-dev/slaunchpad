# Secomm_Pancake

Pancake POS adapter (SLP-30 / PNC-001..004). One Magento module: HTTP client,
create-order exporter, inbound poll/webhook, `PancakeStatusMapper`,
**warehouse mapping** (MSI Source ↔ Pancake warehouse), and
**status mapping** (Pancake status code ↔ NormalizedFulfillmentStatus).
Does **not** sequence or call `Secomm_ShippingCore` / `Secomm_Ghtk` / `Secomm_Ahamove`.

## Purpose

Push Magento-origin orders to `POST /shops/{SHOP_ID}/orders` with the correct
`warehouse_id` so invite admins only see their warehouse orders. Status/carrier/tracking
return through `Secomm_FulfillmentCore` applier only.

## Defaults (SA)

- Q4 `increment_id` → payload `custom_id` (+ `note`)
- Address: `full_address` text when Pancake geo ids unknown
- Inbound: poll GET order for **mapped** rows + optional `POST /pancake/webhook/index?secret=`
- Tracking: fulfillment state + comment (no Magento native Track)
- Warehouse (2026-08-20): 1 MSI ↔ 1 Pancake warehouse per service; unmapped export fails
  `warehouse_unmapped`; multi-source → primary/first + warning

## Admin

Secomm → Pancake POS → Warehouse Mapping — maps MSI sources to POS warehouses
(`GET /shops/{SHOP_ID}/warehouses`). Rows are hard-scoped to `service_code=pancake`.

Secomm → Pancake POS → Status Mapping — admin Add Mapping grid (same pattern as
Warehouse Mapping) on core table `secomm_fulfillment_status_map`:

- Magento Order Status ↔ Pancake Status Code
- Hard-scoped to `service_code=pancake`
- Active map → create Magento invoice/shipment if needed, then Magento order status + comment
- No active map → comment only

Timeline/normalized milestones still use `PancakeStatusMapper` (code), not this table.

## Configuration

Stores → Configuration → Secomm Extensions → Pancake POS:

- Enable (default No)
- Base URL (`https://pos.pages.fm/api/v1`)
- Shop ID
- API Key (encrypted; query `api_key`; never logged)
- Webhook secret (encrypted)
- Poll Magento-origin orders (default Yes)
- Enable log (default No) → `var/log/fulfillment/pancake/fulfillment.log`

## Cron group `secomm_pos` (server)

Jobs do **not** run under `--group=default`. Production OS crontab must include:

```cron
* * * * * www-data cd /var/www/slaunchpad && /usr/bin/php bin/magento cron:run --group=secomm_pos >> /var/log/magento.cron.secomm_pos.log 2>&1
```

Adjust user, Magento root, and `php` path to the server. Keep Magento’s usual `default` / `index` lines as well.

Admin checklist before expecting updates:

1. Pancake POS → Enable = Yes  
2. Pancake POS → Poll Magento-origin POS orders = Yes  
3. Pancake POS → Enable log = Yes (debug: `var/log/fulfillment/pancake/fulfillment.log`)  
4. Fulfillment Core → Enable outbound order export = Yes  
5. Export row `push_status=success` and `external_order_id` filled  
6. Change status on Pancake to a **new** code — same status + same tracking yields `same event_id` and **no new comment**

`cron_schedule.status=success` only means PHP finished without exception. It can still skip comment when config is off or event_id is unchanged.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --no-extensions app/code/Secomm/Pancake/Test/Unit
```
