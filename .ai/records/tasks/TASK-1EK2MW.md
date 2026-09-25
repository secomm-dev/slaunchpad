---
id: TASK-1EK2MW
type: task
project_code: SLP
parent: {type: feature, id: FEAT-QA23PZ}
legacy_ids: []
title: 'ShippingCore zone persistence — secomm_shipping_zone + repository + persistent registry (persisted-wins precedence) + shared carrier scope config reader + DESTINATION_NOT_IN_SCOPE reason'
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: medium
status: in_progress
created: 2026-09-21
updated: 2026-09-21
plan: ../../plans/TASK-1EK2MW-implementation-plan.md
decisions:
  - DEC-FEATQA23PZ-001
decision_assessment: material
decision_refs: [DEC-FEATQA23PZ-001]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-QA23PZ][TASK-1EK2MW] ShippingCore zone persistence — secomm_shipping_zone + repository + persistent registry (persisted-wins precedence) + shared carrier scope config reader + DESTINATION_NOT_IN_SCOPE reason

## Mini Spec

### Goal
Zone CanonicalZone hiện chỉ tồn tại qua DI array rỗng — không thể quản lý không cần code change.
Slice này thêm persistence layer + repository + registry nguồn kép (persisted ↔ DI) + shared
carrier config reader, nền cho admin CRUD (TASK-ZA10BT) và wiring GHN (TASK-BYT2WK).

### Expected Behavior
- Table `secomm_shipping_zone` (schema §3.1 SPEC) với JSON code lists; whitelist đầy đủ.
- `CanonicalZoneRepositoryInterface`: getByCode (VO domain), getEnabledByCodes, save/delete,
  collection cho admin; save/delete flush cache type `secomm_shippingcore_zones`.
- `PersistentCanonicalZoneRegistry` là DI preference cho `CanonicalZoneRegistryInterface`:
  persisted authoritative cho code trùng, DI zone chỉ fallback khi code chưa tồn tại trong DB;
  lazy-load (không query lúc construction); duplicate trong từng nguồn vẫn fail-fast;
  zero zone cả hai nguồn = valid.
- Shared reader `Api/Config/CarrierDestinationScopeConfigInterface` đọc
  `carriers/<code>/destination_scope|allowed_zone_codes`: rỗng/missing → ALL / [];
  scope persist sai enum → ALL + warning; SELECTED_ZONES + unknown/disabled zone code →
  vẫn trả list + `logger.warning` per code (diagnostic §16/§22); evaluator/matcher KHÔNG đổi.
- `ShippingFailureReason::DESTINATION_NOT_IN_SCOPE` additive constant (owner duy nhất,
  KHÔNG thêm vào SafeDegradationEligibilityPolicy defaults).
- Validator `Model/Zone/Validator` theo §4 SPEC (unique/normalized code; province/ward tồn tại
  active scheme; include-ward thuộc included province khi có province constraint; exclude chỉ
  cần tồn tại).

### Constraints / Rules
- `secomm_shipping_zone` KHÔNG có cột provider-specific; chỉ canonical `VN-XX`/`VNA25-*`.
- KHÔNG query DB trong DI construction (lazy-load registry).
- Cache chỉ invalidate qua repository mutations (create/update/enable-disable/delete).
- ShippingCore không thêm carrier-specific conditional; evaluator/matcher giữ nguyên API.
- KHÔNG seed zone mặc định (§12); KHÔNG auto-persist DI zone (§33).

### Out of Scope
Admin UI (TASK-ZA10BT); GHN wiring (TASK-BYT2WK); scheme-swap auto-migration zone data;
indexer/subsystem.

### Acceptance Criteria
- Unit: registry precedence (persisted-wins / DI fallback / duplicate fail-fast / zero-state);
  repository cache invalidate 4 sự kiện; validator reject (unknown province/ward, code trùng,
  include-ward cross-province) + accept hợp lệ; reader parse + diagnostics + fail-closed ALL.
- `setup:upgrade` tạo table + `setup:di:compile` green; validator records green.
