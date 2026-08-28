# Secomm_Pancake

Pancake POS adapter (SLP-30 / PNC-001..004). One Magento module: HTTP client,
create-order exporter, inbound poll/webhook, `PancakeStatusMapper`, and
**warehouse mapping dashboard** (MSI Source ↔ Pancake warehouse).
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

## Configuration

Stores → Configuration → Secomm Extensions → Pancake POS:

- Enable (default No)
- Base URL (`https://pos.pages.fm/api/v1`)
- Shop ID
- API Key (encrypted; query `api_key`; never logged)
- Webhook secret (encrypted)
- Poll Magento-origin orders (default Yes)

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --no-extensions app/code/Secomm/Pancake/Test/Unit
```
