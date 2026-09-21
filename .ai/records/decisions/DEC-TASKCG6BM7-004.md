---
id: DEC-TASKCG6BM7-004
title: 'ZaloPay refund: Magento validation preflight TRƯỚC provider (mirror validateForRefund) + blocking theo semantic refund_state (exhausted UNKNOWN vẫn block)'
status: proposed
owners: [sa, tl]
decision_type: correctness
approval_date: 2026-09-16
created: 2026-09-16
last_verified: 2026-09-16
verified_against_commit: ae943bcf375aa033941b394d6076d1babc03db55
supersedes: []
superseded_by:
amends: [DEC-TASKCG6BM7-003]
work_items: [TASK-CG6BM7]
---

# Decision: Preflight Magento validation + semantic refund_state blocking (BLOCKER round 2)

## Bối cảnh — hai khiếm khuyết TL bắt được trong chính code corrective round 1

1. **Provider được hỏi trước khi Magento refund validation pass.** Kiến trúc round 1
   (DEC-TASKCG6BM7-003) gọi `RefundCommand::execute()` TRƯỚC `$proceed()`, mà
   `CreditmemoService::refund()` chạy `validateForRefund()` BÊN TRONG `$proceed()`
   (vendor/magento/module-sales/Model/Service/CreditmemoService.php 2.4.8-p5, refund
   :147-180, validateForRefund protected :189-219). Hệ quả: một refund Magento không hợp lệ
   (over-refund; credit memo đã processed; order reference hỏng) chạm ZaloPay trước khi bị chặn.
2. **Row UNKNOWN cạn ngân sách query rơi khỏi blocking.** `hasInFlight` round 1 =
   `is_processed = 0 AND query_attempts < MAX`: transport/protocol exhaustion đẩy row ra khỏi
   guard trong khi outcome tại provider có thể là SUCCESS ⇒ refund lần nữa ⇒ DOUBLE REFUND.

## Quyết định

### D1 — Preflight mirror (option 3 trong 3 option TL đưa)

- Option 1 (reuse public Magento validation/service) KHÔNG khả dụng: `validateForRefund()` là
  **protected**, không có service public nào bộc lộ đúng contract này.
- Chọn **option 3**: `Service/CreditmemoRefundPreflight::validateRefundable()` mirror **1:1 TOÀN
  BỘ** `CreditmemoService::validateForRefund()` — cùng thứ tự, cùng message contract, cùng
  rounding qua `PriceCurrencyInterface`:
  1. credit memo đã tồn tại && state != STATE_OPEN → `LocalizedException('We cannot register an
     existing credit memo.')` (core :192-197);
  2. thiếu order id / order không resolve được → `NoSuchEntityException('We found an invalid
     order to refund.')` (core :199-203; supplementary: order model phải resolve được);
  3. `round(baseTotalRefunded + baseGrandTotal) > round(baseTotalPaid)` →
     `LocalizedException('The most money available to refund is %1.')` với
     `formatTxt(baseTotalPaid - baseTotalRefunded)` (core :205-218).
- Supplementary riêng gateway online: `baseGrandTotal <= 0` → `LocalizedException` (core không có
  check này; provider không bao giờ được hỏi với số tiền <= 0).
- **Upgrade coupling (ghi tường minh trong docblock class):** mirror neo vào source 2.4.8-p5
  `CreditmemoService.php:189-219`; khi core đổi validation phải re-diff + re-run parity tests
  (`CreditmemoRefundPreflightTest` pin từng check).
- Invariant: `Magento refund validation PASS → provider refund mới được yêu cầu`. Plugin gọi
  preflight TRƯỚC mọi provider I/O và TRƯỚC mọi persistence (pending Credit Memo KHÔNG được
  persist khi preflight chưa pass).

### D2 — Blocking là semantic state, KHÔNG phải budget

- Cột mới `zalo_pay_refund.refund_state` (varchar 32, default `processing`), 4 giá trị:
  `processing` (provider chấp nhận, outcome đang mở — BLOCK), `confirmed_success` (đã finalize
  qua native accounting — về số dư refundable chuẩn), `confirmed_fail` (provider TỪ CHỐI tường
  minh, tiền chưa ra — MỞ KHÓA), `unknown` (outcome chưa xác nhận: transport/protocol/finalize
  exhaustion, malformed payload, missing creditmemo, state drift — **TIẾP TỤC BLOCK** đến khi
  được resolve chủ đích).
- `hasInFlight` = row `refund_state IN (processing, unknown)` — KHÔNG còn điều kiện
  `is_processed`/`query_attempts`; `query_attempts == MAX` không tự mang nghĩa "safe to refund".
- `consumeQueryBudget` tự **quarantine → unknown** khi attempts chạm MAX (mọi path tiêu budget
  đều là outcome chưa xác nhận).
- `terminate()` nhận state tường minh, **mặc định unknown** (outcome chưa xác nhận không bao giờ
  được coi là an toàn); cron provider-FAIL truyền `confirmed_fail`.
- `finalizeSuccess` (cả nhánh fresh lẫn recovery) bind `confirmed_success`.

## Hệ quả / giới hạn

- Mirror là coupling có chủ đích: parity tests + anchor ghi rõ giúp TL review diff core khi
  upgrade Magento.
- Row `unknown` tồn tại vĩnh viễn cho đến khi thao tác vận hành chủ đích (cập nhật state sau
  reconcile thủ công) — đúng yêu cầu "remains visible and blocking until deliberately resolved".
- Regress matrix round 1 giữ nguyên: PROCESSING không mutate totals/không đóng order; SUCCESS
  native accounting; FAIL không đụng kế toán; email claim atomic; không provider refund thứ hai.

## Bằng chứng

- `Service/CreditmemoRefundPreflight.php` + `CreditmemoRefundPreflightTest` (9 test, parity từng
  check).
- `Plugin/Model/Service/CreditmemoRefundPlugin.php:121-127` (preflight trước provider :148).
- Plugin "provider NEVER called" matrix: over-refund / non-open creditmemo / invalid order /
  zero amount → `RefundCommand::execute` NEVER; valid → exactly once, preflight trước provider.
- `Service/PendingRefundManager.php` (hasInFlight state filter :108-118; quarantine :199-206;
  terminate :228-238; finalizeSuccess binds confirmed_success) + `RefundCronjob.php` (FAIL ↦
  confirmed_fail) + tests `PendingRefundManagerTest` (16), `RefundCronjobTest` (13).
