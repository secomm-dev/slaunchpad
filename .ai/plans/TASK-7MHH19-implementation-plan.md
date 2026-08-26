# TASK-7MHH19 Implementation Plan — Continue Payment (button + controller)

| Field | Value |
|---|---|
| Specification | tickets/TASK-7MHH19-paymentcore-continue-payment.md (`## Mini Spec`) · canonical parent SPEC-FEAT-CSWYEJ §4.1/§4.8, AC-004→AC-007/AC-014 |
| Decisions | DEC-FEATCSWYEJ-003 (refresh window mỗi lần retry) · DEC-004 (adapter chỉ cần URL) |

> **Mode B** · Tier 1 · Status: **Retro-canonical** — Dev complete sau 3 runtime fix (reserved word, layout placement, getOrder source); button render verified qua QC.

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Tên action? | `Retry` — `Continue` là PHP reserved word (fatal lúc di:compile) | QC session 2026-08-25 |
| Block lấy order đâu? | `Registry::registry('current_order')` — Template không có getOrder(); sales_order_view controller đăng ký sẵn | QC session 2026-08-25 |
| Layout gắn đâu? | Thẳng vào `referenceContainer name="content"` after `sales.order.info` — gắn làm con của sales.order.info KHÔNG render (template không render children; đã verify bằng die() probe) | QC session 2026-08-25 |
| CSRF? | POST form + form_key (HttpPostActionInterface + default form validation) | spec §4.8 |
| Refresh window khi retry? | Có — controller gọi `refreshExpiry` sau khi adapter cấp URL (DEC-003, đóng race cancel-giữa-lúc-thanh-toán) | DEC-FEATCSWYEJ-003 |
| Cache? | `cacheable="false"` — visibility phụ thuộc state + expiry | — |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Guard service (9 deny reasons, dùng cho cả button + controller) | Model/Lifecycle/CanContinuePayment.php | ✅ |
| 2 | Controller (CSRF + ownership + guard + redirect + refresh window) | Controller/Payment/Retry.php | ✅ (rename từ Continue.php) |
| 3 | Block + template Hyvä + layout | Block/ContinuePaymentButton.php · view/frontend/** | ✅ (fix layout + getOrder runtime) |
| 4 | Expiry refresh method | CanContinuePayment::refreshExpiry() | ✅ (DEC-003) |
| 5 | Route + i18n | etc/frontend/routes.xml · i18n vi/en | ✅ |

## Remaining steps (human)

1. QC S-2/S-3 (nút hiện đúng điều kiện; click redirect sandbox token mới).
2. QC S-8 (gọi trực tiếp URL retry cho order hết hạn/người khác/thiếu form_key → deny).
3. F5 sau khi thanh toán thành công → nút biến mất (state + total_due=0).

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Deny server-side không chỉ ẩn UI | Retry controller denyReason + ownership 404-like |
| Ownership + CSRF | AC-007 code path; S-8 QC pending |
| Disable core → nút biến mất | REASON_CORE_DISABLED branch |
