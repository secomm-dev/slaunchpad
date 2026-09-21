# DEC-TASKCG6BM7-009 — Round 7: RefundOutcomeMarker theo credit_memo_id, one-shot, fail-closed (F28/B2)

Date: 2026-09-17. Status: accepted. Replaces the order-keyed process boolean.

## D1 — Identity của provider-skip authorization là CREDIT MEMO (không còn order-level boolean)

Marker cũ: `key = order_id, value = true, lifetime = PHP process, clear = NEVER` — hai lỗ: (1) refund B trên CÙNG order trong cùng process kế thừa authorization của refund A ⇒ B bị skip provider SAI; (2) mọi `prepare() === null` bất kỳ nguyên nhân nào đều được hiểu là "provider đã hỏi" ⇒ fail-open. Chọn `credit_memo_id` làm key vì: một refund attempt ≡ một credit memo (admin tạo CM mới cho mỗi lần refund); hai refund trên cùng order = hai CM khác nhau ⇒ isolation theo cấu trúc; CM id tồn tại từ trước provider I/O (bind phase round 4) nên sync path lẫn cron finalize đều có sẵn identity. API chốt: `authorize(int $creditMemoId): void`, `consume(int $creditMemoId): bool` (one-shot — remove + true chỉ lần đầu), `clear(int $creditMemoId): void` (idempotent).

## D2 — One-shot + try/finally: authorization không thể rò rỉ

`consume()` xóa authorization ngay tại lần đọc đầu ⇒ không thể authorize hai lần; mọi caller (plugin sync-success, `PendingRefundManager::finalizeSuccess`) bọc đoạn core accounting trong `try { ... } finally { clear($cmId); }` ⇒ authorize mà chưa consume (core throw trước khi gateway chạm prepare) cũng bị dọn, không rò sang refund khác. Marker vẫn process-scoped (DI singleton): cron worker và admin request có instance riêng — không persist, không cần dọn chéo process.

## D3 — Fail-closed cho `prepare() === null` trong plugin

Trong path bình thường plugin CHỈ gọi `prepare()` khi chưa có authorization nào cho refund này ⇒ `null` là trạng thái KHÔNG MONG ĐỢI ⇒ bắt buộc: log evidence (safe text) + `LocalizedException` customer-safe. KHÔNG `$proceed()`, KHÔNG provider call, KHÔNG accounting. `null` hợp lệ chỉ xảy ra bên trong core flow (Payment::refund → gateway `prepare()` consume authorization) — nơi `null` = "skip provider, chạy accounting thuần" như thiết kế.

## D4 — Bằng chứng

Unit: one-shot (lần 1 true, lần 2 false); isolation A/B cùng order cùng process; finally-clear khi core throw (PSLP path); prepare-null fail-closed (provider 0, `$proceed` 0); sync path lẫn cron finalize path đều assert authorize/consume/clear đúng thứ tự. Real-Magento Scenario 7: hai refund tuần tự trên cùng order trong CÙNG PHP process — refund B vẫn gọi provider bình thường (counter `/refund` tăng đúng 2 lần sau 2 refund).
