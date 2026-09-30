---
id: TASK-ZA10BT
type: task
project_code: SLP
parent: {type: feature, id: FEAT-QA23PZ}
legacy_ids: []
title: 'Admin Shipping Zones CRUD — menu/ACL/UiComponent grid + form với ward options theo province (AJAX) + canonical validation'
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: low
status: in_progress
created: 2026-09-21
updated: 2026-09-21
plan: ../../plans/TASK-ZA10BT-implementation-plan.md
decisions: []
decision_assessment: minor
decision_refs: []
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-QA23PZ][TASK-ZA10BT] Admin Shipping Zones CRUD — menu/ACL/UiComponent grid + form với ward options theo province (AJAX) + canonical validation

## Mini Spec

### Goal
Merchant quản lý CanonicalZone không cần code/DI change: grid + form CRUD theo pattern Secomm
hiện có (PancakeBridge WarehouseMap), dữ liệu canonical từ Secomm_VietNamAddress.

### Expected Behavior
- Menu `MenuSecomm_Base::menu → Secomm_ShippingCore::zones` ("Shipping Zones"); ACL
  `Secomm_ShippingCore::zones` (View) → `::zones_manage` (Create/Edit/Enable/Disable/Delete).
- Grid: Code, Label, Enabled, Province Count, Included Ward Count, Excluded Ward Count,
  Updated At (counts `JSON_LENGTH()`); actions Create/Edit/Delete + mass
  Enable/Disable/Delete; `<aclResource>` trên dataSource.
- Form: Code (read-only khi edit), Label, Enabled, Provinces multiselect (options level-1
  active scheme `VN_ADMIN_2025` qua `VnAddressUnitProviderInterface`), Included Wards +
  Excluded Wards multiselect — options constrained theo selected provinces qua AJAX
  `secomm_shippingcore/zone/wardOptions?provinces=…` (isAjax + ACL guard, precedent
  `Launchpad_MageplazaTableRate/Controller/Adminhtml/City/Options`).
- Save path chạy Validator (TASK-1EK2MW): invalid → error message đầy đủ, KHÔNG silent drop;
  unique code clash → error.
- Dependency UX carrier config (§15) thuộc system.xml GHN — implement cùng slice C fields,
  không phải slice này (form zones không phụ thuộc carrier).

### Constraints / Rules
- Pattern Secomm: `public const ADMIN_RESOURCE` (không `_isAllowed()` override); POST-only cho
  mutating controllers; UiComponent form qua dataSource (form_key tự động).
- KHÔNG visual zone builder/map UI; KHÔNG dùng TableRate City/Area tables.
- Admin options chỉ từ active scheme; label hiển thị `name_vi` (+ `name_en` fallback) —
  label chỉ display, identity = code.

### Out of Scope
Carrier system.xml fields + depends UX (slice C); import/export zone; approval workflow;
audit author tracking (§32 — chỉ created_at/updated_at).

### Acceptance Criteria
- Controller tests (pattern GHN CancelTest): POST-only save/delete, ACL const qua reflection,
  redirect paths, validation error mapping.
- Validator integration qua save path: create valid zone; duplicate code rejected; unknown
  province/ward rejected; edit + mass actions chạy qua repository (cache flush).
- Grid provider đọc collection có counts đúng (JSON_LENGTH).
