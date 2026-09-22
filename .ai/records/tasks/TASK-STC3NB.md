---
id: TASK-STC3NB
type: task
title: 'ShippingCore COD payment identification — architecture v4 §4.1 delta (resolver + config)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-STC3NB — contract shape theo address-shipping.md Revision v4 §4.1 (đã TL/SA amend)
specification_ref: ../../specs/SPEC-TASK-STC3NB-shippingcore-cod-payment-identification.md
risk: low                     # 1 interface + 1 resolver + admin config field; chưa có consumer code
status: in_progress
priority: high
decision_assessment: none-material   # COD identification đã được approve trong architecture v4 §4.1; các gap PARTIAL khác DEFER + REPORT
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-11
updated: 2026-09-11
owner: [dev]
related_tickets: [TASK-M3ME32, TASK-NQT782]
---

# [SLP][FEAT-YA2C0W][TASK-STC3NB] ShippingCore COD payment identification — architecture v4 §4.1 delta (resolver + config)

## Embedded Mini-Spec

*(đầy đủ tại specs/SPEC-TASK-STC3NB-shippingcore-cod-payment-identification.md, FULL)*

### Goal

Implement COD payment identification theo architecture v4 §4.1: `Api\Cod\CodPaymentMethodResolverInterface::isCod(string): bool`
+ `Model\Cod\ConfiguredCodPaymentMethodResolver` (config `secomm_shippingcore/cod/payment_methods`,
comma-separated, trim + exact strict match, empty/malformed → safe false). Identification thuần —
carrier (GHN/GHTK) sau này consume `order.getPayment().getMethod()` → `isCod(...)`.

### Expected Behavior

1. Contract `isCod(string $paymentMethodCode): bool` — scalar-in/bool-out, không OrderInterface.
2. Resolver: config comma-separated → trim từng code → filter rỗng → exact strict `in_array`
   (case-sensitive; prefix/similar → false); query code trim; empty/null/malformed config → false;
   không default COD method.
3. Admin config: section `secomm_shippingcore` → group `cod` → field `payment_methods`; default
   rỗng; ACL `Magento_Backend::stores`. DI preference interface → resolver.

### Constraints / Rules

- KHÔNG: `Secomm_Cod`, eligibility engine, COD amount resolver/`getCollectAmount`, surcharge,
  min/max, risk scoring, OTP, reconciliation, settlement, payment visibility orchestration,
  service-level COD eligibility, partial payment/deposit.
- KHÔNG OrderInterface trong COD contract; KHÔNG hardcode Magento COD implementation.
- DEFER (report, không code): capability per-operation + representations (interface đổi sẽ phá
  `GhnAddressCapability`/`GhtkAddressCapability` — cần task riêng Tier-2);
  `CanonicalResolutionSnapshot` persistence (DB = Tier-2).

### Out of Scope

Carrier module changes · COD framework items (trên) · capability refactor · snapshot persistence.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-STC3NB (tóm tắt): contract + DI preference · config semantics (multi/
exact/normalize/safe-false) · 0 hardcode · system.xml/config.xml · tests §6 pass + regression
ShippingCore 0 new fail · README/CHANGELOG + working memory sync.

## Plan

`../plans/TASK-STC3NB-implementation-plan.md`
