# Secomm_PancakeFunction

Pancake POS **vendor functions** for offline fulfillment (SLP-30):

- HTTP client (`PosClient`)
- Create-order payload builder
- Inbound order JSON parser
- Status mapper / status catalog
- Warehouse catalog API

Depends on `Secomm_FulfillmentCore` APIs. Magento admin/cron/webhook wiring lives in a separate Bridge Magento module (this Function module must not depend on it).

Config is injected via `PosApiConfigInterface` (implemented by the Bridge Magento config class).

Does **not** depend on `Secomm_ShippingCore` / Ghtk / Ahamove.
