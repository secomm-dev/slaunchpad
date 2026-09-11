# Secomm_Pancake

Pancake POS **vendor logic** for offline fulfillment (SLP-30):

- HTTP client (`PosClient`)
- Create-order payload builder
- Inbound order JSON parser (poll GET + webhook `WebhookOrderResponse`)
- Status mapper / status catalog
- Warehouse catalog API
- `PosApiConfigInterface` + `ServiceCode`

Magento admin, cron, CLI, and **webhook endpoint** live in **`Secomm_PancakeBridge`**. Config is injected via `PosApiConfigInterface`.

Inbound: Bridge webhook receives POS POSTs; Bridge poll/CLI still GET orders. Parser is shared. See Bridge README for webhook ops setup.

Does **not** depend on ShippingCore / Ghtk / Ahamove.
