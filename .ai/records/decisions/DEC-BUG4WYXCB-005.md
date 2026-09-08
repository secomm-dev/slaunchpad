---
id: DEC-BUG4WYXCB-005
title: 'VNPAY: quote-based intent do owner tự implement inline trong Info/Ipn/Pay/JS — vnp_TxnRef = reserved order id, placeOrder tại Ipn/Pay sau khi payment thành công (supersedes DEC-BUG4WYXCB-004)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-08
verified_against_commit:
supersedes: [DEC-BUG4WYXCB-004]
superseded_by:
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY quote-based intent — owner inline implementation

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08 — owner tự implement, AI không sửa code, chỉ cập nhật records. Supersedes DEC-BUG4WYXCB-004 (VnpayPaymentService approach — service + exception giờ là file mồ côi). Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

DEC-004 (VnpayPaymentService + thin controllers) bị owner revert để tự viết theo hướng controller inline. Sau khi owner thử, AI hỗ trợ gắn lại thin controllers + sửa lỗi runtime (`LockBackendInterface` không tồn tại trong 2.4.x → `LockManagerInterface`) và lỗi sandbox code=15 (`vnp_ExpireDate` gửi theo giờ server UTC lệch 7h so với giờ VNPAY → giao dịch sinh ra đã hết hạn). Owner cuối cùng tự hoàn thiện toàn bộ theo hướng của mình và chốt: **không sửa code nữa, chỉ cập nhật records**.

## Decision (accepted)

Quote-based payment intent, do owner implement inline (4 file: Info, Ipn, Pay, vnpay-method.js + `Model/vnpay.php` chặn email):

1. `Info.php`: load quote từ checkout session → `setMethod('vnpay')` → `reserveOrderId()` (chỉ khi chưa có) → `collectTotals()->save()` → build URL với `vnp_TxnRef = reserved_order_id`, KHÔNG gửi `vnp_ExpireDate` → trả chuỗi URL thuần.
2. JS: `setPaymentInformationAction` (không place-order REST) → POST Info → `window.location.replace(url)`.
3. `Ipn.php` (SSOT): verify hash → nếu order chưa tồn tại theo increment id → tra quote active theo `reserved_order_id` → code `00` → `CartManagement::placeOrder()` → chạy confirm logic gốc (amount check vs `baseGrandTotal`, status theo config, invoice `CAPTURE_ONLINE`); mã khác → respond `00`.
4. `Pay.php` (return fallback): cùng tra quote → `placeOrder()` → set session success state → success page; fail → restore cart → cart.
5. `Model/vnpay.php::order()`: `setCanSendNewEmailFlag(false)` — chặn email xác nhận cho VNPAY orders.

## Alternatives considered

- **DEC-004 `VnpayPaymentService` + thin controllers** — superseded: owner prefer inline, tự implement hoàn chỉnh. File service + exception thành mồ côi (không ai gọi) — đề nghị xóa trước commit.
- **DEC-002 pending_payment + core cron** — không đạt literal requirement.
- `vnp_ExpireDate` 15' — loại khỏi flow (gây sandbox code=15 do timezone UTC vs GMT+7; nếu muốn dùng lại phải format giờ Asia/Ho_Chi_Minh).

## Consequences / Risks (danh sách cho TL review — chi tiết trong BUG-4WYXCB §Known limitations)

1. Amount check so `baseGrandTotal` với amount đã convert VND — chỉ đúng khi base currency = VND.
2. Invoice `CAPTURE_ONLINE` cho offline method — invoice không `pay()`.
3. Nhánh fail IPN `setTotalPaid()` trên order canceled.
4. `catch (Exception)` thiếu `\` + `echo` không `exit` ở Ipn.
5. Không idempotency lock — QC bắt buộc test IPN + return song song.
6. Retry cùng quote trùng `reserved_order_id` → VNPAY có thể từ chối trùng `vnp_TxnRef`.
7. Email async: nếu bật `sales_email/general/async_sending`, cron có thể vẫn gửi (chỉ chặn sync path).

## Approval

- [x] Owner chấp nhận implementation của mình → `status: accepted` + `approval_date` 2026-09-08
- [ ] TL code review (chú ý 7 risks trên) → QC sandbox T1–T8 theo BUG-4WYXCB
- [ ] Dọn file mồ côi: `Model/VnpayPaymentService.php`, `Exception/VnpayConfirmationException.php` (chờ owner xác nhận)
