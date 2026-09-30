---
id: TASK-BYT2WK
type: task
project_code: SLP
parent: {type: feature, id: FEAT-QA23PZ}
legacy_ids: []
title: 'GHN production wiring qua CarrierRateExecutionService + carrier DestinationScope/AllowedZoneCodes admin config + FallbackCoordinator zone-miss guard + runtime proof'
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: medium
status: in_progress
created: 2026-09-21
updated: 2026-09-21
plan: ../../plans/TASK-BYT2WK-implementation-plan.md
decisions:
  - DEC-FEATQA23PZ-001
decision_assessment: material
decision_refs: [DEC-FEATQA23PZ-001]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
  - app/code/Launchpad/MageplazaTableRate/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-QA23PZ][TASK-BYT2WK] GHN production wiring qua CarrierRateExecutionService + carrier DestinationScope/AllowedZoneCodes admin config + FallbackCoordinator zone-miss guard + runtime proof

## Mini Spec

### Goal
Audit 2026-09-21: production GHN RATE bypass `CarrierRateExecutionService` (0 invocation).
Slice này đóng composition gap nhỏ nhất: route `Ghn::collect()` qua execution service
(contributor seam TASK-WAWNDS chuyển dead → LIVE), thêm admin config scope/zones cho GHN,
và lock fallback boundary §20.

### Expected Behavior
- `Ghn::collect()`: giữ active/VN/VND gates + catch-all + hide/buildResult/adjust; thay mode
  gate + `calculate()` call bằng: build `CarrierRateExecutionRequest` (scope/zones từ
  `GhnConfig::getDestinationScope()/getAllowedZoneCodes()`; canonical province/ward scalars từ
  `VnOperationalAddressResolverInterface::resolveFromRuntime` — unresolved → ''/null
  fail-closed; resolutionContext từ `RuntimeAddressContextBuilder`; shippingContext từ
  `ShippingContextFactory::fromRateRequest`; contributor từ `RealtimeRateContributorFactory`)
  → `execute()` → post-decision: ineligible → record
  `unavailable(DESTINATION_NOT_IN_SCOPE)` + warning + hide; FALLBACK_ONLY eligible → record
  `unavailable(REASON_RATE_SKIPPED_FALLBACK_ONLY)` + hide; realtime → record decision outcome
  → adjust/buildResult hoặc hide.
- Config fields `carriers/secomm_ghn/destination_scope` (select ALL default | SELECTED_ZONES)
  + `carriers/secomm_ghn/allowed_zone_codes` (multiselect enabled zones, source model đọc
  repository); `<depends>`: ALL → ẩn Allowed Zones; SELECTED_ZONES → visible + required ≥1
  (backend_model validate server-side).
- `FallbackCoordinator::isMemberEligible()`: guard `DESTINATION_NOT_IN_SCOPE → false` TRƯỚC
  mode branches (§20 — kể cả FALLBACK_ONLY member không được mở fallback trên zone miss).
- Behavior giữ nguyên khi scope = ALL (default): realtime rate path qua contributor ≡ kết quả
  `calculate()` hiện tại (cùng `quoteWithHandoff` tail, hard-limit gate duplicated sẵn);
  origin gate pass do Magento default `shipping/origin/country_id=US`.

### Constraints / Rules
- KHÔNG đổi: GhnRateCalculator internals (quoteWithHandoff/mapping/fee), Type-5 estimator,
  CREATE/mapping/buffer, GhnRateAdjuster, exception taxonomy, method identity; contributor
  không thấy zone internals (chỉ final handoff).
- `GhnRateCalculator::calculate()/resolveAndQuote()` giữ nguyên code+test (standalone path,
  docblock note — không xoá).
- GHTK: KHÔNG thêm config field, KHÔNG đổi provider logic (regression-only).
- Coordinator guard đọc ShippingCore-owned constant — không message parsing.

### Out of Scope
GHTK adoption; zone-specific pricing; DI zone seeding; scheduler warning cho dangling refs
(runtime reader warning đủ P1).

### Acceptance Criteria
- GHN integration tests (real execution service + evaluator + registry fake + mock API client
  đếm call): ALL + 0 zones → API ≥1 call, method hiển thị; SELECTED_ZONES match → API call;
  SELECTED_ZONES miss → 0 contributor + 0 API + collector có
  `unavailable(DESTINATION_NOT_IN_SCOPE)` + method ẩn; FALLBACK_ONLY eligible → skip outcome +
  coordinator cho fallback; FALLBACK_ONLY + miss → coordinator TỪ CHỐI fallback (guard test).
- Coordinator unit tests: guard trước 3 mode branches; case §28 matrix đầy đủ.
- Regression: Secomm_ShippingCore + Secomm_Ghn + Secomm_Ghtk + Launchpad_MageplazaTableRate
  suites 0 fail mới.
