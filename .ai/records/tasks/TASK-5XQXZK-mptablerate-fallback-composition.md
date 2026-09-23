---
id: TASK-5XQXZK
type: task
title: 'Mageplaza TableRate fallback composition — Magento-orchestrated carrier outcomes + per-method fallback groups/capabilities + city dimension'
project_code: SLP
parent: {type: feature, id: FEAT-GGTWXW}
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../specs/SPEC-TASK-5XQXZK-mptablerate-fallback-composition.md
risk: medium
status: in_progress
priority: high
decision_assessment: material
decisions: [DEC-TASK5XQXZK-001]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Launchpad/MageplazaTableRate/
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
  - app/code/Secomm/Ghtk/
  - app/code/Mageplaza/TableRateShipping/
changes_project_state: true
created: 2026-09-15
updated: 2026-09-15
owner: [dev]
related_tickets: [TASK-NQT782]
---

# [SLP][FEAT-GGTWXW][TASK-5XQXZK] Mageplaza TableRate fallback composition

## Embedded Mini-Spec

*(đầy đủ tại ../specs/SPEC-TASK-5XQXZK-mptablerate-fallback-composition.md, FULL — DEC-TASK5XQXZK-001)*

### Goal

`Launchpad_MageplazaTableRate` = composition/bridge: Magento own carrier execution; carriers
report outcome vào ShippingCore `CarrierRateOutcomeCollectorInterface` (pair
`(carrier_code, method_code)`); bridge trigger/append fallback ở outer seam
`Magento\Shipping\Model\Shipping::collectRates`; Mageplaza Method = fallback grouping identity
với per-method `show_to_customer`/`use_as_fallback`/membership; city dimension optional
zero-regression.

### Scope

In: collector + eligibility policy + 3 failure reasons mới (ShippingCore); GHN/GHTK report;
3 extension tables; outer plugin; visibility; city matching/precedence; admin UX; CSV;
deprecate global mode; tests/docs. Out: fan-out registry, business service enum, auto-dedup,
ranking, retry, OMS, vendor edits.

### Approach

ShippingCore contract → carrier record → Launchpad schema → outer plugin (visibility + fallback
coordinator) → city resolver/precedence → admin/CSV → tests → docs/evidence. Chi tiết §3 SPEC.

### Constraints

Dependency rules DEC-TASK5XQXZK-001 §1 (Launchpad −X→ carriers; ShippingCore −X→
Mageplaza/carriers); không vendor edit; policy-owned eligibility (không parse message);
member-not-participating không trigger; city stable code + không guess; PHP 8.2+;
vi/en strings; scope fence §8 directive.

### Rules

AC matrix §5 SPEC: capabilities 4 tổ hợp; eligibility matrix (TECHNICAL/CANONICAL_AMBIGUOUS
eligible, UNMAPPED/all-UNAVAILABLE no, SUCCESS suppress, non-participating no); visibility
independent của membership; city precedence 3-tier + SUM/MIN/MAX regression-proof + CSV
valid/invalid/round-trip; cascade FK; evidence + validator pass.
