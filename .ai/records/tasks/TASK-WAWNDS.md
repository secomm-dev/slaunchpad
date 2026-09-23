---
id: TASK-WAWNDS
type: task
title: 'GHN RATE Type-5 / Multi-parcel Estimation / Parcel Eligibility'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — GHN-specific RATE capability; 0 ShippingCore redesign
plan: ../../plans/TASK-WAWNDS-implementation-plan.md
risk: high                    # checkout pricing path (Tier-2); plan approval = signoff
status: done                  # 2026-09-18: dev-complete + FREEZE VERIFIED (Secomm_Ghn v10 = FROZEN, pending commit); ShippingCore v10 runtime orchestration (TASK-8MQHJX) consumed via RealtimeRateContributor
priority: high
decision_assessment: material
decisions: [DEC-TASK9Q5ZAK-001]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-18
updated: 2026-09-18
owner: [dev]
related_tickets: [TASK-FMBBSD, TASK-9Q5ZAK, TASK-PWHG0V]
---

# [SLP][FEAT-FQWEQ3][TASK-WAWNDS] GHN RATE Type-5 / Multi-parcel Estimation / Parcel Eligibility

**Prerequisites**: GHN-C closed · GHN-D closed (type-5 create sandbox-proven) · address-shipping v6.

## Progress Log

- **2026-09-18 — activated; contract re-verification COMPLETE (docs + sandbox).** Docs re-fetch
  2026-09-18 (calculate-fee / create / available-services — developer.ghn.vn). Sandbox probes
  A–I chạy 2026-09-18 (staging, sanitized): **type-5 fee BẮT BUỘC `items[]`** (root-only → 400
  "Cân nặng không hợp lệ"); **`quantity=2` ≠ 2 rows** (fee 616,000 vs 605,000) → serialize
  per-unit rows; **aggregate >50kg KHÔNG bị chặn ở fee** (2×35kg = 200); **fee KHÔNG enforce
  50kg/package hay 200cm** (60kg single + 210cm = 200, flat service 550,000 trên route probe);
  per-item dims OPTIONAL ở fee (item không dims = 200) — khác CREATE (docs: required type-5);
  root dims Ở FEED type-2 THAY ĐỔI GIÁ (70,400 → 185,900) → giữ policy OMIT dims toàn bộ RATE.
  Existing assumption "50kg/200cm = per-package hard limit" bị sandbox fee bác bỏ ở mức RATE —
  provenance ghi rõ trong plan/evidence; provider remains final authority.

## Embedded Mini-Spec

### Goal

Thay limitation `GHN_HEAVY_PARCEL_UNSUPPORTED` bằng Type-5 RATE thật: quote-time parcel
estimator (PRODUCT_UNIT_AS_PACKAGE) → classification type 2/5 → fee payload với `items[]` →
provider rate → GHN buffer (post-rate only) → CarrierRateOutcome. KHÔNG redesign ShippingCore
strategy/zones/fallback/address.

### Expected Behavior

1. `QuoteParcelEstimator`: RateRequest items → `QuoteParcelEstimate{totalWeightG, packages[]}`;
   `EstimatedPackage` = per-unit row (sourceItemId, sku, weightG, source, dims=null tại RATE).
   PRODUCT_UNIT_AS_PACKAGE: 1 sellable unit = 1 estimated package; serialize `items[]` per-unit
   (`quantity:1` mỗi row — sandbox-proven qty≠rows). Product-type rules: configurable → child
   simple facts (skip parent); grouped → child simples; virtual/downloadable → bỏ; bundle có
   children → `GHN_RATE_ESTIMATION_UNAVAILABLE` (không pack); parent/child double-count guard.
2. Weight: `StoreWeightConverter` (kg/lbs fail-closed) — không assume kg. Dims: KHÔNG đọc
   (giữ GHN-C policy — fee type-2 dims thay đổi giá; type-5 fee chấp nhận items không dims —
   sandbox-proven). Missing/invalid weight (≤0) trên physical item → fail-closed.
3. Type selection: `packages>1 OR totalWeight ≥ 20,000g → 5 else 2` (docs threshold = TOTAL
   weight). KHÔNG pre-reject 50kg/200cm tại RATE (sandbox: fee chấp nhận — provider authority);
   `GHN_PACKAGE_*_LIMIT_EXCEEDED` KHÔNG emit khi chưa có provider evidence của rejection.
   Hard limits chỉ áp CREATE (unchanged).
4. Fee payload type-5: root weight (Σ) + `items[]` per-unit {name, quantity:1, weight} (dims
   omit). Provider vẫn có thể reject route/service → existing taxonomy (BUSINESS/TECHNICAL).
5. Buffer config (GHN carrier-level): `rate_adjustment` {enabled(No), type fixed|percent,
   value, rounding none|1000|5000, apply_to carrier_rate_only|carrier_and_fallback} — áp SAU
   provider/fallback rate thành công, KHÔNG bao giờ ảnh hưởng eligibility; providerRate được
   log riêng.
6. KHÔNG persist estimate vào `sales_shipment.packages`/`secomm_physical` (CREATE truth tách
   bạch; estimate transient). Log diagnostics: strategy, service_type_id, package count, total
   weight, failure reason — 0 PII.

### Constraints / Rules

- 0 ShippingCore edit; 0 CREATE behavior change; 0 schema change; 0 cartonization/splitting.
- Type-2 không bị stricter (dims vẫn omit; weight-only path giữ nguyên).
- Đơn vị weight fail-closed; không assume kg; không assume dims unit.
- Fallback boundary (integration note cho ShippingCore stream): hard GHN capability rejection
  KHÔNG được mask bằng fallback rate branded `secomm_ghn`.
- 0 token/PII trong log/evidence; provider remains final authority sau local validation.

### Out of Scope

Cartonization/bin-packing/warehouse optimizer · ShippingCore zones/fallback orchestration ·
address resolution changes · CREATE architecture · label flow · legacy cutover · volumetric
pricing client-side · Available-Services runtime dependency (decision: fee rejection đủ —
docs service_id reference-only; record decision).

### Acceptance Criteria

- AC-1: contract matrix OFFICIAL_DOCUMENTED / SANDBOX_OBSERVED / EXISTING_ASSUMPTION tách bạch
  với provenance từng limit (root vs package vs aggregate).
- AC-2: sandbox probes A–I evidence (sanitized) — done 2026-09-18.
- AC-3: Type-5 heavy quote thật nhận fee thật (sandbox: multi-parcel + >50kg aggregate).
- AC-4: Type-2 không regress (weight-only path + tests).
- AC-5: decision matrix §32 covered bởi tests (estimator + calculator + serializer).
- AC-6: buffer post-rate only, config-driven, default off; providerRate/finalRate quan sát
  được trong log; buffer không cứu parcel invalid.
- AC-7: estimator không persist; không double-count parent/child; virtual/grouped/configurable/
  bundle đúng matrix §E; decimal qty fail-safe.
- AC-8: regression Ghn/ShippingCore/VietNamAddress/Ghtk 0F/0E; compile; validator; sandbox
  evidence sanitized.

- **2026-09-18 — PAUSED bởi TL (ShippingCore v10 supersedes RATE orchestration assumptions).**
  Trạng thái bàn giao — mọi thứ UNCOMMITTED, không gates đã chạy sau điểm dừng:
  - **PRESERVED (research/evidence, purely GHN-specific Type-5):** docs re-verify 2026-09-18
    (calculate-fee / create / available-services); sandbox probes A–I (items[] BẮT BUỘC type-5,
    quantity=N ≠ N rows, aggregate >50kg KHÔNG bị fee chặn, fee KHÔNG enforce 50kg/200cm,
    per-item dims optional, root dims type-2 THAY ĐỔI GIÁ); evidence matrix trong plan;
    GHN-specific draft VOs + estimator (`QuoteParcelEstimate`/`EstimatedPackage`/
    `QuoteParcelEstimator`/`GhnRateEstimationException`) + wiring (GhnRateQuery/mapper/calculator
    items-payload + carrier estimation-exception catch) — unit-green tại thời điểm dừng
    (Ghn 333/0F/0E + adjuster 5/0F/0E chạy riêng), CHƯA cross-module/compile-final.
  - **FLAG cho TL review lại theo v10:** buffer draft (`GhnRateAdjuster` + system.xml
    `rate_adjustment` + carrier post-rate hook) — không thuộc Type-5 cốt lõi, `apply_to`
    carrier_and_fallback chạm biên fallback policy → quyết định giữ/sửa/bỏ trong prompt v10.
  - **KHÔNG làm (v10 territory):** GHN-local RateSourceMode, PICK_PRIMARY/candidate selection,
    carrier eligibility/zones, fallback orchestration, generic fallback policy.
  - **KHÔNG commit** bất kỳ phần nào. Task chờ prompt v10-aligned từ TL.

- **2026-09-18 — v10-realigned continuation (TL prompt), dev-draft complete, vẫn PAUSED/UNCOMMITTED.**
  Realignment: audit §32 = NOT PRESENT (0 GHN-local v10-generic code; grep-verified); FALLBACK_ONLY
  misuse-guard = documented chờ v10 contract; v10 input-boundary dependency documented
  (selected PRE destination → thay handoff call khi frozen); buffer draft giữ + FLAG v10.
  Type-5 completion: `GhnPackageLimits` (150cm SANDBOX-OBSERVED — conflict docs 200, sandbox
  thắng; weight UNSETTLED → không pre-reject) + `findHardLimitViolation()` pre-gate trước
  handoff/fee + EstimatedPackage optional trusted-dims (null = unknown; missing dims ≠ carrier
  rejection — probe I3) + calculator/adjuster tests. Gates: Ghn **342/0F/0E**; compile GREEN;
  validator 0 fail WAWNDS; cross-module 1 failure EXTERNAL (VietNamAddress VnMappingReaderTest
  — concurrent stream 11:35, §38 documented). Evidence `.ai/evidence/TASK-WAWNDS/type5-rate.md`
  (matrix + probes + v10 dependency + gates). Chờ v10 frozen contracts để wire integration.

- **2026-09-18 — v10 ADAPTATION (TL prompt, RE-FROZEN contracts có code) — dev-complete, chờ TL.**
  (1) **Compile blocker §1**: system.xml inline `<option value="">` attribute — system.xsd cấm
  (value = text content chuẩn `<option label="…">value</option>`); 7 chỗ cũ + 6 chỗ mới (v10
  fields) chuẩn hóa → SCHEMA VALID; compile GREEN. (2) **v10 contracts consume** (frozen code):
  `RateSourceMode` + `AddressResolutionPolicy` (ShippingCore Api) — GHN config accessors
  (whitelist fail-closed, defaults CARRIER_WITH_FALLBACK/FALLBACK) + system.xml fields
  (sortOrder 145/146, policy comment n/a cho FALLBACK_ONLY); carrier ENTRY guard
  FALLBACK_ONLY → `GHN_RATE_SKIPPED_FALLBACK_ONLY` + hide (thin adapter read duy nhất — không
  re-evaluate trong provider logic); calculator pass policy verbatim vào
  `handoffContextForOperation(..., policy)` — selection/ranking 100% shared (§7: GHN chỉ nhận
  selected unit_code → Stage-2 mapping). (3) **LegacyRateStrategy migration §5**: GHN 0 refs
  (NOT PRESENT) — không có saved-config cần migrate; không song song 2 strategy systems.
  (4) **Audit §2**: path documented (collectRates → guards → estimator → handoff(policy) →
  mappingResolver → fee → outcome → collector); origin = config `origin_district_id` đơn
  (deterministic P1 ✓); outcome/fallback classification khớp policy map hiện có
  (SERVICE_UNAVAILABLE = not-eligible, PROVIDER_MAPPING_MISSING = eligible, TECHNICAL =
  eligible). (5) Tests +8: FALLBACK_ONLY short-circuit (API/mapping/calculate never),
  CARRIER_ONLY pipeline chạy, policy pass-through (PICK_PRIMARY verbatim), candidates-leak
  boundary (mapping resolver chỉ nhận selected unit). Gates: Ghn **346/0F/0E**; cross-module
  **1039/0F/0E** (external failure stream khác đã tự fix); compile GREEN; validator 0 WAWNDS;
  §28 invariants grep sạch. Freeze criteria §29: 1-13 self-audit PASS — chờ TL confirm.

- **2026-09-18 — FINAL ORCHESTRATION DELTA (TL prompt — v10 RE-FROZEN có code).**
  **§2 audit (code-truth, toàn app/code)**: `RateSourceMode` consumer = 0 ShippingCore orchestration
  class (chỉ Api definition + GHN adapter read); `CarrierEligibility`/`DestinationScope`/zones =
  **KHÔNG tồn tại trong code**; `AddressResolutionPolicy` consumer = `CarrierAddressHandoffService`
  — nhưng **wiring gap nhỏ trong frozen impl**: `handoffForOperation` forward policy xuống
  `handoffContextForOperation` (3-param) → param bị PHP drop; `VnPrimaryCandidateSelector` đã
  inject (ctor) nhưng 0 usage → policy chưa có effect runtime ở BẤT KỲ entry nào.
  **Wiring correction (§19-allowed)**: `handoffContextForOperation` (Interface + Model) +=
  optional `string $addressResolutionPolicy = FALLBACK`; `AddressResolutionPolicy::assertKnown`
  fail-fast; policy áp cho AMBIGUOUS: STRICT → unresolved + candidates[] + textual=false;
  PICK_PRIMARY → shared selector `selectPrimary(sourceScheme, sourceCode, target, candidates)`
  → SELECTED → resolved MAPPED selected-unit (candidates[], textual=false — carriers không thấy
  candidates); NO_DESIGNATED/MULTIPLE/NOT_APPLICABLE → fail-closed unresolved (VO guard:
  reason = CANONICAL_UNRESOLVED — frozen CarrierAddressHandoff invariant); FALLBACK/default =
  pre-v10 shape BC-lock test. **GHN consume**: calculator pass config policy verbatim (đã có);
  FALLBACK_ONLY entry guard GIỮ như defensive misuse-protection (§5 allowance — upstream
  invocation chưa guaranteed). **GATES**: Ghn 346/0F/0E; ShippingCore 278/0F/0E (+4 policy
  wiring); cross-module **1043/0F/0E**; compile GREEN; validator 0 WAWNDS.
  **V10 CONTRACT GAPS báo TL (§2/§19 — KHÔNG tự tạo)**: (1) `CarrierEligibility`/
  `DestinationScope` contracts + orchestrator execution-before-carrier chưa tồn tại trong code;
  (2) RateSourceMode upstream orchestrator consumer chưa có — GHN entry guard hiện là lớp
  phòng thủ duy nhất. Freeze: theo DoD #1/#3/#4 → **NOT FROZEN** chờ 2 contract trên; mọi
  điều kiện còn lại (2,5-14) PASS.

- **2026-09-18 — FREEZE VERIFICATION (TL prompt — ShippingCore v10 runtime orchestration COMPLETE, TASK-8MQHJX CLOSED).**
  **§2 code-truth**: v10 runtime = `CarrierRateExecutionService` (hard-ordered: eligibility →
  mode → origin-readiness → AddressResolutionPolicy qua shared handoff → realtime contributor →
  eligibility emission) + `CarrierEligibilityEvaluator`/`CanonicalZoneMatcher|Registry`/
  `DestinationScope` + `ServiceLevelRateOrchestrator::decide` (3 fallback sources: TECHNICAL/
  LEGACY_ADDRESS/INTEGRATION_LIMITATION; 0 reason parsing). **GHN consume**: mới implement
  `RealtimeRateContributor` (+Factory) = GHN's `RealtimeCarrierRateContributorInterface` —
  input = FINAL carrier-facing handoff (gated) + đóng RateRequest hiện hành; output =
  CarrierRateOutcome; **KHÔNG có handoff-service/context-builder trong contributor deps**
  (structural proof §14 — Stage-1 không lặp). Calculator split: `calculate()` (compat/standalone,
  giữ handoff+policy) vs `quoteWithHandoff()` (Stage-2+fee cho contributor; hard-limit pre-gate
  cả hai đường). GHN FALLBACK_ONLY guard = **DEFENSIVE_ONLY** (normal runtime: execution service
  chặn tại step 2 — unit-locked ShippingCore tests; guard không bao giờ là runtime owner).
  Mapping-missing → UNAVAILABLE+PROVIDER_MAPPING_MISSING pass-through verbatim; execution service
  map → INTEGRATION_LIMITATION upstream (code-locked). Provider-auth warning seam = DEFERRED gap
  (§23 — không blocking, INVALID_CONFIGURATION fail-closed đúng frozen). Gates: Ghn **353/0F/0E**
  (+7 contributor), ShippingCore 278/0F/0E, cross-module **1131/0F/0E**, compile GREEN,
  validator 0 WAWNDS, §20 invariants sạch. **Runtime smoke GREEN**: GraphQL ALL + compat path →
  `secomm_ghn 53,900 VND` thật (calculate_fee HTTP 200) sau split; apache restart cần lúc probe.
  **FREEZE: Secomm_Ghn v10 = FROZEN** (pending commit; execution-service runtime wiring vào
  Magento checkout composition thuộc Launchpad stream — GHN contributor sẵn sàng consume).

- **2026-09-18 — correctness fix (pre-review reopened freeze), 2 fixes + tests.**
  (1) **Contributor exception boundary đóng**: `RealtimeRateContributor::contribute` giờ
  outcome-based hoàn toàn — `GhnMappingNotFoundException` → UNAVAILABLE+PROVIDER_MAPPING_MISSING;
  technical group (`Timeout|Remote|ServiceUnavailable` — client classify 429/5xx cùng nhóm) →
  TECHNICAL_FAILURE+TECHNICAL_ERROR; business group (`Auth|InvalidAddress|InvalidRequest|
  RateUnavailable`) → UNAVAILABLE+SERVICE_UNAVAILABLE; `LocalizedException` mapper-path
  (weight-unit) → UNAVAILABLE+INVALID_CONFIGURATION fail-closed; `GhnRateEstimationException`
  giữ nguyên reason. KHÔNG catch Throwable (TypeError vẫn fail-loud). Root cause leak: import
  `LocalizedException` thiếu trong contributor + duplicate catch — cả hai đã dọn.
  (2) **Config defaults**: etc/config.xml `rate_source_mode=CARRIER_WITH_FALLBACK`,
  `address_resolution_policy=FALLBACK`, `rate_adjustment_enabled=0`, `_type=fixed`,
  `_rounding=none`, `_apply_to=carrier_rate_only` — khớp 100% accessor fallbacks.
  Tests: contributor timeout test đổi từ "pass-through exception" (sai) sang outcome
  TECHNICAL_FAILURE + data-provider 7 families + programming-defect-propagates +
  localized-mapper-path fail-closed. Gates: Ghn **360/0F/0E**; ShippingCore 359/0F/0E;
  cross-module **1138/0F/0E**; compile GREEN; validator 0 WAWNDS; §22 greps sạch
  (Throwable còn ở GhnApiClient transport + Manifest dataset loader — pre-existing, đúng phạm vi).
