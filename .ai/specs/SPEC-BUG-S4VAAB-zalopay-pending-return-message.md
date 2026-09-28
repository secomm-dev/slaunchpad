# SPEC-BUG-S4VAAB — ZaloPay pending Return message

Specification ID: SPEC-BUG-S4VAAB
Specification Level: FULL

| Field | Value |
|---|---|
| Specification ID | SPEC-BUG-S4VAAB |
| Feature ID | FEAT-ZLP1PF |
| Specification Level | FULL |
| Status | Draft — TL/SA review required |
| Work item | [BUG-S4VAAB](../records/bugs/BUG-S4VAAB.md) |
| External reference | SLP-184 (staged follow-up; original order bug: TASK-EDS9T5) |
| Workflow Mode | A — payment/checkout/order-lifecycle risk |
| Date | 2026-09-24 |

## Goal

Khi ZaloPay `v2/query` trả `return_code=3` (PROCESSING), khách thấy payment còn đang xử lý bằng
thông điệp an toàn, đúng ngôn ngữ, không bị dẫn dắt bởi `return_message` tự do từ provider.

## System Behavior

- Return luôn query server-to-server; browser redirect parameters không phải payment proof.
- Query code 3 giữ attempt không đổi, không tạo Sales Order, không chạy finalizer. Thông điệp
  pending cố định hiện có được dùng cho mọi `return_message`, kể cả rỗng/thiếu.
- Query code 1 chỉ vào paid path với amount lock và lifecycle guards hiện có; code 2 đi vào
  authoritative failure path hiện có. IPN và retry/reconciliation không đổi.
- `en_US` đọc câu English; `vi_VN` đọc bản dịch tiếng Việt hiện có. Không còn key dùng riêng
  để dịch câu provider “The transaction has not been completed.” trong patch staged.

## Acceptance Criteria

1. `return_code=3` với `return_message` thiếu, rỗng, hay “The transaction has not been completed.”
   đều cho cùng message “Your ZaloPay payment is still being processed. Please check back shortly.”
   ở `en_US`; bản dịch hiện có ở `vi_VN`.
2. Cả ba case không gọi `PaymentAttemptRepository::save`, `recordVerifiedPaid`,
   `recordVerifiedFailure` hoặc `OrderFinalizer::finalizeOrRecover`.
3. Code 1/2, browser checksum mismatch, duplicate Return và IPN-first recovery giữ hành vi
   hiện có; generic `placeOrder` của quote ZaloPay chưa thanh toán vẫn bị guard chặn.
4. Focused regression test + toàn bộ ZaloPay unit suite, PHP lint, PHPCS, DI compile pass; QC
   thanh toán thực tế qua Luma và Mageplaza OSC trước release.

## Scope

`Secomm_ZaloPay` Return pending message, tests và hai module CSV. Không sửa gateway response
contract, payment state machine, IPN, finalizer, guard, schema, config, refund, Mageplaza/vendor.

## Risks / Gate

Một thông điệp sai có thể khiến khách trả lại khi attempt cũ vẫn pending. Payment path là
high-risk: TL/SA Tier 2 phê duyệt spec/plan trước implementation; Code Gate + L3 payment QC
trước release. Không có architecture, database hay API change dự kiến.

## Evidence

[Review staged SLP-184](../evidence/SLP-184/staged-review.md); source baseline tại commit
`b055a3bd24fb2bb710379773b46debf0765128de` cùng staged diff hiện tại.
