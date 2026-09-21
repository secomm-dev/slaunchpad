# DEC-TASKCG6BM7-012 — Round 7: chính sách retry sau CONFIRMED_FAIL (A4)

Date: 2026-09-17. Status: accepted.

## D1 — CONFIRMED provider FAIL ⇒ retry CHO PHÉP; unresolved ⇒ retry BỊ CHẶN

ZaloPay `return_code=2` (FAIL) là provider xác nhận money KHÔNG di chuyển — không có lý do nào về tiền để khóa vĩnh viễn order/CM sau refusal rõ ràng; chặn cơ học sẽ đẩy người dùng vào quy trình thủ công vô nghĩa. Chọn: `confirmed_fail` nhả claim (`active_claim=NULL`) ⇒ admin được tạo refund MỚI (CM mới, `m_refund_id` mới — không tái sử dụng identity cũ, tránh dính query_refund của attempt trước). Ngược lại mọi state UNRESOLVED (initiating, provider_request_started, processing, unknown, provider_success_local_pending) giữ `active_claim=1` ⇒ unique `(order_id, active_claim)` + `(m_refund_id, active_claim)` từ chối claim mới ở tầng DB ⇒ retry cùng tiền bị chặn TRƯỚC provider (không phải check-then-act).

## D2 — Không thêm block cộng thêm sau confirmed_fail

Không đánh dấu CM "used/blocked" sau FAIL: CM giữ OPEN (DEC-011) là đủ — core `validateForRefund` kiểm tra refundable balance còn lại, over-refund vẫn bị chặn bởi chính core. Giữ chính sách tối giản: blocking chỉ do UNRESOLVED, release chỉ do TERMINAL truth.

## D3 — Bằng chứng

Unit: UNKNOWN/PROCESSING/PSLP cùng CM ⇒ chặn trước provider (duplicate claim 1062 → LocalizedException "Another refund ... active"); confirmed_fail ⇒ claim nhả, attempt mới với `m_refund_id` mới được phép (unit acquireClaim + real-Magento Scenario 5 mở rộng: sau FAIL chạy refund lần 2, `/refund` counter tăng, provider nhận request mới).
