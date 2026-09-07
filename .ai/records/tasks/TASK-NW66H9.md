---
id: TASK-NW66H9
type: task
title: 'Address Profile + Schema XML config, DTOs, Resolver/SchemaProvider contracts'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: completed
created: 2026-08-25
updated: 2026-08-26
decisions: [DEC-FEAT2PZQKJ-001]
decision_assessment: material
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/etc/
  - app/code/Secomm/AddressDropdown/Api/
  - app/code/Secomm/AddressDropdown/Model/
changes_project_state: true
changes_architecture: true
changes_integration: true
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-NW66H9] Address Profile + Schema XML config, DTOs, Resolver/SchemaProvider contracts

<!-- CANONICAL TASK RECORD — Phase 1. API contract mới (generic risk category) → Mode A. -->
<!-- TL code review approved 2026-08-26 (batch FEAT-2PZQKJ/YA2C0W; AI pre-review: PRE-REVIEW-2026-08-26-FEAT-2PZQKJ-YA2C0W.md PASS). → completed. -->

## Summary

Định nghĩa Address Profile/Schema dưới dạng XML config merge được (`etc/address_profiles.xml`) + DTO + 2 service contracts đầu tiên: `AddressProfileResolverInterface`, `AddressSchemaProviderInterface` (di.xml preferences). Generic module chỉ ship profile `default` fallback (region + city depth 1); country modules tự đăng ký profile của mình.

## Mini Spec

### Goal
Profile định nghĩa cách render/traverse hierarchy theo country/context: levels (`entity_type` ∈ {region, city}, `depth`, `label`, `placeholder`, `sort_order`, `required`). Label là translate-key — KHÔNG BAO GIỜ suy label từ depth.

### Expected Behavior
1. `etc/address_profiles.xml` schema (XSD) + reader merge nhiều module: `<profile code country label><levels><level entity_type depth label placeholder sort_order required translate/>`.
2. DTO: `AddressProfileInterface` (code, countryId, label), `SchemaLevelInterface` (entityType, depth, label, placeholder, sortOrder, required).
3. `AddressProfileResolverInterface::resolve(countryId, context = null)`: đọc config `address/profiles/mapping` (country → profile_code, default NULL = native Magento behavior); context override là config phụ nếu SA/TL chốt (D4).
4. `AddressSchemaProviderInterface::getSchema(profileCode): SchemaLevelInterface[]` sorted by sort_order.
5. Profile không tồn tại / country không map → fallback native (không throw trên storefront path).
6. Unit tests: merge, sort, fallback, translate-key passthrough.

### Constraints / Rules
- Không rules engine — chỉ map country→profile (+ optional per-context override).
- Không chứa country-specific label/terminology trong generic module (DEC-FEATJSZQV3-003).
- No direct ObjectManager; strict_types; PHP 8.2+.

### Out of Scope
- Membership query (TASK-J49PRZ); renderer (TASK-3T3NSV); `vn_current` profile (TASK-4F1K3N — sống trong VietNamAddress).

### Acceptance Criteria
- AC-001: 2 module khai báo profile cùng country → merge theo module load order không mất level.
- AC-002: resolve() đúng theo config scope store; unmapped country trả native-fallback marker.
- AC-003: getSchema() trả levels sorted; label là key chưa dịch (dịch tại render qua `__()` của module khai báo).
- AC-004: Unit tests xanh (happy + merge + fallback).

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 1, Step 2.

## Implementation Notes

Đã triển khai 2026-08-25 (chờ TL code review):

- **Config surface**: `etc/address_profiles.xsd` + `_file.xsd` (merged + per-file validation) + `etc/address_profiles.xml` của module khai báo duy nhất profile `default` (region + city depth 1, labels Magento-default theo DEC-FEATJSZQV3-003). Merge bằng `Magento\Framework\Config\Reader\Filesystem` virtualType theo `<profile code>`; cacheId `secomm_addressdropdown_address_profiles`.
- **API**: `Api\Data\AddressProfileInterface` + `SchemaLevelInterface` (DTO `Model\Data\*` theo pattern DataObject của module), `Api\AddressProfileResolverInterface` (4 CONTEXT_* constants, `context` reserved — mọi context resolve như nhau ở giai đoạn này), `Api\AddressSchemaProviderInterface`, `Api\NoSuchProfileException extends NoSuchEntityException`.
- **Impl**: `Model\Profile\Config\{SchemaLocator,Converter}` (converter fail-loud: thiếu code / duplicate slot (entity_type,depth) / city depth < 1 / profile rỗng) · `Model\Profile\ProfilePool` (memoized, DTO hydration) · `Model\AddressProfileResolver` (config `address/profiles/mapping` store-scoped serialized; unmapped → null; mapped-but-undeclared → warning + null; **corrupted serialized → catch InvalidArgumentException → warning + null** — phát hiện qua unit test: `Serialize::unserialize` throw thay vì trả false) · `Model\AddressSchemaProvider` (usort stable PHP 8).
- **di.xml**: 4 preferences + virtualType Reader/Data + Pool argument. **system.xml**: group `address/profiles` field `mapping` (textarea + `ArraySerialized` backend). **i18n**: 2 label en_US.csv (vi_VN.csv để trống theo DEC-8 boundary).

## Verification

- [x] AC-001: merge theo module load order — reader Filesystem standard; duplicate slot bị chặn loud ở Converter (ConverterTest::testDuplicateEntityTypeDepthSlotThrows) — evidence 07
- [x] AC-002: resolve() theo store scope; unmapped country → null (ResolverTest + smoke #2/#4) — evidence 06/07
- [x] AC-003: getSchema() sorted by sort_order, stable trên tie (SchemaProviderTest ×2) — evidence 07
- [x] AC-004: 22 tests / 47 assertions xanh (20 mới + 2 cũ) — evidence 07
- Smoke end-to-end qua Magento bootstrap: XSD validate + merge + converter + pool + DI + config mapping thật (save → resolve → cleanup) — evidence 06
- Evidence: `.ai/runtime/evidence/TASK-NW66H9/`

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted — đặc biệt D4 profile XML vs DB)
