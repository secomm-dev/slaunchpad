---
id: BUG-63CVS3
type: bug
title: "[VietQR][Order admin] Update additional information in order admin"
project_code: SLP
parent: FEAT-ZKD4VA
external_refs:
  ticket: SLP-149
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-03
updated: 2026-09-03
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_VietQr
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/VietQr
source_areas:
  - payment
  - admin-order-view
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-03
supersedes: []
---

# [SLP][BUG-63CVS3] [VietQR][Order admin] Update additional information in order admin

<!-- External ticket: SLP-149 -->

## Summary

Khi khách hàng đặt hàng qua phương thức thanh toán VietQR và xác nhận chuyển khoản trên trang `/vietqr/payment/view`, các thông tin chuyển khoản và xác nhận (`vietqr_bank_code`, `vietqr_bank_account`, `vietqr_account_name`, `vietqr_amount`, `vietqr_content`, `vietqr_customer_confirmed`, `vietqr_customer_confirmed_at`, `vietqr_transaction_ref`, `vietqr_customer_notes`) được lưu trong `sales_order_payment.additional_information`. Tuy nhiên, trang chi tiết đơn hàng trong Magento Admin (**Sales > Orders > View Order**) chưa hiển thị các thông tin này vì module `Secomm_VietQr` chưa có custom Payment Info block cho admin; đồng thời ghi chú lịch sử đơn hàng (`sales_order_status_history`) chưa bao gồm mã giao dịch và ghi chú của khách.

## Mini Spec

### Goal

Hiển thị đầy đủ thông tin thanh toán VietQR và thông tin xác nhận chuyển khoản của khách hàng trong trang quản trị đơn hàng (Admin Order View):
- Khối **Payment Information** hiển thị các thông tin chi tiết từ `additional_information`.
- Khối **Status History / Comments** hiển thị mã giao dịch (`transaction_ref`) và ghi chú (`customer_notes`) khi khách xác nhận chuyển khoản.

### Expected Behavior

- Trong Admin Order View, khối Payment Information hiển thị:
  - Ngân hàng (Bank)
  - Số tài khoản (Account Number)
  - Chủ tài khoản (Account Holder)
  - Số tiền (Amount)
  - Nội dung chuyển khoản (Transfer Content)
  - Khách xác nhận (Customer Confirmed: Có / Chưa)
  - Thời gian xác nhận (Confirmed At - nếu có)
  - Mã giao dịch (Transaction Reference - nếu có)
  - Ghi chú của khách (Customer Notes - nếu có)
- Khi khách submit xác nhận trên trang `/vietqr/payment/view`:
  - Comment đơn hàng được ghi kèm:
    - "Khách hàng xác nhận chuyển khoản qua trang VietQR."
    - "Mã giao dịch: {transaction_ref}" (nếu có)
    - "Ghi chú của khách: {customer_notes}" (nếu có)
- Hỗ trợ song ngữ `vi_VN` và `en_US` đầy đủ (BR-001).

### Constraints / Rules

- Tuân thủ coding standard của Secomm: file header Secomm chuẩn, `declare(strict_types=1);`, không dùng ObjectManager trực tiếp.
- Giữ tương thích với cấu trúc của Magento Payment: kế thừa `\Magento\Payment\Block\Info`, khai báo qua `$_infoBlockType` trong Payment Model.
- Format tiền tệ hiển thị theo đúng currency của đơn hàng.
- Chuỗi i18n phải được mirror đầy đủ giữa `vi_VN.csv` và `en_US.csv`.

### Out of Scope

- Thay đổi quy trình thanh toán hoặc trạng thái đơn hàng (order statuses vẫn giữ nguyên `vietqr_pending` và `vietqr_awaiting_payment_confirm`).
- Thay đổi API VietQR tích hợp hoặc giao diện thanh toán phía storefront.

### Acceptance Criteria

- AC-001: Tạo block `Secomm\VietQr\Block\Info\VietQr` kế thừa `\Magento\Payment\Block\Info` và override `_prepareSpecificInformation()` trích xuất các thông tin từ `additional_information`.
- AC-002: Đăng ký `$_infoBlockType` trong `Secomm\VietQr\Model\Payment` trỏ đến block vừa tạo.
- AC-003: Cập nhật `Submit.php` để lưu `transaction_ref` và `customer_notes` vào comment lịch sử của đơn hàng khi submit.
- AC-004: Khối Payment Information trong Admin Order View hiển thị rõ ràng, đúng format tiền tệ và các nhãn song ngữ.
- AC-005: Các nhãn mới có mặt ở cả `vi_VN.csv` và `en_US.csv`.
- AC-006: Thêm cấu hình `customer_confirm_comment` trong system.xml cho phép merchant tùy biến câu comment mặc định khi khách xác nhận chuyển khoản.
