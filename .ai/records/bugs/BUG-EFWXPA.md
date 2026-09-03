---
id: BUG-EFWXPA
type: bug
title: "[Ahamove][Shipment] Hiển thị status order không chính xác sau khi Send order to Ahamove thành công"
project_code: SLP
parent:
external_refs:
  ticket: SLP-120
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-08-27
updated: 2026-08-27
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_Ahamove
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/Ahamove
source_areas:
  - shipping-carrier
  - order-processing
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-08-27
supersedes: []
---

# [SLP][BUG-EFWXPA] [Ahamove][Shipment] Hiển thị status order không chính xác sau khi Send order to Ahamove thành công

<!-- External ticket: SLP-120 -->

## Summary

Sau khi Admin bấm nút **"Send to Ahamove"** thành công trên trang chi tiết đơn hàng (Order View):
1. Khi đơn hàng chưa có Shipment, Controller [`PushAhamove.php`](app/code/Secomm/Ahamove/Controller/Adminhtml/Order/PushAhamove.php) tạo mới Magento Shipment (`register()`) và lưu tracking code. Tuy nhiên, trạng thái Order (State & Status) có thể không cập nhật đúng theo trạng thái xử lý bán hàng (Order State Flow) của Magento:
   - Đơn hàng đã Invoiced trước đó + Ship đầy đủ: Cần chuyển sang state/status `complete`.
   - Đơn hàng chưa Invoiced (thanh toán COD / Bank Transfer / VietQR): Cần giữ đúng state tương ứng (`processing` hoặc giữ custom status thanh toán) nhưng phải cập nhật `qty_shipped` trên từng order item và ghi log/comment lịch sử đơn hàng.
   - Khi đơn hàng đã có sẵn Shipment từ trước (được tạo thủ công mà không auto-push), việc gắn thêm track Ahamove không cập nhật Order comment / history để Admin theo dõi.
2. Vấn đề truyền rỗng `$items = []` vào `ShipmentFactory::create($order, [], $tracks)` trước đó gây ra lỗi `We cannot create an empty shipment` (`The shipment couldn't be saved`), khiến trạng thái Order hoàn toàn không được chuyển đổi dù đơn trên Ahamove đã được tạo thành công.

---

## Mini Spec

### Goal

- Đảm bảo sau khi Admin bấm **"Send to Ahamove"** thành công:
  - Shipment được tạo và lưu thành công kèm mã vận đơn Ahamove (Tracking Number & Shared Link).
  - Trạng thái Order (State, Status, `qty_shipped`, `qty_to_ship`) được Magento cập nhật chính xác và đồng bộ theo đúng State Machine của Magento Sales.
  - Lịch sử đơn hàng (Order Comments History) được ghi nhận đầy đủ thông tin mã vận đơn và đường link theo dõi đơn hàng Ahamove.

### Expected Behavior

- **Trường hợp 1 (Đơn chưa có Shipment & đã Invoice/Thanh toán)**:
  - Tạo Shipment, gán Track Ahamove.
  - State chuyển thành `complete`, Status chuyển thành `Complete` (hoặc cấu hình hoàn tất của Store).
- **Trường hợp 2 (Đơn chưa có Shipment & chưa Invoice)**:
  - Tạo Shipment, gán Track Ahamove.
  - Các item trong đơn hàng cập nhật đúng `qty_shipped`, `canShip()` chuyển thành `false`.
  - State giữ `processing` hoặc trạng thái pending tương ứng, không bị rơi vào trạng thái không hợp lệ.
- **Trường hợp 3 (Đơn đã có sẵn Shipment thủ công)**:
  - Gắn mã Tracking vào Shipment hiện tại.
  - Thêm Order Comment: "Pushed to Ahamove. Tracking Code: <CODE>" để lưu vết.

### Constraints / Rules

- Tuân thủ quy chuẩn Magento Sales State Machine (không can thiệp ép cứng trạng thái trái với flow của Magento Sales).
- Không được làm phát sinh lỗi `We cannot create an empty shipment`.
- Chuẩn hóa số điện thoại và mảng `items` gửi sang Ahamove API v3.

### Acceptance Criteria

- **AC-001**: Bấm "Send to Ahamove" trên đơn hàng shippable -> Tạo Shipment thành công, có tracking Ahamove, Order hiển thị đúng trạng thái (Processing hoặc Complete tùy vào Invoice).
- **AC-002**: Không còn lỗi `Error pushing to Ahamove: The shipment couldn't be saved.`
- **AC-003**: Cột **Qty Shipped** của Order Items hiển thị đúng số lượng đã giao, không còn hiển thị nút "Ship" trùng lặp khi đã giao đủ.
- **AC-004**: Đơn hàng đã có sẵn Shipment trước đó -> Gắn track thành công và lưu Order comment ghi nhận mã tracking.

---

## Steps to Reproduce

1. Đặt 1 đơn hàng mới sử dụng phương thức vận chuyển Ahamove (`ahamove_standard` hoặc `ahamove_express`).
2. Vào Admin -> **Sales -> Orders -> Xem chi tiết đơn hàng (Order View)**.
3. Bấm nút **"Send to Ahamove"** trên thanh công cụ và xác nhận dialog.
4. Quan sát thông báo thành công và kiểm tra:
   - Trạng thái (Status/State) của đơn hàng ở khung Status.
   - Bảng Items Ordered (cột Qty Shipped vs Qty Invoiced).
   - Tab Shipments và Tab Comments History.

---

## Expected Behavior

- Hệ thống thông báo: `Pushed order #... to Ahamove successfully! Tracking Code: ...`
- Tab Shipments xuất hiện Shipment mới với đầy đủ item và mã tracking Ahamove.
- Status đơn hàng cập nhật đồng bộ (Processing nếu chưa Invoice, Complete nếu đã Invoice).

---

## Actual Behavior (Before Fix)

- Khi gọi `$shipmentFactory->create($order, [], $tracks)`, Shipment không có item dẫn đến ném lỗi `The shipment couldn't be saved.`
- Nếu lưu được shipment nhưng không gọi cập nhật đầy đủ Order/Items, status của Order không chuyển đổi, cột Qty Shipped vẫn là 0 hoặc trạng thái hiển thị không chính xác so với thực tế xử lý vận đơn.

---

## Root Cause Analysis

1. **Truyền sai mảng items vào `ShipmentFactory`**: Trong Magento 2, `ShipmentFactory::create()` yêu cầu mảng `$items` chứa `[item_id => qty_to_ship]`. Khi truyền `[]`, Magento không đưa item nào vào shipment và quăng exception `We cannot create an empty shipment`.
2. **Không đồng bộ Order State sau khi tạo Shipment**: Sau khi `$shipment->register()`, cần phải lưu cả `$shipment` qua `ShipmentRepository` và `$order` qua `OrderRepository` để cascade cập nhật trạng thái đơn hàng và các order items trong database.

---

## Affected Files

- [`app/code/Secomm/Ahamove/Controller/Adminhtml/Order/PushAhamove.php`](app/code/Secomm/Ahamove/Controller/Adminhtml/Order/PushAhamove.php)
- [`app/code/Secomm/Ahamove/Command/CreateShipment.php`](app/code/Secomm/Ahamove/Command/CreateShipment.php)
- [`app/code/Secomm/Ahamove/Helper/Data.php`](app/code/Secomm/Ahamove/Helper/Data.php)
- [`app/code/Secomm/Ahamove/Plugin/Adminhtml/AddPushAhamoveOrderButtonPlugin.php`](app/code/Secomm/Ahamove/Plugin/Adminhtml/AddPushAhamoveOrderButtonPlugin.php)

---

## Verification & Test Results

- **Environment**: Magento 2.4.8-p5, PHP 8.3-FPM (Docker container `launchpad-docker-phpfpm-1`).
- **Test Order**: Đơn hàng #000000048 (`cabinet-display-fluted`, Qty: 2).
- **Kết quả**:
  - Tạo thành công Shipment #000000021 với tracking code `26082715URUE`.
  - Cập nhật đúng `qty_shipped = 2`, `canShip = 0`.
  - Trạng thái Order đồng bộ và không còn xảy ra lỗi save shipment.
