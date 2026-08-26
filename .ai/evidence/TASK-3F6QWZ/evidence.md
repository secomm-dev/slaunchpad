# Evidence Report: TASK-3F6QWZ — Review and Install Existed Shipping Method Modules

- **Task**: TASK-3F6QWZ (external PM ref: SLP-12)
- **Task Record**: `.ai/records/tasks/TASK-3F6QWZ.md`
- **Spec**: `.ai/specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md`
- **Date**: 2026-08-20
- **Verified Commit**: `1cca6024edc849e7b233a0279c6d3df3985799a4`

---

## 1. Kết Quả Biên Dịch & Di Chuyển Module (Compilation & Module Status)

### Danh sách các module triển khai thành công:
1. `Secomm_ShippingCore` (Status: Enabled)
2. `Secomm_GhnAddressMapper` (Status: Enabled)
3. `Secomm_GiaoHangNhanh` (Status: Enabled)
4. `Secomm_Ahamove` (Status: Enabled)

### Kiểm tra biên dịch Dependency Injection:
- Lệnh: `bin/magento setup:di:compile`
- Kết quả: **PASS** — Không phát hiện cảnh báo deprecation fatal hoặc class không tìm thấy.

---

## 2. Kiểm Tra Đơn Vị (Unit Testing & Code Quality)

### Test Suite: `Secomm_GhnAddressMapper`
- Unit Test class: `Secomm\GhnAddressMapper\Test\Unit\Model\MappingImporterTest`
- Kết quả: **PASS** — Validate chính xác luồng import mapping CSV và kiểm tra trùng lặp dữ liệu.

### Kiểm thử CLI Commands:
- `bin/magento secomm:ghn-mapping:import`: Import dữ liệu mapping từ file CSV thành công.
- `bin/magento secomm:ghn-mapping:export`: Export dữ liệu ra CSV chuẩn format.
- `bin/magento secomm:ghn-mapping:validate`: Phân tích và phát hiện các bản ghi mapping thiếu `ward_code` hoặc `district_id`.

---

## 3. Kiểm Thử Tích Hợp Luồng Checkout & Fallback (Integration Testing)

| Kịch bản kiểm thử | Mô tả kịch bản | Kết quả mong đợi | Trạng thái |
|---|---|---|---|
| **TC-001** | Khách chọn địa chỉ nội thành VN (Hà Nội / HCM) | Hiển thị chính xác các phương thức GHN Nhanh/Chuẩn và Ahamove Instant | **PASS** |
| **TC-002** | Khách chọn địa chỉ ngoại thành | Hiển thị phương thức GHN phù hợp, ẩn/vô hiệu hóa Ahamove Instant | **PASS** |
| **TC-003** | API hãng vận chuyển bị Timeout / Sai Credential | **Graceful Hide (Accepted)**: Hệ thống log error và ẩn phương thức GHN/Ahamove bị lỗi. Magento checkout hiển thị các phương thức khác (FlatRate, Mageplaza TableRate) để khách mua hàng bình thường. Auto-fallback nội bộ carrier (GHN/Ahamove tự trả TableRate khi API lỗi) là Known Limitation — theo dõi bằng follow-up ticket. | **PASS** |
| **TC-004** | Webhook callback từ Ahamove / GHN | Webhook được tiếp nhận qua `WebhookTrackingBridge`, cập nhật trạng thái vào `secomm_carrier_tracking_state` | **PASS** |

---

## 4. Fallback Decision (DoD-003 — Resolved)

**Quyết định TL**: Chấp nhận cơ chế graceful degradation (system-level fallback) là đáp ứng đủ DoD-003 cho task review/install. Khi API GHN/Ahamove timeout/lỗi:
- Carrier ẩn đi (graceful hide)
- Checkout tiếp tục hiển thị Flat Rate / Mageplaza TableRate
- Luồng checkout không bị gián đoạn

**Known Limitation**: Chưa có auto-fallback nội bộ carrier (GHN/Ahamove tự động trả giá TableRate/FlatRate khi API Online lỗi). Theo dõi bằng follow-up ticket nếu business yêu cầu.
