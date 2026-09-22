---
id: TASK-7HP4R0
type: task
title: '[MoMo][MOMO-01] Convert redirect checkout to payment-first order finalization'
project_code: SLP
parent: NONE
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-7HP4R0-momo-payment-first-order-finalization.md
risk: high
status: ready_for_review
created: 2026-09-18
updated: 2026-09-18
legacy_ids: []
decisions: []
decision_assessment: architecture-material
components:
  - Secomm_MoMo
source_areas:
  - app/code/Secomm/MoMo/Controller/Payment/Redirect.php
  - app/code/Secomm/MoMo/Controller/Payment/Notify.php
  - app/code/Secomm/MoMo/Controller/Payment/ReturnAction.php
  - app/code/Secomm/MoMo/Gateway/Request/CreateOrderBuilder.php
  - app/code/Secomm/MoMo/Gateway/Validator/NotifyValidator.php
  - app/code/Secomm/MoMo/Service/ReturnProcessor.php
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: false
verified_against_commit: 87f4db4459c67fe92ce6ba99a61c4d833ebde7bd
last_verified: 2026-09-18
supersedes: []
external_refs:
  - github:thanhle74/slaunchpad#3
---

# [SLP][TASK-7HP4R0] [MoMo][MOMO-01] Convert redirect checkout to payment-first order finalization

## Bối cảnh (Context)

GitHub issue [#3](https://github.com/thanhle74/slaunchpad/issues/3) (lane MoMo, epic #1, phụ thuộc #2 + #6 —
đã CLOSED) yêu cầu chuyển `Secomm_MoMo` từ flow order-first redirect sang payment-first:

> `NO verified MoMo payment → NO Magento Sales Order`

Flow hiện tại (order-first, tại base `87f4db44`):
- `Controller/Payment/Redirect::execute()` đọc `checkoutSession->getLastOrderId()`, load Sales Order
  và gửi `order increment id` cho MoMo **trước khi** provider xác nhận thanh toán.
- `Controller/Payment/Notify` load lại order đã tồn tại theo increment id rồi chuyển khỏi `pending_payment`.
- JS renderer gọi `placeOrder()` chuẩn của Magento (tạo order client-driven) rồi mới redirect.

Target contract khớp accepted redirect-payment policy của ZaloPay (FEAT-ZLP1PF / DEC-FEATZLP1PF-003):
Active Quote → snapshot contract + attempt row → MoMo create API → redirect — KHÔNG có Sales Order;
Notify (IPN) là success path có thẩm quyền; một `OrderFinalizer` canonical tạo order đúng một lần,
bind attempt ↔ order, invoice/capture bằng native Magento services; Return chỉ là UX/recovery.

## Implementation summary (fill at completion)

Đã chuyển `Secomm_MoMo` sang payment-first (chi tiết kỹ thuật xem
`../../plans/TASK-7HP4R0-implementation-plan.md`, spec, và handoff comment trên issue #3 —
[comment handoff](https://github.com/thanhle74/slaunchpad/issues/3#issuecomment-5726623439)):

- Attempt row `secomm_momo_payment_attempt` (db_schema, 22 cột, UNIQUE
  `order_ref`/`request_id`/`order_id`) — freeze amount/currency/contract fingerprint trước
  khi gọi MoMo; trạng thái qua state machine `initiated→active→paid→finalized` (terminal states
  không có cạnh ra; paid không regress).
- Verify boundary: Notify/IPN (13-field HMAC + identity + amount) và Return (re-query
  `v2/query`, 7002 non-terminal) đều server-side; browser params không đáng tin.
- `OrderFinalizer` canonical: `FOR UPDATE` → single-use grant (plugin
  `CartManagementPlaceOrderGuard` trên `QuoteManagement::placeOrder`) → 1 order →
  invoice/capture → bind → email claim; duplicate → recover bound order.
- Durable money-real: fail sau `paid` giữ `PAID` + IPN HTTP 500 (MoMo retry = recovery);
  anomaly → quarantine `requires_reconciliation` + mã (amount/contract/identity conflict).
- Validation: unit 105 tests / 278 assertions OK; PHPCS Magento2 0 errors; setup:upgrade
  tạo bảng đúng schema; setup:di:compile OK. Evidence: `.ai/evidence/TASK-7HP4R0/`.
- Trạng thái: **READY_FOR_REVIEW** (chưa commit — theo CLAUDE.md; chờ TL review).

## Lessons (fill at completion)
