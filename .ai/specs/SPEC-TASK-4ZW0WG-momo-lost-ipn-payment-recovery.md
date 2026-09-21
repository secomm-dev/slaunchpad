# SPEC-TASK-4ZW0WG — MoMo lost/delayed-IPN payment recovery (MOMO-03)

Specification ID: SPEC-TASK-4ZW0WG
Specification Level: FULL

- Issue: github:thanhle74/slaunchpad#5
- Lane: MoMo · Epic #1 · Depends on #3 (DONE — durable attempt + canonical finalizer + v2/query path)
- Mode: A (payment/order path → high-risk, Tier 2)
- Risk: high (payment reconciliation + additive schema + cron)
- Branch: `thanhle74/momo-momo-03-recover-paid-attempts-when-authorit`
- BASE: `26c4ef6e0dd2cac7d6b3d57f66c4823e8bdae0e1` (verified: HEAD == BASE khi start)
- RUN_ID: RUN-20260921-TASK4ZW0WG-6f2a41 · host thanhle-aloha

## 1. Goal

Trong payment-first (MOMO-01), nếu IPN có thẩm quyền bị mất/trễ và browser Return
không xảy ra, một MoMo transaction **đã được trả tiền** có thể ở lại `active`/`paid`
mãi không có Magento Sales Order (bằng chứng thực tế: sự cố #13 — order-creation
fail vì defect runtime không liên quan). Task này thêm **một** recovery worker
bounded, cron-driven: query `v2/query` chủ động cho các attempt quá window callback,
và route kết quả verified **qua đúng canonical services** của MOMO-01
(`PaymentAttemptLifecycle` + `OrderFinalizer::finalizeOrRecover`) — KHÔNG finalizer
thứ hai, KHÔNG tin browser params, retries bounded + idempotent, exhaustion có
durable evidence.

## 2. Current state (verified at BASE `26c4ef6e`)

- Attempt state machine (`Model/PaymentAttempt.php`): `initiated→active→paid→finalized`;
  `failed/stale/expired` terminal, không có outgoing edge. `retry_count` = lineage
  counter số attempt trước đó của quote **lúc tạo** (set 1 lần, `PaymentAttemptManagement.php:237`)
  — KHÔNG tái dùng làm recovery budget.
- Bảng `secomm_momo_payment_attempt` có sẵn: `requires_reconciliation`,
  `reconciliation_code`, `order_id` (unique), `amount` (frozen VND), `store_id`.
  Chưa có cột recovery budget/exhaustion.
- `PaymentAttemptLifecycle::recordVerifiedPaid()` trên trạng thái non-payable
  (INITIATED/FAILED/STALE/EXPIRED) → quarantine `late_paid_terminal_state`
  (PaymentAttemptLifecycle.php:96-99) ⇒ recovery CHỈ được select `active`/`paid`.
- `recordVerifiedFailure()` trên `paid` → quarantine `provider_state_conflict`
  (không bao giờ regress PAID) ⇒ gọi từ recovery là an toàn concurrency.
- Query path: `MoMoQueryCommand` (pool key `query_transaction`), subject
  `['order_ref' => string, 'attempt' => PaymentAttemptInterface]`;
  `QueryValidator` kiểm echo partnerCode/orderId/requestId + amount numeric
  (`resultCode` KHÔNG được validator bắt buộc). Query request dùng đúng
  `attempt.order_ref` làm MoMo `orderId`; query `requestId` minted fresh mỗi lần
  (đúng contract MoMo — không phải reuse create-time `request_id`).
- Chưa có cron/observer/queue nào trong `Secomm_MoMo`; recovery hiện phụ thuộc
  IPN retry của MoMo + duplicate Return hits.

## 3. Design decisions

1. **Reuse-only mutation path**: mọi state mutation qua `PaymentAttemptLifecycle`,
   order placement duy nhất qua `OrderFinalizer::finalizeOrRecover()` (refuse
   ContractMismatchException từ finalizer, không tự đặt order). Không tạo service
   mutation riêng. (Issue SAFETY: "Do not create a second order-finalizer".)
2. **Schema additive (Tier-2 flag)**: 2 cột mới trên `secomm_momo_payment_attempt` —
   `recovery_attempts` smallint unsigned NOT NULL default 0 (query budget per row)
   và `recovery_exhausted` boolean NOT NULL default false (marker operational-only).
   Lý do không tái dùng `retry_count` (semantic clash — §2) và không đánh dấu
   exhaustion bằng `requires_reconciliation` (sẽ biến exhaustion thành money-real
   quarantine → chặn luôn IPN hợp lệ đến sau, đi ngược Goal).
3. **Claim-before-HTTP** (ZaloPay parity, đã qua 4 vòng review): 1 atomic
   conditional `UPDATE` tăng `recovery_attempts` + set `recovery_exhausted` (nếu
   claim này tiêu nốt budget) CHƯỚC khi gọi MoMo. Thua race (IPN/Return/cron khác
   mutate row trước) → skip row. Không giữ DB transaction/row lock qua HTTP.
4. **Selection deterministic, bounded**: `payment_status IN (active, paid)`,
   `order_id IS NULL`, `requires_reconciliation != 1`, `recovery_exhausted != 1`,
   `recovery_attempts < max`, `created_at <= now − window`, order `entity_id ASC`,
   page size = batch. FINALIZED/order-bound/quarantined rows không bao giờ được
   select (AC5, AC2).
5. **Phân loại resultCode (AC3 chặt hơn ReturnProcessor)**:
   - `7000`/`7002` → PENDING: không mutation (như ReturnProcessor).
   - `0` → PAID guards theo đúng thứ tự ReturnProcessor: amount ≠ frozen snapshot
     → `recordAmountMismatch(source 'Recovery')`; transId không khớp `/^\d+$/`
     hoặc ≤ 0 → `recordProviderIdentityUnavailable(source 'Recovery-query')`;
     còn lại → `recordVerifiedPaid(orderRef, transId)` → fresh `paid|finalized` và
     `!requires_reconciliation` → `finalizeOrRecover(fresh, transId)`.
   - giá trị parse được khác (≠ 0, ≠ pending) → `recordVerifiedFailure` — lifecycle
     tự bảo vệ (PAID/FINALIZED không regress; conflict → quarantine).
   - **exception (timeout/transport/validator) hoặc `resultCode` thiếu/không
     parse-được là số nguyên → AMBIGUOUS: log error, KHÔNG mutation, KHÔNG
     false-fail** — chờ run sau (budget đã consume bởi claim, như ZaloPay).
     (Lưu ý quan sát cho TL: ReturnProcessor hiện map thiếu resultCode → `-1` →
     failure — ngoài scope, không sửa.)
6. **Cron tĩnh, không toggle**: job `secomm_momo_payment_recovery_cronjob`,
   group `default`, `*/5 * * * *` — mirror precedent
   `secomm_zalopay_payment_recovery_cronjob`. Không thêm system.xml field. Cron
   class thin (`Cron/PaymentRecoveryCronjob`) — toàn logic trong
   `Service/PaymentRecovery` (đúng [BLOCK] "no logic in handlers/cron wrappers").
7. **Chạy cả khi MoMo disabled**: attempt row là nguồn sự thật của tiền đã commit;
   dừng recovery khi method disabled sẽ bỏ tiền đã trả. Ghi risk cho TL.
8. **Config defaults trong `config.xml`** (`payment/momo_payment/`):
   `recovery_window=15` (phút), `recovery_batch_size=25`, `recovery_max_attempts=5`
   — đọc qua `ScopeConfigInterface` với floor 1 (ZaloPay parity).
9. **Exhaustion evidence (issue SCOPE dòng cuối)**: marker per-row
   `recovery_exhausted` + log `critical` đúng 1 lần khi row tiêu nốt budget.
   Marker operational-only: không quarantine, không chặn IPN/Return hợp lệ resolve
   sau đó; chỉ dừng query chủ động (selection + claim đều filter).

## 4. Components

| File | Thay đổi |
|---|---|
| `app/code/Secomm/MoMo/etc/db_schema.xml` | +2 cột `recovery_attempts`, `recovery_exhausted` |
| `app/code/Secomm/MoMo/etc/db_schema_whitelist.json` | regenerate |
| `app/code/Secomm/MoMo/Api/Data/PaymentAttemptInterface.php` | +2 const + getter/setter |
| `app/code/Secomm/MoMo/Model/PaymentAttempt.php` | +implement getter/setter |
| `app/code/Secomm/MoMo/Service/PaymentRecovery.php` | MỚI — worker |
| `app/code/Secomm/MoMo/Cron/PaymentRecoveryCronjob.php` | MỚI — thin cron |
| `app/code/Secomm/MoMo/etc/crontab.xml` | MỚI |
| `app/code/Secomm/MoMo/etc/config.xml` | +3 defaults recovery |
| `app/code/Secomm/MoMo/Test/Unit/Service/PaymentRecoveryTest.php` | MỚI |
| `app/code/Secomm/MoMo/Test/Unit/Cron/PaymentRecoveryCronjobTest.php` | MỚI |
| `app/code/Secomm/MoMo/README.md`, `CHANGELOG.md` | docs |

## 5. Flow

```
cron */5 (group default) → Cron\PaymentRecoveryCronjob::execute()
  → Service\PaymentRecovery::execute():
    selection (deterministic, bounded, no lock)
    ├─ với mỗi attempt:
    │   claim: atomic UPDATE (budget+1, exhausted-if-last) WHERE trạng thái còn hợp lệ
    │   ├─ claim fail (thua race) → skip, không đếm claimed
    │   └─ claim ok → query_transaction (HTTP NGOÀI TX, identity = attempt gốc):
    │        7000/7002            → PENDING: không mutation
    │        0 + amount lệch    → recordAmountMismatch('Recovery') → KHÔNG order
    │        0 + transId xấu    → recordProviderIdentityUnavailable → KHÔNG order
    │        0 + hợp lệ         → recordVerifiedPaid → finalizeOrRecover (≤1 order)
    │        ≠0 parse được      → recordVerifiedFailure (lifecycle tự giữ PAID)
    │        exception/missing  → AMBIGUOUS: log, không mutation, chờ run sau
    └─ summary counters → cron log info (critical khi có exhaust/error)
```

## 6. Acceptance criteria (issue #5) → cơ chế + kiểm chứng

| AC | Cơ chế | Bằng chứng test |
|---|---|---|
| AC1 query PAID → đúng 1 order | recordVerifiedPaid → finalizeOrRecover (finalizer idempotent: row lock + unique order_ref/order_id) | PaymentRecoveryTest: PAID → finalizer called once với transId đúng |
| AC2 pending/fail → không order | 7000/7002 no-op; ≠0 → recordVerifiedFailure không đặt order | test pending không mutation; fail → lifecycle-only |
| AC3 timeout/ambiguous → không false-fail | exception/missing resultCode → no mutation, chờ run sau | test query throw + test resultCode thiếu |
| AC4 idempotent, không duplicate | claim WHERE chặn row đổi trạng thái; finalizer exactly-once; re-run an toàn | test duplicate recovery; test claim-thua-race skip |
| AC5 FINALIZED short-circuit | selection loại finalized/order-bound; claim re-check | test không select row có order_id/status finalized |
| AC6 identity từ attempt gốc | query dùng attempt.order_ref; amount lock vs frozen snapshot | test assert subject `order_ref` + amount so snapshot |

## 7. Assumption / limitation log (cho TL review)

1. **Schema additive** trên module-owned table — Tier 2 (AGENTS §11). Không data
   migration (default 0/false cho rows cũ).
2. **Store scope**: cron chạy default scope (store 0) — cấu hình MoMo per-store
   khác nhau sẽ query sai keys. Attempt có `store_id` nhưng `Model/Config` đọc
   current-scope; chấp nhận như ZaloPay precedent (single-store hiện tại).
3. **MoMo disabled vẫn chạy recovery** (decision #7) — ngược trực giác "cron tắt
   theo method" nhưng đúng nghĩa tiền đã commit.
4. Quan sát ngoài scope (flag, không sửa): ReturnProcessor map thiếu `resultCode`
   → `-1` → failure (nghi false-fail biên); `OrderFinalizer::captureOrder` đọc
   `payment_action` trong khi system.xml field là `momo_payment_action`.
5. E2E sandbox chỉ chạy khi có credentials (issue: "if credentials are available").

## 8. Validation plan

- `php -l` changed files; PHPCS Magento2 (severity ≥ 6) changed files.
- PHPUnit: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\MoMo'`
  (baseline 182–185 PASS — không regress).
- `setup:di:compile` EXIT=0; `setup:upgrade` trong validation env (m2r-php /
  `/tmp/m2r`, DB `zt-mariadb106` — mirror evidence MOMO-02) → verify 2 cột + whitelist.
- `.ai/bin/project-ai-validate --check-specs --check-identity --check-records`.
- Evidence `.ai/evidence/TASK-4ZW0WG/`.
