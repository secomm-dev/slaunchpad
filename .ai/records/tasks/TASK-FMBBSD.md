---
id: TASK-FMBBSD
type: task
title: 'Phase GHN-C — Rate + available-services + leadtime qua ShippingCore handoff + GHN legacy IDs (không magic fallback)'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec (slice reference; đặc biệt §12..§15, §43)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
plan: ../../plans/TASK-FMBBSD-implementation-plan.md   # slice 2 (Magento Carrier wiring); slice 1 chạy thiếu plan artifact — REPORT TL
risk: high                    # checkout rate path + external dependency block
status: in_progress           # activated 2026-09-11: architecture v3 audit + contract matrix + sandbox validation done; RATE slice 1 (provider calculator) dev-complete — Magento Carrier wiring (checkout) là slice kế, Tier-2
priority: medium
decision_assessment: material   # cần ShippingCore rate-provider contract slice (TL review riêng) — KHÔNG code thay trong Secomm_Ghn
decisions: [DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-11
owner: [dev]
related_tickets: [TASK-MZ2TCB, TASK-5XDG1P]
---

# [SLP][FEAT-FQWEQ3][TASK-FMBBSD] Phase GHN-C — Rate + available-services + leadtime qua ShippingCore handoff + GHN legacy IDs (không magic fallback)

**BLOCKED** tới khi: (1) GHN-B done; (2) E-B v2 operational (ShippingCore invoke
`ExternalAddressResolverPool`, fail closed); (3) VietMap PoC đạt precision ngưỡng; (4) NO_MATCH
authoring xong data part; (5) coverage `FULL_PRE_2025_ADDRESS_RESOLVED` đạt ngưỡng TL chốt
(đề xuất ≥95% trước production enable); (6) ShippingCore slice "carrier rate provider contract"
được TL approve ( ShippingCore đang hard-stop generic additions).

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §12 Rate, §13 No Magic Fallback, §14 Service Resolution, §15 Leadtime, §43 AC-RATE)*

### Goal

Checkout rate GHN realtime: destination (VN_ADMIN_2025) → ShippingCore handoff (capability
PRE_2025) → `GhnMappingResolver` → legacy triple → available-services + Calculate Fee + Leadtime →
`CarrierRateOutcome` normalize cho ShippingCore. Thay thế hoàn toàn rate path legacy
(`Secomm_GiaoHangNhanh` `Model/Carrier/GHN.php`).

### Expected Behavior

1. Flow: `CarrierAddressHandoffServiceInterface::handoff(dest, GhnAddressCapability)` →
   `isApplicable()` + `getResolvedAddress()` EXACT/MAPPED (AMBIGUOUS/UNMAPPED → outcome
   UNAVAILABLE reason `CANONICAL_UNRESOLVED` — fail closed, không method) → mapping triple →
   `v2/shipping-order/available-services` (from_district_id = origin GHN legacy mapping qua cùng
   chain từ `OriginProviderInterface`) → `v2/shipping-order/fee` (`to_district_id`, `to_ward_code`,
   `service_type_id`, weight, COD, dimensions).
2. Outcome: SUCCESS → `CarrierRateInterface` (amount, currency — VND normalize base currency,
  preserve logic legacy 1.1.1); UNAVAILABLE/TECHNICAL_FAILURE + `ShippingFailureReason`;
  Provider exception translate ở boundary; **KHÔNG `$shippingFee=10`, KHÔNG suppress failure,
  KHÔNG cache fee**.
3. Service: `GhnServiceResolver` map GHN `service_type_id` → ShippingCore service level
   (`CarrierServiceLevelInterface` qua registry) — không hard-code GHN service ID như identity.
4. Leadtime: cùng triple → normalized ETA string cho ShippingCore expose.
5. Đo 2-call/collectRates; chỉ cache available-services (short TTL, fail-open nếu QC cần — R15).

### Constraints / Rules

- Mọi provider failure phải classify + propagate (AC-RATE-003/004) — cấm catch-all trả null như legacy.
- Không expose GHN service name/ID ra storefront như identity (AC-RATE-005).
- Không sửa ShippingCore/VietNamAddress trong task này (slice contracts là task riêng).
- Provider timeout config `secomm_ghn/general/*` phải được tôn trọng.

### Out of Scope

Create/cancel/return (GHN-D) · webhook (GHN-E) · E-B v2 implementation · VietMap · fallback policy
decisions (thuộc ShippingCore E-SL2) · checkout UI.

### Acceptance Criteria

- AC-C1: địa chỉ resolve được → fee thật trả về storefront; AMBIGUOUS/UNMAPPED → method ẩn +
  reason log (không exception checkout).
- AC-C2: GHN timeout/auth lỗi → outcome TECHNICAL_FAILURE/UNAVAILABLE chuẩn, không fake price.
- AC-C3: service-level mapping test; leadtime test; currency normalize test.
- AC-C4: phpunit scoped green + QC L3 checkout (rate hiển thị/hide đúng trên store thật staging).

## Progress Log

- **2026-09-14 — QC L3 (GHN-C slice 2): pipeline PROVEN end-to-end; 2 upstream defects phát hiện → GHN-C PARTIAL (chưa close).**
  Preflight PASS (active=1, sandbox shop 200537 token masked, weight_unit=kgs website-1, dataset v1.0.0,
  audit 2 scheme production_ready 100%). Runtime evidence (`.ai/evidence/TASK-FMBBSD/qc-l3-checkout.md`):
  **Domain path PASS**: 2025 source identity (VNA25-F2118484F0) → ShippingCore handoff per-op RATE
  (targetScheme PRE_2025, representations=[UNIT_ID]) → canonical snapshot VNAP25-01965FA7E0 (chỉ Secomm
  identity, 0 provider id) → Stage-2 APPROVED → GHN sandbox fee **214,500 VND THẬT** (1 calculate_fee
  HTTP 200) — snapshot purity + efficiency (1 call) + method identity (unit) đều PASS. Fail-closed
  matrix PASS tại runtime: heavy 45kg → GHN_HEAVY_PARCEL_UNSUPPORTED 0 HTTP; weight-unit 'stones'
  (website scope) → INVALID_CONFIGURATION warning, restored; AMBIGUOUS (Kỳ Lừa 5 candidates) →
  CANONICAL_UNRESOLVED 0 HTTP; mapping flip → PROVIDER_MAPPING_MISSING ≠ CANONICAL_UNRESOLVED (§7
  boundary runtime-proven); postcode: **NOT APPLICABLE cho VN** (VN zip-optional trong Magento core
  `optional_zip_countries`); logging 0 token/PII. **QC CASE 1 (real Quote path) PARTIAL — 2 upstream
  defects (REPORT, KHÔNG patch trong GHN)**: (U1) VietNamAddress name-bridge chết — mọi level-2 unit
  parent_code=NULL (seed 2025 link qua region_code; getChildren khớp parent_code) → mọi address theo
  tên → UNMAPPED — chặn cả GHTK name path, owner Secomm_VietNamAddress; (U2) core RateRequest không
  mang dest city node id → id-bridge không đi qua real collectRates được — Magento core limitation +
  ShippingCore scalar-builder gap đã ghi. Regression: Ghn 174/0F/0E; ShippingCore+VietNamAddress
  400/0F/0E; grep gates 0; production code 0 đổi trong QC; config restore checklist hoàn tất
  (weight_unit/showmethod/debug/mapping row). **Verdict: GHN-C QC PARTIAL — BLOCKERS REMAIN (upstream
  U1/U2); closure chờ TL quyết sau khi U1 có owner/task.**

- **2026-09-14 — slice 2 r2: TL review fixes applied (approved-with-fixes → addressed).** TL verdict:
  standalone RATE carrier / per-op handoff / Stage-2 mapping / outcome / no-fallback-VietMap /
  heavy fail-closed = APPROVED; 3 MUST + 1 governance đã xử lý: (1) **processAdditionalValidation**
  — đọc toàn bộ method vendor: ngoài weight trap còn có **zip-code gate** + decimal-weight expansion
  + showmethod rendering → viết lại parent-parity trừ trap (per-item max_package_weight chỉ chạy
  khi merchant cấu hình; zip gate GIỮ; error rendering giữ) + 3 test khóa behavior (unconfigured
  không chặn / configured vẫn enforce / zip gate giữ nguyên); (2) **weight-unit missing → FAIL-CLOSED**
  `LocalizedException` → UNAVAILABLE/`INVALID_CONFIGURATION` (carrier catch riêng, warning log với
  reason) — bỏ assume-kgs; (3) **dimensions OMIT hoàn toàn ở RATE** (bỏ assumption cm — chỉ gửi khi
  có parcel contract unit-aware upstream); (4) **GhnServiceLevel REMOVED** — không hardcode STANDARD
  làm intrinsic identity; service-level = config-driven/upstream mapping tại composition task (E-SL).
  Governance ghi nhận: VND-only accepted cho release VN-scope (lâu dài = conversion boundary,
  không hide carrier); `enabled`→`active` manual re-enable chấp nhận được (0.3.x chưa từng commit →
  không có production adoption; nếu có project adopt 0.3.x sau này → data-patch nhẹ). **Backlog rõ:
  GHN type-5 RATE support (item payload) — limitation của slice, không phải vĩnh viễn.** Tests:
  **174 / 210,438 / 0F / 0E** (+3 tests). Lưu ý QC: zip gate parent-parity = cart thiếu postcode ở
  country zip-required sẽ ẩn GHN (giống mọi carrier chuẩn Magento) — verify flow postcode Launchpad.

- **2026-09-14 — slice 2 (Magento Carrier RATE wiring trên ShippingCore v5) dev-complete, chờ TL review.**
  `Model\Carrier\Ghn` (extends `AbstractCarrierOnline`, `$_code='secomm_ghn'`): active gate → VN guard →
  VND-only base-currency guard → `GhnRateRequestMapper` (weight gram theo store `general/locale/
  weight_unit`, kgs/lbs, missing→kgs+warning; dims >0 giả định cm; KHÔNG COD inference) →
  `GhnRateCalculator` → `CarrierRateOutcome` → `RateResult\Method` | no method; catch-all → error log
  → no method. Override `processAdditionalValidation()` (trap vendor `max_package_weight` = 0.0 khi
  không config → parent validation ẨN carrier với mọi giỏ hàng có item weight>0) +
  `isTrackingAvailable`/`isShippingLabelsAvailable`=false + `_doShipmentRequest` throw (rate-only).
  **Per-op migration (v5)**: `GhnAddressCapability` → `CarrierOperationAddressCapabilityInterface`
  (RATE=PRE_2025+[UNIT_ID]; CREATE=2025+[TEXT_NAME] khai báo thôi); calculator dùng
  `handoffContextForOperation(context, capability, RATE)`; context build qua bridge module-private
  `GhnRateCapabilityAdapter` — **ShippingCore gap REPORT TL**: chưa có public per-op scalar builder
  (`buildForOperation` chỉ nhận Quote\Address; `OperationCapabilityAdapter` @internal) → 1 shim tạm.
  **Heavy guard**: service_type 5 (≥20kg) → UNAVAILABLE `GHN_HEAVY_PARCEL_UNSUPPORTED` TRƯỚC API
  (sandbox: type-5 cần items; cấm gửi invalid request; cấm downgrade). Method identity ổn định
  carrier=method=`secomm_ghn` (§19). `GhnServiceLevel` declaration `['STANDARD']` (không wire registry
  — composition thuộc Launchpad). Config: `enabled` → `active` chuẩn Magento + 8 field display chuẩn
  (owner cần bật lại toggle 1 lần); module.xml += Backend/Config/Directory/Shipping; i18n 2 file.
  Tests: **Secomm_Ghn 171 / 210,436 / 0F / 0E / 0S** (carriers ×9, mapper ×6, capability matrix ×7,
  adapter ×1, service level ×1; calculator per-op + heavy; ConfigTest bỏ enabled). Compile OK;
  grep gates 0 hit (Mageplaza/TableRate/Fallback/VietMap/GiaoHangNhanh/Ghtk; `service_id` 0 runtime).
  **Deviations REPORT TL**: (1) VND-only slice — legacy convert bảng tỉ giá, deferred (user-approved);
  (2) dims giả định cm khi RateRequest có >0 (user-approved); (3) weight-unit store-level, missing→
  kgs+warning (core default `lbs` là trap phóng đại 2.2×); (4) **slice 1 chạy thiếu plan artifact**
  (đã bổ sung `.ai/plans/TASK-FMBBSD-implementation-plan.md` — plan slice này). Snapshot boundary:
  consume handoff → `ResolvedShippingAddress` (scheme+unit_code canonical-only) — `CanonicalResolution
  SnapshotInterface` contract-only chưa có producer (ShippingCore-owned, persistence task riêng); consumer
  GHN không đổi khi persistence lands. QC L3 checkout trên store thật chờ TL (Tier-2).

- **2026-09-11 — slice 1 (RATE provider calculator) dev-complete, chờ TL review.** PHASE A:
  current-docs audit 12 trang (developer.ghn.vn/en/docs) → **GHN API Contract Matrix**
  (`.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md`, 10+ discrepancy rows + error
  classification matrix) + **sandbox validation live** sau khi owner cấu hình credentials
  (`sandbox-validation.md`): fee service_type_id-only ✓, `is_new_to_address=true` + district rỗng ✓
  (order thật trên sandbox, đã cancel sạch), idempotency client_order_code ✓, payment_type_id=1 ✓,
  type-5 cần items (deferred), **available-services docs ≠ gateway (mọi naming bị reject) → không
  dùng trong runtime**. Blocker cũ (E-B v2/VietMap/NO_MATCH) re-frame bởi architecture v3 §5.1/§23:
  carrier KHÔNG invoke external resolver — AMBIGUOUS/UNMAPPED → UNAVAILABLE tại adapter;
  ShippingCore snapshot persistence = task riêng (ShippingCore-owned). PHASE B implemented:
  `Model/Rate/{GhnParcel,GhnRateQuery,GhnRateCalculator}` (handoff E-C0 → GhnMappingResolver
  Stage-2 APPROVED-only → fee `service_type_id`+`cod_value` → CarrierRateOutcome E-C1;
  PROVIDER_MAPPING_MISSING ≠ CANONICAL_UNRESOLVED; malformed fee response = TECHNICAL, không
  bao giờ zero-rate) + config `origin_district_id` + **P0 fix payment_type default 2→1**
  (double-charge; DB row merchant hiện vẫn 2 — khuyến nghị TL đổi) + **stale>0 chặn
  production_ready** (architecture §38) + test. Deviations-from-mini-spec (có chủ đích, REPORT TL):
  (1) available-services không gọi trên critical path (contract không yêu cầu + sandbox binding
  chưa rõ); (2) fee KHÔNG retry (POST business op — GET-only retry giữ cho master-data);
  (3) leadtime DEFERRED (CarrierRateInterface chưa có consumer cho ETA — §28 hard stop);
  (4) service-level mapping DEFERRED sang slice Magento Carrier wiring (E-SL1 cần registry
  composition; GHN current contract không có speed-tier nên không map 1↔1 theo type).
  Tests: Secomm_Ghn 148 / 210,380 / 0F / 0E / 0S (+19 mới). Sandbox shop để sạch (đã cancel).
