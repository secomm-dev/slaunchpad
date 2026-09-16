# PLAN-TASK-CG6BM7 — ZaloPay post-fix audit & correction

| Field | Value |
|-------|-------|
| Work item | TASK-CG6BM7 (Mode A, high risk) |
| Specification | [SPEC-TASK-CG6BM7-zalopay-postfix-audit](../specs/SPEC-TASK-CG6BM7-zalopay-postfix-audit.md) |
| Baseline | `a48de3cac477cada0882974151db7765c554aa25`, branch `task/zalopay-postfix-audit` |
| Mode / Risk | Mode A / high (payment, order lifecycle, DB schema) |
| Decisions | DEC-TASKCG6BM7-001, DEC-TASKCG6BM7-002 |

## Steps

1. **Rate strict validation** — `Gateway/Helper/Rate.php`: strict parser (int/float/numeric string/
   thousands-group string accepted; malformed rejected via `LocalizedException`, never coerce to 0).
   Currency conversion logic unchanged.
2. **Email idempotency** — `Service/OrderFinalizer.php`: FINALIZED-duplicate path resends khi
   `email_sent != 1` (try/catch Throwable, log critical); fresh path giữ nguyên (post-commit only).
3. **RefundCommand** — `Gateway/Command/RefundCommand.php`: LocalizedException-only; message từ map
   trên response hiện tại; raw exception text không bao giờ ra UI; safe array reads; bỏ
   `//TODO change condition`; giữ finally PROCESSING persistence.
4. **ResponseMessagesHandler** — code 3 không set `is_fraud_detected`; 1 approve; 2/unknown error+fraud.
5. **TransactionRefundHandler** — guard `refund_id` missing.
6. **Schema** — `etc/db_schema.xml` + `db_schema_whitelist.json`: `zalo_pay_refund` + cột
   `query_attempts` (smallint unsigned, NOT NULL, default 0) + `last_error` (text NULL);
   `Api/Data/RefundInterface.php` + `Model/Data/RefundData.php` getters/setters.
7. **RefundCronjob rewrite** — `Cron/RefundCronjob.php`: per-item try/catch; bounded
   (MAX_QUERY_ATTEMPTS = 96 constant); terminal FAIL (last_error, không requery); PROCESSING tăng
   budget; SUCCESS → creditmemo REFUNDED + row PROCESSED + last_error NULL; safe unserialize per
   item; collection filter `is_processed = NOT_PROCESSED AND query_attempts < 96 AND last_error IS NULL`.
8. **Tests (34-item matrix)** — extend `Test/Unit/Gateway/Helper/RateTest.php`,
   `Test/Unit/Service/OrderFinalizerTest.php`; new `Test/Unit/Gateway/Command/RefundCommandTest.php`,
   `Test/Unit/Gateway/Response/ResponseMessagesHandlerTest.php`, `Test/Unit/Cron/RefundCronjobTest.php`.
9. **Validation L3** — php -l changed files; PHPCS module-wide; full ZaloPay unit suite;
   `setup:di:compile`; Secomm regression; evidence `.ai/evidence/TASK-CG6BM7/`.
10. **Commit** (style `[Zalo] ...`, Co-Authored-By: Claude Code) trên task branch; KHÔNG merge;
    push chỉ task branch; receipt §15.

## Out of plan

Dead-wiring + map-text + instant-success-row + m_refund_id-resubmit: ghi nhận trong evidence
(MEDIUM/LOW), không sửa trong task này.

---

# Appendix — Material change 2026-09-16 (corrective round, cùng task)

TL direct source review phát hiện 3 lỗ hổng của chính output round 1. Kế hoạch bổ sung (steps
11–15), các steps 1–10 giữ nguyên lịch sử:

11. **Refund lifecycle rewrite (BLOCKER 1)** — kiến trúc plugin-orchestrated theo
    DEC-TASKCG6BM7-003: `RefundCommand` provider-only (trả `RefundOutcome`, không persist);
    mới `Plugin/Model/Service/CreditmemoRefundPlugin` (around `CreditmemoService::refund`:
    guard offline/in-flight/missing invoice-txn → hỏi provider TRƯỚC core → SUCCESS: mark +
    `proceed()`, PROCESSING: `registerPending` + STOP, TRANSPORT: track từ outcome carried);
    mới `Service/PendingRefundManager` (registerPending / consumeQueryBudget / terminate /
    finalizeSuccess exact-once: FOR UPDATE row lock + re-check `is_processed` + recovery branch,
    native accounting qua `RefundOperation` với marker chặn provider call thứ hai); mới
    `Service/RefundOutcomeMarker`, `Gateway/Command/RefundOutcome`, `Exception/RefundTransportException`.
    Bước 7 (RefundCronjob) viết lại cho phù hợp: cron KHÔNG tự chốt bằng mapped message nữa mà
    gọi `finalizeSuccess` (native), classify retry theo exception type + evidence prefix.
12. **Email concurrency (HIGH)** — nâng DEC-TASKCG6BM7-002: cột `email_dispatch` trong
    `secomm_zalopay_payment_attempt` (db_schema + whitelist), claim/release API trên resource +
    repository, `OrderFinalizer` claim trong tx khoá row trước commit, send token-guarded sau
    commit, release trong finally (EMAIL 8–10 + PaymentAttemptResourceTest).
13. **Test matrices mở rộng** — thêm PendingRefundManagerTest, CreditmemoRefundPluginTest,
    PaymentAttemptResourceTest; rewrite RefundCronjobTest + RefundCommandTest; patch
    OrderFinalizerTest. Unit-mock bù bằng source-call-chain proofs P7–P10 (vendor không trong
    CodeGraph index — đọc source anchor file:line); integration vẫn ENVIRONMENT_BLOCKED.
14. **Validation L3 lại toàn bộ** — php -l 16 file; PHPCS module-wide 0 error; full unit
    260/937; setup:di:compile THẬT trên bản sao Magento trong container (9/9, plugin-list có
    CreditmemoRefundPlugin); regression scope-proof.
15. **Commit + push + receipt corrective** — chỉ `task/zalopay-postfix-audit`; receipt theo mẫu
    corrective (27 field); STOP chờ TL.

## Appendix 2 — Corrective round 2 (2026-09-16, TL direct source review lần 2)

16. **Preflight Magento validation trước provider (F10)** — `Service/CreditmemoRefundPreflight`
    mirror 1:1 `CreditmemoService::validateForRefund` (protected ⇒ option 3; anchor 2.4.8-p5
    :189-219; upgrade coupling trong docblock; supplementary online-amount > 0); plugin gọi
    preflight sau in-flight guard, trước mọi provider I/O và mọi persistence; parity tests
    (`CreditmemoRefundPreflightTest` 9) + plugin provider-never matrix (5 test mới).
17. **Blocking semantic `refund_state` thay budget-implied safety (F11)** — cột + whitelist +
    constants `RefundInterface`; `hasInFlight` theo state (processing+unknown);
    `consumeQueryBudget` quarantine at-cap; `terminate` state tường minh (default unknown);
    cron FAIL ↦ `confirmed_fail`; `finalizeSuccess` ↦ `confirmed_success`; test matrix
    quarantine (manager 16 / cron 13).
18. **Validation + receipt round 2** — php -l; PHPCS 0 errors; full suite 279/988;
    setup:di:compile chạy lại trên code cuối (9/9 exit 0); CodeGraph P13 (worktree index, ghi
    rõ giới hạn callers nhiễu dev/tests); commit `[Zalo]` + push CHỈ task branch; STOP chờ TL.

## Appendix 3 — Corrective round 3 (2026-09-16, TL direct source review lần 3)

19. **Atomic claim (F12, DEC-TASKCG6BM7-005 D1)** — `etc/db_schema.xml`: cột
    `active_claim` smallint NULL + 2 unique index `ZALO_PAY_REFUND_ORDER_ACTIVE (order_id,
    active_claim)` / `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE (m_refund_id, active_claim)`
    (NULL-trick cho row lịch sử) + whitelist; `Api/Data/RefundInterface.php` constants +
    accessor; `Service/PendingRefundManager::acquireClaim` INSERT đơn autocommit (KHÔNG
    transaction xuyên HTTP), duplicate-key ↦ "active or awaiting reconciliation", lỗi khác ↦
    abort safe.
20. **RefundCommand split (F12 + crash/recovery, D2)** — `prepare()` (identity `m_refund_id`
    + reconciliation payload, KHÔNG I/O, `null` khi marker-skip; VO mới
    `Gateway/Command/RefundRequest`) + `executePrepared()` (provider DUY NHẤT);
    `execute()` = prepare + executePrepared (contract public giữ nguyên).
21. **State machine v3 (F13/F16, D3)** — 6 state `initiating/processing/unknown/
    confirmed_fail/confirmed_success/provider_success_local_pending`; manager mark×5 +
    `hasInFlight` 3 filter (`is_processed=0` + 4-state IN + budget); plugin transport ↦
    `unknown` semantic; SUCCESS + `$proceed()` throw ↦ PSLP.
22. **Cron v3 (F13/F15)** — step 0 PSLP shortcut (finalize local only, KHÔNG query);
    step 2a CM REFUNDED ↦ markConfirmedSuccess; step 2b drift ↦ terminate unknown; FAIL ↦
    terminate(confirmed_fail) + `releaseCreditmemoAfterFail` (STATE_PROCESSING → STATE_OPEN,
    save-fail critical swallow).
23. **Backfill (F14, D4)** — `Setup/Patch/Data/BackfillRefundState.php`: 3 UPDATE theo
    evidence order (refund_failed → confirmed_fail; còn lại is_processed=1 →
    confirmed_success; is_processed=0 → unknown).
24. **Test matrix round 3** — rewrite PendingRefundManagerTest (24), CreditmemoRefundPluginTest
    (18), RefundCronjobTest (18); new BackfillRefundStateTest (2); full suite 296/1057.
25. **Real-DB concurrency evidence** — MariaDB 10.4 container throwaway riêng (KHÔNG đụng DB
    dev chung, đã xoá sau thu evidence): E1–E8 (first-claim commits; same-order duplicate-key
    reject; same-m_refund_id reject; historical NULL rows coexist; release-reclaim;
    exactly-one-active; UNKNOWN quarantine giữ slot; 2 session song song 1 winner) — proofs
    P14.
26. **Validation + receipt round 3** — php -l; PHPCS 0 errors; full suite 296/1057;
    setup:di:compile PASS (/tmp/m2 re-stage); CodeGraph rebuild + anchor P15; commit
    `[Zalo]` + push CHỈ `task/zalopay-postfix-audit`; receipt theo mẫu round 3 (~35 field);
    STOP chờ TL.

## Appendix 4 — Round 4 steps (27+)

27. `db_schema.xml`: `credit_memo_id` nullable + 2 unique `<constraint>` (F17/F21) + whitelist tương ứng. 28. `BackfillRefundState` v2: 4 cohort + claim ownership MIN(entity_id) (F19/F20). 29. `PendingRefundManager`: `bindCreditMemo` guard `active_claim`, `isDuplicateKey` 1062-driver-only, `markProcessing` park CM, `EVIDENCE_ABANDONED` (F17/F22/F18). 30. Plugin bind-phase: save → bind → provider; fail-any → terminate abandoned (F17). 31. Cron step 0b abandoned-unbound + CANCELED-only drift + CM OPEN hợp lệ (F18). 32. Tests: 4 plugin + 3 cron + backfill rewrite + FK test → **306/1105 OK**. 33. PHPCS 0 errors. 34. Real-DB F21/F19: DEFER TL (P18, stack /tmp/m2b + zt-mariadb + zt-opensearch).
