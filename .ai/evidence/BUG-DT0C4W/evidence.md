# Evidence — BUG-DT0C4W (Show Packages trống + dead layout handle)

Ngày: 2026-09-30 · Branch: development · Cross-ref: BUG-74VGQX, TASK-S52DGA, TASK-W5BW4F.

## 1. Unit suites (phpunit-secomm.xml)

| Suite | Kết quả | Delta |
|---|---|---|
| Secomm_ShippingCore | **600 OK** | +2 so với 598? — thực tế: 594 (sau TASK-S52DGA) + `FormShowPackagesPluginTest` 5 + `stripMarkers` 1 = **600** |
| Secomm_Ghn | **448 OK** | 446 + 2 (layout-anchor tests mới trong `SalesShipmentViewLayoutTest`) |
| Secomm_Cod | 48 OK (không đổi) | — |

Lưu ý: test hiện hữu `SalesShipmentViewLayoutTest` (TASK-PWHG0V) load layout file theo path —
đã cập nhật sang `adminhtml_order_shipment_view.xml` + thêm 2 test khóa (file name = real
handle; blocks anchored trong `extra_shipment_info`).

## 2. Compile + validator

- `php bin/magento setup:di:compile` — OK (bắt buộc: interceptor cho plugin mới `FormShowPackagesPlugin` — probe CHẠY TRƯỚC compile cho thấy nút chưa ẩn, đúng DI trap đã ghi trong memory).
- `bash .ai/bin/project-ai-validate --check-records --check-specs` — **56 FAIL = baseline**, 0 mới.

## 3. CLI layout probe (`probe_view_layout.php`) — 28/28 PASS

Load layout `adminhtml_order_shipment_view` thật với `current_shipment` từ DB, assert block
tồn tại + render-according-to-visibility + button:

| Shipment | State | ProviderStatus | Actions | FulfillmentStatus | Show Packages |
|---|---|---|---|---|---|
| 19 | OFFLINE (markers-only) | render (offline banner + packages) | hidden (0 row) | render (metadata) | **hidden** ✓ |
| 17 | GHN FAILED (không order code) | render (FAILED + reason) | hidden (không gì cancel/return) | hidden (no marker) | **hidden** ✓ |
| 15 | SUBMITTED, tracking CANCELLED | render | hidden (terminal per matrix) | hidden | **hidden** ✓ |
| 14 | SUBMITTED, tracking không terminal | render | **render 2648 bytes — Cancel/Return lần đầu hiện trên trang** | hidden | **hidden** ✓ |

Root-cause fix evidence: trước compile nút vẫn render (`<button ... Show Packages`) → sau
compile `html=""`. Trước rename handle: `layout has block …` sẽ FAIL (không chạy probe đó
trước khi rename — bằng chứng dead handle = grep không có gì emit/update handle
`sales_shipment_view`; vendor layout thật = `adminhtml_order_shipment_view.xml`).

## 4. Browser smoke (dành cho QC/TL — agent không có browser)

1. Mở shipment view của shipment OFFLINE (vd 19): KHÔNG còn nút "Show Packages"; thấy section
   **"Fulfillment"** (Offline / secomm_ghn / Not Created / reason / note) + **"GHN Shipment"**
   (offline banner + bảng Confirmed packages).
2. Shipment GHN SUBMITTED đang giao (vd 14): nút Show Packages vẫn ẩn (markers-only); thấy
   **nút Cancel/Return GHN** (lần đầu render — verify confirm dialog + form key + ACL).
3. Shipment có native packages (nếu tạo được qua label flow): nút + modal như core.

## 5. Files

- `Secomm/ShippingCore/Plugin/Shipping/PackagingBlockPlugin.php` — extract `stripMarkers()`.
- `Secomm/ShippingCore/Plugin/Shipping/FormShowPackagesPlugin.php` — mới.
- `Secomm/ShippingCore/etc/adminhtml/di.xml` — register plugin.
- `Secomm/{Ghn,ShippingCore}/view/adminhtml/layout/sales_shipment_view.xml` →
  `adminhtml_order_shipment_view.xml` + re-anchor `extra_shipment_info`.
- Tests: `FormShowPackagesPluginTest` (mới, 5), `PackagingBlockPluginTest` (+1),
  `SalesShipmentViewLayoutTest` (+2, path mới).
