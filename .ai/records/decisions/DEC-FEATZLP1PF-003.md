---
id: DEC-FEATZLP1PF-003
title: 'ZaloPay payment-first: one flattened DB transaction for finalization — no intermediate ORDER_CREATED state, capture stays inside the TX'
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

# Decision Record: ZaloPay Single Flattened Finalization Transaction

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). -->
<!-- `memory/DECISIONS.md` giữ role Navigator ADR store + 1 dòng INDEX trỏ tới file này. -->
<!-- Status: proposed — TL review fixes đã implement + test, chờ TL re-review (FEAT-ZLP1PF §11). -->

## Context

TL question: nếu `captureOrder()` throw, `{attempt FINALIZED, order persistence,
MSI reservation}` có bị commit một phần không? Constraint từ TL: không được assume
nested Magento transactions atomic nếu chưa verify bằng source.

## Decision

Giữ MỘT transaction duy nhất trong `OrderFinalizer` (TX-B: attempt-row FOR UPDATE →
placeOrder → markFinalized → capture), KHÔNG thêm state trung gian
`PAID → ORDER_CREATED → FINALIZED`. Capture ở lại trong TX. Verified bằng source
(evidence trong FEAT §11.3):

- `framework/DB/Adapter/Pdo/Mysql.php` (~371–435): real BEGIN chỉ ở level 0, real
  COMMIT chỉ ở level 1, KHÔNG savepoints — nested begin/commit là counter; nested
  rollBack poison unit (`_isRolledBack`), commit ngoài sau đó throw
  `ERROR_ROLLBACK_INCOMPLETE` → rollback ngoài cùng thực hiện ROLLBACK thật.
  Nested transactions bị FLATTEN thành một đơn vị atomic.
- Chuỗi `placeOrder` không tự mở transaction (module-quote dùng QuoteIdMutex
  advisory locks).
- MSI reservations chạy đồng bộ cùng connection qua plugin trên
  `OrderManagementInterface::place` ⇒ nằm trong TX của OrderFinalizer.
- ZaloPay `capture` là `NullCommand` — không HTTP trong TX.

## Alternatives

- **State machine trung gian ORDER_CREATED** — reject: redesign không cần thiết
  (TL: không redesign ngoài scope); không thêm guarantee nào vì TX đã all-or-nothing.
- **Capture sau commit** — reject: capture fail sẽ để lại orphan order; capture
  trong TX nghĩa là capture exception rollback cả order (attempt về PAID cho
  reconciliation, không có orphan).

## Consequences

- Capture exception ⇒ order rollback ⇒ attempt PAID + `last_error` (sau rollback)
  → reconciliation Phase 2; không bao giờ order không-capture lơ lửng.
- Phụ thuộc flattening behavior của Mysql adapter — ổn định trong Magento 2.4.x,
  đã verify bằng source; nếu Magento đổi adapter semantics phải re-check.
