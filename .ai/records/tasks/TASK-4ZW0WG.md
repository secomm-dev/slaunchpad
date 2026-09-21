---
id: TASK-4ZW0WG
type: task
title: '[MoMo][MOMO-03] Recover paid attempts when authoritative IPN is lost'
project_code: SLP
parent: NONE
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md
risk: high
status: ready_for_review
created: 2026-09-21
updated: 2026-09-21
legacy_ids: []
decisions: []
decision_assessment: none-material
components:
  - Secomm_MoMo
source_areas:
  - app/code/Secomm/MoMo/etc/db_schema.xml
  - app/code/Secomm/MoMo/Api/Data/PaymentAttemptInterface.php
  - app/code/Secomm/MoMo/Service/PaymentRecovery.php
  - app/code/Secomm/MoMo/Cron/PaymentRecoveryCronjob.php
  - app/code/Secomm/MoMo/etc/crontab.xml
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit: 26c4ef6e0dd2cac7d6b3d57f66c4823e8bdae0e1
last_verified: 2026-09-21
supersedes: []
external_refs:
  - github:thanhle74/slaunchpad#5
plan_ref: ../../plans/TASK-4ZW0WG-implementation-plan.md
---

# [SLP][TASK-4ZW0WG] [MoMo][MOMO-03] Recover paid attempts when authoritative IPN is lost

GitHub issue [#5](https://github.com/thanhle74/slaunchpad/issues/5) (lane MoMo,
epic #1, phụ thuộc #3 — DONE). Bounded automatic recovery cho lost/delayed IPN:
cron query `v2/query` chủ động → verified PAID route qua đúng canonical
`PaymentAttemptLifecycle` + `OrderFinalizer::finalizeOrRecover` của MOMO-01;
retries bounded + idempotent; exhaustion có durable evidence (marker per-row +
critical log). Promoted sau sự cố #13 (order-creation fail vì defect runtime
không liên quan để lại tiền đã verified không có order).

## Mini Spec

### Goal

Không để tiền MoMo đã verified ở lại vô hạn không có Magento Sales Order khi
IPN mất/trễ — bằng một worker bounded, tái sử dụng đúng canonical services,
không tạo finalizer thứ hai, không cho browser Return bypass verification.

### Expected Behavior

- Cron `*/5` chọn các attempt `active`/`paid` quá callback window (15'), chưa
  bind order, chưa quarantine, còn recovery budget → claim atomic (tăng
  `recovery_attempts`, set `recovery_exhausted` nếu tiêu nốt budget) → query
  `v2/query` bằng đúng `order_ref` của attempt.
- `resultCode 0` + amount khớp + transId hợp lệ → `recordVerifiedPaid` →
  `finalizeOrRecover` — đúng một order, idempotent qua re-run.
- `7000/7002` → không mutation; `≠0` parse được → `recordVerifiedFailure`
  (lifecycle tự giữ PAID/FINALIZED); exception/thiếu resultCode → AMBIGUOUS,
  không mutation, không false-fail.
- Exhaustion: dừng query chủ động cho row đó (marker + critical log); IPN/Return
  hợp lệ đến sau vẫn resolve được tiền (marker operational-only, không quarantine).

### Constraints / Rules

- Mọi state mutation qua `PaymentAttemptLifecycle`; order duy nhất qua
  `OrderFinalizer::finalizeOrRecover` — KHÔNG finalizer thứ hai (issue SAFETY).
- Không giữ DB lock/transaction qua MoMo HTTP.
- Selection chỉ `active`/`paid` (lifecycle quarantine `late_paid_terminal_state`
  nếu PAID evidence rơi vào trạng thái terminal khác).
- Tier 2 surface: additive schema (2 cột) + payment cron — flag trong PR cho TL.

### Out of Scope

- Refund reconciliation (#4); generic queue framework; high-frequency polling;
  finalizer mới; sửa ReturnProcessor nhánh `-1` / config-key `payment_action`
  (chỉ quan sát); system.xml toggle/frequency.

### Acceptance Criteria

- AC1: Lost/delayed IPN + provider query xác nhận PAID → cùng attempt finalize
  đúng một order.
- AC2: Provider query pending/fail → không tạo order.
- AC3: Query timeout/ambiguous → attempt giữ nguyên trạng thái, không false-fail.
- AC4: Recovery lặp lại idempotent, không duplicate order/invoice.
- AC5: FINALIZED attempt short-circuit an toàn (không bao giờ được select).
- AC6: Recovery dùng đúng merchant/provider identities của attempt gốc
  (`order_ref` làm MoMo orderId; amount lock với frozen snapshot).

## Ghi chú thực thi

- RUN_ID: RUN-20260921-TASK4ZW0WG-6f2a41 · host thanhle-aloha · worktree
  `momo-momo-03-recover-paid-attempts-when-authorit` tại BASE `26c4ef6e`
  (verified HEAD == BASE khi start).
- Thiết kế chốt với user (2026-09-21): thêm 2 cột `recovery_attempts` +
  `recovery_exhausted` (ZaloPay parity; `retry_count` là lineage counter —
  semantic khác), cron tĩnh `*/5 * * * *` group `default` không toggle.
- Validation env: mirror evidence MOMO-02/HF1 (container m2r-php, throwaway
  copy `/tmp/m2r`, DB `zt-mariadb106`).
- Kết quả validation 2026-09-21 (full: `.ai/evidence/TASK-4ZW0WG/`): php -l
  EXIT=0 toàn bộ; PHPCS Magento2 ≥6 EXIT=0; PHPUnit `Secomm\MoMo` 200 tests /
  562 assertions OK (baseline HF1 185/515 + 15 test mới; 5 PHPUnit deprecations
  pre-existing giống baseline); `setup:di:compile` EXIT=0; `setup:upgrade`
  EXIT=0 + SHOW CREATE TABLE xác nhận 2 cột mới; whitelist hand-add 2 cột
  theo đúng format mảng đã commit (container generate-whitelist emits format
  object cũ — churn không cần thiết); `project-ai-validate` 58 FAIL đều
  pre-existing của task khác, 0 finding cho TASK-4ZW0WG.
- Tier-2 surfaced cho TL review: additive schema (2 cột), payment cron mới,
  store-scope limitation (cron chạy default scope), quan sát ReturnProcessor
  `-1`→failure khi response thiếu resultCode (không sửa trong task này).
