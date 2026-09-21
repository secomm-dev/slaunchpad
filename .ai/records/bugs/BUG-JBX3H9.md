---
id: BUG-JBX3H9
type: bug
title: 'Remove unsafe GHN hardcoded destination fallback — unmapped destination phải fail closed (1456/21511/1457/21715/Phường 17 + is_develop_mode)'
project_code: SLP
parent: null
external_refs: {}
mode: C
specification_level: MINI
spec_status: VALID            # user-directed bug directive 2026-09-08 (audit + behavior + test matrix trong request); Mini-Spec embedded đủ 5 sections
specification_ref: null       # Mini-Spec embedded (Mode C — DEC-TASKZ132WA-002)
risk: medium                  # behavior change trên rate path (unmapped → method unavailable thay vì quote SAI); strictly safer
status: in_progress
priority: high
decisions: [DEC-FEATYA2C0W-004]   # P0-3 GHN dev-mode hardening đã ratified direction (Consequences); không DEC mới
decision_assessment: non-material
decision_approval_summary:
  total: 1
  pending_approval: []
  approved: [DEC-FEATYA2C0W-004]
  rejected: []
  superseded: []
  last_synced: '2026-09-08'
verified_against_commit: null
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/GhnAddressMapper/
changes_project_state: true       # xóa config field is_develop_mode
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: '2026-09-08'
supersedes: []
work_items: [BUG-JBX3H9, FEAT-YA2C0W]
related_tickets: [TASK-AQT7V3, SPIKE-W273TB]
---

# [SLP][BUG-JBX3H9] Remove unsafe GHN hardcoded destination fallback — unmapped destination phải fail closed (1456/21511/1457/21715/Phường 17 + is_develop_mode)

## Mini Spec

### Goal

Loại bỏ toàn bộ hành vi thay destination (và from-location develop-mode) mapping bằng GHN
district/ward ID cứng. `mapping unavailable → fail closed`, không bao giờ `→ fake district/ward`.
Bug độc lập, đứng TRƯỚC canonical migration (Phase E-C); KHÔNG đưa ShippingCore orchestration vào.

### Expected Behavior

1. `AbstractDataBuilder::resolveGhnLocation()` — mapping miss (không row / thiếu input /
   partial row district|ward rỗng) → throw `GhnLocationMappingException` (LocalizedException mới,
   1 class, không hierarchy) + log warning context (side origin|destination, region_id, city_id,
   city, mode id|name — không street/phone/PII).
2. **Rate path** (`ServicesDataBuilder` → get_services; `ShippingDetailsDataBuilder` →
   calculate_rate): exception bubble lên `GHN::estimateShippingCost()` (catch hiện có) → `null`
   → `collectRates()` trả `false` = **GHN method unavailable cho address đó**. Không fake quote,
   không stack trace cho customer.
3. **Order/shipment sync** (`SynchronizeOrderDataBuilder`): exception bubble qua
   `OrderSyncService::sync()` — MQ consumer log `[GHN OrderSync]` + re-throw (MQ retry);
   admin direct-sync → error message. Không gửi request sai lên GHN.
4. Toàn bộ develop-mode fake-data branches BỊ XÓA: `resolveGhnLocation` (1456/21511),
   `ServicesDataBuilder` (from 1457 / to 1456), `ShippingDetailsDataBuilder` (from 1457/21715
   override NGAY cả origin map được), `SynchronizeOrderDataBuilder` (from 'Phường 17'/
   'Quận Phú Nhuận'/'Hồ Chí Minh'). `is_develop_mode` không còn reader nào → xóa config field
   (config.xml default + system.xml) — reviewed: 0 legitimate use khác (grep toàn app/).
   Flag KHÔNG thể kích hoạt fake address dưới mọi giá trị.
5. Mapping hợp lệ hiện có hoạt động nguyên trạng (id-based `resolve(regionId, cityId)` chính,
   name-based `resolveByName` fallback giữ nguyên hành vi mapper — E-C mới migrate).

### Constraints / Rules

- KHÔNG refactor sang canonical scheme_code/unit_code; KHÔNG đụng `Secomm_ShippingCore`,
  GHTK, Ahamove, VietNamAddress; KHÔNG đổi `LocationResolver` contract/behavior.
- KHÔNG ShippingAddressResolutionManager / CarrierAddressCapability / ResolvedShippingAddress
  integration (Phase E-B/C riêng).
- Reuse failure paths hiện có (carrier catch-all, MQ consumer retry, admin messageManager) —
  không thêm exception hierarchy; 1 exception class mới duy nhất.
- Log: warning level, context identifier (region_id/city_id/city/side/mode) — không PII
  (street, telephone, receiver name).
- PHP 8.2+ strict_types; DI constructor (không ObjectManager); không đụng config khác.

### Out of Scope

GHN canonical mapping schema migration (E-C) · ShippingCore orchestration (E-B) ·
`resolveByName` first-match semantics (policy AMBIGUOUS — E-C/D theo DEC-004 D5/D9) ·
GHTK/Ahamove fallbacks (BUG/feature riêng) · origin config redesign (OD-1) ·
MQ retry policy / sync_mode redesign · xóa orphan `core_config_data` rows cũ (harmless).

### Acceptance Criteria

- **AC-1**: mapping hợp lệ (id + name) → district_id/ward_code thật được dùng, behavior giữ nguyên.
- **AC-2**: mapping miss (`NoSuchEntityException` từ resolver) → `GhnLocationMappingException`,
  KHÔNG 1456, KHÔNG 21511, bất kể giá trị config nào (test với mocked `is_develop_mode`=1 path
  trước khi xóa field — chứng minh flag không còn thể re-activate).
- **AC-3**: thiếu input (region_id=0 / không city) → fail closed (không fallback).
- **AC-4**: partial mapping (district=0 hoặc ward='') → fail closed.
- **AC-5**: grep cuối: `1456|21511|Phường 17|1457|21715|is_develop_mode` = 0 hit production code
  GiaoHangNhanh/GhnAddressMapper (test fixtures legitimate phải giải thích).
- **AC-6**: unit tests mới pass (mapping exists/missing/partial/flag-ignored + ServicesDataBuilder
  dùng mapping); Secomm suite không thêm failure mới (15 errors pre-existing).
- **AC-7**: validator + `setup:di:compile` pass; README/CHANGELOG GHN cập nhật; evidence ghi nhận.

## Approach

`resolveGhnLocation` (single choke-point của cả 3 builder + origin của 2 builder) là điểm fix
duy nhất cần throw; 3 develop-branches xóa trắng (không còn reader). Exception mới extends
`LocalizedException` (pattern `CommandException` sẵn có). Rate path đã có catch-all trong
`GHN::estimateShippingCost()` → không đổi carrier; sync path đã có consumer try/catch + retry →
không đổi queue. Logger inject vào `AbstractDataBuilder` constructor (2 child constructor override
cập nhật theo). Thứ tự: record → exception + builder fix + xóa branches + config → tests → grep
verify → validator/compile → docs/memory/evidence.

## Verification

- [x] AC-1 — valid mapping (id + name) dùng thật: `AbstractDataBuilderTest::testValidMappingByIdIsUsed` + `testValidMappingByNameIsUsedWhenNoCityId` + `ServicesDataBuilderTest::testBuildsFromConfiguredFromDistrictAndMappedToDistrict`.
- [x] AC-2 — mapping miss → `GhnLocationMappingException` kể cả với stale `is_develop_mode=1` (config value bị bỏ hoàn toàn; field đã xóa khỏi config.xml/system.xml): `testMissingMappingFailsClosedEvenWithLegacyDevelopModeValue`.
- [x] AC-3 — thiếu identifier → fail closed: `testMissingIdentifiersFailClosed`, `testMissingRegionWithOnlyCityNameFailsClosed`.
- [x] AC-4 — partial mapping (district=0 / ward='') → fail closed: `testPartialMappingWithoutDistrictFailsClosed`, `testPartialMappingWithoutWardFailsClosed`.
- [x] AC-5 — grep cuối: production code GiaoHangNhanh/GhnAddressMapper = **0 hit** cho `1456|21511|1457|21715|Phường 17|is_develop_mode`; còn 2 hit legitimate trong test files (string literal cố ý chứng minh stale flag bị ignore — comment ghi rõ) + 1 hit README ghi nhận fix trong Known-Issues table.
- [x] AC-6 — 9 tests mới pass; full Secomm suite 846 tests / 7 errors — cả 7 pre-existing `Secomm_Tracking\EventNormalizerTest` (mismatch constructor Magento core trong test cũ, verified tồn tại ở clean HEAD, module không đụng), 0 failure mới.
- [x] AC-7 — `setup:di:compile` pass; validator 0 finding trên BUG-JBX3H9; README Known-Issues #2 + CHANGELOG 1.2.0 cập nhật; evidence tại `.ai/evidence/BUG-JBX3H9/`.

Kết quả 2026-09-08 (pre-review evidence cho TL review; chưa mark done).
