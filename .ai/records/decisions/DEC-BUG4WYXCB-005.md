---
id: DEC-BUG4WYXCB-005
title: 'VNPAY: quote-based intent do owner tự implement inline trong Info/Ipn/Pay/JS — vnp_TxnRef = reserved order id, placeOrder tại Ipn/Pay sau khi payment thành công (supersedes DEC-BUG4WYXCB-003)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-08
created: 2026-09-08
last_verified: 2026-09-09
verified_against_commit:
supersedes: [DEC-BUG4WYXCB-003]
superseded_by:
work_items: [BUG-4WYXCB]
---

# Decision Record: VNPAY quote-based intent — owner inline implementation

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-08, updated 2026-09-09 — owner tự implement, AI không sửa code, chỉ cập nhật records. Supersedes DEC-BUG4WYXCB-003. Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

Sau các vòng duyệt approach (DEC-001..003), một approach trung gian tách logic vào service class riêng đã được thử rồi bị owner revert — owner kết luận sửa logic rải rác nhiều rủi ro và **tự tay hoàn thiện toàn bộ** theo hướng inline trong các controller có sẵn. State cuối cùng là implementation của owner (không dùng class/service bên ngoài).

## Decision (accepted)

Quote-based payment intent, do owner implement inline (4 file sửa + `Model/Vnpay.php` chặn email):

1. `Info.php`: load quote từ checkout session → `setMethod('vnpay')` → `reserveOrderId()` (chỉ khi chưa có) → `collectTotals()->save()` → build URL với `vnp_TxnRef = reserved_order_id`, KHÔNG gửi `vnp_ExpireDate` → trả chuỗi URL thuần.
2. JS: `setPaymentInformationAction` (không place-order REST) → POST Info → `window.location.replace(url)`.
3. `Ipn.php` (SSOT): verify hash → nếu order chưa tồn tại theo increment id → tra quote active theo `reserved_order_id` → code `00` → `CartManagement::placeOrder()` → chạy confirm logic gốc (amount check vs `baseGrandTotal`, status theo config, invoice `CAPTURE_ONLINE`); mã khác → respond `00`.
4. `Pay.php` (return fallback): cùng tra quote → `placeOrder()` → set session success state → success page; fail → restore cart → cart.
5. `Model/Vnpay.php::order()`: `setCanSendNewEmailFlag(false)` — chặn email xác nhận cho VNPAY orders.

## Alternatives considered

- **Approach tách service class riêng** — rejected: owner prefer inline tự implement; các file service/exception trung gian đã bị revert và xóa khỏi module.
- **DEC-002 pending_payment + core cron** — không đạt literal requirement (đã loại từ DEC-003).
- `vnp_ExpireDate` 15' — loại khỏi flow (gây sandbox code=15 do timezone UTC vs GMT+7; nếu muốn dùng lại phải format giờ Asia/Ho_Chi_Minh).

## Consequences / Risks (danh sách cho TL review — chi tiết trong BUG-4WYXCB §Known limitations)

1. Amount check so `baseGrandTotal` với amount đã convert VND — chỉ đúng khi base currency = VND.
2. Invoice `CAPTURE_ONLINE` cho offline method — invoice không `pay()`.
3. Nhánh fail IPN `setTotalPaid()` trên order canceled.
4. `catch (Exception)` thiếu `\` + `echo` không `exit` ở Ipn.
5. Không idempotency lock — QC bắt buộc test IPN + return song song.
6. Retry cùng quote trùng `reserved_order_id` → VNPAY có thể từ chối trùng `vnp_TxnRef`.
7. Email async: nếu bật `sales_email/general/async_sending`, cron có thể vẫn gửi (chỉ chặn sync path).

## Post-approval updates

- **2026-09-09 — rename module**: `Vnpayment_VNPAY` → `Secomm_VNPAY` (thống nhất vendor prefix `Secomm_`): move `app/code/Vnpayment/VNPAY` → `app/code/Secomm/VNPAY`, namespace + registration.php + `app/etc/config.php` + JS/layout references; `Model/vnpay.php` → `Model/Vnpay.php` (class `Vnpay`). Config path `payment/vnpay/*` + payment code `vnpay` không đổi.
- **2026-09-09 — dọn code không dùng**: các file code trung gian của approach đã revert được xóa khỏi module.

## Approval

- [x] Owner chấp nhận implementation của mình → `status: accepted` + `approval_date` 2026-09-08
- [ ] TL code review (chú ý 7 risks trên) → QC sandbox T1–T8 theo BUG-4WYXCB
