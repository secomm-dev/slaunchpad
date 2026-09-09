---
id: DEC-BUG4WYXCB-003
title: 'VNPAY: quote-based intent inline trong 4 file có sẵn — order chỉ tạo sau khi payment thành công + không gửi email (supersedes DEC-BUG4WYXCB-002)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-08
verified_against_commit:
supersedes: [DEC-BUG4WYXCB-002]
superseded_by: DEC-BUG4WYXCB-005
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY quote-based intent — inline, no new files, no email

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08 — user acting as SA/TL in chat. Supersedes DEC-BUG4WYXCB-002 (pending_payment minimal fix). Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

Sau hai vòng duyệt approach (DEC-001 quote-based qua class service riêng → DEC-002 pending_payment + core cron), requirement owner chốt lại yêu cầu gốc và các ràng buộc mới: (a) **đúng literal** — "chưa thanh toán thành công thì chưa tạo order" (tắt tab / rớt mạng = không tồn tại gì), phương án pending_payment của DEC-002 không đáp ứng vì order row vẫn được tạo rồi thành Canceled; (b) **không tạo file mới nào** — chỉ sửa các file có sẵn của `Secomm_VNPAY`; (c) **không gửi bất kỳ email nào** cho VNPAY orders.

## Decision (accepted)

Quote-based payment intent, inline trong các file có sẵn (6 file sửa, 0 file mới):

1. `Controller/Order/Info.php` — chuyển thành intent endpoint session-based: validate quote, sinh `vnp_TxnRef` per attempt (`Q{quoteId}T{time}R{rand}`) lưu `quote_payment additional_information`, build URL + `vnp_ExpireDate` 15'. Đóng lỗ hổng `order_id` cũ.
2. `vnpay-method.js` — bỏ gọi place-order REST; gọi `/paymentvnpay/order/info` rồi redirect.
3. `Controller/Order/Ipn.php` (SSOT) + `Controller/Order/Pay.php` (fallback khi IPN không tới) — tra quote theo ref, so amount với `vnp_request_amount`, `placePaidOrder()` inline (placeOrder → status config → chặn email → invoice `CAPTURE_OFFLINE` → đánh dấu ref). Idempotency: check order-theo-ref + guard `is_active` + row-lock của `placeOrder`. Kèm fix các defect cũ: bỏ `setTotalPaid`-on-cancel, `catch (\Exception)`, `exit` sau JSON, `CAPTURE_OFFLINE`.
4. `Model/vnpay.php` — override `order()`: `setCanSendNewEmailFlag(false)`; trong `placePaidOrder` thêm `setEmailSent(1)` để cron `sales_send_order_emails` không gửi lại → **không email nào cho VNPAY orders**.
5. `i18n/en_US.csv` — +7 string.

Chi tiết đầy đủ: `.ai/records/bugs/BUG-4WYXCB.md` (Embedded Full Spec).

## Alternatives considered

- **DEC-BUG4WYXCB-002 (pending_payment + core cron)** — superseded: không đạt literal requirement "0 order khi chưa trả tiền".
- **Quote-based intent qua class service riêng (`VnpayOrderCreationService` + Intent + Builder + Exception)** — rejected: owner yêu cầu không tạo file mới; chấp nhận logic inline trong controller + mirror giữa Ipn/Pay (trái khuyến nghị "controllers thin" — là lựa chọn có chủ đích của owner, ghi nhận cho TL review).

## Consequences / Risks

- Controller dày hơn khuyến nghị kiến trúc; Ipn/Pay có code mirror — phải sửa đồng bộ (đã đánh dấu mirror-comment).
- Payment thành công nhưng `placeOrder` lỗi (hết stock / quote đổi giữa chừng) → không có order + log critical + hoàn tiền thủ công; `vnp_ExpireDate` 15' giới hạn window.
- Không email nào cho VNPAY orders — kể cả đơn đã trả tiền (yêu cầu tường minh của owner).
- Idempotency không có lock phân tán — dựa vào check ref + `is_active` + row-lock DB của `placeOrder`; cần QC T5 (IPN + return song song).

## Approval

- [x] SA/TL approve → `status: accepted` + `approval_date` 2026-09-08 (user acting as SA/TL in chat)
- [ ] TL code review (6 file) → QC sandbox theo Test Plan BUG-4WYXCB