---
id: DEC-TASKCG6BM7-002
title: 'ZaloPay order confirmation email: post-commit only + idempotent resend trên FINALIZED-duplicate qua email_sent guard; không rollback payment vì email'
status: proposed
owners: [sa, tl]
decision_type: correctness
approval_date: 2026-09-16
created: 2026-09-16
last_verified: 2026-09-16
verified_against_commit: a48de3cac477cada0882974151db7765c554aa25
supersedes: []
superseded_by:
work_items: [TASK-CG6BM7]
---

# Decision: Email idempotency & retry semantics (TASK-CG6BM7)

## Bối cảnh

Audit baseline `a48de3ca` (§3 của yêu cầu TL): `OrderFinalizer` đã gửi email SAU commit (đúng) nhưng
path FINALIZED-duplicate (Return revisit / duplicate IPN / recovery) return sớm KHÔNG email; nếu
crash/throw giữa commit và `orderSender->send()` (hoặc send() fail), không có driver nào gửi lại —
mail-loss state. Không được assume OrderSender tự idempotent: tự nó không có dedup — dedup phải đến
từ `email_sent` (sync thành công persist `email_sent = 1` qua `saveAttribute`).

## Quyết định

1. **Post-commit only** (giữ nguyên): email là bước sau commit trong `finalizeOrRecover` — không có
   path nào gửi trước commit; capture fail/contract mismatch → throw trước block email → rollback
   (hoặc evidence-persist) → không email.
2. **Idempotency qua email_sent (Magento-native)**: FINALIZED-duplicate path kiểm tra
   `$existing->getEmailSent()` — `== 1` → skip (không mail trùng); `!= 1` → gửi bù đúng 1 lần
   (sync thành công persist =1 nên lần sau skip). Đây là chính sách "at-least-once với guard
   idempotent", dùng đúng signal mà OrderSender đã persist.
3. **Payment không rollback vì email** (giữ nguyên): send() throw → log critical, order giữ
   FINALIZED. Không tạo state không thể khôi phục: retry driver = mọi caller tiếp theo của
   `finalizeOrRecover` trên attempt FINALIZED (Return revisit, duplicate IPN, admin recovery).
4. **Không tự chế scheduled retry driver**: không thêm cron riêng cho email retry. Async mode
   (`sales_email/general/async_sending = 1`) là giải pháp native đầy đủ (cron `sales_send_order_emails`
   có retry riêng) nhưng là store config — nằm ngoài scope code, ghi trong khuyến nghị TL.
   Trong async mode, gọi send() lặp trên order chưa gửi chỉ re-đánh dấu hàng đợi state-based
   (email_sent IS NULL) — không nhân bản mail.

## Lựa chọn đã loại bỏ

- Extend PaymentRecovery để revisit FINALIZED rows: trộn concern (recovery worker = lost-callback
  query, selection/claim của nó loại row có order_id), rủi ro phá bounded budget hiện có.
- Cron email retry riêng: scope creep; duplicate driver.
- Gửi email trước commit: đã bị loại từ trước — email trước commit = mail cho order có thể
  rollback.

## Hệ quả

- `OrderFinalizer` duplicate path thêm guard + resend try/catch.
- Tests EMAIL 1–7 trong matrix chứng minh: fresh → 1 lần; duplicate + email_sent=1 → 0; duplicate +
  email_sent!=1 → đúng 1; capture-fail/mismatch → 0; send-throw → order giữ FINALIZED.
- Khuyến nghị cho TL (config-level, không code): cân nhắc bật `sales_email/general/async_sending`.
