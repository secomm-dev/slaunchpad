# Evidence — BUG-74VGQX (marker secomm_physical crash admin packaging view)

Ngày: 2026-09-30 · Mode C · read-side plugin trong Secomm_ShippingCore

## 1. Reproduce (before fix)

Trace do user cung cấp (live trên shipment entity 17, order 21, increment 2000000002):

```
TypeError: Magento\Framework\DataObject::__construct(): Argument #1 ($data) must be of type
array, null given, called in vendor/magento/module-shipping/view/adminhtml/templates/order/
packaging/packed.phtml on line 16
#0 packed.phtml(16): DataObject->__construct()      // new DataObject($package->getParams())
#16 .../module-shipping/view/adminhtml/templates/view/form.phtml(176): getChildHtml()
```

DB `launchpad` — 7 shipments có packages chứa marker, thiếu `params`:

| entity_id | order_id | increment_id | created_at | packages |
|---|---|---|---|---|
| 17 | 21 | 2000000002 | 2026-09-30 10:57:41 | `{"secomm_physical":[[48600,300,300,300]]}` |
| 16 | 20 | 000000015 | 2026-09-16 | `{"secomm_physical":[[4500,40,30,20]]}` |
| 15 | 19 | 000000014 | 2026-09-16 | tương tự |
| 14 | 18 | 000000013 | 2026-09-15 | 2 packages |
| 13 | 17 | 000000012 | 2026-09-15 | tương tự |
| 12 | 13 | 000000011 | 2026-09-15 | tương tự |
| 11 | 14 | 2000000001 | 2026-09-15 | tương tự |

Query: `SELECT ... FROM sales_shipment WHERE packages NOT LIKE '%params%'` (kèm `NOT IN ('','[]','a:0:{}')`).

## 2. Fix

- `Plugin\Shipping\PackagingBlockPlugin::afterGetPackages` — unset `ShipmentPhysicalPersister::PACKAGES_KEY` khỏi result; non-array pass-through. Wire qua `etc/adminhtml/di.xml` (admin-only).
- Docblock `ShipmentPhysicalPersister` — amendment claim "collision-safe by construction" (chỉ đúng write path).
- Không đổi data model, không migrate.

## 3. Unit tests

`vendor/bin/phpunit --bootstrap dev/tests/unit/framework/bootstrap.php app/code/Secomm/ShippingCore/Test/Unit`

- `PackagingBlockPluginTest` — **OK (4 tests, 6 assertions)**: marker-only → `[]`; mixed giữ native + drop marker; không marker pass-through; non-array pass-through.
- Full suite ShippingCore — **OK (555 tests, 1448 assertions)**.

## 4. Compile

`php bin/magento setup:di:compile` → `Generated code and dependency injection configuration successfully.`
(plugin mới bắt buộc compile — cache:clean không đủ.)

## 5. End-to-end code-level verify (CLI, area adminhtml, shipment entity 17)

```
raw model keys:        ["secomm_physical"]
block getPackages:     []
persister read():      snapshot OK, totalWeightG=48600
```

→ Marker còn nguyên trên model (retry replay OK), block display data sạch (template foreach rỗng — hết TypeError), `read()` vẫn trả snapshot.

## 6. Validator

`bin/project-ai-validate --check-records --check-specs` — 56 FAIL, **giống hệt khi stash toàn bộ thay đổi** (baseline pre-existing: plans cũ thiếu Specification reference…). BUG-74VGQX không gây fail mới.

## 7. Còn lại cho QC/TL (cần admin login trên browser)

- AC-001 phần UI: mở admin shipment view shipment entity 17 → page 200, packed window rỗng.
- AC-003: click printPackage trên shipment marker-only → PDF không crash (cùng `getPackages()` đã verify ở mục 5).
