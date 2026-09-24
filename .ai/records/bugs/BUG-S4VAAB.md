---
id: BUG-S4VAAB
type: bug
title: Keep ZaloPay pending return messages accurate and bilingual
project_code: SLP
parent: null
mode: A
specification_level: FULL
spec_status: DRAFT
specification_ref: ../../specs/SPEC-BUG-S4VAAB-zalopay-pending-return-message.md
plan_ref: ../../plans/PLAN-BUG-S4VAAB-zalopay-pending-return-message.md
risk: high
status: proposed
created: 2026-09-24
updated: 2026-09-24
external_refs:
  ticket: SLP-184
legacy_ids: []
decisions: []
decision_assessment: none-material
components:
  - Secomm_ZaloPay
source_areas:
  - app/code/Secomm/ZaloPay/Service/ReturnProcessor.php
  - app/code/Secomm/ZaloPay/Test/Unit/Service/ReturnProcessorTest.php
  - app/code/Secomm/ZaloPay/i18n/en_US.csv
  - app/code/Secomm/ZaloPay/i18n/vi_VN.csv
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: b055a3bd24fb2bb710379773b46debf0765128de
last_verified: 2026-09-24
supersedes: []
---

# [SLP][BUG-S4VAAB] Keep ZaloPay pending return messages accurate and bilingual

## Summary

Review phần staged của SLP-184 phát hiện nhánh `ReturnProcessor` cho `v2/query return_code=3`
đã thêm cách hiển thị nguyên `return_message` của provider. Code 3 là PROCESSING, chưa phải
payment failure; thông điệp provider như “The transaction has not been completed.” có thể khiến
khách hiểu rằng giao dịch đã thất bại và thử thanh toán lại. Dòng mới trong `en_US.csv` còn dịch
sang tiếng Việt. Đây là regression trong patch **chưa được duyệt**, không phải bug tạo Sales Order
trước khi thanh toán: invariant đó đã có ở `TASK-EDS9T5`.

## Mini Spec

### Goal

Browser Return hiển thị đúng trạng thái đang xử lý của ZaloPay, an toàn cho khách và đúng locale,
trong khi giữ nguyên payment-first order invariant.

### Expected Behavior

- `v2/query return_code=3`: hiển thị thông điệp pending cố định, đã dịch theo locale; attempt
  không đổi trạng thái và không tạo order, bất kể `return_message` là gì.
- `return_code=1`: tiếp tục các guard amount/identity/lifecycle hiện có rồi mới finalization;
  `return_code=2`: giữ nhánh failure authoritative hiện có.
- Store `en_US` hiển thị English; `vi_VN` hiển thị tiếng Việt.

### Constraints / Rules

- Browser params và provider `return_message` không quyết định money state; chỉ response code
  từ server-to-server `v2/query` quyết định nhánh Return.
- Không đưa raw provider/internal text lên storefront cho trạng thái pending.
- Không thay `OrderFinalizer`, `PaymentAttemptLifecycle`, IPN, guard `placeOrder`, gateway contract
  hoặc third-party code.
- Payment/checkout/order lifecycle là Tier 2 theo `.ai/AGENTS.md` §11–§12.

### Out of Scope

- Thực hiện lại SLP-184 / `TASK-EDS9T5`; thay đổi state machine, retry, refund, schema, config;
  cập nhật các message từ API khác.

### Acceptance Criteria

- AC-001: Code 3 với `return_message` rỗng, thiếu, hoặc mang câu “The transaction has not been
  completed.” đều hiển thị cùng thông điệp pending customer-safe.
- AC-002: Mỗi case AC-001 giữ attempt ACTIVE, không ghi repository và không gọi finalizer.
- AC-003: `en_US` hiển thị English; `vi_VN` hiển thị Vietnamese; không có translation key thừa
  hoặc English key bị dịch sai trong `en_US.csv`.
- AC-004: Paid/failure Return, IPN và generic `placeOrder` guard không regress; ZaloPay unit suite
  pass, Luma/Mageplaza OSC payment QC được ghi evidence trước release.

## Reproduction / Evidence

1. Dùng staged diff hiện tại: cho `query_transaction` trả `return_code=3` và
   `return_message="The transaction has not been completed."`.
2. `ReturnProcessor::process()` throw text provider thay vì text pending cố định; controller
   hiển thị text đó trên cart. Với locale `en_US`, CSV dịch câu này thành tiếng Việt.
3. Review và unit baseline: [staged-review.md](../../evidence/SLP-184/staged-review.md).

Đây là reproduction theo code path; chưa có giao dịch thật qua ZaloPay gateway cho nhánh này.

## Specification / Plan

- Full Spec (draft, chờ TL/SA xác nhận): [SPEC-BUG-S4VAAB](../../specs/SPEC-BUG-S4VAAB-zalopay-pending-return-message.md).
- Implementation plan (draft): [PLAN-BUG-S4VAAB](../../plans/PLAN-BUG-S4VAAB-zalopay-pending-return-message.md).
- Related original payment-first fix: [TASK-EDS9T5](../tasks/TASK-EDS9T5.md).
- Related legacy feature: [FEAT-ZLP1PF](../features/FEAT-ZLP1PF.md).

## Escalation

Chưa implement/duyệt patch staged. TL/SA Tier 2 review Full Spec và plan; sau đó mới chuyển
`spec_status` sang `VALID` và bắt đầu sửa code.
