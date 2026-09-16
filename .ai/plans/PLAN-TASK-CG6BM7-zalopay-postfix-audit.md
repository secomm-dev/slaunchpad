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
