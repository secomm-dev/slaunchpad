---
id: TASK-3F6QWZ
type: task
title: 'Review, Install and Refactor shipping method modules (GHN, Ahamove, AddressMapper, ShippingCore)'
project_code: SLP
parent: null
external_refs:
  xcorp: SLP-12
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md
risk: high
status: completed
created: 2026-08-20
updated: 2026-08-20
decisions:
  - DEC-SL015-001            # ShippingCore Origin Provider
  - DEC-SL017-001            # ShippingCore Tracking pipeline
  - DEC-TASK3F6QWZ-001       # Decouple GHN mapping into Secomm_GhnAddressMapper
  - DEC-TASK3F6QWZ-002       # Refactor GHN and Ahamove into ShippingCore tracking/status mapper
decision_assessment: material
decision_approval_summary:
  total: 4
  pending_approval: []
  approved: [DEC-SL015-001, DEC-SL017-001, DEC-TASK3F6QWZ-001, DEC-TASK3F6QWZ-002]
  rejected: []
  superseded: []
  last_synced: 2026-08-20
verified_against_commit: 1cca6024edc849e7b233a0279c6d3df3985799a4
components:
  - CMP-SHIPPINGCORE         # Secomm_ShippingCore (Shared Shipping contracts/processors)
  - CMP-GHNMAPPING           # Secomm_GhnAddressMapper (Dynamic Magento region/city/ward to GHN IDs)
  - CMP-GHN                  # Secomm_GiaoHangNhanh (GHN v2 carrier API integration)
  - CMP-AHAMOVE              # Secomm_Ahamove (Ahamove instant shipping integration)
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/Ahamove/
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: true
last_verified: 2026-08-20
supersedes: []
---

# [SLP][TASK-3F6QWZ] Review, Install and Refactor shipping method modules (GHN, Ahamove, AddressMapper, ShippingCore)

<!-- CANONICAL TASK RECORD — TASK-3F6QWZ (external PM ref: SLP-12). Review, install & refactor legacy shipping modules to standard Launchpad Core patterns. -->

## Bối cảnh (Context)
Dự án Launchpad Core sử dụng nền tảng Magento 2.4.8-p5 + Hyvä Theme + Mageplaza OSC. Các module vận chuyển trước đây (GHN, Ahamove) cần được tích hợp lại, dọn dẹp các đoạn code lỗi thời/xung đột (deprecations, hardcoded JSON files, session_destroy) và chuẩn hóa theo kiến trúc dùng chung của hệ thống.

## Mini-Spec (Embedded)

### Goal
Clone, review, cài đặt và refactor 4 module vận chuyển hiện có (`Secomm_ShippingCore`, `Secomm_GhnAddressMapper`, `Secomm_GiaoHangNhanh`, `Secomm_Ahamove`) lên môi trường Launchpad Core (Magento 2.4.8-p5 / PHP 8.2-8.4 / Hyvä 3.x / Mageplaza OSC), đảm bảo tương thích backend, checkout real-time rate, cơ chế fallback graceful degradation, và webhook tracking pipeline.

### Expected Behavior
1. Toàn bộ 4 module biên dịch DI không có lỗi, không có deprecation fatal.
2. GHN và Ahamove hiển thị phí ship real-time trên Mageplaza OSC, phân biệt địa chỉ trong/ngoài tỉnh.
3. Khi API GHN/Ahamove timeout/lỗi, carrier ẩn đi (graceful hide), checkout tiếp tục hiển thị Flat Rate / TableRate → luồng checkout không chết.
4. Webhook từ GHN/Ahamove được normalize qua ShippingCore pipeline, cập nhật tracking state + shipment comment đúng trạng thái.

### Constraints / Rules
- Không làm thay đổi logic Flat Rate / Mageplaza TableRate core.
- Không thay đổi Mageplaza OSC checkout flow.
- Module Ahamove: xóa `session_destroy()` gây mất session giỏ hàng.
- Module GHN: xóa file JSON tĩnh >35.000 dòng, chuyển sang dynamic mapping từ `Secomm_GhnAddressMapper`.
- Namespace: `Boolfly_GiaoHangNhanh` → `Secomm_GiaoHangNhanh`.
- PHP 8.2+ compatible, Magento coding standard, `strict_types`.

### Out of Scope
- Auto-fallback nội bộ trong carrier (GHN/Ahamove tự trả giá TableRate khi API lỗi) — đây là Known Limitation, không triển khai.
- GHN/Ahamove native label flow (thuộc ticket khác).
- Thay đổi Mageplaza TableRate logic tính giá.

### Acceptance Criteria
- [x] **DoD-001**: 4 module compile DI + static content không lỗi.
- [x] **DoD-002**: Real-time rate hiển thị đúng trên Mageplaza OSC.
- [x] **DoD-003**: Graceful hide khi API lỗi, checkout không chết — chấp nhận là đủ DoD (Known Limitation: chưa có auto-fallback nội bộ carrier).
- [x] **DoD-004**: Webhook normalize qua ShippingCore pipeline, tracking state + comment đúng.

## Kiến trúc đã triển khai (Implemented Architecture)

### 1. `Secomm_ShippingCore` (Shared Shipping Contracts)
- Cung cấp Interface chuẩn: `OriginProviderInterface` (kho gửi hàng), `CarrierTrackingProcessorInterface` (chuỗi xử lý trạng thái).
- Tránh việc các module hãng vận chuyển phải tự triển khai lại logic lưu trữ tracking state.

### 2. `Secomm_GhnAddressMapper` (Tầng Mapping địa chỉ GHN)
- Giải quyết đồng bộ địa giới hành chính Magento ↔ GHN IDs.
- Admin Grid import/export CSV, cascading AJAX, CLI commands (`secomm:ghn-mapping:*`).

### 3. `Secomm_GiaoHangNhanh` (Tích hợp GHN)
- Namespace: `Boolfly_GiaoHangNhanh` → `Secomm_GiaoHangNhanh`.
- Xóa JSON tĩnh >35.000 dòng, dùng dynamic mapping từ `Secomm_GhnAddressMapper`.
- Queue Consumer: `ghn.sync.order`, `ghn.cancel.order`.
- `GhnStatusMapper` chuẩn hóa trạng thái qua `ShippingCore`.

### 4. `Secomm_Ahamove` (Tích hợp Ahamove)
- Chuẩn hóa Webhook callback → `WebhookTrackingBridge` của `ShippingCore`.
- Xóa `session_destroy()` gây mất session/giỏ hàng.

## Known Limitations

1. **Carrier Rate Fallback (DoD-003)**: Khi API GHN/Ahamove timeout/lỗi, module chỉ catch exception và trả về `false` (ẩn carrier). Chưa có cơ chế auto-fallback nội bộ sang bảng giá TableRate/FlatRate của riêng carrier ở Online Mode. Cần bật sẵn Magento Flat Rate hoặc Mageplaza Table Rate trên Checkout làm phương thức dự phòng. → Follow-up ticket nếu business yêu cầu auto-fallback nội bộ.
