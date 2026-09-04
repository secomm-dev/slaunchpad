---
id: DEC-FEATZLP1PF-002
title: 'ZaloPay payment-first: OrderFinalizer is the single owner of the checkout success-session Last* keys'
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

# Decision Record: ZaloPay Single Success-Session Writer (payment-first Blocker 2)

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). -->
<!-- `memory/DECISIONS.md` giữ role Navigator ADR store + 1 dòng INDEX trỏ tới file này. -->
<!-- Status: proposed — TL review fixes đã implement + test, chờ TL re-review (FEAT-ZLP1PF §11). -->

## Context

TL review commit `1bd469b2` (Blocker 2): `ReturnProcessor` short-circuit attempt
FINALIZED bằng early `return 'checkout/onepage/success'`, bypass `OrderFinalizer` —
chủ sở hữu duy nhất các key LastQuoteId/LastSuccessQuoteId/LastOrderId/
LastRealOrderId/LastOrderStatus. Return trùng lặp với checkout session mới/mất
đến success page với session RỖNG → core SuccessValidator fail → khách đã trả tiền
bị đá về cart. Constraint từ TL: không duplicate session-setting logic qua
nhiều controllers/services.

## Decision

Bỏ short-circuit. `OrderFinalizer::finalizeOrRecover()` (alias cũ `finalize()`) là
code DUY NHẤT viết success-session state: attempt FINALIZED ⇒ load bound order,
validate binding (order tồn tại; increment id + quote id + payment method `zalopay`
khớp attempt), rebuild idempotent đủ 5 key (mirror core `Onepage::saveOrder` /
`QuoteManagement`), return success. Binding hỏng ⇒ `ContractMismatchException` ⇒
customer-safe error — không bao giờ hollow success. `ReturnProcessor` chỉ delegate.

## Alternatives

- **Mỗi controller tự set session khi cần** — reject: chính là nguyên nhân bug
  (logic phân tán, không idempotent); TL cấm duplicate.
- **SuccessValidator plugin ép pass** — reject: đã bị remove ở Phase 1 (reflection
  hack trên private Session state, FEAT §6).
- **Redirect thẳng URL success không qua validator** — reject: success page chuẩn
  vẫn đọc session để render order info.

## Consequences

- Mọi surface (return, reconciliation cron tương lai, admin recovery) PHẢI route
  qua `OrderFinalizer` để có session đúng — interface contract rõ ràng.
- Duplicate return giờ idempotent cả về session lẫn order (test: FINALIZED duplicate
  với session rỗng rebuild đủ 5 key).
