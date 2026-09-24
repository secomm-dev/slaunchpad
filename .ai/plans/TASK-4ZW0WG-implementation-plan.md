# Kế hoạch triển khai: TASK-4ZW0WG — MoMo lost/delayed-IPN payment recovery (MOMO-03)

| Field | Value |
|---|---|
| Specification | Full Spec — [SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md](../specs/SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md) (VALID) |
| Record | [TASK-4ZW0WG](../records/tasks/TASK-4ZW0WG.md) · Issue github:thanhle74/slaunchpad#5 |
| Workflow mode | A (payment reconciliation + schema + cron → high-risk, Tier 2) |
| Risk | high — mitigation: reuse-only mutation path (lifecycle + finalizer), ZaloPay `PaymentRecovery` precedent đã qua 4 vòng review, additive schema |
| Date | 2026-09-21 · RUN_ID RUN-20260921-TASK4ZW0WG-6f2a41 · BASE `26c4ef6e` · host thanhle-aloha |

## 1. Hướng tiếp cận

Thiếu mảnh cuối của payment-first: khi IPN có thẩm quyền bị mất/trễ và Return
không xảy ra, tiền đã verified không bao giờ thành order. Thiết kế mirror
`Secomm\ZaloPay\Service\PaymentRecovery` + `Cron\PaymentRecoveryCronjob` (đã qua
4 vòng review, cùng pattern lost-callback) thích ứng sang contract MoMo:

- **Selection** deterministic bounded: `payment_status IN (active, paid)`,
  `order_id IS NULL`, `requires_reconciliation != 1`, `recovery_exhausted != 1`,
  `recovery_attempts < max`, `created_at <= now − window`, order `entity_id ASC`,
  page size = batch (25).
- **Claim-before-HTTP**: 1 atomic conditional UPDATE tăng `recovery_attempts`,
  set `recovery_exhausted` khi tiêu nốt budget (MySQL đánh giá SET trái→phải);
  thua race → skip. Không TX/lock qua HTTP.
- **Verification + outcome** qua đúng canonical services: `query_transaction`
  command (subject `order_ref` + `attempt`) → phân loại resultCode → lifecycle
  (`recordVerifiedPaid/recordAmountMismatch/recordProviderIdentityUnavailable/
  recordVerifiedFailure`) → `OrderFinalizer::finalizeOrRecover`. Ambiguous
  (exception/thiếu resultCode) → không mutation, không false-fail (AC3).

Các bước:

1. **Schema** — `etc/db_schema.xml`: +`recovery_attempts` (smallint unsigned
   NOT NULL default 0), +`recovery_exhausted` (boolean NOT NULL default false)
   vào `secomm_momo_payment_attempt`; regenerate whitelist
   (`setup:db-declaration:generate-whitelist --module-name=Secomm_MoMo`).
2. **Contract** — `Api/Data/PaymentAttemptInterface.php`: const
   `RECOVERY_ATTEMPTS`, `RECOVERY_EXHAUSTED` + `getRecoveryAttempts()/
   setRecoveryAttempts()/isRecoveryExhausted()/setRecoveryExhausted()`;
   implement trong `Model/PaymentAttempt.php`.
3. **Worker** — `Service/PaymentRecovery.php` (DI: collection factory,
   ResourceConnection, CommandPoolInterface, PaymentAttemptLifecycle,
   OrderFinalizer, ScopeConfigInterface, Psr logger): `execute(): array`
   summary (claimed/finalized/failed/mismatch/pending/ambiguous/errors);
   private `selectCandidates/claim/recoverAttempt/queryTransaction` + config
   getters (floor 1). Phân loại resultCode theo spec §3.5.
4. **Cron** — `Cron/PaymentRecoveryCronjob.php` thin wrapper (try/catch toàn
   run → critical; log info summary khi claimed > 0); `etc/crontab.xml` mới:
   `secomm_momo_payment_recovery_cronjob`, group `default`, `*/5 * * * *`.
5. **Config** — `etc/config.xml`: `recovery_window=15`, `recovery_batch_size=25`,
   `recovery_max_attempts=5` trong `payment/momo_payment`.
6. **Tests** — `Test/Unit/Service/PaymentRecoveryTest.php` (selection filters;
   PAID → finalizer đúng 1 lần với transId; amount mismatch → quarantine không
   order; transId xấu → identity-unavailable; pending → không mutation; query
   throw + resultCode thiếu → không mutation không false-fail; claim-thua-race
   skip; row exhausted/không claim được skip; order-bound không được select) +
   `Test/Unit/Cron/PaymentRecoveryCronjobTest.php` (delegate + exception-safe +
   summary log). Style mirror `PaymentAttemptLifecycleTest` (mock repository/
   connection/commandPool, real `PaymentAttempt`).
7. **Docs** — `README.md` (mục recovery + cron), `CHANGELOG.md` (entry mới).
8. **Evidence** — `.ai/evidence/TASK-4ZW0WG/` (php-lint, phpcs, phpunit,
   db-schema-after-upgrade, compile, validators).

## 2. Rủi ro & biện pháp

| Rủi ro | Biện pháp |
|---|---|
| Double-order giữa recovery × IPN/Return | finalizer exactly-once (row lock + unique); claim WHERE re-check; lifecycle chỉ transition nơi fresh state cho phép |
| False-fail mất tiền | AC3: mọi result không parse được/exception → no mutation; chỉ resultCode parse được ≠0/pending mới `recordVerifiedFailure` |
| Exhaustion chặn nhầm IPN hợp lệ | marker operational-only, không quarantine; selection + claim đều filter; IPN/Return không đọc marker |
| Schema drift | additive-only, default an toàn; whitelist regenerate; `setup:upgrade` verify trong throwaway env |
| Cron chồng nhau | claim atomic per-row; batch + budget bounded; không global lock cần thiết (ZaloPay parity) |

## 3. Non-scope

Refund reconciliation (#4); generic queue/message framework; high-frequency
polling; finalizer/order-placement mới; sửa ReturnProcessor nhánh `-1` hay
config-key `payment_action` (chỉ flag cho TL); system.xml toggle/frequency.

## 4. Outcomes / handoff

- Definition of done theo issue EVIDENCE/HANDOFF: comment READY_FOR_REVIEW lên
  issue #5 với branch/base/TIP, validation, known risks.
- Không push bất kỳ remote nào nếu không có lệnh (global git rule); merge vào
  `dev/development/thanhle` chỉ khi user nghiệm thu.
