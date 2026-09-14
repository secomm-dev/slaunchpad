---
id: DEC-TASKEDS9T5-002
title: 'ZaloPay corrective: PaymentAttemptLifecycle là bộ máy chuyển trạng thái canonical (short tx + FOR UPDATE + re-evaluate fresh state); Return quyết định CHỈ bằng v2/query; guard bắt buộc triple khớp attempt đã persist; tách SuccessSessionPreparer khỏi OrderFinalizer'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit: 66f684468c9969e64e341f98ccafd475576ab138
supersedes: []
superseded_by:
work_items: [TASK-EDS9T5]
---

# Decision: Corrective round — canonical lifecycle + authoritative-only Return + triple guard (TASK-EDS9T5)

## Bối cảnh

TL review tại HEAD `66f68446` (round 1) kết luận 4 BLOCKER:
1. Browser Return mark FAILED khi `status != 1` TRƯỚC khi chạy v2/query → race: FAILED terminal
   + IPN hợp lệ đến sau ⇒ ack, không order, khách đã trả tiền thật.
2. Return/IPN mutate state qua `getByAppTransId → inspect → mark* → save` KHÔNG khoá row
   (quyết định trên stale copy trước lock FOR UPDATE của finalizer).
3. Guard chỉ validate `consumeForQuote(quoteId)` — quote-only, không đối chiếu attempt đã persist.
4. `OrderFinalizer` inject `Magento\Checkout\Model\Session` ⇒ IPN (server-to-server) chạm session khách.
Ngoài ra docblock còn nhắc "Phase 2 reconciliation cron" KHÔNG tồn tại.

## Quyết định

1. **`Service/PaymentAttemptLifecycle` = bộ máy chuyển trạng thái canonical** (`recordVerifiedPaid`,
   `recordVerifiedFailure`, `recordAmountMismatch`). Mỗi operation: begin short tx →
   `lockByAppTransId` (SELECT ... FOR UPDATE) → re-evaluate FRESH `payment_status` → transition
   idempotent hoặc evidence-only → save → commit. KHÔNG bao giờ giữ DB tx khi gọi ZaloPay HTTP
   (verification chạy TRƯỚC lock; `OrderFinalizer` mở tx riêng sau đó). Cả `ReturnProcessor` lẫn
   `IpnProcessor` delegate 100% — không còn logic callback phân kỳ. Terminal states không có
   outgoing edge ⇒ PAID evidence đến trễ trên FAILED/STALE/EXPIRED chỉ ghi evidence +
   `provider_transaction_id` (không broaden trạng thái, không order).
2. **Return quyết định CHỈ bằng v2/query** (semantics thật: return_code 1 = paid, 3 = processing):
   browser `status` KHÔNG còn là input của bất kỳ quyết định trạng thái; checksum redirect chỉ là
   tamper evidence (mismatch ⇒ từ chối trước khi verify, không mutate). PROCESSING (3) ⇒ không
   mutate, không order (IPN/lần Return sau/TTL giải quyết). Authoritative failure ⇒ FAILED chỉ khi
   persisted state cho phép (PAID/FINALIZED không bao giờ bị regressed).
3. **Guard bắt buộc triple khớp PERSISTED attempt**: xoá `consumeForQuote`; thêm `peekForQuote`
   (đọc grant KHÔNG consume) — guard load attempt theo `grant.attempt_id`, đối chiếu
   `attempt.quote_id == cartId` VÀ `attempt.app_trans_id == grant.app_trans_id` rồi mới
   `consumeIfMatches` (consume đúng một lần). Test chạy đúng production `beforePlaceOrder`.
4. **Session ownership**: tách `Service/SuccessSessionPreparer` (writer DUY NHẤT của success-session),
   do Return path gọi sau khi finalizer trả order (cả fresh-finalize lẫn recovery FINALIZED).
   `OrderFinalizer` không còn dependency `Checkout\Session` (reflection test chặn ngược); `IpnProcessor`
   không hề có session. Alias chết `OrderFinalizer::finalize()` đã xoá.
5. **Xoá claim "Phase 2 reconciliation cron"** — KHÔNG có cron reconcile. 4 nhóm case reconcile
   THỦ CÔNG thật được ghi rõ trong CHANGELOG 1.1.1 (amount mismatch; contract mismatch; late PAID
   evidence trên terminal; evidence mâu thuẫn PAID vs query failure) — evidence đầy đủ
   (`last_error` + `provider_transaction_id`) để thao tác thủ công, không tự động tạo order.

## Hệ quả

- Không trạng thái nào cho phép: khách đã trả tiền (authoritative) mà về sau KHÔNG thể đối soát.
- `FINALIZED` không bao giờ bị regressed bởi Return/IPN stale (transition map + lifecycle lock).
- Chỉ `OrderFinalizer` chạm `placeOrder` (grep production: 1 call site, trong grant window);
  writer của `payment_status`/`order_id`/`provider_transaction_id`/`last_error` chỉ còn:
  Lifecycle (callback), OrderFinalizer (finalize + contract-mismatch evidence),
  PaymentAttemptManagement (initiation: markActive/markFailed/markStale/setPaymentStatus(INITIATED)).
- Bảo lưu toàn bộ test payment-first round 1 (78 test) về mặt ngữ nghĩa; suite tăng 78 → 112.
