---
id: DEC-TASK3F6QWZ-002
title: 'Standardize GHN and Ahamove into Secomm_ShippingCore shared tracking pipeline and status normalization'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-20
created: 2026-08-20
last_verified: 2026-08-20
verified_against_commit: 588a0d17e914022416b23d9b4b60098f9219e2df
supersedes: []
superseded_by:
work_items: [TASK-3F6QWZ]
---

# Decision Record: Standardize GHN and Ahamove into Secomm_ShippingCore shared tracking pipeline

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-20 — TASK-3F6QWZ (SLP-12) Architecture Decision -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context
Trước đây, các module vận chuyển của Secomm (Ahamove, GHN, GHTK) mỗi bên tự xử lý webhook, tự lưu trạng thái đơn hàng vào các bảng riêng lẻ (ví dụ: `ahamove_order_status`), dẫn đến phân mảnh logic, khó đồng bộ vào `sales_shipment_track` và bình luận đơn hàng của Magento.

Sau khi thiết lập kiến trúc `Secomm_ShippingCore` (được định nghĩa trong DEC-SL015-001 và DEC-SL017-001), task TASK-3F6QWZ (external PM ref: SLP-12) tiến hành tái cấu trúc cả `Secomm_GiaoHangNhanh` và `Secomm_Ahamove` để kết nối vào pipeline chung này.

## Quyết định (Decision)
1. **Tích hợp Status Mapper Interface**:
   - `Secomm\GiaoHangNhanh\Model\Tracking\GhnStatusMapper` và `Secomm\Ahamove\Model\Tracking\AhamoveStatusMapper` triển khai `Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface`.
   - Ánh xạ mã trạng thái gốc của từng hãng vận chuyển (ví dụ: `ready_to_pick`, `delivering`, `delivered` của GHN; `COMPLETED`, `CANCELLED`, `FAILED` của Ahamove) sang mã trạng thái chuẩn hóa của hệ thống (`NormalizedTrackingStatus::PICKING`, `IN_TRANSIT`, `DELIVERED`, `CANCELLED`, ...).

2. **Chuẩn hóa Webhook Handler qua Tracking Bridge**:
   - Tái cấu trúc Controller nhận webhook của Ahamove (`/ahamove/webhooks/index`) để parse payload, khởi tạo đối tượng `TrackingUpdate` và chuyển tiếp cho `ShipmentTrackingProcessor` của `ShippingCore`.
   - Đảm bảo tính idempotent, hỗ trợ xử lý out-of-order webhook (không hạ cấp trạng thái nếu đơn vị vận chuyển gửi webhook chậm sau khi đã DELIVERED/CANCELLED).

3. **Loại bỏ Code Legacy và Hạn chế Tác vụ Phá hủy**:
   - Xóa bỏ `session_destroy()` trong `ErrorMessageManager` của module Ahamove, tránh gây mất session/giỏ hàng của người dùng khi gặp lỗi từ API.
   - Thống nhất cơ chế đọc địa chỉ kho gửi hàng (`ShippingOriginProvider`) từ `Secomm_ShippingCore` cho các API tính giá ship và tạo đơn.

## Hệ quả (Consequences)
- (+) Tất cả hãng vận chuyển (GHN, Ahamove, GHTK) đều đi qua một chuỗi xử lý trạng thái thống nhất.
- (+) Không còn hiện tượng xung đột session hay mất dữ liệu giỏ hàng của khách hàng.
- (+) Tự động đồng bộ lịch sử trạng thái vào Admin Shipment comments và bảng `secomm_carrier_tracking_state`.
