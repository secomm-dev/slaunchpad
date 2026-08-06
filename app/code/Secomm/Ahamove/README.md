# Module Secomm_Ahamove

## 1. Giới thiệu (Overview)
Module `Secomm_Ahamove` là giải pháp tích hợp dịch vụ giao hàng nhanh **Ahamove** cho hệ thống Magento 2 (tương thích giao diện Hyvä Theme và Luma Storefront). Module giúp tự động tính phí vận chuyển thời gian thực, quản lý danh mục thành phố hỗ trợ, đẩy đơn tạo chuyến giao hàng sang Ahamove, đồng bộ trạng thái vận đơn qua Webhook và tự động xử lý làm mới Token gia hạn API.

- **Vendor**: `Secomm`
- **Module Name**: `Secomm_Ahamove`
- **Yêu cầu hệ thống**: Magento 2.4.6+, PHP 8.2 / 8.3, Hyvä 1.5.2+ / Luma

---

## 2. Các Tính Năng Chính (Core Features)

### 🚚 2.1. Phương Thức Vận Chuyển Thời Gian Thực (Live Shipping Rate Calculation)
- Hỗ trợ 2 carrier code chính:
  - **Ahamove Standard** (`ahamove_standard`): Các dịch vụ giao hàng tiêu chuẩn (Xe máy - `BIKE`, Xe tải - `VAN-500`).
  - **Express / Siêu Tốc** (`ahamove_express`): Dịch vụ giao hàng nhanh 2H (`2H-PUBLIC`).
- Tự động gọi API Ahamove (`POST /v3/orders/fee`) tính giá cước chính xác dựa trên địa chỉ kho xuất hàng (Origin Address) và địa chỉ nhận hàng của khách.
- Hệ thống Cache thông minh phân vùng theo `store_id` giúp tối ưu tốc độ checkout.

### 🔑 2.2. Quản Lý Token & Multi-Store Configuration
- Hỗ trợ lưu trữ cấu hình API Key, Telephone và Token tách biệt theo từng **Store View / Website Scope** hoặc Default Scope.
- Cơ chế tự động làm mới Token (**Auto Refresh Token**): Khi gặp lỗi Token hết hạn (Mã 401 / Authentication Fail), module tự động bắt event `refresh_ahamove_token` để xin lại Token mới từ Ahamove và cập nhật vào đúng Scope cấu hình.

### 📦 2.3. Đẩy Đơn Hàng & Khởi Tạo Chuyến Giao Hàng (Create Order / Shipment)
- Khởi tạo chuyến giao hàng trên Ahamove khi Admin thực hiện **Tạo Shipment (Phiếu giao hàng)** cho đơn hàng.
- Gọi API `POST /v3/orders` truyền thông tin lấy hàng (Kho), thông tin nhận hàng (Khách hàng) và chi tiết danh sách sản phẩm trong kiện.
- Nhận và lưu trữ thông tin trả về từ Ahamove: Mã vận đơn (`track_number`), Mã đơn Ahamove (`order_ahamove_id`), Trạng thái (`status`) và Link theo dõi tài xế thời gian thực (`shared_link`).

### 🏙️ 2.4. Đồng Bộ Danh Mục Thành Phố (City Sync & CLI Command)
- Cung cấp CLI Command: `php bin/magento secomm_ahamove:generate:city` để khởi tạo và đồng bộ danh mục thành phố phục vụ tính phí.
- Cung cấp nút bấm **Refresh City Data** trong Admin System Config hỗ trợ sync dữ liệu Tỉnh/Thành phố chi tiết từ Ahamove vào bảng `ahamove_city` và `ahamove_city_detail`.

### 🔔 2.5. Webhook Đồng Bộ Trạng Thái & Email Cảnh Báo
- Endpoint tiếp nhận Webhook: `/ahamove/webhooks/index`.
- Nhận phản hồi trạng thái vận đơn từ Ahamove (VD: `COMPLETED`, `CANCELLED`, `FAILED`, `RETURNED`, `IN_RETURN`).
- Tự động kích hoạt gửi Email thông báo tới người quản trị (Seller) khi giao hàng thất bại hoặc hủy chuyến.
- Hỗ trợ tự động tạo Shipment trên Magento khi gói đóng gói hoàn tất (`evt_packaging_manager_auto_create_shipment`).

### 📝 2.6. Hệ Thống Logging
- Ghi vết lịch sử gọi API, dữ liệu request/response và các lỗi phát sinh tại:
  `var/log/ahamove-shipping-method.log`

---

## 3. Kiến Trúc Cơ Sở Dữ Liệu (Database Schema)

Module định nghĩa các bảng dữ liệu sau trong DB (`etc/db_schema.xml`):

| Tên Bảng (Table Name) | Mô Tả (Description) | Các Trường Chính (Key Fields) |
|---|---|---|
| `ahamove_city` | Danh mục tỉnh/thành phố Ahamove hỗ trợ | `city_id`, `country_id`, `name`, `name_vi_vn` |
| `ahamove_city_detail` | Chi tiết cấu hình dịch vụ theo từng thành phố | `city_id`, `name`, `location`, `service_city_id`, `public_service` |
| `ahamove_order_status` | Trạng thái đơn hàng nhận từ Webhook Ahamove | `order_ahamove_id`, `track_number`, `status`, `shared_link`, `order_data` |
| `ahamove_standard` | Bảng cấu hình cước phí Ahamove Standard theo vùng | `pk`, `website_id`, `dest_country_id`, `dest_region_id`, `price` |
| `ahamove_express` | Bảng cấu hình cước phí Ahamove Express theo vùng | `pk`, `website_id`, `dest_country_id`, `dest_region_id`, `price` |

---

## 4. Hướng Dẫn Cấu Hình Trong Admin (Admin Configuration)

Vào **Admin Panel** -> **Stores** -> **Configuration** -> **Sales** -> **Delivery Methods** -> **Ahamove**:

1. **General Settings**:
   - **Mode**: Chọn `Sandbox` (Thử nghiệm) hoặc `Production` (Vận hành thực tế).
   - **Staging / Production API Key & Phone**: Nhập API Key và SĐT tài khoản Ahamove do Ahamove cấp.
   - **City ID Service**: Chọn thành phố hoạt động chính (VD: `SGN` - TP.HCM, `HAN` - Hà Nội).
   - **Debug**: Bật `Yes` để ghi log chi tiết API vào file `var/log/ahamove-shipping-method.log`.
2. **Ahamove Standard & Express Settings**:
   - **Enabled**: Bật/Tắt từng phương thức vận chuyển.
   - **Title / Method Name**: Tên hiển thị phương thức ngoài Storefront.
   - **Maximum Weight / Dimensions**: Cấu hình giới hạn trọng lượng và kích thước tối đa của kiện hàng.

---

## 5. Danh Sách Lệnh CLI (Console Commands)

```bash
# 同步 / Khởi tạo danh mục Tỉnh Thành từ Ahamove vào DB
php bin/magento secomm_ahamove:generate:city
```

---

## 6. Lịch Sử Sửa Lỗi & Tối Ưu (Bug Fixes & Refactor Notes)
- **Session Protection**: Đã loại bỏ lệnh `session_destroy()` nguy hiểm trong `ErrorMessageManager.php` giúp bảo vệ giỏ hàng khách hàng.
- **Multi-Store Isolation**: Tất cả Cache Key tính phí và lưu Token gia hạn tự động đã được phân vùng độc lập theo `store_id` và `scope`.
- **PHP 8 Compatibility**: Sửa toàn bộ các lỗi Fatal Error về type casting và null pointer trong Webhook Plugin và API Response parser.
