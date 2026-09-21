---
id: TASK-3083BD
type: task
title: '[MoMo][MOMO-04] Make ReturnProcessor resultCode handling fail-safe'
project_code: SLP
parent: NONE
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-3083BD-momo-returnprocessor-resultcode-fail-safe.md
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
  - app/code/Secomm/MoMo/Service/ReturnProcessor.php
  - app/code/Secomm/MoMo/Service/PaymentRecovery.php
  - app/code/Secomm/MoMo/Service/PurchaseQueryClassifier.php
  - app/code/Secomm/MoMo/Service/PurchaseQueryOutcome.php
  - app/code/Secomm/MoMo/Test/Unit/Service/ReturnProcessorTest.php
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit: a87255f8c895b6f59ae23bb3ce776aa3d665a430
last_verified: 2026-09-21
supersedes: []
external_refs:
  - github:thanhle74/slaunchpad#16
plan_ref: ../../plans/TASK-3083BD-implementation-plan.md
---

# [SLP][TASK-3083BD] [MoMo][MOMO-04] Make ReturnProcessor resultCode handling fail-safe

GitHub issue [#16](https://github.com/thanhle74/slaunchpad/issues/16) (lane MoMo,
epic #1, depends #5 — DONE). Browser Return's authoritative `v2/query`
classification hiện là legacy "non-zero = failure" ⇒ false-fail được tiền đang
xử lý/đã trả (`1000`, `9000`, request/system codes, missing/unparseable, unmapped).
Task thay bằng fail-safe classification nhất quán MOMO-03, extract thành shared
`PurchaseQueryClassifier` dùng chung cả `ReturnProcessor` lẫn `PaymentRecovery`
(single source of truth, hết drift risk giữa hai path).

## Mini Spec

### Goal

Không bao giờ false-fail một payment attempt từ browser Return: chỉ resultCode nằm
trong explicit provider-documented allowlist mới được kết luận (paid/pending/final
failure); mọi code khác (missing/unparseable/request-system/unmapped) → AMBIGUOUS —
không mutation, không order, không failure, khách được thông báo retry/check-back.

### Expected Behavior

- PAID `0`/`9000` (contract 1-step `captureWallet`/default autoCapture=true) →
  vẫn qua đủ amount lock + positive-transId + lifecycle guards trước
  `recordVerifiedPaid` → `OrderFinalizer` → `SuccessSessionPreparer` (AC3/AC9).
- PENDING `1000`/`7000`/`7002` → no mutation, "still being processed. Please
  check back shortly." (AC1/AC2).
- VERIFIED FAILURE chỉ allowlist final `98`/`99`/`1001–1007`/`1017`/`1026`/
  `2019`/`4001`/`4002`/`4100` → `recordVerifiedFailure` (AC8; lifecycle tự giữ
  PAID/FINALIZED — AC11).
- AMBIGUOUS: request/system `10–13`/`20–22`/`40–43`/`45`/`47`, missing,
  unparseable, MỌI code unmapped → log error có context + throw
  "could not be verified right now" — ZERO mutation (AC4–AC7).
- Browser GET params vẫn non-authoritative; `v2/query` là input duy nhất của
  money state (AC10).

### Constraints / Rules

- Mọi state mutation qua `PaymentAttemptLifecycle`; order duy nhất qua
  `OrderFinalizer::finalizeOrRecover` — không finalizer thứ hai.
- Classifier chỉ phục vụ PURCHASE query — refund (`RefundResultClassifier`,
  `9000` nghĩa khác) không đụng đến.
- Default clause của classifier luôn AMBIGUOUS (unmapped không bao giờ FAILED).

### Out of Scope

MOMO-05 config-key drift; refund semantics; IPN path; schema/migration; new
payment states; checkout UX; generic retry framework; ZaloPay; Bitbucket sync.

### Acceptance Criteria

- AC1: `1000` → pending/no payment-state mutation.
- AC2: `7000`/`7002` → pending/no mutation (giữ nguyên).
- AC3: `9000` → cùng guarded paid/finalization path như `0`.
- AC4: `10` → ambiguous/no failure mutation.
- AC5: missing resultCode → ambiguous/no mutation.
- AC6: unparseable resultCode → ambiguous/no mutation.
- AC7: unknown code (`424242`) → ambiguous/no mutation.
- AC8: documented final failure (`1001`) → `recordVerifiedFailure`, no order.
- AC9: successful Return vẫn rebuild checkout success session.
- AC10: browser GET params non-authoritative (giữ nguyên).
- AC11: finalized/paid concurrency safety không đổi.
- AC12: focused tests + full `Secomm\MoMo` unit suite pass; PHPCS + DI compile pass.

## Ghi chú thực thi

- RUN_ID: RUN-20260921-TASK3083BD-887acd · host thanhle-aloha · worktree
  `momo-momo-04-make-returnprocessor-resultcode-han` tại BASE `a87255f8`
  (verified HEAD == BASE khi start). Task branch chính thức trên origin:
  `thanhle74/momo-momo-04-returnprocessor-resultcode-fail-safe` @ cùng BASE —
  local branch rename khớp, KHÔNG tạo branch mới (coordinator directive).
- Tier-2 surface cho TL review: payment state classification trên browser-Return
  path (Owner authorization GRANTED trên issue #16 trước khi start).
- Kết quả validation 2026-09-21 (full: `.ai/evidence/TASK-3083BD/`): php -l
  EXIT=0 toàn bộ; PHPCS Magento2 ≥6 EXIT=0; PHPUnit `Secomm\MoMo` 253 tests /
  746 assertions OK (baseline MOMO-03 200/562 + 48 test mới; 5 PHPUnit
  deprecations pre-existing giống baseline); `setup:di:compile` EXIT=0
  (generated/code wiped first); `project-ai-validate --check-specs
  --check-identity --check-records`: 58 FAIL đều pre-existing của task khác
  (bộ giống baseline MOMO-03), 0 finding cho TASK-3083BD.
- Thiết kế chốt: extract shared `PurchaseQueryClassifier` + value object
  `PurchaseQueryOutcome` (issue DESIGN CONSTRAINT — tránh copy thứ hai của
  MOMO-03 allowlists); 3 log messages phân biệt cause AMBIGUOUS
  (unparseable/request-system/unmapped) được giữ nguyên qua `getReason()`.
