# Evidence — TASK-DFGFZ9 Phase 3 closure (atomic per-order claim, r4)

Date: 2026-09-23 · DEC-TASKDFGFZ9-004 · Plan approved (user acting SA/TL)

## 1. Recovery timeline — 5 điểm ngắt (code-verified)

| Điểm ngắt | Ledger | Anchor | Retry cùng attempt | Attempt khác |
|---|---|---|---|---|
| Trước claim | — | — | bình thường | bình thường |
| Sau claim, trước anchor | PENDING (claim giữ) | — | frozen replay → POST lại cùng reference | **claim chặn** (CodClaimConflictException / COD_REJECTED) |
| Sau anchor, trước POST | PENDING | PENDING | frozen → POST lại | chặn ✓ |
| POST OK, mất response | UNKNOWN (claim giữ) | UNKNOWN | frozen + POST cùng reference → ORDER_ID_EXIST / idempotency → RECOVERED | chặn ✓ |
| Sau lưu response | SUBMITTED/RECOVERED | SUBMITTED/RECOVERED | GHN SUBMITTED short-circuit / GHTK ORDER_ID_EXIST | chặn ✓ |

FAILED (chỉ khi chắc chắn không POST: business rejection, pre-POST validation/handoff/mapping
failures) → claim NULL → attempt mới được quyết định fresh. UNKNOWN giữ claim — admin path:
GHTK "retry safe" message + native label flow re-run; GHN `secomm:ghn:shipment:retry`; lookup
bằng reference qua `findByPartnerCode` / `findByClientOrderCode` / `findFrozenAmount`.

## 2. Atomic claim — real-DB concurrency proof (2 PDO connections)

Script `php -r` với 2 connections riêng (A, B) trên dev DB `launchpad`; B có
`innodb_lock_wait_timeout = 3`. Output nguyên văn:

```
== SCENARIO 1: concurrent cross-carrier contest ==
A claimed ghtk/gC1-1: OK
B rejected by engine: [23000] SQLSTATE[23000]: Integrity constraint violation: 1062 Duplic
active claims for order: 1 (expect 1)

== SCENARIO 2: FAILED releases the claim ==
B claimed ghn/GHNS900101 after release: OK
new holder: ghn

== SCENARIO 3: same-reference re-arm + no-downgrade ==
same-ref re-insert → 1062 (expected)
re-armed: status=PENDING amount=750000.0000 (single row: 1)

== SCENARIO 4: transaction-blocked (real engine contention) ==
B BLOCKED by A uncommitted lock for 3s: [HY000] SQLSTATE[HY000]: General error: 1205 Lock wait tim
A committed — claim now visible; B retry → 1062 loser (correct — A holds the claim)

cleanup: 0 rows left
```

Scenario 4 chứng minh **cạnh tranh engine thật**: B bị BLOCK 3s bởi row-lock của A
(uncommitted) rồi nhận 1062 sau commit — không phải 2 lời gọi tuần tự giả lập.

## 3. Claim-release fixes (stuck-PENDING bugs)

1. GHTK: `recordPending` chuyển xuống SAU `buildProducts` + `requestMapper->map` — một throw
   trong build/map không còn để lại PENDING claim (regression test: map/buildProducts throw →
   ledger trống).
2. GHN: 3 fail sites pre-POST trong `resolveAndCreate` (handoff not-applicable /
   resolved-null / mapping-missing) giờ pass `$codAttempt, $codAmountFloat` → ledger
   `markNotSubmitted(FAILED)` → claim released (test: `testPrePostFailuresReleaseTheLedgerClaimAsFailed`).

## 4. Currency + zero-amount

- Resolver KHÔNG gate currency — decision trả order currency; test USD → COLLECTIBLE
  (99.9, 'USD'); frozen row non-VND replay currency của nó.
- GHTK: non-VND collectible → LocalizedException "COD currency unsupported (USD)…" trước
  ledger/anchor/POST (test `testUsdCollectibleDecisionIsRejectedBeforeAnyWrite`).
- GHN: non-VND collectible → `COD_REJECTED` `currency_unsupported` trước mọi write
  (test `testNonVndCollectibleDecisionIsCodRejectedBeforeAnyWrite`).
- Zero-total COD: collectible(0.0) giữ classification (decision test + resolver test +
  GHTK `testZeroAmountCodOrderStaysCodWithNoLedgerClaim` pick_money 0 + GHN
  `testZeroAmountCodDecisionShipsWithZeroAndNeverClaimsTheLedger` cod_amount 0).
- Negative: REJECTED `invalid_order_amount` (resolver + decision whitelist).

## 5. Test numbers

- Scoped `Cod|Ghtk|Ghn`: **705 tests / 211,823 assertions — 0F/0E**.
  Cod: 8 decision + 6 identification + 16 resolver + 18 ledger.
  Ghtk service 22 (+USD reject / claim-conflict propagate / zero-amount / mapping-throw guard).
  Ghn service 22 (+currency COD_REJECTED / claim-conflict COD_REJECTED / zero-amount /
  pre-POST fail releases claim).
- Full suite: 2626 tests — 8E + 2F toàn pre-existing stream khác (3 FulfillmentCore +
  7 Tracking; PromotionMaxDiscount đã được stream đó sửa); **0 thuộc COD/Ghtk/Ghn**.
- `setup:di:compile` GREEN; `setup:upgrade` GREEN (cột + UNIQUE index applied — SHOW
  COLUMNS/INDEX verified); whitelist regenerated; validator 0 finding TASK-DFGFZ9.

## 6. OPEN gates (không ghi PASS)

1. **Fresh-install full-flow**: empty-DB `setup:upgrade` blocker "The default website isn't
   defined" (third-party eager `Session\Config` trong schema phase) — N-Defect môi trường
   riêng. Structural proof (declarative-only, 0 config dependency) KHÔNG được ghi thành
   fresh-install pass. Re-run clean install khi blocker được sửa.
2. **GHN sandbox `cod_amount > 0` probe**: BLOCKED_BY_CREDENTIAL — gate doc
   `ghn-cod-sandbox-gate.md`; KHÔNG ghi E2E pass khi chưa có response thật.
3. Admin browser smoke: COD order e2e (claim/second-shipment/retry) trên store thật.
