---
id: TASK-Y6SGHE
type: task
title: '[MoMo][MOMO-01-HF1] Fix missing payment-attempt DI binding blocking non-MoMo orders'
project_code: SLP
parent: NONE
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-Y6SGHE-momo-payment-attempt-di-binding.md
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
  - app/code/Secomm/MoMo/etc/di.xml
  - app/code/Secomm/MoMo/Plugin/Quote/CartManagementPlaceOrderGuard.php
  - app/code/Secomm/MoMo/Model/PaymentAttemptRepository.php
  - app/code/Secomm/MoMo/Test/Unit/Di/PaymentAttemptDiBindingTest.php
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit: 88050d2d8cf20b768e3ff9c7b2198b1a4e2fd5e9
last_verified: 2026-09-21
supersedes: []
external_refs:
  - github:thanhle74/slaunchpad#13
plan_ref: ../../plans/TASK-Y6SGHE-implementation-plan.md
---

# [SLP][TASK-Y6SGHE] [MoMo][MOMO-01-HF1] Fix missing payment-attempt DI binding blocking non-MoMo orders

GitHub issue [#13](https://github.com/thanhle74/slaunchpad/issues/13) (lane MoMo, epic #1,
phụ thuộc #3 — DONE). Hotfix cho regression từ MOMO-01: đặt ZaloPay order triggers
`Error: Cannot instantiate interface Secomm\MoMo\Api\PaymentAttemptRepositoryInterface`
qua plugin global `Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard`.

## Mini Spec

### Goal

Binding DI hợp lệ cho `Secomm\MoMo\Api\PaymentAttemptRepositoryInterface` → mọi
place-order path (non-MoMo lẫn MoMo) khởi tạo guard plugin thành công; non-MoMo
giữ nguyên hành vi Magento; guard MoMo giữ nguyên hành vi chặn/cho phép.

### Expected Behavior

- Sau fix, preference `PaymentAttemptRepositoryInterface` → `Secomm\MoMo\Model\PaymentAttemptRepository`
  tồn tại trong `app/code/Secomm/MoMo/etc/di.xml` (global) → ObjectManager resolve
  được interface ở mọi area (frontend, webapi_rest, graphql, webapi_soap).
- `CartManagementPlaceOrderGuard` instantiate được từ ObjectManager trong clean DI state.
- `beforePlaceOrder` với non-MoMo quote → no-op (return null) — Magento behaviour không đổi.
- `beforePlaceOrder` với MoMo quote → vẫn chặn generic placement khi không có grant
  backed bởi persisted attempt triple (quote_id + attempt_id + order_ref), và vẫn
  cho phép đúng một pass-through của grant hợp lệ (OrderFinalizer path) — hành vi
  MOMO-01 không đổi.
- `Api\Data\PaymentAttemptInterface` cố tình KHÔNG được thêm preference — không có
  ObjectManager-created path nào resolve interface này (xem spec §3.2).

### Constraints / Rules

- Chỉ thêm binding thực sự cần thiết bởi source/runtime — không speculative binding.
- Không sửa plugin/service/ZaloPay — không business-rule change (NON_SCOPE #13).
- Không đổiarea registration của guard (global, mọi area — theo thiết kế MOMO-01).
- Validation từ clean generated/DI state trong throwaway env (`/tmp/m2r`), không
  đụng generated của main checkout.

### Out of Scope

- Redesign MOMO-01; MOMO-03 recovery; ZaloPay code; business-rule payment; production
  push; DI cleanup không liên quan.

### Acceptance Criteria

- AC1: `PaymentAttemptRepositoryInterface` resolves đúng `PaymentAttemptRepository`
  dưới Magento ObjectManager (runtime smoke từ clean DI).
- AC2: Clean `setup:di:compile` EXIT=0 với final tree.
- AC3: non-MoMo/ZaloPay place-order không còn fatal tại guard instantiation; guard
  là no-op với non-MoMo quote (unit test với repository thật).
- AC4: Guard MoMo giữ nguyên: block unauthorized placement, cho phép canonical
  authorized finalizer path (suite `CartManagementPlaceOrderGuardTest` giữ nguyên PASS).
- AC5: Regression coverage cho missing-DI failure tồn tại và chạy trong focused
  unit suite (`PaymentAttemptDiBindingTest`).
- AC6: Không có source change nào ngoài scope (di.xml preference + test + changelog
  + .ai artifacts).

## Ghi chú thực thi

- RUN_ID: RUN-20260921-TASKY6SGHE-e11bca · host thanhle-aloha · workspace worktree
  `13-momo-momo-01-hf1-fix-missing-payment-attempt` tại BASE `88050d2d`.
- Validation env: container `m2r-php` (PHP 8.3) + throwaway copy `/tmp/m2r` —
  tái sử dụng như MOMO-02; các module ngoài lẻ gây lỗi fresh install (Secomm_Ahamove,
  Mageplaza_ExtraFee, Hyva_Koti* sample patches) không liên quan và chỉ bị disable
  trong throwaway env khi cần (không đụng main checkout).
