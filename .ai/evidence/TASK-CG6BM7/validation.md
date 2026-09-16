---
id: EVIDENCE-TASK-CG6BM7-VALIDATION
work_item: TASK-CG6BM7
spec: SPEC-TASK-CG6BM7-zalopay-postfix-audit
kind: l3-validation
created: 2026-09-16
updated: 2026-09-16 (corrective round)
environment: container slaunchpad-phpfpm-1 (PHP 8.3.20, PHPUnit 10.5.x, PHPCS w/ Magento2 standard); unit harness /tmp/zt (worktree module copy + prepend spl_autoload_register — bắt buộc vì composer.json có PSR-0 fallback "": app/code làm main src thắng nếu append); di-compile tree /tmp/m2 (bản sao Magento đầy đủ trong container, share workspace không bị ghi)
pre_correction_head: 70416b7aa6889276818b72e311a9955cbf5ed578
---

# §12 L3 validation — corrective round (kết quả trung thực)

## PHP syntax (php -l)
- PASS — 16/16 file PHP đã đổi lint sạch (PHP 8.3.20 trong container).

## Unit — ZaloPay (toàn bộ `Test/Unit`)
- PASS — **OK, 260 tests / 937 assertions** (baseline corrective head của round 1: 223/825 →
  corrective round thêm 37 test / 112 assertion, không test nào đỏ).
  Suite corrective mới/đổi:
  - `PendingRefundManagerTest` — 11 test / 42 assertions (lifecycle: registerPending không đụng
    totals, finalize native exact-once + marker, race already-processed, recovery đã-REFUNDED,
    rollback khi accounting fail, consume/terminate budget).
  - `CreditmemoRefundPluginTest` — 11 test / 46 assertions (guard offline/in-flight/missing
    invoice-txn, PROCESSING STOP trước core, SUCCESS mark + proceed, FAIL propagate nguyên vẹn,
    TRANSPORT track-đúng-cách, pass-through non-ZaloPay/null outcome).
  - `RefundCronjobTest` (rewrite) — 13 test / 33 assertions (SUCCESS finalize, duplicate no-op,
    finalize-fail tiêu budget, FAIL terminal `refund_failed:`, PROCESSING budget, transport tiêu
    budget, budget cap critical, malformed payload / thiếu m_refund_id / missing creditmemo /
    state drift → terminal, batch sống sót).
  - `RefundCommandTest` (rewrite) — 11 test / 45 assertions (provider-only: SUCCESS, PROCESSING +
    query thành công/đang xử lý/transport-fail/FAIL, rc=2 message map `Zalopay: Refund failed.`,
    raw provider text KHÔNG lọt UI, transport mang tracking outcome, marker skip, thiếu creditmemo).
  - `OrderFinalizerTest` — 24 test / 92 assertions (giữ EMAIL 1–7 + mới EMAIL 8–10: thua claim ⇒
    không gửi; send fail ⇒ release claim ⇒ retry được; send thành công ⇒ release).
  - `PaymentAttemptResourceTest` — 3 test / 5 assertions (pin SQL: conditional UPDATE claim,
    0-row ⇒ false, release token-guarded).
- Hạn chế khai báo: đây là UNIT với mock ở biên DB/HTTP (connection, repository, gateway client).
  Invariant CLOSED được chứng minh bằng unit + source-call-chain (proofs P7–P8), KHÔNG phải bằng
  integration runtime.

## PHPCS (Magento2 standard, module-wide)
- ERRORS: **0** (`-n` exit 0 trên toàn module).
- WARNINGS: 321 module-wide (round 1: 284) — style/annotation; các file đổi đều 0 error
  (đếm per-file: RefundCommand W6, RefundCronjob W7, CreditmemoRefundPlugin W6,
  PendingRefundManager W2, OrderFinalizer W0, các test file W1–W17 — annotation style, khớp
  convention hiện có của module; xem M6 round 1).

## setup:di:compile
- **PASS (thật, corrective round)** — chạy `setup:di:compile` trên BẢN SAO Magento đầy đủ trong
  container (`/tmp/m2`: bin/app/vendor/generated/setup + module worktree thay cho module main),
  không đụng workspace/DB chung: **9/9 step, exit 0** ("Generated code and dependency injection
  configuration successfully").
- Xác minh thêm: `CreditmemoRefundPlugin` xuất hiện trong compiled
  `generated/metadata/*|plugin-list.php` cho mọi scope (global/frontend/webapi_rest/crontab).

## Regression
- Scope: `git status` — mọi thay đổi nằm trong `app/code/Secomm/ZaloPay` (+ `.ai`); không file
  nào ngoài ZaloPay bị đụng (auth.json modified pre-existing, KHÔNG commit — chứa token).
- Cross-module proof: grep toàn `app/code` — KHÔNG có tham chiếu PHP class `Secomm\ZaloPay`
  nào từ module khác (chỉ 1 mention chữ "ZaloPay" trong docblock GhtkConfig, không phải code),
  ⇒ thay đổi nội bộ ZaloPay không thể regress module khác.
- Round-1 regression evidence giữ nguyên tính hợp lệ (1047 tests, 8e+2f pre-existing ở
  FulfillmentCore/Tracking, chứng minh pre-existing bằng baseline rerun — không liên quan ZaloPay).

## Integration runtime
- **INTEGRATION = ENVIRONMENT_BLOCKED** (không claim PASS) — bất đổi round 1: DB
  `magento_integration_tests` đã tồn tại trên MySQL dev chung; chạy integration suite sẽ DROP/đè
  DB dev chung và ghi vào share — vi phạm SHARED_WORKSPACE_MODIFIED=NO.

## §9 SMTP/staging (READ-ONLY)
- KHÔNG đụng bất kỳ env/system config/credentials/Mailpit/Mageplaza SMTP nào (bất biến qua round).

## Ghi chú harness (lesson cho session sau)
- `phpunit.xml` của module không tự chạy được ngoài Magento root; harness sandbox phải
  `spl_autoload_register(..., throw=true, prepend=true)` — nếu KHÔNG prepend, Composer ClassLoader
  của Magento (PSR-0 fallback `"": app/code/, generated/code/`) thắng và resolve class module từ
  MAIN src (khác branch) → kết quả test sai lặng lẽ. Đã từng gây 16 failure "phantom" trước khi fix.
