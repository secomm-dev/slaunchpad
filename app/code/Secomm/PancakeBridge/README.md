# Secomm_PancakeBridge

Magento wiring layer for Pancake POS integration.

## Role

`Secomm_PancakeBridge` connects Magento admin, cron, CLI, webhook, and FulfillmentCore pools to the vendor logic in `Secomm_Pancake`.

- **Pancake** — POS API client, payload builder, inbound parser, status/warehouse catalogs, status mapper.
- **PancakeBridge** (this module) — Magento config UI, ACL, routes, warehouse/status admin grids, export/inbound registration, cron poll, webhook endpoint.

## Dependencies

- `secomm/module-pancake`
- `secomm/module-fulfillment-core`

## DI wiring

- `PosApiConfigInterface` → `PancakeConfig`
- `ExporterPool` → `PancakeOrderExporter`
- `MapperPool` → `Pancake\Model\Mapping\PancakeStatusMapper`
- `FulfillmentLogger` gates → `PancakeLogConfig`
- CLI `secomm:pancake:poll` → `PollOrdersCommand`

## Inbound paths (keep both)

| Path | Role |
|------|------|
| **Webhook** | Pancake POS POSTs order updates immediately when status/carrier/tracking changes |
| **Poll** | Cron/CLI `GET` Magento-origin exports every ~15 minutes (fallback if webhook misses) |

Do **not** disable poll when enabling webhook.

## Webhook configuration (Pancake → Magento)

Contract: Pancake POS Open API — `PUT /shops/{SHOP_ID}` (Webhook configuration), type **`orders`**, payload `WebhookOrderResponse`.

### 1. Magento

1. Enable `Secomm_Pancake` + `Secomm_PancakeBridge` (keep `Secomm_PancakeFunction` disabled if present).
2. Admin → **Stores → Configuration → Secomm → Pancake POS**:
   - General: Enable export + Enable log as needed
   - API: Base URL, Shop ID, API Key
   - **Webhook → Webhook secret**: invent a long random string (encrypted). Do not leave empty on a public storefront.
3. Note the storefront URL (replace host):

```text
https://<your-magento-host>/pancake/webhook/index?secret=<your-webhook-secret>
```

### 2. Pancake POS

1. Open **Cấu hình → Nâng cao → Kết nối bên thứ 3 → Webhook/API**  
   (EN: Setting → Advance → Third-party connection → Webhook/API).
2. Enable webhook.
3. Set **Webhook URL** to the Magento URL above (include `?secret=...`).
4. Select webhook type **`orders`** (required for status / carrier / tracking sync).
5. Optionally set error email / request headers.
6. Save.

You can also set the same fields via OpenAPI `PUT /shops/{SHOP_ID}` (`webhook_enable`, `webhook_url`, `webhook_types`).

### 3. Behaviour

- Magento applies updates **only** for Magento-origin rows in `secomm_fulfillment_export` (orders pushed from Magento). Manual POS-only orders are ignored.
- Matching: POS order `id` → `external_order_id`, fallback `custom_id` → Magento `increment_id`.
- Updates flow through FulfillmentCore (comment, status map, timeline, invoice/shipment when mapped) — same as poll.
- Logs (when Enable log = Yes): `var/log/fulfillment/pancake/fulfillment.log` — no `api_key`, no raw phones.

### 4. Quick check

1. Place/export a Magento order to POS.
2. Change status or tracking on Pancake.
3. Magento order gets a Pancake comment / status update without waiting for poll.
4. `bin/magento secomm:pancake:poll --force` still works as fallback.

## Note

Enable both `Secomm_Pancake` and `Secomm_PancakeBridge`. Keep the deprecated `Secomm_PancakeFunction` stub disabled.
