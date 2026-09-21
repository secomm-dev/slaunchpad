# Secomm_GiaoHangNhanh — Technical Specification

> **Document Status**: Approved  
> **Author**: Technical Lead — Secomm Core Team  
> **Target Audience**: Developer, QC, DevOps  
> **Last Updated**: 2026-08-13  
> **Platform**: Magento 2.4.8-p5 | PHP 8.2–8.4 | Hyvä 3.x | Mageplaza One Step Checkout

---

## 1. Tổng Quan (Overview)

`Secomm_GiaoHangNhanh` là module tích hợp dịch vụ vận chuyển **Giao Hàng Nhanh (GHN) API v2** cho Magento 2, được xây dựng theo kiến trúc Command Pattern + Asynchronous Queue, tách biệt rõ ràng giữa:
- **Rate Calculation** (tính phí real-time tại Checkout)
- **Order Synchronization** (tạo vận đơn bất đồng bộ qua Queue)
- **Webhook Receiver** (nhận cập nhật trạng thái từ GHN)

Module phụ thuộc bắt buộc vào `Secomm_GhnAddressMapper` để chuyển đổi địa chỉ hành chính Việt Nam (Tỉnh/Quận/Phường từ `Secomm_AddressDropdown`) sang `DistrictID` và `WardCode` của GHN.

---

## 2. Carrier Codes & Phân Loại Dịch Vụ

| Carrier Class | Carrier Code | GHN Service Name | Trường Hợp Áp Dụng |
|---|---|---|---|
| `GHN\Express` | `giaohangnhanh_express` | `Chuyển phát thương mại điện tử` / Service short name: `Hàng nặng` | Kiện hàng ≤ 50kg, ≤ 200cm mỗi chiều |
| `GHN\Standard` | `giaohangnhanh_standard` | `Chuyển phát truyền thống` / Service short name: `Hàng nhẹ` | Kiện hàng ≤ 5000kg, với validation từng sản phẩm |

Cả hai lớp kế thừa từ abstract class `Secomm\GiaoHangNhanh\Model\Carrier\GHN`, triển khai `Magento\Shipping\Model\Carrier\CarrierInterface`.

### 2.1 Logic `canDisplay()` — Điều Kiện Hiển Thị Phương Thức

Mỗi carrier implement `canDisplay(RateRequest $request): bool` để filter phương thức vận chuyển phù hợp theo kích thước và trọng lượng đơn hàng:

```
Converted Mass (kg) = (length × width × height) / 5000
```

| Giới hạn | Express (Default) | Standard (Default) |
|---|---|---|
| Max Weight | 50 kg | 5.000 kg |
| Max L/W/H | 200 cm mỗi chiều | 20.000 cm mỗi chiều |
| Max Converted Mass | 200 kg | 1.600 kg |

> **Standard** thêm bước validate từng item trong giỏ hàng — sản phẩm phải có đầy đủ `weight`, `length`, `width`, `height`. Nếu thiếu bất kỳ trường nào, Standard sẽ ẩn khỏi Checkout.

---

## 3. Luồng Rate Calculation (Tính Phí Vận Chuyển)

```
Checkout Address Changed
        │
        ▼
collectRates(RateRequest)
        │
        ├── 1. Gọi CommandPool::get('get_services') → GHN API: lấy danh sách service khả dụng theo tuyến
        │
        ├── 2. canDisplay() → kiểm tra trọng lượng & kích thước đơn
        │
        ├── 3. Gọi CommandPool::get('calculate_rate') → GHN API /fee
        │         Payload: from_district_id, to_district_id, to_ward_code,
        │                  service_id, service_type_id, weight, cod_value (nếu COD)
        │
        └── 4. Trả về shippingFee sau quy đổi tiền tệ sang store currency
```

**COD handling**: Nếu phương thức thanh toán của đơn hàng nằm trong danh sách `payment_for_shipping_cod`, module tự động tính `cod_value = grand_total` (đã quy đổi sang VNĐ) và truyền vào payload tính phí.

---

## 4. Luồng Order Synchronization (Tạo Vận Đơn GHN)

### 4.1 Auto Sync: Sau Khi Khách Đặt Hàng

```
Event: checkout_onepage_controller_success_action
    │
    ▼
SalesOrderPlaceAfterObserver
    │ (Nếu auto_sync_on_place_order = Yes)
    ▼
Queue Publisher → Topic: ghn.sync.order → Queue: ghn_sync_order_queue
    │
    ▼ (Async — Consumer chạy qua Supervisor/Cron)
OrderSyncConsumer::process()
    │
    ▼
SynchronizeOrderDataBuilder::build()
    │ Payload gồm:
    │   - token, shop_id, payment_type_id, required_note
    │   - from_name, from_phone, from_address, from_ward_name, from_district_name, from_province_name
    │   - to_name, to_phone, to_address
    │   - to_district_id, to_ward_code  ← từ Secomm_GhnAddressMapper
    │   - service_id, service_type_id
    │   - weight (gram), length=1, width=1, height=1 (fallback)
    │   - items[] (name, code, qty, price, weight, width, height, length)
    │   - client_order_code (= increment_id)
    │   - cod_amount (nếu COD)
    │
    ▼
GHN API: POST /shiip/public-api/v2/shipping-order/create
    │
    ▼
Lưu tracking_code, ghn_status vào sales_order & ghn_webhook_track
```

### 4.2 Auto Sync: Khi Tạo Shipment

```
Event: sales_order_shipment_save_after
    │
    ▼
SalesShipmentSaveAfterObserver
    │ (Nếu auto_sync_on_shipment_create = Yes)
    ▼
Queue Publisher → Topic: ghn.sync.order
```

### 4.3 Manual Sync: Từ Admin Order View

Khi `enable_admin_manual_sync_button = Yes`, nút **Sync GHN** xuất hiện tại trang Admin Order View, trigger cùng Queue message trên.

### 4.4 Auto Cancel: Khi Huỷ Đơn Hàng

```
Event: order_cancel_after
    │
    ▼
SalesOrderCancelAfterObserver
    │
    ▼
Queue Publisher → Topic: ghn.cancel.order → Queue: ghn_cancel_order_queue
    │
    ▼
OrderCancelConsumer::process() → GHN API: Cancel Order
```

---

## 5. Webhook Receiver

**Endpoint**: `POST /giaohangnhanh/webhook/shippingUpdate`

Controller `Secomm\GiaoHangNhanh\Controller\Webhook\ShippingUpdate` implements `CsrfAwareActionInterface` (bypass CSRF) và `HttpPostActionInterface`.

### 5.1 Mapping Trạng Thái GHN → Magento

| GHN Status | Magento Order Status | Magento Order State |
|---|---|---|
| `ready_to_pick`, `picking`, `picked`, `transporting`, `delivering` | `picking` / `delivering` / ... | `processing` |
| `delivery_fail` | `delivery_fail` | `processing` |
| `delivered` | `complete` | `complete` |
| `cancel`, `waiting_to_return`, `return*`, `returning*`, `returned` | `canceled` | `closed` |
| `exception`, `damage`, `lost` | `exception` | — |

### 5.2 Luồng Xử Lý Webhook

1. Nhận JSON payload từ GHN.
2. Parse `tracking_code` (= GHN Order Code), `status`, `warehouse`, `total_fee`, `reason`.
3. Lookup `Magento Order` theo `tracking_code` (cột `tracking_code` trong `sales_order`).
4. Lưu bản ghi vào `ghn_webhook_track`.
5. Cập nhật `ghn_status` trên `sales_order` và `sales_order_grid`.
6. Thêm Order Status History comment.
7. Cập nhật Order State nếu status = `delivered` → State `complete`.

---

## 6. Database Schema

Module tạo các bảng và sửa đổi bảng core sau:

### Bảng riêng của module

| Bảng | Mục Đích | Các Trường Quan Trọng |
|---|---|---|
| `ghn_webhook_track` | Lưu log trạng thái vận đơn nhận từ Webhook GHN | `order_id` (FK → `sales_order`), `tracking_code`, `status_code`, `status_label`, `warehouse`, `total_fee`, `result_code`, `additional_data` |
| `secomm_giaohangnhanh_province` | Danh mục Tỉnh/Thành GHN | `province_id`, `province_name`, `region_id_mapping` |
| `secomm_giaohangnhanh_district` | Danh mục Quận/Huyện GHN | `district_id`, `province_id` (FK), `district_name` |
| `secomm_giaohangnhanh_ward` | Danh mục Phường/Xã GHN | `ward_code`, `district_id` (FK), `ward_name` |

> **Quan hệ**: Province → District → Ward (CASCADE DELETE)

### Alter bảng core

| Bảng Core | Cột Thêm | Mục Đích |
|---|---|---|
| `sales_order` | `ghn_status`, `tracking_code`, `ghn_canceling_status` | Lưu trạng thái GHN và mã vận đơn trực tiếp trên đơn hàng |
| `sales_order_grid` | `ghn_status`, `tracking_code`, `ghn_canceling_status` | Hiển thị trên lưới quản lý đơn hàng Admin |
| `quote_address` | `district`, `shipping_service_id`, `shipping_service_type_id` | Lưu District và Service ID được chọn khi tính phí |

### Extension Attributes

Module extend 4 interface với attribute `district`:
- `Magento\Checkout\Api\Data\ShippingInformationInterface`
- `Magento\Customer\Api\Data\AddressInterface`
- `Magento\Quote\Api\Data\AddressInterface`
- `Magento\Checkout\Api\Data\TotalsInformationInterface`

---

## 7. Queue Configuration

| Thông Số | Sync Order | Cancel Order |
|---|---|---|
| Topic name | `ghn.sync.order` | `ghn.cancel.order` |
| Queue name | `ghn_sync_order_queue` | `ghn_cancel_order_queue` |
| Connection | `db` | `db` |
| Max messages / run | 100 | 100 |
| Handler | `OrderSyncConsumer::process` | `OrderCancelConsumer::process` |

> **Triển khai Production**: Queue connection hiện tại là `db` (MySQL Queue). Khi hạ tầng sẵn sàng, có thể chuyển sang `amqp` (RabbitMQ) bằng cách update `queue_publisher.xml` mà không ảnh hưởng logic nghiệp vụ.

**Lệnh chạy consumer (Supervisor / systemd):**

```bash
php bin/magento queue:consumers:start ghn.sync.order
php bin/magento queue:consumers:start ghn.cancel.order
```

---

## 8. Data Patch

`Setup\Patch\Data\AddGhnOrderStatuses` — Tạo các Magento Order Status tương ứng với trạng thái GHN khi module được cài đặt lần đầu (chạy tự động qua `bin/magento setup:upgrade`).

---

## 9. Admin System Configuration

**Path**: Stores → Configuration → Sales → Delivery Methods → **GHN**

### General Settings (`giaohangnhanh_setting/general/`)

| Field | Config Path | Mô Tả |
|---|---|---|
| Sandbox Mode | `sandbox_flag` | Bật môi trường Sandbox |
| API Token | `api_token` | Token mã hoá (backend: Encrypted) |
| Shop ID | `shop_id` | ID cửa hàng GHN |
| Payment Type | `payment_type` | `1` = Seller trả cước; `2` = Buyer trả cước (COD) |
| Note Code | `note_code` | `CHOHANTUCHUYEN` / `CHOXEMHANGKHONGTHU` / `KHONGCHOXEMHANG` |
| Store District | `district` | Quận/Huyện kho xuất hàng |
| URL Sandbox | `giaohangnhanh_sandbox_url` | Endpoint Sandbox GHN |
| URL Production | `giaohangnhanh_url` | Endpoint Production GHN |
| Payment Shipping COD | `payment_for_shipping_cod` | Danh sách payment method tính COD amount |
| Auto Sync on Place Order | `auto_sync_on_place_order` | Tự động đẩy queue sau Place Order |
| Auto Sync on Shipment Create | `auto_sync_on_shipment_create` | Tự động đẩy queue khi tạo Shipment |
| Enable Admin Manual Sync | `enable_admin_manual_sync_button` | Nút "Sync GHN" trong Order View |
| Debug | `debug` | Ghi log API ra `var/log/` |

### Advanced Settings — Express (`carriers/giaohangnhanh_express/`)

`active`, `name`, `title`, `sort_order`, `sallowspecific`, `specificcountry`, `showmethod`, `specificerrmsg`, `maximum_weight` (gram), `maximum_length/width/height` (cm), `maximum_converted_mass_order` (gram)

### Advanced Settings — Standard (`carriers/giaohangnhanh_standard/`)

Bao gồm tất cả các field của Express, **cộng thêm** giới hạn per-item: `maximum_weight_item`, `maximum_length_item`, `maximum_width_item`, `maximum_height_item`, `maximum_converted_mass_item`

---

## 10. Known Issues & Technical Debt

| # | Vấn Đề | Mức Độ | Ghi Chú |
|---|---|---|---|
| 1 | `length`, `width`, `height` trong payload tạo đơn GHN được hardcode = `1` | Medium | Dimension thực tế bị mất do `Secomm_ShippingDimensions` đã bị loại bỏ. Cần implement lại logic tính tổng kích thước từ các item. |
| 2 | ~~Develop Mode hardcode địa chỉ kho = "Phường 17, Quận Phú Nhuận, HCM" trong `SynchronizeOrderDataBuilder`~~ **ĐÃ XÓA (BUG-JBX3H9, 2026-09-08)** | — | Toàn bộ fake-location fallback (1456/21511, 1457/21715, "Phường 17…") + config `is_develop_mode` đã bị loại bỏ — mapping unavailable ⇒ fail closed (`GhnLocationMappingException`), rate → GHN method unavailable; sync → fail + log. |
| 3 | `queue_consumer.xml` dùng `connection="db"` (MySQL Queue) | Low | Phù hợp môi trường hiện tại. Cần chuyển `amqp` trước khi đạt tải cao Production. |

---

## 11. Escalation & Change Control

Theo quy định dự án tại [`.ai/AGENTS.md`](file:///var/www/Secomm/SECOMM-Internal/LaunchPad-docker/src/.ai/AGENTS.md) (§11 & §12):

| Thay Đổi | Tier | Người Duyệt | Lý Do |
|---|---|---|---|
| Logic tính phí / payload API GHN (`/fee`, `/create`) | **Tier 2** | SA / CTO | Ảnh hưởng trực tiếp đến doanh thu và trải nghiệm checkout |
| Webhook mapping GHN Status → Magento Order State | **Tier 2** | SA / TL | Rủi ro order data integrity, state transition |
| DB Schema (`db_schema.xml`) | **Tier 2** | SA | Migration không rollback được |
| Observer events, Queue topics | **Tier 1** | TL | Ảnh hưởng vòng đời đơn hàng |
| Admin Config / Log / UI | Tier 1 | TL | Low risk |

---

## 12. Changelog

| Phiên Bản | Ngày | Nội Dung |
|---|---|---|
| v1.1.0 | 2026-08-13 | Loại bỏ dependency `Secomm_ShippingDimensions` không tồn tại. Refactor `SynchronizeOrderDataBuilder`: gán fallback dimensions (`length=width=height=1`). Làm sạch code comment legacy. Cập nhật tài liệu kỹ thuật chuẩn TL. |
| v1.0.0 | — | Phiên bản khởi tạo ban đầu (tích hợp GHN API v2, Queue sync, Webhook). |
