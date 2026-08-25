# Implementation Plan: TASK-3F6QWZ — Review and Install Existed Shipping Method Modules

| Field | Value |
|---|---|
| Specification | specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md |

> Mode A · Tier-2 (shipping + carrier API integration) · Plan derived from Spec.
> External PM ref: SLP-12. Canonical work item: [TASK-3F6QWZ](../records/tasks/TASK-3F6QWZ.md).

## Metadata

| Field | Value |
|-------|-------|
| Task | [TASK-3F6QWZ](../records/tasks/TASK-3F6QWZ.md) |
| Spec | [review-install-shipping-modules](../specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md) |
| Author | dev (thangpham) |
| Workflow Mode | A |
| Date | 2026-08-20 |
| Decisions | DEC-SL015-001 · DEC-SL017-001 · DEC-TASK3F6QWZ-001 · DEC-TASK3F6QWZ-002 |
| Validation level | L3 (shipping + checkout + carrier API) |

---

## 1. Checklist Triển Khai & Kiểm Thử (Implementation Checklist)

### 1.1. Chuẩn hóa Backend & Compatibility
- [x] Đổi namespace `Boolfly_GiaoHangNhanh` → `Secomm_GiaoHangNhanh`.
- [x] Xóa bỏ các file JSON tĩnh khổng lồ (>35.000 dòng) trong module GHN.
- [x] Tạo mới module `Secomm_GhnAddressMapper` với bảng CSDL `secomm_ghn_address_mapping`, Admin UI grid, form cascading, import/export CSV.
- [x] Xóa bỏ `session_destroy()` và các code legacy trong `Secomm_Ahamove`.
- [x] Tích hợp `Secomm_ShippingCore` cho cả GHN và Ahamove.

### 1.2. Kiểm tra Checkout & Fallback
- [x] Xác minh luồng lấy giá ship trên Mageplaza OSC.
- [x] **Fallback Mechanism (Accepted)**: Hệ thống sử dụng graceful degradation:
  - Khi API GHN/Ahamove timeout/lỗi, `collectRates()` catch Exception và `return false` → carrier bị ẩn.
  - Magento checkout tiếp tục hiển thị các carrier khác (Flat Rate, Mageplaza TableRate) nếu store đã cấu hình.
  - Luồng checkout không chết → đáp ứng DoD-003 ở cấp system-level.
  - **Known Limitation**: Chưa có cơ chế auto-fallback nội bộ carrier (GHN/Ahamove tự trả giá TableRate khi API lỗi). Theo dõi bằng follow-up ticket nếu business yêu cầu.

### 1.3. Tương thích Hyvä & Static Assets
- [x] Kiểm tra giao diện và luồng chọn shipping method trên Hyvä theme không phụ thuộc RequireJS/jQuery.
