# Secomm_Pancake

Pancake POS vendor logic for offline fulfillment (SLP-30):

- HTTP client (`PosClient`)
- Create-order payload builder
- Inbound order JSON parser
- Status mapper and status catalog
- Warehouse catalog API

Depends on `Secomm_FulfillmentCore` APIs. Magento admin, cron, CLI, webhook, and configuration wiring live in the separate `Secomm_PancakeBridge` module. `Secomm_Pancake` must not depend on the Bridge.

Configuration is injected through `PosApiConfigInterface`, implemented by the Bridge Magento configuration class.

`Secomm_PancakeFunction` is deprecated and its vendor logic was merged into this module in version 0.4.0.

Does not depend on `Secomm_ShippingCore`, Ghtk, or Ahamove.
