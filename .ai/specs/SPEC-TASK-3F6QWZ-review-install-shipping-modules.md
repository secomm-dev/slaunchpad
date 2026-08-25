# Feature Spec — Review, Install and Refactor Shipping Method Modules (GHN, Ahamove, AddressMapper, ShippingCore)

Specification ID: SPEC-TASK-3F6QWZ
Feature ID: NONE
Specification Level: FULL

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho work item TASK-3F6QWZ (external PM ref: SLP-12 — Review and Install existed shipping method module). Standalone task · Mode A · Tier-2 (shipping + carrier integration). -->
<!-- Decisions: DEC-SL015-001 (ShippingCore Origin Provider) · DEC-SL017-001 (ShippingCore Tracking pipeline) · DEC-TASK3F6QWZ-001 (decouple GHN mapping → GhnAddressMapper) · DEC-TASK3F6QWZ-002 (GHN/Ahamove → ShippingCore tracking). -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Tier-2 — shipping logic + third-party carrier API integration (AGENTS.md §12) → Mode A, escalate TL/SA.

## Feature Overview

**Feature name**: Review, Install and Refactor legacy shipping method modules (GHN, Ahamove) into Launchpad Core standard patterns.

**Work item reference**: `TASK-3F6QWZ` (external PM ref `SLP-12`) · related capability `Secomm_ShippingCore` (DEC-SL015-001, DEC-SL017-001).

**Feature type**: Reuse + refactor existing modules

**Priority**: P1 / High

**Mode / Risk**: A · Tier-2 (shipping logic + carrier integration)

## Scope note

Spec này bao phủ **TASK-3F6QWZ** (SLP-12): review, cài đặt và chuẩn hóa bộ 4 module vận chuyển:

- `Secomm_ShippingCore` — tầng contract chuẩn cho kho gửi hàng + chuỗi xử lý trạng thái vận đơn.
- `Secomm_GhnAddressMapper` — tầng ánh xạ địa giới hành chính Magento → ID GHN (dynamic, DB-backed).
- `Secomm_GiaoHangNhanh` — carrier API v2 cho Giao Hàng Nhanh (Express/Standard, Queue Order Sync).
- `Secomm_Ahamove` — carrier API cho Ahamove (Standard Bike / Express 2H, Webhook bridge).

## 1. Bối cảnh & Mục tiêu

- **Mục tiêu**: Tái sử dụng, nâng cấp và chuẩn hóa bộ module vận chuyển của Secomm tương thích hoàn toàn với nền tảng Magento 2.4.8-p5, PHP 8.2–8.4, Hyvä 3.x Theme và Mageplaza One Step Checkout.
- **Trọng tâm task**: review và cài đặt để kiểm tra độ tương thích (compatibility) với baseline hệ thống mới — clone code từ dự án nguồn, fix nhanh deprecation/lỗi function không còn hỗ trợ, kiểm tra luồng checkout và cơ chế fallback.

## 2. Luồng Dữ Liệu & Trình Tự Thực Thi

### A. Luồng Tính Phí Vận Chuyển Real-Time & Cơ chế Fallback (Checkout Rate Flow)

```
Customer/OSC -> Carrier Model (GHN / Ahamove) -> collectRates()
                      │
                      ├─► 1. Lấy thông tin kho xuất hàng (OriginProviderInterface - ShippingCore)
                      ├─► 2. Phân giải địa chỉ nhận hàng:
                      │      - GHN: LocationResolver -> to_district_id, to_ward_code (GhnAddressMapper)
                      │      - Ahamove: tọa độ Lat/Lng hoặc địa chỉ chi tiết
                      ├─► 3. Gửi Request tính giá sang Carrier API (GHN v2 / Ahamove API)
                      │      │
                      │      ├─ [API Thành công] ──► Trả về phí vận chuyển hiển thị trên Checkout
                      │      │
                      │      └─ [API Timeout / Lỗi / Exception] ──► Graceful Catch & Graceful Fallback:
                      │                                 - Log error via Logger, Carrier trả về false (hide)
                      │                                 - Magento tiếp tục render các carrier khác (Flat Rate, TableRate)
```

### B. Luồng Đồng Bộ Đơn Hàng Sang Hãng Vận Chuyển (Order Submission & Sync Flow)

```
Order Placed / Shipment Created
         │
         ├─► [Chế độ Real-time]: OrderSyncService -> Gọi API tạo đơn sang GHN / Ahamove
         │
         └─► [Chế độ Queue]: Publish Message vào Topic `ghn.sync.order`
                                      │
                                      ▼
                             Consumer (OrderSyncConsumer) -> Gọi API tạo đơn GHN -> Lưu tracking code vào Sales Shipment
```

### C. Luồng Xử Lý Webhook & Chuẩn Hóa Trạng Thái (Webhook & Tracking Normalization Flow)

```
Carrier Server (GHN / Ahamove) -> Gửi Webhook POST -> Controller Webhook (/ahamove/webhooks/index)
                                                                 │
                                                                 ▼
                                                   WebhookTrackingBridge / TrackingUpdate
                                                                 │
                                                                 ▼
                                             StatusMapper (AhamoveStatusMapper / GhnStatusMapper)
                                                                 │
                                                                 ▼
                                             ShipmentTrackingProcessor (Secomm_ShippingCore)
                                                                 │
                                                                 ├─► Lưu vào `secomm_carrier_tracking_state`
                                                                 ├─► Cập nhật Track.description trong Magento
                                                                 └─► Thêm Comment vào lịch sử Shipment
```

## 3. Acceptance Criteria (DoD)

- **DoD-001 (Cài đặt & Tương thích)**: Toàn bộ 4 module cài đặt thành công lên Magento 2.4.8-p5 / PHP 8.2–8.4, biên dịch DI và static content không có lỗi.
- **DoD-002 (Tính phí ship & Hiển thị OSC)**: Luồng gọi API lấy phí ship (real-time rate) hoạt động trơn tru tại trang Checkout Mageplaza OSC, phân biệt được địa chỉ trong/ngoài tỉnh của GHN và instant rate của Ahamove.
- **DoD-003 (Cơ chế Fallback)**: Khi API GHN/Ahamove timeout/lỗi, `collectRates()` catch Exception và `return false` → carrier bị ẩn đi; Magento checkout tiếp tục hiển thị các carrier khác đang active (Flat Rate, Mageplaza TableRate) nếu store đã cấu hình sẵn → luồng checkout không chết (graceful at system level). **Được chấp nhận là đủ DoD** — auto-fallback nội bộ trong carrier là Known Limitation, theo dõi bằng follow-up ticket.
- **DoD-004 (Đồng bộ Trạng thái / Webhook)**: Webhook phản hồi từ GHN/Ahamove truyền đúng payload, qua mapper chuẩn hóa trạng thái về `secomm_carrier_tracking_state` và cập nhật chính xác description của vận đơn.

## 4. Out of Scope

- **Auto-fallback nội bộ carrier** (GHN/Ahamove tự trả giá TableRate/FlatRate khi API Online lỗi) — ghi nhận Known Limitation, không triển khai trong task này.
- Thay đổi logic tính giá của Mageplaza TableRate / Flat Rate core.
