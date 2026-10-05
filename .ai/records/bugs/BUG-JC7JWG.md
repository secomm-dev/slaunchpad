---
id: BUG-JC7JWG
type: bug
title: '[MoMo] Bind the recovery worker to the configured command pool'
project_code: SLP
parent: null
external_refs:
  ticket: SLP-17
legacy_ids: []
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md
plan_ref: ../../plans/BUG-JC7JWG-implementation-plan.md
risk: high
status: ready_for_review
created: 2026-10-02
updated: 2026-10-02
decisions: []
decision_assessment: none-material
components:
  - Secomm_MoMo
source_areas:
  - app/code/Secomm/MoMo/etc/di.xml
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-10-02
supersedes: []
---

# [SLP][BUG-JC7JWG] [MoMo] Bind the recovery worker to the configured command pool

External ticket: SLP-17. The MOMO-03 recovery worker has a
`CommandPoolInterface` constructor dependency, but `PaymentRecovery` did not
receive the module's `MoMoCommandPool` in `etc/di.xml`. That pool defines the
authoritative `query_transaction` command used by recovery. Without the binding,
the scheduled worker cannot reliably resolve and execute the MoMo query command.

Specification: [MOMO-03 lost-IPN recovery](../../specs/SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md).

## Mini Spec

### Goal

Wire the MoMo recovery worker to the same configured command pool used by the
other MoMo payment services so scheduled recovery can query eligible attempts.

### Expected Behavior

- Magento DI constructs `Secomm\MoMo\Service\PaymentRecovery` with
  `MoMoCommandPool` for its `commandPool` argument.
- The worker resolves the existing `query_transaction` command and follows the
  MOMO-03 recovery behavior in the referenced Full Spec.
- No payment state transition or order-finalization behavior changes.

### Constraints / Rules

- Keep the change in `Secomm_MoMo` DI configuration.
- Preserve the existing `MoMoCommandPool` definition and MOMO-03 lifecycle,
  amount, identity, retry, and finalization rules.
- Payment and order-lifecycle changes are high risk and require Tier 2 review
  under `.ai/AGENTS.md` §§ 8.6, 9, 11, and 12.

### Out of Scope

- Changes to recovery logic, payment status classification, schema, cron
  schedule, provider API behavior, refunds, or checkout.

### Acceptance Criteria

- AC-001: `etc/di.xml` injects `MoMoCommandPool` into
  `Secomm\MoMo\Service\PaymentRecovery::$commandPool`.
- AC-002: The bound pool retains the existing `query_transaction` command
  mapping and no other payment service wiring changes.
- AC-003: Magento DI can construct the recovery worker and scheduled cron path
  can execute the MOMO-03 query when an eligible attempt exists.
- AC-004: MOMO-03 lifecycle and order-finalization behavior remain unchanged.

## Implementation / Verification

The staged `di.xml` change adds the `commandPool` argument for
`PaymentRecovery`.

- XML parsing passed with Python `xml.etree.ElementTree`.
- The project validator reported no finding for this bug record or plan, but
  exits `INVALID` on 112 existing project-wide findings in unrelated records.
- Magento runtime DI/cron QC remains pending for Tier 2 review.
