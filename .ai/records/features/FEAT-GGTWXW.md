---
id: FEAT-GGTWXW
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'Mageplaza TableRate fallback composition — Magento-orchestrated carrier outcomes, per-method fallback groups + capabilities, city/address-unit dimension (Launchpad_MageplazaTableRate + Secomm_ShippingCore collector)'
mode: A                      # ShippingCore contract + DB schema + checkout behavior → Tier-2
specification_level: FULL
spec_status: VALID
specification_ref: ../../records/specs/SPEC-TASK-5XQXZK-mptablerate-fallback-composition.md
risk: medium
status: in_progress
created: 2026-09-15
updated: 2026-09-15
ticket_ref:
  - TASK-5XQXZK              # Implementation slice: collector + eligibility policy + carrier report + bridge composition (settings/membership/visibility/city/admin/CSV) — theo directive 2026-09-15
decisions:
  - DEC-TASK5XQXZK-001
decision_assessment: material
decision_refs: [DEC-TASK5XQXZK-001, DEC-FEATYA2C0W-004, DEC-FEATYA2C0W-005]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Launchpad/MageplazaTableRate/
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
  - app/code/Secomm/Ghtk/
  - app/code/Mageplaza/TableRateShipping/
changes_project_state: true
changes_architecture: true   # collector contract + safe-degradation policy + per-method fallback groups
---

# [SLP][FEAT-GGTWXW] Mageplaza TableRate fallback composition

Feature kế thừa nền TASK-NQT782 (bridge TASK-NQT782) sau audit 2026-09-15. Mageplaza TableRate
Method là **fallback grouping identity**: mỗi method có `show_to_customer`, `use_as_fallback`
và membership N realtime shipping methods `(carrier_code, method_code)`. Magento Shipping
Framework own carrier execution; carriers report normalized outcomes vào
`Secomm_ShippingCore` `CarrierRateOutcomeCollectorInterface`; `Launchpad_MageplazaTableRate`
trigger/append fallback ở outer lifecycle seam (`Magento\Shipping\Model\Shipping::collectRates`).
City/address-unit dimension bổ sung cho TableRate matching qua extension table keyed by stable
`city_code`. KHÔNG có carrier fan-out registry; KHÔNG có business service enum; KHÔNG dedup tự
động. Chi tiết: SPEC-TASK-5XQXZK + DEC-TASK5XQXZK-001.
