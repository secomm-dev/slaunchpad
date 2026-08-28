# Evidence: BUG-EFWXPA (SLP-120)

- **Work Item**: `BUG-EFWXPA`
- **External Ref**: `SLP-120`
- **Date**: 2026-08-27
- **Environment**: Docker container `launchpad-docker-phpfpm-1` (Magento 2.4.8-p5, PHP 8.3-FPM, MySQL 8.4)

---

## 1. Syntax Check Evidence

```bash
docker exec launchpad-docker-phpfpm-1 php -l app/code/Secomm/Ahamove/Plugin/Adminhtml/AddPushAhamoveOrderButtonPlugin.php
# Output: No syntax errors detected in app/code/Secomm/Ahamove/Plugin/Adminhtml/AddPushAhamoveOrderButtonPlugin.php

docker exec launchpad-docker-phpfpm-1 php -l app/code/Secomm/Ahamove/Controller/Adminhtml/Order/PushAhamove.php
# Output: No syntax errors detected in app/code/Secomm/Ahamove/Controller/Adminhtml/Order/PushAhamove.php

docker exec launchpad-docker-phpfpm-1 php -l app/code/Secomm/Ahamove/Command/CreateShipment.php
# Output: No syntax errors detected in app/code/Secomm/Ahamove/Command/CreateShipment.php

docker exec launchpad-docker-phpfpm-1 php -l app/code/Secomm/Ahamove/Helper/Data.php
# Output: No syntax errors detected in app/code/Secomm/Ahamove/Helper/Data.php
```

---

## 2. Order #48 Shipment Creation & State Sync Verification

```php
// Test script executing shipment creation, track addition, and state sync:
$order = $orderRepo->get(48);
$items = [];
foreach ($order->getAllItems() as $item) {
    if ($item->getIsVirtual() || $item->getQtyToShip() <= 0) continue;
    $items[$item->getItemId()] = $item->getQtyToShip();
}
$tracks = [[
    "carrier_code" => "ahamove_express",
    "number" => "26082715URUE",
    "title" => "Ahamove Express Delivery",
    "description" => "https://expressstg.ahamove.com/s/26082715URUE",
]];

$shipment = $shipmentFactory->create($order, $items, $tracks);
$shipment->register();
$shipmentRepo->save($shipment);
$orderRepo->save($order);
```

### Result Output:
```text
SUCCESS: Shipment #000000021 created and saved for Order #48!
Order #48 new state=new, status=vietqr_pending, canShip=0
```

---

## 3. Plugin Verification on Existing Shipment Without Tracking (Order #47)

```text
Testing AddPushAhamoveOrderButtonPlugin on Order #47:
  - shippingMethod: ahamove_standard_ahamove_standard
  - manualButton: true
  - isCanceled: false
  - isHolded: false
  - state: complete
  - canShip: false
  - hasShipments: true
  - shipments count: 1
    shipment id: 20
  - hasTracking: false
Result: Button will be ADDED!
```

---

## 4. Cache Flush Evidence

```text
Flushed cache types:
config, layout, block_html, collections, reflection, db_ddl, compiled_config, eav, customer_notification, config_integration, config_integration_api, graphql_query_resolver_result, full_page, config_webservice, translate, elasticsuite, magewire, secomm_ai_discoverability, secomm_ghn_address_mapper
```
