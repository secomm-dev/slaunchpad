---
id: TASK-WY6WP5
type: task
project_code: SLP
parent: {type: feature, id: FEAT-QA23PZ}
legacy_ids: []
title: 'Shipping Coverage P1 Admin UX — CoverageTarget abstraction (CARRIER-only, METHOD reserved), Add/Configure/Edit/Reset lifecycle, core ui-select selector rebuild, zone form geography-only'
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: medium
status: in_review  # dev-complete 2026-09-23; ShippingCore 545/1420 OK; integration proof PASS; admin smoke 27/27 PASS (evidence .ai/evidence/TASK-WY6WP5/)
created: 2026-09-23
updated: 2026-09-23
plan: ../../plans/TASK-WY6WP5-implementation-plan.md
decisions: [DEC-TASKWY6WP5-001]
decision_assessment: minor
decision_refs: [DEC-TASKWY6WP5-001, DEC-FEATQA23PZ-001]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-QA23PZ][TASK-WY6WP5] Shipping Coverage P1 Admin UX — CoverageTarget abstraction (CARRIER-only, METHOD reserved), Add/Configure/Edit/Reset lifecycle, core ui-select selector rebuild, zone form geography-only

TL directive "Shipping Coverage P1 Admin UX + Future Method-Ready Coverage Target" (36 section,
2026-09-23) — rework bề mặt admin của TASK-G3K9V2 (đang in_review) trên NỀN runtime frozen
v10. KHÔNG đổi runtime eligibility semantics, KHÔNG đổi config paths, KHÔNG đổi GHN runtime.

## Mini Spec

### Goal

1. IA admin: menu `Secomm → Shipping → [Shipping Zones, Shipping Coverage]` (node cha
   "Shipping" — user chốt 2026-09-23); rename user-facing "Carrier Coverage" → "Shipping
   Coverage" (ACL ids + route giữ ổn định).
2. Abstraction config-layer trong ShippingCore: `CoverageTargetType` (P1 = CARRIER,
   METHOD reserved), `CoverageTargetIdentity` (immutable VO type+code),
   `CoverageTargetRegistry` (DI opt-in `{type, code, label}`; GHN registers
   `CARRIER:secomm_ghn`). Registration ≠ persisted coverage — không auto-create.
3. Shipping Coverage listing (UiComponent grid): Target / Type / Configuration Status /
   Availability / Zones / Action — rows = registered targets (không fabricate persisted
   config); nút Add Coverage; lifecycle Configure (Not Configured → form tạo) / Edit
   (Configured) / Reset to Defaults (xoá 4 paths DEFAULT; scoped rows warning).
4. Selector rebuild trên core `ui-select` (recipe `new_category_form.xml`): Province(s),
   Included Wards (subclass `ward-select` giữ cascade AJAX), Coverage Zones, Create-Carrier
   select. XOÁ custom `searchable-multiselect` component + template (chưa từng render được
   trong browser thật — root cause client-side, không debug mù).
5. Zone form geography-only: BỎ "Carriers Referencing This Zone" (`assigned_carriers` +
   `CarrierOptions`); `CarrierZoneIndex` + `ZoneReferenceGuard` giữ nguyên cho delete/
   mass-delete/disable protection.

### Expected Behavior

- Grid row "Not Configured" ⇔ `hasExplicitConfig() = false` (đọc thẳng `core_config_data`
  mọi scope); "Configured" ⇔ có row bất kỳ scope. Không config → runtime defaults
  (`destination_scope` missing → ALL) — reader/evaluator không đổi.
- Create: "Applies To: Carrier" fixed; Carrier = searchable single select từ registry
  MINUS configured targets; không nhập tay code; Save duplicate-guard
  (`is_create=1` + `hasExplicitConfig` → reject, adapter không gọi).
- Save ghi 4 paths `carriers/<code>/*` DEFAULT scope qua
  `CarrierCoverageConfigAdapter` (rename từ `PolicyConfig`, paths byte-identical) +
  `cleanType('config')`. Edit reload persisted values; carrier readonly.
- Availability = ALL → zones ẩn qua `switcherConfig`; zone modes → hiện + Validator
  require ≥1 zone tồn tại (deleted reject, disabled OK) — admin chặt hơn runtime giữ
  nguyên (ALL_EXCEPT + 0 zone → runtime ≡ ALL nhưng admin reject).
- Disabled zone được tham chiếu: visible "— Disabled" trong picker, không select mới được
  (`ReferencableZoneCodes` — giữ nguyên); disabled zone vẫn không match runtime.
- Reset: xoá 4 rows DEFAULT; còn rows WEBSITE/STORE → warning + status vẫn Configured;
  target vẫn registered. Delete/mass-delete protection all-scope không đổi.
- METHOD entry (nếu DI sai): Edit/Save refuse với message "reserved" rõ ràng; registry
  chỉ normalize data.

### Constraints / Rules

- FROZEN: `DestinationScope`, `CarrierEligibility`, `CanonicalZone`,
  `CarrierRateExecutionService`, `ServiceLevelRateOrchestrator`, GHN runtime,
  `RateSourceMode`, `AddressResolutionPolicy`, config paths, zero DB schema change.
- Không METHOD runtime/UI (chỉ reserved constant + document-only inheritance rule §24).
- Không auto-discovery carrier Magento; GHTK KHÔNG register (opt-in).
- Không lib JS mới; admin = Luma/RequireJS; không đụng GHN system.xml fields (chỉ text
  pointer note + di.xml registry metadata).
- ACL `Secomm_ShippingCore::carrier_coverage[_manage]` + route
  `secomm_shippingcore/coverage/*` giữ nguyên; menu/labels đổi chữ.
- ShippingCore không hardcode GHN; P1 DEFAULT scope only (không scope selector UI).

### Out of Scope

METHOD-level execution/registration; website/store scope UI; multi-country; Excluded
Wards UI (backend `exclude_ward_codes` preserve-on-save giữ nguyên từ TASK-G3K9V2);
"Used By" column trên zone grid; auto-create coverage từ registration.

### Acceptance Criteria

Directive §30 A–N: (A) registered target trong list; (B) unregistered vắng mặt; (C) không
config → Not Configured + effective All Vietnam; (D) Add options từ registry; (E)
configured excluded khỏi create; (F) duplicate type+code reject; (G) Save persist đúng
4 paths cũ; (H) Edit reload; (I) ALL → zones không bắt buộc; (J/K) zone modes require
≥1; (L) enabled zones selectable; (M) disabled referenced visible-not-selectable;
(N) deleted zone reference reject. Cộng: zone form structurally không còn
assigned_carriers; listing framework calls không fatal; selector render được trong
browser thật (§32 checklist) hoặc khai báo trung thực
`ADMIN_SMOKE = BLOCKED_BY_ENVIRONMENT`; §31 integration proof CLI (SELECTED_ZONES +
HCM_INNER qua adapter → evaluator eligible/ineligible đúng → reset → default ALL).
Regression: ShippingCore + Ghn + Ghtk + VietNamAddress suites; `setup:di:compile`;
`bin/project-ai-validate --check-specs --check-records --check-identity`.

## Implementation summary (2026-09-23 — dev-complete)

- Deliverables khớp plan: abstraction + registry thay CarrierRegistry; adapter rename với
  `load/hasExplicitConfig/reset/nonDefaultScopeRows` (paths byte-identical); listing grid +
  NewAction/Edit/Save(duplicate guard)/Reset; form rework với switcherConfig; zone form
  geography-only; ward-select subclass; menu Secomm → Shipping; Ghn di.xml metadata +
  CoveragePointer text.
- **3 root-cause render defect của TASK-G3K9V2 đã fix + browser-verified** (chi tiết
  `.ai/evidence/TASK-WY6WP5/evidence.md` §1): (a) meta injection dead-code trên 2.4.8 —
  framework không truyền meta vào form DataProvider, guard isset cũ không bao giờ chạy →
  ward optionsUrl chưa từng được inject; (b) form hydration record-key contract
  (`$data[$requestParamValue]`) — key composite cũ làm client rơi về defaults; (c) ward
  import-link `data.general.*` sai vì provider data flat.
- Suites: ShippingCore 545/1420 (+51), Ghn 387, Ghtk 237, VN 195, Launchpad 135 — 0 FAIL;
  compile OK; validator records 0 FAIL/WARN (89 FAIL pre-existing ngoài scope).
- Integration proof CLI §31 PASS; admin smoke §32 **27/27 PASS** (không BLOCKED_BY_ENVIRONMENT).
- DB restored: 0 rows `carriers/secomm_ghn/*`; zones NOITHANH + HCM_INNER (giữ); smoke user
  đã xoá; zone test artifact đã xoá.
