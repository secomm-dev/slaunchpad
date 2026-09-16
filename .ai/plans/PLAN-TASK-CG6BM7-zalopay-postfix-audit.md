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
