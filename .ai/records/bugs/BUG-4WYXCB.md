---
id: BUG-4WYXCB
type: bug
title: Create VNPAY Order Only After Successful Payment
project_code: SLP
parent:
external_refs: {}
legacy_ids: []
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: Embedded Full Spec
risk: high
status: in_progress
created: 2026-09-08
updated: 2026-09-09
ticket_ref:
affects_version: Magento 2.4.8-p5
decisions: [DEC-BUG4WYXCB-005]
decision_assessment: material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/VNPAY
source_areas:
  - payment
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-09
supersedes: []
---

# [SLP][BUG-4WYXCB] Create VNPAY Order Only After Successful Payment

<!-- CANONICAL RECORD — Mode A (Tier-2: payment logic). Embedded Full Spec — không sinh spec/plan riêng (records README). -->
<!-- Spec VALID 2026-09-08 — user acting as SA/TL in chat (precedent DEC-TASKZ132WA-001). Approach cuối = DEC-BUG4WYXCB-005: quote-based intent do OWNER tự implement inline trong Info/Ipn/Pay/JS. 2026-09-09: module rename Vnpayment_VNPAY → Secomm_VNPAY (spec viết lại theo module mới). TL code review vẫn bắt buộc trước merge (§8.3/§11). -->

## Summary

Phương thức VNPAY (`Secomm_VNPAY`) khai báo `_isOffline = true` nên **order được tạo ngay khi khách bấm Place Order** (status `pending`), rồi mới redirect sang VNPAY. Khi khách abandon (tắt tab, rớt mạng, timeout, hủy giao dịch) **không có bất kỳ cơ chế nào cancel order**: IPN chỉ fire khi có kết quả giao dịch, module không có cron, cron core `sales_clean_orders` chỉ nhận status `pending_payment` trong khi order VNPAY là `pending`. Hệ quả: order rác `pending` tích tụ vĩnh viễn, tăng increment ID, và gửi email xác nhận cho giao dịch chưa trả tiền. Fix cuối (owner implement): **quote-based payment intent** — `vnp_TxnRef` = reserved order id của QUOTE, order chỉ được `placeOrder()` ở Ipn/Pay sau khi VNPAY xác nhận thanh toán thành công.

## Mini Spec

### Goal

Giao dịch VNPAY chưa thanh toán thành công thì **không tồn tại order**. Order chỉ xuất hiện khi VNPAY xác nhận (`vnp_ResponseCode = '00'`).

### Expected Behavior

- Bấm thanh toán VNPAY tại OSC → tạo payment attempt trên quote (reserve order id) + redirect sang VNPAY; `sales_order` không có row mới.
- Trả tiền thành công → order được tạo từ quote (tại Ipn hoặc Pay, whichever tới trước), invoice, session success.
- Fail / hủy / abandon → không order, giỏ hàng giữ nguyên.

### Constraints / Rules

- Không modify core Magento, `Mageplaza_*` (OSC), `Mollie`, các `Secomm_*` khác.
- Implementation do owner tự viết inline trong `Info.php` / `Ipn.php` / `Pay.php` / `vnpay-method.js` — không class service, không plugin, không schema migration.
- `Secomm_PaymentCore` (FEAT-CSWYEJ) đã bị loại (owner confirm 2026-09-08: không còn sử dụng).
- Hash `hash_hmac('sha512')` + `ksort` giữ nguyên thuật toán; `vnp_TxnRef` = reserved order id của quote.
- Message source là English; tiếng Việt dịch qua `i18n/vi_VN.csv` (BR-001).

### Out of Scope

- Dọn order `pending` legacy đã tồn tại trong DB trước fix.
- Idempotency lock phân tán / bảng attempt riêng.

### Acceptance Criteria

- AC-001: Bấm thanh toán VNPAY → redirect sang VNPAY, `sales_order` **không có row mới**.
- AC-002: Pay success (`00`) → order tạo từ quote đúng 1 lần (tại Ipn hoặc Pay), invoice, `total_paid` đúng, session success state set.
- AC-003: Fail/hủy tại VNPAY (VD code `24`) → **0 order**, cart restore được, retry OK.
- AC-004: Abandon (tắt tab / rớt mạng / timeout) → **0 order vĩnh viễn**.
- AC-005: Message source tiếng Anh; `vi_VN.csv` map ngược sang tiếng Việt (BR-001).
- AC-006: Regression — JS các method khác, Mollie, OSC validation không đổi.

## Steps to Reproduce

1. Thêm sản phẩm vào cart → checkout OSC → chọn VNPAY → bấm Place Order → sang sandbox VNPAY.
2. Tắt tab (hoặc rớt mạng / để hết thời gian / hủy giao dịch mà không quay lại).
3. Admin → Sales → Orders: order mới xuất hiện status `pending`, không bao giờ tự `canceled`.
4. Lặp N lần → N order `pending`.

## Expected Behavior

Chưa thanh toán thành công → không tồn tại order nào.

## Actual Behavior

Mỗi lần Place Order tạo 1 order `pending` + email xác nhận; abandon để lại order `pending` vĩnh viễn.

## Root Cause

**Thiết kế order-first của method offline áp nhầm cho redirect gateway.** `_isOffline = true` khiến order submit ngay khi place order, trước redirect — trong khi hành vi đúng cho redirect gateway là order chỉ tồn tại sau khi xác nhận thanh toán.

## Final Implementation (owner, 2026-09-08; rename 2026-09-09)

> IMPLEMENTED bởi owner theo hướng controller inline — DEC-BUG4WYXCB-005. TL code review bắt buộc trước merge (§8.3/§11).

**Luồng:** JS `setPaymentInformationAction` (không place-order) → POST `/paymentvnpay/order/info` → `Info.php` load quote từ session, `setMethod('vnpay')`, `reserveOrderId()` (nếu chưa có), trả về URL với `vnp_TxnRef = reserved_order_id` (trả chuỗi URL thuần, JS `window.location.replace(url)`). Sau VNPAY:

- `Ipn.php` (SSOT): verify hash → `loadByIncrementId(vnp_TxnRef)`; nếu chưa có order → tra quote active theo `reserved_order_id` → code `00` → `CartManagement::placeOrder()`, sau đó chạy logic confirm gốc (amount check vs `baseGrandTotal`, status theo config, invoice); mã khác → respond `00` (không có gì để cancel).
- `Pay.php` (return fallback): cùng logic tra quote → `placeOrder()` khi order chưa tồn tại → set session success state (`LastQuoteId/LastSuccessQuoteId/LastOrderId/LastRealOrderId`) → success page; fail → `clearStaleOrderSession()` + `restoreCart()` → cart.

**Files** (4 file sửa + i18n; toàn bộ dưới `app/code/Secomm/VNPAY/` sau rename):

| File | Vai trò |
|------|---------|
| `Controller/Order/Info.php` | Tạo payment attempt trên quote, trả URL VNPAY (bare string) |
| `Controller/Order/Ipn.php` | SSOT: place order từ quote khi `00` + confirm logic; fail → respond `00` |
| `Controller/Order/Pay.php` | Return fallback: place order nếu chưa có → success page; fail → restore cart |
| `view/frontend/web/js/view/payment/method-renderer/vnpay-method.js` | `setPaymentInformationAction` → POST Info → redirect URL |
| `i18n/en_US.csv`, `i18n/vi_VN.csv` | Message source English; `vi_VN.csv` map ngược tiếng Việt |

**Lưu ý đã nêu với owner:** payment thành công mà `placeOrder` lỗi (hết stock / quote đổi giữa chừng) → không có order + log error — hoàn tiền thủ công theo SOP. Không gửi `vnp_ExpireDate` (đã loại sau khi gây sandbox Error code=15 do lệch timezone UTC vs GMT+7 — nếu dùng lại phải format `Asia/Ho_Chi_Minh`).

**Known limitations — cần TL/SA xem xét khi review (không block, ghi nhận có chủ đích):**

1. Amount check `Ipn` so `(int)vnp_Amount` với `(int)(baseGrandTotal * 100)` trong khi Info gửi amount **đã convert VND** — chỉ đúng khi base currency = VND; base ≠ VND sẽ `RspCode 04` (khách trả mà không confirm).
2. Invoice `CAPTURE_ONLINE` cho offline method (`_canCapture = false`) — invoice không `pay()`, order có thể không nhảy `processing` qua invoice (chỉ qua branch set status nếu config = processing).
3. Nhánh fail IPN vẫn `setTotalPaid()` trên order bị cancel — dữ liệu tài chính sai.
4. `echo` không `exit` ở nhánh cuối Ipn — response có thể lẫn output khác (`catch (\Exception)` đã fix ở A13).
5. Race IPN vs return: Magento 2.4.8 bọc `placeOrder` bằng `CartMutex` (`cart_lock_<quoteId>`, timeout 0) — luồng thua lock nhận `CartLockedException`. **Đã xử lý (A14)**: Pay catch `CartLockedException` → `sleep(2)` + re-check order 1 lần; guard session stale ở Info. QC T6 bắt buộc.
6. ~~Retry trên cùng quote dùng lại cùng `reserved_order_id`~~ — **ĐÃ FIX (A17)**: Info luôn re-reserve order id mới cho mỗi attempt.

Rollback: `git checkout` 5 file + `app/etc/config.php`.

## Resolved Questions (2026-09-08/09 — user acting as SA/TL in chat)

- **A1–A6**: lịch sử phương án `pending_payment` + core cron (DEC-002) — superseded.
- **A7**: chốt behavior literal — "chưa thanh toán thành công thì chưa tạo order" (tắt giữa chừng / rớt mạng = không có gì).
- **A8**: quote-based intent inline trong các file có sẵn, không class service mới, không email.
- **A9**: giữ cấu trúc `Info.php` gốc, sửa tối thiểu; bug "không place order được" = JS cache + race select-payment-method vs POST Info.
- **A10**: owner tự vá inline phát hiện thiếu "đuôi" (Ipn không thấy order, Pay không tạo order = mất tiền) → yêu cầu solution tối ưu → AI dựng service class (DEC-004).
- **A11**: owner tự implement lại toàn bộ theo hướng inline của mình (DEC-005), không dùng service class — AI không sửa code, chỉ cập nhật records.
- **A14 (2026-09-15)**: race IPN vs return materialize trên staging/local — Pay thua `CartMutex` lock (IPN thắng, order vẫn tạo) → swallow exception → không set session → Success bounce về cart với quote stale → lần sau "The cart is locked". Fix theo variant owner duyệt: Pay catch `CartLockedException` → `sleep(2)` + re-check `loadByIncrementId` 1 lần; success path `clearQuote()`+`clearHelperData()` trước set last-ids; fallback payment-received-không-placeable (log critical + error message + csv); Info guard quote inactive (clearQuote + getQuote mới) + re-reserve `reserved_order_id` khi đã tồn tại order ).
- **A15 (2026-09-15)**: sau A14 vẫn còn item ở **minicart** — minicart đọc `cart` section từ `mage-cache-storage` (localStorage), flow VNPAY không POST `payment-information` nên `sections.xml` không invalidate. Fix: `vnpay-method.js` gọi `customerData.invalidate(["cart"])` trước redirect sang VNPAY — khi khách quay về (full page load), customer-data reload cart từ server: rỗng nếu paid, nguyên trạng nếu fail/abandon.
- **A13 (2026-09-09)**: refactor code style theo yêu cầu review: message source English (dịch ngược tiếng Việt qua `i18n/vi_VN.csv`), properties PHP 8.1 toàn module (typed + constructor property promotion + `readonly` dependencies; `Vnpay.php` `$_code`/`$_isOffline` + `Logger/Handler.php` giữ untyped do inherited từ parent — redeclare typed là fatal), nullable khi cần, `catch (\Exception)` thay catch sai namespace, bỏ deprecated API (`addError/addSuccess` → `addErrorMessage/addSuccessMessage`), save qua Repository (`QuoteRepository`, `OrderRepositoryInterface`) thay vì model `save()` trực tiếp, `$_SERVER[REMOTE_ADDR]` → `RemoteAddress`. Flow KHÔNG đổi. Email: không chặn — order chỉ tạo sau khi paid nên email xác nhận tự nhiên chỉ ứng với giao dịch thật.
- **A12 (2026-09-09)**: rename module `Vnpayment_VNPAY` → `Secomm_VNPAY` (thống nhất vendor prefix `Secomm_`): move `app/code/Vnpayment/VNPAY` → `app/code/Secomm/VNPAY`, đổi namespace/registration.php/config.php/JS references, `Model/vnpay.php` → `Model/Vnpay.php` (class `Vnpay`), dọn các file code không dùng, cập nhật `.ai` references. Config path `payment/vnpay/*` và payment code `vnpay` KHÔNG đổi.

## Test Plan (QC)

Sandbox VNPAY, guest + registered (`bin/magento setup:upgrade && bin/magento cache:flush` sau rename; hard-refresh browser):

| # | Scenario | Kỳ vọng | AC |
|---|----------|---------|-----|
| T1 | Bấm thanh toán | Redirect VNPAY, `sales_order` 0 row mới | AC-001 |
| T2 | Pay success (IPN tới) | 1 order + invoice, success page; message hiển thị tiếng Anh/việt đúng locale | AC-002/005 |
| T3 | Pay success (IPN không tới — local) | Return tạo order → success page | AC-002 |
| T4 | Abandon (tắt tab / rớt mạng / timeout) | 0 order vĩnh viễn | AC-004 |
| T5 | Fail/cancel tại VNPAY → về store | Message thất bại + cart restore + retry OK | AC-003 |
| T6 | IPN + return gần đồng thời | Đúng 1 order | AC-002 |
| T7 | Retry sau fail | Attempt mới hoạt động (chú ý trùng `vnp_TxnRef` — limitation #6) | AC-003 |
| T8 | Regression: Mollie, method khác, module rename (admin config `payment/vnpay/*` vẫn đọc đúng) | Không đổi behavior | AC-006 |

## Verification

- Reproduced before fix: ✅ (phân tích code — Root Cause)
- Fixed confirmed: ⏳ owner đang QC sandbox (lỗi code=15 timezone đã xử lý bằng cách bỏ `vnp_ExpireDate`)
- Regression checked: ⏳ T8

> Raw evidence → `.ai/runtime/evidence/BUG-4WYXCB/`
