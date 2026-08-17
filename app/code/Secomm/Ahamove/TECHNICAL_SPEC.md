# Secomm_Ahamove — Technical Specification

> **Document Status**: Approved  
> **Author**: Technical Lead — Secomm Core Team  
> **Target Audience**: Developer, QC, DevOps  
> **Last Updated**: 2026-08-13  
> **Platform**: Magento 2.4.8-p5 | PHP 8.2–8.4 | Hyvä 3.x | Mageplaza One Step Checkout

---

## 1. Tổng Quan (Overview)

`Secomm_Ahamove` là module tích hợp dịch vụ vận chuyển **Ahamove API v3** cho Magento 2, hỗ trợ giao hàng tức thì trong nội thành (On-demand delivery) với các loại hình phương thức (Xe máy, Xe bán tải, Giao 2H).

Module đảm nhận các vai trò chính:
- **Rate Calculation & Estimation** (ước tính cước phí nhiều dịch vụ cùng lúc qua `/v3/orders/estimates`).
- **Shipment Creation** (tạo đơn vận chuyển Ahamove qua `/v3/orders` khi tạo Shipment hoặc Auto-Push).
- **Webhook Receiver** (nhận webhook cập nhật trạng thái đơn hàng từ Ahamove lưu vào `ahamove_order_status` & phát thông báo khi có lỗi).
- **City & Master Data Management** (đồng bộ danh mục thành phố qua CLI command).

---

## 2. Carrier Codes & Phân Loại Dịch Vụ

Module đăng ký 2 Carrier với Magento:

| Carrier Class | Carrier Code | Method Name | Ahamove Service Key | Full Service ID (ví dụ HCM - SGN) |
|---|---|---|---|---|
| `ShippingMethod\Standard` | `ahamove_standard` | `Standard` | `bike`, `van` | `SGN-BIKE`, `SGN-VAN-500` |
| `ShippingMethod\Express` | `ahamove_express` | `Express` | `two_hours` | `SGN-2H-PUBLIC` |

Cả hai lớp đều kế thừa từ abstract class `Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod` (kế thừa `AhamoveAbstractCarrier`).

### 2.1 Ma Trận Giới Hạn Kích Thước (Dimensions & Volume Limits)

Mỗi loại dịch vụ có giới hạn chiều dài, rộng, cao cấu hình mặc định trong `etc/config.xml` và hỗ trợ override qua Admin Panel:

| Service Group | Service ID | Max Length | Max Width | Max Height |
|---|---|---|---|---|
| Xe Máy (`bike`) | `BIKE` | 50 cm | 40 cm | 50 cm |
| Xe Bán Tải (`van`) | `VAN-500` | 180 cm | 120 cm | 80 cm |
| Giao 2 Giờ (`two_hours`) | `2H-PUBLIC` | 50 cm | 40 cm | 50 cm |

> **Validation Logic**: Khi tính phí, `AhamoveShippingMethod` lấy kích thước sản phẩm từ Quote item (`height`, `width`, `length`). Nếu bất kỳ kích thước sản phẩm nào vượt quá giới hạn của dịch vụ, dịch vụ đó sẽ bị loại bỏ (trả về shipping fee = 0).

---

## 3. Luồng Rate Calculation (Tính Phí Vận Chuyển)

```
Checkout Rate Request
        │
        ▼
collectRates(RateRequest)
        │
        ├── 1. Build Origin Address (Store Config) & Destination Address (Quote Address)
        │
        ├── 2. Gọi Ahamove API: POST /v3/orders/estimates
        │         Payload: order_time (0), path [from_address, to_address],
        │                  group_services [{_id: BIKE}, {_id: VAN-500}, {_id: 2H-PUBLIC}],
        │                  payment_method (BALANCE/CASH)
        │
        ├── 3. Caching Result (Cache TTL 300s theo store_id & serialized params)
        │
        ├── 4. Filter & Validate kích thước sản phẩm trong giỏ với từng Service ID
        │
        └── 5. Trả về Result chứa các Method khả dụng kèm giá cước đã quy đổi currency
```

---

## 4. Luồng Shipment Creation (Tạo Vận Đơn Ahamove)

### 4.1 Auto Push: Khi Lưu Shipment trong Magento

```
Event: sales_order_shipment_save_after
        │
        ▼
OrderShipmentSaveAfter Observer
        │
        ├── Check auto_push_shipment == 1
        ├── Check shipping_method belongs to ahamove_standard / ahamove_express
        ├── Resolve full Service ID (vd: SGN-BIKE)
        │
        ▼
CreateShipment Command :: execute($package)
        │
        ▼
POST /v3/orders
        │ Payload:
        │   - service_id: "SGN-BIKE"
        │   - payment_method: "BALANCE" | "CASH"
        │   - order_time: 0
        │   - path: [
        │       from: { address, mobile },
        │       to: { address, name, mobile, tracking_number }
        │     ]
        │   - items: [ {_id: sku, num: qty, name, price} ]
        │
        ▼
Nhận Response → Gán tracking_code & shared_link vào Shipment Track
```

### 4.2 Manual Push: Admin Order View

Admin có thể kích hoạt nút bấm tạo vận đơn thủ công trong Admin Order View nếu `manual_push_button = 1`.

---

## 5. Webhook Receiver

**Endpoint**: `POST /ahamove/webhooks/index`  
**Controller**: `Secomm\Ahamove\Controller\Webhooks\Index` (Bypass CSRF via `CsrfAwareActionInterface`).

### 5.1 Luồng Xử Lý

1. Nhận JSON payload từ Ahamove callback.
2. Trích xuất `_id` (Ahamove Order ID), `status`, `shared_link`, `external_id` / `supplier_id` (Mã đơn Magento).
3. Lưu thông tin vào bảng `ahamove_order_status`.
4. Nếu trạng thái thuộc nhóm thất bại (`CANCELLED`, `RETURNED`, `IN_RETURN`, `FAILED`):
   - Kích hoạt `sendNotifyWebhookAhamove()` để gửi email / thông báo cho Seller.

---

## 6. Database Schema

Module định nghĩa 5 bảng dữ liệu trong `etc/db_schema.xml`:

| Bảng | Khóa Chính | Mục Đích |
|---|---|---|
| `ahamove_city` | `entity_id`, `city_id` | Danh mục Thành phố hỗ trợ bởi Ahamove (vd: `SGN`, `HAN`) |
| `ahamove_city_detail` | `entity_id` (FK `city_id` → `ahamove_city`) | Chi tiết cấu hình & dịch vụ hỗ trợ của từng Thành phố |
| `ahamove_standard` | `pk` | Cấu hình bảng cước phí Ahamove Standard theo vùng |
| `ahamove_express` | `pk` | Cấu hình bảng cước phí Ahamove Express theo vùng |
| `ahamove_order_status` | `entity_id` | Log trạng thái vận đơn và `shared_link` từ Webhook Ahamove |

---

## 7. Console Commands (CLI)

```bash
# Khởi tạo / Đồng bộ danh mục Thành phố từ Ahamove API vào database
php bin/magento ahamove:generate:city
```

Command `GenerateCityCommand` sẽ gọi API Ahamove `/v1/order/cities?country_id=VN` và lưu dữ liệu vào `ahamove_city` & `ahamove_city_detail`.

---

## 8. Admin System Configuration

**Path**: Stores → Configuration → Sales → Delivery Methods → **Ahamove**

| Field | Config Path | Mô Tả |
|---|---|---|
| Enable | `ahamove/general/enabled` | Bật/Tắt module Ahamove |
| Mode | `ahamove/general/mode` | `sandbox` hoặc `production` |
| Payment Type | `ahamove/general/payment_type` | `BALANCE` (Trừ ví) hoặc `CASH` (Tiền mặt) |
| Auto Push Shipment | `ahamove/general/auto_push_shipment` | Tự động tạo đơn Ahamove khi save Shipment |
| Manual Push Button | `ahamove/general/manual_push_button` | Hiển thị nút Push Ahamove trong Admin |
| Debug | `ahamove/general/debug` | Ghi log API ra `var/log/ahamove-shipping-method.log` |
| City ID Service | `ahamove/general/city` | Mã thành phố kho lấy hàng (vd: `SGN`, `HAN`) |
| Store Address | `ahamove/general/shipping_street`, ... | Địa chỉ kho lấy hàng xuất phát |

---

## 9. Known Issues & Technical Debt

| # | Vấn Đề | Mức Độ | Ghi Chú |
|---|---|---|---|
| 1 | Payload tạo đơn (`POST /v3/orders`) chưa truyền `package_detail` | Low / Info | Hiện tại API Ahamove v3 không bắt buộc `package_detail` cho luồng tạo đơn cơ bản. Đã ghi nhận để cân nhắc bổ sung nếu cần chi tiết kích thước kiện hàng sau này. |
| 2 | Trường `lat` và `lng` không truyền trong `path` | Info | Không bắt buộc theo specification của Ahamove nếu chuỗi địa chỉ `address` đã đầy đủ và đúng định dạng. |
| 3 | Queue handling | Info | Hiện tại tạo đơn Ahamove chạy đồng bộ trong Observer hoặc Command, chưa chuyển qua Message Queue. |

---

## 10. Escalation & Change Control

Theo quy định dự án tại [`.ai/AGENTS.md`](file:///var/www/Secomm/SECOMM-Internal/LaunchPad-docker/src/.ai/AGENTS.md) (§11 & §12):

| Thay Đổi | Tier | Người Duyệt | Lý Do |
|---|---|---|---|
| Logic tính phí (`/v3/orders/estimates`) | **Tier 2** | SA / CTO | Ảnh hưởng cước phí và checkout |
| Logic tạo vận đơn (`/v3/orders`) & Payload | **Tier 2** | SA / TL | Rủi ro liên kết đơn hàng & tạo đơn sai service |
| DB Schema (`db_schema.xml`) | **Tier 2** | SA | Migration cơ sở dữ liệu |
| Webhook & Notification logic | Tier 1 | TL | Ghi log & thông báo |
| CLI / Admin Config / Log | Tier 1 | TL | Low risk |

---

## 11. Changelog

| Phiên Bản | Ngày | Nội Dung |
|---|---|---|
| v1.1.0 | 2026-08-13 | Rà soát toàn bộ source code tích hợp Ahamove API v3. Tạo tài liệu kĩ thuật `TECHNICAL_SPEC.md` từ vị trí Technical Lead. Ghi nhận tình trạng `package_detail` và `lat`/`lng`. |
| v1.0.0 | — | Phiên bản khởi tạo ban đầu (Tích hợp Ahamove API v3, Rate estimation, Auto-push shipment, Webhook handler). |
