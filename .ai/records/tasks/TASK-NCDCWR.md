---
id: TASK-NCDCWR
type: task
title: '[MoMo][MOMO-05] Align payment action config key with runtime behavior'
project_code: SLP
parent: NONE
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-NCDCWR-momo-payment-action-config-key.md
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
  - app/code/Secomm/MoMo/etc/adminhtml/system.xml
  - app/code/Secomm/MoMo/Model/Config.php
  - app/code/Secomm/MoMo/Service/OrderFinalizer.php
  - app/code/Secomm/MoMo/Test/Unit/Service/OrderFinalizerTest.php
  - app/code/Secomm/MoMo/README.md
  - app/code/Secomm/MoMo/CHANGELOG.md
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit: ad6e2d7fe32bf71188deff49e666cc2643089b8b
last_verified: 2026-09-21
supersedes: []
external_refs:
  - github:thanhle74/slaunchpad#17
plan_ref: ../../plans/TASK-NCDCWR-implementation-plan.md
---

# [SLP][TASK-NCDCWR] [MoMo][MOMO-05] Align payment action config key with runtime behavior

GitHub issue [#17](https://github.com/thanhle74/slaunchpad/issues/17) (lane
MoMo, epic #1, depends #16 — DONE). Admin field `momo_payment_action` viết
legacy key `payment/momo_payment/momo_payment_action` (ZERO readers) trong khi
runtime (`OrderFinalizer::captureOrder` + core `Order\Payment::place()`) đọc
canonical key `payment/momo_payment/payment_action` — thay đổi Payment Action
từ admin không bao giờ có hiệu lực. Task align: admin field rename sang
canonical key, constant `Model\Config::KEY_PAYMENT_ACTION` sửa + dùng làm
single source, default `authorize_capture` giữ nguyên; legacy install
provably unchanged (zero-reader + single-option proof — spec §2.2).

## Mini Spec

### Goal

Admin persistence và runtime read dùng MỘT canonical key
`payment/momo_payment/payment_action`; default `authorize_capture` giữ nguyên;
không đụng lifecycle/API contract/refund classification.

### Expected Behavior

- Admin save Payment Action → ghi canonical key; runtime (`OrderFinalizer`,
  core placement) đọc cùng key → config có hiệu lực thật.
- `authorize_capture` (default) → local capture sau verify như cũ (AC3).
- Giá trị khác/null → không capture, vẫn finalize (strict gate, test locked).
- Legacy row `momo_payment_action` (nếu có) inert — behavior mọi install
  hiện có unchanged (AC4).

### Constraints / Rules

- Không đổi mutation path (lifecycle + finalizer kiến trúc giữ nguyên).
- Không đụng refund/IPN/Return classification; không schema; không
  provider API contract; không source-model option expansion.

### Out of Scope

MOMO-04 classification; provider API result-code semantics; refund redesign;
schema/migration; ZaloPay; Bitbucket sync.

### Acceptance Criteria

- AC1: một canonical key được tài liệu hoá + dùng nhất quán.
- AC2: admin save path và runtime read path cùng key.
- AC3: default `authorize_capture` explicit + tested.
- AC4: legacy saved value không silently đổi behavior — no-migration decision
  documented (spec §2.2).
- AC5: OrderFinalizer/capture behavior covered bởi 2 focused tests mới.
- AC6: full `Secomm\MoMo` unit suite + PHPCS + DI compile pass.

## Ghi chú thực thi

- RUN_ID: RUN-20260921-TASKNCDCWR-ad6e2d · host thanhle-aloha · worktree
  `momo-momo-05-align-payment-action-config-key-wit`; local branch
  fast-forwarded `a87255f8` → BASE `ad6e2d7f` trước khi implement, rồi rename
  khớp branch chính thức `thanhle74/momo-momo-05-align-payment-action-config`
  (KHÔNG tạo branch mới — coordinator directive pattern MOMO-04).
- Tier-2 surface: payment capture configuration (Owner authorization trên
  issue #17 trước khi start).
- Validation 2026-09-21 (full: `.ai/evidence/TASK-NCDCWR/`): kết quả đã ghi
  ở evidence. Handoff cuối: implementation commit
  `eff9e7210633d1d2cc71606464b32ab3ee8e6aaa` pushed NON-FORCE lên GitHub
  `origin` (`ad6e2d7f..eff9e721`) dưới explicit Owner authorization; issue #17
  READY_FOR_REVIEW; Bitbucket untouched. Coordinator review @ TIP `eff9e721`:
  implementation ACCEPTED, doc-only correction round 1 syncs note này.
