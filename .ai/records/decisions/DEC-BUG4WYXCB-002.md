---
id: DEC-BUG4WYXCB-002
title: 'VNPAY abandoned orders: minimal fix — order-first `pending_payment` + core `sales_clean_orders` cron (supersedes quote-based intent DEC-BUG4WYXCB-001)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-08
verified_against_commit:
supersedes: [DEC-BUG4WYXCB-001]
superseded_by: DEC-BUG4WYXCB-003
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY minimal fix — pending_payment + core cron

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08 — user acting as SA/TL in chat. Supersedes DEC-BUG4WYXCB-001 (quote-based payment intent). Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

DEC-BUG4WYXCB-001 chốt approach quote-based intent (order chỉ tạo sau khi VNPAY xác nhận). Khi triển khai draft (4 file mới: Intent controller, RequestBuilder, OrderCreationService, Exception), requirement owner đánh giá **over-engineered so với nhu cầu** ("chỉ muốn fix đơn giản, sao lại tạo ra nhiều file mới") và rút luồng.

## Decision (proposed → accepted)

Giữ nguyên luồng place-order, đổi trạng thái order sang `pending_payment` để **cron core tự hủy** đơn abandon — pattern chuẩn Magento cho redirect gateway (PayPal Express) và chính là pattern in-house của `Secomm_ZaloPay\Gateway\Command\InitializeCommand` / `Secomm_MoMo`:

1. `Model/vnpay.php`: `_isInitializeNeeded = true` + `initialize()` set `STATE_PENDING_PAYMENT` + `is_notified=false` + `canSendNewEmailFlag(false)` (không gửi email xác nhận cho đơn chưa trả tiền).
2. `Controller/Order/Ipn.php`: guard confirm nhận `pending_payment` (giữ `pending` cho order legacy); fix kèm 3 defect — bỏ `setTotalPaid` trên order canceled, `catch (\Exception)`, invoice `CAPTURE_ONLINE` → `CAPTURE_OFFLINE` (offline method `_canCapture=false` — bắt buộc `pay()` ngay để order rời `pending_payment` trước cron lifetime); gửi order email qua `OrderSender` sau confirm thành công.
3. `Controller/Order/Pay.php`: `restoreCart()` nhận thêm `pending_payment`.
4. Không tạo file mới, không xóa file, không đổi JS/i18n/schema. Config vận hành: admin đặt `sales/orders/delete_pending_after` = 30–60 phút.

Chi tiết: `.ai/records/bugs/BUG-4WYXCB.md` (Embedded Full Spec, đã update theo approach này).

## Alternatives considered

- **Quote-based intent (DEC-BUG4WYXCB-001)** — rejected: đạt được "0 order row khi chưa trả tiền" nhưng phải thêm Intent/Service/Builder + rewrite JS checkout; owner đánh giá vượt nhu cầu anti-spam.
- **Cron module riêng cancel order `pending`** — rejected: không sửa gốc rễ (state sai), phải maintain cron riêng trong khi cron core có sẵn.

## Consequences / Risks

- Order row VNPAY vẫn được tạo ngay khi Place Order rồi thành `Canceled` sau lifetime (không phải "0 row" như 001) — ngưỡng chấp nhận của owner.
- Order `pending` legacy trước fix không được cron dọn (status không match) — Out of Scope.
- Paid-order correctness phụ thuộc `CAPTURE_OFFLINE` fix — nếu revert nhầm, cron sẽ hủy nhầm đơn đã trả tiền; đã ghi trong record.
- Cần verify cron chạy trong môi trường (cron_schedule có `sales_clean_orders`).

## Approval

- [x] SA/TL approve → `status: accepted` + `approval_date` 2026-09-08 (user acting as SA/TL in chat)
- [ ] TL code review (3 file) → QC sandbox theo Test Plan BUG-4WYXCB