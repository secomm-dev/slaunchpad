---
id: FEAT-QA23PZ
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'ShippingCore Canonical Zone Admin & Carrier Assignment — persistent CanonicalZone registry + carrier DestinationScope/AllowedZoneCodes config + GHN production wiring qua CarrierRateExecutionService'
mode: A                      # DB schema + shipping runtime + shared contract constant → Tier-2
specification_level: FULL
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: medium
status: in_progress
created: 2026-09-21
updated: 2026-09-21
ticket_ref:
  - TASK-1EK2MW              # Slice A: zone persistence + repository + persistent registry + shared config reader + shared reason constant
  - TASK-ZA10BT              # Slice B: Admin Zone CRUD (menu/ACL/grid/form/ward options/validation)
  - TASK-BYT2WK              # Slice C: GHN production wiring qua CarrierRateExecutionService + coordinator guard + runtime proof
decisions:
  - DEC-FEATQA23PZ-001
decision_assessment: material
decision_refs: [DEC-FEATQA23PZ-001, DEC-FEATYA2C0W-006, DEC-TASKWY6WP5-001]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
  - app/code/Launchpad/MageplazaTableRate/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: false  # operational completion của v10 §35 — KHÔNG tạo v11
---

# [SLP][FEAT-QA23PZ] ShippingCore Canonical Zone Admin & Carrier Assignment — persistent CanonicalZone registry + carrier DestinationScope/AllowedZoneCodes config + GHN production wiring qua CarrierRateExecutionService

Biến canonical carrier eligibility (v10 §35, TASK-8MQHJX — hiện **TEST_ONLY**) thành
merchant-configurable: zone persistence (`secomm_shipping_zone`) + admin CRUD + carrier config
`carriers/<code>/destination_scope|allowed_zone_codes` + **wiring production GHN carrier entry
qua `CarrierRateExecutionService`** (audit 2026-09-21: 0 production invocation — GHN bypass
bằng entry gates riêng). Fallback boundary §20 lock qua guard `DESTINATION_NOT_IN_SCOPE` trong
`FallbackCoordinator`. KHÔNG redesign provider internals; GHTK regression-only. Chi tiết:
SPEC-FEAT-QA23PZ.
