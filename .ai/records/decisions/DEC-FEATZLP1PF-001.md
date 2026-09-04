---
id: DEC-FEATZLP1PF-001
title: 'ZaloPay payment-first: quote contract fingerprint locked at initiation and re-validated before automatic order creation'
status: proposed
owners: [sa, tl]
decision_type: architecture
approval_date:
created: 2026-08-27
last_verified: 2026-08-27
verified_against_commit: ddd83871c5fc61122eb927ed0e7553fa692638df
supersedes: []
superseded_by:
work_items: [FEAT-ZLP1PF]
---

# Decision Record: ZaloPay Quote Contract Fingerprint (payment-first Blocker 1)

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). -->
<!-- `memory/DECISIONS.md` giữ role Navigator ADR store + 1 dòng INDEX trỏ tới file này. -->
<!-- Status: proposed — TL review fixes đã implement + test, chờ TL re-review (FEAT-ZLP1PF §11). -->

## Context

TL review commit `1bd469b2` (Blocker 1): luồng payment-first verify `provider amount == attempt snapshot` nhưng `placeOrder($attempt->getQuoteId())` chạy trên quote HIỆN TẠI còn mutable — T0 giỏ 500.000đ → snapshot → khách sửa giỏ lên 700.000đ → trả 500.000đ → order tạo ở 700.000đ. Constraint từ TL: không được giải quyết chỉ bằng `collectTotals()` + so amount.

## Decision

Tại Start (trong section lock quote-row TX), toàn bộ contract state của quote được
normalize + hash sha-256 vào `payment_attempt.contract_hash` (varchar 64, nullable):
quote_id, reserved_order_id, store_id, currency, grand_total, base_grand_total,
provider VND amount, per-item `sku|product_id|qty` (kể cả children, sorted),
shipping method, coupon, rule ids, sha-256 của shipping + billing address fields
(hash — equality, không persist nội dung). Trước khi tự động finalizer, quote hiện
tại được reload + `collectTotals()` + FX amount + `hash_equals` với hash đã persist.
Mismatch ⇒ `ContractMismatchException` ⇒ TX rollback, attempt **stay PAID**
(`order_id` NULL — tiền thật, không bao giờ FAILED), lý do vào `last_error` SAU rollback,
customer-safe message → reconciliation/manual/refund (Phase 2).

Reuse gate: ACTIVE attempt chỉ được reuse khi amount AND fingerprint khớp; lệch ⇒
markStale + mint attempt mới với hash mới. Legacy rows null-hash không bao giờ
compare-equal (an toàn mặc định).

## Alternatives

- **Chỉ collectTotals + so amount** — reject: TL cấm rõ; amount khớp vẫn sai items/
  shipping/address (đã có test cùng total đổi qty/items chứng minh).
- **Persist full quote snapshot** — reject: nặng, chứa PII không cần thiết; chỉ cần
  equality checksum.
-- **Không validate (chỉ tin IPN)** — reject: order tạo sai cấu trúc là irreversible
  ở Magento (không có "un-place").

## Consequences

- ✂ PAID-with-mismatch phụ thuộc reconciliation Phase 2 (cron/queue từ `last_error`)
  — đã liệt kê trong FEAT §10.
- PHP 8.3 runtime thiếu `JSON_SORT_KEYS` (verified) — dùng recursive `ksort`.
- Mọi surface tạo attempt phải đi qua `PaymentAttemptManagement` (single hash writer).
