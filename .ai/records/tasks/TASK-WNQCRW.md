---
id: TASK-WNQCRW
type: task
title: Align GHN RATE Weight Classification and CREATE Physical Parcel Decision
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-10-01
updated: 2026-10-01
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-TASKWNQCRW-001]
decision_assessment: material
decision_refs: [DEC-TASKWNQCRW-001]
related_tickets: [TASK-ZS2B41, TASK-FXFMJ0, TASK-WAWNDS]
components: [CMP-GHN, CMP-SHIPPING]
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-10-01
supersedes: []
---

# [SLP][TASK-WNQCRW] Align GHN RATE Weight Classification and CREATE Physical Parcel Decision

## Summary

Brief TL 2026-10-01 (frozen operational rules): (1) RATE `service_type_id` phụ thuộc CHỈ total
quote weight (`<20000g → 2`, `>=20000g → 5`) — bỏ dependency `packageCount === 1` hiện có
(TASK-WAWNDS); (2) per-sellable-unit weight gate merchant-tunable `carriers/secomm_ghn/
max_package_weight_g` (GRAMS, default 50000g = TYPE_5_MAX_WEIGHT_G, user decision cùng ngày)
— unit > limit → ẩn GHN, không fee call, không fallback; (3) aggregate không cap khi mọi unit
≤ limit; (4) RATE ≠ CREATE truth — CREATE giữ nguyên derive từ PhysicalPackages, constraints
frozen. Đảo chiều một phần DEC-TASKFXFMJ0-001 ("no RATE-side weight cap" + "type rule giữ
nguyên" clause); fallback rule FXFMJ0 giữ nguyên.

## Mini Spec

### Goal

RATE type = total weight only; per-unit weight gate config-tunable default 50000g; giữ CREATE
truth độc lập và constraints frozen.

### Expected Behavior

- RATE: `total < 20000g → 2`; `total >= 20000g → 5` — bất kể số item/package/SKU/qty.
- RATE gate (trước mọi fee call): 1 unit > config → UNAVAILABLE
  `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED`, API count 0, fallback NONE. Strictly `>` (50000 biên
  vẫn quote). Aggregate 60/100/120kg (mọi unit ≤ limit) → type 5 → fee call ×1.
- CREATE: derive từ `ShipmentPhysicalData` → `PhysicalPackage(s)` độc lập (2 parcel light →
  type 5 bất kể RATE type 2); per-package 50kg cap + dimension limits (TASK-ZS2B41 rev.2)
  giữ nguyên.
- Weight check trước dimension check trong `findHardLimitViolation()` (weight luôn present).

### Constraints / Rules

- Config GRAMS, store scope, `validate-digits validate-greater-than-zero`; empty/≤0 → fallback
  `TYPE_5_MAX_WEIGHT_G`; CHANGELOG/comment provenance TÁCH 4 nguồn (§ brief 17): 20kg = fee
  contract; >50kg single = CREATE capability dùng làm checkout eligibility guard
  (merchant-tunable); aggregate acceptance = sandbox-observed; CREATE interpretation = Create
  contract + PhysicalPackage architecture.
- Không persist RATE service_type (`secomm_ghn_shipment.service_type_id` chỉ từ CREATE plan).
- Không đụng §18: address/PICK_PRIMARY/zones/origin/COD/tracking/TableRate/PhysicalPackage
  contracts. Legacy `carriers/secomm_ghn/max_package_weight` (không `_g`, Ghn.php
  validation-stage, inert) không đụng — docs interplay only.
- TYPE_2/TYPE_5 constants giữ contract; CREATE weight getter frozen (GhnPhysicalLimitTest pin).

### Out of Scope

- CREATE-side weight config; fallback eligibility cho weight reason (ShippingCore policy không
  đổi); legacy knob; data migration; commit/push.

### Acceptance Criteria

- AC-001: type flip — `<20kg` multi-package → 2 (2 test re-pin + §13 matrix).
- AC-002: item-count independence (§14): 1×15kg ≡ 3×5kg ≡ 10×1.5kg → 2; 1×30kg ≡ 3×10kg → 5.
- AC-003: weight gate — 50001 → UNAVAILABLE weight reason + API never + fallback none; 50000
  quote; aggregate 2×30kg / 2×50kg / 3×40kg → type 5 + API call; merchant nâng config → quote
  lại (explicit ctor tests giữ FXFMJ0 sandbox evidence).
- AC-004: config field default/fallback/override (ConfigTest +3) + admin field + i18n.
- AC-005: CREATE regression (§15) — RATE 15kg multi-item → 2 trong khi interpreter 2 physical
  light parcels → 5 items[]; CREATE 51000g vẫn reject (test hiện có).
- AC-006: sandbox matrix §6 đủ 11 cases + record (service_type/root weight/items/HTTP/code/
  message/fee).
- AC-007: suites green + compile + validator baseline 56 không tăng; guard grep (reason chỉ
  emit từ QuoteParcelEstimate; không còn "multi-parcel" wording RATE context).

## Approach

Audit trước (đã trace — plan file) → records → code (type flip + weight gate theo pattern
dimension rev.2) → tests (re-pin 2 flip + §13/§14/§15 matrix) → sandbox matrix (reuse script
FXFMJ0) → docs (§16 sweep) → verification + final report A-L.

## Implementation Notes

(2026-10-01) Audit trước khi sửa — kết quả chính:
- Flip = 1 ternary `QuoteParcelEstimate::getServiceTypeId()` (L88-94); consumer duy nhất
  `GhnRateCalculator::fetchFeeTotal()` L252; items[] guard theo type L264 → type 2
  multi-package payload-safe (root weight only, không items/dims).
- RATE transient (không persist/cache); `secomm_ghn_shipment.service_type_id` ghi từ CREATE
  plan (`GhnShipmentCreationService:314` → `GhnShipmentRepository:120`); CREATE interpreter
  (`GhnPhysicalParcelInterpreter:53-78`) độc lập.
- `RATE_REQUEST_UNREPRESENTABLE` đã RESERVED (no emitter) từ FXFMJ0 — giữ RESERVED.
- 2 test assertion flip: GhnRateCalculatorTest::testTwoParcelTenKgTotalStillSelectsTypeFive,
  QuoteParcelEstimateTest::testMultiParcelEstimatesAlwaysSelectTypeFive (lightMulti leg);
  comment GhnRateRequestMapperTest:126.
- Sandbox script reuse: `.ai/evidence/TASK-FXFMJ0/sandbox_weight_matrix.php` (shop 200537,
  dest 1846/291124; đổi L113 type derivation + thêm 2×5/2×10/2×50/3×40).

## Verification

- [x] AC-001 — type flip: testMultiItemLightCartSelectsTypeTwoWithoutItems (2×5kg → type 2,
      no items) + testMultiPackageTypeFollowsTotalWeightNotPackageCount
- [x] AC-002 — testItemCountDoesNotInfluenceServiceType (1×15kg ≡ 3×5kg ≡ 10×1.5kg → 2;
      1×30kg ≡ 3×10kg → 5)
- [x] AC-003 — 50001 → UNAVAILABLE weight reason API-never; 50000 quote; aggregate
      2×50kg=100kg + 70kg full flow; raised-limit 60kg quote (FXFMJ0 evidence giữ)
- [x] AC-004 — ConfigTest +3; admin field + i18n vi/en; runtime default 50000
- [x] AC-005 — testCreateDerivesIndependentlyFromTheRateClassification (RATE 15kg → 2,
      CREATE 2 light parcels → 5); interpreter 51000g reject giữ nguyên
- [x] AC-006 — sandbox matrix 13/13 HTTP 200 (evidence.md §B; case G type-2 multi-unit mới)
- [x] AC-007 — Ghn **484/484**, Ghn+ShippingCore **1084/1084** (0F/0E/0 skip); compile OK;
      validator baseline 56 không tăng; guard grep pass (reason 1 emitter; không stale wording)

Full report A-L: `.ai/evidence/TASK-WNQCRW/evidence.md`.

## Related records

- Decision: DEC-TASKWNQCRW-001
- Related: TASK-FXFMJ0 (superseded một phần — weight gate + type rule), TASK-WAWNDS (chủ cũ
  type rule), TASK-ZS2B41 (dimension config rev.2 — nền), TASK-MQ2DRG (historical pre-gate)
