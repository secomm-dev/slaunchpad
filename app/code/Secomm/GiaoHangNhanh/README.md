# Module Secomm_GiaoHangNhanh

## 1. Giới thiệu (Overview)
Module `Secomm_GiaoHangNhanh` là giải pháp tích hợp dịch vụ giao hàng **Giao Hàng Nhanh (GHN)** API v2 cho hệ thống Magento 2 (tương thích giao diện Hyvä Theme và Luma Storefront). Module giúp tự động tính phí vận chuyển thời gian thực, quản lý và khởi tạo vận đơn tự động qua Queue/Admin, liên kết địa chỉ với `Secomm_GhnAddressMapper`, và theo dõi trạng thái đơn hàng (Tracking).

- **Vendor**: `Secomm`
- **Module Name**: `Secomm_GiaoHangNhanh`
- **Yêu cầu hệ thống**: Magento 2.4.6+, PHP 8.2 / 8.3 / 8.4, Hyvä 1.5.2+ / Luma

---

## 2. Các Tính Năng Chính (Core Features)

### 🚚 2.1. Tính Phí Vận Chuyển Thời Gian Thực (Live Shipping Rate Calculation)
- Hỗ trợ 2 phương thức vận chuyển chính:
  - **GHN Nhanh / Express** (`giaohangnhanh_express`)
  - **GHN Chuẩn / Standard** (`giaohangnhanh_standard`)
- Tự động gọi API GHN v2 (`/shiip/public-api/v2/shipping-order/fee`) tính cước dựa trên địa chỉ kho xuất hàng (Store District/Ward) và địa chỉ nhận hàng của khách.
- Tương thích với `Secomm_GhnAddressMapper` để chuyển đổi địa chỉ hành chính Việt Nam (Tỉnh/Thành, Quận/Huyện, Phường/Xã từ `Secomm_AddressDropdown`) sang `DistrictID` và `WardCode` của GHN.

### 📦 2.2. Khởi Tạo Vận Đơn & Đồng Bộ Đơn Hàng (Order Synchronization)
- Khởi tạo và đẩy đơn hàng sang hệ thống GHN (`/shiip/public-api/v2/shipping-order/create`) với các chế độ linh hoạt:
  - **Tự động qua Queue khi đặt hàng** (`auto_sync_on_place_order`): Đẩy thông điệp vào Queue Topology (RabbitMQ/MySQL Queue) ngay sau khi khách đặt hàng thành công.
  - **Tự động khi tạo Shipment** (`auto_sync_on_shipment_create`): Tự động đẩy đơn khi Admin tạo phiếu giao hàng (Shipment).
  - **Đồng bộ thủ công từ Admin** (`enable_admin_manual_sync_button`): Hiển thị nút **Sync GHN** trong trang chi tiết đơn hàng (Admin Order View).
- Nhận và lưu mã vận đơn GHN (`ghn_order_code`), trạng thái giao hàng và thông tin tracking chi tiết vào bảng `secomm_ghn_track`.

### ⚡ 2.3. Hàng Đợi Bất Đồng Bộ (Queue Topology & Consumer)
- Sử dụng Queue Consumer `secomm.ghn.order.sync` xử lý đẩy vận đơn bất đồng bộ, bảo vệ trải nghiệm khách hàng tại trang Checkout không bị ảnh hưởng bởi độ trễ API.

### 📝 2.4. Logging & Debug
- Ghi vết chi tiết thông tin API Request/Response và các lỗi phát sinh tại:
  `var/log/giaohangnhanh-shipping-method.log`

---

## 3. Kiến Trúc Cơ Sở Dữ Liệu (Database Schema)

Module định nghĩa các bảng dữ liệu sau trong DB (`etc/db_schema.xml`):

| Tên Bảng (Table Name) | Mô Tả (Description) | Các Trường Chính (Key Fields) |
|---|---|---|
| `secomm_ghn_track` | Lưu trữ thông tin và trạng thái vận đơn GHN | `entity_id`, `order_id`, `ghn_order_code`, `status`, `tracking_data` |
| `secomm_ghn_service` | Danh mục dịch vụ GHN hỗ trợ theo tuyến đường | `entity_id`, `service_id`, `service_name`, `service_type_id` |
| `secomm_ghn_fee` | Bảng lưu cache cước phí tính toán | `entity_id`, `quote_id`, `service_id`, `fee` |

---

## 4. Hướng Dẫn Cấu Hình Trong Admin (Admin Configuration)

Vào **Admin Panel** -> **Stores** -> **Configuration** -> **Sales** -> **Delivery Methods** -> **GHN**:

1. **General Settings**:
   - **Sandbox Mode**: Chọn `Yes` khi thử nghiệm, `No` khi chạy Production.
   - **Api Token & Shop Id**: Nhập API Token và Shop ID do GHN cấp.
   - **Payment Type**: Chọn đối tượng thanh toán cước phí (1: Người gửi trả, 2: Người nhận trả/COD).
   - **Note Code**: Quy định cho xem hàng (`CHOHANTUCHUYEN`, `CHOXEMHANGKHONGTHU`, `KHONGCHOXEMHANG`).
   - **Store District**: Chọn Quận/Huyện kho gửi hàng chính.
   - **Auto Sync Options**: Tùy chỉnh bật/tắt tự động sync khi Place Order hoặc khi Create Shipment.
   - **Debug**: Bật `Yes` để ghi log chi tiết API.
2. **Express & Standard Method Settings**:
   - **Enabled**: Bật/Tắt từng phương thức vận chuyển.
   - **Method Name**: Tên hiển thị ngoài Storefront Checkout.

---

## 5. Lịch Sử Sửa Lỗi & Tối Ưu (Bug Fixes & Refactor Notes)
- **Dependency Cleanup**: Đã loại bỏ phụ thuộc không tồn tại `Secomm_ShippingDimensions` trong `etc/module.xml` và làm sạch các đoạn code dư thừa.
- **PHP 8.2+ Support**: Hoàn toàn tương thích PHP 8.2 - 8.4 và Magento 2.4.8-p5.
- **Async Queue Sync**: Tối ưu cơ chế đẩy đơn qua Queue giúp luồng Checkout diễn ra nhanh chóng.
