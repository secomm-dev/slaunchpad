---
id: DEC-BUG4WYXCB-001
title: 'VNPAY: order created only after successful payment — quote-based payment intent replaces order-first offline flow'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-08
verified_against_commit:
supersedes: []
superseded_by: DEC-BUG4WYXCB-002
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY quote-based payment intent (order only after successful payment)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08 — approved by user acting as SA/TL in chat (precedent DEC-TASKZ132WA-001): approach quote-based intent + scope gọn trong Secomm_VNPAY (không module mới) + loại Secomm_PaymentCore (FEAT-CSWYEJ) khỏi phương án. TL code review vẫn bắt buộc trước merge (§8.3/§11). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

BUG-4WYXCB: method `vnpay` khai báo `_isOffline = true` → order tạo ngay khi Place Order (status `pending`), trước redirect sang VNPAY. Khách abandon (tắt tab / rớt mạng / timeout / hủy) thì không tồn tại cơ chế cancel nào: IPN chỉ fire khi có kết quả giao dịch; browser return chỉ restore quote; module không có cron; cron core `sales_clean_orders` chỉ cancel `pending_payment` (CleanExpiredOrders.php:60) — order `pending` nằm vĩnh viễn, kèm email xác nhận gửi cho giao dịch chưa trả tiền. Root cause là **thiết kế order-first của method offline áp nhầm cho redirect gateway**, cộng 4 defect code (setTotalPaid trên order canceled; `catch (Exception)` thiếu `\`; amount check lệch `baseGrandTotal` vs amount convert VND; `Info.php` không check quyền theo `order_id`).

## Decision (proposed)

Đổi `Secomm_VNPAY` sang **quote-based payment intent — order chỉ được tạo sau khi VNPAY xác nhận thành công**:

1. Renderer VNPAY không gọi place-order REST; gọi `POST /paymentvnpay/order/intent` (session-based) → validate quote → sinh `vnp_TxnRef` unique per attempt (lưu `quote_payment additional_information`) → build URL + `vnp_ExpireDate` (config `expire_minutes`, default 15).
2. IPN là **source of truth**: verify hash + amount === amount-at-intent → `VnpayOrderCreationService::createFromQuote()` idempotent (placeOrder từ quote → total_paid + status config → invoice `CAPTURE_OFFLINE`).
3. Browser return mang ref, gọi cùng service khi order chưa tồn tại (fallback — quyết định Q1, khuyến nghị chấp nhận vì local/sandbox không nhận được IPN).
4. Mapping ref ↔ order qua `additional_information`, không tạo bảng mới, không schema migration.
5. Xóa `Controller/Order/Info.php` (đóng lỗ hổng authorization).
6. Scope: mọi thay đổi nằm gọn trong `Secomm_VNPAY` — không tạo module mới, không schema migration. `Secomm_PaymentCore` (FEAT-CSWYEJ) không còn sử dụng — không tham chiếu trong approach.

Chi tiết đầy đủ (flow, AC-001..012, files, test plan): `.ai/records/bugs/BUG-4WYXCB.md` (Embedded Full Spec).

## Alternatives considered

- **Giữ order-first + cron module riêng cancel `pending` sau X phút** — rejected: không sửa gốc rễ (vẫn tạo order cho giao dịch chưa trả tiền), ngữ nghĩa state `pending` gây hiểu nhầm cho CS, phải maintain cron riêng, và `Ipn.php` vẫn phải sửa 4 defect — chi phí cộng thêm để làm đúng không đáng kể.
- **Order-first với status `pending_payment` + cron core** (`delete_pending_after`, pattern PayPal Express trong checkout) — rejected là lựa chọn chính vì không thỏa yêu cầu nghiệp vụ đã chốt: *chưa trả tiền thành công thì không có order*; order row vẫn được tạo và để lại canceled history. (Giữ làm fallback tham chiếu nếu quote-based flow bị bác bỏ ở review.)
- **Order-after-payment với mapping bảng riêng + lock phân tán** — rejected ở giai đoạn này: thêm schema migration (mở rộng phạm vi Tier-2 DB) không cần thiết cho volume hiện tại; guard `is_active` của `placeOrder` + ref check đủ cho race IPN/return.

## Consequences / Risks

- Intent endpoint phải replicate server-side validation của place-order chuẩn (terms, address, stock) — liệt kê tường minh khi implement, [BLOCK] re-validate client input.
- Payment thành công nhưng không place được order (hết stock / quote đổi giữa chừng) → không có order, cần hoàn tiền thủ công: log critical + SOP đối soát; giảm xác suất bằng `vnp_ExpireDate` ngắn (15 phút).
- Không còn order row cho giao dịch bỏ dở → mất audit trail các attempt ở cấp order; bù bằng log `vnpay` logger (Q3: không làm report riêng giai đoạn này).

## Approval

- [x] SA/TL approve approach + A1–A6 (BUG-4WYXCB §Resolved Questions) → `status: accepted` + `approval_date` 2026-09-08 (user acting as SA/TL in chat)
- [ ] Implement theo BUG-4WYXCB Embedded Full Spec → AI pre-review → **TL code review (bắt buộc)** → QC e2e + payment test sandbox