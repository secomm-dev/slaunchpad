---
id: DEC-BUG4WYXCB-004
title: 'VNPAY: quote-based intent gom vào VnpayPaymentService (thin controllers, idempotent + lock) — order chỉ tạo sau khi payment thành công, không email (supersedes DEC-BUG4WYXCB-003)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-08
verified_against_commit:
supersedes: [DEC-BUG4WYXCB-003]
superseded_by: DEC-BUG4WYXCB-005
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY quote-based intent qua VnpayPaymentService

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08 — user acting as SA/TL in chat. Supersedes DEC-BUG4WYXCB-003 (inline trong file có sẵn). Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

DEC-003 triển khai inline trong các file có sẵn. Owner tự vá tiếp theo hướng đó rồi phát hiện lỗ hổng kiến trúc của approach inline: Ipn order-based `loadByIncrementId` không bao giờ thấy order (order chưa tồn tại lúc payment), Pay return success không tạo order → **khách trả tiền xong không có order**. Owner kết luận "sửa trực tiếp ở file nhiều rủi ro" và yêu cầu solution tối ưu, giữ behavior đã chốt: chưa thanh toán thành công = không có order, không gửi email.

## Decision (accepted)

Behavior giữ nguyên DEC-001/003 (quote-based intent), kiến trúc gom về một service:

1. `Model/VnpayPaymentService` — toàn bộ logic: `createAttempt()` (validate + reserve order id MỚI mỗi attempt + lưu ref/amount lên quote payment `additional_information` + build URL + `vnp_ExpireDate` 15'), `confirmPayment($ref, $amount)` (idempotent: `loadByIncrementId` → check trong DB lock `VNPAY_CONFIRM_*` → `findQuoteByRef(reserved_order_id)` → amount check → `placeOrder` → status theo config → `setCanSendNewEmailFlag(false)` + `setEmailSent(1)` → invoice `CAPTURE_OFFLINE`), `markFailed()`.
2. `Exception/VnpayConfirmationException` — mang RspCode (01/04/99) + Message trả VNPAY.
3. `Info.php` / `Ipn.php` / `Pay.php` thành thin controller gọi service — hết mirror-duplication Ipn/Pay.
4. `vnp_TxnRef` = reserved order id (đọc được trên portal VNPAY; sau khi order tạo, increment id == ref nên `loadByIncrementId` là check idempotency tự nhiên); re-reserve mỗi attempt để retry không trùng ref.
5. JS: `setPaymentInformationAction` (không place-order) → POST `/paymentvnpay/order/info` (đợi select xong, `dataType: 'json'`) → redirect `response.url`.
6. Email: `Model/vnpay.php::order()` override + `setEmailSent(1)` — không email nào cho VNPAY orders.

Files: 2 new (Service, Exception) + 5 modified (Info, Ipn, Pay, vnpay.php, vnpay-method.js) + i18n en_US.csv. Chi tiết: `.ai/records/bugs/BUG-4WYXCB.md`.

## Alternatives considered

- **DEC-003 inline (no new files)** — superseded: owner tự thử và xác nhận rủi ro (logic thiếu mảnh, mirror lệch); chi phí fix lỗi phát sinh cao hơn chi phí 1 service class.
- **pending_payment + core cron (DEC-002)** — không đạt literal requirement (đã loại từ DEC-003).

## Consequences / Risks

- Idempotency: order-theo-ref + DB lock (lock.provider=db trong env.php) — QC T5 (IPN + return song song) bắt buộc.
- Payment thành công nhưng `placeOrder` lỗi → log critical + hoàn tiền thủ công (SOP); `vnp_ExpireDate` 15' giới hạn window.
- `setEmailSent(1)` là mark dữ liệu (email không hề gửi) — chấp nhận có chủ đích để chặn cron email; ghi nhận cho TL review.
- Reserved order id "đốt" mỗi attempt (kể cả attempt abandon) — increment id tăng nhanh hơn số order thật; chấp nhận.

## Approval

- [x] SA/TL approve → `status: accepted` + `approval_date` 2026-09-08 (user acting as SA/TL in chat: "bạn làm cho tôi đi" sau khi thấy solution)
- [ ] TL code review (7 file) → QC sandbox theo Test Plan BUG-4WYXCB
